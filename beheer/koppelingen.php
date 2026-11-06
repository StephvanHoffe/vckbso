<?php
/*
 * Koppelingen (recht "Koppelingen"): Stripe en het versturen van e-mail instellen.
 * - Sleutels en wachtwoorden worden versleuteld opgeslagen en zijn daarna niet meer te lezen;
 *   je ziet alleen het begin en de laatste vier tekens. Een nieuwe sleutel wordt eerst getest.
 * - Een geheim wijzigen of Stripe ontkoppelen vraagt om je eigen wachtwoord.
 * - Wat in config.php op de server staat, gaat voor en is hier alleen te zien.
 * De opslag staat in inc/koppelingen.php.
 */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_recht('koppelingen');
$fouten = [];
$formulier = '';
$opgeslagen = koppelingen_opgeslagen();
$invoer = [
    'methoden_machtiging' => stripe_methoden('machtiging'),
    'methoden_betalen' => stripe_methoden('betalen'),
    'methode' => mail_methode(),
    'van' => (string) koppeling('mail.van'),
    'van_naam' => (string) koppeling('mail.van_naam'),
    'team_email' => (string) koppeling('team_email'),
    'host' => (string) koppeling('smtp.host'),
    'poort' => (string) koppeling('smtp.poort'),
    'beveiliging' => koppeling('smtp.beveiliging') === 'ssl' ? 'ssl' : 'starttls',
    'gebruiker' => (string) koppeling('smtp.gebruiker'),
];
$bevestigd = fn () => password_verify((string) ($_POST['bevestig_wachtwoord'] ?? ''), (string) $gebruiker['wachtwoord_hash']);
$bewaar = function (string $sleutel, string $waarde) use ($gebruiker): bool {
    if (koppeling_in_config($sleutel) || (string) koppeling($sleutel) === $waarde) {
        return false;
    }
    koppeling_zet($sleutel, $waarde, (int) $gebruiker['id']);
    return true;
};

