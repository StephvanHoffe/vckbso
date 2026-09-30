<?php
/*
 * Configuratie van de beheeromgeving.
 *
 * Kopieer dit bestand naar config.php (in dezelfde map) en vul het in.
 * config.php staat niet in git: er staan geheimen in.
 */

return [
    // Volledige URL van de beheeromgeving, zonder / aan het eind.
    // Nodig voor links in e-mails en om na een betaling terug te komen.
    // TODO: aanpassen als het domein anders wordt dan bsovck.nl
    'app_url' => 'https://bsovck.nl/beheer',

    // Map voor de database en de foto's. Zet deze liefst BUITEN de webroot,
    // bijvoorbeeld '/home/account/beheer-data'. De standaardmap beheer/data
    // is met .htaccess afgeschermd, maar dat werkt alleen op Apache.
    'data_dir' => __DIR__ . '/data',

    // Eenmalige code om de eerste beheerder aan te maken via installeren.php.
    // Kies iets lang en willekeurigs. Na de installatie mag je hem leegmaken.
    'installatiecode' => '',

    // Mollie (automatische incasso en betalen). Leeg = demo-modus: er wordt
    // dan niets echt afgeschreven. Begin met een test-sleutel (test_...).
    'mollie' => [
        'api_key' => '',
        // Betaalmethode voor de eerste betaling van 1 cent waarmee de ouder de
        // machtiging afgeeft. Leeg laten = de ouder kiest zelf bij Mollie.
        'methode_machtiging' => 'ideal',
    ],

    // E-mail. 'log' schrijft berichten naar data/mail.log (om te testen),
    // 'mail' verstuurt ze met de mailfunctie van de server.
    'mail' => [
        'methode' => 'log',
        'van' => 'info@vckbso.nl',
        'van_naam' => 'BSO VCK',
    ],

    // Hier komen meldingen binnen van nieuwe aanmeldingen en berichten.
    'team_email' => 'info@vckbso.nl',
];
