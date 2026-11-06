<?php
/*
 * Foto's opslaan. Elke foto wordt opnieuw opgeslagen als JPEG: daarmee
 * verdwijnen verborgen gegevens zoals de GPS-locatie (EXIF) uit het bestand.
 */

declare(strict_types=1);

const FOTO_MAX_BYTES = 15 * 1024 * 1024;
const FOTO_MAX_ZIJDE = 2000;
const FOTO_KLEIN_BREEDTE = 600;

function foto_map(): string
{
    $map = data_dir() . '/fotos';
    if (!is_dir($map)) {
        mkdir($map, 0770, true);
    }
    return $map;
}

/**
 * Slaat een geüploade foto op. Geeft [bestandsnaam, breedte, hoogte] terug,
 * of gooit een RuntimeException met een foutmelding voor de gebruiker.
 */
function bewaar_foto(array $upload): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(match ($upload['error'] ?? 0) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'is te groot.',
            UPLOAD_ERR_NO_FILE => 'is niet meegestuurd.',
            default => 'kon niet worden geüpload.',
        });
    }
    if ($upload['size'] > FOTO_MAX_BYTES) {
        throw new RuntimeException('is groter dan 15 MB.');
    }
    $info = @getimagesize($upload['tmp_name']);
    $type = $info[2] ?? 0;
    if ($info && $info[0] * $info[1] > 50_000_000) {
        throw new RuntimeException('heeft te veel pixels (maximaal 50 megapixel).');
    }
    $bron = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($upload['tmp_name']),
        IMAGETYPE_PNG => @imagecreatefrompng($upload['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($upload['tmp_name']),
        default => false,
    };
    if (!$bron) {
        throw new RuntimeException('is geen JPG-, PNG- of WebP-foto. (Foto van een iPhone? Kies dan bij het delen "Meest compatibel" of stuur hem als JPG.)');
    }

    // Telefoonfoto's rechtop zetten volgens de EXIF-oriëntatie
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($upload['tmp_name']);
        $draai = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($draai !== 0) {
            $gedraaid = imagerotate($bron, $draai, 0);
            if ($gedraaid) {
                imagedestroy($bron);
                $bron = $gedraaid;
            }
        }
    }

    $groot = verklein($bron, FOTO_MAX_ZIJDE);
    $klein = verklein($bron, FOTO_KLEIN_BREEDTE, true);

    $relatief = date('Y/m/') . bin2hex(random_bytes(16));
    $map = foto_map() . '/' . date('Y/m');
    if (!is_dir($map)) {
        mkdir($map, 0770, true);
    }
    versleutel_bestand(foto_map() . '/' . $relatief . '.jpg', jpeg_inhoud($groot, 82));
    versleutel_bestand(foto_map() . '/' . $relatief . '_klein.jpg', jpeg_inhoud($klein, 78));
    return [$relatief, imagesx($groot), imagesy($groot)];
}

function jpeg_inhoud(GdImage $beeld, int $kwaliteit): string
{
    ob_start();
    imagejpeg($beeld, null, $kwaliteit);
    return (string) ob_get_clean();
}

/** Verkleint tot maximaal $max pixels (langste zijde, of alleen de breedte). */
function verklein(GdImage $bron, int $max, bool $alleenBreedte = false): GdImage
{
    $b = imagesx($bron);
    $h = imagesy($bron);
    $factor = $alleenBreedte ? $max / $b : $max / max($b, $h);
    if ($factor >= 1) {
        $kopie = imagecreatetruecolor($b, $h);
        imagefill($kopie, 0, 0, imagecolorallocate($kopie, 255, 255, 255));
        imagecopy($kopie, $bron, 0, 0, 0, 0, $b, $h);
        return $kopie;
    }
    $nb = max(1, (int) round($b * $factor));
    $nh = max(1, (int) round($h * $factor));
    $doel = imagecreatetruecolor($nb, $nh);
    imagefill($doel, 0, 0, imagecolorallocate($doel, 255, 255, 255)); // transparante PNG's op wit
    imagecopyresampled($doel, $bron, 0, 0, 0, 0, $nb, $nh, $b, $h);
    return $doel;
}

function verwijder_fotobestanden(string $relatief): void
{
    if (!preg_match('#^\d{4}/\d{2}/[a-f0-9]{32}$#', $relatief)) {
        return;
    }
    foreach (['.jpg', '_klein.jpg'] as $achtervoegsel) {
        $pad = foto_map() . '/' . $relatief . $achtervoegsel;
        if (is_file($pad)) {
            unlink($pad);
        }
    }
}

/** Mag de ingelogde gebruiker deze foto zien? Team: als er een kind uit de eigen groepen op staat. Verzorger: met recht op foto's van een kind op de foto. */
function foto_met_toegang(int $fotoId): array
{
    $gebruiker = vereis_login();
    $foto = rij('SELECT * FROM fotos WHERE id = ?', [$fotoId]);
    if (!$foto) {
        niet_gevonden();
    }
    if (is_team($gebruiker)) {
        if (!team_mag('fotos', $gebruiker) && !team_mag('kinderen', $gebruiker)) {
            niet_gevonden();
        }
        $magZien = waarde('SELECT 1 FROM foto_kinderen fk JOIN kinderen k ON k.id = fk.kind_id WHERE fk.foto_id = ? AND ' . groep_voorwaarde('k.groep_id'), [$fotoId])
            || team_groep_ids($gebruiker) === null;
    } else {
        $magZien = waarde('SELECT 1 FROM foto_kinderen fk JOIN kind_verzorgers v ON v.kind_id = fk.kind_id WHERE fk.foto_id = ? AND v.gebruiker_id = ? AND v.recht_fotos = 1', [$fotoId, $gebruiker['id']]);
    }
    if (!$magZien) {
        niet_gevonden();
    }
    return $foto;
}

