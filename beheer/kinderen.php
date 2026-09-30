<?php
/* Kinderen. Team: alle kinderen, met snel indelen in een groep. Ouder: eigen kinderen en een kind toevoegen. */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_login();

/* ---------- Ouder ---------- */
if (is_ouder($gebruiker)) {
    $fouten = [];
    $nieuw = ['voornaam' => '', 'achternaam' => '', 'geboortedatum' => '', 'school' => '', 'bijzonderheden' => '', 'foto_toestemming' => ''];
    if (is_post()) {
        csrf_controleer();
        foreach (['voornaam', 'achternaam', 'geboortedatum', 'school', 'bijzonderheden'] as $veld) {
            $nieuw[$veld] = invoer($veld);
        }
        $nieuw['foto_toestemming'] = invoer('foto_toestemming') === '1' ? '1' : '';
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
                $gebruiker['id'], $nieuw['voornaam'], $nieuw['achternaam'], $nieuw['geboortedatum'], $nieuw['school'], $nieuw['bijzonderheden'], $nieuw['foto_toestemming'] ? 1 : 0, nu(),
            ]);
            $kindId = laatste_id();
            q("INSERT INTO berichten (ouder_id, afzender_id, kind_id, soort, tekst, gelezen_ouder, gelezen_team, aangemaakt_op) VALUES (?, ?, ?, 'systeem', ?, 1, 0, ?)", [
                $gebruiker['id'], $gebruiker['id'], $kindId, 'Nieuw kind toegevoegd: ' . $nieuw['voornaam'] . ' ' . $nieuw['achternaam'] . '. Graag indelen in een groep.', nu(),
            ]);
            log_actie('Kind toegevoegd door ouder', $nieuw['voornaam']);
            flash('succes', $nieuw['voornaam'] . ' is toegevoegd. We zetten ' . $nieuw['voornaam'] . ' zo snel mogelijk in een groep; daarna kun je dagen kiezen.');
            redirect('kinderen.php');
        }
    }
    $kinderen = rijen('SELECT k.*, g.naam AS groepnaam FROM kinderen k LEFT JOIN groepen g ON g.id = k.groep_id WHERE k.ouder_id = ? ORDER BY k.actief DESC, k.voornaam', [$gebruiker['id']]);

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
          <div class="kindrij__naam"><a href="kind.php?id=<?= (int) $kind['id'] ?>"><?= e(kindnaam($kind)) ?></a><?= $kind['actief'] ? '' : ' ' . status_badge('gestopt') ?></div>
          <div class="kindrij__info">
            <span><?= leeftijd($kind['geboortedatum']) ?> jaar</span>
            <span><?= $kind['groepnaam'] ? 'Groep ' . e($kind['groepnaam']) : 'Nog geen groep' ?></span>
            <span><?= $kind['foto_toestemming'] ? "Mag op groepsfoto's" : "Niet op groepsfoto's" ?></span>
          </div>
        </div>
        <a class="btn btn--secondary btn--mini" href="kind.php?id=<?= (int) $kind['id'] ?>">Gegevens<span class="visually-hidden"> van <?= e($kind['voornaam']) ?></span></a>
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
    $kind = rij('SELECT * FROM kinderen WHERE id = ?', [(int) invoer('kind')]);
    if (!$kind) {
        niet_gevonden();
    }
    $groepId = invoer('groep') === '' ? null : (int) invoer('groep');
    if ($groepId !== null && !groep($groepId)) {
        niet_gevonden();
    }
    q('UPDATE kinderen SET groep_id = ? WHERE id = ?', [$groepId, $kind['id']]);
    log_actie('Kind ingedeeld', $kind['voornaam'] . ' → ' . ($groepId ? groep($groepId)['naam'] : 'geen groep'));
    flash('succes', $kind['voornaam'] . ($groepId ? ' zit nu in groep ' . groep($groepId)['naam'] . '.' : ' zit nu in geen groep.'));
    redirect(veilig_terug(invoer('terug', 'kinderen.php')));
}

$filterGroep = get_str('groep');
$zoek = get_str('zoek');
$metGestopt = get_str('alle') === '1';
$waar = ['1 = 1'];
$params = [];
if ($filterGroep === 'geen') {
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
        <option value="geen"<?= $filterGroep === 'geen' ? ' selected' : '' ?>>Nog geen groep</option>
<?php foreach (rijen('SELECT * FROM groepen ORDER BY naam') as $groep): ?>
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
          <td><a href="ouder.php?id=<?= (int) $kind['ouder_id'] ?>"><?= e($kind['oudernaam']) ?></a><?= $kind['ouderstatus'] === 'nieuw' ? ' ' . status_badge('nieuw') : '' ?></td>
          <td>
            <form class="inline-form groepkeuze" method="post">
              <?= csrf_veld() ?>
              <input type="hidden" name="kind" value="<?= (int) $kind['id'] ?>">
              <input type="hidden" name="terug" value="<?= e($terug) ?>">
              <label class="visually-hidden" for="groep-<?= (int) $kind['id'] ?>">Groep van <?= e($kind['voornaam']) ?></label>
              <select id="groep-<?= (int) $kind['id'] ?>" name="groep"><?= groep_opties($kind['groep_id'] !== null ? (int) $kind['groep_id'] : null) ?></select>
              <button class="btn btn--secondary btn--mini" type="submit">Opslaan<span class="visually-hidden"> groep van <?= e($kind['voornaam']) ?></span></button>
            </form>
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
