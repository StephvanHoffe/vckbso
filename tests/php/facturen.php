<?php
/*
 * Hulpscript voor tests/facturen.test.mjs: maakt in een lege, tijdelijke database een paar gezinnen aan,
 * maakt de facturen voor maart 2027 en geeft de facturen en regels terug als JSON.
 * Draaien met: VCK_BEHEER_CONFIG=/tmp/.../config.php php tests/php/facturen.php
 */
declare(strict_types=1);

require __DIR__ . '/../../beheer/inc/bootstrap.php';

$nu = '2027-02-20 10:00:00';
$ouder = function (string $naam, string $status = 'actief') use ($nu): int {
    q("INSERT INTO gebruikers (rol, status, naam, email, aangemaakt_op) VALUES ('ouder', ?, ?, ?, ?)", [$status, $naam, strtolower(str_replace(' ', '.', $naam)) . '@test.invalid', $nu]);
    return laatste_id();
};
q("INSERT INTO groepen (naam, max_kinderen, dagen) VALUES ('Testgroep', 20, '1,2,3,4,5')");
$groep = laatste_id();
$kind = function (int $ouderId, string $voornaam, string $abonnement, string $dagen = '', ?string $vanaf = null, int $actief = 1, ?string $gestopt = null) use ($groep, $nu): int {
    q('INSERT INTO kinderen (ouder_id, groep_id, voornaam, achternaam, geboortedatum, actief, gestopt_op, abonnement, vaste_dagen, abonnement_vanaf, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $ouderId, $groep, $voornaam, 'Test', '2019-05-01', $actief, $gestopt, $abonnement, $dagen, $vanaf, $nu,
    ]);
    return laatste_id();
};
$dag = function (int $kindId, string $datum, string $status, string $gewijzigd = '2027-02-20 10:00:00') use ($groep): void {
    q('INSERT INTO inschrijvingen (kind_id, groep_id, datum, status, aangemaakt_op, gewijzigd_op) VALUES (?, ?, ?, ?, ?, ?)', [$kindId, $groep, $datum, $status, '2027-02-20 10:00:00', $gewijzigd]);
};

// 1. Sporty Abonnement, dinsdag en donderdag, de hele maand: 2 x 27 uur x EUR 9,75 = EUR 526,50
//    (ook als het kind een dag afgemeld of ziek is)
$a = $ouder('Ouder Abonnement');
$noor = $kind($a, 'Noor', 'sporty-52', '2,4', '2027-01-04');
$dag($noor, '2027-03-02', 'bevestigd');
$dag($noor, '2027-03-04', 'afgemeld');
$dag($noor, '2027-03-09', 'ziek');

// 2. Incidenteel: twee schooldagen, één vakantiedag, één dag te laat afgemeld (minder dan 48 uur) en
//    één dag op tijd afgemeld (telt niet mee)
$b = $ouder('Ouder Incidenteel');
$daan = $kind($b, 'Daan', 'incidenteel');
$dag($daan, '2027-03-02', 'bevestigd');
$dag($daan, '2027-03-11', 'ziek');
q('INSERT INTO groep_uitzonderingen (groep_id, datum, hele_dag) VALUES (?, ?, 1)', [$groep, '2027-03-15']);
$dag($daan, '2027-03-15', 'bevestigd');
$dag($daan, '2027-03-18', 'afgemeld', '2027-03-17 09:00:00');   // 29 uur voor 14.00 uur: te laat
$dag($daan, '2027-03-23', 'afgemeld', '2027-03-20 09:00:00');   // 77 uur van tevoren: kosteloos

// 3. Sporty Abonnement dat halverwege de maand begint (16 maart): naar rato 16 van de 31 dagen
$c = $ouder('Ouder Halverwege');
$kind($c, 'Elif', 'sporty-52', '1', '2027-03-16');

// 4. Geen factuur: een nieuwe aanmelding (nog niet goedgekeurd) en een kind zonder abonnement
$d = $ouder('Ouder Nieuw', 'nieuw');
$kind($d, 'Mees', 'sporty-52', '2,4', '2027-03-01');
$e = $ouder('Ouder Zonder');
$bram = $kind($e, 'Bram', '');
$dag($bram, '2027-03-02', 'bevestigd');

$zonder = array_column(kinderen_zonder_abonnement('2027-03'), 'voornaam');
[$aantal, $totaal] = maak_facturen('2027-03');
[$nogmaals] = maak_facturen('2027-03'); // tweede keer: niets dubbel

$facturen = [];
foreach (rijen('SELECT f.*, g.naam FROM facturen f JOIN gebruikers g ON g.id = f.ouder_id ORDER BY f.id') as $f) {
    $facturen[$f['naam']] = [
        'bedrag_cent' => (int) $f['bedrag_cent'],
        'regels' => array_map(fn ($r) => ['omschrijving' => $r['omschrijving'], 'aantal' => (float) $r['aantal'], 'prijs_cent' => (int) $r['prijs_cent'], 'bedrag_cent' => (int) $r['bedrag_cent']],
            rijen('SELECT * FROM factuurregels WHERE factuur_id = ? ORDER BY id', [$f['id']])),
    ];
}
echo json_encode([
    'aantal' => $aantal,
    'totaal' => $totaal,
    'nogmaals' => $nogmaals,
    'zonder_abonnement' => $zonder,
    'facturen' => $facturen,
    'gefactureerd' => array_map('intval', array_column(rijen('SELECT gefactureerd FROM inschrijvingen WHERE kind_id = ? ORDER BY datum', [$daan]), 'gefactureerd')),
    'te_laat' => [
        'precies 48 uur' => te_laat_afgemeld(['datum' => '2027-03-18', 'gewijzigd_op' => '2027-03-16 14:00:00'], false, 48),
        'net te laat' => te_laat_afgemeld(['datum' => '2027-03-18', 'gewijzigd_op' => '2027-03-16 14:00:01'], false, 48),
        'hele dag om 08.00' => te_laat_afgemeld(['datum' => '2027-03-18', 'gewijzigd_op' => '2027-03-16 09:00:00'], true, 48),
    ],
], JSON_UNESCAPED_UNICODE);
