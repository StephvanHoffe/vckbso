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
    echo '<p><a class="btn btn--secondary" href="../index.html">' . icoon('back') . 'Naar de website</a></p>';
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
        op_de_hoogte_pagina('Bijna klaar!', 'We hebben je een e-mail gestuurd. Klik op de link in die e-mail om je aanmelding te bevestigen. Niets gezien? Kijk ook even in je spam.', true);
    }
    op_de_hoogte_pagina('Aanmelden is nog niet gelukt', e($melding ?: 'Er ging iets niet goed.') . ($fouten ? '<br>' . implode('<br>', array_map('e', $fouten)) : ''), false, '<p><a class="btn" href="../blijf-op-de-hoogte.html">Probeer het nog eens</a></p>');
}

/* ---------- Links uit de e-mails: eerst een knop, dan pas bevestigen of afmelden ---------- */

foreach (['bevestig', 'afmelden'] as $soort) {
    $token = (string) ($_GET[$soort] ?? (is_post() && invoer('actie') === $soort ? invoer('token') : ''));
    if ($token === '') {
        continue;
    }
    $rij = interesse_via_token($token);
    if (!$rij) {
        op_de_hoogte_pagina('Deze link werkt niet (meer)', $soort === 'afmelden'
            ? 'Je staat niet (meer) op onze lijst. Je krijgt van ons geen e-mails meer.'
            : 'Misschien heb je je al afgemeld, of is de link verlopen. Meld je gerust opnieuw aan.', $soort === 'afmelden', $soort === 'afmelden' ? '' : '<p><a class="btn" href="../blijf-op-de-hoogte.html">Opnieuw aanmelden</a></p>');
    }
    if (!is_post()) {
        $knop = '<form method="post" action="op-de-hoogte.php"><input type="hidden" name="actie" value="' . $soort . '"><input type="hidden" name="token" value="' . e($token) . '">'
            . '<button class="btn" type="submit">' . ($soort === 'afmelden' ? 'Ja, meld me af' : 'Ja, bevestig mijn aanmelding') . '</button></form>';
        op_de_hoogte_pagina($soort === 'afmelden' ? 'Afmelden' : 'Bevestig je aanmelding', $soort === 'afmelden'
            ? 'Wil je geen e-mails meer van Sporty? Klik op de knop. We wissen dan je gegevens.'
            : 'Nog één klik, dan blijf je op de hoogte van Sporty en krijg je het kennismakingspakket.', true, $knop);
    }
    if ($soort === 'afmelden') {
        interesse_verwijder($rij, 'afgemeld via de link in de e-mail');
        op_de_hoogte_pagina('Je bent afgemeld', 'Je krijgt geen e-mails meer van ons, en we hebben je gegevens gewist. Jammer dat je gaat. Je bent altijd welkom bij de club!', true);
    }
    if (!$rij['bevestigd_op']) {
        q('UPDATE interesse SET bevestigd_op = ? WHERE id = ?', [nu(), $rij['id']]);
        log_actie('Blijf op de hoogte: aanmelding bevestigd', '', null, 'wijziging', 'interesse:' . $rij['id']);
        // Melding voor het team, zonder persoonsgegevens
        mail_team('Nieuwe aanmelding: Blijf op de hoogte', "Er heeft zich iemand aangemeld om op de hoogte te blijven. Vergeet het kennismakingspakket niet!\n\nBekijk de lijst in de beheeromgeving:\n" . app_url('interesse.php'));
    }
    op_de_hoogte_pagina('Gelukt, je blijft op de hoogte!', 'Bedankt! Je staat op onze lijst en we laten je weten hoe je je Sporty-kennismakingspakket krijgt. Afmelden kan altijd, met de link onder elke e-mail.', true);
}

/* ---------- Aanmelden via het formulier op de website ---------- */

if (!is_post()) {
    redirect('../blijf-op-de-hoogte.html');
}

// Alleen vanaf onze eigen website (de browser stuurt mee waar het formulier vandaan komt)
$herkomst = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$eigen = [parse_url(basis_url(), PHP_URL_HOST), parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)];
if ($herkomst !== '' && !in_array(parse_url($herkomst, PHP_URL_HOST), $eigen, true)) {
    op_de_hoogte_antwoord($alsJson, 403, [], 'Aanmelden kan alleen via onze eigen website.');
}

// Verborgen veld ingevuld: een robot. Doe alsof het gelukt is, maar bewaar niets.
if (invoer('website') !== '') {
    op_de_hoogte_antwoord($alsJson, 200);
}

if (recente_pogingen('interesse:' . ip_adres()) >= MAX_INTERESSE_PER_KWARTIER) {
    op_de_hoogte_antwoord($alsJson, 429, [], 'Er zijn net al een paar aanmeldingen vanaf dit adres gedaan. Probeer het over een kwartier nog eens.');
}

$naam = mb_substr(invoer('naam'), 0, 120);
$email = mb_substr(invoer('email'), 0, 200);
$telefoon = mb_substr(invoer('telefoon'), 0, 30);
$fouten = [];
if ($naam === '') {
    $fouten['naam'] = 'Vul je naam in.';
}
if (!geldig_email($email)) {
    $fouten['email'] = $email === '' ? 'Vul je e-mailadres in, dan sturen we je een bevestiging.' : 'Dit lijkt geen geldig e-mailadres. Controleer het even, bijvoorbeeld naam@voorbeeld.nl.';
}
if ($telefoon !== '' && !geldig_telefoon($telefoon)) {
    $fouten['telefoon'] = 'Vul een geldig telefoonnummer in, bijvoorbeeld 06 12 34 56 78, of laat het leeg.';
}
if (invoer('toestemming') === '') {
    $fouten['toestemming'] = 'Vink aan dat we je op de hoogte mogen houden.';
}
if ($fouten) {
    op_de_hoogte_antwoord($alsJson, 422, $fouten, count($fouten) === 1 ? 'Er is nog 1 veld niet goed ingevuld.' : 'Er zijn nog ' . count($fouten) . ' velden niet goed ingevuld.');
}

try {
    interesse_aanmelden($naam, $email, $telefoon);
} catch (Throwable $fout) {
    error_log('Blijf op de hoogte: ' . $fout->getMessage());
    op_de_hoogte_antwoord($alsJson, 500, [], 'Aanmelden is niet gelukt. Probeer het nog eens, of mail ons.');
}
registreer_poging('interesse:' . ip_adres());
op_de_hoogte_antwoord($alsJson, 200);