if (is_post()) {
    csrf_controleer();
    $actie = invoer('actie');

    /* ---------- Stripe ---------- */
    if ($actie === 'stripe') {
        $formulier = 'stripe';
        $sleutel = trim((string) ($_POST['geheime_sleutel'] ?? ''));
        $webhook = trim((string) ($_POST['webhook_geheim'] ?? ''));
        foreach (['machtiging' => STRIPE_KEUZES_MACHTIGING, 'betalen' => STRIPE_KEUZES_BETALEN] as $soort => $keuzes) {
            $invoer['methoden_' . $soort] = array_values(array_intersect(array_keys($keuzes), array_map('strval', (array) ($_POST['methoden_' . $soort] ?? []))));
            if (!koppeling_in_config('stripe.methoden_' . $soort) && !$invoer['methoden_' . $soort]) {
                $fouten['methoden_' . $soort] = $soort === 'machtiging' ? 'Kies minstens één manier om de machtiging af te geven.' : 'Kies minstens één manier om los te betalen.';
            }
        }
        if ($webhook !== '' && !preg_match('/^whsec_[A-Za-z0-9+\/=]{16,}$/', $webhook)) {
            $fouten['webhook_geheim'] = 'Het webhookgeheim begint met whsec_. Je vindt het bij de webhook in het dashboard van Stripe ("Ondertekeningsgeheim").';
        }
        if (($sleutel !== '' || $webhook !== '') && !$bevestigd()) {
            $fouten['bevestig_wachtwoord'] = 'Vul je eigen wachtwoord in om een sleutel te wijzigen.';
        }
        $modus = null;
        if ($sleutel !== '' && !$fouten) {
            try {
                $modus = stripe_test_sleutel($sleutel);
            } catch (RuntimeException $fout) {
                $fouten['geheime_sleutel'] = 'Deze sleutel werkt niet. ' . $fout->getMessage();
            }
        }
        if (!$fouten) {
            $gewijzigd = [];
            if ($sleutel !== '' && $bewaar('stripe.geheime_sleutel', $sleutel)) {
                $gewijzigd[] = 'geheime sleutel (' . ($modus === 'live' ? 'live' : 'test') . ')';
            }
            if ($webhook !== '' && $bewaar('stripe.webhook_geheim', $webhook)) {
                $gewijzigd[] = 'webhookgeheim';
            }
            foreach (['machtiging', 'betalen'] as $soort) {
                if ($bewaar('stripe.methoden_' . $soort, implode(',', $invoer['methoden_' . $soort]))) {
                    $gewijzigd[] = 'betaalmethoden ' . ($soort === 'machtiging' ? 'machtiging' : 'los betalen');
                }
            }
            if ($gewijzigd) {
                log_actie('Koppeling gewijzigd', 'Stripe: ' . implode(', ', $gewijzigd), null, 'beveiliging');
            }
            flash('succes', !$gewijzigd ? 'Er was niets veranderd.' : ('Stripe is bijgewerkt.' . ($modus === 'live' ? ' De sleutel werkt: Mijn BSO is live gekoppeld, er wordt echt afgeschreven.' : ($modus === 'test' ? ' De sleutel werkt, in testmodus: er wordt nog niets echt afgeschreven.' : ''))));
            redirect('koppelingen.php#stripe');
        }
    }

    if ($actie === 'stripe_testen') {
        try {
            $modus = stripe_test_sleutel(stripe_geheim());
            flash('succes', 'De verbinding met Stripe werkt (' . ($modus === 'live' ? 'live' : 'testmodus') . ').');
        } catch (RuntimeException $fout) {
            flash('fout', 'De verbinding met Stripe werkt niet. ' . $fout->getMessage());
        }
        redirect('koppelingen.php#stripe');
    }

    if ($actie === 'stripe_ontkoppelen') {
        if (!$bevestigd()) {
            flash('fout', 'Vul je eigen wachtwoord in om Stripe te ontkoppelen.');
        } else {
            koppeling_zet('stripe.geheime_sleutel', '', (int) $gebruiker['id']);
            koppeling_zet('stripe.webhook_geheim', '', (int) $gebruiker['id']);
            log_actie('Koppeling verwijderd', 'Stripe ontkoppeld', null, 'beveiliging');
            flash('succes', 'Stripe is ontkoppeld' . (stripe_actief() ? ', maar de sleutel in config.php op de server blijft gelden.' : '. Mijn BSO staat weer in demo-modus.'));
        }
        redirect('koppelingen.php#stripe');
    }

    /* ---------- E-mail ---------- */
    if ($actie === 'mail' || $actie === 'mail_test') {
        $formulier = 'mail';
        foreach (['methode', 'van', 'van_naam', 'team_email', 'host', 'poort', 'beveiliging', 'gebruiker'] as $veld) {
            $invoer[$veld] = mb_substr(invoer($veld), 0, 200);
        }
        $wachtwoord = (string) ($_POST['smtp_wachtwoord'] ?? '');
        $invoer['methode'] = in_array($invoer['methode'], ['log', 'mail', 'smtp'], true) ? $invoer['methode'] : 'log';
        $invoer['beveiliging'] = $invoer['beveiliging'] === 'ssl' ? 'ssl' : 'starttls';
        if (!smtp_adres_ok($invoer['van'])) {
            $fouten['van'] = 'Vul een geldig e-mailadres in, bijvoorbeeld info@sporty.nl.';
        }
        if ($invoer['van_naam'] === '') {
            $fouten['van_naam'] = 'Vul de naam in die ouders als afzender zien.';
        }
        if (!smtp_adres_ok($invoer['team_email'])) {
            $fouten['team_email'] = 'Vul een geldig e-mailadres in voor het team.';
        }
        if ($invoer['methode'] === 'smtp') {
            if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/i', $invoer['host'])) {
                $fouten['host'] = 'Vul de naam van de mailserver in, bijvoorbeeld smtp.office365.com of mail.jouwprovider.nl.';
            }
            if (!ctype_digit($invoer['poort']) || (int) $invoer['poort'] < 1 || (int) $invoer['poort'] > 65535) {
                $fouten['poort'] = 'Vul de poort in: meestal 587 (STARTTLS) of 465 (SSL/TLS).';
            }
            if ($invoer['gebruiker'] !== '' && $wachtwoord === '' && (string) koppeling('smtp.wachtwoord') === '') {
                $fouten['smtp_wachtwoord'] = 'Vul het wachtwoord van de mailbox in.';
            }
        }
        if ($wachtwoord !== '' && !$bevestigd()) {
            $fouten['bevestig_wachtwoord_mail'] = 'Vul je eigen wachtwoord in om het wachtwoord van de mailbox te wijzigen.';
        }
        if (!$fouten) {
            $gewijzigd = [];
            foreach (['mail.methode' => 'methode', 'mail.van' => 'van', 'mail.van_naam' => 'van_naam', 'team_email' => 'team_email', 'smtp.host' => 'host', 'smtp.poort' => 'poort', 'smtp.beveiliging' => 'beveiliging', 'smtp.gebruiker' => 'gebruiker'] as $sleutel => $veld) {
                if ($bewaar($sleutel, $invoer[$veld])) {
                    $gewijzigd[] = mb_strtolower(KOPPELING_VELDEN[$sleutel][0]);
                }
            }
            if ($wachtwoord !== '' && $bewaar('smtp.wachtwoord', $wachtwoord)) {
                $gewijzigd[] = 'wachtwoord van de mailbox';
            }
            if ($gewijzigd) {
                log_actie('Koppeling gewijzigd', 'E-mail: ' . implode(', ', $gewijzigd), null, 'beveiliging');
            }
            if ($actie === 'mail_test') {
                // Een technische test, geen e-mail van het platform: daarom niet onder E-mails aanpassen
                $gelukt = stuur_mail((string) $gebruiker['email'], 'Testmail van Mijn BSO', "Hoi " . explode(' ', (string) $gebruiker['naam'])[0] . ",\n\nDit is een testmail vanaf de pagina Koppelingen in Mijn BSO. Komt hij aan? Dan werkt het versturen van e-mail.");
                log_actie('Testmail verstuurd', 'Koppelingen, ' . mail_methode() . ($gelukt ? '' : ', mislukt'));
                if (!$gelukt) {
                    flash('fout', 'De instellingen zijn opgeslagen, maar de testmail kon niet worden verstuurd. ' . mail_laatste_fout());
                } elseif (mail_methode() === 'log') {
                    flash('info', 'Opgeslagen. Mijn BSO staat in testmodus: de testmail is niet verstuurd, maar staat in het logbestand op de server (data/mail.log).');
                } else {
                    flash('succes', 'Opgeslagen, en de testmail is verstuurd naar ' . $gebruiker['email'] . '. Kijk ook even in de map met ongewenste e-mail.');
                }
            } else {
                flash('succes', $gewijzigd ? 'De e-mailinstellingen zijn opgeslagen.' : 'Er was niets veranderd.');
            }
            redirect('koppelingen.php#email');
        }
    }
}

