<?php
/*
 * Kinddossier en kindvolgsysteem.
 * Team (binnen de eigen groepen): gegevens, aanwezigheid, observaties per ontwikkelgebied.
 * Met het recht klanten beheren: ook groep, verzorgers en het controleren van gezag.
 * Verzorger: inzien met het recht "dossier"; wijzigen en toestemming geven alleen
 * met gecontroleerd gezag.
 */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_login();
$kind = kind_met_toegang(get_int('id'), 'dossier');
$kind = ontsleutel_kolommen($kind, ['bijzonderheden', 'ophaalpersonen'], true);
$team = is_team($gebruiker);
if ($team) {
    vereis_recht('kinderen');
}
// Gegevens, gezag en verzorgers beheren: met het recht klanten beheren
$beheerder = team_mag('klanten_beheren', $gebruiker);
$magBeslissen = !$team && mag_namens_kind((int) $kind['id'], (int) $gebruiker['id']);
$terug = 'kind.php?id=' . (int) $kind['id'];
$onderwerp = 'kind:' . (int) $kind['id'];
$fouten = [];

if (is_post()) {
    csrf_controleer();
    $actie = invoer('actie');

    if ($actie === 'gegevens' && ($team || $magBeslissen)) {
        $velden = [
            'school' => mb_substr(invoer('school'), 0, 120),
            'bijzonderheden' => versleutel(mb_substr(invoer('bijzonderheden'), 0, 2000)),
            'ophaalpersonen' => versleutel(mb_substr(invoer('ophaalpersonen'), 0, 500)),
        ];
        // Toestemming voor groepsfoto's is een beslissing van wie gezag heeft (of wordt door de beheerder vastgelegd)
        if ($magBeslissen || $beheerder) {
            $velden['foto_toestemming'] = invoer('foto_toestemming') === '1' ? 1 : 0;
        }
        if ($beheerder) {
            $velden['voornaam'] = invoer('voornaam');
            $velden['achternaam'] = invoer('achternaam');
            $velden['geboortedatum'] = invoer('geboortedatum');
            $velden['groep_id'] = invoer('groep') === '' ? null : (int) invoer('groep');
            $velden['actief'] = invoer('actief') === '1' ? 1 : 0;
            $velden['gestopt_op'] = $velden['actief'] ? null : ($kind['gestopt_op'] ?: nu());
            if ($velden['voornaam'] === '') {
                $fouten['voornaam'] = 'Vul de voornaam in.';
            }
            if (!geldige_datum($velden['geboortedatum'])) {
                $fouten['geboortedatum'] = 'Vul een geldige geboortedatum in.';
            }
            if ($velden['groep_id'] !== null && (!groep($velden['groep_id']) || !team_mag_groep($velden['groep_id']))) {
                $fouten['groep'] = 'Kies een van de groepen waar je toegang toe hebt.';
            }
        }
        if (!$fouten) {
            $sets = implode(', ', array_map(fn ($v) => "$v = ?", array_keys($velden)));
            q("UPDATE kinderen SET $sets WHERE id = ?", [...array_values($velden), $kind['id']]);
            if (!$team) {
                melding_voor_team((int) $gebruiker['id'], 'De gegevens van ' . $kind['voornaam'] . ' zijn aangepast (school, bijzonderheden, ophalen of foto-toestemming).', (int) $kind['id']);
            }
            if ($beheerder && !$velden['actief']) {
                // Gestopt: toekomstige dagen vrijgeven
                foreach (rijen("SELECT * FROM inschrijvingen WHERE kind_id = ? AND datum > ? AND status IN ('bevestigd', 'wachtlijst')", [$kind['id'], vandaag()]) as $inschrijving) {
                    meld_af($inschrijving);
                }
            }
            log_actie('Kindgegevens gewijzigd', implode(', ', array_keys($velden)), null, 'wijziging', $onderwerp);
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
                $kind['id'], $gebruiker['id'], $datum, $gebied, versleutel(mb_substr($tekst, 0, 5000)), $gedeeld, nu(),
            ]);
            if ($gedeeld) {
                bericht_aan_verzorgers((int) $kind['id'], 'dossier', 'Er staat een nieuwe observatie over ' . $kind['voornaam'] . ' voor je klaar. Je vindt hem bij "Mijn kinderen".');
            }
            log_actie('Observatie toegevoegd', $gebied . ($gedeeld ? ', gedeeld' : ''), null, 'wijziging', $onderwerp);
            flash('succes', 'De observatie is opgeslagen' . ($gedeeld ? ' en gedeeld met de ouders.' : '.'));
            redirect($terug . '#kindvolg');
        }
    }

    if ($actie === 'observatie_delen' && $team) {
        $obs = rij('SELECT * FROM observaties WHERE id = ? AND kind_id = ?', [(int) invoer('observatie'), $kind['id']]);
        if ($obs) {
            q('UPDATE observaties SET gedeeld = 1 - gedeeld WHERE id = ?', [$obs['id']]);
            log_actie($obs['gedeeld'] ? 'Observatie niet meer gedeeld' : 'Observatie gedeeld', '', null, 'wijziging', $onderwerp);
            flash('succes', $obs['gedeeld'] ? 'De observatie is niet meer zichtbaar voor de ouders.' : 'De observatie is gedeeld met de ouders.');
        }
        redirect($terug . '#kindvolg');
    }

    if ($actie === 'observatie_verwijderen' && $team) {
        $obs = rij('SELECT * FROM observaties WHERE id = ? AND kind_id = ?', [(int) invoer('observatie'), $kind['id']]);
        if ($obs && ((int) $obs['auteur_id'] === (int) $gebruiker['id'] || $beheerder)) {
            q('DELETE FROM observaties WHERE id = ?', [$obs['id']]);
            log_actie('Observatie verwijderd', '', null, 'wijziging', $onderwerp);
            flash('succes', 'De observatie is verwijderd.');
        }
        redirect($terug . '#kindvolg');
    }

    /* ---------- Verzorgers en gezag (beheerder) ---------- */

    if ($beheerder && in_array($actie, ['gezag', 'rechten', 'ontkoppelen'], true)) {
        $koppeling = verzorger_koppeling((int) $kind['id'], (int) invoer('verzorger'));
        if (!$koppeling) {
            niet_gevonden();
        }
        $wie = 'gebruiker:' . $koppeling['gebruiker_id'];
        if ($actie === 'gezag') {
            $heeftGezag = invoer('gezag') === '1';
            $bron = array_key_exists(invoer('bron'), GEZAG_BRONNEN) ? invoer('bron') : '';
            if ($heeftGezag && $bron === '') {
                flash('fout', 'Kies waarmee je het gezag hebt gecontroleerd.');
                redirect($terug . '#verzorgers');
            }
            q('UPDATE kind_verzorgers SET gezag = ?, gezag_gecontroleerd_op = ?, gezag_gecontroleerd_door = ?, gezag_bron = ? WHERE kind_id = ? AND gebruiker_id = ?', [
                $heeftGezag ? 1 : 0, nu(), $gebruiker['id'], $heeftGezag ? $bron : '', $kind['id'], $koppeling['gebruiker_id'],
            ]);
            log_actie($heeftGezag ? 'Gezag gecontroleerd' : 'Vastgelegd: geen gezag', ($heeftGezag ? GEZAG_BRONNEN[$bron] : '') . ' (' . $wie . ')', null, 'wijziging', $onderwerp);
            flash('succes', 'Het gezag is vastgelegd.');
        } elseif ($actie === 'rechten') {
            $rechten = array_values(array_intersect((array) ($_POST['rechten'] ?? []), array_keys(RECHTEN)));
            koppel_verzorger((int) $kind['id'], (int) $koppeling['gebruiker_id'], invoer('relatie'), (bool) $koppeling['gezag'], $rechten);
            log_actie('Rechten verzorger gewijzigd', implode(', ', $rechten) . ' (' . $wie . ')', null, 'wijziging', $onderwerp);
            flash('succes', 'De rechten zijn opgeslagen.');
        } elseif ($actie === 'ontkoppelen') {
            if ((int) $koppeling['gebruiker_id'] === (int) $kind['ouder_id']) {
                flash('fout', 'De contracthouder kun je niet ontkoppelen. Zet eventueel de rechten uit.');
            } else {
                q('DELETE FROM kind_verzorgers WHERE kind_id = ? AND gebruiker_id = ?', [$kind['id'], $koppeling['gebruiker_id']]);
                log_actie('Verzorger ontkoppeld', $wie, null, 'wijziging', $onderwerp);
                flash('succes', 'De verzorger is ontkoppeld en ziet dit kind niet meer.');
            }
        }
        redirect($terug . '#verzorgers');
    }

    if ($beheerder && $actie === 'verzorger_toevoegen') {
        $naam = invoer('v_naam');
        $email = invoer('v_email');
        $rechten = array_values(array_intersect((array) ($_POST['rechten'] ?? []), array_keys(RECHTEN)));
        if ($naam === '' || !geldig_email($email)) {
            flash('fout', 'Vul de naam en een geldig e-mailadres van de verzorger in.');
            redirect($terug . '#verzorgers');
        }
        $bestaand = rij('SELECT * FROM gebruikers WHERE email = ?', [$email]);
        if ($bestaand && $bestaand['rol'] !== 'ouder') {
            flash('fout', 'Dit e-mailadres hoort bij een medewerker. Gebruik voor een verzorger een eigen, persoonlijk account.');
            redirect($terug . '#verzorgers');
        }
        $id = $bestaand ? (int) $bestaand['id'] : null;
        if (!$id) {
            q("INSERT INTO gebruikers (rol, status, naam, email, aangemaakt_op) VALUES ('ouder', 'actief', ?, ?, ?)", [$naam, $email, nu()]);
            $id = laatste_id();
            $link = maak_wachtwoordlink($id, 72);
            stuur_mail($email, 'Je account bij Sporty', "Hoi {$naam},\n\nJe bent bij Sporty gekoppeld als verzorger van {$kind['voornaam']}. Via deze link kies je een wachtwoord voor Mijn BSO (de link werkt 3 dagen):\n\n{$link}\n\nTot snel!");
            $_SESSION['laatste_uitnodiging'] = $link;
        }
        $gezag = invoer('v_gezag') === '1';
        koppel_verzorger((int) $kind['id'], $id, invoer('v_relatie'), $gezag, $rechten);
        $bron = array_key_exists(invoer('v_bron'), GEZAG_BRONNEN) ? invoer('v_bron') : '';
        if ($gezag && $bron !== '') {
            q('UPDATE kind_verzorgers SET gezag_gecontroleerd_op = ?, gezag_gecontroleerd_door = ?, gezag_bron = ? WHERE kind_id = ? AND gebruiker_id = ?', [nu(), $gebruiker['id'], $bron, $kind['id'], $id]);
        }
        log_actie('Verzorger gekoppeld', 'gebruiker:' . $id . ($gezag ? ', gezag' . ($bron ? ' gecontroleerd' : ' nog te controleren') : ', zonder gezag'), null, 'wijziging', $onderwerp);
        flash('succes', $naam . ' is gekoppeld aan ' . $kind['voornaam'] . ($bestaand ? '.' : ' en krijgt een e-mail om een wachtwoord te kiezen.'));
        redirect($terug . '#verzorgers');
    }
}

