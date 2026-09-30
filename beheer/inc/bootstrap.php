<?php
/*
 * Start van elke pagina in de beheeromgeving: configuratie, organisatie,
 * database, sessie en beveiligingsheaders. Pagina's beginnen met:
 *     require __DIR__ . '/inc/bootstrap.php';
 */

declare(strict_types=1);

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    exit('De beheeromgeving heeft PHP 8.2 of hoger nodig (oudere versies krijgen geen beveiligingsupdates meer).');
}
$ontbrekend = array_filter(['pdo_sqlite', 'sodium', 'gd', 'intl', 'mbstring'], fn ($extensie) => !extension_loaded($extensie));
if ($ontbrekend) {
    http_response_code(500);
    exit('Deze PHP-extensies ontbreken: ' . implode(', ', $ontbrekend) . '. Zet ze aan in het beheerpaneel van je hosting.');
}
unset($ontbrekend);

const BEHEER_DIR = __DIR__ . '/..';

date_default_timezone_set('Europe/Amsterdam');
mb_internal_encoding('UTF-8');

$standaardConfig = [
    'app_url' => '',
    'data_dir' => BEHEER_DIR . '/data',
    'sleutel' => '',
    'ontwikkelmodus' => false,
    'installatiecode' => '',
    'mollie' => ['api_key' => '', 'methode_machtiging' => 'ideal'],
    'mail' => ['methode' => 'log', 'van' => 'info@vckbso.nl', 'van_naam' => 'Sporty'],
    'team_email' => 'info@vckbso.nl',
    'backup' => ['map' => '', 'sleutel' => '', 'bewaardagen' => 30],
    'organisaties' => [],
];
$configBestand = getenv('VCK_BEHEER_CONFIG') ?: BEHEER_DIR . '/config.php';
$GLOBALS['config'] = array_replace_recursive(
    $standaardConfig,
    is_file($configBestand) ? (require $configBestand) : []
);

require __DIR__ . '/versleuteling.php';
kies_organisatie();

require __DIR__ . '/helpers.php';
require __DIR__ . '/database.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/rechten.php';
require __DIR__ . '/toegang.php';
require __DIR__ . '/mfa.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/mollie.php';
require __DIR__ . '/agenda.php';
require __DIR__ . '/berichten.php';
require __DIR__ . '/facturen.php';
require __DIR__ . '/financien.php';
require __DIR__ . '/fotos.php';
require __DIR__ . '/export.php';
require __DIR__ . '/bewaarbeleid.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/weergave.php';

if (PHP_SAPI !== 'cli') {
    // Alleen via https (behalve lokaal testen)
    if (!is_https() && !is_lokaal()) {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    // Persoonsgegevens van kinderen: niet cachen, niet in frames, strikte bronnen
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://www.mollie.com https://*.mollie.com");
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store, private');

    if (!defined('GEEN_SESSIE')) {
        start_sessie();
        // Terugval als er geen cron is: één keer per dag het bewaarbeleid uitvoeren
        bewaarbeleid_indien_nodig();
    }
}

/**
 * Meerdere opvangorganisaties: elke organisatie heeft een eigen database,
 * fotomap, encryptiesleutel en betaalaccount. De organisatie volgt uit het
 * domein (of op de opdrachtregel uit --organisatie=... of VCK_ORGANISATIE).
 * Onbekend domein = geen toegang. Zo kunnen gegevens nooit door elkaar lopen.
 */
function kies_organisatie(): void
{
    $organisaties = $GLOBALS['config']['organisaties'] ?? [];
    unset($GLOBALS['config']['organisaties']);
    if (!$organisaties) {
        $GLOBALS['config']['organisatie'] = 'standaard';
        return;
    }

    // Elke organisatie een eigen datamap en sleutel, anders weigeren
    $mappen = [];
    $sleutels = [];
    foreach ($organisaties as $naam => $org) {
        if (empty($org['data_dir']) || (empty($org['sleutel']) && empty($org['ontwikkelmodus']))) {
            stop_met_fout("Organisatie '{$naam}' heeft geen eigen data_dir of sleutel in config.php.");
        }
        $mappen[] = rtrim((string) $org['data_dir'], '/');
        $sleutels[] = !empty($org['sleutel']) ? (string) $org['sleutel'] : 'ontwikkel:' . $naam;
    }
    if (count(array_unique($mappen)) !== count($mappen) || count(array_unique($sleutels)) !== count($sleutels)) {
        stop_met_fout('Twee organisaties delen een data_dir of encryptiesleutel. Elke organisatie moet een eigen map en sleutel hebben.');
    }

    $gekozen = null;
    if (PHP_SAPI === 'cli') {
        foreach ($GLOBALS['argv'] ?? [] as $argument) {
            if (str_starts_with($argument, '--organisatie=')) {
                $gekozen = substr($argument, strlen('--organisatie='));
            }
        }
        $gekozen ??= getenv('VCK_ORGANISATIE') ?: null;
    } else {
        $host = strtolower((string) parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
        foreach ($organisaties as $naam => $org) {
            if (in_array($host, array_map('strtolower', (array) ($org['hosts'] ?? [])), true)) {
                $gekozen = $naam;
                break;
            }
        }
    }
    if ($gekozen === null || !isset($organisaties[$gekozen])) {
        if (PHP_SAPI === 'cli') {
            stop_met_fout('Kies een organisatie met --organisatie=naam. Beschikbaar: ' . implode(', ', array_keys($organisaties)));
        }
        http_response_code(404);
        exit('Onbekende organisatie.');
    }
    $GLOBALS['config'] = array_replace_recursive($GLOBALS['config'], $organisaties[$gekozen]);
    $GLOBALS['config']['organisatie'] = (string) $gekozen;
}

/** Lokaal testen (localhost): geen https nodig. */
function is_lokaal(): bool
{
    if (PHP_SAPI === 'cli') {
        return false;
    }
    $host = strtolower((string) parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
    return $host === 'localhost' || str_starts_with($host, '127.') || str_ends_with($host, '.test');
}
