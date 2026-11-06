<?php
/*
 * Op de hoogte: ouders die zich via de website hebben aangemeld om op de hoogte te blijven (met het
 * Sporty-kennismakingspakket). Voor teamleden met het recht "Op de hoogte".
 * Bijhouden wie het pakket heeft gekregen, iedereen een update sturen (met een afmeldlink per persoon),
 * een CSV maken (komt in het logboek) en iemand verwijderen.
 */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_recht('interesse');
$fouten = [];
$update = ['onderwerp' => '', 'tekst' => ''];

if (get_str('export') === 'csv') {
    $veilig = fn (string $tekst): string => preg_match('/^[=+\-@\t\r]/', $tekst) ? "'" . $tekst : $tekst;
    $uit = fopen('php://temp', 'w+');
    fwrite($uit, "\xEF\xBB\xBF");
    fputcsv($uit, ['naam', 'e-mail', 'telefoon', 'aangemeld', 'bevestigd', 'kennismakingspakket'], ';');
    foreach (rijen('SELECT * FROM interesse WHERE bevestigd_op IS NOT NULL ORDER BY aangemaakt_op') as $r) {
        fputcsv($uit, [$veilig($r['naam']), $veilig($r['email']), $veilig($r['telefoon']), substr($r['aangemaakt_op'], 0, 10), substr((string) $r['bevestigd_op'], 0, 10), $r['pakket_op'] ? substr($r['pakket_op'], 0, 10) : ''], ';');
    }
    rewind($uit);
    $csv = stream_get_contents($uit);
    log_export('Lijst Blijf op de hoogte geëxporteerd', '');
    stuur_download($csv, 'blijf-op-de-hoogte-' . vandaag() . '.csv', 'text/csv');
}

if (is_post()) {
    csrf_controleer();
    $actie = invoer('actie');
    $rij = rij('SELECT * FROM interesse WHERE id = ?', [(int) invoer('id')]);

    if ($actie === 'pakket' && $rij) {
        q('UPDATE interesse SET pakket_op = ? WHERE id = ?', [$rij['pakket_op'] ? null : nu(), $rij['id']]);
        log_actie($rij['pakket_op'] ? 'Kennismakingspakket: toch nog niet gegeven' : 'Kennismakingspakket gegeven', '', null, 'wijziging', 'interesse:' . $rij['id']);
        flash('succes', $rij['pakket_op'] ? 'Aangepast: ' . $rij['naam'] . ' heeft het pakket nog niet.' : 'Genoteerd: ' . $rij['naam'] . ' heeft het kennismakingspakket.');
        redirect('interesse.php');
    }
    if ($actie === 'verwijderen' && $rij) {
        interesse_verwijder($rij, 'door het team');
        flash('succes', 'De gegevens van ' . $rij['naam'] . ' zijn gewist.');
        redirect('interesse.php');
    }
    if ($actie === 'update') {
        $update = ['onderwerp' => mb_substr(invoer('onderwerp'), 0, 150), 'tekst' => mb_substr(invoer('tekst'), 0, 8000)];
        if ($update['onderwerp'] === '') {
            $fouten['onderwerp'] = 'Vul een onderwerp in.';
        }
        if ($update['tekst'] === '') {
            $fouten['tekst'] = 'Schrijf je bericht.';
        }
        $ontvangers = rijen('SELECT * FROM interesse WHERE bevestigd_op IS NOT NULL ORDER BY id');
        if (!$ontvangers) {
            $fouten['onderwerp'] = 'Er staat nog niemand (bevestigd) op de lijst.';
        }
        if (!$fouten) {
            $gelukt = 0;
            foreach ($ontvangers as $r) {
                $voornaam = explode(' ', trim($r['naam']))[0];
                $gelukt += stuur_mail($r['email'], $update['onderwerp'], "Hoi {$voornaam},\n\n" . $update['tekst'] . interesse_voet($r)) ? 1 : 0;
            }
            log_actie('Update verstuurd aan Blijf op de hoogte', $update['onderwerp'] . " ({$gelukt} van " . count($ontvangers) . ')');
            $totaal = count($ontvangers);
            flash($gelukt === $totaal ? 'succes' : 'fout', $gelukt === $totaal
                ? 'De update is verstuurd naar ' . ($totaal === 1 ? '1 persoon.' : "alle {$totaal} mensen op de lijst.")
                : "De update is verstuurd naar {$gelukt} van de {$totaal} mensen. Bij de rest lukte het versturen niet; probeer het later nog eens.");
            redirect('interesse.php');
        }
    }
}

$lijst = rijen('SELECT * FROM interesse ORDER BY bevestigd_op IS NULL, aangemaakt_op DESC');
$bevestigd = array_filter($lijst, fn ($r) => $r['bevestigd_op'] !== null);
$wachten = count($lijst) - count($bevestigd);
$zonderPakket = count(array_filter($bevestigd, fn ($r) => $r['pakket_op'] === null));

