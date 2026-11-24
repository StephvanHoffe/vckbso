<?php
/*
 * Sollicitatie via de website (het formulier op word-sporty-coach.html). Publiek, zonder inloggen.
 *
 * Het formulier verstuurt met JavaScript en krijgt JSON terug: {"ok": true}, of {"ok": false,
 * "fouten": {veld: melding}}. Zonder JavaScript krijgt de sollicitant een eenvoudige pagina.
 * Het cv en de motivatie (pdf of Word) worden versleuteld opgeslagen; het team krijgt alleen een melding per e-mail (zonder
 * persoonsgegevens) en bekijkt de sollicitatie in de beheeromgeving bij Sollicitaties.
 *
 * Tegen spam: een verborgen veld, alleen vanaf de eigen website, en hooguit
 * MAX_SOLLICITATIES_PER_KWARTIER verstuurde sollicitaties per kwartier vanaf hetzelfde adres.
 */
require __DIR__ . '/inc/bootstrap.php';
// Staat de website op een ander adres (bsosporty.nl, Mijn BSO op mijn.bsosporty.nl), dan mag die het antwoord lezen
cors_voor_website();

$alsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

/** Antwoord geven en stoppen: JSON voor het formulier met JavaScript, anders een pagina. */
function sollicitatie_antwoord(bool $alsJson, int $code, array $fouten = [], string $melding = ''): never
{
    http_response_code($code);
    if ($alsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $code < 300, 'fouten' => (object) $fouten, 'melding' => $melding], JSON_UNESCAPED_UNICODE);
        exit;
    }
    pagina_begin($code < 300 ? t('Sollicitatie verstuurd', 'Application sent') : t('Sollicitatie niet verstuurd', 'Application not sent'), '', ['publiek' => true]);
    echo '<div class="auth"><div class="panel">';
    if ($code < 300) {
        echo '<h1>' . e(t('Joepie, je sollicitatie is binnen!', 'Hooray, your application is in!')) . '</h1>';
        echo '<div class="melding melding--succes" role="status">' . icoon('check') . '<p>' . e(t('Bedankt voor je sollicitatie. Je krijgt een bevestiging per e-mail, en we nemen zo snel mogelijk contact met je op.', "Thanks for applying. You'll receive a confirmation by email, and we'll get in touch as soon as we can.")) . '</p></div>';
    } else {
        echo '<h1>' . e(t('Je sollicitatie is nog niet verstuurd', "Your application hasn't been sent yet")) . '</h1>';
        echo '<div class="melding melding--fout" role="alert">' . icoon('alert') . '<p>' . e($melding ?: t('Er ging iets niet goed:', 'Something went wrong:')) . '</p></div>';
        if ($fouten) {
            echo '<ul>';
            foreach ($fouten as $tekst) {
                echo '<li>' . e($tekst) . '</li>';
            }
            echo '</ul>';
        }
        echo '<p>' . e(t('Ga terug naar het formulier en probeer het nog eens, of mail je sollicitatie naar ', 'Go back to the form and try again, or email your application to ')) . '<a href="mailto:' . e(instelling('email', 'info@bsosporty.nl')) . '">' . e(instelling('email', 'info@bsosporty.nl')) . '</a>.</p>';
    }
    echo '<a class="btn btn--secondary" href="' . e(website_pagina('word-sporty-coach.html', 'become-a-sporty-coach.html')) . '#solliciteren">' . icoon('back') . e(t('Terug naar Word Sporty coach', 'Back to Become a Sporty coach')) . '</a>';
    echo '</div></div>';
    pagina_einde();
    exit;
}

if (!is_post()) {
    redirect(website_pagina('word-sporty-coach.html', 'become-a-sporty-coach.html') . '#solliciteren');
}

// Alleen vanaf onze eigen website (de browser stuurt mee waar het formulier vandaan komt)
if (!eigen_herkomst()) {
    sollicitatie_antwoord($alsJson, 403, [], t('Je sollicitatie kan alleen via onze eigen website worden verstuurd.', 'You can only send your application via our own website.'));
}

