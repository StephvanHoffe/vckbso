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

// Foto's (stockfoto's van Pexels, zie assets/fotos/BRONNEN.md) gedeeld met ouders
$fotos = [['voetbal', 'Voetballen op het veldje', [0, 1]], ['knutselen', 'Knutselmiddag', [0]], ['schilderen', 'Schilderen in de zon', [2]], ['bal', 'Balspelletje', [1, 3]]];
foreach ($fotos as [$bestand, $bijschrift, $welke]) {
    $pad = __DIR__ . "/demofotos/{$bestand}.jpg";
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

// Open sollicitaties via Werken bij, met een (verzonnen) cv
$cvInhoud = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
foreach ([
    ['Demo Sollicitant', 'sollicitant@demo.bsovck.nl', '06 98 76 54 32', 'Amsterdam', 'Sport en spel begeleiden', 'dinsdag,donderdag', 'Ik ben zelf jeugdtrainer bij de voetbalclub en sta het liefst de hele middag op het veld. Bij Sporty lijkt het me geweldig om sport en spel te begeleiden!', true, 'nieuw', '-2 days'],
    ['Demo Stagiair', 'stagiair@demo.bsovck.nl', '06 11 22 33 44', 'Amstelveen', 'Stage lopen', 'maandag,woensdag,vrijdag', 'Ik studeer Sport en Bewegen en zoek een stageplek waar kinderen na school lekker kunnen bewegen. Sporty past daar precies bij.', false, 'gesprek', '-9 days'],
] as [$naam, $email, $telefoon, $woonplaats, $functie, $dagen, $motivatie, $bewaren, $status, $wanneer]) {
    $bestand = bin2hex(random_bytes(16));
    versleutel_bestand(cv_map() . '/' . $bestand . '.bin', $cvInhoud);
    $binnen = date('Y-m-d H:i:s', strtotime($wanneer));
    q('INSERT INTO sollicitaties (status, naam, email, telefoon, woonplaats, functie, dagen, motivatie, cv_bestand, cv_naam, cv_type, cv_grootte, bewaren, aangemaakt_op, verwijderen_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $status, $naam, $email, $telefoon, $woonplaats, $functie, $dagen, versleutel($motivatie), $bestand, 'cv-' . strtolower(explode(' ', $naam)[1]) . '.pdf', 'application/pdf', strlen($cvInhoud), $bewaren ? 1 : 0, $binnen, sollicitatie_verwijderdatum($status, $bewaren, $binnen),
    ]);
}

// Facturen van vorige maand; de meeste betaald
[$aantal] = maak_facturen(date('Y-m', strtotime('first day of last month')));
q("UPDATE facturen SET status = 'betaald', betaald_op = ? WHERE id IN (SELECT id FROM facturen ORDER BY id LIMIT 3)", [nu()]);
echo "Verrijkt: {$aantal} facturen\n";