/* ---------- Pagina ---------- */

$wie = function (string $sleutel) use ($opgeslagen): string {
    $rij = $opgeslagen[$sleutel] ?? null;
    if (!$rij) {
        return '';
    }
    $door = $rij['gewijzigd_door'] ? rij('SELECT naam FROM gebruikers WHERE id = ?', [$rij['gewijzigd_door']]) : null;
    return ' Opgeslagen ' . e(datum_nl($rij['gewijzigd_op'], "d MMMM y 'om' HH:mm")) . ($door ? ' door ' . e($door['naam']) : '') . '.';
};
$uitConfig = '<span class="field__hint">Ingesteld in config.php op de server; daar aanpassen.</span>';
$geheimVeld = function (string $sleutel, string $naam, string $label, string $voorbeeld, string $uitleg) use ($fouten, $wie, $uitConfig): string {
    $huidig = (string) koppeling($sleutel);
    $hintId = $naam . '-hint';
    if (koppeling_in_config($sleutel)) {
        return '<div class="field"><span class="legend-label">' . e($label) . '</span><p class="koppeling__waarde"><code>' . e(koppeling_masker($sleutel, $huidig)) . '</code> ' . $uitConfig . '</p></div>';
    }
    return '<div class="field"><label for="' . $naam . '">' . e($label) . '</label>'
        . '<input id="' . $naam . '" name="' . $naam . '" type="password" autocomplete="new-password" spellcheck="false" placeholder="' . e($voorbeeld) . '" aria-describedby="' . $hintId . (isset($fouten[$naam]) ? ' ' . $naam . '-fout' : '') . '"' . (isset($fouten[$naam]) ? ' aria-invalid="true"' : '') . '>'
        . veldfout($fouten, $naam)
        . '<p class="field__hint" id="' . $hintId . '">' . ($huidig !== '' ? 'Nu: <code>' . e(koppeling_masker($sleutel, $huidig)) . '</code>.' . $wie($sleutel) . ' Laat leeg om te houden. ' : 'Nog niet ingevuld. ') . $uitleg . '</p></div>';
};
$tekstVeld = function (string $sleutel, string $naam, string $label, array $extra = []) use ($fouten, $invoer, $uitConfig): string {
    $inConfig = koppeling_in_config($sleutel);
    return '<div class="field' . (!empty($extra['klein']) ? ' field--klein' : '') . '"><label for="' . $naam . '">' . e($label) . '</label>'
        . '<input id="' . $naam . '" name="' . $naam . '"' . (!empty($extra['type']) ? ' type="' . $extra['type'] . '"' : '') . (!empty($extra['inputmode']) ? ' inputmode="' . $extra['inputmode'] . '"' : '') . ' value="' . e((string) $invoer[$naam]) . '"' . ($inConfig ? ' disabled' : '') . (!empty($extra['hint']) ? ' aria-describedby="' . $naam . '-hint"' : '') . aria_fout($fouten, $naam) . ' autocomplete="off" spellcheck="false">'
        . veldfout($fouten, $naam)
        . ($inConfig ? $uitConfig : '')
        . (!empty($extra['hint']) ? '<p class="field__hint" id="' . $naam . '-hint">' . $extra['hint'] . '</p>' : '')
        . '</div>';
};
$keuzes = function (string $naam, array $opties, array $gekozen, string $legend, string $type = 'checkbox') use ($fouten, $uitConfig): string {
    $sleutel = $naam === 'methode' ? 'mail.methode' : 'stripe.' . $naam;
    $inConfig = koppeling_in_config($sleutel);
    $html = '<fieldset class="field fotokeuze"' . (isset($fouten[$naam]) ? ' aria-describedby="' . $naam . '-fout"' : '') . '><legend class="legend-label">' . e($legend) . '</legend>' . veldfout($fouten, $naam) . ($inConfig ? $uitConfig : '') . '<div class="choices choices--stapel">';
    foreach ($opties as $waarde => [$label, $uitleg]) {
        $id = $naam . '-' . $waarde;
        $html .= '<label class="choice choice--uitleg" for="' . $id . '"><input type="' . $type . '" id="' . $id . '" name="' . $naam . ($type === 'checkbox' ? '[]' : '') . '" value="' . e($waarde) . '"'
            . (in_array($waarde, $gekozen, true) ? ' checked' : '') . ($inConfig ? ' disabled' : '') . ' aria-describedby="' . $id . '-uitleg"><span><strong>' . e($label) . '</strong><span class="choice__uitleg" id="' . $id . '-uitleg">' . e($uitleg) . '</span></span></label>';
    }
    return $html . '</div></fieldset>';
};

