<?php
/*
 * Hulpscript voor tests/emails.test.mjs: verstuurt in een lege, tijdelijke database een paar e-mails en geeft
 * terug wat er in mail_log staat (E-mails → Verzonden), plus de aanmeldmail met de wachtwoordlink.
 * Draaien met: VCK_BEHEER_CONFIG=/tmp/.../config.php php tests/php/emails.php
 */
declare(strict_types=1);

require __DIR__ . '/../../beheer/inc/bootstrap.php';

$uit = [];
// 1. Testmodus (standaard): niets de deur uit, wel vastgelegd, en een waarschuwing
$uit['methode'] = mail_methode();
$link = 'https://mijn.example/beheer/wachtwoord-instellen.php?token=abc';
stuur_mailtekst('ouder@test.invalid', 'aanmelding_ontvangen', ['voornaam' => 'Sam', 'kinderen' => 'Noor', 'link' => $link]);
$uit['waarschuwing_testmodus'] = mail_waarschuwing()[0] ?? null;
$uit['maillog_bestand_heeft_link'] = str_contains((string) file_get_contents(data_dir() . '/mail.log'), $link);

// 2. Een aangepaste tekst zonder {link} (van vóór de link verplicht werd): de link komt er onderaan bij
q("INSERT INTO mail_teksten (sleutel, onderwerp, tekst, gewijzigd_op) VALUES ('aanmelding_ontvangen', 'Welkom!', 'Hoi {voornaam}, leuk!', ?)", [nu()]);
mailtekst_vernieuw();
[, $tekst] = mailtekst('aanmelding_ontvangen', ['voornaam' => 'Sam', 'kinderen' => 'Noor', 'link' => $link]);
$uit['aangepast_met_link'] = $tekst;
$uit['controle_zonder_link'] = mailtekst_controleer('aanmelding_ontvangen', 'Welkom!', 'Hoi {voornaam}, leuk!');

// 3. Overgeslagen (te veel e-mails naar hetzelfde adres)
mail_overgeslagen('veel@test.invalid', 'aanmelding_ontvangen', MAIL_REDEN_TE_VEEL);

// 4. Eigen mailserver die niet bereikbaar is: mislukt, met de reden, en een waarschuwing
koppeling_zet('mail.methode', 'smtp', 1);
koppeling_zet('smtp.host', '127.0.0.1', 1);
koppeling_zet('smtp.poort', '9', 1);
koppeling_zet('smtp.gebruiker', 'info@test.invalid', 1);
koppeling_zet('smtp.wachtwoord', 'geheim', 1);
koppelingen_opgeslagen(true);
$uit['gelukt_smtp'] = stuur_mailtekst('ouder@test.invalid', 'wachtwoord_vergeten', ['naam' => 'Sam', 'link' => $link]);
$uit['waarschuwing_mislukt'] = mail_waarschuwing()[0] ?? null;

$uit['log'] = array_map(fn ($r) => ['aan' => $r['aan'], 'sleutel' => $r['sleutel'], 'status' => $r['status'], 'onderwerp' => $r['onderwerp'], 'fout_leeg' => $r['fout'] === ''],
    rijen('SELECT * FROM mail_log ORDER BY id'));
$uit['inhoud_bewaard'] = (int) waarde("SELECT COUNT(*) FROM mail_log WHERE onderwerp LIKE '%token%' OR fout LIKE '%token%'");

// 5. Bewaartermijn: een e-mail van een jaar geleden verdwijnt, een recente blijft
q("INSERT INTO mail_log (tijd, aan, status) VALUES (?, 'oud@test.invalid', 'verstuurd')", [date('Y-m-d H:i:s', strtotime('-1 year'))]);
voer_bewaarbeleid_uit();
$uit['na_bewaarbeleid'] = array_column(rijen('SELECT aan FROM mail_log ORDER BY id'), 'aan');
$uit['rechten'] = in_array('emails', alle_rechten(), true);

echo json_encode($uit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
