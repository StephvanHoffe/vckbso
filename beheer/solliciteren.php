<?php
/*
 * Open sollicitatie via de website (het formulier op werken-bij.html). Publiek, zonder inloggen.
 *
 * Het formulier verstuurt met JavaScript en krijgt JSON terug: {"ok": true}, of {"ok": false,
 * "fouten": {veld: melding}}. Zonder JavaScript krijgt de sollicitant een eenvoudige pagina.
 * Het cv wordt versleuteld opgeslagen; het team krijgt alleen een melding per e-mail (zonder
 * persoonsgegevens) en bekijkt de sollicitatie in de beheeromgeving bij Sollicitaties.
 *
 * Tegen spam: een verborgen veld, alleen vanaf de eigen website, en hooguit
 * MAX_SOLLICITATIES_PER_KWARTIER verstuurde sollicitaties per kwartier vanaf hetzelfde adres.
 */
require __DIR__ . '/inc/bootstrap.php';

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
    pagina_begin($code < 300 ? 'Sollicitatie verstuurd' : 'Sollicitatie niet verstuurd', '', ['publiek' => true]);
    echo '<div class="auth"><div class="panel">';
    if ($code < 300) {
        echo '<h1>Joepie, je sollicitatie is binnen!</h1>';
        echo '<div class="melding melding--succes" role="status">' . icoon('check') . '<p>Bedankt voor je open sollicitatie. Je krijgt een bevestiging per e-mail, en we nemen zo snel mogelijk contact met je op.</p></div>';
    } else {
        echo '<h1>Je sollicitatie is nog niet verstuurd</h1>';
        echo '<div class="melding melding--fout" role="alert">' . icoon('alert') . '<p>' . e($melding ?: 'Er ging iets niet goed:') . '</p></div>';
        if ($fouten) {
            echo '<ul>';
            foreach ($fouten as $tekst) {
                echo '<li>' . e($tekst) . '</li>';
            }
            echo '</ul>';
        }
        echo '<p>Ga terug naar het formulier en probeer het nog eens, of mail je sollicitatie naar <a href="mailto:' . e(instelling('email', 'info@sporty.nl')) . '">' . e(instelling('email', 'info@sporty.nl')) . '</a>.</p>';
    }
    echo '<a class="btn btn--secondary" href="../werken-bij.html">' . icoon('back') . 'Terug naar Werken bij</a>';
    echo '</div></div>';
    pagina_einde();
    exit;
}

if (!is_post()) {
    redirect('../werken-bij.html#solliciteren');
}

// Alleen vanaf onze eigen website (de browser stuurt mee waar het formulier vandaan komt)
$herkomst = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$eigen = [parse_url(basis_url(), PHP_URL_HOST), parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)];
if ($herkomst !== '' && !in_array(parse_url($herkomst, PHP_URL_HOST), $eigen, true)) {
    sollicitatie_antwoord($alsJson, 403, [], 'Je sollicitatie kan alleen via onze eigen website worden verstuurd.');
}

// Groter dan de server aankan? Dan komt er niets aan
if (!$_POST && !$_FILES && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    sollicitatie_antwoord($alsJson, 413, ['cv' => 'Dit bestand is groter dan 5 MB. Maak het wat kleiner, of stuur het later per mail.']);
}

// Verborgen veld ingevuld: een robot. Doe alsof het gelukt is, maar bewaar niets.
if (invoer('website') !== '') {
    sollicitatie_antwoord($alsJson, 200);
}

if (recente_pogingen('sollicitatie:' . ip_adres()) >= MAX_SOLLICITATIES_PER_KWARTIER) {
    sollicitatie_antwoord($alsJson, 429, [], 'Er zijn net al een paar sollicitaties vanaf dit adres verstuurd. Probeer het over een kwartier nog eens.');
}

$waarden = [];
foreach (['naam', 'email', 'telefoon', 'woonplaats', 'functie', 'motivatie', 'link'] as $veld) {
    $waarden[$veld] = invoer($veld);
}
$dagen = array_values(array_intersect(SOLLICITATIE_DAGEN, (array) ($_POST['dagen'] ?? [])));

