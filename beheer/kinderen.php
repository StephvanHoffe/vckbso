<?php
/* Kinderen. Team: alle kinderen, met snel indelen in een groep. Ouder: eigen kinderen en een kind toevoegen. */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_login();
if (is_team($gebruiker)) {
    vereis_recht('kinderen');
}

/* ---------- Ouder ---------- */
if (is_ouder($gebruiker)) {
    $fouten = [];
    $nieuw = ['voornaam' => '', 'achternaam' => '', 'geboortedatum' => '', 'school' => '', 'bijzonderheden' => '', 'foto_toestemming' => '', 'relatie' => 'ouder', 'gezag' => '1'];
    if (is_post()) {
        csrf_controleer();
        foreach (['voornaam', 'achternaam', 'geboortedatum', 'school', 'bijzonderheden'] as $veld) {
            $nieuw[$veld] = invoer($veld);
        }
        $nieuw['foto_toestemming'] = invoer('foto_toestemming') === '1' ? '1' : '';
        $nieuw['relatie'] = array_key_exists(invoer('relatie'), RELATIES) ? invoer('relatie') : 'ouder';
        $nieuw['gezag'] = invoer('gezag') === '1' ? '1' : '';
        if ($nieuw['voornaam'] === '') {
            $fouten['voornaam'] = 'Vul de voornaam in.';
        }
        if ($nieuw['achternaam'] === '') {
            $fouten['achternaam'] = 'Vul de achternaam in.';
        }
        if (!geldige_datum($nieuw['geboortedatum']) || $nieuw['geboortedatum'] > vandaag() || $nieuw['geboortedatum'] < date('Y-m-d', strtotime('-16 years'))) {
            $fouten['geboortedatum'] = 'Vul een geldige geboortedatum in.';
        }
        if (!$fouten) {
            q('INSERT INTO kinderen (ouder_id, voornaam, achternaam, geboortedatum, school, bijzonderheden, foto_toestemming, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
                $gebruiker['id'], $nieuw['voornaam'], $nieuw['achternaam'], $nieuw['geboortedatum'], $nieuw['school'], versleutel($nieuw['bijzonderheden']), $nieuw['foto_toestemming'] ? 1 : 0, nu(),
            ]);
            $kindId = laatste_id();
            koppel_verzorger($kindId, (int) $gebruiker['id'], $nieuw['relatie'], (bool) $nieuw['gezag']);
            melding_voor_team((int) $gebruiker['id'], 'Nieuw kind toegevoegd: ' . $nieuw['voornaam'] . ' ' . $nieuw['achternaam'] . '. Graag gezag controleren en indelen in een groep.', $kindId);
            log_actie('Kind toegevoegd door ouder', '', null, 'wijziging', 'kind:' . $kindId);
            flash('succes', $nieuw['voornaam'] . ' is toegevoegd. We zetten ' . $nieuw['voornaam'] . ' zo snel mogelijk in een groep; daarna kun je dagen kiezen.');
            redirect('kinderen.php');
        }
    }
    $kinderen = kinderen_van_verzorger((int) $gebruiker['id'], null, false);

    pagina_begin('Mijn kinderen', 'kinderen.php');
    pagina_kop('Mijn kinderen', 'Bekijk en wijzig de gegevens van je kinderen, zoals allergieën en wie ze mag ophalen.');
    ?>
<div class="kolommen">
  <section class="panel" aria-labelledby="lijst-titel">
    <h2 id="lijst-titel">Je kinderen</h2>
<?php if (!$kinderen): ?>
    <p class="leeg">Nog geen kinderen in je account.</p>
<?php else: ?>
    <ul class="kindlijst">
<?php foreach ($kinderen as $kind): ?>
      <li class="kindrij">
        <div>
          <div class="kindrij__naam"><?= (int) $kind['v_recht_dossier'] === 1 ? '<a href="kind.php?id=' . (int) $kind['id'] . '">' . e(kindnaam($kind)) . '</a>' : e(kindnaam($kind)) ?><?= $kind['actief'] ? '' : ' ' . status_badge('gestopt') ?></div>
          <div class="kindrij__info">
            <span><?= leeftijd($kind['geboortedatum']) ?> jaar</span>
            <span><?= $kind['groepnaam'] ? 'Groep ' . e($kind['groepnaam']) : 'Nog geen groep' ?></span>
            <span><?= e(RELATIES[$kind['v_relatie']] ?? '') ?><?= (int) $kind['v_gezag'] === 1 ? (empty($kind['v_gezag_gecontroleerd_op']) ? ', gezag nog niet gecontroleerd' : ', gezag gecontroleerd') : ', zonder gezag' ?></span>
          </div>
        </div>
