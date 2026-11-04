<?php
/*
 * Vrijblijvend aanmelden via de website (aanmelden.html, "Kom bij de club"): een formulier in 7 stappen,
 * verstuurd naar aanvraag.php. Er ontstaat een ouder met status "nieuw" (zonder wachtwoord) met de
 * kinderen eronder. Het team neemt contact op, maakt kennis en activeert het account; de ouder krijgt
 * dan een link om een wachtwoord te kiezen en geeft daarna de machtiging voor automatische incasso.
 *
 * Gegevens die alleen in het formulier voorkomen (voorletters, geslacht, groep, startdatum per kind,
 * partner, hoe de ouder Sporty vond) staan versleuteld als JSON in de kolom "aanvraag".
 */

declare(strict_types=1);

const AANVRAAG_MAX_KINDEREN = 6;
const AANVRAAG_GESLACHT_KIND = ['jongen' => 'Jongen', 'meisje' => 'Meisje', 'anders' => 'Anders'];
const AANVRAAG_GESLACHT_OUDER = ['man' => 'Man', 'vrouw' => 'Vrouw', 'anders' => 'Anders'];
const AANVRAAG_GEVONDEN = [
    'online' => 'Ik heb zelf online gezocht',
    'school' => 'Via school',
    'social' => 'Gezien op social media (Facebook / Instagram)',
    'anderen' => 'Via anderen die al bij Sporty zitten',
    'buurt' => 'Ik heb Sporty in de buurt gezien',
];
// TODO: abonnementen bevestigen met Sporty (de abonnementsstap is nog in overleg); nu is er één abonnement
const AANVRAAG_ABONNEMENTEN = ['sporty-52' => 'Sporty Abonnement (52 weken)'];

/** Stappen van het formulier (1 t/m 7) en 8 = verstuurd, voor de anonieme telling. */
const AANVRAAG_STAPPEN = [1 => 'Gegevens kind', 2 => 'School', 3 => 'Abonnement', 4 => 'Dagen', 5 => 'Gegevens ouder', 6 => 'Partner', 7 => 'Controleren', 8 => 'Verstuurd'];

/**
 * Leest en controleert een aanvraag. Geeft [gegevens, fouten] terug; de sleutels van de fouten zijn de
 * veldnamen van het formulier (bijvoorbeeld "kinderen[0][voornaam]"), zodat de website de melding bij het
 * goede veld zet.
 */
