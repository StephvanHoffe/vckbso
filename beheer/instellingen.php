<?php
/* Instellingen (beheerder): bedrijfsgegevens voor facturen, tarief en afmeldtijd. */
require __DIR__ . '/inc/bootstrap.php';

vereis_beheerder();

$velden = [
    'bedrijfsnaam' => 'Naam van de BSO',
    'statutaire_naam' => 'Statutaire naam',
    'adres' => 'Straat en huisnummer',
    'postcode_plaats' => 'Postcode en plaats',
    'email' => 'E-mailadres',
    'telefoon' => 'Telefoonnummer',
    'kvk' => 'KvK-nummer',
    'lrk' => 'LRK-nummer',
    'iban' => 'IBAN van de BSO',
];
$fouten = [];
$waarden = [];
foreach (array_merge(array_keys($velden), ['uurtarief', 'uren_per_middag', 'afmelden_tot', 'betaaltermijn_dagen', 'mfa_ouders'], array_keys(BEWAARTERMIJNEN)) as $sleutel) {
    $waarden[$sleutel] = $sleutel === 'uurtarief'
        ? (instelling('uurtarief_cent') !== '' ? cent_naar_euro((int) instelling('uurtarief_cent')) : '')
        : instelling($sleutel);
}

if (is_post()) {
    csrf_controleer();
    foreach (array_keys($waarden) as $sleutel) {
        $waarden[$sleutel] = mb_substr(invoer($sleutel), 0, 200);
    }
    $cent = $waarden['uurtarief'] === '' ? null : euro_naar_cent($waarden['uurtarief']);
    if ($waarden['uurtarief'] !== '' && ($cent === null || $cent <= 0)) {
        $fouten['uurtarief'] = 'Vul het uurtarief in euro in, bijvoorbeeld 10,25.';
    }
    $uren = str_replace(',', '.', $waarden['uren_per_middag']);
    if ($waarden['uren_per_middag'] !== '' && (!is_numeric($uren) || (float) $uren <= 0 || (float) $uren > 12)) {
        $fouten['uren_per_middag'] = 'Vul het aantal uren per middag in, bijvoorbeeld 4,5.';
    }
    if (!geldige_tijd($waarden['afmelden_tot'])) {
        $fouten['afmelden_tot'] = 'Vul een tijd in, bijvoorbeeld 12:00.';
    }
    if (!ctype_digit($waarden['betaaltermijn_dagen']) || (int) $waarden['betaaltermijn_dagen'] > 60) {
        $fouten['betaaltermijn_dagen'] = 'Vul een aantal dagen in (0 tot 60).';
    }
    if ($waarden['email'] !== '' && !geldig_email($waarden['email'])) {
        $fouten['email'] = 'Vul een geldig e-mailadres in.';
    }
    foreach (BEWAARTERMIJNEN as $sleutel => [$omschrijving, $eenheid]) {
        if (!ctype_digit($waarden[$sleutel]) || (int) $waarden[$sleutel] > 240) {
            $fouten[$sleutel] = 'Vul een geheel aantal ' . $eenheid . ' in.';
        }
    }
    // Minimaal de fiscale bewaarplicht voor de financiële administratie
    if (ctype_digit($waarden['bewaar_financieel_jaren']) && (int) $waarden['bewaar_financieel_jaren'] < 7) {
        $fouten['bewaar_financieel_jaren'] = 'De fiscale bewaarplicht is 7 jaar; korter mag niet.';
    }
    $waarden['mfa_ouders'] = $waarden['mfa_ouders'] === 'verplicht' ? 'verplicht' : 'optioneel';
    if (!$fouten) {
        foreach (array_keys($velden) as $sleutel) {
            instelling_zet($sleutel, $waarden[$sleutel]);
        }
        instelling_zet('uurtarief_cent', $cent === null ? '' : (string) $cent);
        instelling_zet('uren_per_middag', $waarden['uren_per_middag'] === '' ? '' : str_replace('.', ',', $uren));
        instelling_zet('afmelden_tot', $waarden['afmelden_tot']);
        instelling_zet('betaaltermijn_dagen', $waarden['betaaltermijn_dagen']);
        instelling_zet('mfa_ouders', $waarden['mfa_ouders']);
        foreach (array_keys(BEWAARTERMIJNEN) as $sleutel) {
            instelling_zet($sleutel, (string) (int) $waarden[$sleutel]);
        }
        log_actie('Instellingen gewijzigd', '', null, 'beveiliging');
        flash('succes', 'De instellingen zijn opgeslagen.');
        redirect('instellingen.php');
    }
}

pagina_begin('Instellingen', 'instellingen.php');
pagina_kop('Instellingen', 'Gegevens voor de facturen, het tarief, afmelden, beveiliging en bewaartermijnen.', '<a class="btn btn--secondary btn--small" href="beveiliging.php">' . icoon('shield') . 'Beveiligingsstatus</a><a class="btn btn--secondary btn--small" href="logboek.php">' . icoon('book') . 'Logboek</a>');
?>
<?= foutensamenvatting($fouten) ?>
<form class="form" method="post">
  <?= csrf_veld() ?>
  <div class="kolommen kolommen--gelijk">
    <section class="panel" aria-labelledby="bedrijf-titel">
      <h2 id="bedrijf-titel">Gegevens op de facturen</h2>
      <div class="form">