$stripeStatus = !stripe_actief() ? '<span class="badge badge--demo">Niet gekoppeld</span>' : (stripe_testmodus() ? '<span class="badge badge--in_behandeling">Testmodus</span>' : '<span class="badge badge--geldig">Live</span>');
$mailStatus = ['log' => '<span class="badge badge--demo">Testmodus</span>', 'mail' => '<span class="badge badge--geldig">Mailfunctie server</span>', 'smtp' => '<span class="badge badge--geldig">Eigen mailserver</span>'][mail_methode()];
$ontvangen = instelling('stripe_webhook_ontvangen');
$geweigerd = instelling('stripe_webhook_geweigerd');
$mailFout = instelling('mail_laatste_fout');
$mailOk = instelling('mail_laatst_verstuurd');

pagina_begin('Koppelingen', 'koppelingen.php');
pagina_kop('Koppelingen', 'Hier koppel je Mijn BSO aan andere diensten. Sleutels en wachtwoorden worden versleuteld opgeslagen: na het opslaan zie je alleen nog het begin en het einde.');
?>
<?= foutensamenvatting($fouten) ?>
<div class="kolommen kolommen--gelijk">
  <section class="panel" id="stripe" aria-labelledby="stripe-titel">
    <div class="panel__kop"><h2 id="stripe-titel"><?= icoon('bank') ?>Stripe</h2><?= $stripeStatus ?></div>
    <p class="muted">Voor de machtiging voor automatische incasso, de maandelijkse incasso's en het los betalen van een factuur.<?= !stripe_actief() ? ' Zonder Stripe draait Mijn BSO in demo-modus: er wordt niets echt afgeschreven.' : '' ?></p>
    <form class="form" method="post" action="koppelingen.php#stripe">
      <?= csrf_veld() ?>
      <input type="hidden" name="actie" value="stripe">
      <?= $geheimVeld('stripe.geheime_sleutel', 'geheime_sleutel', 'Geheime sleutel', 'sk_live_… of sk_test_…', 'In het dashboard van Stripe onder Ontwikkelaars → API-sleutels. Begin met de testsleutel (sk_test_…). Mijn BSO test een nieuwe sleutel voordat hij wordt opgeslagen.') ?>
      <?= $geheimVeld('stripe.webhook_geheim', 'webhook_geheim', 'Webhookgeheim', 'whsec_…', 'Bij de webhook in het dashboard van Stripe ("Ondertekeningsgeheim"). Hiermee weet Mijn BSO zeker dat een melding echt van Stripe komt.') ?>
      <?= $keuzes('methoden_machtiging', STRIPE_KEUZES_MACHTIGING, $invoer['methoden_machtiging'], 'Ouders geven de machtiging af via') ?>
      <?= $keuzes('methoden_betalen', STRIPE_KEUZES_BETALEN, $invoer['methoden_betalen'], 'Een factuur los betalen kan met') ?>
      <p class="field__hint" style="margin: 0">Zet de gekozen betaalmethoden ook aan in het dashboard van Stripe (Instellingen → Betaalmethoden), en SEPA-incasso voor de automatische incasso.</p>
      <div class="field">
        <label for="bevestig_wachtwoord">Je eigen wachtwoord <span class="field__hint">(alleen nodig als je een sleutel wijzigt)</span></label>
        <input id="bevestig_wachtwoord" name="bevestig_wachtwoord" type="password" autocomplete="current-password"<?= aria_fout($fouten, 'bevestig_wachtwoord') ?>>
        <?= veldfout($fouten, 'bevestig_wachtwoord') ?>
      </div>
      <div class="form__acties"><button class="btn" type="submit"><?= icoon('check') ?>Opslaan</button></div>
    </form>

    <details class="uitklap koppeling__webhook"<?= stripe_actief() && !$ontvangen ? ' open' : '' ?>>
      <summary><?= icoon('link') ?>Webhook instellen bij Stripe</summary>
      <p>Maak in het dashboard van Stripe onder <strong>Ontwikkelaars → Webhooks</strong> een webhook aan naar dit adres:</p>
      <label class="legend-label" for="webhook-url">Adres van de webhook</label>
      <input id="webhook-url" class="koppeling__kopieer" readonly value="<?= e(app_url('stripe-webhook.php')) ?>">
      <p>Met deze gebeurtenissen:</p>
      <ul class="koppeling__events"><?php foreach (STRIPE_EVENTS as $event): ?><li><code><?= e($event) ?></code></li><?php endforeach; ?></ul>
      <p>Zet daarna het ondertekeningsgeheim (whsec_…) hierboven bij Webhookgeheim.</p>
    </details>
    <dl class="gegevens">
      <dt>Laatste melding van Stripe</dt><dd><?= $ontvangen ? e(moment_nl($ontvangen)) : 'Nog geen. Die komt zodra er iets gebeurt, bijvoorbeeld een machtiging in testmodus.' ?></dd>
