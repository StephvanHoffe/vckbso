<?php
/*
 * Financiën: inkomsten en uitgaven per jaar of maand.
 * Betaalde facturen tellen automatisch mee als inkomsten; overige inkomsten en alle
 * uitgaven voer je hier in, eventueel met een bonnetje. Export als CSV voor de boekhouder.
 */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_recht('financien');

/* ---------- Bonnetje openen ---------- */

if (get_int('bijlage') > 0) {
    $boeking = rij('SELECT * FROM boekingen WHERE id = ? AND bijlage IS NOT NULL', [get_int('bijlage')]);
    if (!$boeking) {
        niet_gevonden();
    }
    $inhoud = lees_versleuteld_bestand(bijlage_map() . '/' . $boeking['bijlage'] . '.bin');
    if ($inhoud === null) {
        niet_gevonden('Dit bonnetje is niet (meer) beschikbaar.');
    }
    log_actie('Bonnetje geopend', 'boeking ' . $boeking['id'], null, 'inzage');
    header_remove('Cache-Control');
    header('Cache-Control: no-store, private');
    header('Content-Type: ' . $boeking['bijlage_type']);
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $boeking['bijlage_naam']) . '"');
    header('Content-Length: ' . strlen($inhoud));
    echo $inhoud;
    exit;
}

/* ---------- Periode ---------- */

$jaren = financien_jaren();
$jaar = get_int('jaar') ?: (int) date('Y');
if ($jaar < 2000 || $jaar > (int) date('Y') + 1) {
    $jaar = (int) date('Y');
}
$maand = get_int('maand');
$maand = $maand >= 1 && $maand <= 12 ? $maand : 0;
$van = $maand ? sprintf('%04d-%02d-01', $jaar, $maand) : "{$jaar}-01-01";
$tot = $maand ? date('Y-m-t', strtotime($van)) : "{$jaar}-12-31";
$periodeNaam = $maand ? datum_nl($van, 'MMMM y') : (string) $jaar;
$hier = 'financien.php?jaar=' . $jaar . ($maand ? '&maand=' . $maand : '');

/* ---------- Export voor de boekhouder ---------- */

if (get_str('export') === 'csv') {
    // Tekst die met = + - @ begint, zou Excel als formule uitvoeren: met een ' ervoor blijft het tekst
    $veilig = fn (string $tekst): string => preg_match('/^[=+\-@\t\r]/', $tekst) ? "'" . $tekst : $tekst;
    $uit = fopen('php://temp', 'w+');
    fwrite($uit, "\xEF\xBB\xBF"); // zodat Excel de tekens goed leest
    fputcsv($uit, ['datum', 'soort', 'categorie', 'omschrijving', 'bedrag', 'bron', 'factuurnummer', 'bonnetje'], ';');
    $totExclusief = date('Y-m-d', strtotime($tot . ' +1 day'));
    foreach (rijen("SELECT * FROM facturen WHERE status = 'betaald' AND betaald_op >= ? AND betaald_op < ? ORDER BY betaald_op", [$van, $totExclusief]) as $f) {
        fputcsv($uit, [substr($f['betaald_op'], 0, 10), 'inkomst', CATEGORIE_FACTUREN, 'Factuur ' . $f['nummer'] . ' (' . $f['periode'] . ')', cent_naar_euro((int) $f['bedrag_cent']), 'factuur', $f['nummer'], ''], ';');
    }
    foreach (rijen('SELECT * FROM boekingen WHERE datum >= ? AND datum <= ? ORDER BY datum, id', [$van, $tot]) as $b) {
        fputcsv($uit, [$b['datum'], $b['soort'], categorie_label($b['soort'], $b['categorie']), $veilig($b['omschrijving']), ($b['soort'] === 'uitgave' ? '-' : '') . cent_naar_euro((int) $b['bedrag_cent']), 'boeking', '', $b['bijlage'] ? $veilig($b['bijlage_naam']) : ''], ';');
    }
    rewind($uit);
    $csv = stream_get_contents($uit);
    log_export('Financiën geëxporteerd', '', "{$van} t/m {$tot}");
    stuur_download($csv, 'financien-' . ($maand ? substr($van, 0, 7) : $jaar) . '.csv', 'text/csv');
}