function lees_aanvraag(array $post): array
{
    $tekst = fn ($waarde, int $max = 200) => mb_substr(trim((string) ($waarde ?? '')), 0, $max);
    $fouten = [];

    $kinderen = [];
    foreach (array_slice(array_values(array_filter((array) ($post['kinderen'] ?? []), 'is_array')), 0, AANVRAAG_MAX_KINDEREN) as $i => $k) {
        $veld = fn (string $naam) => "kinderen[{$i}][{$naam}]";
        $kind = [
            'voornaam' => $tekst($k['voornaam'] ?? '', 80),
            'tussenvoegsel' => $tekst($k['tussenvoegsel'] ?? '', 20),
            'achternaam' => $tekst($k['achternaam'] ?? '', 80),
            'voorletters' => $tekst($k['voorletters'] ?? '', 20),
            'geslacht' => (string) ($k['geslacht'] ?? ''),
            'geboortedatum' => $tekst($k['geboortedatum'] ?? '', 10),
            'startdatum' => $tekst($k['startdatum'] ?? '', 10),
            'groep_klas' => $tekst($k['groep_klas'] ?? '', 40),
            'school_plaats' => $tekst($k['school_plaats'] ?? '', 80),
            'school' => $tekst($k['school'] ?? '', 120),
            'abonnement' => (string) ($k['abonnement'] ?? ''),
            'dagen' => array_values(array_intersect([1, 2, 3, 4, 5], array_map('intval', (array) ($k['dagen'] ?? [])))),
            'toelichting' => $tekst($k['toelichting'] ?? '', 500),
            'allergieen' => $tekst($k['allergieen'] ?? '', 500),
            'sportclub' => $tekst($k['sportclub'] ?? '', 120),
        ];
        $wie = $kind['voornaam'] !== '' ? $kind['voornaam'] : 'kind ' . ($i + 1);
        if ($kind['voornaam'] === '') {
            $fouten[$veld('voornaam')] = 'Vul de voornaam van je kind in.';
        }
        if ($kind['achternaam'] === '') {
            $fouten[$veld('achternaam')] = "Vul de achternaam van {$wie} in.";
        }
        if (!array_key_exists($kind['geslacht'], AANVRAAG_GESLACHT_KIND)) {
            $fouten[$veld('geslacht')] = "Kies jongen, meisje of anders voor {$wie}.";
        }
        if (!geldige_datum($kind['geboortedatum']) || $kind['geboortedatum'] > vandaag() || $kind['geboortedatum'] < date('Y-m-d', strtotime('-16 years'))) {
            $fouten[$veld('geboortedatum')] = "Vul een geldige geboortedatum in voor {$wie}.";
        }
        if ($kind['startdatum'] !== '' && (!geldige_datum($kind['startdatum']) || $kind['startdatum'] < vandaag())) {
            $fouten[$veld('startdatum')] = 'Kies een startdatum vanaf vandaag.';
        }
        if ($kind['school'] === '') {
            $fouten[$veld('school')] = "Vul de school van {$wie} in.";
        }
        if (!array_key_exists($kind['abonnement'], AANVRAAG_ABONNEMENTEN)) {
            $kind['abonnement'] = array_key_first(AANVRAAG_ABONNEMENTEN);
        }
        if (!$kind['dagen']) {
            $fouten[$veld('dagen') . '[]'] = "Kies minstens één dag voor {$wie}.";
        }
        $kinderen[] = $kind;
    }
    if (!$kinderen) {
        $fouten['kinderen[0][voornaam]'] = 'Vul de gegevens van je kind in.';
    }

    $ouder = [
        'voornaam' => $tekst($post['voornaam'] ?? '', 80),
        'tussenvoegsel' => $tekst($post['tussenvoegsel'] ?? '', 20),
        'achternaam' => $tekst($post['achternaam'] ?? '', 80),
        'voorletters' => $tekst($post['voorletters'] ?? '', 20),
        'geslacht' => (string) ($post['geslacht'] ?? ''),
        'postcode' => $tekst($post['postcode'] ?? '', 10),
        'huisnummer' => $tekst($post['huisnummer'] ?? '', 12),
        'straat' => $tekst($post['straat'] ?? '', 120),
        'plaats' => $tekst($post['plaats'] ?? '', 80),
        'email' => $tekst($post['email'] ?? '', 200),
        'telefoon' => $tekst($post['telefoon'] ?? '', 30),
        'gevonden' => array_key_exists((string) ($post['gevonden'] ?? ''), AANVRAAG_GEVONDEN) ? (string) $post['gevonden'] : '',
        'opmerkingen' => $tekst($post['opmerkingen'] ?? '', 1000),
    ];
    if ($ouder['geslacht'] !== '' && !array_key_exists($ouder['geslacht'], AANVRAAG_GESLACHT_OUDER)) {
        $ouder['geslacht'] = '';
    }
    if ($ouder['voornaam'] === '') {
        $fouten['voornaam'] = 'Vul je voornaam in.';
    }
    if ($ouder['achternaam'] === '') {
        $fouten['achternaam'] = 'Vul je achternaam in.';
    }
    if (!geldige_postcode($ouder['postcode'])) {
        $fouten['postcode'] = 'Vul een geldige postcode in, bijvoorbeeld 1098 TZ.';
    }
    if ($ouder['huisnummer'] === '') {
        $fouten['huisnummer'] = 'Vul je huisnummer in.';
    }
    if ($ouder['straat'] === '') {
        $fouten['straat'] = 'Vul je straat in.';
    }
    if ($ouder['plaats'] === '') {
        $fouten['plaats'] = 'Vul je woonplaats in.';
    }
    if (!geldig_email($ouder['email'])) {
        $fouten['email'] = $ouder['email'] === '' ? 'Vul je e-mailadres in, dan sturen we je een bevestiging.' : 'Dit lijkt geen geldig e-mailadres. Controleer het even, bijvoorbeeld naam@voorbeeld.nl.';
    } elseif (waarde('SELECT id FROM gebruikers WHERE email = ?', [$ouder['email']])) {
        $fouten['email'] = 'We kennen dit e-mailadres al. Heb je al een account? Log dan in. Heb je je net aangemeld? Dan nemen we snel contact met je op.';
    }
    if (!geldig_telefoon($ouder['telefoon'])) {
        $fouten['telefoon'] = $ouder['telefoon'] === '' ? 'Vul je telefoonnummer in, dan kunnen we je bellen.' : 'Vul een geldig telefoonnummer in, bijvoorbeeld 06 12 34 56 78.';
    }

    $partner = null;
    if (($post['partner'] ?? '') === 'ja') {
        $partner = [
            'voornaam' => $tekst($post['partner_voornaam'] ?? '', 80),
            'tussenvoegsel' => $tekst($post['partner_tussenvoegsel'] ?? '', 20),
            'achternaam' => $tekst($post['partner_achternaam'] ?? '', 80),
            'email' => $tekst($post['partner_email'] ?? '', 200),
            'telefoon' => $tekst($post['partner_telefoon'] ?? '', 30),
        ];
        if ($partner['voornaam'] === '') {
            $fouten['partner_voornaam'] = 'Vul de voornaam van je partner in.';
        }
        if ($partner['achternaam'] === '') {
            $fouten['partner_achternaam'] = 'Vul de achternaam van je partner in.';
        }
        if ($partner['email'] !== '' && !geldig_email($partner['email'])) {
            $fouten['partner_email'] = 'Dit lijkt geen geldig e-mailadres.';
        }
        if ($partner['telefoon'] !== '' && !geldig_telefoon($partner['telefoon'])) {
            $fouten['partner_telefoon'] = 'Vul een geldig telefoonnummer in, bijvoorbeeld 06 12 34 56 78.';
        }
    } elseif (($post['partner'] ?? '') !== 'nee') {
        $fouten['partner'] = 'Kies of je de gegevens van je partner wilt toevoegen.';
    }

    foreach (['akkoord_privacy' => 'Vink aan dat je akkoord gaat met de registratie van je gegevens volgens de privacyverklaring.', 'akkoord_voorwaarden' => 'Vink aan dat je akkoord gaat met de algemene voorwaarden.', 'akkoord_correct' => 'Vink aan dat de gegevens kloppen.'] as $vinkje => $melding) {
        if (empty($post[$vinkje])) {
            $fouten[$vinkje] = $melding;
        }
    }

    return [['kinderen' => $kinderen, 'ouder' => $ouder, 'partner' => $partner], $fouten];
}