<?php if ($geweigerd && $geweigerd >= $ontvangen): ?>
      <dt>Geweigerde melding</dt><dd><span class="let-op"><?= icoon('alert') ?><span><?= e(moment_nl($geweigerd)) ?>: de handtekening klopte niet. Controleer het webhookgeheim (test- en live-webhooks hebben elk een eigen geheim).</span></span></dd>
<?php endif; ?>
    </dl>
<?php if (stripe_actief()): ?>
    <div class="btn-group">
      <form method="post" action="koppelingen.php#stripe"><?= csrf_veld() ?><input type="hidden" name="actie" value="stripe_testen"><button class="btn btn--secondary btn--small" type="submit"><?= icoon('check') ?>Verbinding testen</button></form>
    </div>
<?php if (!koppeling_in_config('stripe.geheime_sleutel')): ?>
    <details class="uitklap">
      <summary><?= icoon('x') ?>Stripe ontkoppelen</summary>
      <form class="form" method="post" action="koppelingen.php#stripe" data-bevestig="Stripe ontkoppelen? Mijn BSO gaat dan terug naar demo-modus: er worden geen incasso's meer gestart en lopende betalingen worden niet meer bijgewerkt.">
        <?= csrf_veld() ?><input type="hidden" name="actie" value="stripe_ontkoppelen">
        <p class="muted">De sleutels worden uit Mijn BSO verwijderd. Machtigingen en betalingen bij Stripe blijven bestaan.</p>
        <div class="field"><label for="bevestig-ontkoppelen">Je eigen wachtwoord</label><input id="bevestig-ontkoppelen" name="bevestig_wachtwoord" type="password" autocomplete="current-password" required></div>
        <div class="form__acties"><button class="btn btn--gevaar btn--small" type="submit">Ontkoppelen</button></div>
      </form>
    </details>