<?php if ((int) $kind['v_recht_dossier'] === 1): ?>
        <a class="btn btn--secondary btn--mini" href="kind.php?id=<?= (int) $kind['id'] ?>">Gegevens<span class="visually-hidden"> van <?= e($kind['voornaam']) ?></span></a>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </section>
  <section class="panel" aria-labelledby="nieuw-titel">
    <h2 id="nieuw-titel">Kind toevoegen</h2>
    <p class="muted">Een broertje of zusje dat ook naar de BSO gaat?</p>
    <?= foutensamenvatting($fouten) ?>
    <form class="form" method="post">
      <?= csrf_veld() ?>
      <div class="field"><label for="voornaam">Voornaam</label><input id="voornaam" name="voornaam" required value="<?= e($nieuw['voornaam']) ?>"<?= aria_fout($fouten, 'voornaam') ?>><?= veldfout($fouten, 'voornaam') ?></div>
      <div class="field"><label for="achternaam">Achternaam</label><input id="achternaam" name="achternaam" required value="<?= e($nieuw['achternaam']) ?>"<?= aria_fout($fouten, 'achternaam') ?>><?= veldfout($fouten, 'achternaam') ?></div>
      <div class="field"><label for="geboortedatum">Geboortedatum</label><input id="geboortedatum" name="geboortedatum" type="date" max="<?= vandaag() ?>" required value="<?= e($nieuw['geboortedatum']) ?>"<?= aria_fout($fouten, 'geboortedatum') ?>><?= veldfout($fouten, 'geboortedatum') ?></div>
      <div class="field"><label for="school">Basisschool <span class="field__hint">(optioneel)</span></label><input id="school" name="school" value="<?= e($nieuw['school']) ?>"></div>
      <div class="field"><label for="bijzonderheden">Allergieën, medicijnen of bijzonderheden <span class="field__hint">(optioneel)</span></label><textarea id="bijzonderheden" name="bijzonderheden" rows="3"><?= e($nieuw['bijzonderheden']) ?></textarea></div>
      <div class="field"><label for="relatie">Jij bent</label><select id="relatie" name="relatie"><?php foreach (RELATIES as $sleutel => $label): ?><option value="<?= e($sleutel) ?>"<?= $nieuw['relatie'] === $sleutel ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
      <div class="field"><div class="consent"><input id="gezag" name="gezag" type="checkbox" value="1"<?= $nieuw['gezag'] ? ' checked' : '' ?>><label for="gezag">Ik heb het (ouderlijk) gezag over dit kind <span class="field__hint">(we controleren dit bij het volgende bezoek)</span></label></div></div>
      <div class="field"><div class="consent"><input id="foto_toestemming" name="foto_toestemming" type="checkbox" value="1"<?= $nieuw['foto_toestemming'] ? ' checked' : '' ?>><label for="foto_toestemming">Mag op groepsfoto's die ook naar andere ouders gaan</label></div></div>
      <div class="form__acties"><button class="btn" type="submit"><?= icoon('plus') ?>Toevoegen</button></div>
    </form>
  </section>
</div>
<?php
    pagina_einde();
    exit;
}

/* ---------- Team ---------- */

if (is_post()) {
    csrf_controleer();
    // Kinderen indelen in een groep: met het recht klanten beheren
    vereis_recht('klanten_beheren');
    $kind = rij('SELECT * FROM kinderen WHERE id = ? AND geanonimiseerd_op IS NULL', [(int) invoer('kind')]);
    if (!$kind || !team_mag_kind($kind)) {
        niet_gevonden();
    }
    $groepId = invoer('groep') === '' ? null : (int) invoer('groep');
    if ($groepId !== null && (!groep($groepId) || !team_mag_groep($groepId))) {
        niet_gevonden();
    }
    q('UPDATE kinderen SET groep_id = ? WHERE id = ?', [$groepId, $kind['id']]);
    log_actie('Kind ingedeeld', $groepId ? groep($groepId)['naam'] : 'geen groep', null, 'wijziging', 'kind:' . $kind['id']);
    flash('succes', $kind['voornaam'] . ($groepId ? ' zit nu in groep ' . groep($groepId)['naam'] . '.' : ' zit nu in geen groep.'));
    redirect(veilig_terug(invoer('terug', 'kinderen.php')));
}

$filterGroep = get_str('groep');
$zoek = get_str('zoek');
$metGestopt = get_str('alle') === '1';
$waar = ['k.geanonimiseerd_op IS NULL', groep_voorwaarde('k.groep_id')];
$params = [];
if ($filterGroep === 'geen' && team_groep_ids($gebruiker) === null) {
    $waar[] = 'k.groep_id IS NULL';
} elseif (ctype_digit($filterGroep)) {
    $waar[] = 'k.groep_id = ?';
    $params[] = (int) $filterGroep;
}
if ($zoek !== '') {
    $waar[] = "(k.voornaam || ' ' || k.achternaam LIKE ? OR o.naam LIKE ?)";
    $params[] = '%' . $zoek . '%';
    $params[] = '%' . $zoek . '%';
}
if (!$metGestopt) {
    $waar[] = "k.actief = 1 AND o.status != 'gestopt'";
}
$kinderen = rijen(
    'SELECT k.*, g.naam AS groepnaam, o.naam AS oudernaam, o.status AS ouderstatus FROM kinderen k
     JOIN gebruikers o ON o.id = k.ouder_id LEFT JOIN groepen g ON g.id = k.groep_id
     WHERE ' . implode(' AND ', $waar) . ' ORDER BY k.voornaam, k.achternaam',
    $params
);
$kinderen = ontsleutel_kolommen($kinderen, ['bijzonderheden']);
$terug = 'kinderen.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');

