<?php
/*
 * Vrijblijvend aanmelden via de website (aanmelden.html, "Kom bij de club"). Publiek, zonder inloggen.
 *
 * - GET ?info=1: de weekdagen die vol zitten (die kunnen ouders niet aanvinken).
 * - POST ?stap=1..8: telt anoniem dat een stap is bereikt, om te zien waar ouders afhaken
 *   (geen cookies en geen persoonsgegevens; 8 = verstuurd).
 * - POST met het formulier: slaat de aanvraag op (zie inc/aanvraag.php) en geeft JSON terug
 *   ({"ok": true} of {"ok": false, "fouten": {veld: melding}}), of zonder JavaScript een pagina.
 *
 * Tegen spam: een verborgen veld, alleen vanaf de eigen website, en hooguit 10 aanvragen per kwartier
 * vanaf hetzelfde adres.
 */
require __DIR__ . '/inc/bootstrap.php';
// Staat de website op een ander adres (bsosporty.nl, Mijn BSO op mijn.bsosporty.nl), dan mag die het antwoord lezen
cors_voor_website();

$alsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function aanvraag_antwoord(bool $alsJson, int $code, array $fouten = [], string $melding = ''): never
{
    http_response_code($code);
    if ($alsJson) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => $code < 300, 'fouten' => (object) $fouten, 'melding' => $melding], JSON_UNESCAPED_UNICODE);
        exit;
    }
    pagina_begin($code < 300 ? t('Aanmelding verstuurd', 'Sign-up sent') : t('Aanmelding niet verstuurd', 'Sign-up not sent'), '', ['publiek' => true]);
    echo '<div class="auth"><div class="panel">';
    if ($code < 300) {
        echo '<h1>' . e(t('Gelukt! Je aanmelding is binnen.', 'Done! Your sign-up is in.')) . '</h1>';
        echo '<div class="melding melding--succes" role="status">' . icoon('check') . '<p>' . e(t('Bedankt voor je aanmelding! Check je mail: daarin staat een link om meteen je wachtwoord voor Mijn BSO aan te maken. Geen mail binnen een paar minuten? Kijk dan ook even bij je ongewenste e-mail. We bekijken de mogelijkheden en nemen binnen 24 uur contact met je op.', "Thanks for signing up! Check your inbox: we've sent you a link to create your password for Mijn BSO straight away. No email within a few minutes? Have a look in your spam folder too. We'll look at the options and get in touch within 24 hours.")) . '</p></div>';
    } else {
        echo '<h1>' . e(t('Je aanmelding is nog niet verstuurd', "Your sign-up hasn't been sent yet")) . '</h1>';
        echo '<div class="melding melding--fout" role="alert">' . icoon('alert') . '<p>' . e($melding ?: t('Er ging iets niet goed:', 'Something went wrong:')) . '</p></div>';
        if ($fouten) {
            echo '<ul>';
            foreach ($fouten as $tekst) {
                echo '<li>' . e($tekst) . '</li>';
            }
            echo '</ul>';
        }
    }
    echo '<a class="btn btn--secondary" href="' . e(website_pagina('aanmelden.html', 'join-the-club.html')) . '">' . icoon('back') . e(t('Terug naar het aanmeldformulier', 'Back to the sign-up form')) . '</a>';
    echo '</div></div>';
    pagina_einde();
    exit;
}

if (!is_post()) {
    if (isset($_GET['info'])) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => true, 'volle_dagen' => array_map('intval', volle_dagen())]);
        exit;
    }
    redirect(website_pagina('aanmelden.html', 'join-the-club.html'));
}

// Alleen vanaf onze eigen website
if (!eigen_herkomst()) {
    aanvraag_antwoord($alsJson, 403, [], t('Je aanmelding kan alleen via onze eigen website worden verstuurd.', 'You can only send your sign-up via our own website.'));
}

// Anonieme telling per stap (hooguit 60 per kwartier per adres, zodat niemand de cijfers kan opblazen)
if (isset($_GET['stap'])) {
    if (!te_veel_verzoeken('as:' . ip_adres(), 60)) {
        tel_aanvraagstap((int) $_GET['stap']);
    }
    http_response_code(204);
    exit;
}

