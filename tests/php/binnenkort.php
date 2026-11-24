<?php
/*
 * Hulpscript voor tests/binnenkort.test.mjs: de binnenkort-pagina (coming soon) en de livegang in een lege, tijdelijke
 * database en webmap. Geeft als JSON terug wat er gebeurde.
 * Draaien met: VCK_BEHEER_CONFIG=/tmp/.../config.php php tests/php/binnenkort.php
 */
declare(strict_types=1);

require __DIR__ . '/../../beheer/inc/bootstrap.php';

$uit = [];
$pakket = ['straat' => 'Teststraat 1', 'postcode' => '1098 TZ', 'plaats' => 'Amsterdam'];
$bevestig = fn (string $email) => q('UPDATE interesse SET bevestigd_op = ? WHERE email = ?', [nu(), $email]);
$mails = fn () => (string) @file_get_contents(data_dir() . '/mail.log');

file_put_contents(website_map() . '/' . BINNENKORT_BESTAND, '');
$uit['aan_bij_start'] = binnenkort_aan();

// Drie aanmeldingen via de binnenkort-pagina (één Engelstalig, één nog niet bevestigd) en één via de pop-up
interesse_aanmelden('Sam Test', 'sam@test.invalid', '', 'binnenkort', $pakket);
$bevestig('sam@test.invalid');
taal('en');
interesse_aanmelden('Alex Test', 'alex@test.invalid', '', 'binnenkort', $pakket);
taal('');
$bevestig('alex@test.invalid');
interesse_aanmelden('Fatima Test', 'fatima@test.invalid', '', 'binnenkort', $pakket);
interesse_aanmelden('Pip Test', 'pip@test.invalid', '', 'pop-up', $pakket);
$bevestig('pip@test.invalid');

$uit['wachtend_voor_live'] = array_column(live_mail_wachtend(), 'email');
$uit['niet_live_geen_mail'] = stuur_live_mails();
file_put_contents(data_dir() . '/mail.log', '');

// Live zetten: het bestand weg en een mail aan de bevestigde aanmeldingen van de binnenkort-pagina
$uit['live'] = zet_website_live();
$uit['aan_na_live'] = binnenkort_aan();
$uit['live_op_gezet'] = website_live_op() !== null;
$log = $mails();
$uit['mail_aan'] = array_values(array_unique(array_map(fn ($m) => $m[1], preg_match_all('/\] Aan: (\S+)$/m', $log, $m, PREG_SET_ORDER) ? $m : [])));
$uit['nl_onderwerp'] = str_contains($log, 'Onderwerp: Het is zover: de website van Sporty is live!');
$uit['en_onderwerp'] = str_contains($log, "Onderwerp: It's here: Sporty's website is live!");
$uit['nl_link'] = str_contains($log, 'Kijk rond op https://test.invalid/') && str_contains($log, 'op-de-hoogte.php?afmelden=');
$uit['en_link'] = str_contains($log, 'Have a look around at https://test.invalid/en/');

// Nog een keer: niemand dubbel
$uit['nogmaals'] = zet_website_live();

// Fatima bevestigt pas na de livegang: het dagelijkse onderhoud (of de bevestiging zelf) stuurt de mail alsnog
$bevestig('fatima@test.invalid');
file_put_contents(data_dir() . '/mail.log', '');
$uit['later'] = stuur_live_mails();
$uit['later_aan'] = str_contains($mails(), 'Aan: fatima@test.invalid');

// Terug naar de binnenkort-pagina, en iemand die al op de lijst stond meldt zich daar aan
zet_binnenkort_aan();
$uit['weer_aan'] = binnenkort_aan() && website_live_op() === null;
interesse_aanmelden('Pip Test', 'pip@test.invalid', '', 'binnenkort', $pakket);
$uit['pip_wacht_nu'] = array_column(live_mail_wachtend(), 'email');

echo json_encode($uit, JSON_UNESCAPED_UNICODE);