pagina_begin('Kinderen', 'kinderen.php');
pagina_kop('Kinderen', 'Alle kinderen met hun groep. Klik op een naam voor het dossier met gegevens, aanwezigheid en observaties.');
?>
<section class="panel" aria-labelledby="lijst-titel">
  <h2 id="lijst-titel" class="visually-hidden">Lijst met kinderen</h2>
  <form class="filters" method="get">
    <div class="field">
      <label for="zoek">Zoeken</label>
      <input id="zoek" name="zoek" type="search" value="<?= e($zoek) ?>" placeholder="Naam van kind of ouder">
    </div>
    <div class="field">
      <label for="groep">Groep</label>
      <select id="groep" name="groep">
        <option value="">Alle groepen</option>
<?php if (team_groep_ids($gebruiker) === null): ?>
        <option value="geen"<?= $filterGroep === 'geen' ? ' selected' : '' ?>>Nog geen groep</option>
<?php endif; ?>
<?php foreach (rijen('SELECT * FROM groepen WHERE ' . groep_voorwaarde('id') . ' ORDER BY naam') as $groep): ?>
        <option value="<?= (int) $groep['id'] ?>"<?= $filterGroep === (string) $groep['id'] ? ' selected' : '' ?>><?= e($groep['naam']) ?></option>
<?php endforeach; ?>
      </select>
    </div>
    <div class="field"><div class="consent"><input id="alle" name="alle" type="checkbox" value="1"<?= $metGestopt ? ' checked' : '' ?>><label for="alle">Ook gestopte kinderen</label></div></div>
    <button class="btn btn--secondary btn--small" type="submit">Toon</button>
  </form>
<?php if (!$kinderen): ?>
  <p class="leeg">Geen kinderen gevonden.</p>
<?php else: ?>
  <div class="tabel">
    <table>
      <caption><?= count($kinderen) ?> <?= count($kinderen) === 1 ? 'kind' : 'kinderen' ?></caption>
      <thead><tr><th scope="col">Naam</th><th scope="col">Leeftijd</th><th scope="col">Ouder</th><th scope="col">Groep</th><th scope="col">Let op</th></tr></thead>
      <tbody>
<?php foreach ($kinderen as $kind): ?>
        <tr>
          <th scope="row"><a href="kind.php?id=<?= (int) $kind['id'] ?>"><?= e(kindnaam($kind)) ?></a><?= $kind['actief'] ? '' : ' ' . status_badge('gestopt') ?></th>
          <td><?= leeftijd($kind['geboortedatum']) ?? '' ?></td>
          <td><?= link_als('ouders', 'ouder.php?id=' . (int) $kind['ouder_id'], e($kind['oudernaam'])) ?><?= $kind['ouderstatus'] === 'nieuw' ? ' ' . status_badge('nieuw') : '' ?></td>
          <td>
<?php if (!team_mag('klanten_beheren', $gebruiker)): ?>
            <?= e($kind['groepnaam'] ?? '-') ?>
<?php else: ?>
            <form class="inline-form groepkeuze" method="post">
              <?= csrf_veld() ?>
              <input type="hidden" name="kind" value="<?= (int) $kind['id'] ?>">
              <input type="hidden" name="terug" value="<?= e($terug) ?>">
              <label class="visually-hidden" for="groep-<?= (int) $kind['id'] ?>">Groep van <?= e($kind['voornaam']) ?></label>
              <select id="groep-<?= (int) $kind['id'] ?>" name="groep"><?= groep_opties($kind['groep_id'] !== null ? (int) $kind['groep_id'] : null) ?></select>
              <button class="btn btn--secondary btn--mini" type="submit">Opslaan<span class="visually-hidden"> groep van <?= e($kind['voornaam']) ?></span></button>
            </form>
<?php endif; ?>
          </td>
          <td><?= $kind['bijzonderheden'] !== '' ? '<span class="let-op">' . icoon('alert') . '<span>' . e(mb_strimwidth($kind['bijzonderheden'], 0, 60, '…')) . '</span></span>' : '' ?><?= $kind['foto_toestemming'] ? '' : '<br><small class="muted">Niet op groepsfoto\'s</small>' ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</section>
<?php
pagina_einde();