// Verborgen veld ingevuld: een robot. Doe alsof het gelukt is, maar bewaar niets.
if (invoer('website') !== '') {
    aanvraag_antwoord($alsJson, 200);
}
if (te_veel_verzoeken('a:' . ip_adres(), 10)) {
    aanvraag_antwoord($alsJson, 429, [], t('Er zijn net erg veel aanmeldingen verstuurd vanaf dit netwerk. Probeer het over een kwartier opnieuw, of bel ons even.', 'A lot of sign-ups were just sent from this network. Please try again in 15 minutes, or give us a call.'));
}

[$aanvraag, $fouten] = lees_aanvraag($_POST);
if ($fouten) {
    aanvraag_antwoord($alsJson, 422, $fouten, count($fouten) === 1 ? t('Er is nog 1 veld niet goed ingevuld.', "1 field isn't filled in correctly yet.") : t('Er zijn nog ' . count($fouten) . ' velden niet goed ingevuld.', count($fouten) . " fields aren't filled in correctly yet."));
}

// Hooguit 3 e-mails per kwartier naar hetzelfde adres (geen mailbom via ons formulier). De aanmelding zelf
// bewaren we wel; alleen de e-mail slaan we dan over (te zien onder E-mails → Verzonden).
$mailMag = !te_veel_mails_naar($aanvraag['ouder']['email']);

// Bekend e-mailadres: niets opnieuw opslaan, wel een mail naar dat adres. Op de website zie je
// hetzelfde antwoord als bij een nieuwe aanmelding, zodat niemand kan uitproberen wie er klant is.
$bekend = rij('SELECT id, email FROM gebruikers WHERE email = ?', [$aanvraag['ouder']['email']]);
if ($bekend) {
    tel_aanvraagstap(8);
    log_actie('Aanmelding met een bekend e-mailadres', 'niet opnieuw opgeslagen', (int) $bekend['id'], 'wijziging', 'gebruiker:' . $bekend['id']);
    if ($mailMag) {
        stuur_mailtekst($bekend['email'], 'aanmelding_bestaand_account', ['inloggen' => app_url('inloggen.php'), 'wachtwoord' => app_url('wachtwoord-vergeten.php')]);
    } else {
        mail_overgeslagen($bekend['email'], mailtekst_in_taal('aanmelding_bestaand_account'), MAIL_REDEN_TE_VEEL);
    }
    aanvraag_antwoord($alsJson, 200);
}

try {
    $ouderId = bewaar_aanvraag($aanvraag);
} catch (Throwable $fout) {
    error_log('Aanvraag opslaan: ' . $fout->getMessage());
    aanvraag_antwoord($alsJson, 500, [], t('Het versturen is niet gelukt. Probeer het nog eens, of bel of mail ons.', "Sending didn't work. Please try again, or call or email us."));
}
tel_aanvraagstap(8);
$aantal = count($aanvraag['kinderen']);
log_actie('Aangemeld via de website', $aantal . ' kind(eren)', $ouderId, 'wijziging', 'gebruiker:' . $ouderId);

// Melding voor het team (zonder persoonsgegevens) en meteen een bevestiging voor de ouder, met een link om
// direct een wachtwoord voor Mijn BSO te kiezen (een week geldig; daarna via "Wachtwoord vergeten", en bij het
// activeren stuurt Mijn BSO opnieuw een link als er nog geen wachtwoord is). Via de e-mail weten we ook zeker
// dat het adres klopt. Inloggen kan dan meteen; dagen kiezen pas als het team het account heeft geactiveerd.
mail_team_tekst('team_aanmelding', ['aantal' => $aantal . ' ' . ($aantal === 1 ? 'kind' : 'kinderen'), 'link' => app_url('ouder.php?id=' . $ouderId)]);
$namen = array_map(fn ($k) => $k['voornaam'], $aanvraag['kinderen']);
// TODO: tekst van de bevestigingsmail volgt van Sporty (nu een voorlopige tekst). Sporty past hem zelf aan onder E-mails.
if ($mailMag) {
    stuur_mailtekst($aanvraag['ouder']['email'], 'aanmelding_ontvangen', [
        'voornaam' => $aanvraag['ouder']['voornaam'],
        'kinderen' => count($namen) > 1 ? implode(', ', array_slice($namen, 0, -1)) . t(' en ', ' and ') . end($namen) : $namen[0],
        'link' => maak_wachtwoordlink($ouderId, 24 * 7) . (taal() === 'en' ? '&taal=en' : ''),
    ]);
} else {
    mail_overgeslagen($aanvraag['ouder']['email'], mailtekst_in_taal('aanmelding_ontvangen'), MAIL_REDEN_TE_VEEL);
}

aanvraag_antwoord($alsJson, 200);
