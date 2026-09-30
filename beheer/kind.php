<?php
/*
 * Kinddossier en kindvolgsysteem. Team: alle gegevens, aanwezigheid en
 * observaties per ontwikkelgebied. Ouder: gegevens aanpassen en de gedeelde observaties lezen.
 */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_login();
$kind = kind_met_toegang(get_int('id'));
$team = is_team($gebruiker);
$terug = 'kind.php?id=' . (int) $kind['id'];
$fouten = [];

if (is_post()) {
    csrf_controleer();
    $actie = invoer('actie');

    if ($actie === 'gegevens') {
        $velden = [
            'school' => mb_substr(invoer('school'), 0, 120),
            'bijzonderheden' => mb_substr(invoer('bijzonderheden'), 0, 2000),
            'ophaalpersonen' => mb_substr(invoer('ophaalpersonen'), 0, 500),
            'foto_toestemming' => invoer('foto_toestemming') === '1' ? 1 : 0,
        ];
        if ($team) {
            $velden['voornaam'] = invoer('voornaam');
            $velden['achternaam'] = invoer('achternaam');
            $velden['geboortedatum'] = invoer('geboortedatum');
            $velden['groep_id'] = invoer('groep') === '' ? null : (int) invoer('groep');
            $velden['actief'] = invoer('actief') === '1' ? 1 : 0;
            if ($velden['voornaam'] === '') {
                $fouten['voornaam'] = 'Vul de voornaam in.';
            }
            if (!geldige_datum($velden['geboortedatum'])) {
                $fouten['geboortedatum'] = 'Vul een geldige geboortedatum in.';
            }
            if ($velden['groep_id'] !== null && !groep($velden['groep_id'])) {
                $fouten['groep'] = 'Deze groep bestaat niet.';
            }
        }
        if (!$fouten) {
            $sets = implode(', ', array_map(fn ($v) => "$v = ?", array_keys($velden)));
            q("UPDATE kinderen SET $sets WHERE id = ?", [...array_values($velden), $kind['id']]);
            if (!$team) {
                q("INSERT INTO berichten (ouder_id, afzender_id, kind_id, soort, tekst, gelezen_ouder, gelezen_team, aangemaakt_op) VALUES (?, ?, ?, 'systeem', ?, 1, 0, ?)", [
                    $gebruiker['id'], $gebruiker['id'], $kind['id'], 'De gegevens van ' . $kind['voornaam'] . ' zijn aangepast (school, bijzonderheden, ophalen of foto-toestemming).', nu(),
                ]);
            }
            if ($team && !$velden['actief']) {
                // Gestopt: toekomstige dagen vrijgeven
                foreach (rijen("SELECT * FROM inschrijvingen WHERE kind_id = ? AND datum > ? AND status IN ('bevestigd', 'wachtlijst')", [$kind['id'], vandaag()]) as $inschrijving) {
                    meld_af($inschrijving);
                }
            }
            log_actie('Kindgegevens gewijzigd', $kind['voornaam']);
            flash('succes', 'De gegevens zijn opgeslagen.');
            redirect($terug);
        }
    }

    if ($actie === 'observatie' && $team) {
        $datum = invoer('datum');
        $gebied = invoer('gebied');
        $tekst = invoer('tekst');
        if (!geldige_datum($datum) || $datum > vandaag()) {
            $fouten['obs_datum'] = 'Vul een geldige datum in (niet in de toekomst).';
        }
        if (!array_key_exists($gebied, ONTWIKKELGEBIEDEN)) {
            $fouten['obs_gebied'] = 'Kies een ontwikkelgebied.';
        }
        if ($tekst === '') {
            $fouten['obs_tekst'] = 'Schrijf je observatie op.';
        }
        if (!$fouten) {
            $gedeeld = invoer('gedeeld') === '1' ? 1 : 0;
            q('INSERT INTO observaties (kind_id, auteur_id, datum, gebied, tekst, gedeeld, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, ?)', [
                $kind['id'], $gebruiker['id'], $datum, $gebied, mb_substr($tekst, 0, 5000), $gedeeld, nu(),
            ]);
            if ($gedeeld) {
                systeembericht((int) $kind['ouder_id'], 'Er staat een nieuwe observatie over ' . $kind['voornaam'] . ' voor je klaar. Je vindt hem bij "Mijn kinderen".', (int) $kind['id']);
            }
            log_actie('Observatie toegevoegd', $kind['voornaam'] . ' (' . $gebied . ')');
            flash('succes', 'De observatie is opgeslagen' . ($gedeeld ? ' en gedeeld met de ouders.' : '.'));
            redirect($terug . '#kindvolg');
        }
    }

    if ($actie === 'observatie_delen' && $team) {
        $obs = rij('SELECT * FROM observaties WHERE id = ? AND kind_id = ?', [(int) invoer('observatie'), $kind['id']]);
        if ($obs) {
            q('UPDATE observaties SET gedeeld = 1 - gedeeld WHERE id = ?', [$obs['id']]);
            flash('succes', $obs['gedeeld'] ? 'De observatie is niet meer zichtbaar voor de ouders.' : 'De observatie is gedeeld met de ouders.');
        }
        redirect($terug . '#kindvolg');
    }

    if ($actie === 'observatie_verwijderen' && $team) {
        $obs = rij('SELECT * FROM observaties WHERE id = ? AND kind_id = ?', [(int) invoer('observatie'), $kind['id']]);
        if ($obs && ((int) $obs['auteur_id'] === (int) $gebruiker['id'] || is_beheerder($gebruiker))) {
            q('DELETE FROM observaties WHERE id = ?', [$obs['id']]);
            log_actie('Observatie verwijderd', $kind['voornaam']);
            flash('succes', 'De observatie is verwijderd.');
        }
        redirect($terug . '#kindvolg');
    }
}