/* ---------- Boekingen invoeren, wijzigen en verwijderen ---------- */

$fouten = [];
$bewerk = get_int('bewerk') ? rij('SELECT * FROM boekingen WHERE id = ?', [get_int('bewerk')]) : null;
$waarden = [
    'categorie' => $bewerk ? $bewerk['soort'] . ':' . $bewerk['categorie'] : 'uitgave:overig',
    'datum' => $bewerk['datum'] ?? vandaag(),
    'omschrijving' => $bewerk['omschrijving'] ?? '',
    'bedrag' => $bewerk ? cent_naar_euro((int) $bewerk['bedrag_cent']) : '',
];

if (is_post()) {
    csrf_controleer();
    $actie = invoer('actie');

    if ($actie === 'verwijderen') {
        $boeking = rij('SELECT * FROM boekingen WHERE id = ?', [(int) invoer('boeking')]);
        if (!$boeking) {
            niet_gevonden();
        }
        q('DELETE FROM boekingen WHERE id = ?', [$boeking['id']]);
        verwijder_bijlage($boeking['bijlage']);
        log_actie('Boeking verwijderd', $boeking['soort'] . ' ' . $boeking['datum'] . ' ' . geld((int) $boeking['bedrag_cent']) . ' ' . $boeking['omschrijving'], null, 'wijziging');
        flash('succes', 'De boeking is verwijderd.');
        redirect($hier . '#boekingen');
    }

    if (in_array($actie, ['toevoegen', 'wijzigen'], true)) {
        $boeking = $actie === 'wijzigen' ? rij('SELECT * FROM boekingen WHERE id = ?', [(int) invoer('boeking')]) : null;
        if ($actie === 'wijzigen' && !$boeking) {
            niet_gevonden();
        }
        $bewerk = $boeking;
        foreach (array_keys($waarden) as $veld) {
            $waarden[$veld] = mb_substr(invoer($veld), 0, 200);
        }
        [$soort, $categorie] = array_pad(explode(':', $waarden['categorie'], 2), 2, '');
        $lijst = $soort === 'inkomst' ? INKOMST_CATEGORIEEN : ($soort === 'uitgave' ? UITGAVE_CATEGORIEEN : []);
        if (!isset($lijst[$categorie])) {
            $fouten['categorie'] = 'Kies een categorie.';
        }
        if (!geldige_datum($waarden['datum']) || $waarden['datum'] < '2000-01-01' || $waarden['datum'] > date('Y-m-d', strtotime('+1 year'))) {
            $fouten['datum'] = 'Vul een geldige datum in.';
        }
        $bedrag = bedrag_naar_cent($waarden['bedrag']);
        if ($bedrag === null) {
            $fouten['bedrag'] = 'Vul het bedrag in, bijvoorbeeld 12,50 of 1.250,00.';
        }
        $nieuweBijlage = null;
        if (!$fouten && ($_FILES['bijlage']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $nieuweBijlage = bewaar_bijlage($_FILES['bijlage']);
            } catch (RuntimeException $fout) {
                $fouten['bijlage'] = $fout->getMessage();
            }
        }
        if (!$fouten) {
            $oudeBijlage = $boeking['bijlage'] ?? null;
            $bijlage = $nieuweBijlage ? $nieuweBijlage : ($boeking && invoer('bijlage_weg') !== '1' && $oudeBijlage ? [$oudeBijlage, $boeking['bijlage_naam'], $boeking['bijlage_type']] : [null, null, null]);
            if ($boeking) {
                q('UPDATE boekingen SET soort = ?, datum = ?, categorie = ?, omschrijving = ?, bedrag_cent = ?, bijlage = ?, bijlage_naam = ?, bijlage_type = ?, gewijzigd_op = ? WHERE id = ?', [
                    $soort, $waarden['datum'], $categorie, $waarden['omschrijving'], $bedrag, $bijlage[0], $bijlage[1], $bijlage[2], nu(), $boeking['id'],
                ]);
                if ($oudeBijlage && $oudeBijlage !== $bijlage[0]) {
                    verwijder_bijlage($oudeBijlage);
                }
                log_actie('Boeking gewijzigd', $soort . ' ' . $waarden['datum'] . ' ' . geld($bedrag) . ' ' . $waarden['omschrijving'], null, 'wijziging');
                flash('succes', 'De boeking is gewijzigd.');
            } else {
                q('INSERT INTO boekingen (soort, datum, categorie, omschrijving, bedrag_cent, bijlage, bijlage_naam, bijlage_type, aangemaakt_door, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                    $soort, $waarden['datum'], $categorie, $waarden['omschrijving'], $bedrag, $bijlage[0], $bijlage[1], $bijlage[2], $gebruiker['id'], nu(),
                ]);
                log_actie('Boeking toegevoegd', $soort . ' ' . $waarden['datum'] . ' ' . geld($bedrag) . ' ' . $waarden['omschrijving'], null, 'wijziging');
                flash('succes', ($soort === 'inkomst' ? 'De inkomst' : 'De uitgave') . ' van ' . geld($bedrag) . ' is toegevoegd.');
            }
            // Naar de periode van de boeking, zodat je hem meteen ziet
            redirect('financien.php?jaar=' . substr($waarden['datum'], 0, 4) . ($maand ? '&maand=' . (int) substr($waarden['datum'], 5, 2) : '') . '#boekingen');
        }
    }
}