pagina_begin('Op de hoogte', 'interesse.php');
pagina_kop('Op de hoogte', 'Ouders die via de website op de hoogte willen blijven. Ze krijgen een Sporty-kennismakingspakket. Op de lijst staat alleen wie de aanmelding in de e-mail heeft bevestigd; afmelden kan met de link onder elke e-mail.',
    $bevestigd ? '<a class="btn btn--secondary btn--small" href="interesse.php?export=csv">' . icoon('download') . 'Lijst als CSV</a>' : '');
?>
<?= foutensamenvatting($fouten) ?>
<section class="panel" aria-labelledby="lijst-titel">
  <h2 id="lijst-titel"><?= count($bevestigd) ?> op de lijst<?= $zonderPakket ? ', ' . $zonderPakket . ' nog zonder pakket' : '' ?></h2>
<?php if ($wachten): ?>
  <p class="muted"><?= $wachten ?> <?= $wachten === 1 ? 'aanmelding wacht' : 'aanmeldingen wachten' ?> nog op bevestiging. Wordt een aanmelding niet binnen <?= INTERESSE_ONBEVESTIGD_DAGEN ?> dagen bevestigd, dan wissen we de gegevens vanzelf.</p>
<?php endif; ?>
<?php if (!$lijst): ?>
  <p class="leeg">Nog niemand. Op de homepage en in de footer van de website staat de knop "Blijf op de hoogte!".</p>
<?php else: ?>
  <div class="tabel">
    <table>
      <thead><tr><th scope="col">Naam</th><th scope="col">Contact</th><th scope="col">Aangemeld</th><th scope="col">Status</th><th scope="col">Kennismakingspakket</th><th scope="col"><span class="visually-hidden">Verwijderen</span></th></tr></thead>
      <tbody>
<?php foreach ($lijst as $r): ?>
        <tr>
          <th scope="row"><?= e($r['naam']) ?></th>
          <td><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a><?php if ($r['telefoon'] !== ''): ?><br><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $r['telefoon'])) ?>"><?= e($r['telefoon']) ?></a><?php endif; ?></td>
          <td><?= e(datum_nl($r['aangemaakt_op'])) ?></td>
          <td><?= $r['bevestigd_op'] ? '<span class="badge badge--actief">Bevestigd</span>' : '<span class="badge badge--nieuw">Wacht op bevestiging</span>' ?></td>
          <td>
<?php if ($r['bevestigd_op']): ?>
            <form class="inline-form" method="post"><?= csrf_veld() ?><input type="hidden" name="actie" value="pakket"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <?= $r['pakket_op'] ? icoon('check') . ' Gekregen op ' . e(datum_nl($r['pakket_op'])) . ' <button class="link-button" type="submit">Ongedaan maken<span class="visually-hidden"> voor ' . e($r['naam']) . '</span></button>' : '<button class="btn btn--secondary btn--mini" type="submit">Pakket gegeven<span class="visually-hidden"> aan ' . e($r['naam']) . '</span></button>' ?>
            </form>
<?php else: ?>
            <span class="muted">Na bevestiging</span>
<?php endif; ?>
          </td>
          <td><form class="inline-form" method="post" data-bevestig="De gegevens van <?= e($r['naam']) ?> wissen? Deze persoon krijgt dan geen updates meer."><?= csrf_veld() ?><input type="hidden" name="actie" value="verwijderen"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="link-button" type="submit"><?= icoon('trash') ?>Wissen<span class="visually-hidden"> <?= e($r['naam']) ?></span></button></form></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</section>

<section class="panel" aria-labelledby="update-titel" style="margin-top: var(--space-l)">
  <h2 id="update-titel">Update sturen</h2>
  <p class="muted">Gaat naar iedereen die bevestigd op de lijst staat (<?= count($bevestigd) ?>), elk in een eigen e-mail die begint met "Hoi" en de voornaam. Onder elke e-mail staat een persoonlijke afmeldlink.</p>
  <form class="form" method="post" data-bevestig="Deze update naar <?= count($bevestigd) ?> <?= count($bevestigd) === 1 ? 'persoon' : 'mensen' ?> sturen?">
    <?= csrf_veld() ?>
    <input type="hidden" name="actie" value="update">
    <div class="field"><label for="onderwerp">Onderwerp</label><input id="onderwerp" name="onderwerp" maxlength="150" required value="<?= e($update['onderwerp']) ?>"<?= aria_fout($fouten, 'onderwerp') ?>><?= veldfout($fouten, 'onderwerp') ?></div>
    <div class="field"><label for="tekst">Bericht</label><textarea id="tekst" name="tekst" rows="7" maxlength="8000" required<?= aria_fout($fouten, 'tekst') ?>><?= e($update['tekst']) ?></textarea><?= veldfout($fouten, 'tekst') ?></div>
    <div class="form__acties"><button class="btn" type="submit"<?= $bevestigd ? '' : ' disabled' ?>><?= icoon('send') ?>Update versturen</button></div>
  </form>
</section>
<p class="note"><?= icoon('clock') ?><span>We bewaren de gegevens <?= bewaartermijn('bewaar_interesse_maanden') ?> maanden na het aanmelden (in te stellen bij Instellingen → Bewaartermijnen), of korter als iemand zich afmeldt.</span></p>
<?php pagina_einde();
