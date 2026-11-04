<?php
/*
 * Proefdagen: momenten klaarzetten waarop ouders een gratis proefdag kunnen boeken
 * (op de website, proefdag.html), en de boekingen bekijken en afhandelen.
 * Voor teamleden met het recht "Proefdagen". Boekingen worden 4 weken na de proefdag
 * vanzelf verwijderd (zie inc/proefdagen.php).
 */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_recht('proefdagen');

if (is_post()) {
    csrf_controleer();
    $actie = invoer('actie');

    if ($actie === 'moment_toevoegen') {
        $datum = invoer('datum');
        $begin = invoer('begin');
        $eind = invoer('eind');
        $plekken = (int) invoer('plekken');
        $herhaal = max(1, min(12, (int) invoer('herhaal', '1')));
        if (!geldige_datum($datum) || $datum <= vandaag()) {
            flash('fout', 'Kies een datum vanaf morgen.');
        } elseif (!geldige_tijd($begin) || !geldige_tijd($eind) || $begin >= $eind) {
            flash('fout', 'Vul een begintijd en een eindtijd in; de eindtijd ligt na de begintijd.');
        } elseif ($plekken < 1 || $plekken > 50) {
            flash('fout', 'Kies tussen 1 en 50 plekken.');
        } else {
            for ($week = 0; $week < $herhaal; $week++) {
                $dag = date('Y-m-d', strtotime($datum . ' +' . $week . ' weeks'));
                q('INSERT INTO proefdag_momenten (datum, begin, eind, plekken, aangemaakt_op) VALUES (?, ?, ?, ?, ?)', [$dag, $begin, $eind, $plekken, nu()]);
            }
            log_actie('Proefdagmoment toegevoegd', "{$datum} {$begin}-{$eind}, {$plekken} plekken" . ($herhaal > 1 ? ", {$herhaal} weken" : ''));
            flash('succes', $herhaal > 1 ? "Er staan {$herhaal} nieuwe momenten klaar, elke week op dezelfde dag en tijd." : 'Het nieuwe moment staat klaar. Ouders kunnen het nu kiezen op de website.');
        }
    } elseif ($actie === 'moment_verwijderen' && ($moment = rij('SELECT * FROM proefdag_momenten WHERE id = ?', [(int) invoer('id')]))) {
        $geboekt = (int) waarde("SELECT COUNT(*) FROM proefdag_boekingen WHERE moment_id = ? AND status = 'geboekt'", [$moment['id']]);
        if ($geboekt > 0) {
            flash('fout', 'Op dit moment staan nog boekingen. Laat de ouders eerst weten dat het niet doorgaat, en zet hun boeking op "Geannuleerd".');
        } else {
            foreach (rijen('SELECT id FROM proefdag_boekingen WHERE moment_id = ?', [$moment['id']]) as $boeking) {
                verwijder_proefdagboeking($boeking, 'moment verwijderd');
            }
            q('DELETE FROM proefdag_momenten WHERE id = ?', [$moment['id']]);
            log_actie('Proefdagmoment verwijderd', $moment['datum'] . ' ' . $moment['begin']);
            flash('succes', 'Het moment is verwijderd.');
        }
    } elseif ($actie === 'plekken' && ($moment = rij('SELECT * FROM proefdag_momenten WHERE id = ?', [(int) invoer('id')]))) {
        $plekken = (int) invoer('plekken');
        if ($plekken >= 1 && $plekken <= 50) {
            q('UPDATE proefdag_momenten SET plekken = ? WHERE id = ?', [$plekken, $moment['id']]);
            log_actie('Proefdagmoment: plekken aangepast', $moment['datum'] . ' ' . $moment['begin'] . ': ' . $plekken);
            flash('succes', 'Het aantal plekken is aangepast.');
        }
    } elseif ($actie === 'status' && ($boeking = rij('SELECT * FROM proefdag_boekingen WHERE id = ?', [(int) invoer('id')])) && array_key_exists(invoer('status'), PROEFDAG_STATUSSEN)) {
        q('UPDATE proefdag_boekingen SET status = ? WHERE id = ?', [invoer('status'), $boeking['id']]);
        log_actie('Proefdag: status ' . PROEFDAG_STATUSSEN[invoer('status')], 'proefdag ' . $boeking['id'], null, 'wijziging', 'proefdag:' . $boeking['id']);
        flash('succes', 'De status is nu "' . PROEFDAG_STATUSSEN[invoer('status')] . '".');
    } elseif ($actie === 'boeking_verwijderen' && ($boeking = rij('SELECT * FROM proefdag_boekingen WHERE id = ?', [(int) invoer('id')]))) {
        verwijder_proefdagboeking($boeking, 'handmatig');
        flash('succes', 'De boeking is verwijderd.');
    }
    redirect('proefdagen.php');
}