// Inzage door het team wordt vastgelegd
if ($team) {
    log_inzage('Kinddossier bekeken', $onderwerp);
}

$ouder = rij('SELECT * FROM gebruikers WHERE id = ?', [$kind['ouder_id']]);
$verzorgers = $team ? verzorgers_van_kind((int) $kind['id']) : [];
$eigenKoppeling = $team ? null : verzorger_koppeling((int) $kind['id'], (int) $gebruiker['id']);
$observaties = ontsleutel_kolommen(rijen(
    'SELECT o.*, g.naam AS auteur FROM observaties o LEFT JOIN gebruikers g ON g.id = o.auteur_id WHERE o.kind_id = ?' . ($team ? '' : ' AND o.gedeeld = 1') . ' ORDER BY o.datum DESC, o.id DESC',
    [$kind['id']]
), ['tekst']);
$perGebied = array_fill_keys(array_keys(ONTWIKKELGEBIEDEN), 0);
foreach ($observaties as $obs) {
    $perGebied[$obs['gebied']] = ($perGebied[$obs['gebied']] ?? 0) + 1;
}
$komend = rijen("SELECT * FROM inschrijvingen WHERE kind_id = ? AND datum >= ? AND status != 'afgemeld' ORDER BY datum LIMIT 6", [$kind['id'], vandaag()]);
$geweest = rijen("SELECT * FROM inschrijvingen WHERE kind_id = ? AND datum < ? ORDER BY datum DESC LIMIT 10", [$kind['id'], vandaag()]);
$fotos = ($team || heeft_recht((int) $kind['id'], (int) $gebruiker['id'], 'fotos')) ? fotos_van([(int) $kind['id']], 8) : [];

