<?php
/*
 * Gratis proefdag boeken via de website (proefdag.html). Publiek, zonder inloggen.
 *
 * GET ?momenten=1 geeft de momenten die ouders kunnen kiezen (JSON). Een POST boekt een moment:
 * JSON terug ({"ok": true, "moment": "..."} of {"ok": false, "fouten": {veld: melding}}), of
 * zonder JavaScript een eenvoudige pagina. Twee ouders kunnen nooit samen de laatste plek boeken:
 * de controle en de boeking gebeuren in één transactie.
 *
 * Tegen spam: een verborgen veld, alleen vanaf de eigen website, en hooguit
 * MAX_PROEFDAGEN_PER_KWARTIER boekingen per kwartier vanaf hetzelfde adres.
 */
require __DIR__ . '/inc/bootstrap.php';

$alsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

/** Antwoord geven en stoppen: JSON voor de website met JavaScript, anders een pagina. */
function proefdag_antwoord(bool $alsJson, int $code, array $extra = [], array $fouten = [], string $melding = ''): never
{
    http_response_code($code);
    if ($alsJson) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => $code < 300, 'fouten' => (object) $fouten, 'melding' => $melding] + $extra, JSON_UNESCAPED_UNICODE);
        exit;
    }
    pagina_begin($code < 300 ? 'Proefdag geboekt' : 'Proefdag niet geboekt', '', ['publiek' => true]);
    echo '<div class="auth"><div class="panel">';
    if ($code < 300) {
        echo '<h1>Joepie, je proefdag staat!</h1>';
        echo '<div class="melding melding--succes" role="status">' . icoon('check') . '<p>We zien je graag op <strong>' . e($extra['moment'] ?? '') . '</strong>. Je krijgt een bevestiging per e-mail.</p></div>';
    } else {
        echo '<h1>Je proefdag is nog niet geboekt</h1>';
        echo '<div class="melding melding--fout" role="alert">' . icoon('alert') . '<p>' . e($melding ?: 'Er ging iets niet goed:') . '</p></div>';
        if ($fouten) {
            echo '<ul>';
            foreach ($fouten as $tekst) {
                echo '<li>' . e($tekst) . '</li>';
            }
            echo '</ul>';
        }
    }
    echo '<a class="btn btn--secondary" href="../proefdag.html#boeken">' . icoon('back') . 'Terug naar de proefdag</a>';
    echo '</div></div>';
    pagina_einde();
    exit;
}

// De momenten die ouders kunnen kiezen
if (!is_post()) {
    if (isset($_GET['momenten'])) {
        $momenten = array_map(fn ($m) => [
            'id' => (int) $m['id'],
            'datum' => $m['datum'],
            'dag' => datum_nl($m['datum'], 'EEEE d MMMM'),
            'begin' => $m['begin'],
            'eind' => $m['eind'],
            'vrij' => (int) $m['vrij'],
        ], proefdag_momenten_open());
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => true, 'momenten' => $momenten], JSON_UNESCAPED_UNICODE);
        exit;
    }
    redirect('../proefdag.html#boeken');
}

// Alleen vanaf onze eigen website (de browser stuurt mee waar het formulier vandaan komt)
$herkomst = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$eigen = [parse_url(basis_url(), PHP_URL_HOST), parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)];
if ($herkomst !== '' && !in_array(parse_url($herkomst, PHP_URL_HOST), $eigen, true)) {
    proefdag_antwoord($alsJson, 403, [], [], 'Je proefdag kan alleen via onze eigen website worden geboekt.');
}

// Verborgen veld ingevuld: een robot. Doe alsof het gelukt is, maar bewaar niets.
if (invoer('website') !== '') {
    proefdag_antwoord($alsJson, 200, ['moment' => '']);
}

// Hooguit 5 boekingen en 30 pogingen per kwartier vanaf hetzelfde adres
if (recente_pogingen('proefdag:' . ip_adres()) >= MAX_PROEFDAGEN_PER_KWARTIER || te_veel_verzoeken('proefdag-poging:' . ip_adres(), 30)) {
    proefdag_antwoord($alsJson, 429, [], [], 'Er zijn net al een paar proefdagen vanaf dit adres geboekt. Probeer het over een kwartier nog eens, of bel ons even.');
}

$waarden = [];
foreach (['naam', 'email', 'telefoon', 'kind_naam', 'kind_leeftijd', 'opmerking'] as $veld) {
    $waarden[$veld] = invoer($veld);
}
$momentId = (int) invoer('moment_id');