pagina_begin('Proefdagen', 'proefdagen.php');
log_inzage('Proefdagboekingen bekeken', 'proefdagen');

$grens = date('Y-m-d', strtotime('-' . PROEFDAG_BEWAAR_DAGEN . ' days'));
$momenten = rijen('SELECT * FROM proefdag_momenten WHERE datum >= ? ORDER BY datum, begin', [$grens]);
$boekingen = [];
foreach (rijen('SELECT b.* FROM proefdag_boekingen b JOIN proefdag_momenten m ON m.id = b.moment_id WHERE m.datum >= ? ORDER BY b.aangemaakt_op', [$grens]) as $b) {
    $boekingen[(int) $b['moment_id']][] = ontsleutel_kolommen($b, ['opmerking'], true);
}
$komend = array_filter($momenten, fn ($m) => $m['datum'] >= vandaag());
$geweest = array_reverse(array_filter($momenten, fn ($m) => $m['datum'] < vandaag()));

pagina_kop('Proefdagen', 'Zet momenten klaar waarop ouders een gratis proefdag kunnen boeken. Ze kiezen er zelf een op de website (vanaf morgen, tot ' . PROEFDAG_WEKEN_VOORUIT . ' weken vooruit) en krijgen een bevestiging per e-mail.');

/** Eén moment met de boekingen eronder. */
$toonMoment = function (array $moment, array $lijst, bool $voorbij): void {
    $geboekt = count(array_filter($lijst, fn ($b) => $b['status'] === 'geboekt'));
    $vrij = (int) $moment['plekken'] - $geboekt;
    ?>
  <section class="panel" aria-labelledby="moment-<?= (int) $moment['id'] ?>">
    <div class="panel__kop">
      <h2 id="moment-<?= (int) $moment['id'] ?>"><?= e(ucfirst(datum_nl($moment['datum'], 'EEEE d MMMM y'))) ?>, <?= e($moment['begin']) ?>–<?= e($moment['eind']) ?> uur</h2>
      <p class="muted"><?= $geboekt ?> van de <?= (int) $moment['plekken'] ?> plekken geboekt<?= !$voorbij ? ($vrij > 0 ? ', nog ' . $vrij . ' vrij' : ', vol') : '' ?></p>
    </div>
<?php if (!$lijst): ?>
    <p class="leeg">Nog geen boekingen.</p>
<?php else: ?>
    <div class="tabel">
      <table>
        <thead><tr><th scope="col">Ouder</th><th scope="col">Kind</th><th scope="col">Contact</th><th scope="col">Opmerking</th><th scope="col">Status</th></tr></thead>
        <tbody>
<?php foreach ($lijst as $b): ?>
          <tr>
            <th scope="row"><?= e($b['naam']) ?><br><span class="muted">geboekt <?= e(moment_nl($b['aangemaakt_op'])) ?></span></th>
            <td><?= e($b['kind_naam']) ?> (<?= e($b['kind_leeftijd']) ?>)</td>
            <td><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $b['telefoon'])) ?>"><?= e($b['telefoon']) ?></a><br><a href="mailto:<?= e($b['email']) ?>"><?= e($b['email']) ?></a></td>
            <td><?= $b['opmerking'] !== '' ? nl2br(e($b['opmerking'])) : '<span class="muted">-</span>' ?></td>
            <td>
              <form class="inline-form" method="post">
                <?= csrf_veld() ?>
                <input type="hidden" name="actie" value="status">
                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                <label class="visually-hidden" for="status-<?= (int) $b['id'] ?>">Status van de proefdag van <?= e($b['kind_naam']) ?></label>
                <select id="status-<?= (int) $b['id'] ?>" name="status">
