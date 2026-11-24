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
// De abonnementen (feedback 2.0, november 2026): het Sporty Abonnement en Incidenteel (dan zijn vaste dagen niet verplicht)
const AANVRAAG_ABONNEMENTEN = ABONNEMENTEN;

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
            'school' => $tekst($k['school'] ?? '', 120),
            'sportvereniging' => $tekst($k['sportvereniging'] ?? '', 120),
            'abonnement' => (string) ($k['abonnement'] ?? ''),
            'dagen' => array_values(array_intersect([1, 2, 3, 4, 5], array_map('intval', (array) ($k['dagen'] ?? [])))),
            'toelichting' => $tekst($k['toelichting'] ?? '', 500),
            'allergieen' => $tekst($k['allergieen'] ?? '', 500),
        ];
        $wie = $kind['voornaam'] !== '' ? $kind['voornaam'] : t('kind ', 'child ') . ($i + 1);
        if (!geldige_naam($kind['voornaam'])) {
            $fouten[$veld('voornaam')] = t('Vul de voornaam van je kind in', "Fill in your child's first name") . ($kind['voornaam'] !== '' ? t(' (zonder links of e-mailadressen)', ' (without links or email addresses)') : '') . '.';
        }
        if (!geldige_naam($kind['achternaam'])) {
            $fouten[$veld('achternaam')] = t("Vul de achternaam van {$wie} in", "Fill in {$wie}'s surname") . ($kind['achternaam'] !== '' ? t(' (zonder links of e-mailadressen)', ' (without links or email addresses)') : '') . '.';
        }
        if ($kind['voorletters'] === '') {
            $fouten[$veld('voorletters')] = t("Vul de voorletters van {$wie} in.", "Fill in {$wie}'s initials.");
        }
        if (!array_key_exists($kind['geslacht'], AANVRAAG_GESLACHT_KIND)) {
            $fouten[$veld('geslacht')] = t("Kies jongen, meisje of anders voor {$wie}.", "Choose boy, girl or other for {$wie}.");
        }
        if (!geldige_datum($kind['geboortedatum']) || $kind['geboortedatum'] > vandaag() || $kind['geboortedatum'] < date('Y-m-d', strtotime('-16 years'))) {
            $fouten[$veld('geboortedatum')] = t("Vul een geldige geboortedatum in voor {$wie}.", "Fill in a valid date of birth for {$wie}.");
        }
        if ($kind['startdatum'] === '') {
            $fouten[$veld('startdatum')] = t("Kies de gewenste startdatum voor {$wie}.", "Choose the preferred start date for {$wie}.");
        } elseif (!geldige_datum($kind['startdatum']) || $kind['startdatum'] < vandaag()) {
            $fouten[$veld('startdatum')] = t('Kies een startdatum vanaf vandaag.', 'Choose a start date from today onwards.');
        }
        if ($kind['groep_klas'] === '') {
            $fouten[$veld('groep_klas')] = t("Vul de groep of klas van {$wie} in.", "Fill in {$wie}'s school year (groep) or class.");
        }
        if ($kind['school'] === '') {
            $fouten[$veld('school')] = t("Vul de school van {$wie} in.", "Fill in {$wie}'s school.");
        }
        if (!array_key_exists($kind['abonnement'], AANVRAAG_ABONNEMENTEN)) {
            $kind['abonnement'] = array_key_first(AANVRAAG_ABONNEMENTEN);
        }
        if (!$kind['dagen'] && $kind['abonnement'] !== 'incidenteel') {
            $fouten[$veld('dagen') . '[]'] = t("Kies minstens één dag voor {$wie}.", "Choose at least one day for {$wie}.");
        } elseif ($kind['dagen'] && array_diff($kind['dagen'], open_weekdagen())) {
            $fouten[$veld('dagen') . '[]'] = t('Tot de zomervakantie zijn we alleen open op ' . weekdagen_tekst(open_weekdagen()) . '. Wil je een andere dag? Zet het bij de toelichting.', 'Until the summer holiday we are only open on ' . weekdagen_tekst(open_weekdagen()) . '. Would you like a different day? Mention it in the notes.');
        }
        $kinderen[] = $kind;
    }
    if (!$kinderen) {
        $fouten['kinderen[0][voornaam]'] = t('Vul de gegevens van je kind in.', "Fill in your child's details.");
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
    if (!geldige_naam($ouder['voornaam'])) {
        $fouten['voornaam'] = t('Vul je voornaam in', 'Fill in your first name') . ($ouder['voornaam'] !== '' ? t(' (zonder links of e-mailadressen)', ' (without links or email addresses)') : '') . '.';
    }
    if (!geldige_naam($ouder['achternaam'])) {
        $fouten['achternaam'] = t('Vul je achternaam in', 'Fill in your surname') . ($ouder['achternaam'] !== '' ? t(' (zonder links of e-mailadressen)', ' (without links or email addresses)') : '') . '.';
    }
    if (!geldige_postcode($ouder['postcode'])) {
        $fouten['postcode'] = t('Vul een geldige postcode in, bijvoorbeeld 1098 TZ.', 'Fill in a valid Dutch postcode, for example 1098 TZ.');
    }
    if ($ouder['huisnummer'] === '') {
        $fouten['huisnummer'] = t('Vul je huisnummer in.', 'Fill in your house number.');
    }
    if ($ouder['straat'] === '') {
        $fouten['straat'] = t('Vul je straat in.', 'Fill in your street.');
    }
    if ($ouder['plaats'] === '') {
        $fouten['plaats'] = t('Vul je woonplaats in.', 'Fill in your town or city.');
    }
    if (!geldig_email($ouder['email'])) {
        $fouten['email'] = $ouder['email'] === '' ? t('Vul je e-mailadres in, dan sturen we je een bevestiging.', "Fill in your email address and we'll send you a confirmation.") : t('Dit lijkt geen geldig e-mailadres. Controleer het even, bijvoorbeeld naam@voorbeeld.nl.', "That doesn't look like a valid email address. Please check it, for example name@example.com.");
    }
    // Een bekend e-mailadres geeft hier bewust geen foutmelding: zie aanvraag.php (geen accounts uitproberen)
    if (!geldig_telefoon($ouder['telefoon'])) {
        $fouten['telefoon'] = $ouder['telefoon'] === '' ? t('Vul je telefoonnummer in, dan kunnen we je bellen.', 'Fill in your phone number so we can call you.') : t('Vul een geldig telefoonnummer in, bijvoorbeeld 06 12 34 56 78.', 'Fill in a valid phone number, for example +31 6 12 34 56 78.');
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
        if (!geldige_naam($partner['voornaam'])) {
            $fouten['partner_voornaam'] = t('Vul de voornaam van je partner in', "Fill in your partner's first name") . ($partner['voornaam'] !== '' ? t(' (zonder links of e-mailadressen)', ' (without links or email addresses)') : '') . '.';
        }
        if (!geldige_naam($partner['achternaam'])) {
            $fouten['partner_achternaam'] = t('Vul de achternaam van je partner in', "Fill in your partner's surname") . ($partner['achternaam'] !== '' ? t(' (zonder links of e-mailadressen)', ' (without links or email addresses)') : '') . '.';
        }
        if ($partner['email'] !== '' && !geldig_email($partner['email'])) {
            $fouten['partner_email'] = t('Dit lijkt geen geldig e-mailadres.', "That doesn't look like a valid email address.");
        }
        if ($partner['telefoon'] !== '' && !geldig_telefoon($partner['telefoon'])) {
            $fouten['partner_telefoon'] = t('Vul een geldig telefoonnummer in, bijvoorbeeld 06 12 34 56 78.', 'Fill in a valid phone number, for example +31 6 12 34 56 78.');
        }
    } elseif (($post['partner'] ?? '') !== 'nee') {
        $fouten['partner'] = t('Kies of je de gegevens van je partner wilt toevoegen.', "Choose whether you'd like to add your partner's details.");
    }

    foreach ([
        'akkoord_privacy' => t('Vink aan dat je akkoord gaat met de registratie van je gegevens volgens de privacyverklaring.', 'Tick the box to agree to us recording your details as described in the privacy statement.'),
        'akkoord_voorwaarden' => t('Vink aan dat je akkoord gaat met de algemene voorwaarden.', 'Tick the box to agree to the terms and conditions.'),
        'akkoord_correct' => t('Vink aan dat de gegevens kloppen.', 'Tick the box to confirm your details are correct.'),
    ] as $vinkje => $melding) {
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
            'partner' => $aanvraag['partner'], 'verstuurd_op' => nu(), 'taal' => taal(),
        ];
        q("INSERT INTO gebruikers (rol, status, naam, email, telefoon, straat, postcode, plaats, contactvoorkeur, wachtwoord_hash, gewenste_dagen, gewenste_startdatum, opmerkingen, aanvraag, aangemaakt_op)
           VALUES ('ouder', 'nieuw', ?, ?, ?, ?, ?, ?, '', NULL, ?, ?, ?, ?, ?)", [
            aanvraag_naam($ouder), $ouder['email'], $ouder['telefoon'], trim($ouder['straat'] . ' ' . $ouder['huisnummer']), normaliseer_postcode($ouder['postcode']), $ouder['plaats'],
            implode(',', $dagen), $starts ? min($starts) : null, versleutel($ouder['opmerkingen']), versleutel(json_encode($extra, JSON_UNESCAPED_UNICODE)), nu(),
        ]);
        $ouderId = laatste_id();
        foreach ($aanvraag['kinderen'] as $kind) {
            $extraKind = array_intersect_key($kind, array_flip(['voorletters', 'geslacht', 'startdatum', 'groep_klas', 'sportvereniging', 'abonnement', 'dagen', 'toelichting']));
            // Het gekozen abonnement staat meteen klaar voor de facturen; het team controleert het bij de kennismaking
            // (een ouder met status "nieuw" krijgt nog geen factuur, zie inc/facturen.php)
            q('INSERT INTO kinderen (ouder_id, voornaam, achternaam, geboortedatum, school, bijzonderheden, foto_toestemming, aanvraag, abonnement, vaste_dagen, abonnement_vanaf, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)', [
                $ouderId, $kind['voornaam'], trim($kind['tussenvoegsel'] . ' ' . $kind['achternaam']), $kind['geboortedatum'], $kind['school'],
                versleutel($kind['allergieen']), versleutel(json_encode($extraKind, JSON_UNESCAPED_UNICODE)),
                array_key_exists($kind['abonnement'], ABONNEMENTEN) ? $kind['abonnement'] : '', implode(',', $kind['dagen']), $kind['startdatum'] !== '' ? $kind['startdatum'] : null, nu(),
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