pagina_begin(kindnaam($kind), 'kinderen.php');
echo '<a class="terug-link" href="kinderen.php">' . icoon('back') . ($team ? 'Alle kinderen' : 'Mijn kinderen') . '</a>';
pagina_kop(kindnaam($kind), ($kind['groepnaam'] ? 'Groep ' . e($kind['groepnaam']) : 'Nog geen groep') . (($l = leeftijd($kind['geboortedatum'])) !== null ? ' · ' . $l . ' jaar' : ''),
    $team ? '<a class="btn btn--secondary btn--small" href="berichten.php?ouder=' . (int) $kind['ouder_id'] . '">' . icoon('chat') . 'Bericht aan ouder</a>' : (heeft_recht((int) $kind['id'], (int) $gebruiker['id'], 'agenda') ? '<a class="btn btn--small" href="agenda.php?kind=' . (int) $kind['id'] . '">' . icoon('calendar') . 'Agenda</a>' : ''));
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
<?php if (!$team && !$magBeslissen): ?>
      <p class="note"><?= icoon('info') ?><span><?= gezag_gecontroleerd($eigenKoppeling) || !$eigenKoppeling || !(int) $eigenKoppeling['gezag']
          ? 'Gegevens wijzigen en toestemming geven kan alleen wie het gezag over ' . e($kind['voornaam']) . ' heeft.'
          : 'We hebben je gezag nog niet gecontroleerd. Daarna kun je de gegevens en toestemmingen zelf wijzigen. Neem bij je volgende bezoek een uittreksel uit het gezagsregister mee.' ?> Klopt er iets niet? Stuur ons een <a href="berichten.php">bericht</a> of dien een <a href="privacy.php">correctieverzoek</a> in.</span></p>