<?php endif; ?>
<?php endif; ?>
  </section>

  <section class="panel" id="email" aria-labelledby="email-titel">
    <div class="panel__kop"><h2 id="email-titel"><?= icoon('mail') ?>E-mail versturen</h2><?= $mailStatus ?></div>
    <p class="muted">Hoe Mijn BSO e-mails verstuurt, zoals de bevestiging van een aanmelding, een nieuw wachtwoord of een melding voor het team. De teksten pas je aan onder <?= link_als('instellingen', 'mailteksten.php', 'E-mails aanpassen') ?>.</p>
    <form class="form koppeling__mail" method="post" action="koppelingen.php#email">
      <?= csrf_veld() ?>
      <?= $keuzes('methode', [
          'smtp' => ['Eigen mailserver (SMTP)', 'Aanbevolen. De e-mail gaat via de mailbox van jullie eigen domein, zoals info@sporty.nl, en komt zo het best aan.'],
          'mail' => ['Mailfunctie van de webserver', 'Werkt bij de meeste webhosting zonder instellingen, maar komt vaker in de map met ongewenste e-mail terecht.'],
          'log' => ['Testmodus', 'Er wordt niets verstuurd. E-mails komen alleen in het logbestand op de server (data/mail.log).'],
      ], [$invoer['methode']], 'Versturen via', 'radio') ?>
      <?= $tekstVeld('mail.van', 'van', 'Afzenderadres', ['type' => 'email']) ?>
      <?= $tekstVeld('mail.van_naam', 'van_naam', 'Afzendernaam') ?>
      <?= $tekstVeld('team_email', 'team_email', 'Teamadres', ['type' => 'email', 'hint' => 'Hier komen meldingen binnen, zoals een nieuwe aanmelding, een bericht of een privacyverzoek. Antwoordt een ouder op een e-mail, dan komt dat antwoord ook hier.']) ?>
      <fieldset class="koppeling__smtp">
        <legend class="legend-label">Eigen mailserver <span class="field__hint">(alleen nodig bij SMTP; de gegevens krijg je van jullie e-mailprovider)</span></legend>
        <?= $tekstVeld('smtp.host', 'host', 'Mailserver (SMTP)', ['hint' => 'Bijvoorbeeld smtp.office365.com, smtp.gmail.com of mail.jouwprovider.nl.']) ?>
        <div class="form__row">
          <?= $tekstVeld('smtp.poort', 'poort', 'Poort', ['inputmode' => 'numeric', 'klein' => true]) ?>
          <div class="field">
            <label for="beveiliging">Beveiliging</label>
            <select id="beveiliging" name="beveiliging"<?= koppeling_in_config('smtp.beveiliging') ? ' disabled' : '' ?>>
              <option value="starttls"<?= $invoer['beveiliging'] === 'starttls' ? ' selected' : '' ?>>STARTTLS (poort 587)</option>
              <option value="ssl"<?= $invoer['beveiliging'] === 'ssl' ? ' selected' : '' ?>>SSL/TLS (poort 465)</option>
            </select>
          </div>
        </div>
        <?= $tekstVeld('smtp.gebruiker', 'gebruiker', 'Gebruikersnaam', ['hint' => 'Meestal het volledige e-mailadres van de mailbox.']) ?>
        <?= $geheimVeld('smtp.wachtwoord', 'smtp_wachtwoord', 'Wachtwoord', '', 'Gebruikt jullie provider tweestapsverificatie? Maak dan een app-wachtwoord aan en vul dat hier in.') ?>
      </fieldset>
      <div class="field">
        <label for="bevestig_wachtwoord_mail">Je eigen wachtwoord <span class="field__hint">(alleen nodig als je het wachtwoord van de mailbox wijzigt)</span></label>
        <input id="bevestig_wachtwoord_mail" name="bevestig_wachtwoord" type="password" autocomplete="current-password"<?= aria_fout($fouten, 'bevestig_wachtwoord_mail') ?>>
        <?= veldfout($fouten, 'bevestig_wachtwoord_mail') ?>
      </div>
      <div class="form__acties">
        <button class="btn" type="submit" name="actie" value="mail"><?= icoon('check') ?>Opslaan</button>
        <button class="btn btn--secondary" type="submit" name="actie" value="mail_test"><?= icoon('send') ?>Opslaan en testmail naar mij</button>
      </div>
    </form>
    <dl class="gegevens">
      <dt>Laatst verstuurd</dt><dd><?= $mailOk ? e(moment_nl($mailOk)) : (mail_methode() === 'log' ? 'Nog niets: testmodus' : 'Nog niets') ?></dd>
<?php if ($mailFout && substr($mailFout, 0, 19) > $mailOk): ?>
      <dt>Laatste fout</dt><dd><span class="let-op"><?= icoon('alert') ?><span><?= e(moment_nl(substr($mailFout, 0, 19))) ?>: <?= e(substr($mailFout, 20)) ?></span></span></dd>
<?php endif; ?>
    </dl>
  </section>
</div>
<p class="note"><?= icoon('lock') ?><span>Alleen collega's met het recht "Koppelingen" zien deze pagina (zie <?= link_als('team', 'medewerkers.php', 'Team') ?>). Elke wijziging staat in het <?= link_als('instellingen', 'logboek.php', 'logboek') ?>, zonder de sleutels zelf. Wat in config.php op de server staat, gaat voor op wat hier staat.</span></p>
<?php
pagina_einde();