// Groter dan de server aankan? Dan komt er niets aan
if (!$_POST && !$_FILES && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    // Zonder formuliervelden weten we de taal alleen uit de pagina waar het formulier stond
    if (str_contains((string) ($_SERVER['HTTP_REFERER'] ?? ''), '/en/')) {
        taal('en');
    }
    sollicitatie_antwoord($alsJson, 413, ['cv' => t('Je bestanden zijn samen te groot. Maak ze wat kleiner (elk hooguit 5 MB), of stuur ze per mail.', 'Your files are too large together. Make them a bit smaller (5 MB each at most), or send them by email.')]);
}

// Verborgen veld ingevuld: een robot. Doe alsof het gelukt is, maar bewaar niets.
if (invoer('website') !== '') {
    sollicitatie_antwoord($alsJson, 200);
}

if (recente_pogingen('sollicitatie:' . ip_adres()) >= MAX_SOLLICITATIES_PER_KWARTIER || te_veel_verzoeken('sollicitatie-poging:' . ip_adres(), 30)) {
    sollicitatie_antwoord($alsJson, 429, [], t('Er zijn net al een paar sollicitaties vanaf dit adres verstuurd. Probeer het over een kwartier nog eens.', 'A few applications were just sent from this address. Please try again in 15 minutes.'));
}

$waarden = [];
foreach (['naam', 'email', 'telefoon', 'woonplaats', 'functie', 'motivatie', 'link'] as $veld) {
    $waarden[$veld] = invoer($veld);
}
$dagen = array_values(array_intersect(SOLLICITATIE_DAGEN, (array) ($_POST['dagen'] ?? [])));

