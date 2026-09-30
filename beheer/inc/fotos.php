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
        $magZien = waarde('SELECT 1 FROM foto_kinderen fk JOIN kinderen k ON k.id = fk.kind_id WHERE fk.foto_id = ? AND ' . groep_voorwaarde('k.groep_id'), [$fotoId])
            || (is_beheerder($gebruiker));
    } else {
        $magZien = waarde('SELECT 1 FROM foto_kinderen fk JOIN kind_verzorgers v ON v.kind_id = fk.kind_id WHERE fk.foto_id = ? AND v.gebruiker_id = ? AND v.recht_fotos = 1', [$fotoId, $gebruiker['id']]);
    }
    if (!$magZien) {
        niet_gevonden();
    }
    return $foto;
}