$fouten = [];
if ($momentId <= 0) {
    $fouten['moment_id'] = 'Kies een moment voor je proefdag.';
}
if (!geldige_naam($waarden['naam'])) {
    $fouten['naam'] = $waarden['naam'] === '' ? 'Vul je naam in.' : 'Vul je naam in (zonder links of e-mailadressen).';
}
if (!geldig_email($waarden['email']) || mb_strlen($waarden['email']) > 200) {
    $fouten['email'] = $waarden['email'] === '' ? 'Vul je e-mailadres in, dan sturen we je een bevestiging.' : 'Dit lijkt geen geldig e-mailadres. Controleer het even, bijvoorbeeld naam@voorbeeld.nl.';
}
if (!geldig_telefoon($waarden['telefoon'])) {
    $fouten['telefoon'] = $waarden['telefoon'] === '' ? 'Vul je telefoonnummer in, voor als er iets verandert.' : 'Vul een geldig telefoonnummer in, bijvoorbeeld 06 12 34 56 78.';
}
if (!geldige_naam($waarden['kind_naam'])) {
    $fouten['kind_naam'] = $waarden['kind_naam'] === '' ? 'Vul de naam van je kind in.' : 'Vul de naam van je kind in (zonder links of e-mailadressen).';
}
if (!in_array($waarden['kind_leeftijd'], PROEFDAG_LEEFTIJDEN, true)) {
    $fouten['kind_leeftijd'] = 'Kies de leeftijd van je kind.';
}
$waarden['opmerking'] = mb_substr($waarden['opmerking'], 0, 1000);
if (invoer('toestemming') === '') {
    $fouten['toestemming'] = 'Vink aan dat we je gegevens voor de proefdag mogen gebruiken.';
}
// Hooguit 3 proefdagen tegelijk op één e-mailadres (bijvoorbeeld voor broertjes en zusjes)
if (!isset($fouten['email']) && (int) waarde("SELECT COUNT(*) FROM proefdag_boekingen b JOIN proefdag_momenten m ON m.id = b.moment_id WHERE lower(b.email) = lower(?) AND b.status = 'geboekt' AND m.datum >= ?", [$waarden['email'], vandaag()]) >= 3) {
    $fouten['email'] = 'Op dit e-mailadres staan al een paar proefdagen. Wil je iets veranderen? Bel of mail ons even.';
}
if ($fouten) {
    proefdag_antwoord($alsJson, 422, [], $fouten, count($fouten) === 1 ? 'Er is nog 1 veld niet goed ingevuld.' : 'Er zijn nog ' . count($fouten) . ' velden niet goed ingevuld.');
}

if (te_veel_mails_naar($waarden['email'])) {
    proefdag_antwoord($alsJson, 429, [], [], 'We hebben net al een paar mails naar dit e-mailadres gestuurd. Probeer het over een kwartier nog eens, of bel ons even.');
}

// Boeken: controle op een vrije plek en de boeking zelf in één transactie
try {
    $moment = transactie(function () use ($momentId, $waarden) {
        $moment = rij('SELECT * FROM proefdag_momenten WHERE id = ?', [$momentId]);
        if (!$moment || $moment['datum'] <= vandaag()) {
            throw new DomainException('Dit moment kun je niet (meer) kiezen. Kies een ander moment.');
        }
        if (proefdag_vrij($momentId) <= 0) {
            throw new DomainException('Dit moment is net vol geraakt. Kies een ander moment.');
        }
        q('INSERT INTO proefdag_boekingen (moment_id, naam, email, telefoon, kind_naam, kind_leeftijd, opmerking, aangemaakt_op, verwijderen_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $momentId, $waarden['naam'], $waarden['email'], $waarden['telefoon'], $waarden['kind_naam'], $waarden['kind_leeftijd'],
            $waarden['opmerking'] !== '' ? versleutel($waarden['opmerking']) : '', nu(), proefdag_verwijderdatum($moment['datum']),
        ]);
        return $moment;
    });
} catch (DomainException $fout) {
    proefdag_antwoord($alsJson, 409, [], ['moment_id' => $fout->getMessage()], $fout->getMessage());
} catch (Throwable $fout) {
    error_log('Proefdag boeken: ' . $fout->getMessage());
    proefdag_antwoord($alsJson, 500, [], [], 'Het boeken is niet gelukt. Probeer het nog eens, of bel of mail ons.');
}
$id = laatste_id();
registreer_poging('proefdag:' . ip_adres());
log_actie('Proefdag geboekt via de website', 'proefdag ' . $id . ' op ' . $moment['datum'], null, 'wijziging', 'proefdag:' . $id);

$wanneer = proefdag_omschrijving($moment);
$adres = 'Sporty, Voorlandpad 17, 1098 TZ Amsterdam (Sportpark Middenmeer)';
// Melding voor het team (zonder persoonsgegevens) en een bevestiging voor de ouder
mail_team_tekst('team_proefdag', ['wanneer' => $wanneer, 'link' => app_url('proefdagen.php')]);
// TODO: tekst van de bevestigingsmail laten controleren door Sporty (aan te passen onder Instellingen → E-mails)
stuur_mailtekst($waarden['email'], 'proefdag_bevestiging', [
    'voornaam' => explode(' ', $waarden['naam'])[0], 'kind' => explode(' ', $waarden['kind_naam'])[0], 'wanneer' => $wanneer, 'adres' => $adres,
]);

proefdag_antwoord($alsJson, 200, ['moment' => $wanneer]);