$ouder = rij('SELECT * FROM gebruikers WHERE id = ?', [$kind['ouder_id']]);
$observaties = rijen(
    'SELECT o.*, g.naam AS auteur FROM observaties o LEFT JOIN gebruikers g ON g.id = o.auteur_id WHERE o.kind_id = ?' . ($team ? '' : ' AND o.gedeeld = 1') . ' ORDER BY o.datum DESC, o.id DESC',
    [$kind['id']]
);
$perGebied = array_fill_keys(array_keys(ONTWIKKELGEBIEDEN), 0);
foreach ($observaties as $obs) {
    $perGebied[$obs['gebied']] = ($perGebied[$obs['gebied']] ?? 0) + 1;
}
$komend = rijen("SELECT * FROM inschrijvingen WHERE kind_id = ? AND datum >= ? AND status != 'afgemeld' ORDER BY datum LIMIT 6", [$kind['id'], vandaag()]);
$geweest = rijen("SELECT * FROM inschrijvingen WHERE kind_id = ? AND datum < ? ORDER BY datum DESC LIMIT 10", [$kind['id'], vandaag()]);
$fotos = fotos_van([(int) $kind['id']], 8);

pagina_begin(kindnaam($kind), 'kinderen.php');
echo '<a class="terug-link" href="kinderen.php">' . icoon('back') . ($team ? 'Alle kinderen' : 'Mijn kinderen') . '</a>';
pagina_kop(kindnaam($kind), ($kind['groepnaam'] ? 'Groep ' . e($kind['groepnaam']) : 'Nog geen groep') . (($l = leeftijd($kind['geboortedatum'])) !== null ? ' · ' . $l . ' jaar' : ''),
    $team ? '<a class="btn btn--secondary btn--small" href="berichten.php?ouder=' . (int) $kind['ouder_id'] . '">' . icoon('chat') . 'Bericht aan ouder</a>' : '<a class="btn btn--small" href="agenda.php?kind=' . (int) $kind['id'] . '">' . icoon('calendar') . 'Agenda</a>');
