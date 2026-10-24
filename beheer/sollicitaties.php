<?php
/*
 * Open sollicitaties via de website (Werken bij). Voor teamleden met het recht "Sollicitaties".
 * Per sollicitatie: gegevens, motivatie en cv bekijken (komt in het logboek), status bijhouden,
 * een notitie voor het team, en verwijderen. Afgeronde sollicitaties worden vanzelf verwijderd
 * (zie inc/sollicitaties.php); de datum staat erbij.
 */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_recht('sollicitaties');

// Het cv downloaden
if (get_int('cv') > 0) {
    $sollicitatie = rij('SELECT * FROM sollicitaties WHERE id = ? AND cv_bestand IS NOT NULL', [get_int('cv')]);
    if (!$sollicitatie) {
        niet_gevonden();
    }
    $inhoud = lees_versleuteld_bestand(cv_map() . '/' . $sollicitatie['cv_bestand'] . '.bin');
    if ($inhoud === null) {
        niet_gevonden('Dit cv is niet (meer) beschikbaar.');
    }
    log_inzage('Cv van sollicitant geopend', 'sollicitatie:' . $sollicitatie['id']);
    header_remove('Cache-Control');
    header('Cache-Control: no-store, private');
    header('Content-Type: ' . $sollicitatie['cv_type']);
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $sollicitatie['cv_naam']) . '"');
    header('Content-Length: ' . strlen($inhoud));
    echo $inhoud;
    exit;
}

$sollicitatie = get_int('id') ? rij('SELECT * FROM sollicitaties WHERE id = ?', [get_int('id')]) : null;
if (get_int('id') && !$sollicitatie) {
    niet_gevonden('Deze sollicitatie bestaat niet (meer). Misschien is hij al verwijderd na de bewaartermijn.');
}

if (is_post() && $sollicitatie) {
    csrf_controleer();
    $actie = invoer('actie');
    $terug = 'sollicitaties.php?id=' . (int) $sollicitatie['id'];
    $onderwerp = 'sollicitatie:' . $sollicitatie['id'];

    if ($actie === 'status' && array_key_exists(invoer('status'), SOLLICITATIE_STATUSSEN)) {
        $status = invoer('status');
        $afgerond = in_array($status, ['afgewezen', 'aangenomen'], true) ? ($sollicitatie['afgerond_op'] ?: nu()) : null;
        $verwijderen = sollicitatie_verwijderdatum($status, (bool) $sollicitatie['bewaren'], $sollicitatie['aangemaakt_op'], $afgerond);
        q('UPDATE sollicitaties SET status = ?, afgerond_op = ?, verwijderen_op = ? WHERE id = ?', [$status, $afgerond, $verwijderen, $sollicitatie['id']]);
        log_actie('Sollicitatie: status ' . SOLLICITATIE_STATUSSEN[$status], 'sollicitatie ' . $sollicitatie['id'], null, 'wijziging', $onderwerp);
        flash('succes', 'De status is nu "' . SOLLICITATIE_STATUSSEN[$status] . '". De sollicitatie wordt vanzelf verwijderd op ' . datum_nl($verwijderen) . '.');
    } elseif ($actie === 'notitie') {
        q('UPDATE sollicitaties SET notitie = ? WHERE id = ?', [versleutel(mb_substr(invoer('notitie'), 0, 3000)), $sollicitatie['id']]);
        log_actie('Sollicitatie: notitie bijgewerkt', 'sollicitatie ' . $sollicitatie['id'], null, 'wijziging', $onderwerp);
        flash('succes', 'De notitie is opgeslagen.');
    } elseif ($actie === 'verwijderen') {
        verwijder_sollicitatie($sollicitatie, 'handmatig');
        flash('succes', 'De sollicitatie en het cv zijn verwijderd.');
        redirect('sollicitaties.php');
    }
    redirect($terug);
}

pagina_begin('Sollicitaties', 'sollicitaties.php');

