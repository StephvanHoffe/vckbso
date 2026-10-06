<?php
/* Kleine hulpfuncties die overal gebruikt worden. */

declare(strict_types=1);

/** Tekst veilig in HTML zetten. */
function e(null|string|int|float $tekst): string
{
    return htmlspecialchars((string) $tekst, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Configuratiewaarde ophalen, bijvoorbeeld cfg('mollie.api_key'). */
function cfg(string $sleutel, mixed $standaard = null): mixed
{
    $waarde = $GLOBALS['config'];
    foreach (explode('.', $sleutel) as $deel) {
        if (!is_array($waarde) || !array_key_exists($deel, $waarde)) {
            return $standaard;
        }
        $waarde = $waarde[$deel];
    }
    return $waarde;
}

function nu(): string
{
    return date('Y-m-d H:i:s');
}

function vandaag(): string
{
    return date('Y-m-d');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Tekst uit het formulier, zonder spaties aan de randen. */
function invoer(string $sleutel, string $standaard = ''): string
{
    $waarde = $_POST[$sleutel] ?? $standaard;
    return is_string($waarde) ? trim($waarde) : $standaard;
}

/** Getal uit de URL (bijvoorbeeld ?id=12). */
function get_int(string $sleutel): int
{
    $waarde = $_GET[$sleutel] ?? '';
    return is_string($waarde) && ctype_digit($waarde) ? (int) $waarde : 0;
}

function get_str(string $sleutel, string $standaard = ''): string
{
    $waarde = $_GET[$sleutel] ?? $standaard;
    return is_string($waarde) ? trim($waarde) : $standaard;
}

function redirect(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

/** Een melding tonen op de volgende pagina. $soort: succes, fout of info. */
function flash(string $soort, string $tekst): void
{
    $_SESSION['flash'][] = ['soort' => $soort, 'tekst' => $tekst];
}

function haal_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* ---------- Beveiliging tegen vervalste formulieren (CSRF) ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_veld(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_controleer(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        pagina_begin('Verlopen formulier', '', ['publiek' => !huidige_gebruiker()]);
        echo '<div class="panel"><h1>Probeer het nog een keer</h1><p>Dit formulier is verlopen, bijvoorbeeld omdat de pagina lang openstond. Ga terug, vernieuw de pagina en verstuur het opnieuw.</p><p><a class="btn" href="index.php">Naar het overzicht</a></p></div>';
        pagina_einde();
        exit;
    }
}

/* ---------- Datums, tijden en bedragen in Nederlandse notatie ---------- */

function datum_nl(?string $iso, string $patroon = 'd MMMM y'): string
{
    if (!$iso) {
        return '';
    }
    $moment = new DateTimeImmutable($iso);
    $opmaak = new IntlDateFormatter('nl_NL', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'Europe/Amsterdam', IntlDateFormatter::GREGORIAN, $patroon);
    return (string) $opmaak->format($moment);
}

/** Bijvoorbeeld "ma 5 okt". */
function datum_kort(string $iso): string
{
    return datum_nl($iso, 'EEEEEE d MMM');
}

function tijd_nl(?string $iso): string
{
    return $iso ? date('H:i', strtotime($iso)) : '';
}

function moment_nl(?string $iso): string
{
    if (!$iso) {
        return '';
    }
    $dag = substr($iso, 0, 10);
    if ($dag === vandaag()) {
        return 'vandaag ' . tijd_nl($iso);
    }
    if ($dag === date('Y-m-d', strtotime('-1 day'))) {
        return 'gisteren ' . tijd_nl($iso);
    }
    return datum_nl($iso, 'd MMM y') . ', ' . tijd_nl($iso);
}

function geldige_datum(string $tekst): bool
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $tekst);
    return $d !== false && $d->format('Y-m-d') === $tekst;
}

function geldige_tijd(string $tekst): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $tekst);
}

const WEEKDAGEN = [1 => 'maandag', 2 => 'dinsdag', 3 => 'woensdag', 4 => 'donderdag', 5 => 'vrijdag', 6 => 'zaterdag', 7 => 'zondag'];

function weekdag(string $iso): int
{
    return (int) date('N', strtotime($iso));
}

/**
 * Weekdagen (1 = maandag) die vol zitten voor nieuwe aanmeldingen. Het team stelt ze in bij Groepen;
 * in het aanmeldformulier zijn ze dan niet aan te vinken. Op de agenda van bestaande klanten heeft dit geen invloed.
 */
function volle_dagen(): array
{
    return array_values(array_intersect([1, 2, 3, 4, 5], array_map('intval', explode(',', instelling('volle_dagen')))));
}

/** Weekdagen als leesbare opsomming, bijvoorbeeld "dinsdag en donderdag". */
function dagen_opsomming(array $dagen): string
{
    $namen = array_map(fn (int $d) => WEEKDAGEN[$d], $dagen);
    $laatste = array_pop($namen);
    return $namen ? implode(', ', $namen) . ' en ' . $laatste : (string) $laatste;
}