?>
<?= foutensamenvatting($fouten) ?>
<div class="kolommen">
  <div class="stapel">
    <section class="panel" aria-labelledby="gegevens-titel">
      <h2 id="gegevens-titel">Gegevens</h2>
      <dl class="gegevens">
        <dt>Geboortedatum</dt><dd><?= e(datum_nl($kind['geboortedatum'])) ?></dd>
        <dt>School</dt><dd><?= e($kind['school'] ?: '-') ?></dd>
        <dt>Bijzonderheden</dt><dd><?= $kind['bijzonderheden'] !== '' ? '<span class="let-op">' . icoon('alert') . '<span>' . e($kind['bijzonderheden']) . '</span></span>' : 'Geen' ?></dd>
        <dt>Mag opgehaald worden door</dt><dd><?= e($kind['ophaalpersonen'] ?: 'Alleen de ouders') ?></dd>
        <dt>Groepsfoto's</dt><dd><?= $kind['foto_toestemming'] ? 'Mag op groepsfoto\'s' : 'Niet op groepsfoto\'s (alleen eigen foto\'s naar eigen ouders)' ?></dd>
<?php if ($team): ?>
        <dt>Ouder</dt><dd><a href="ouder.php?id=<?= (int) $ouder['id'] ?>"><?= e($ouder['naam']) ?></a><br><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $ouder['telefoon'])) ?>"><?= e($ouder['telefoon']) ?></a> · <a href="mailto:<?= e($ouder['email']) ?>"><?= e($ouder['email']) ?></a></dd>
<?php endif; ?>
      </dl>
      <details class="uitklap"<?= isset($fouten['voornaam']) || isset($fouten['geboortedatum']) ? ' open' : '' ?>>
        <summary><?= icoon('gear') ?>Gegevens wijzigen</summary>
        <form class="form" method="post" action="<?= e($terug) ?>">
          <?= csrf_veld() ?>
          <input type="hidden" name="actie" value="gegevens">
<?php if ($team): ?>
          <div class="form__row">
            <div class="field"><label for="voornaam">Voornaam</label><input id="voornaam" name="voornaam" required value="<?= e($kind['voornaam']) ?>"<?= aria_fout($fouten, 'voornaam') ?>><?= veldfout($fouten, 'voornaam') ?></div>
            <div class="field"><label for="achternaam">Achternaam</label><input id="achternaam" name="achternaam" value="<?= e($kind['achternaam']) ?>"></div>
          </div>
          <div class="form__row">
            <div class="field"><label for="geboortedatum">Geboortedatum</label><input id="geboortedatum" name="geboortedatum" type="date" required value="<?= e($kind['geboortedatum']) ?>"<?= aria_fout($fouten, 'geboortedatum') ?>><?= veldfout($fouten, 'geboortedatum') ?></div>
            <div class="field"><label for="groep">Groep</label><select id="groep" name="groep"><?= groep_opties($kind['groep_id'] !== null ? (int) $kind['groep_id'] : null) ?></select></div>
          </div>
<?php endif; ?>
          <div class="field"><label for="school">Basisschool</label><input id="school" name="school" value="<?= e($kind['school']) ?>"></div>
          <div class="field"><label for="bijzonderheden">Allergieën, medicijnen of bijzonderheden</label><textarea id="bijzonderheden" name="bijzonderheden" rows="3"><?= e($kind['bijzonderheden']) ?></textarea></div>
          <div class="field"><label for="ophaalpersonen">Wie mag <?= e($kind['voornaam']) ?> ophalen, behalve de ouders?</label><input id="ophaalpersonen" name="ophaalpersonen" value="<?= e($kind['ophaalpersonen']) ?>" placeholder="Bijvoorbeeld: oma Els, buurvrouw Fatima"></div>
          <div class="field"><div class="consent"><input id="foto_toestemming" name="foto_toestemming" type="checkbox" value="1"<?= $kind['foto_toestemming'] ? ' checked' : '' ?>><label for="foto_toestemming">Mag op groepsfoto's die ook naar andere ouders gaan</label></div></div>
