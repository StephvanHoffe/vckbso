<?php
/* Instellingen (beheerder): bedrijfsgegevens voor facturen, de tarieven van beide abonnementen en afmelden. */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_recht('instellingen');

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
    'iban_naam' => 'Naam op de rekening (t.n.v.)',
];
$fouten = [];
$waarden = [];
// Tarieven (in euro op het scherm, in centen opgeslagen) en uren (met een komma, zoals 4,5)
const TARIEF_VELDEN = ['abonnement_uurtarief' => 'abonnement_uurtarief_cent', 'incidenteel_uurtarief' => 'incidenteel_uurtarief_cent'];
const UREN_VELDEN = ['abonnement_uren_per_dag' => [1, 60], 'uren_schooldag' => [0.5, 12], 'uren_hele_dag' => [0.5, 14]];
foreach (array_merge(array_keys($velden), array_keys(TARIEF_VELDEN), array_keys(UREN_VELDEN), ['kosteloos_afmelden_uren', 'afmelden_tot', 'betaaltermijn_dagen', 'mfa_ouders'], array_keys(BEWAARTERMIJNEN)) as $sleutel) {
    $waarden[$sleutel] = isset(TARIEF_VELDEN[$sleutel])
        ? (instelling(TARIEF_VELDEN[$sleutel]) !== '' ? cent_naar_euro((int) instelling(TARIEF_VELDEN[$sleutel])) : '')
        : instelling($sleutel);
}