$o = financieel_overzicht($van, $tot);
$boekingen = rijen('SELECT b.*, g.naam AS door FROM boekingen b LEFT JOIN gebruikers g ON g.id = b.aangemaakt_door WHERE b.datum >= ? AND b.datum <= ? ORDER BY b.datum DESC, b.id DESC', [$van, $tot]);

pagina_begin('Financiën', 'financien.php');
pagina_kop('Financiën', 'Wat er binnenkwam en wat eruit ging. Betaalde facturen tellen automatisch mee op de dag van betaling; andere inkomsten en alle uitgaven voer je hieronder in.',
    '<a class="btn btn--secondary btn--small" href="' . e($hier . '&export=csv') . '">' . icoon('download') . 'Exporteren voor de boekhouder</a>');
?>
<form class="filters" method="get" action="financien.php" aria-label="Periode kiezen">
  <div class="field"><label for="jaar">Jaar</label><select id="jaar" name="jaar">
<?php foreach ($jaren as $j): ?>
    <option value="<?= $j ?>"<?= $j === $jaar ? ' selected' : '' ?>><?= $j ?></option>
<?php endforeach; ?>
  </select></div>
  <div class="field"><label for="maand">Periode</label><select id="maand" name="maand">
    <option value="0">Hele jaar</option>
<?php for ($m = 1; $m <= 12; $m++): ?>
    <option value="<?= $m ?>"<?= $m === $maand ? ' selected' : '' ?>><?= e(ucfirst(datum_nl(sprintf('2026-%02d-01', $m), 'MMMM'))) ?></option>
<?php endfor; ?>
  </select></div>
  <button class="btn btn--secondary btn--small" type="submit">Toon</button>
</form>

