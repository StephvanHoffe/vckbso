<?php
/*
 * Voorproef van de website zolang de binnenkort-pagina aanstaat (inc/binnenkort.php): zet een cookie waardoor
 * .htaccess (alles op één server) of middleware.js (website op Vercel) de echte website laat zien in plaats van de
 * binnenkort-pagina. Staat de website elders, dan geldt het cookie voor dat domein (bsosporty.nl en www). Alleen voor het team.
 * ?uit=1 haalt het cookie weer weg. Dit is gemak, geen beveiliging: de website zelf is niet geheim.
 */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_login();
if (!is_team($gebruiker)) {
    niet_gevonden();
}
$uit = get_str('uit') === '1';
$cookie = [
    'expires' => $uit ? time() - 3600 : time() + 30 * 86400,
    'path' => '/',
    'secure' => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
];
if (website_elders()) {
    // Mijn BSO op mijn.bsosporty.nl, de website op bsosporty.nl: het cookie voor het hele domein
    $cookie['domain'] = preg_replace('/^www\./', '', (string) parse_url(website_url(), PHP_URL_HOST));
}
setcookie(VOORPROEF_COOKIE, $uit ? '' : '1', $cookie);
if ($uit) {
    flash('succes', 'Je ziet de website nu weer zoals bezoekers hem zien.');
    redirect('interesse.php#website-live');
}
redirect(website_link());
