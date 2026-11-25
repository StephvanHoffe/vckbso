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
    // Volledige URL van de beheeromgeving, zonder / aan het eind. Sinds november 2026 op een eigen subdomein
    // (de website staat op Vercel). Alles op één server? Dan 'https://bsosporty.nl/beheer'.
    'app_url' => 'https://mijn.bsosporty.nl/beheer',

    // Het adres van de website, zonder / aan het eind, als die NIET op deze server staat (de website op Vercel).
    // Mijn BSO linkt dan daarheen, laat de formulieren van de website toe, en "Website live zetten" werkt via
    // website-status.php (zie middleware.js). Alles op één server? Dan deze regel weglaten.
    'website_url' => 'https://bsosporty.nl',

    // Map voor de database en de foto's: BUITEN de webroot, bijvoorbeeld
    // '/home/account/beheer-data'. (De standaardmap beheer/data is met .htaccess
    // afgeschermd, maar dat werkt alleen op Apache.)
    'data_dir' => __DIR__ . '/data',

    // Encryptiesleutel voor gevoelige gegevens (bijzonderheden, observaties,
    // berichten, foto's, exports). Verplicht. Nooit meer wijzigen na de start.
    'sleutel' => '',

    // Alleen om lokaal te testen: zonder sleutel starten (sleutel in de datamap).
    'ontwikkelmodus' => false,

    // Staat er een server vóór Apache die https afhandelt (zoals bij Vimexx)? Dan ziet PHP zelf geen https, en
    // vertrouwt Mijn BSO de kop X-Forwarded-Proto van die server. Zonder deze regel: "te veel doorverwijzingen".
    'achter_proxy' => true,

    // Eenmalige code om de eerste beheerder aan te maken via installeren.php.
    // Kies iets lang en willekeurigs. Na de installatie weer leegmaken.
    'installatiecode' => '',

    // Stripe en e-mail stel je in Mijn BSO in, onder Koppelingen (versleuteld opgeslagen).
    // Wil je ze toch hier vastzetten? Wat hier staat, gaat voor en is in Mijn BSO dan
    // niet meer te wijzigen. Zie docs/beheeromgeving.md, "Koppelingen".
    //
    // 'stripe' => [
    //     'geheime_sleutel' => 'sk_test_...',   // Ontwikkelaars → API-sleutels
    //     'webhook_geheim' => 'whsec_...',      // Ontwikkelaars → Webhooks: het ondertekeningsgeheim
    //     'methoden_machtiging' => ['ideal', 'sepa_debit'],
    //     'methoden_betalen' => ['ideal'],
    // ],
    // 'mail' => [
    //     'methode' => 'smtp',                  // 'log' (alleen data/mail.log), 'mail' (mailfunctie server) of 'smtp'
    //     'van' => 'info@bsosporty.nl',
    //     'van_naam' => 'Sporty',
    // ],
    // 'smtp' => ['host' => 'smtp.example.nl', 'poort' => 587, 'beveiliging' => 'starttls', 'gebruiker' => 'info@bsosporty.nl', 'wachtwoord' => '...'],
    // 'team_email' => 'info@bsosporty.nl',         // meldingen van aanmeldingen, berichten en privacyverzoeken

    // Back-ups (php beheer/cli/backup.php, dagelijks via cron).
    'backup' => [
        'map' => '',          // buiten de webroot, bijvoorbeeld '/home/account/backups'
        'sleutel' => '',      // een ANDERE sleutel dan hierboven
        'bewaardagen' => 30,  // daarna verwijderd; zo verdwijnen gewiste gegevens ook uit back-ups
    ],

    // Meerdere opvangorganisaties op één installatie? Geef elke organisatie
    // een eigen domein, datamap, sleutel en eventueel Stripe-account. De
    // gegevens staan dan in aparte databases en lopen nooit door elkaar.
    // Een onbekend domein krijgt geen toegang.
    //
    // 'organisaties' => [
    //     'vck' => [
    //         'hosts' => ['bsosporty.nl', 'www.bsosporty.nl'],
    //         'app_url' => 'https://bsosporty.nl/beheer',
    //         'data_dir' => '/home/account/data-vck',
    //         'sleutel' => '...',
    //         'backup' => ['map' => '/home/account/backups-vck', 'sleutel' => '...'],
    //     ],
    //     'andere-bso' => [
    //         'hosts' => ['mijn.anderebso.nl'],
    //         'app_url' => 'https://mijn.anderebso.nl/beheer',
    //         'data_dir' => '/home/account/data-andere',
    //         'sleutel' => '...',
    //         'stripe' => ['geheime_sleutel' => '...', 'webhook_geheim' => '...'],
    //         'backup' => ['map' => '/home/account/backups-andere', 'sleutel' => '...'],
    //     ],
    // ],
];