<?php if ($team): ?>
          <div class="field"><div class="consent"><input id="actief" name="actief" type="checkbox" value="1"<?= $kind['actief'] ? ' checked' : '' ?>><label for="actief">Komt naar de BSO (uitvinken als het kind stopt; toekomstige dagen worden dan afgemeld)</label></div></div>
<?php endif; ?>
          <div class="form__acties"><button class="btn" type="submit">Opslaan</button></div>
        </form>
      </details>
    </section>

    <section class="panel" id="kindvolg" aria-labelledby="kindvolg-titel">
      <div class="panel__kop"><h2 id="kindvolg-titel">Kindvolgsysteem</h2><?php if (!$team): ?><span class="muted">Wat de begeleiders met je delen</span><?php endif; ?></div>
<?php if ($team): ?>
      <ul class="legenda" aria-label="Aantal observaties per ontwikkelgebied">
<?php foreach (ONTWIKKELGEBIEDEN as $sleutel => $label): ?>
        <li class="badge gebied-<?= e($sleutel) ?>" style="border-left: 4px solid var(--gebied-kleur)"><?= e($label) ?>: <?= $perGebied[$sleutel] ?></li>
<?php endforeach; ?>
      </ul>
      <details class="uitklap"<?= array_intersect_key($fouten, ['obs_datum' => 1, 'obs_gebied' => 1, 'obs_tekst' => 1]) ? ' open' : '' ?>>
        <summary><?= icoon('plus') ?>Observatie toevoegen</summary>
        <form class="form" method="post" action="<?= e($terug) ?>#kindvolg">
          <?= csrf_veld() ?>
          <input type="hidden" name="actie" value="observatie">
          <div class="form__row">
            <div class="field"><label for="obs_datum">Datum</label><input id="obs_datum" name="datum" type="date" max="<?= vandaag() ?>" required value="<?= e(invoer('datum', vandaag())) ?>"<?= aria_fout($fouten, 'obs_datum') ?>><?= veldfout($fouten, 'obs_datum') ?></div>
            <div class="field"><label for="obs_gebied">Ontwikkelgebied</label><select id="obs_gebied" name="gebied"<?= aria_fout($fouten, 'obs_gebied') ?>>
<?php foreach (ONTWIKKELGEBIEDEN as $sleutel => $label): ?>
              <option value="<?= e($sleutel) ?>"<?= invoer('gebied') === $sleutel ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
            </select><?= veldfout($fouten, 'obs_gebied') ?></div>
          </div>
          <div class="field"><label for="obs_tekst">Wat zag je?</label><textarea id="obs_tekst" name="tekst" rows="4" required placeholder="Beschrijf wat je zag, zo feitelijk mogelijk. Bijvoorbeeld: Durfde vandaag voor het eerst mee te doen met trefbal en moedigde anderen aan."<?= aria_fout($fouten, 'obs_tekst') ?>><?= e(invoer('tekst')) ?></textarea><?= veldfout($fouten, 'obs_tekst') ?></div>
          <div class="field"><div class="consent"><input id="obs_gedeeld" name="gedeeld" type="checkbox" value="1"><label for="obs_gedeeld">Delen met de ouders (ze krijgen een berichtje)</label></div></div>
          <div class="form__acties"><button class="btn" type="submit">Opslaan</button></div>
        </form>
      </details>
<?php endif; ?>
<?php if (!$observaties): ?>
      <p class="leeg"><?= $team ? 'Nog geen observaties. Leg hierboven vast wat je ziet: zo volg je de ontwikkeling van ' . e($kind['voornaam']) . '.' : 'Er zijn nog geen observaties gedeeld.' ?></p>
<?php else: ?>
      <ol class="tijdlijn">
