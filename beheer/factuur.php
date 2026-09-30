<?php
/* Eén factuur bekijken en printen. Ouders kunnen een open of mislukte factuur los betalen. */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_login(['beheerder', 'ouder']);
$factuur = factuur_met_toegang(get_int('id'));

// Terug van een losse betaling: status meteen ophalen
if (get_str('betaald') === '1' && mollie_actief()) {
    $laatste = rij("SELECT mollie_id FROM betalingen WHERE factuur_id = ? AND soort = 'eenmalig' ORDER BY id DESC LIMIT 1", [$factuur['id']]);
    if ($laatste) {
        try {
            verwerk_betaling($laatste['mollie_id']);
        } catch (RuntimeException $fout) {
            error_log('Betaling controleren: ' . $fout->getMessage());
        }
    }
    $factuur = factuur_met_toegang((int) $factuur['id']);
    flash($factuur['status'] === 'betaald' ? 'succes' : 'info', $factuur['status'] === 'betaald' ? 'Bedankt, de factuur is betaald!' : 'We hebben de betaling nog niet binnen. Het kan even duren voordat je bank hem bevestigt.');
    redirect('factuur.php?id=' . (int) $factuur['id']);
}

if (is_post() && is_ouder($gebruiker)) {
    csrf_controleer();
    if (in_array($factuur['status'], ['open', 'mislukt'], true) && mollie_actief()) {
        try {
            redirect(start_eenmalige_betaling($factuur));
        } catch (RuntimeException $fout) {
            error_log('Losse betaling starten: ' . $fout->getMessage());
            flash('fout', 'Het lukte even niet om de betaling te starten. Probeer het later opnieuw.');
        }
    }
    redirect('factuur.php?id=' . (int) $factuur['id']);
}

$regels = rijen('SELECT * FROM factuurregels WHERE factuur_id = ? ORDER BY id', [$factuur['id']]);

pagina_begin('Factuur ' . $factuur['nummer'], 'facturen.php');
echo '<a class="terug-link niet-printen" href="facturen.php' . (is_beheerder($gebruiker) ? '?periode=' . e($factuur['periode']) : '') . '">' . icoon('back') . 'Alle facturen</a>';
?>
<article class="panel factuur" aria-labelledby="factuur-titel">
  <div class="factuur__kop">
    <div>
      <h1 id="factuur-titel" style="font-size: var(--step-3)">Factuur <?= e($factuur['nummer']) ?></h1>
      <p><?= status_badge($factuur['status']) ?></p>
      <address>
        <strong><?= e($factuur['naam']) ?></strong><br>
        <?= e($factuur['straat']) ?><br>
        <?= e(trim($factuur['postcode'] . ' ' . $factuur['plaats'])) ?>
      </address>
    </div>
    <address>
      <strong><?= e(instelling('bedrijfsnaam', 'BSO VCK')) ?></strong><br>
      <?= e(instelling('statutaire_naam')) ?><br>
<?php if (instelling('adres') !== ''): ?><?= e(instelling('adres')) ?><br><?php endif; ?>
      <?= e(instelling('postcode_plaats')) ?><br>
      <?= e(instelling('email')) ?> · <?= e(instelling('telefoon')) ?><br>
      KvK: <?= e(instelling('kvk') ?: 'volgt') ?><br>
      LRK: <?= e(instelling('lrk') ?: 'volgt') ?>
<?php if (instelling('iban') !== ''): ?><br>IBAN: <?= e(instelling('iban')) ?><?php endif; ?>
    </address>
  </div>
  <dl class="gegevens" style="margin-bottom: var(--space-l)">
    <dt>Factuurdatum</dt><dd><?= e(datum_nl($factuur['datum'])) ?></dd>
    <dt>Periode</dt><dd><?= e(datum_nl($factuur['periode'] . '-01', 'MMMM y')) ?></dd>
    <dt>Te betalen voor</dt><dd><?= e(datum_nl($factuur['vervaldatum'])) ?></dd>
  </dl>
  <div class="tabel">
    <table>
      <caption class="visually-hidden">Factuurregels</caption>
      <thead><tr><th scope="col">Omschrijving</th><th scope="col" class="rechts">Uren</th><th scope="col" class="rechts">Uurtarief</th><th scope="col" class="rechts">Bedrag</th></tr></thead>
      <tbody>
<?php foreach ($regels as $regel): ?>
        <tr><td><?= e($regel['omschrijving']) ?></td><td class="rechts"><?= e(uren_nl((float) $regel['aantal'])) ?></td><td class="rechts"><?= e(geld((int) $regel['prijs_cent'])) ?></td><td class="rechts"><?= e(geld((int) $regel['bedrag_cent'])) ?></td></tr>
<?php endforeach; ?>
      </tbody>
      <tfoot><tr class="factuur__totaal"><th scope="row" colspan="3">Totaal</th><td class="rechts"><?= e(geld((int) $factuur['bedrag_cent'])) ?></td></tr></tfoot>
    </table>
  </div>
  <!-- TODO: btw-regel bevestigen met de klant/boekhouder. Kinderopvang is doorgaans vrijgesteld van btw. -->
  <p class="muted">Kinderopvang is vrijgesteld van btw.</p>
<?php if ($factuur['status'] === 'betaald'): ?>
  <p><strong>Betaald op <?= e(datum_nl($factuur['betaald_op'])) ?>.</strong> Bedankt!</p>
<?php elseif ($factuur['status'] === 'incasso'): ?>
  <p>Dit bedrag wordt automatisch afgeschreven van rekening <?= e($factuur['mandaat_rekening']) ?>. Je hoeft niets te doen.</p>
<?php elseif ($factuur['status'] === 'open' && in_array($factuur['mandaat_status'], ['geldig', 'demo'], true)): ?>
  <p>Dit bedrag wordt binnenkort automatisch afgeschreven van rekening <?= e($factuur['mandaat_rekening']) ?>.</p>
<?php elseif (in_array($factuur['status'], ['open', 'mislukt'], true)): ?>
  <p><?= $factuur['status'] === 'mislukt' ? 'Het automatisch afschrijven is niet gelukt.' : 'Er is (nog) geen machtiging voor automatische incasso.' ?>
<?php if (instelling('iban') !== ''): ?> Je kunt het bedrag overmaken naar <?= e(instelling('iban')) ?> t.n.v. <?= e(instelling('statutaire_naam')) ?>, onder vermelding van <?= e($factuur['nummer']) ?>.<?php endif; ?></p>
<?php endif; ?>
<?php if ($factuur['notitie'] !== '' && is_beheerder($gebruiker)): ?>
  <p class="muted"><?= e($factuur['notitie']) ?></p>
<?php endif; ?>
  <div class="btn-group niet-printen" style="margin-top: var(--space-l)">
    <button class="btn btn--secondary btn--small" type="button" hidden data-print><?= icoon('print') ?>Printen of opslaan als pdf</button>
<?php if (is_ouder($gebruiker) && in_array($factuur['status'], ['open', 'mislukt'], true) && mollie_actief()): ?>
    <form method="post"><?= csrf_veld() ?><button class="btn btn--small" type="submit"><?= icoon('bank') ?>Nu betalen via iDEAL</button></form>
<?php endif; ?>
  </div>
</article>
<?php
pagina_einde();
