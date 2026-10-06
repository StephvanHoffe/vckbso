<?php
// Extra verzonnen voorbeeldgegevens voor de momentopname van tools/voorbeeld/bouw.mjs.
// Alleen voor een tijdelijke demo-omgeving, nooit op de echte omgeving draaien.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    exit('Alleen via de opdrachtregel.');
}
require __DIR__ . '/../../beheer/inc/bootstrap.php';

// Team heeft tweestapsverificatie al ingesteld (voorbeeld)
q("UPDATE gebruikers SET mfa_actief = 1, mfa_geheim = ? WHERE rol IN ('beheerder', 'medewerker')", [versleutel('JBSWY3DPEHPK3PXP')]);
q("UPDATE gebruikers SET naam = 'Demo Beheerder' WHERE rol = 'beheerder'");

$beheerder = (int) waarde("SELECT id FROM gebruikers WHERE rol = 'beheerder'");
$begeleider = (int) waarde("SELECT id FROM gebruikers WHERE rol = 'medewerker'");
$kinderen = rijen('SELECT * FROM kinderen WHERE actief = 1 ORDER BY id');
$noor = $kinderen[0];
$sam = (int) $noor['ouder_id'];

// Foto's (stockfoto's van de website) gedeeld met ouders
$fotos = [['voetbal', 'Voetballen op het veldje', [0, 1]], ['knutselen', 'Knutselmiddag', [0]], ['schilderen', 'Schilderen in de zon', [2]], ['bal', 'Balspelletje', [1, 3]]];
foreach ($fotos as [$bestand, $bijschrift, $welke]) {
    $pad = __DIR__ . "/../../assets/fotos/bron/{$bestand}.jpg";
    [$rel, $b, $h] = bewaar_foto(['error' => UPLOAD_ERR_OK, 'size' => filesize($pad), 'tmp_name' => $pad]);
    q('INSERT INTO fotos (bestand, breedte, hoogte, bijschrift, geupload_door, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?)', [$rel, $b, $h, $bijschrift, $begeleider, nu()]);
    $fotoId = laatste_id();
    foreach ($welke as $i) {
        q('INSERT INTO foto_kinderen (foto_id, kind_id) VALUES (?, ?)', [$fotoId, $kinderen[$i]['id']]);
    }
}

// Observaties
foreach ([
    [$noor['id'], 'sociaal', 'Hielp vandaag uit zichzelf een nieuw kindje met de weg vinden. Heel lief om te zien!', 1],
    [$noor['id'], 'taal', 'Vertelde aan tafel een lang verhaal over het schoolreisje, met veel details.', 1],
    [$noor['id'], 'zelfstandigheid', 'Ruimde vandaag zonder vragen zelf de knutseltafel op en hielp daarna mee met de bekers. Was na het ophalen van de broer even verdrietig, na een knuffel weer vrolijk mee met spelen.', 0],
    [$kinderen[1]['id'], 'motoriek', 'Doelman bij het voetballen en hield drie ballen tegen.', 1],
] as [$kind, $gebied, $tekst, $gedeeld]) {
    q('INSERT INTO observaties (kind_id, auteur_id, datum, gebied, tekst, gedeeld, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, ?)', [$kind, $begeleider, date('Y-m-d', strtotime('-' . random_int(1, 20) . ' days')), $gebied, versleutel($tekst), $gedeeld, nu()]);
}

// Berichten heen en weer
nieuw_bericht($sam, $begeleider, (int) $noor['id'], 'bericht', 'Hoi! Goed doorgegeven, we weten dat oma Noor vandaag ophaalt. Tot straks!', false, true);
$aylin = (int) $kinderen[2]['ouder_id'];
nieuw_bericht($aylin, $aylin, (int) $kinderen[2]['id'], 'ziekmelding', 'Elif heeft koorts en blijft vandaag en morgen thuis.', true, false);
nieuw_bericht($aylin, $begeleider, (int) $kinderen[2]['id'], 'bericht', 'Beterschap voor Elif! We hebben haar voor beide dagen afgemeld.', false, true);

// Oma als verzorger van Noor: alleen foto's en berichten, geen gezag
q("INSERT INTO gebruikers (rol, status, naam, email, telefoon, wachtwoord_hash, aangemaakt_op) VALUES ('ouder', 'actief', 'Oma Joke', 'oma.joke@demo.bsovck.nl', '', ?, ?)", [password_hash('demo-wachtwoord', PASSWORD_DEFAULT), nu()]);
koppel_verzorger((int) $noor['id'], laatste_id(), 'grootouder', false, ['fotos', 'berichten']);

// Dinsdag zit vol voor nieuwe aanmeldingen (te zien in het aanmeldformulier en bij Groepen)
instelling_zet('volle_dagen', '2');

// Privacyverzoek van een ouder
q('INSERT INTO avg_verzoeken (gebruiker_id, kind_id, over_naam, soort, toelichting, deadline, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, ?)', [$sam, $noor['id'], kindnaam($noor), 'inzage', versleutel('Ik wil graag weten welke gegevens jullie over Noor bewaren.'), date('Y-m-d', strtotime('+1 month')), nu()]);

// Facturen van vorige maand; de meeste betaald
[$aantal] = maak_facturen(date('Y-m', strtotime('first day of last month')));
q("UPDATE facturen SET status = 'betaald', betaald_op = ? WHERE id IN (SELECT id FROM facturen ORDER BY id LIMIT 3)", [nu()]);
echo "Verrijkt: {$aantal} facturen\n";