/* ---------- Toestemming per kind ---------- */

/** De drie keuzes voor foto's van een kind, met de uitleg. {naam} wordt de voornaam. */
const FOTO_KEUZES = [
    'groep' => ["Ja, ook op groepsfoto's", "Staat {naam} samen met andere kinderen op een foto? Dan krijgen hun ouders die foto ook."],
    'eigen' => ["Ja, maar alleen foto's waar {naam} alleen op staat", "Die foto's krijg alleen jij (en wie je toegang geeft tot de foto's van {naam})."],
    'geen' => ["Nee, liever geen foto's", "De begeleiders delen geen foto's van {naam} in Mijn BSO."],
];

/** Wat is er voor dit kind gekozen: 'groep', 'eigen', 'geen', of null als het nog niet is ingevuld. */
function foto_keuze(array $kind): ?string
{
    if (!array_key_exists('fotos_toegestaan', $kind) || $kind['fotos_toegestaan'] === null) {
        return null;
    }
    if (!(int) $kind['fotos_toegestaan']) {
        return 'geen';
    }
    return (int) $kind['foto_toestemming'] ? 'groep' : 'eigen';
}

/** Mogen de begeleiders een foto van dit kind delen? */
function mag_op_foto(array $kind): bool
{
    return in_array(foto_keuze($kind), ['groep', 'eigen'], true);
}

/** Korte tekst voor overzichten, bijvoorbeeld "Geen foto's". */
function foto_keuze_kort(?string $keuze): string
{
    return match ($keuze) {
        'groep' => "Foto's: ook groepsfoto's",
        'eigen' => "Alleen eigen foto's",
        'geen' => "Geen foto's",
        default => "Foto's: nog niet ingevuld",
    };
}

/** De kolommen die bij een keuze horen. */
function foto_keuze_velden(string $keuze): array
{
    return [
        'fotos_toegestaan' => $keuze === 'geen' ? 0 : 1,
        'foto_toestemming' => $keuze === 'groep' ? 1 : 0,
        'fotos_toegestaan_op' => nu(),
    ];
}

/** Keuze uit het formulier: een geldige keuze, of null als er niets (geldigs) is gekozen. */
function foto_keuze_invoer(): ?string
{
    $keuze = invoer('fotos');
    return isset(FOTO_KEUZES[$keuze]) ? $keuze : null;
}

/** De vraag als keuzerondjes. */
function foto_keuze_veld(string $voornaam, ?string $huidig, array $fouten = [], string $extraHint = ''): string
{
    $naam = $voornaam !== '' ? $voornaam : 'je kind';
    $html = '<fieldset class="field fotokeuze"' . (isset($fouten['fotos']) ? ' aria-describedby="fotos-fout"' : '') . '>'
        . '<legend class="legend-label">Mogen we foto\'s van ' . e($naam) . ' delen?' . $extraHint . '</legend>'
        . veldfout($fouten, 'fotos')
        . '<div class="choices choices--stapel">';
    foreach (FOTO_KEUZES as $waarde => [$label, $uitleg]) {
        $id = 'fotos-' . $waarde;
        $html .= '<label class="choice choice--uitleg" for="' . $id . '"><input type="radio" id="' . $id . '" name="fotos" value="' . $waarde . '"'
            . ($huidig === $waarde ? ' checked' : '') . ' aria-describedby="' . $id . '-uitleg"><span><strong>' . e(str_replace('{naam}', $naam, $label)) . '</strong>'
            . '<span class="choice__uitleg" id="' . $id . '-uitleg">' . e(str_replace('{naam}', $naam, $uitleg)) . '</span></span></label>';
    }
    return $html . '</div></fieldset>';
}

/**
 * Minder toestemming dan eerst? Dan de foto's weghalen die daar niet meer bij passen, als de ouder dat wil:
 * bij 'geen' alle foto's van het kind, bij 'eigen' de foto's waar ook andere kinderen op staan.
 * Geeft het aantal verwijderde foto's terug.
 */
function verwijder_fotos_na_intrekken(int $kindId, string $keuze, string $reden): int
{
    $sql = 'SELECT f.* FROM fotos f JOIN foto_kinderen fk ON fk.foto_id = f.id WHERE fk.kind_id = ?';
    if ($keuze === 'eigen') {
        $sql .= ' AND EXISTS (SELECT 1 FROM foto_kinderen ander WHERE ander.foto_id = f.id AND ander.kind_id != fk.kind_id)';
    } elseif ($keuze !== 'geen') {
        return 0;
    }
    $fotos = rijen($sql, [$kindId]);
    foreach ($fotos as $foto) {
        verwijder_foto($foto);
        registreer_verwijdering('foto', (int) $foto['id'], $reden);
    }
    return count($fotos);
}

/** Hoeveel foto's van dit kind zouden verdwijnen bij deze keuze? */
function fotos_bij_intrekken(int $kindId): array
{
    return [
        'geen' => (int) waarde('SELECT COUNT(*) FROM foto_kinderen WHERE kind_id = ?', [$kindId]),
        'eigen' => (int) waarde('SELECT COUNT(*) FROM foto_kinderen fk WHERE fk.kind_id = ? AND EXISTS (SELECT 1 FROM foto_kinderen ander WHERE ander.foto_id = fk.foto_id AND ander.kind_id != fk.kind_id)', [$kindId]),
    ];
}