/** € 1.234,56 */
function geld(int $cent): string
{
    $teken = $cent < 0 ? '-' : '';
    return '€ ' . $teken . number_format(abs($cent) / 100, 2, ',', '.');
}

/** "12,50" of "12.50" → 1250 cent. Null als het geen bedrag is. */
function euro_naar_cent(string $tekst): ?int
{
    $tekst = str_replace(['€', ' '], '', $tekst);
    if (!preg_match('/^\d{1,6}([.,]\d{1,2})?$/', $tekst)) {
        return null;
    }
    return (int) round((float) str_replace(',', '.', $tekst) * 100);
}

function cent_naar_euro(int $cent): string
{
    return number_format($cent / 100, 2, ',', '');
}

function uren_nl(float $uren): string
{
    return rtrim(rtrim(number_format($uren, 2, ',', ''), '0'), ',');
}

/* ---------- Invoer controleren ---------- */

function geldig_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL) && mb_strlen($email) <= 190;
}

function geldig_telefoon(string $telefoon): bool
{
    return (bool) preg_match('/^[0-9 +()\-]{10,20}$/', $telefoon);
}

function geldige_postcode(string $postcode): bool
{
    return (bool) preg_match('/^[1-9][0-9]{3} ?[A-Za-z]{2}$/', $postcode);
}

function normaliseer_postcode(string $postcode): string
{
    $postcode = strtoupper(str_replace(' ', '', $postcode));
    return substr($postcode, 0, 4) . ' ' . substr($postcode, 4);
}

/** NL91ABNA0417164300 → NL91 **** **** **** 4300 */
function masker_iban(string $iban): string
{
    $iban = strtoupper(preg_replace('/\s+/', '', $iban));
    if (strlen($iban) < 8) {
        return $iban;
    }
    return substr($iban, 0, 4) . ' **** **** ' . substr($iban, -4);
}

/* ---------- Instellingen (in de database) ---------- */

function instelling(string $sleutel, string $standaard = ''): string
{
    static $cache = null;
    if ($cache === null || $sleutel === '__reset') {
        $cache = [];
        foreach (rijen('SELECT sleutel, waarde FROM instellingen') as $rij) {
            $cache[$rij['sleutel']] = $rij['waarde'];
        }
    }
    return $cache[$sleutel] ?? $standaard;
}

function instelling_zet(string $sleutel, string $waarde): void
{
    q('INSERT INTO instellingen (sleutel, waarde) VALUES (?, ?) ON CONFLICT(sleutel) DO UPDATE SET waarde = excluded.waarde', [$sleutel, $waarde]);
    instelling('__reset');
}

/* ---------- Logboek ---------- */

const LOG_SOORTEN = [
    'inzage' => 'Inzage',
    'wijziging' => 'Wijziging',
    'export' => 'Export',
    'inloggen' => 'Inloggen',
    'beveiliging' => 'Beveiliging',
    'avg' => 'AVG-verzoek',
    'bewaarbeleid' => 'Bewaarbeleid',
];

/**
 * Registreert een actie. $soort: inzage, wijziging, export, inloggen, beveiliging,
 * avg of bewaarbeleid. $onderwerp: over wie het gaat, bijvoorbeeld "kind:12".
 * Elke regel bevat de hash van de vorige regel (hashketen): een regel achteraf
 * wijzigen of weghalen valt op bij "Controleer logboek".
 */
function log_actie(string $actie, string $details = '', ?int $gebruikerId = null, string $soort = 'wijziging', string $onderwerp = ''): void
{
    $gebruikerId ??= huidige_gebruiker()['id'] ?? null;
    $rij = [
        'gebruiker_id' => $gebruikerId,
        'actie' => $actie,
        'details' => mb_substr($details, 0, 500),
        'ip' => ip_adres(),
        'aangemaakt_op' => nu(),
        'soort' => array_key_exists($soort, LOG_SOORTEN) ? $soort : 'wijziging',
        'onderwerp' => $onderwerp,
    ];
    transactie(function () use ($rij) {
        $vorige = (string) (waarde('SELECT hash FROM logboek ORDER BY id DESC LIMIT 1') ?? '');
        $rij['vorige'] = $vorige;
        $rij['hash'] = log_hash($vorige, $rij);
        q('INSERT INTO logboek (gebruiker_id, actie, details, ip, aangemaakt_op, soort, onderwerp, vorige, hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $rij['gebruiker_id'], $rij['actie'], $rij['details'], $rij['ip'], $rij['aangemaakt_op'], $rij['soort'], $rij['onderwerp'], $rij['vorige'], $rij['hash'],
        ]);
    });
}

