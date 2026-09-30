<?php
/*
 * Start van elke pagina in de beheeromgeving: configuratie, database,
 * sessie en beveiligingsheaders. Pagina's beginnen met:
 *     require __DIR__ . '/inc/bootstrap.php';
 */

declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('De beheeromgeving heeft PHP 8.1 of hoger nodig.');
}

const BEHEER_DIR = __DIR__ . '/..';

date_default_timezone_set('Europe/Amsterdam');
mb_internal_encoding('UTF-8');

$standaardConfig = [
    'app_url' => '',
    'data_dir' => BEHEER_DIR . '/data',
    'installatiecode' => '',
    'mollie' => ['api_key' => '', 'methode_machtiging' => 'ideal'],
    'mail' => ['methode' => 'log', 'van' => 'info@vckbso.nl', 'van_naam' => 'BSO VCK'],
    'team_email' => 'info@vckbso.nl',
];
$configBestand = getenv('VCK_BEHEER_CONFIG') ?: BEHEER_DIR . '/config.php';
$GLOBALS['config'] = array_replace_recursive(
    $standaardConfig,
    is_file($configBestand) ? (require $configBestand) : []
);

require __DIR__ . '/helpers.php';
require __DIR__ . '/database.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/mollie.php';
require __DIR__ . '/agenda.php';
require __DIR__ . '/facturen.php';
require __DIR__ . '/fotos.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/weergave.php';

if (PHP_SAPI !== 'cli') {
    // Alleen via https (behalve lokaal testen)
    $host = strtolower((string) parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
    $lokaal = $host === 'localhost' || str_starts_with($host, '127.') || str_ends_with($host, '.test');
    if (!is_https() && !$lokaal) {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    // Persoonsgegevens van kinderen: niet cachen, niet in frames, strikte bronnen
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://www.mollie.com https://*.mollie.com");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store, private');

    if (!defined('GEEN_SESSIE')) {
        start_sessie();
    }
}