<?php foreach (PROEFDAG_STATUSSEN as $sleutel => $label): ?>
                  <option value="<?= e($sleutel) ?>"<?= $b['status'] === $sleutel ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
                </select>
                <button class="btn btn--secondary btn--mini" type="submit">Opslaan</button>
              </form>
              <form method="post" data-bevestig="De boeking van <?= e($b['naam']) ?> verwijderen? Dit kan niet ongedaan worden gemaakt.">
                <?= csrf_veld() ?>
                <input type="hidden" name="actie" value="boeking_verwijderen">
                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                <button class="link-button" type="submit"><?= icoon('trash') ?>Verwijderen</button>
              </form>
            </td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
<?php endif; ?>
<?php if (!$voorbij): ?>
    <div class="form__acties">
      <form class="inline-form" method="post">
        <?= csrf_veld() ?>
        <input type="hidden" name="actie" value="plekken">
        <input type="hidden" name="id" value="<?= (int) $moment['id'] ?>">
        <label for="plekken-<?= (int) $moment['id'] ?>">Plekken</label>
        <input id="plekken-<?= (int) $moment['id'] ?>" name="plekken" type="number" min="1" max="50" value="<?= (int) $moment['plekken'] ?>" style="max-width: 6rem">
        <button class="btn btn--secondary btn--small" type="submit">Aanpassen</button>
      </form>
<?php if ($geboekt === 0): ?>
      <form method="post" data-bevestig="Dit moment verwijderen? Ouders kunnen het dan niet meer kiezen.">
        <?= csrf_veld() ?>
        <input type="hidden" name="actie" value="moment_verwijderen">
        <input type="hidden" name="id" value="<?= (int) $moment['id'] ?>">
        <button class="btn btn--gevaar btn--small" type="submit"><?= icoon('trash') ?>Moment verwijderen</button>
      </form>
<?php endif; ?>
    </div>
<?php endif; ?>
  </section>
<?php
};
?>
<section class="panel" aria-labelledby="nieuw-titel">
  <h2 id="nieuw-titel">Nieuw moment</h2>
  <form class="form" method="post">
    <?= csrf_veld() ?>
    <input type="hidden" name="actie" value="moment_toevoegen">
    <div class="form__row">
      <div class="field"><label for="datum">Datum</label><input id="datum" name="datum" type="date" required min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>"></div>
      <div class="field"><label for="plekken">Plekken</label><input id="plekken" name="plekken" type="number" min="1" max="50" required value="3"></div>
    </div>
    <div class="form__row">
      <!-- TODO: standaardtijden aanpassen zodra de tijden van het middagprogramma bekend zijn -->
      <div class="field"><label for="begin">Van</label><input id="begin" name="begin" type="time" required value="15:00"></div>
      <div class="field"><label for="eind">Tot</label><input id="eind" name="eind" type="time" required value="16:30"></div>
    </div>
    <div class="field">
      <label for="herhaal">Herhalen</label>
      <select id="herhaal" name="herhaal">
        <option value="1">Alleen deze datum</option>
<?php for ($weken = 2; $weken <= 12; $weken++): ?>
        <option value="<?= $weken ?>"><?= $weken ?> weken lang, elke week op deze dag</option>
<?php endfor; ?>
      </select>
    </div>
    <div class="form__acties"><button class="btn btn--small" type="submit"><?= icoon('plus') ?>Moment klaarzetten</button></div>
  </form>
</section>

<div class="stapel" style="margin-top: var(--space-l)">
<h2>Komende proefdagen</h2>
<?php if (!$komend): ?>
<section class="panel"><p class="leeg">Er staan geen momenten klaar. Zet hierboven een moment klaar, dan kunnen ouders het kiezen op de website. Zolang er niets klaarstaat, zien ouders daar een knop om contact op te nemen.</p></section>
<?php endif; ?>
<?php foreach ($komend as $moment) {
    $toonMoment($moment, $boekingen[(int) $moment['id']] ?? [], false);
} ?>

<?php if ($geweest): ?>
<details class="panel uitklap">
  <summary>Geweest (laatste <?= PROEFDAG_BEWAAR_DAGEN / 7 ?> weken)</summary>
  <div class="stapel">
<?php foreach ($geweest as $moment) {
    $toonMoment($moment, $boekingen[(int) $moment['id']] ?? [], true);
} ?>
  </div>
</details>
<?php endif; ?>
</div>
<p class="note"><?= icoon('clock') ?><span>Boekingen worden <?= PROEFDAG_BEWAAR_DAGEN / 7 ?> weken na de proefdag vanzelf verwijderd. Meldt een ouder zich aan, dan staan de gegevens in de aanmelding.</span></p>
<?php
pagina_einde();
