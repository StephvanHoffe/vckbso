<?php
/*
 * Open sollicitaties via de website (werken-bij.html, verstuurd naar solliciteren.php).
 * Het cv staat versleuteld in de datamap (sollicitaties/), de motivatie, de link en de
 * notities van het team versleuteld in de database. Alleen teamleden met het recht
 * "Sollicitaties" zien ze (sollicitaties.php).
 *
 * Bewaren (zoals in de privacyverklaring):
 * - afgerond (geen plek of aangenomen): 4 weken daarna weg;
 * - geen plek, maar toestemming om te bewaren: een jaar na binnenkomst weg;
 * - nog niet afgehandeld: na drie maanden weg (met toestemming na een jaar).
 * Elke sollicitatie heeft daarvoor een datum verwijderen_op; het bewaarbeleid ruimt dagelijks op.
 */

declare(strict_types=1);

const SOLLICITATIE_STATUSSEN = [
    'nieuw' => 'Nieuw',
    'gesprek' => 'Uitgenodigd',
    'afgewezen' => 'Geen plek',
    'aangenomen' => 'Aangenomen',
];

/** De keuzes op de website (werken-bij.html); iets anders wordt "Iets anders". */
const SOLLICITATIE_FUNCTIES = [
    'Begeleider op de groep',
    'Sport en spel begeleiden',
    'Stage lopen',
    'Invallen als het nodig is',
    'Iets anders, dat vertel ik hieronder',
];

const SOLLICITATIE_DAGEN = ['maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag'];

const CV_MAX_BYTES = 5 * 1024 * 1024;

/** Toegestane soorten cv: type => extensie. */
const CV_TYPES = [
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
];

/** Spamrem: zoveel sollicitaties per kwartier vanaf hetzelfde adres. */
const MAX_SOLLICITATIES_PER_KWARTIER = 5;

function cv_map(): string
{
    $map = data_dir() . '/sollicitaties';
    if (!is_dir($map)) {
        mkdir($map, 0700, true);
    }
    return $map;
}

/**
 * Het soort cv, bepaald aan de inhoud (en bij Word aan de naam, omdat niet elke server
 * een .docx herkent). Geeft een sleutel uit CV_TYPES terug, of null.
 */
function cv_type(string $pad, string $naam): ?string
{
    $type = (new finfo(FILEINFO_MIME_TYPE))->file($pad) ?: '';
    $extensie = strtolower(pathinfo($naam, PATHINFO_EXTENSION));
    if (isset(CV_TYPES[$type])) {
        return $type;
    }
    // .docx is een zip-bestand met een Word-document erin
    if ($extensie === 'docx' && in_array($type, ['application/zip', 'application/octet-stream'], true)
        && str_contains((string) file_get_contents($pad, false, null, 0, 1024 * 1024), 'word/')) {
        return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }
    // .doc is een ouder Office-bestand
    if ($extensie === 'doc' && in_array($type, ['application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office'], true)) {
        return 'application/msword';
    }
    return null;
}

/** Wat er mis is met een geüpload cv (melding voor de sollicitant), of null als het goed is. */
function cv_fout(array $upload): ?string
{
    $teGroot = 'Dit bestand is groter dan 5 MB. Maak het wat kleiner, of stuur het later per mail.';
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return in_array($upload['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? $teGroot : 'Je cv kon niet worden geüpload. Probeer het nog eens.';
    }
    if ($upload['size'] > CV_MAX_BYTES) {
        return $teGroot;
    }
    if (cv_type($upload['tmp_name'], (string) ($upload['name'] ?? '')) === null) {
        return 'Kies een pdf- of Word-bestand (.pdf, .doc of .docx).';
    }
    return null;
}

/**
 * Slaat een geüpload cv versleuteld op. Geeft [bestandsnaam, originele naam, type, grootte]
 * terug, of gooit een RuntimeException met een melding voor de sollicitant.
 */
function bewaar_cv(array $upload): array
{
    if (($fout = cv_fout($upload)) !== null) {
        throw new RuntimeException($fout);
    }
    $type = (string) cv_type($upload['tmp_name'], (string) ($upload['name'] ?? ''));
    $inhoud = (string) file_get_contents($upload['tmp_name']);
    $bestand = bin2hex(random_bytes(16));
    versleutel_bestand(cv_map() . '/' . $bestand . '.bin', $inhoud);
    $origineel = preg_replace('/[^\p{L}\p{N} ._-]/u', '', basename((string) ($upload['name'] ?? ''))) ?: 'cv';
    $origineel = mb_substr(pathinfo($origineel, PATHINFO_FILENAME), 0, 80) . '.' . CV_TYPES[$type];
    return [$bestand, $origineel, $type, strlen($inhoud)];
}

function verwijder_cv(?string $bestand): void
{
    if ($bestand && preg_match('/^[a-f0-9]{32}$/', $bestand) && is_file(cv_map() . '/' . $bestand . '.bin')) {
        unlink(cv_map() . '/' . $bestand . '.bin');
    }
}

/** Wanneer een sollicitatie vanzelf wordt verwijderd (zie de uitleg bovenaan). */
function sollicitatie_verwijderdatum(string $status, bool $bewaren, string $aangemaakt, ?string $afgerond = null): string
{
    $binnen = strtotime($aangemaakt);
    if ($status === 'afgewezen' && $bewaren) {
        return date('Y-m-d H:i:s', strtotime('+1 year', $binnen));
    }
    if (in_array($status, ['afgewezen', 'aangenomen'], true)) {
        return date('Y-m-d H:i:s', strtotime('+4 weeks', $afgerond ? strtotime($afgerond) : time()));
    }
    return date('Y-m-d H:i:s', strtotime($bewaren ? '+1 year' : '+3 months', $binnen));
}

function sollicitatie_badge(string $status): string
{
    $klasse = ['nieuw' => 'nieuw', 'gesprek' => 'wachtlijst', 'afgewezen' => 'ziek', 'aangenomen' => 'bevestigd'][$status] ?? 'nieuw';
    return '<span class="badge badge--' . $klasse . '">' . e(SOLLICITATIE_STATUSSEN[$status] ?? $status) . '</span>';
}

/** Verwijdert een sollicitatie met het cv. $registreer = true bij handmatig verwijderen (komt dan niet terug via een back-up). */
function verwijder_sollicitatie(array $sollicitatie, string $reden, bool $registreer = true): void
{
    verwijder_cv($sollicitatie['cv_bestand'] ?? null);
    q('DELETE FROM sollicitaties WHERE id = ?', [$sollicitatie['id']]);
    if ($registreer) {
        registreer_verwijdering('sollicitatie', (int) $sollicitatie['id'], $reden);
    }
    log_actie('Sollicitatie verwijderd', 'sollicitatie ' . $sollicitatie['id'] . ' (' . $reden . ')', null, $registreer ? 'wijziging' : 'bewaarbeleid', 'sollicitatie:' . $sollicitatie['id']);
}

/** Aantal sollicitaties dat nog op een reactie wacht (voor het overzicht Vandaag). */
function open_sollicitaties(): int
{
    return (int) waarde("SELECT COUNT(*) FROM sollicitaties WHERE status = 'nieuw'");
}
