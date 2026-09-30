<?php
/*
 * Dagelijks onderhoud: het bewaarbeleid uitvoeren.
 *
 *   php beheer/cli/onderhoud.php                  dagelijks via cron, bijvoorbeeld om 03:15
 *   php beheer/cli/onderhoud.php --na-herstel     na het terugzetten van een back-up
 *
 * Met meerdere organisaties: voeg --organisatie=naam toe (één regel per organisatie).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../inc/bootstrap.php';

if (in_array('--na-herstel', $argv, true)) {
    $aantal = herhaal_verwijderingen();
    echo "Verwijderingen opnieuw uitgevoerd: {$aantal}\n";
}
$telling = voer_bewaarbeleid_uit();
echo 'Bewaarbeleid uitgevoerd (' . cfg('organisatie') . "):\n";
foreach ($telling as $categorie => $aantal) {
    echo str_pad($categorie, 22) . $aantal . "\n";
}
