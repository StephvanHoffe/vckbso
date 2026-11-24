<?php
/*
 * Hulpscript voor tests/vercel.test.mjs: Mijn BSO met de website op een ander adres (website_url in de configuratie:
 * de website op Vercel, Mijn BSO op mijn.bsosporty.nl). Geeft als JSON terug wat de functies doen.
 * Draaien met: VCK_BEHEER_CONFIG=/tmp/.../config.php php tests/php/website-elders.php
 */
declare(strict_types=1);

require __DIR__ . '/../../beheer/inc/bootstrap.php';

$herkomst = function (string $origin): bool {
    $_SERVER['HTTP_ORIGIN'] = $origin;
    return eigen_herkomst();
};
$uit = [
    'elders' => website_elders(),
    'website_url' => website_url(),
    'hosts' => website_hosts(),
    'pagina_nl' => website_pagina('blijf-op-de-hoogte.html', 'stay-in-the-loop.html'),
    'home' => website_pagina('index.html', 'index.html'),
    'link' => website_link('privacy.html'),
    'herkomst' => [
        'website' => $herkomst('https://bsosporty.nl'),
        'www' => $herkomst('https://www.bsosporty.nl'),
        'mijn' => $herkomst('https://mijn.bsosporty.nl'),
        'null' => $herkomst('null'),
        'leeg' => $herkomst(''),
        'vreemd' => $herkomst('https://kwaadaardig.example'),
        'lijkt erop' => $herkomst('https://bsosporty.nl.example'),
    ],
];
taal('en');
$uit['pagina_en'] = website_pagina('blijf-op-de-hoogte.html', 'stay-in-the-loop.html');
$uit['adres_en'] = website_adres('en');
taal('');

// De schakelaar: zonder bestand, via website_live_op (website-status.php geeft !binnenkort_aan())
$uit['binnenkort_bij_start'] = binnenkort_aan();
$uit['live'] = zet_website_live();
$uit['binnenkort_na_live'] = binnenkort_aan();
zet_binnenkort_aan();
$uit['binnenkort_weer_aan'] = binnenkort_aan();
$uit['geen_bestand'] = !is_file(website_map() . '/' . BINNENKORT_BESTAND);

echo json_encode($uit, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