<h2 class="visually-hidden">Samenvatting <?= e($periodeNaam) ?></h2>
<ul class="tegels tegels--bedragen">
  <li><span class="tegel" style="--tegel-bg: var(--color-mint-soft)"><span class="tegel__getal"><?= e(geld($o['inkomsten'])) ?></span><span class="tegel__label">inkomsten <?= e($periodeNaam) ?></span></span></li>
  <li><span class="tegel" style="--tegel-bg: var(--color-coral-soft)"><span class="tegel__getal"><?= e(geld($o['uitgaven'])) ?></span><span class="tegel__label">uitgaven <?= e($periodeNaam) ?></span></span></li>
  <li><span class="tegel" style="--tegel-bg: var(--color-sun-soft)"><span class="tegel__getal"><?= e(geld($o['resultaat'])) ?></span><span class="tegel__label"><?= $o['resultaat'] < 0 ? 'tekort' : 'resultaat' ?> <?= e($periodeNaam) ?></span></span></li>
  <li><<?= team_mag('facturen', $gebruiker) ? 'a class="tegel" href="facturen.php"' : 'span class="tegel"' ?>><span class="tegel__getal"><?= e(geld($o['openstaand'])) ?></span><span class="tegel__label">nog te ontvangen (<?= $o['openstaand_aantal'] ?> <?= $o['openstaand_aantal'] === 1 ? 'factuur' : 'facturen' ?>)</span></<?= team_mag('facturen', $gebruiker) ? 'a' : 'span' ?>></li>
</ul>
<p class="muted financien__uitleg">Gefactureerd in deze periode: <?= e(geld($o['gefactureerd'])) ?>. Daarvan is betaald wat binnenkwam; wat nog openstaat, telt pas mee als het betaald is.</p>

<?php if ($o['per_maand']): ?>
<section class="panel" aria-labelledby="grafiek-titel">
  <div class="panel__kop"><h2 id="grafiek-titel">Per maand in <?= $jaar ?></h2></div>
  <p id="grafiek-uitleg" class="visually-hidden">Kolomgrafiek met per maand de inkomsten en de uitgaven. Alle bedragen staan ook in de tabel onder de grafiek.</p>
  <ul class="legenda" aria-label="Legenda">
    <li><span class="legenda__sleutel legenda__sleutel--in" aria-hidden="true"></span>Inkomsten</li>
    <li><span class="legenda__sleutel legenda__sleutel--uit" aria-hidden="true"></span>Uitgaven</li>
  </ul>
  <div class="grafiek" data-grafiek><?= grafiek_per_maand($o['per_maand']) ?></div>
  <details class="uitklap">
    <summary>Bekijk als tabel</summary>
    <div class="tabel">
      <table>
        <thead><tr><th scope="col">Maand</th><th scope="col" class="getal">Inkomsten</th><th scope="col" class="getal">Uitgaven</th><th scope="col" class="getal">Resultaat</th></tr></thead>
        <tbody>
<?php foreach ($o['per_maand'] as $m => $bedragen): ?>
          <tr><th scope="row"><a href="financien.php?jaar=<?= $jaar ?>&amp;maand=<?= (int) substr($m, 5, 2) ?>"><?= e(ucfirst(datum_nl($m . '-01', 'MMMM'))) ?></a></th><td class="getal"><?= e(geld($bedragen['inkomsten'])) ?></td><td class="getal"><?= e(geld($bedragen['uitgaven'])) ?></td><td class="getal"><?= e(geld($bedragen['inkomsten'] - $bedragen['uitgaven'])) ?></td></tr>
<?php endforeach; ?>
        </tbody>
        <tfoot><tr><th scope="row">Totaal</th><td class="getal"><?= e(geld($o['inkomsten'])) ?></td><td class="getal"><?= e(geld($o['uitgaven'])) ?></td><td class="getal"><?= e(geld($o['resultaat'])) ?></td></tr></tfoot>
      </table>
    </div>
  </details>
</section>
<?php endif; ?>

<div class="kolommen kolommen--gelijk">
<?php foreach (['uitgave' => ['Uitgaven per categorie', 'uit'], 'inkomst' => ['Inkomsten per soort', 'in']] as $soort => [$titel, $klasse]): $rijen = $o['per_categorie'][$soort]; $totaal = max(1, array_sum(array_column($rijen, 'bedrag'))); ?>
  <section class="panel" aria-labelledby="cat-<?= $soort ?>">
    <h2 id="cat-<?= $soort ?>"><?= e($titel) ?></h2>
