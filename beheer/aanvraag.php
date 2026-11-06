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
    pagina_begin($code < 300 ? 'Aanmelding verstuurd' : 'Aanmelding niet verstuurd', '', ['publiek' => true]);
    echo '<div class="auth"><div class="panel">';
    if ($code < 300) {
        echo '<h1>Gelukt! Je aanmelding is binnen.</h1>';
        echo '<div class="melding melding--succes" role="status">' . icoon('check') . '<p>Bedankt voor je aanmelding. We bekijken de mogelijkheden en nemen contact met je op. Je ontvangt ook een bevestiging per e-mail.</p></div>';
    } else {
        echo '<h1>Je aanmelding is nog niet verstuurd</h1>';
        echo '<div class="melding melding--fout" role="alert">' . icoon('alert') . '<p>' . e($melding ?: 'Er ging iets niet goed:') . '</p></div>';
        if ($fouten) {
            echo '<ul>';
            foreach ($fouten as $tekst) {
                echo '<li>' . e($tekst) . '</li>';
            }
            echo '</ul>';
        }
    }
    echo '<a class="btn btn--secondary" href="../aanmelden.html">' . icoon('back') . 'Terug naar het aanmeldformulier</a>';
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
    redirect('../aanmelden.html');
}

// Alleen vanaf onze eigen website
$herkomst = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$eigen = [parse_url(basis_url(), PHP_URL_HOST), parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)];
if ($herkomst !== '' && !in_array(parse_url($herkomst, PHP_URL_HOST), $eigen, true)) {
    aanvraag_antwoord($alsJson, 403, [], 'Je aanmelding kan alleen via onze eigen website worden verstuurd.');
}

// Anonieme telling per stap
if (isset($_GET['stap'])) {
    tel_aanvraagstap((int) $_GET['stap']);
    http_response_code(204);
    exit;
}

// Verborgen veld ingevuld: een robot. Doe alsof het gelukt is, maar bewaar niets.
if (invoer('website') !== '') {
    aanvraag_antwoord($alsJson, 200);
}
if (te_veel_verzoeken('a:' . ip_adres(), 10)) {
    aanvraag_antwoord($alsJson, 429, [], 'Er zijn net erg veel aanmeldingen verstuurd vanaf dit netwerk. Probeer het over een kwartier opnieuw, of bel ons even.');
}

[$aanvraag, $fouten] = lees_aanvraag($_POST);
if ($fouten) {
    aanvraag_antwoord($alsJson, 422, $fouten, count($fouten) === 1 ? 'Er is nog 1 veld niet goed ingevuld.' : 'Er zijn nog ' . count($fouten) . ' velden niet goed ingevuld.');
}

try {
    $ouderId = bewaar_aanvraag($aanvraag);
} catch (Throwable $fout) {
    error_log('Aanvraag opslaan: ' . $fout->getMessage());
    aanvraag_antwoord($alsJson, 500, [], 'Het versturen is niet gelukt. Probeer het nog eens, of bel of mail ons.');
}
tel_aanvraagstap(8);
$aantal = count($aanvraag['kinderen']);
log_actie('Aangemeld via de website', $aantal . ' kind(eren)', $ouderId, 'wijziging', 'gebruiker:' . $ouderId);

// Melding voor het team (zonder persoonsgegevens) en een bevestiging voor de ouder
mail_team_tekst('team_aanmelding', ['aantal' => $aantal . ' ' . ($aantal === 1 ? 'kind' : 'kinderen'), 'link' => app_url('ouder.php?id=' . $ouderId)]);
$namen = array_map(fn ($k) => $k['voornaam'], $aanvraag['kinderen']);
// TODO: tekst van de bevestigingsmail volgt van Sporty (nu een voorlopige tekst). Sporty past hem zelf aan onder Instellingen → E-mails.
stuur_mailtekst($aanvraag['ouder']['email'], 'aanmelding_ontvangen', [
    'voornaam' => $aanvraag['ouder']['voornaam'],
    'kinderen' => count($namen) > 1 ? implode(', ', array_slice($namen, 0, -1)) . ' en ' . end($namen) : $namen[0],
]);

aanvraag_antwoord($alsJson, 200);