<?php else: ?>
      <details class="uitklap"<?= isset($fouten['voornaam']) || isset($fouten['geboortedatum']) ? ' open' : '' ?>>
        <summary><?= icoon('gear') ?>Gegevens wijzigen</summary>
        <form class="form" method="post" action="<?= e($terug) ?>">
          <?= csrf_veld() ?>
          <input type="hidden" name="actie" value="gegevens">
<?php if ($beheerder): ?>
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
<?php if ($magBeslissen || $beheerder): ?>
          <div class="field"><div class="consent"><input id="foto_toestemming" name="foto_toestemming" type="checkbox" value="1"<?= $kind['foto_toestemming'] ? ' checked' : '' ?>><label for="foto_toestemming">Mag op groepsfoto's die ook naar andere ouders gaan<?= $beheerder ? ' <span class="field__hint">(alleen aanpassen op verzoek van wie gezag heeft)</span>' : '' ?></label></div></div>
<?php endif; ?>
<?php if ($beheerder): ?>
          <div class="field"><div class="consent"><input id="actief" name="actief" type="checkbox" value="1"<?= $kind['actief'] ? ' checked' : '' ?>><label for="actief">Komt naar de BSO (uitvinken als het kind stopt; toekomstige dagen worden dan afgemeld)</label></div></div>
<?php endif; ?>
          <div class="form__acties"><button class="btn" type="submit">Opslaan</button></div>
        </form>
      </details>
<?php endif; ?>
    </section>
