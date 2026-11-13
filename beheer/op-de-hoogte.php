<?php
/*
 * Blijf op de hoogte (openbaar): aanmelden via het formulier op blijf-op-de-hoogte.html, en de links in de
 * e-mails om te bevestigen en af te melden.
 *
 * POST met naam, email, telefoon en toestemming: aanmelden. Antwoord als JSON voor de website met
 * JavaScript ({"ok": true} of {"ok": false, "fouten": {...}}), of zonder JavaScript een eenvoudige pagina.
 * GET ?bevestig=code of ?afmelden=code: een pagina met één knop. Pas de klik (POST) bevestigt of meldt af,
 * zodat een virusscanner die links in e-mails vooraf opent niets kan aanzetten of uitzetten.
 *
 * Tegen spam: een verborgen veld, alleen vanaf de eigen website, en hooguit MAX_INTERESSE_PER_KWARTIER
 * aanmeldingen per kwartier vanaf hetzelfde adres.
 */
require __DIR__ . '/inc/bootstrap.php';

$alsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

/** Een eenvoudige openbare pagina met een melding en eventueel een knop. */
function op_de_hoogte_pagina(string $titel, string $tekst, bool $gelukt, string $knop = ''): never
{
    pagina_begin($titel, '', ['publiek' => true]);
    echo '<div class="auth"><div class="panel">';
    echo '<h1>' . e($titel) . '</h1>';
    echo '<div class="melding melding--' . ($gelukt ? 'succes' : 'fout') . '" role="' . ($gelukt ? 'status' : 'alert') . '">' . icoon($gelukt ? 'check' : 'alert') . '<p>' . $tekst . '</p></div>';
    echo $knop;
    echo '<p><a class="btn btn--secondary" href="' . e(website_pagina('index.html', 'index.html')) . '">' . icoon('back') . e(t('Naar de website', 'To the website')) . '</a></p>';
    echo '</div></div>';
    pagina_einde();
    exit;
}

function op_de_hoogte_antwoord(bool $alsJson, int $code, array $fouten = [], string $melding = ''): never
{
    http_response_code($code);
    if ($alsJson) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => $code < 300, 'fouten' => (object) $fouten, 'melding' => $melding], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($code < 300) {
        op_de_hoogte_pagina(t('Bijna klaar!', 'Almost done!'), e(t('We hebben je een e-mail gestuurd. Klik op de link in die e-mail om je aanmelding te bevestigen. Niets gezien? Kijk ook even in je spam.', "We've sent you an email. Click the link in it to confirm your sign-up. Nothing there? Have a look in your spam folder too.")), true);
    }
    op_de_hoogte_pagina(t('Aanmelden is nog niet gelukt', "Signing up hasn't worked yet"), e($melding ?: t('Er ging iets niet goed.', 'Something went wrong.')) . ($fouten ? '<br>' . implode('<br>', array_map('e', $fouten)) : ''), false, '<p><a class="btn" href="' . e(website_pagina('blijf-op-de-hoogte.html', 'stay-in-the-loop.html')) . '">' . e(t('Probeer het nog eens', 'Try again')) . '</a></p>');
}

/* ---------- Links uit de e-mails: eerst een knop, dan pas bevestigen of afmelden ---------- */

foreach (['bevestig', 'afmelden'] as $soort) {
    $token = (string) ($_GET[$soort] ?? (is_post() && invoer('actie') === $soort ? invoer('token') : ''));
    if ($token === '') {
        continue;
    }
    $rij = interesse_via_token($token);
    if (!$rij) {
        op_de_hoogte_pagina(t('Deze link werkt niet (meer)', "This link doesn't work (any more)"), e($soort === 'afmelden'
            ? t('Je staat niet (meer) op onze lijst. Je krijgt van ons geen e-mails meer.', "You're not on our list (any more). You won't receive any more emails from us.")
            : t('Misschien heb je je al afgemeld, of is de link verlopen. Meld je gerust opnieuw aan.', 'Maybe you already unsubscribed, or the link has expired. Feel free to sign up again.')), $soort === 'afmelden', $soort === 'afmelden' ? '' : '<p><a class="btn" href="' . e(website_pagina('blijf-op-de-hoogte.html', 'stay-in-the-loop.html')) . '">' . e(t('Opnieuw aanmelden', 'Sign up again')) . '</a></p>');
    }
    // De pagina's in de taal waarin iemand zich aanmeldde
    taal((string) ($rij['taal'] ?? 'nl'));
    if (!is_post()) {
        $knop = '<form method="post" action="op-de-hoogte.php"><input type="hidden" name="actie" value="' . $soort . '"><input type="hidden" name="token" value="' . e($token) . '">'
            . '<button class="btn" type="submit">' . e($soort === 'afmelden' ? t('Ja, meld me af', 'Yes, unsubscribe me') : t('Ja, bevestig mijn aanmelding', 'Yes, confirm my sign-up')) . '</button></form>';
        op_de_hoogte_pagina($soort === 'afmelden' ? t('Afmelden', 'Unsubscribe') : t('Bevestig je aanmelding', 'Confirm your sign-up'), e($soort === 'afmelden'
            ? t('Wil je geen e-mails meer van Sporty? Klik op de knop. We wissen dan je gegevens.', "Don't want any more emails from Sporty? Click the button and we'll delete your details.")
            : t('Nog één klik, dan blijf je op de hoogte van Sporty en krijg je het kennismakingspakket.', "One more click and you'll stay in the loop with Sporty, and get the Sporty welcome goodies.")), true, $knop);
    }
    if ($soort === 'afmelden') {
        interesse_verwijder($rij, 'afgemeld via de link in de e-mail');
        op_de_hoogte_pagina(t('Je bent afgemeld', "You've been unsubscribed"), e(t('Je krijgt geen e-mails meer van ons, en we hebben je gegevens gewist. Jammer dat je gaat. Je bent altijd welkom bij de club!', "You won't receive any more emails from us, and we've deleted your details. Sorry to see you go. You're always welcome at the club!")), true);
    }
    if (!$rij['bevestigd_op']) {
        q('UPDATE interesse SET bevestigd_op = ? WHERE id = ?', [nu(), $rij['id']]);
        log_actie('Blijf op de hoogte: aanmelding bevestigd', '', null, 'wijziging', 'interesse:' . $rij['id']);
        // Melding voor het team, zonder persoonsgegevens
        mail_team_tekst('team_op_de_hoogte', ['link' => app_url('interesse.php')]);
    }
    op_de_hoogte_pagina(t('Gelukt, je blijft op de hoogte!', "Done, you're in the loop!"), e(t('Bedankt! Je staat op onze lijst en we laten je weten hoe je je Sporty-kennismakingspakket krijgt. Afmelden kan altijd, met de link onder elke e-mail.', "Thanks! You're on our list and we'll let you know how you'll receive your Sporty welcome goodies. You can unsubscribe any time, with the link at the bottom of every email.")), true);
}