function log_hash(string $vorige, array $rij): string
{
    return hash('sha256', $vorige . "\x1f" . implode("\x1f", [
        (string) $rij['gebruiker_id'], $rij['actie'], $rij['details'], $rij['ip'], $rij['aangemaakt_op'], $rij['soort'], $rij['onderwerp'],
    ]));
}

/**
 * Controleert de hashketen van het logboek. Regels die volgens de bewaartermijn
 * zijn verwijderd, zijn geen probleem: de controle begint bij de oudste regel die er nog is.
 */
function controleer_logboek(): array
{
    $vorigeHash = null;
    $aantal = 0;
    $stmt = q('SELECT * FROM logboek ORDER BY id');
    while ($rij = $stmt->fetch()) {
        $aantal++;
        if ($vorigeHash !== null && $rij['vorige'] !== $vorigeHash) {
            return ['ok' => false, 'aantal' => $aantal, 'fout_id' => (int) $rij['id']];
        }
        if (!hash_equals($rij['hash'], log_hash($rij['vorige'], $rij))) {
            return ['ok' => false, 'aantal' => $aantal, 'fout_id' => (int) $rij['id']];
        }
        $vorigeHash = $rij['hash'];
    }
    return ['ok' => true, 'aantal' => $aantal, 'fout_id' => null];
}

/** Iemand van het team bekijkt gegevens van een kind of ouder. */
function log_inzage(string $wat, string $onderwerp): void
{
    log_actie($wat, '', null, 'inzage', $onderwerp);
}

function log_export(string $wat, string $onderwerp, string $details = ''): void
{
    log_actie($wat, $details, null, 'export', $onderwerp);
}

function ip_adres(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
}

/* ---------- URL's ---------- */

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** Absolute URL van de beheeromgeving, zonder / aan het eind. */
function basis_url(): string
{
    $url = rtrim((string) cfg('app_url', ''), '/');
    if ($url !== '') {
        return $url;
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $pad = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/beheer/index.php')), '/');
    return (is_https() ? 'https' : 'http') . '://' . $host . $pad;
}

function app_url(string $pad): string
{
    return basis_url() . '/' . ltrim($pad, '/');
}

/** Pad van de beheeromgeving op de server, bijvoorbeeld "/beheer/". Voor het sessiecookie. */
function basis_pad(): string
{
    $pad = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/beheer/index.php'));
    return rtrim($pad, '/') . '/';
}

/* ---------- Foutpagina's ---------- */

function niet_gevonden(string $tekst = 'Deze pagina of dit item bestaat niet (meer).'): never
{
    http_response_code(404);
    pagina_begin('Niet gevonden', '', ['publiek' => !huidige_gebruiker()]);
    echo '<div class="panel"><h1>Niet gevonden</h1><p>' . e($tekst) . '</p><p><a class="btn" href="index.php">Naar het overzicht</a></p></div>';
    pagina_einde();
    exit;
}

function geen_toegang(string $uitleg = 'Je hebt geen toegang tot deze pagina.'): never
{
    http_response_code(403);
    pagina_begin('Geen toegang');
    echo '<div class="panel"><h1>Geen toegang</h1><p>' . e($uitleg) . '</p><p><a class="btn" href="index.php">Naar het overzicht</a></p></div>';
    pagina_einde();
    exit;
}

/* ---------- Statussen als leesbare labels ---------- */

const STATUS_LABELS = [
    // inschrijvingen
    'bevestigd' => 'Komt',
    'wachtlijst' => 'Wachtlijst',
    'afgemeld' => 'Afgemeld',
    'ziek' => 'Ziek',
    // ouders
    'nieuw' => 'Nieuwe aanmelding',
    'actief' => 'Actief',
    'gestopt' => 'Gestopt',
    // facturen
    'open' => 'Open',
    'incasso' => 'Wordt afgeschreven',
    'betaald' => 'Betaald',
    'mislukt' => 'Afschrijving mislukt',
    'geannuleerd' => 'Geannuleerd',
    // machtiging
    'geen' => 'Nog geen machtiging',
    'in_behandeling' => 'Machtiging in behandeling',
    'geldig' => 'Machtiging geldig',
    'demo' => 'Machtiging (demo)',
    'ingetrokken' => 'Machtiging ingetrokken',
];

function status_label(string $status): string
{
    return STATUS_LABELS[$status] ?? ucfirst($status);
}

function status_badge(string $status): string
{
    return '<span class="badge badge--' . e($status) . '">' . e(status_label($status)) . '</span>';
}

/** Naam van een kind. */
function kindnaam(array $kind): string
{
    return trim($kind['voornaam'] . ' ' . ($kind['achternaam'] ?? ''));
}

/** Leeftijd in jaren op een datum. */
function leeftijd(?string $geboortedatum, ?string $op = null): ?int
{
    if (!$geboortedatum || !geldige_datum($geboortedatum)) {
        return null;
    }
    return (new DateTimeImmutable($geboortedatum))->diff(new DateTimeImmutable($op ?? vandaag()))->y;
}