if ($sollicitatie) {
    log_inzage('Sollicitatie bekeken', 'sollicitatie:' . $sollicitatie['id']);
    $s = ontsleutel_kolommen($sollicitatie, ['motivatie', 'link', 'notitie'], true);
    $dagen = array_filter(explode(',', $s['dagen']));
    echo '<a class="terug-link" href="sollicitaties.php">' . icoon('back') . 'Alle sollicitaties</a>';
    pagina_kop($s['naam'], sollicitatie_badge($s['status']) . ' Binnengekomen op ' . e(moment_nl($s['aangemaakt_op'])) . '.');
    ?>
<div class="kolommen">
  <section class="panel" aria-labelledby="sollicitant-titel">
    <h2 id="sollicitant-titel">Sollicitatie</h2>
    <dl class="gegevens">
      <dt>E-mail</dt><dd><a href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a></dd>
      <dt>Telefoon</dt><dd><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $s['telefoon'])) ?>"><?= e($s['telefoon']) ?></a></dd>
      <dt>Woonplaats</dt><dd><?= e($s['woonplaats'] ?: '-') ?></dd>
      <dt>Wil het liefst</dt><dd><?= e($s['functie'] ?: '-') ?></dd>
      <dt>Kan werken op</dt><dd><?= e($dagen ? implode(', ', $dagen) : '-') ?></dd>
      <dt>Link</dt><dd><?= $s['link'] !== '' ? '<a href="' . e($s['link']) . '" rel="noopener noreferrer" target="_blank">' . e($s['link']) . '<span class="visually-hidden"> (opent in een nieuw tabblad)</span></a>' : '-' ?></dd>
      <dt>Cv</dt><dd><?= $s['cv_bestand'] ? '<a class="bonnetje" href="sollicitaties.php?cv=' . (int) $s['id'] . '">' . icoon('clip') . e($s['cv_naam']) . '</a> <span class="muted">(' . e(number_format((int) $s['cv_grootte'] / 1024, 0, ',', '.')) . ' kB)</span>' : 'Geen cv meegestuurd' ?></dd>
      <dt>Bewaren</dt><dd><?= $s['bewaren'] ? 'Mag een jaar bewaard worden, voor als er later een plek is' : 'Geen toestemming om langer te bewaren' ?></dd>
    </dl>
    <h3 style="margin-top: var(--space-l)">Motivatie</h3>
    <p style="white-space: pre-line"><?= e($s['motivatie']) ?></p>
  </section>
  <section class="panel" aria-labelledby="afhandelen-titel">
    <h2 id="afhandelen-titel">Afhandelen</h2>
    <form class="form" method="post">
      <?= csrf_veld() ?>
      <input type="hidden" name="actie" value="status">
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
<?php foreach (SOLLICITATIE_STATUSSEN as $sleutel => $label): ?>
          <option value="<?= e($sleutel) ?>"<?= $s['status'] === $sleutel ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="form__acties"><button class="btn btn--small" type="submit">Status opslaan</button></div>
    </form>
    <p class="note"><?= icoon('clock') ?><span>Wordt vanzelf verwijderd op <strong><?= e(datum_nl($s['verwijderen_op'])) ?></strong><?= match (true) {
        $s['status'] === 'afgewezen' && $s['bewaren'] => ': een jaar na binnenkomst, want de sollicitant gaf toestemming om te bewaren.',
        in_array($s['status'], ['afgewezen', 'aangenomen'], true) => ': 4 weken na het afronden.',
        default => ', ' . ($s['bewaren'] ? 'een jaar' : 'drie maanden') . ' na binnenkomst. Rond je hem af (geen plek of aangenomen), dan 4 weken daarna' . ($s['bewaren'] ? ', of bij "Geen plek" een jaar na binnenkomst (de sollicitant gaf toestemming om te bewaren)' : '') . '.',
    } ?></span></p>
    <p class="muted">Laat de sollicitant zelf even weten hoe het ervoor staat, bijvoorbeeld per mail of telefoon.</p>

    <form class="form" method="post" style="margin-top: var(--space-l)">
      <?= csrf_veld() ?>
      <input type="hidden" name="actie" value="notitie">
      <div class="field"><label for="notitie">Notitie voor het team <span class="field__hint">(alleen voor jullie)</span></label><textarea id="notitie" name="notitie" rows="4"><?= e($s['notitie']) ?></textarea></div>
      <div class="form__acties"><button class="btn btn--secondary btn--small" type="submit">Notitie opslaan</button></div>
    </form>

    <form method="post" style="margin-top: var(--space-l)" data-bevestig="Deze sollicitatie en het cv nu verwijderen? Dit kan niet ongedaan worden gemaakt.">
      <?= csrf_veld() ?>
      <input type="hidden" name="actie" value="verwijderen">
      <button class="btn btn--gevaar btn--small" type="submit"><?= icoon('trash') ?>Nu verwijderen</button>
    </form>
  </section>
</div>
<?php
    pagina_einde();
    exit;
}

$sollicitaties = rijen("SELECT id, status, naam, functie, cv_bestand, aangemaakt_op, verwijderen_op FROM sollicitaties ORDER BY status IN ('afgewezen', 'aangenomen'), aangemaakt_op DESC LIMIT 300");
pagina_kop('Sollicitaties', 'Open sollicitaties via de pagina Werken bij op de website. Alleen collega\'s met het recht "Sollicitaties" zien ze.');
?>
<section class="panel" aria-label="Sollicitaties">
<?php if (!$sollicitaties): ?>
  <p class="leeg">Nog geen sollicitaties. Ze komen hier binnen zodra iemand het formulier op <a href="../werken-bij.html">Werken bij</a> invult.</p>
<?php else: ?>
  <div class="tabel">
    <table>
      <thead><tr><th scope="col">Naam</th><th scope="col">Wil het liefst</th><th scope="col">Binnengekomen</th><th scope="col">Cv</th><th scope="col">Status</th><th scope="col">Wordt verwijderd</th></tr></thead>
      <tbody>
<?php foreach ($sollicitaties as $rij): ?>
        <tr>
          <th scope="row"><a href="sollicitaties.php?id=<?= (int) $rij['id'] ?>"><?= e($rij['naam']) ?></a></th>
          <td><?= e($rij['functie'] ?: '-') ?></td>
          <td><?= e(datum_nl($rij['aangemaakt_op'], 'd MMM y')) ?></td>
          <td><?= $rij['cv_bestand'] ? 'Ja' : 'Nee' ?></td>
          <td><?= sollicitatie_badge($rij['status']) ?></td>
          <td><?= e(datum_nl($rij['verwijderen_op'], 'd MMM y')) ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</section>
<?php
pagina_einde();