/* ---------- Aanmelden via het formulier op de website ---------- */

if (!is_post()) {
    redirect(website_pagina('blijf-op-de-hoogte.html', 'stay-in-the-loop.html'));
}

// Alleen vanaf onze eigen website (de browser stuurt mee waar het formulier vandaan komt)
$herkomst = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$eigen = [parse_url(basis_url(), PHP_URL_HOST), parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)];
if ($herkomst !== '' && !in_array(parse_url($herkomst, PHP_URL_HOST), $eigen, true)) {
    op_de_hoogte_antwoord($alsJson, 403, [], t('Aanmelden kan alleen via onze eigen website.', 'You can only sign up via our own website.'));
}

// Verborgen veld ingevuld: een robot. Doe alsof het gelukt is, maar bewaar niets.
if (invoer('website') !== '') {
    op_de_hoogte_antwoord($alsJson, 200);
}

if (recente_pogingen('interesse:' . ip_adres()) >= MAX_INTERESSE_PER_KWARTIER || te_veel_verzoeken('interesse-poging:' . ip_adres(), 30)) {
    op_de_hoogte_antwoord($alsJson, 429, [], t('Er zijn net al een paar aanmeldingen vanaf dit adres gedaan. Probeer het over een kwartier nog eens.', 'A few sign-ups were just made from this address. Please try again in 15 minutes.'));
}

$naam = mb_substr(invoer('naam'), 0, 120);
$email = mb_substr(invoer('email'), 0, 200);
$telefoon = mb_substr(invoer('telefoon'), 0, 30);
$fouten = [];
if (!geldige_naam($naam)) {
    $fouten['naam'] = $naam === '' ? t('Vul je naam in.', 'Fill in your name.') : t('Vul je naam in (zonder links of e-mailadressen).', 'Fill in your name (without links or email addresses).');
}
if (!geldig_email($email)) {
    $fouten['email'] = $email === '' ? t('Vul je e-mailadres in, dan sturen we je een bevestiging.', "Fill in your email address and we'll send you a confirmation.") : t('Dit lijkt geen geldig e-mailadres. Controleer het even, bijvoorbeeld naam@voorbeeld.nl.', "That doesn't look like a valid email address. Please check it, for example name@example.com.");
}
if ($telefoon !== '' && !geldig_telefoon($telefoon)) {
    $fouten['telefoon'] = t('Vul een geldig telefoonnummer in, bijvoorbeeld 06 12 34 56 78, of laat het leeg.', 'Fill in a valid phone number, for example +31 6 12 34 56 78, or leave it empty.');
}
if (invoer('toestemming') === '') {
    $fouten['toestemming'] = t('Vink aan dat we je op de hoogte mogen houden.', 'Tick the box to let us keep you in the loop.');
}
if ($fouten) {
    op_de_hoogte_antwoord($alsJson, 422, $fouten, count($fouten) === 1 ? t('Er is nog 1 veld niet goed ingevuld.', "1 field isn't filled in correctly yet.") : t('Er zijn nog ' . count($fouten) . ' velden niet goed ingevuld.', count($fouten) . " fields aren't filled in correctly yet."));
}

// Te veel mails naar dit adres: doe alsof het gelukt is (zo verklappen we niets), maar stuur niets
if (te_veel_mails_naar($email)) {
    op_de_hoogte_antwoord($alsJson, 200);
}

try {
    interesse_aanmelden($naam, $email, $telefoon);
} catch (Throwable $fout) {
    error_log('Blijf op de hoogte: ' . $fout->getMessage());
    op_de_hoogte_antwoord($alsJson, 500, [], t('Aanmelden is niet gelukt. Probeer het nog eens, of mail ons.', "Signing up didn't work. Please try again, or email us."));
}
registreer_poging('interesse:' . ip_adres());
op_de_hoogte_antwoord($alsJson, 200);
