<?php
/*
 * Configuratie van de beheeromgeving.
 *
 * Kopieer dit bestand naar config.php (in dezelfde map) en vul het in.
 * config.php staat niet in git: er staan geheimen in. Bewaar een kopie van de
 * sleutels op een veilige plek buiten de server (bijvoorbeeld een wachtwoordkluis):
 * zonder de sleutels zijn de versleutelde gegevens en back-ups onbruikbaar.
 *
 * Sleutel maken: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
 */

return [
    // Volledige URL van de beheeromgeving, zonder / aan het eind.
    // TODO: aanpassen als het domein anders wordt dan bsovck.nl
    'app_url' => 'https://bsovck.nl/beheer',

    // Map voor de database en de foto's: BUITEN de webroot, bijvoorbeeld
    // '/home/account/beheer-data'. (De standaardmap beheer/data is met .htaccess
    // afgeschermd, maar dat werkt alleen op Apache.)
    'data_dir' => __DIR__ . '/data',

    // Encryptiesleutel voor gevoelige gegevens (bijzonderheden, observaties,
    // berichten, foto's, exports). Verplicht. Nooit meer wijzigen na de start.
    'sleutel' => '',

    // Alleen om lokaal te testen: zonder sleutel starten (sleutel in de datamap).
    'ontwikkelmodus' => false,

    // Eenmalige code om de eerste beheerder aan te maken via installeren.php.
    // Kies iets lang en willekeurigs. Na de installatie weer leegmaken.
    'installatiecode' => '',

    // Mollie (automatische incasso en betalen). Leeg = demo-modus: er wordt
    // dan niets echt afgeschreven. Begin met een test-sleutel (test_...).
    'mollie' => [
        'api_key' => '',
        'methode_machtiging' => 'ideal',
    ],

    // E-mail. 'log' schrijft berichten naar data/mail.log (om te testen),
    // 'mail' verstuurt ze met de mailfunctie van de server.
    'mail' => [
        'methode' => 'log',
        'van' => 'info@vckbso.nl',
        'van_naam' => 'BSO VCK',
    ],

    // Hier komen meldingen binnen van nieuwe aanmeldingen, berichten en privacyverzoeken.
    'team_email' => 'info@vckbso.nl',

    // Back-ups (php beheer/cli/backup.php, dagelijks via cron).
    'backup' => [
        'map' => '',          // buiten de webroot, bijvoorbeeld '/home/account/backups'
        'sleutel' => '',      // een ANDERE sleutel dan hierboven
        'bewaardagen' => 30,  // daarna verwijderd; zo verdwijnen gewiste gegevens ook uit back-ups
    ],

    // Meerdere opvangorganisaties op één installatie? Geef elke organisatie
    // een eigen domein, datamap, sleutel en eventueel Mollie-account. De
    // gegevens staan dan in aparte databases en lopen nooit door elkaar.
    // Een onbekend domein krijgt geen toegang.
    //
    // 'organisaties' => [
    //     'vck' => [
    //         'hosts' => ['bsovck.nl', 'www.bsovck.nl'],
    //         'app_url' => 'https://bsovck.nl/beheer',
    //         'data_dir' => '/home/account/data-vck',
    //         'sleutel' => '...',
    //         'backup' => ['map' => '/home/account/backups-vck', 'sleutel' => '...'],
    //     ],
    //     'andere-bso' => [
    //         'hosts' => ['mijn.anderebso.nl'],
    //         'app_url' => 'https://mijn.anderebso.nl/beheer',
    //         'data_dir' => '/home/account/data-andere',
    //         'sleutel' => '...',
    //         'mollie' => ['api_key' => '...'],
    //         'backup' => ['map' => '/home/account/backups-andere', 'sleutel' => '...'],
    //     ],
    // ],
];