<?php foreach ($velden as $sleutel => $label): ?>
        <div class="field">
          <label for="<?= e($sleutel) ?>"><?= e($label) ?></label>
          <input id="<?= e($sleutel) ?>" name="<?= e($sleutel) ?>" value="<?= e($waarden[$sleutel]) ?>"<?= aria_fout($fouten, $sleutel) ?>>
          <?= veldfout($fouten, $sleutel) ?>
        </div>
<?php endforeach; ?>
      </div>
    </section>
    <section class="panel" aria-labelledby="tarief-titel">
      <h2 id="tarief-titel">Tarief en afspraken</h2>
      <div class="form">
        <!-- TODO: het echte uurtarief en de uren per middag van de klant invullen -->
        <div class="field field--klein">
          <label for="uurtarief">Uurtarief in euro</label>
          <input id="uurtarief" name="uurtarief" inputmode="decimal" placeholder="bijv. 10,25" value="<?= e($waarden['uurtarief']) ?>"<?= aria_fout($fouten, 'uurtarief') ?>>
          <?= veldfout($fouten, 'uurtarief') ?>
        </div>
        <div class="field field--klein">
          <label for="uren_per_middag">Uren per middag</label>
          <input id="uren_per_middag" name="uren_per_middag" inputmode="decimal" placeholder="bijv. 4,5" value="<?= e($waarden['uren_per_middag']) ?>"<?= aria_fout($fouten, 'uren_per_middag') ?>>
          <?= veldfout($fouten, 'uren_per_middag') ?>
        </div>
        <div class="field field--klein">
          <label for="afmelden_tot">Ouders kunnen afmelden tot <span class="field__hint">(op de dag zelf)</span></label>
          <input id="afmelden_tot" name="afmelden_tot" type="time" value="<?= e($waarden['afmelden_tot']) ?>"<?= aria_fout($fouten, 'afmelden_tot') ?>>
          <?= veldfout($fouten, 'afmelden_tot') ?>
        </div>
        <div class="field field--klein">
          <label for="betaaltermijn_dagen">Betaaltermijn in dagen</label>
          <input id="betaaltermijn_dagen" name="betaaltermijn_dagen" type="number" min="0" max="60" value="<?= e($waarden['betaaltermijn_dagen']) ?>"<?= aria_fout($fouten, 'betaaltermijn_dagen') ?>>
          <?= veldfout($fouten, 'betaaltermijn_dagen') ?>
        </div>
      </div>
      <h3 style="margin-top: var(--space-l)">Betalen via Mollie</h3>
      <p><?= mollie_actief() ? status_badge('geldig') . ' Gekoppeld' . (mollie_testmodus() ? ' (testmodus: er wordt nog niets echt afgeschreven)' : '') . '.' : status_badge('demo') . ' Nog niet gekoppeld. Zet de API-sleutel van Mollie in <code>beheer/config.php</code>.' ?></p>
    </section>
  </div>
  <div class="kolommen kolommen--gelijk">
    <section class="panel" aria-labelledby="bewaar-titel">
      <h2 id="bewaar-titel">Bewaartermijnen</h2>
      <p class="muted">Na deze termijnen worden gegevens automatisch verwijderd (dagelijks). Ze verdwijnen ook uit de back-ups, uiterlijk <?= (int) cfg('backup.bewaardagen', 30) ?> dagen later. <!-- TODO: termijnen laten bevestigen door de klant en een privacyjurist --></p>
      <div class="form">
<?php foreach (BEWAARTERMIJNEN as $sleutel => [$omschrijving, $eenheid, $uitleg]): ?>
        <div class="field">
          <label for="<?= e($sleutel) ?>"><?= e($omschrijving) ?> <span class="field__hint">(<?= e($eenheid) ?><?= $uitleg ? ', ' . e($uitleg) : '' ?>)</span></label>
          <input id="<?= e($sleutel) ?>" name="<?= e($sleutel) ?>" type="number" min="0" max="240" style="max-width: 8rem" value="<?= e($waarden[$sleutel]) ?>"<?= aria_fout($fouten, $sleutel) ?>>
          <?= veldfout($fouten, $sleutel) ?>
        </div>
<?php endforeach; ?>
      </div>
      <p class="muted">Laatst uitgevoerd: <?= instelling('bewaarbeleid_laatst') ? e(moment_nl(instelling('bewaarbeleid_laatst'))) : 'nog niet' ?>.</p>
    </section>
    <section class="panel" aria-labelledby="mfa-titel">
      <h2 id="mfa-titel">Tweestapsverificatie</h2>
      <p>Voor het team is tweestapsverificatie altijd verplicht. Voor ouders kies je zelf:</p>
      <div class="choices">
        <label class="choice"><input type="radio" name="mfa_ouders" value="optioneel"<?= $waarden['mfa_ouders'] !== 'verplicht' ? ' checked' : '' ?>>Aanbevolen, maar niet verplicht</label>
        <label class="choice"><input type="radio" name="mfa_ouders" value="verplicht"<?= $waarden['mfa_ouders'] === 'verplicht' ? ' checked' : '' ?>>Verplicht voor ouders</label>
      </div>
    </section>
  </div>
  <div class="form__acties" style="margin-top: var(--space-l)"><button class="btn" type="submit">Instellingen opslaan</button></div>
</form>
<?php
pagina_einde();