if (is_post()) {
    csrf_controleer();
    foreach (array_keys($waarden) as $sleutel) {
        $waarden[$sleutel] = mb_substr(invoer($sleutel), 0, 200);
    }
    $centen = [];
    foreach (TARIEF_VELDEN as $veld => $sleutel) {
        $centen[$sleutel] = euro_naar_cent($waarden[$veld]);
        if ($centen[$sleutel] === null || $centen[$sleutel] <= 0) {
            $fouten[$veld] = 'Vul het uurtarief in euro in, bijvoorbeeld 9,75.';
        }
    }
    foreach (UREN_VELDEN as $veld => [$min, $max]) {
        $uren = str_replace(',', '.', $waarden[$veld]);
        if (!is_numeric($uren) || (float) $uren < $min || (float) $uren > $max) {
            $fouten[$veld] = 'Vul een aantal uren in, bijvoorbeeld ' . ['abonnement_uren_per_dag' => '27', 'uren_schooldag' => '4,5', 'uren_hele_dag' => '10,5'][$veld] . '.';
        }
    }
    if (!ctype_digit($waarden['kosteloos_afmelden_uren']) || (int) $waarden['kosteloos_afmelden_uren'] > 168) {
        $fouten['kosteloos_afmelden_uren'] = 'Vul een aantal uren in (0 tot 168), bijvoorbeeld 48.';
    }
    if (!geldige_tijd($waarden['afmelden_tot'])) {
        $fouten['afmelden_tot'] = 'Vul een tijd in, bijvoorbeeld 12:00.';
    }
    if (!ctype_digit($waarden['betaaltermijn_dagen']) || (int) $waarden['betaaltermijn_dagen'] > 60) {
        $fouten['betaaltermijn_dagen'] = 'Vul een aantal dagen in (0 tot 60).';
    }
    if ($waarden['iban'] !== '') {
        if (geldig_iban($waarden['iban'])) {
            $waarden['iban'] = iban_opmaak($waarden['iban']);
        } else {
            $fouten['iban'] = 'Dit IBAN klopt niet. Kijk of je geen cijfer of letter hebt overgeslagen of omgedraaid.';
        }
    }
    if ($waarden['email'] !== '' && !geldig_email($waarden['email'])) {
        $fouten['email'] = 'Vul een geldig e-mailadres in.';
    }
    foreach (BEWAARTERMIJNEN as $sleutel => [$omschrijving, $eenheid]) {
        if (!ctype_digit($waarden[$sleutel]) || (int) $waarden[$sleutel] > 240) {
            $fouten[$sleutel] = 'Vul een geheel aantal ' . $eenheid . ' in.';
        }
    }
    // Ondergrenzen, met minimaal de fiscale bewaarplicht voor de financiële administratie
    foreach (BEWAAR_MINIMUM as $sleutel => $minimum) {
        if (empty($fouten[$sleutel]) && ctype_digit($waarden[$sleutel]) && (int) $waarden[$sleutel] < $minimum) {
            $fouten[$sleutel] = $sleutel === 'bewaar_financieel_jaren' ? 'De fiscale bewaarplicht is 7 jaar; korter mag niet.' : 'Minstens ' . $minimum . ' ' . BEWAARTERMIJNEN[$sleutel][1] . '.';
        }
    }
    // Een kortere termijn wist bij het volgende onderhoud meteen gegevens: dat vraagt om je eigen wachtwoord
    $korter = array_filter(array_keys(BEWAARTERMIJNEN), fn ($s) => ctype_digit($waarden[$s]) && (int) $waarden[$s] < bewaartermijn($s));
    if ($korter && !$fouten) {
        if (te_veel_controles('b', (int) $gebruiker['id'])) {
            $fouten['bevestig_wachtwoord'] = 'Te veel pogingen. Wacht een kwartier en probeer het dan opnieuw.';
        } elseif (!password_verify((string) ($_POST['bevestig_wachtwoord'] ?? ''), (string) $gebruiker['wachtwoord_hash'])) {
            mislukte_controle('b', (int) $gebruiker['id']);
            $fouten['bevestig_wachtwoord'] = 'Je maakt een bewaartermijn korter. Gegevens die daar dan buiten vallen, worden bij het volgende onderhoud gewist. Vul ter bevestiging je eigen wachtwoord in.';
        }
    }
    $waarden['mfa_ouders'] = $waarden['mfa_ouders'] === 'verplicht' ? 'verplicht' : 'optioneel';
    if (!$fouten) {
        foreach (array_keys($velden) as $sleutel) {
            instelling_zet($sleutel, $waarden[$sleutel]);
        }
        foreach ($centen as $sleutel => $cent) {
            instelling_zet($sleutel, (string) $cent);
        }
        foreach (array_keys(UREN_VELDEN) as $veld) {
            instelling_zet($veld, str_replace('.', ',', $waarden[$veld]));
        }
        instelling_zet('kosteloos_afmelden_uren', (string) (int) $waarden['kosteloos_afmelden_uren']);
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
pagina_kop('Instellingen', 'Gegevens voor de facturen, het tarief, afmelden, beveiliging en bewaartermijnen.', (team_mag('emails', $gebruiker) ? '<a class="btn btn--secondary btn--small" href="mailteksten.php">' . icoon('mail') . 'E-mails</a>' : '') . '<a class="btn btn--secondary btn--small" href="beveiliging.php">' . icoon('shield') . 'Beveiligingsstatus</a><a class="btn btn--secondary btn--small" href="logboek.php">' . icoon('book') . 'Logboek</a>');
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
      <h2 id="tarief-titel">Tarieven en afspraken</h2>
      <div class="form">
        <!-- Tarieven van Sporty (antwoorden van november 2026). Hoe de facturen rekenen: zie beheer/inc/facturen.php -->
        <p class="field__hint">Elke maand maakt Mijn BSO achteraf de facturen. Het <strong>Sporty Abonnement</strong> is een vast bedrag per maand: vaste dagen per week × uren per maand × uurtarief. <strong>Incidenteel</strong> rekent per afgenomen dag: een schooldag of een vakantie- of studiedag (die zet je in de agenda bij "Deze dag aanpassen").</p>
        <h3>Sporty Abonnement (52 weken)</h3>
        <div class="field field--klein">
          <label for="abonnement_uurtarief">Uurtarief in euro</label>
          <input id="abonnement_uurtarief" name="abonnement_uurtarief" inputmode="decimal" placeholder="9,75" value="<?= e($waarden['abonnement_uurtarief']) ?>"<?= aria_fout($fouten, 'abonnement_uurtarief') ?>>
          <?= veldfout($fouten, 'abonnement_uurtarief') ?>
        </div>
        <div class="field field--klein">
          <label for="abonnement_uren_per_dag">Uren per maand per vaste dag per week</label>
          <input id="abonnement_uren_per_dag" name="abonnement_uren_per_dag" inputmode="decimal" placeholder="27" value="<?= e($waarden['abonnement_uren_per_dag']) ?>"<?= aria_fout($fouten, 'abonnement_uren_per_dag') ?>>
          <?= veldfout($fouten, 'abonnement_uren_per_dag') ?>
        </div>
        <h3>Incidenteel</h3>
        <div class="field field--klein">
          <label for="incidenteel_uurtarief">Uurtarief in euro</label>
          <input id="incidenteel_uurtarief" name="incidenteel_uurtarief" inputmode="decimal" placeholder="12" value="<?= e($waarden['incidenteel_uurtarief']) ?>"<?= aria_fout($fouten, 'incidenteel_uurtarief') ?>>
          <?= veldfout($fouten, 'incidenteel_uurtarief') ?>
        </div>
        <div class="field field--klein">
          <label for="uren_schooldag">Uren per schooldag</label>
          <input id="uren_schooldag" name="uren_schooldag" inputmode="decimal" placeholder="4,5" value="<?= e($waarden['uren_schooldag']) ?>"<?= aria_fout($fouten, 'uren_schooldag') ?>>
          <?= veldfout($fouten, 'uren_schooldag') ?>
        </div>
        <div class="field field--klein">
          <label for="uren_hele_dag">Uren per vakantie- of studiedag</label>
          <input id="uren_hele_dag" name="uren_hele_dag" inputmode="decimal" placeholder="10,5" value="<?= e($waarden['uren_hele_dag']) ?>"<?= aria_fout($fouten, 'uren_hele_dag') ?>>
          <?= veldfout($fouten, 'uren_hele_dag') ?>
        </div>
        <h3>Afmelden en betalen</h3>
        <div class="field field--klein">
          <label for="kosteloos_afmelden_uren">Kosteloos afmelden tot <span class="field__hint">(uren van tevoren; later afgemelde incidentele dagen worden gerekend)</span></label>
          <input id="kosteloos_afmelden_uren" name="kosteloos_afmelden_uren" type="number" min="0" max="168" value="<?= e($waarden['kosteloos_afmelden_uren']) ?>"<?= aria_fout($fouten, 'kosteloos_afmelden_uren') ?>>
          <?= veldfout($fouten, 'kosteloos_afmelden_uren') ?>
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
      <h3 style="margin-top: var(--space-l)">Betalen via Stripe</h3>
      <p><?= stripe_actief() ? status_badge('geldig') . ' Gekoppeld' . (stripe_testmodus() ? ' (testmodus: er wordt nog niets echt afgeschreven)' : '') . '.' : status_badge('demo') . ' Nog niet gekoppeld.' ?> <?= link_als('koppelingen', 'koppelingen.php', 'Stripe en e-mail instellen onder Koppelingen') ?></p>
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
          <input id="<?= e($sleutel) ?>" name="<?= e($sleutel) ?>" type="number" min="<?= (int) (BEWAAR_MINIMUM[$sleutel] ?? 1) ?>" max="240" style="max-width: 8rem" value="<?= e($waarden[$sleutel]) ?>"<?= aria_fout($fouten, $sleutel) ?>>
          <?= veldfout($fouten, $sleutel) ?>
        </div>
<?php endforeach; ?>
      </div>
      <div class="field">
        <label for="bevestig_wachtwoord">Je eigen wachtwoord <span class="field__hint">(alleen nodig als je een bewaartermijn korter maakt)</span></label>
        <input id="bevestig_wachtwoord" name="bevestig_wachtwoord" type="password" autocomplete="current-password" style="max-width: 20rem"<?= aria_fout($fouten, 'bevestig_wachtwoord') ?>>
        <?= veldfout($fouten, 'bevestig_wachtwoord') ?>
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