<?php foreach ($observaties as $obs): ?>
        <li class="gebied-<?= e($obs['gebied']) ?>">
          <div class="tijdlijn__meta">
            <strong><?= e(ONTWIKKELGEBIEDEN[$obs['gebied']] ?? $obs['gebied']) ?></strong>
            <span><?= e(datum_nl($obs['datum'])) ?></span>
            <span><?= e($obs['auteur'] ?? '') ?></span>
<?php if ($team): ?>
            <span class="badge badge--<?= $obs['gedeeld'] ? 'bevestigd' : 'afgemeld' ?>" style="text-decoration: none"><?= $obs['gedeeld'] ? 'Gedeeld met ouders' : 'Alleen team' ?></span>
<?php endif; ?>
          </div>
          <p><?= e($obs['tekst']) ?></p>
<?php if ($team): ?>
          <div class="btn-group" style="margin-top: var(--space-2xs)">
            <form class="inline-form" method="post" action="<?= e($terug) ?>"><?= csrf_veld() ?><input type="hidden" name="actie" value="observatie_delen"><input type="hidden" name="observatie" value="<?= (int) $obs['id'] ?>"><button class="link-button" type="submit"><?= $obs['gedeeld'] ? 'Niet meer delen' : 'Delen met ouders' ?></button></form>
<?php if ((int) $obs['auteur_id'] === (int) $gebruiker['id'] || is_beheerder($gebruiker)): ?>
            <form class="inline-form" method="post" action="<?= e($terug) ?>" data-bevestig="Deze observatie verwijderen?"><?= csrf_veld() ?><input type="hidden" name="actie" value="observatie_verwijderen"><input type="hidden" name="observatie" value="<?= (int) $obs['id'] ?>"><button class="link-button" type="submit"><?= icoon('trash') ?>Verwijderen</button></form>
<?php endif; ?>
          </div>
<?php endif; ?>
        </li>
<?php endforeach; ?>
      </ol>
<?php endif; ?>
    </section>
  </div>

  <div class="stapel">
    <section class="panel" aria-labelledby="komend-titel">
      <h2 id="komend-titel">Binnenkort</h2>
<?php if (!$komend): ?>
      <p class="leeg">Geen dagen ingeschreven.</p>
<?php else: ?>
      <ul class="kindlijst">
<?php foreach ($komend as $rij): ?>
        <li class="kindrij"><span><?= e(ucfirst(datum_nl($rij['datum'], 'EEEE d MMMM'))) ?></span><?= status_badge($rij['status']) ?></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </section>
    <section class="panel" aria-labelledby="geweest-titel">
      <h2 id="geweest-titel">Aanwezigheid</h2>
<?php if (!$geweest): ?>
      <p class="leeg">Nog geen eerdere dagen.</p>
<?php else: ?>
      <div class="tabel">
        <table>
          <thead><tr><th scope="col">Dag</th><th scope="col">Status</th><th scope="col">Binnen</th><th scope="col">Opgehaald</th></tr></thead>
          <tbody>
<?php foreach ($geweest as $rij): ?>
            <tr><td><?= e(datum_kort($rij['datum'])) ?></td><td><?= status_badge($rij['status']) ?></td><td><?= e(tijd_nl($rij['aanwezig_om'])) ?></td><td><?= e(tijd_nl($rij['opgehaald_om'])) ?></td></tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php endif; ?>
    </section>
    <section class="panel" aria-labelledby="fotos-titel">
      <div class="panel__kop"><h2 id="fotos-titel">Foto's</h2><a href="fotos.php<?= $team ? '' : '?kind=' . (int) $kind['id'] ?>"><?= $team ? "Foto's delen" : "Alle foto's" ?></a></div>
<?php if (!$fotos): ?>
      <p class="leeg">Nog geen foto's.</p>
<?php else: ?>
      <ul class="fotogrid"><?php foreach ($fotos as $foto) { echo foto_kaart($foto); } ?></ul>
<?php endif; ?>
    </section>
  </div>
</div>
<?php
pagina_einde();
