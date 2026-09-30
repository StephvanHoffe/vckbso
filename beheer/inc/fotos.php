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
    imagejpeg($groot, foto_map() . '/' . $relatief . '.jpg', 82);
    imagejpeg($klein, foto_map() . '/' . $relatief . '_klein.jpg', 78);
    return [$relatief, imagesx($groot), imagesy($groot)];
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

/** Mag de ingelogde gebruiker deze foto zien? Team: ja. Ouder: als er een eigen kind op staat. */
function foto_met_toegang(int $fotoId): array
{
    $gebruiker = vereis_login();
    $foto = rij('SELECT * FROM fotos WHERE id = ?', [$fotoId]);
    if (!$foto) {
        niet_gevonden();
    }
    if (!is_team($gebruiker)) {
        $magZien = waarde('SELECT 1 FROM foto_kinderen fk JOIN kinderen k ON k.id = fk.kind_id WHERE fk.foto_id = ? AND k.ouder_id = ?', [$fotoId, $gebruiker['id']]);
        if (!$magZien) {
            niet_gevonden();
        }
    }
    return $foto;
}

/**
 * Wist de gegevens van een gestopte ouder en de kinderen (recht op vergetelheid).
 * Naam, adres en facturen blijven bewaard vanwege de fiscale bewaarplicht (7 jaar).
 */
function wis_oudergegevens(array $ouder): void
{
    transactie(function () use ($ouder) {
        $kindIds = array_map('intval', array_column(rijen('SELECT id FROM kinderen WHERE ouder_id = ?', [$ouder['id']]), 'id'));
        if ($kindIds) {
            $lijst = implode(',', $kindIds);
            // Foto's waar na het wissen geen enkel ander kind meer aan gekoppeld is
            $wezen = rijen("SELECT f.* FROM fotos f WHERE EXISTS (SELECT 1 FROM foto_kinderen fk WHERE fk.foto_id = f.id AND fk.kind_id IN ($lijst))
                            AND NOT EXISTS (SELECT 1 FROM foto_kinderen fk WHERE fk.foto_id = f.id AND fk.kind_id NOT IN ($lijst))");
            foreach ($wezen as $foto) {
                verwijder_fotobestanden($foto['bestand']);
                q('DELETE FROM fotos WHERE id = ?', [$foto['id']]);
            }
            q("DELETE FROM kinderen WHERE id IN ($lijst)"); // inschrijvingen, observaties en fotokoppelingen gaan mee
        }
        q('DELETE FROM berichten WHERE ouder_id = ?', [$ouder['id']]);
        q('DELETE FROM tokens WHERE gebruiker_id = ?', [$ouder['id']]);
        q("UPDATE gebruikers SET email = ?, telefoon = '', wachtwoord_hash = NULL, contactvoorkeur = '', gewenste_dagen = '', gewenste_startdatum = NULL,
           opmerkingen = '', mandaat_naam = '', mandaat_rekening = '' WHERE id = ?", ['gewist-' . $ouder['id'] . '@bsovck.invalid', $ouder['id']]);
    });
    log_actie('Gegevens gewist (AVG)', 'ouder #' . $ouder['id']);
}