/** Volledige naam, bijvoorbeeld "Sam de Jong". */
function aanvraag_naam(array $persoon): string
{
    return trim(preg_replace('/\s+/', ' ', ($persoon['voornaam'] ?? '') . ' ' . ($persoon['tussenvoegsel'] ?? '') . ' ' . ($persoon['achternaam'] ?? '')));
}

/** Slaat een gecontroleerde aanvraag op: de ouder (status nieuw, nog zonder wachtwoord) met de kinderen. Geeft het id van de ouder. */
function bewaar_aanvraag(array $aanvraag): int
{
    return transactie(function () use ($aanvraag) {
        $ouder = $aanvraag['ouder'];
        $dagen = [];
        $starts = [];
        foreach ($aanvraag['kinderen'] as $kind) {
            $dagen = array_merge($dagen, $kind['dagen']);
            if ($kind['startdatum'] !== '') {
                $starts[] = $kind['startdatum'];
            }
        }
        $dagen = array_values(array_unique($dagen));
        sort($dagen);
        $extra = [
            'voornaam' => $ouder['voornaam'], 'tussenvoegsel' => $ouder['tussenvoegsel'], 'achternaam' => $ouder['achternaam'],
            'voorletters' => $ouder['voorletters'], 'geslacht' => $ouder['geslacht'], 'gevonden' => $ouder['gevonden'],
            'partner' => $aanvraag['partner'], 'verstuurd_op' => nu(),
        ];
        q("INSERT INTO gebruikers (rol, status, naam, email, telefoon, straat, postcode, plaats, contactvoorkeur, wachtwoord_hash, gewenste_dagen, gewenste_startdatum, opmerkingen, aanvraag, aangemaakt_op)
           VALUES ('ouder', 'nieuw', ?, ?, ?, ?, ?, ?, '', NULL, ?, ?, ?, ?, ?)", [
            aanvraag_naam($ouder), $ouder['email'], $ouder['telefoon'], trim($ouder['straat'] . ' ' . $ouder['huisnummer']), normaliseer_postcode($ouder['postcode']), $ouder['plaats'],
            implode(',', $dagen), $starts ? min($starts) : null, $ouder['opmerkingen'], versleutel(json_encode($extra, JSON_UNESCAPED_UNICODE)), nu(),
        ]);
        $ouderId = laatste_id();
        foreach ($aanvraag['kinderen'] as $kind) {
            $extraKind = array_intersect_key($kind, array_flip(['voorletters', 'geslacht', 'startdatum', 'groep_klas', 'school_plaats', 'abonnement', 'dagen', 'toelichting', 'sportclub']));
            q('INSERT INTO kinderen (ouder_id, voornaam, achternaam, geboortedatum, school, bijzonderheden, foto_toestemming, aanvraag, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)', [
                $ouderId, $kind['voornaam'], trim($kind['tussenvoegsel'] . ' ' . $kind['achternaam']), $kind['geboortedatum'], $kind['school'],
                versleutel($kind['allergieen']), versleutel(json_encode($extraKind, JSON_UNESCAPED_UNICODE)), nu(),
            ]);
            // Opgegeven gezag; het team controleert dit bij de kennismaking
            koppel_verzorger(laatste_id(), $ouderId, 'ouder', true);
        }
        return $ouderId;
    });
}

