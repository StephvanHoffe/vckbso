<?php
/*
 * Is de website live? Voor de middleware van de website op Vercel (middleware.js in de hoofdmap van het project):
 * zolang dit {"live": false} geeft, zien bezoekers van bsosporty.nl de binnenkort-pagina. "Website live zetten" bij
 * Op de hoogte zet het op true (inc/binnenkort.php). Openbaar, zonder persoonsgegevens en zonder sessie.
 */
define('GEEN_SESSIE', true);
require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['live' => !binnenkort_aan()]);
