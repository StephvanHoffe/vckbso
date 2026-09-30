<?php
/*
 * Vult de beheeromgeving met verzonnen demogegevens om hem uit te proberen.
 * Alleen voor een test- of demo-omgeving, nooit op de echte omgeving draaien.
 *
 * Gebruik (vanuit de hoofdmap van het project):
 *   php tools/beheer-demodata.php
 *
 * Alle accounts krijgen het wachtwoord "demo-wachtwoord".
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Alleen via de opdrachtregel.');
}
require __DIR__ . '/../beheer/inc/bootstrap.php';

if ((int) waarde("SELECT COUNT(*) FROM gebruikers WHERE rol = 'ouder'") > 0 && !in_array('--forceer', $argv, true)) {
    fwrite(STDERR, "Er staan al ouders in de database. Dit script is alleen voor een lege demo-omgeving.\nWeet je het zeker? Gebruik dan --forceer.\n");
    exit(1);
}

const DEMO_WACHTWOORD = 'demo-wachtwoord';
$hash = password_hash(DEMO_WACHTWOORD, PASSWORD_DEFAULT);

function demo_gebruiker(string $rol, string $naam, string $email, string $hash, array $extra = []): int
{
    $bestaand = waarde('SELECT id FROM gebruikers WHERE email = ?', [$email]);
    if ($bestaand) {
        return (int) $bestaand;
    }
    q('INSERT INTO gebruikers (rol, status, naam, email, telefoon, straat, postcode, plaats, wachtwoord_hash, mandaat_status, mandaat_id, mandaat_rekening, mandaat_naam, mandaat_datum, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $rol, $extra['status'] ?? 'actief', $naam, $email, $extra['telefoon'] ?? '06 00 00 00 00', $extra['straat'] ?? 'Demostraat 1', $extra['postcode'] ?? '1000 AA', 'Amsterdam', $hash,
        $extra['mandaat'] ?? 'geen', ($extra['mandaat'] ?? '') === 'demo' ? 'demo' : null, ($extra['mandaat'] ?? '') === 'demo' ? 'NL00 **** **** 0000' : '', ($extra['mandaat'] ?? '') === 'demo' ? $naam : '', ($extra['mandaat'] ?? '') === 'demo' ? vandaag() : null, nu(),
    ]);
    return laatste_id();
}

// Team
$beheerder = demo_gebruiker('beheerder', 'Demo Beheerder', 'beheerder@demo.bsovck.nl', $hash);
$begeleider = demo_gebruiker('medewerker', 'Demo Begeleider', 'begeleider@demo.bsovck.nl', $hash);

// Instellingen (voorbeeldwaarden, geen echte tarieven)
if (instelling('uurtarief_cent') === '') {
    instelling_zet('uurtarief_cent', '1000');
    instelling_zet('uren_per_middag', '4');
}

// Groepen
if (!actieve_groepen()) {
    q("INSERT INTO groepen (naam, omschrijving, max_kinderen, dagen, begintijd, eindtijd, kleur) VALUES ('De Bengels', 'Onderbouw', 8, '1,2,3,4,5', '14:00', '17:00', 'sun'), ('De Kanjers', 'Bovenbouw', 10, '1,2,4', '14:00', '17:00', 'mint')");
}
$groepen = actieve_groepen();
// De begeleider werkt in de eerste groep; de beheerder mag alles
q('INSERT OR IGNORE INTO medewerker_groepen (gebruiker_id, groep_id) VALUES (?, ?)', [$begeleider, $groepen[0]['id']]);
foreach (alle_rechten() as $recht) {
    q('INSERT OR IGNORE INTO team_rechten (gebruiker_id, recht) VALUES (?, ?)', [$beheerder, $recht]);
}
foreach (RECHTEN_BEGELEIDER as $recht) {
    q('INSERT OR IGNORE INTO team_rechten (gebruiker_id, recht) VALUES (?, ?)', [$begeleider, $recht]);
}

// Gezinnen met kinderen (verzonnen namen)
$gezinnen = [
    ['Sam de Jong', [['Noor', 'de Jong', '-6 years', 1], ['Finn', 'de Jong', '-9 years', 1]]],
    ['Aylin Kaya', [['Elif', 'Kaya', '-7 years', 1]]],
    ['Mark Visser', [['Daan', 'Visser', '-10 years', 0]]],
    ['Priya Sharma', [['Anika', 'Sharma', '-5 years', 1]]],
    ['Kees Mulder', [['Lieke', 'Mulder', '-8 years', 1], ['Bram', 'Mulder', '-11 years', 1]]],
];
$kinderen = [];
foreach ($gezinnen as $i => [$ouderNaam, $kids]) {
    $email = strtolower(str_replace(' ', '.', $ouderNaam)) . '@demo.bsovck.nl';
    $ouderId = demo_gebruiker('ouder', $ouderNaam, $email, $hash, ['mandaat' => $i === 3 ? 'geen' : 'demo']);
    foreach ($kids as [$voornaam, $achternaam, $leeftijd, $toestemming]) {
        $groep = $groepen[strtotime($leeftijd) < strtotime('-8 years') ? min(1, count($groepen) - 1) : 0];
        q('INSERT INTO kinderen (ouder_id, groep_id, voornaam, achternaam, geboortedatum, foto_toestemming, bijzonderheden, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
            $ouderId, $groep['id'], $voornaam, $achternaam, date('Y-m-d', strtotime($leeftijd . ' -40 days')), $toestemming, versleutel($voornaam === 'Elif' ? 'Allergisch voor noten' : ''), nu(),
        ]);
        $kindId = laatste_id();
        // Ouder met gezag, gecontroleerd door de beheerder
        koppel_verzorger($kindId, $ouderId, 'ouder', true);
        q("UPDATE kind_verzorgers SET gezag_gecontroleerd_op = ?, gezag_gecontroleerd_door = ?, gezag_bron = 'gezagsregister' WHERE kind_id = ? AND gebruiker_id = ?", [nu(), $beheerder, $kindId, $ouderId]);
        $kinderen[] = rij('SELECT * FROM kinderen WHERE id = ?', [$kindId]);
    }
}
// Eén nieuwe aanmelding die nog goedgekeurd moet worden
$nieuw = demo_gebruiker('ouder', 'Lisa Bos', 'lisa.bos@demo.bsovck.nl', $hash, ['status' => 'nieuw', 'mandaat' => 'demo']);
q("INSERT INTO kinderen (ouder_id, voornaam, achternaam, geboortedatum, foto_toestemming, aangemaakt_op) VALUES (?, 'Mees', 'Bos', ?, 1, ?)", [$nieuw, date('Y-m-d', strtotime('-6 years')), nu()]);
koppel_verzorger(laatste_id(), $nieuw, 'ouder', true); // gezag opgegeven, nog te controleren

// Inschrijvingen: vorige maand (voor facturen) tot drie weken vooruit
$van = date('Y-m-01', strtotime('first day of last month'));
$tot = date('Y-m-d', strtotime('+3 weeks'));
foreach ($kinderen as $n => $kind) {
    foreach (datums_tussen($van, $tot) as $datum) {
        if (in_array(weekdag($datum), [($n % 5) + 1, (($n + 2) % 5) + 1], true)) {
            schrijf_in($kind, $datum, $beheerder, true);
        }
    }
}

// Berichten en observaties
$ouderNoor = (int) $kinderen[0]['ouder_id'];
nieuw_bericht($ouderNoor, $ouderNoor, null, 'bericht', 'Hoi! Noor wordt vandaag om 16:30 opgehaald door oma.', true, false);
q("INSERT INTO observaties (kind_id, auteur_id, datum, gebied, tekst, gedeeld, aangemaakt_op) VALUES (?, ?, ?, 'motoriek', ?, 1, ?)", [$kinderen[0]['id'], $begeleider, vandaag(), versleutel('Oefende vandaag fanatiek met touwtjespringen en kan nu tien keer achter elkaar!'), nu()]);

// Financiën: een paar verzonnen voorbeelduitgaven in de afgelopen maanden
$voorbeelden = [
    ['huur', 'Huur ruimte (voorbeeld)', 150000],
    ['eten', 'Boodschappen fruit en brood (voorbeeld)', 18450],
    ['materiaal', 'Knutselmateriaal (voorbeeld)', 6275],
    ['activiteiten', 'Uitje kinderboerderij (voorbeeld)', 9500],
];
for ($m = 3; $m >= 0; $m--) {
    foreach ($voorbeelden as $i => [$categorie, $omschrijving, $bedrag]) {
        if ($m === 0 && $i > 1) {
            continue;
        }
        q("INSERT INTO boekingen (soort, datum, categorie, omschrijving, bedrag_cent, aangemaakt_door, aangemaakt_op) VALUES ('uitgave', ?, ?, ?, ?, ?, ?)", [
            date('Y-m-d', strtotime("first day of -{$m} month +" . (2 + $i * 5) . ' days')), $categorie, $omschrijving, $bedrag + $m * 125, $beheerder, nu(),
        ]);
    }
}

echo "Demogegevens aangemaakt.\n";
echo "Inloggen met wachtwoord \"" . DEMO_WACHTWOORD . "\":\n";
echo "  beheerder@demo.bsovck.nl   (beheerder)\n";
echo "  begeleider@demo.bsovck.nl  (medewerker)\n";
echo "  sam.de.jong@demo.bsovck.nl (ouder)\n";
echo "Het team stelt bij de eerste keer inloggen tweestapsverificatie in (verplicht).\n";