<?php if (!$rijen): ?>
    <p class="leeg"><?= $soort === 'uitgave' ? 'Nog geen uitgaven in deze periode.' : 'Nog geen inkomsten in deze periode.' ?></p>
<?php else: ?>
    <ul class="verdeling">
<?php foreach ($rijen as $rij): $procent = round($rij['bedrag'] / $totaal * 100); ?>
      <li>
        <div class="verdeling__regel"><span><?= e($rij['label']) ?> <small class="muted">(<?= $rij['aantal'] ?>)</small></span><strong><?= e(geld($rij['bedrag'])) ?></strong></div>
        <div class="verdeling__balk" aria-hidden="true"><span class="verdeling__vulling verdeling__vulling--<?= $klasse ?>" style="width: <?= max(1, $procent) ?>%"></span></div>
        <span class="visually-hidden"><?= $procent ?> procent</span>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </section>
<?php endforeach; ?>
</div>

<div class="kolommen">
  <section class="panel" id="boekingen" aria-labelledby="boekingen-titel">
    <div class="panel__kop"><h2 id="boekingen-titel">Boekingen <?= e($periodeNaam) ?></h2><span class="muted"><?= count($boekingen) ?> <?= count($boekingen) === 1 ? 'boeking' : 'boekingen' ?></span></div>
<?php if (!$boekingen): ?>
    <p class="leeg">Nog niets ingevoerd in deze periode. Betaalde facturen tellen vanzelf mee.</p>
<?php else: ?>
    <ul class="kindlijst">
<?php foreach ($boekingen as $b): $naam = $b['omschrijving'] !== '' ? $b['omschrijving'] : categorie_label($b['soort'], $b['categorie']); ?>
      <li class="kindrij boeking">
        <div>
          <div class="boeking__kop"><span class="kindrij__naam"><?= e($naam) ?></span><span class="bedrag bedrag--<?= $b['soort'] === 'inkomst' ? 'in' : 'uit' ?>"><?= $b['soort'] === 'inkomst' ? '+' : '−' ?> <?= e(geld((int) $b['bedrag_cent'])) ?></span></div>
          <div class="kindrij__info">
            <span><?= e(datum_nl($b['datum'], 'd MMMM y')) ?></span>
            <span><?= e(categorie_label($b['soort'], $b['categorie'])) ?></span>
<?php if ($b['door']): ?>            <span><?= e($b['door']) ?></span>
<?php endif; ?>
<?php if ($b['bijlage']): ?>            <a class="bonnetje" href="financien.php?bijlage=<?= (int) $b['id'] ?>"><?= icoon('clip') ?><?= e($b['bijlage_naam']) ?></a>
<?php endif; ?>
          </div>
        </div>
        <div class="btn-group">
          <a class="btn btn--secondary btn--mini" href="<?= e($hier . '&bewerk=' . (int) $b['id']) ?>#boeking-form">Wijzigen<span class="visually-hidden"> (<?= e($naam) ?>, <?= e(datum_nl($b['datum'])) ?>)</span></a>
          <form class="inline-form" method="post" action="<?= e($hier) ?>" data-bevestig="Deze boeking verwijderen?<?= $b['bijlage'] ? ' Het bonnetje wordt ook verwijderd.' : '' ?>"><?= csrf_veld() ?><input type="hidden" name="actie" value="verwijderen"><input type="hidden" name="boeking" value="<?= (int) $b['id'] ?>"><button class="btn btn--gevaar btn--mini" type="submit">Verwijderen<span class="visually-hidden"> (<?= e($naam) ?>, <?= e(datum_nl($b['datum'])) ?>)</span></button></form>
        </div>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </section>

  <section class="panel" id="boeking-form" aria-labelledby="boeking-form-titel">
    <h2 id="boeking-form-titel"><?= $bewerk ? 'Boeking wijzigen' : 'Inkomst of uitgave invoeren' ?></h2>
    <?= foutensamenvatting($fouten) ?>
    <form class="form" method="post" action="<?= e($hier) ?>#boeking-form" enctype="multipart/form-data">
      <?= csrf_veld() ?>
      <input type="hidden" name="actie" value="<?= $bewerk ? 'wijzigen' : 'toevoegen' ?>">