$fouten = [];
if (!geldige_naam($waarden['naam'])) {
    $fouten['naam'] = $waarden['naam'] === '' ? t('Vul je naam in.', 'Fill in your name.') : t('Vul je naam in (zonder links of e-mailadressen).', 'Fill in your name (without links or email addresses).');
}
if (!geldig_email($waarden['email']) || mb_strlen($waarden['email']) > 200) {
    $fouten['email'] = $waarden['email'] === '' ? t('Vul je e-mailadres in.', 'Fill in your email address.') : t('Dit lijkt geen geldig e-mailadres. Controleer het even, bijvoorbeeld naam@voorbeeld.nl.', "That doesn't look like a valid email address. Please check it, for example name@example.com.");
}
if (!geldig_telefoon($waarden['telefoon'])) {
    $fouten['telefoon'] = $waarden['telefoon'] === '' ? t('Vul je telefoonnummer in, dan kunnen we je bellen.', 'Fill in your phone number so we can call you.') : t('Vul een geldig telefoonnummer in, bijvoorbeeld 06 12 34 56 78.', 'Fill in a valid phone number, for example +31 6 12 34 56 78.');
}
$waarden['woonplaats'] = mb_substr($waarden['woonplaats'], 0, 80);
$waarden['functie'] = in_array($waarden['functie'], SOLLICITATIE_FUNCTIES, true) ? $waarden['functie'] : '';
$waarden['motivatie'] = mb_substr($waarden['motivatie'], 0, 5000);
if ($waarden['link'] !== '' && (!filter_var($waarden['link'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $waarden['link']) || mb_strlen($waarden['link']) > 300)) {
    $fouten['link'] = t('Dit lijkt geen geldige link. Begin met https://', "That doesn't look like a valid link. Start with https://");
}
if (invoer('toestemming') === '') {
    $fouten['toestemming'] = t('Vink aan dat we je gegevens voor je sollicitatie mogen gebruiken.', 'Tick the box to let us use your details for your application.');
}
$bewaren = invoer('bewaren') !== '';

// Het cv en de motivatie komen als bestand (pdf of Word). Een motivatie als tekst mag ook (eerste versie van het formulier).
$geupload = fn (string $veld): bool => is_array($_FILES[$veld] ?? null) && ($_FILES[$veld]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
if (!$geupload('cv')) {
    $fouten['cv'] = t('Voeg je cv toe (pdf of Word).', 'Add your CV (PDF or Word).');
} elseif (($cvFout = cv_fout($_FILES['cv'])) !== null) {
    $fouten['cv'] = $cvFout;
}
$metMotivatiebestand = $geupload('motivatie');
if ($metMotivatiebestand && ($motivatieFout = cv_fout($_FILES['motivatie'], 'motivatie')) !== null) {
    $fouten['motivatie'] = $motivatieFout;
} elseif (!$metMotivatiebestand && mb_strlen($waarden['motivatie']) < 20) {
    $fouten['motivatie'] = t('Voeg je motivatie toe (pdf of Word).', 'Add your cover letter (PDF or Word).');
}
if ($fouten) {
    sollicitatie_antwoord($alsJson, 422, $fouten, count($fouten) === 1 ? t('Er is nog 1 veld niet goed ingevuld.', "1 field isn't filled in correctly yet.") : t('Er zijn nog ' . count($fouten) . ' velden niet goed ingevuld.', count($fouten) . " fields aren't filled in correctly yet."));
}

$cv = $motivatie = [null, null, null, null];
$nu = nu();
try {
    $cv = bewaar_cv($_FILES['cv']);
    if ($metMotivatiebestand) {
        $motivatie = bewaar_cv($_FILES['motivatie'], 'motivatie');
    }
    q('INSERT INTO sollicitaties (naam, email, telefoon, woonplaats, functie, dagen, motivatie, link, cv_bestand, cv_naam, cv_type, cv_grootte, motivatie_bestand, motivatie_naam, motivatie_type, motivatie_grootte, bewaren, taal, aangemaakt_op, verwijderen_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $waarden['naam'], $waarden['email'], $waarden['telefoon'], $waarden['woonplaats'], $waarden['functie'], implode(',', $dagen),
        $waarden['motivatie'] !== '' ? versleutel($waarden['motivatie']) : '', $waarden['link'] !== '' ? versleutel($waarden['link']) : '',
        $cv[0], $cv[1], $cv[2], $cv[3], $motivatie[0], $motivatie[1], $motivatie[2], $motivatie[3], $bewaren ? 1 : 0, taal(), $nu, sollicitatie_verwijderdatum('nieuw', $bewaren, $nu),
    ]);
} catch (Throwable $fout) {
    verwijder_cv($cv[0]);
    verwijder_cv($motivatie[0]);
    error_log('Sollicitatie opslaan: ' . $fout->getMessage());
    sollicitatie_antwoord($alsJson, 500, [], t('Het opslaan is niet gelukt. Probeer het nog eens, of mail je sollicitatie naar ons.', "Saving didn't work. Please try again, or email your application to us."));
}
$id = laatste_id();
registreer_poging('sollicitatie:' . ip_adres());
log_actie('Sollicitatie ontvangen via de website', 'sollicitatie ' . $id . ', met cv' . ($motivatie[0] ? ' en motivatie' : ''), null, 'wijziging', 'sollicitatie:' . $id);

// Melding voor het team (zonder persoonsgegevens) en een bevestiging voor de sollicitant
mail_team_tekst('team_sollicitatie', ['functie' => $waarden['functie'] !== '' ? $waarden['functie'] : 'niet ingevuld', 'link' => app_url('sollicitaties.php?id=' . $id)]);
// De sollicitatie is bewaard; alleen de bevestiging slaan we over als er net al veel mails naar dit adres gingen
if (!te_veel_mails_naar($waarden['email'])) {
    stuur_mailtekst($waarden['email'], 'sollicitatie_ontvangen', [
        'voornaam' => explode(' ', $waarden['naam'])[0],
        'bijlagen' => $motivatie[0] ? t('je cv en motivatie', 'your CV and cover letter') : t('je cv', 'your CV'),
        'bewaren' => $bewaren
            ? t('Je gaf ons toestemming om je sollicitatie een jaar te bewaren, voor als er later een plek vrijkomt. Wil je dat niet meer? Stuur ons dan even een mailtje.', "You gave us permission to keep your application for a year, in case a place opens up later. Changed your mind? Just send us an email.")
            : t('Is er geen plek voor je, dan verwijderen we je gegevens uiterlijk 4 weken nadat we je dat hebben laten weten.', "If there's no place for you, we'll delete your details no later than 4 weeks after letting you know."),
    ]);
}

sollicitatie_antwoord($alsJson, 200);