$fouten = [];
if ($waarden['naam'] === '' || mb_strlen($waarden['naam']) > 120) {
    $fouten['naam'] = 'Vul je naam in.';
}
if (!geldig_email($waarden['email']) || mb_strlen($waarden['email']) > 200) {
    $fouten['email'] = $waarden['email'] === '' ? 'Vul je e-mailadres in.' : 'Dit lijkt geen geldig e-mailadres. Controleer het even, bijvoorbeeld naam@voorbeeld.nl.';
}
if (!geldig_telefoon($waarden['telefoon'])) {
    $fouten['telefoon'] = $waarden['telefoon'] === '' ? 'Vul je telefoonnummer in, dan kunnen we je bellen.' : 'Vul een geldig telefoonnummer in, bijvoorbeeld 06 12 34 56 78.';
}
$waarden['woonplaats'] = mb_substr($waarden['woonplaats'], 0, 80);
$waarden['functie'] = in_array($waarden['functie'], SOLLICITATIE_FUNCTIES, true) ? $waarden['functie'] : '';
if (mb_strlen($waarden['motivatie']) < 20) {
    $fouten['motivatie'] = $waarden['motivatie'] === '' ? 'Vertel ons iets over jezelf en waarom je bij Sporty wilt werken.' : 'Dit is wat kort. Vertel ons gerust wat meer over jezelf!';
}
$waarden['motivatie'] = mb_substr($waarden['motivatie'], 0, 5000);
if ($waarden['link'] !== '' && (!filter_var($waarden['link'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $waarden['link']) || mb_strlen($waarden['link']) > 300)) {
    $fouten['link'] = 'Dit lijkt geen geldige link. Begin met https://';
}
if (invoer('toestemming') === '') {
    $fouten['toestemming'] = 'Vink aan dat we je gegevens voor je sollicitatie mogen gebruiken.';
}
$bewaren = invoer('bewaren') !== '';

$metCv = is_array($_FILES['cv'] ?? null) && ($_FILES['cv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
if ($metCv && ($cvFout = cv_fout($_FILES['cv'])) !== null) {
    $fouten['cv'] = $cvFout;
}
if ($fouten) {
    sollicitatie_antwoord($alsJson, 422, $fouten, count($fouten) === 1 ? 'Er is nog 1 veld niet goed ingevuld.' : 'Er zijn nog ' . count($fouten) . ' velden niet goed ingevuld.');
}

$cv = $metCv ? bewaar_cv($_FILES['cv']) : [null, null, null, null];
$nu = nu();
try {
    q('INSERT INTO sollicitaties (naam, email, telefoon, woonplaats, functie, dagen, motivatie, link, cv_bestand, cv_naam, cv_type, cv_grootte, bewaren, aangemaakt_op, verwijderen_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $waarden['naam'], $waarden['email'], $waarden['telefoon'], $waarden['woonplaats'], $waarden['functie'], implode(',', $dagen),
        versleutel($waarden['motivatie']), $waarden['link'] !== '' ? versleutel($waarden['link']) : '',
        $cv[0], $cv[1], $cv[2], $cv[3], $bewaren ? 1 : 0, $nu, sollicitatie_verwijderdatum('nieuw', $bewaren, $nu),
    ]);
} catch (Throwable $fout) {
    verwijder_cv($cv[0]);
    error_log('Sollicitatie opslaan: ' . $fout->getMessage());
    sollicitatie_antwoord($alsJson, 500, [], 'Het opslaan is niet gelukt. Probeer het nog eens, of mail je sollicitatie naar ons.');
}
$id = laatste_id();
registreer_poging('sollicitatie:' . ip_adres());
log_actie('Open sollicitatie ontvangen via de website', 'sollicitatie ' . $id . ($cv[0] ? ', met cv' : ''), null, 'wijziging', 'sollicitatie:' . $id);

// Melding voor het team (zonder persoonsgegevens) en een bevestiging voor de sollicitant
mail_team('Nieuwe open sollicitatie via de website', "Er is een nieuwe open sollicitatie binnengekomen" . ($waarden['functie'] !== '' ? " ({$waarden['functie']})" : '') . ".\n\nBekijk hem in de beheeromgeving bij Sollicitaties:\n" . app_url('sollicitaties.php?id=' . $id));
$voornaam = explode(' ', $waarden['naam'])[0];
stuur_mail($waarden['email'], 'We hebben je sollicitatie ontvangen', "Hoi {$voornaam},\n\nWat leuk dat je bij Sporty wilt komen werken! We hebben je open sollicitatie goed ontvangen" . ($cv[0] ? ', met je cv' : '') . ". We lezen alles en nemen zo snel mogelijk contact met je op.\n\n"
    . ($bewaren
        ? 'Je gaf ons toestemming om je sollicitatie een jaar te bewaren, voor als er later een plek vrijkomt. Wil je dat niet meer? Stuur ons dan even een mailtje.'
        : 'Is er geen plek voor je, dan verwijderen we je gegevens uiterlijk 4 weken nadat we je dat hebben laten weten.')
    . "\n\nGroetjes,\nTeam Sporty");

sollicitatie_antwoord($alsJson, 200);
