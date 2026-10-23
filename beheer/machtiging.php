<?php
/*
 * Machtiging voor automatische incasso afgeven (ouder).
 * Met Mollie: eerste betaling van € 0,01 via de eigen bank. Zonder Mollie: demo.
 */
require __DIR__ . '/inc/bootstrap.php';

$ouder = vereis_login(['ouder']);

// Terug van de betaalpagina van Mollie: status direct ophalen (de webhook kan later komen)
if (get_str('terug') === '1' && mollie_actief()) {
    $laatste = rij("SELECT mollie_id FROM betalingen WHERE gebruiker_id = ? AND soort = 'machtiging' ORDER BY id DESC LIMIT 1", [$ouder['id']]);
    if ($laatste) {
        try {
            verwerk_betaling($laatste['mollie_id']);
        } catch (RuntimeException $fout) {
            error_log('Machtiging controleren: ' . $fout->getMessage());
        }
    }
    $ouder = huidige_gebruiker(true);
    if ($ouder['mandaat_status'] === 'geldig') {
        flash('succes', 'Top, je machtiging is geregeld! Betalen gaat vanaf nu vanzelf.');
        redirect('index.php');
    }
    if ($ouder['mandaat_status'] === 'geen') {
        flash('fout', 'De machtiging is niet gelukt of afgebroken. Probeer het gerust nog een keer.');
        redirect('machtiging.php');
    }
}

if (is_post()) {
    csrf_controleer();
    if (in_array($ouder['mandaat_status'], ['geldig', 'demo'], true)) {
        redirect('machtiging.php');
    }
    if (mollie_actief()) {
        try {
            redirect(start_machtiging($ouder));
        } catch (RuntimeException $fout) {
            error_log('Machtiging starten: ' . $fout->getMessage());
            flash('fout', 'Het lukte even niet om naar je bank te gaan. Probeer het later nog een keer of neem contact met ons op.');
            redirect('machtiging.php');
        }
    }
    $naam = invoer('rekeninghouder');
    if ($naam === '') {
        flash('fout', 'Vul de naam van de rekeninghouder in.');
        redirect('machtiging.php');
    }
    demo_machtiging($ouder, $naam);
    flash('succes', 'Demo: de machtiging is gesimuleerd. In de echte omgeving loopt dit via je eigen bank.');
    redirect('machtiging.php');
}

pagina_begin('Machtiging automatische incasso', 'profiel.php');
pagina_kop('Automatische incasso', 'Zo hoef jij nergens meer aan te denken: je facturen worden elke maand automatisch betaald.');
?>
<div class="kolommen">
  <div class="panel">
<?php if (in_array($ouder['mandaat_status'], ['geldig', 'demo'], true)): ?>
    <div class="henny-baan henny-baan--vast is-klaar" style="--voortgang: 1;">
      <p class="tekstwolk henny-baan__wolk">Goal! Alles is geregeld. Tot snel bij Sporty!</p>
      <div class="henny-baan__spoor" aria-hidden="true">
        <img class="henny-baan__henny" src="../assets/img/henny/henny-rent-180.webp" srcset="../assets/img/henny/henny-rent-180.webp 180w, ../assets/img/henny/henny-rent-360.webp 360w" sizes="3rem" width="180" height="239" alt="">
        <svg class="henny-baan__doel" viewBox="0 0 44 30" focusable="false"><path d="M3 29V3h38v26"/><path d="M3 3l6 6h26l6-6M9 9v20M35 9v20M15.5 9v20M22 9v20M28.5 9v20M9 15.5h26M9 22h26"/></svg>
      </div>
    </div>
    <h2>Je machtiging is geregeld</h2>
    <dl class="gegevens">
      <dt>Status</dt><dd><?= status_badge($ouder['mandaat_status']) ?></dd>
      <dt>Rekening</dt><dd><?= e($ouder['mandaat_rekening']) ?></dd>
      <dt>Op naam van</dt><dd><?= e($ouder['mandaat_naam']) ?></dd>
      <dt>Sinds</dt><dd><?= e(datum_nl($ouder['mandaat_datum'])) ?></dd>
    </dl>
    <p class="muted">Wil je een andere rekening gebruiken of de machtiging intrekken? Stuur ons een <a href="berichten.php">bericht</a>.</p>
<?php else: ?>
    <!-- Henny rent mee door het aanmelden: na het formulier is dit de laatste stap -->
    <div class="henny-baan henny-baan--vast" style="--voortgang: 0.86;">
      <p class="tekstwolk henny-baan__wolk">Bijna bij het doel! Nog één stap: de machtiging via je eigen bank.</p>
      <div class="henny-baan__spoor" aria-hidden="true">
        <img class="henny-baan__henny" src="../assets/img/henny/henny-rent-180.webp" srcset="../assets/img/henny/henny-rent-180.webp 180w, ../assets/img/henny/henny-rent-360.webp 360w" sizes="3rem" width="180" height="239" alt="">
        <svg class="henny-baan__doel" viewBox="0 0 44 30" focusable="false"><path d="M3 29V3h38v26"/><path d="M3 3l6 6h26l6-6M9 9v20M35 9v20M15.5 9v20M22 9v20M28.5 9v20M9 15.5h26M9 22h26"/></svg>
      </div>
    </div>
    <h2>Machtiging afgeven</h2>
<?php if ($ouder['mandaat_status'] === 'in_behandeling'): ?>
    <p><?= status_badge('in_behandeling') ?> We wachten nog op de bevestiging van je bank. Dat duurt meestal maar even. Ging er iets mis? Probeer het dan hieronder opnieuw.</p>
<?php endif; ?>
<?php if (mollie_actief()): ?>
    <p>Je betaalt eenmalig <strong>€ 0,01</strong> via je eigen bank (iDEAL). Daarmee bevestig je je rekeningnummer en geef je <?= e(incassant()) ?> toestemming om de facturen voor de opvang automatisch af te schrijven.</p>
    <form method="post">
      <?= csrf_veld() ?>
      <button class="btn" type="submit"><?= icoon('bank') ?>Machtiging afgeven via mijn bank</button>
    </form>
    <p class="muted">De betaling loopt via Mollie, een Nederlandse betaalprovider. Je komt daarna vanzelf hier terug.</p>
<?php else: ?>
    <p class="demo-balk"><?= icoon('info') ?>Demo-modus: er is nog geen betaalprovider gekoppeld.</p>
    <p>In de echte omgeving betaal je hier eenmalig € 0,01 via je eigen bank. Nu kun je de machtiging alleen simuleren.</p>
    <form class="form" method="post">
      <?= csrf_veld() ?>
      <div class="field">
        <label for="rekeninghouder">Naam rekeninghouder</label>
        <input id="rekeninghouder" name="rekeninghouder" required value="<?= e($ouder['naam']) ?>">
      </div>
      <div class="form__acties"><button class="btn" type="submit">Machtiging simuleren (demo)</button></div>
    </form>
<?php endif; ?>
    <p><a href="index.php">Later regelen</a></p>
<?php endif; ?>
  </div>
  <div class="panel">
    <h2>Goed om te weten</h2>
    <ul class="checklist">
      <li>Je krijgt elke maand eerst een factuur in je account. Daarna schrijven we het bedrag af.</li>
      <li>Niet eens met een afschrijving? Je kunt die tot 8 weken na afschrijving via je bank laten terugboeken.</li>
      <li>Je kunt de machtiging altijd intrekken. Stuur ons dan even een bericht.</li>
    </ul>
  </div>
</div>
<?php
pagina_einde();