/** De extra gegevens uit het aanmeldformulier (ontsleuteld), of een lege lijst. */
function aanvraag_gegevens(string $versleuteld): array
{
    if ($versleuteld === '') {
        return [];
    }
    $json = json_decode((string) ontsleutel($versleuteld), true);
    return is_array($json) ? $json : [];
}

/** Telt anoniem dat een stap van het formulier is bereikt (geen cookies, geen persoonsgegevens). */
function tel_aanvraagstap(int $stap): void
{
    if (!isset(AANVRAAG_STAPPEN[$stap])) {
        return;
    }
    q('INSERT INTO aanvraag_stappen (datum, stap, aantal) VALUES (?, ?, 1) ON CONFLICT (datum, stap) DO UPDATE SET aantal = aantal + 1', [vandaag(), $stap]);
}

/** Hoeveel keer elke stap is bereikt in de afgelopen $dagen dagen: stap => aantal. */
function aanvraag_trechter(int $dagen = 30): array
{
    $telling = array_fill_keys(array_keys(AANVRAAG_STAPPEN), 0);
    foreach (rijen('SELECT stap, SUM(aantal) AS aantal FROM aanvraag_stappen WHERE datum >= ? GROUP BY stap', [date('Y-m-d', strtotime("-{$dagen} days"))]) as $rij) {
        $telling[(int) $rij['stap']] = (int) $rij['aantal'];
    }
    return $telling;
}