<?php if ($team): ?>
<?= verzorgers_paneel($kind, $verzorgers, $beheerder, $terug) ?>
<?php endif; ?>

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
<?php if ((int) $obs['auteur_id'] === (int) $gebruiker['id'] || $beheerder): ?>
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

/** Verzorgers van het kind met relatie, gezag en rechten. De beheerder beheert ze hier. */
function verzorgers_paneel(array $kind, array $verzorgers, bool $beheerder, string $terug): string
{
    $link = $_SESSION['laatste_uitnodiging'] ?? null;
    unset($_SESSION['laatste_uitnodiging']);
    ob_start();
    ?>
    <section class="panel" id="verzorgers" aria-labelledby="verzorgers-titel">
      <h2 id="verzorgers-titel">Verzorgers en gezag</h2>
      <p class="muted">Wie namens <?= e($kind['voornaam']) ?> mag beslissen (toestemming, gegevens, privacyverzoeken): alleen verzorgers met gezag dat door ons is gecontroleerd.</p>
<?php if ($link): ?>
      <div class="melding" role="status"><?= icoon('info') ?><div><p>Komt de e-mail niet aan? Stuur deze link dan zelf door (werkt 3 dagen):</p><p><code style="word-break: break-all"><?= e($link) ?></code></p></div></div>
<?php endif; ?>
      <ul class="kindlijst">
<?php foreach ($verzorgers as $v): $gecontroleerd = gezag_gecontroleerd($v); ?>
        <li class="kindrij" style="grid-template-columns: 1fr">
          <div>
            <div class="kindrij__naam"><?= e($v['naam']) ?> <span class="badge"><?= e(RELATIES[$v['relatie']] ?? $v['relatie']) ?></span><?= (int) $v['gebruiker_id'] === (int) $kind['ouder_id'] ? ' <span class="badge">Contracthouder</span>' : '' ?>
              <?= (int) $v['gezag'] ? ($gecontroleerd ? '<span class="badge badge--geldig">Gezag gecontroleerd</span>' : '<span class="badge badge--wachtlijst">Gezag nog controleren</span>') : '<span class="badge badge--afgemeld" style="text-decoration:none">Geen gezag</span>' ?></div>
            <div class="kindrij__info">
              <span>Rechten: <?= e(implode(', ', array_map(fn ($r) => mb_strtolower(RECHTEN[$r]), array_filter(array_keys(RECHTEN), fn ($r) => (int) $v['recht_' . $r] === 1))) ?: 'geen') ?></span>
<?php if ($v['gezag_gecontroleerd_op']): ?><span>Vastgelegd op <?= e(datum_nl($v['gezag_gecontroleerd_op'])) ?><?= $v['gezag_bron'] ? ' via ' . e(mb_strtolower(GEZAG_BRONNEN[$v['gezag_bron']] ?? $v['gezag_bron'])) : '' ?><?= $v['gecontroleerd_door_naam'] ? ' door ' . e($v['gecontroleerd_door_naam']) : '' ?></span><?php endif; ?>
            </div>
          </div>
<?php if ($beheerder): ?>
          <details class="uitklap">
            <summary>Gezag en rechten van <?= e($v['naam']) ?></summary>
            <form class="form" method="post" action="<?= e($terug) ?>#verzorgers">
              <?= csrf_veld() ?><input type="hidden" name="actie" value="gezag"><input type="hidden" name="verzorger" value="<?= (int) $v['gebruiker_id'] ?>">
              <fieldset><legend class="legend-label">Gezag controleren</legend>
                <div class="choices">
                  <label class="choice"><input type="radio" name="gezag" value="1"<?= (int) $v['gezag'] ? ' checked' : '' ?>>Heeft gezag</label>
                  <label class="choice"><input type="radio" name="gezag" value="0"<?= (int) $v['gezag'] ? '' : ' checked' ?>>Geen gezag</label>
                </div>
              </fieldset>
              <div class="field"><label for="bron-<?= (int) $v['gebruiker_id'] ?>">Gecontroleerd met</label><select id="bron-<?= (int) $v['gebruiker_id'] ?>" name="bron"><option value="">Kies…</option><?php foreach (GEZAG_BRONNEN as $sleutel => $label): ?><option value="<?= e($sleutel) ?>"<?= $v['gezag_bron'] === $sleutel ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
              <div class="form__acties"><button class="btn btn--small" type="submit">Gezag vastleggen</button></div>
            </form>
            <form class="form" method="post" action="<?= e($terug) ?>#verzorgers">
              <?= csrf_veld() ?><input type="hidden" name="actie" value="rechten"><input type="hidden" name="verzorger" value="<?= (int) $v['gebruiker_id'] ?>">
              <div class="field field--klein"><label for="relatie-<?= (int) $v['gebruiker_id'] ?>">Relatie</label><select id="relatie-<?= (int) $v['gebruiker_id'] ?>" name="relatie"><?php foreach (RELATIES as $sleutel => $label): ?><option value="<?= e($sleutel) ?>"<?= $v['relatie'] === $sleutel ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
              <fieldset><legend class="legend-label">Rechten</legend><div class="choices">
<?php foreach (RECHTEN as $sleutel => $label): ?>
                <label class="choice"><input type="checkbox" name="rechten[]" value="<?= e($sleutel) ?>"<?= (int) $v['recht_' . $sleutel] ? ' checked' : '' ?>><?= e($label) ?></label>
<?php endforeach; ?>
              </div></fieldset>
              <div class="form__acties">
                <button class="btn btn--secondary btn--small" type="submit">Rechten opslaan</button>
<?php if ((int) $v['gebruiker_id'] !== (int) $kind['ouder_id']): ?>
                <button class="btn btn--gevaar btn--small" type="submit" name="actie" value="ontkoppelen" formnovalidate>Ontkoppelen</button>
<?php endif; ?>
              </div>
            </form>
          </details>
<?php endif; ?>
        </li>
<?php endforeach; ?>
      </ul>
<?php if ($beheerder): ?>
      <details class="uitklap">
        <summary><?= icoon('plus') ?>Verzorger toevoegen (bijvoorbeeld de andere ouder)</summary>
        <form class="form" method="post" action="<?= e($terug) ?>#verzorgers">
          <?= csrf_veld() ?><input type="hidden" name="actie" value="verzorger_toevoegen">
          <div class="form__row">
            <div class="field"><label for="v_naam">Naam</label><input id="v_naam" name="v_naam" required></div>
            <div class="field"><label for="v_email">E-mailadres</label><input id="v_email" name="v_email" type="email" required></div>
          </div>
          <div class="form__row">
            <div class="field"><label for="v_relatie">Relatie</label><select id="v_relatie" name="v_relatie"><?php foreach (RELATIES as $sleutel => $label): ?><option value="<?= e($sleutel) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="v_bron">Gezag gecontroleerd met <span class="field__hint">(als van toepassing)</span></label><select id="v_bron" name="v_bron"><option value="">Nog niet gecontroleerd</option><?php foreach (GEZAG_BRONNEN as $sleutel => $label): ?><option value="<?= e($sleutel) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
          </div>
          <div class="field"><div class="consent"><input id="v_gezag" name="v_gezag" type="checkbox" value="1"><label for="v_gezag">Heeft het gezag over <?= e($kind['voornaam']) ?></label></div></div>
          <fieldset><legend class="legend-label">Rechten</legend><div class="choices">
<?php foreach (RECHTEN as $sleutel => $label): ?>
            <label class="choice"><input type="checkbox" name="rechten[]" value="<?= e($sleutel) ?>"<?= $sleutel !== 'agenda' ? ' checked' : '' ?>><?= e($label) ?></label>
<?php endforeach; ?>
          </div></fieldset>
          <div class="form__acties"><button class="btn btn--small" type="submit">Koppelen</button></div>
        </form>
      </details>
      <p class="muted" style="margin-top: var(--space-s)"><a href="logboek.php?onderwerp=<?= e(rawurlencode('kind:' . $kind['id'])) ?>">Logboek van <?= e($kind['voornaam']) ?>: wie heeft het dossier bekeken of gewijzigd?</a></p>
<?php endif; ?>
    </section>
<?php
    return (string) ob_get_clean();
}