<?php if ($bewerk): ?><input type="hidden" name="boeking" value="<?= (int) $bewerk['id'] ?>"><?php endif; ?>
      <div class="field">
        <label for="categorie">Categorie</label>
        <select id="categorie" name="categorie"<?= aria_fout($fouten, 'categorie') ?>>
          <optgroup label="Uitgaven">
<?php foreach (UITGAVE_CATEGORIEEN as $sleutel => $label): ?>
            <option value="uitgave:<?= e($sleutel) ?>"<?= $waarden['categorie'] === 'uitgave:' . $sleutel ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
          </optgroup>
          <optgroup label="Inkomsten (betaalde facturen tellen vanzelf mee)">
<?php foreach (INKOMST_CATEGORIEEN as $sleutel => $label): ?>
            <option value="inkomst:<?= e($sleutel) ?>"<?= $waarden['categorie'] === 'inkomst:' . $sleutel ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
          </optgroup>
        </select>
        <?= veldfout($fouten, 'categorie') ?>
      </div>
      <div class="form__row">
        <div class="field"><label for="datum">Datum</label><input id="datum" name="datum" type="date" required value="<?= e($waarden['datum']) ?>"<?= aria_fout($fouten, 'datum') ?>><?= veldfout($fouten, 'datum') ?></div>
        <div class="field"><label for="bedrag">Bedrag in euro</label><input id="bedrag" name="bedrag" inputmode="decimal" autocomplete="off" required value="<?= e($waarden['bedrag']) ?>"<?= aria_fout($fouten, 'bedrag') ?>><?= veldfout($fouten, 'bedrag') ?></div>
      </div>
      <div class="field"><label for="omschrijving">Omschrijving</label><input id="omschrijving" name="omschrijving" maxlength="200" value="<?= e($waarden['omschrijving']) ?>" placeholder="Bijvoorbeeld: boodschappen fruit en brood"></div>
      <div class="field">
        <label for="bijlage">Bonnetje of factuur <span class="field__hint">(optioneel: pdf, jpg of png, maximaal 10 MB)</span></label>
        <input id="bijlage" name="bijlage" type="file" accept="application/pdf,image/jpeg,image/png"<?= aria_fout($fouten, 'bijlage') ?>>
        <?= veldfout($fouten, 'bijlage') ?>
<?php if ($bewerk && $bewerk['bijlage']): ?>
        <div class="consent" style="margin-top: var(--space-2xs)"><input id="bijlage_weg" name="bijlage_weg" type="checkbox" value="1"><label for="bijlage_weg">Huidig bonnetje verwijderen (<?= e($bewerk['bijlage_naam']) ?>)</label></div>
<?php endif; ?>
      </div>
      <div class="form__acties">
        <button class="btn" type="submit"><?= icoon($bewerk ? 'check' : 'plus') ?><?= $bewerk ? 'Wijziging opslaan' : 'Toevoegen' ?></button>
<?php if ($bewerk): ?><a class="btn btn--secondary" href="<?= e($hier) ?>#boekingen">Annuleren</a><?php endif; ?>
      </div>
      <p class="muted" style="margin: 0">Vul bedragen in zoals ze op de bon staan. Bonnetjes worden versleuteld opgeslagen en, net als de facturen, <?= (int) instelling('bewaar_financieel_jaren', '7') ?> jaar bewaard.</p>
    </form>
  </section>
</div>
<?php
pagina_einde();
