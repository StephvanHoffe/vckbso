<?php
/*
 * Financiën: inkomsten en uitgaven.
 *
 * Inkomsten zijn de betaalde facturen van ouders (automatisch, op de datum van betaling)
 * plus wat je zelf invoert (bijvoorbeeld een subsidie). Uitgaven voer je zelf in, met
 * eventueel een bonnetje (pdf of foto, versleuteld opgeslagen). Het overzicht rekent op
 * kasbasis: wat er in een periode binnenkwam en wat eruit ging.
 *
 * Boekingen en bonnetjes horen bij de financiële administratie: ze blijven even lang
 * bewaard als de facturen (bewaar_financieel_jaren, minimaal 7 jaar).
 */

declare(strict_types=1);

const UITGAVE_CATEGORIEEN = [
    'personeel' => 'Personeel',
    'huur' => 'Huur en energie',
    'eten' => 'Eten en drinken',
    'materiaal' => 'Materialen en spel',
    'activiteiten' => 'Uitjes en activiteiten',
    'vervoer' => 'Vervoer',
    'verzekeringen' => 'Verzekeringen',
    'administratie' => 'Administratie, software en bank',
    'opleiding' => 'Opleiding',
    'overig' => 'Overige uitgaven',
];

const INKOMST_CATEGORIEEN = [
    'ouderbijdrage' => 'Ouderbijdragen buiten Mijn BSO',
    'subsidie' => 'Subsidie',
    'vergoeding' => 'Vergoedingen',
    'overig' => 'Overige inkomsten',
];

/** De vaste categorie voor betaalde facturen (niet zelf te kiezen). */
const CATEGORIE_FACTUREN = 'Ouderbijdragen (facturen)';

const BIJLAGE_MAX_BYTES = 10 * 1024 * 1024;
const BIJLAGE_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

/**
 * Bedrag zoals mensen het typen, naar centen: "12,50", "12.50", "1.234,56", "12500" of "€ 12.500".
 * Null als het geen geldig bedrag is (of 0 of meer dan € 10 miljoen).
 */
function bedrag_naar_cent(string $tekst): ?int
{
    $s = str_replace(['€', ' ', "\u{00A0}"], '', trim($tekst));
    if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $s) || preg_match('/^\d+(,\d{1,2})?$/', $s)) {
        $s = str_replace(['.', ','], ['', '.'], $s); // 1.234,56 of 1234,56
    } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $s)) { // 1234.56
        return null;
    }
    $cent = (int) round(((float) $s) * 100);
    return $cent > 0 && $cent <= 1000000000 ? $cent : null;
}

function categorie_label(string $soort, string $categorie): string
{
    $lijst = $soort === 'inkomst' ? INKOMST_CATEGORIEEN : UITGAVE_CATEGORIEEN;
    return $lijst[$categorie] ?? $categorie;
}

function bijlage_map(): string
{
    $map = data_dir() . '/bijlagen';
    if (!is_dir($map)) {
        mkdir($map, 0700, true);
    }
    return $map;
}

/**
 * Slaat een geüpload bonnetje versleuteld op. Geeft [bestandsnaam, originele naam, type]
 * terug, of gooit een RuntimeException met een melding voor de gebruiker.
 */
function bewaar_bijlage(array $upload): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(match ($upload['error'] ?? 0) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Het bestand is te groot (maximaal 10 MB).',
            default => 'Het bestand kon niet worden geüpload. Probeer het opnieuw.',
        });
    }
    if ($upload['size'] > BIJLAGE_MAX_BYTES) {
        throw new RuntimeException('Het bestand is te groot (maximaal 10 MB).');
    }
    // Het type bepalen we aan de inhoud, niet aan de naam
    $type = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']) ?: '';
    if (!isset(BIJLAGE_TYPES[$type])) {
        throw new RuntimeException('Kies een pdf, jpg of png.');
    }
    $inhoud = (string) file_get_contents($upload['tmp_name']);
    $naam = bin2hex(random_bytes(16));
    versleutel_bestand(bijlage_map() . '/' . $naam . '.bin', $inhoud);
    $origineel = preg_replace('/[^\p{L}\p{N} ._-]/u', '', basename((string) ($upload['name'] ?? ''))) ?: 'bonnetje';
    $origineel = mb_substr(pathinfo($origineel, PATHINFO_FILENAME), 0, 80) . '.' . BIJLAGE_TYPES[$type];
    return [$naam, $origineel, $type];
}

function verwijder_bijlage(?string $naam): void
{
    if ($naam && preg_match('/^[a-f0-9]{32}$/', $naam) && is_file(bijlage_map() . '/' . $naam . '.bin')) {
        unlink(bijlage_map() . '/' . $naam . '.bin');
    }
}

/** De jaren waarin iets geboekt of betaald is, plus het huidige jaar; nieuwste eerst. */
function financien_jaren(): array
{
    $jaren = array_map('intval', array_column(rijen(
        "SELECT DISTINCT substr(datum, 1, 4) AS jaar FROM boekingen
         UNION SELECT DISTINCT substr(betaald_op, 1, 4) FROM facturen WHERE status = 'betaald' AND betaald_op IS NOT NULL
         UNION SELECT DISTINCT substr(datum, 1, 4) FROM facturen WHERE status != 'geannuleerd'"
    ), 'jaar'));
    $jaren[] = (int) date('Y');
    $jaren = array_values(array_unique(array_filter($jaren)));
    rsort($jaren);
    return $jaren;
}

/**
 * Het overzicht voor een periode [van, tot] (datums, tot en met).
 * Bedragen in centen. Per maand alleen bij een heel jaar.
 */
function financieel_overzicht(string $van, string $tot): array
{
    $totExclusief = date('Y-m-d', strtotime($tot . ' +1 day'));
    $facturenBetaald = (int) waarde("SELECT COALESCE(SUM(bedrag_cent), 0) FROM facturen WHERE status = 'betaald' AND betaald_op >= ? AND betaald_op < ?", [$van, $totExclusief]);
    $aantalBetaald = (int) waarde("SELECT COUNT(*) FROM facturen WHERE status = 'betaald' AND betaald_op >= ? AND betaald_op < ?", [$van, $totExclusief]);
    $gefactureerd = (int) waarde("SELECT COALESCE(SUM(bedrag_cent), 0) FROM facturen WHERE status != 'geannuleerd' AND datum >= ? AND datum <= ?", [$van, $tot]);
    $openstaand = rij("SELECT COUNT(*) AS aantal, COALESCE(SUM(bedrag_cent), 0) AS bedrag FROM facturen WHERE status IN ('open', 'incasso', 'mislukt')");

    $perCategorie = ['inkomst' => [], 'uitgave' => []];
    foreach (rijen('SELECT soort, categorie, SUM(bedrag_cent) AS bedrag, COUNT(*) AS aantal FROM boekingen WHERE datum >= ? AND datum <= ? GROUP BY soort, categorie ORDER BY bedrag DESC', [$van, $tot]) as $rij) {
        $perCategorie[$rij['soort']][] = ['label' => categorie_label($rij['soort'], $rij['categorie']), 'bedrag' => (int) $rij['bedrag'], 'aantal' => (int) $rij['aantal']];
    }
    if ($facturenBetaald > 0) {
        array_unshift($perCategorie['inkomst'], ['label' => CATEGORIE_FACTUREN, 'bedrag' => $facturenBetaald, 'aantal' => $aantalBetaald]);
        usort($perCategorie['inkomst'], fn ($a, $b) => $b['bedrag'] <=> $a['bedrag']);
    }
    $inkomsten = array_sum(array_column($perCategorie['inkomst'], 'bedrag'));
    $uitgaven = array_sum(array_column($perCategorie['uitgave'], 'bedrag'));

    // Per maand (voor de grafiek en de tabel)
    $perMaand = [];
    if (substr($van, 5) === '01-01' && substr($tot, 5) === '12-31' && substr($van, 0, 4) === substr($tot, 0, 4)) {
        $jaar = substr($van, 0, 4);
        for ($m = 1; $m <= 12; $m++) {
            $perMaand[sprintf('%s-%02d', $jaar, $m)] = ['inkomsten' => 0, 'uitgaven' => 0];
        }
        foreach (rijen("SELECT substr(betaald_op, 1, 7) AS maand, SUM(bedrag_cent) AS bedrag FROM facturen WHERE status = 'betaald' AND betaald_op >= ? AND betaald_op < ? GROUP BY maand", [$van, $totExclusief]) as $rij) {
            $perMaand[$rij['maand']]['inkomsten'] += (int) $rij['bedrag'];
        }
        foreach (rijen('SELECT substr(datum, 1, 7) AS maand, soort, SUM(bedrag_cent) AS bedrag FROM boekingen WHERE datum >= ? AND datum <= ? GROUP BY maand, soort', [$van, $tot]) as $rij) {
            $perMaand[$rij['maand']][$rij['soort'] === 'inkomst' ? 'inkomsten' : 'uitgaven'] += (int) $rij['bedrag'];
        }
    }

    return [
        'inkomsten' => $inkomsten,
        'uitgaven' => $uitgaven,
        'resultaat' => $inkomsten - $uitgaven,
        'facturen_betaald' => $facturenBetaald,
        'gefactureerd' => $gefactureerd,
        'openstaand' => (int) $openstaand['bedrag'],
        'openstaand_aantal' => (int) $openstaand['aantal'],
        'per_categorie' => $perCategorie,
        'per_maand' => $perMaand,
    ];
}

/** Een mooie stap voor de y-as: 1, 2, 2,5 of 5 maal een macht van tien (in centen). */
function mooie_stap(int $max, int $stappen = 4): int
{
    if ($max <= 0) {
        return 10000; // € 100
    }
    $ruw = $max / $stappen;
    $macht = 10 ** (int) floor(log10($ruw));
    foreach ([1, 2, 2.5, 5, 10] as $factor) {
        if ($factor * $macht >= $ruw) {
            return (int) max(100, round($factor * $macht));
        }
    }
    return (int) (10 * $macht);
}

/** Bedrag zonder centen voor de as: 250000 -> "€ 2.500". */
function euro_rond(int $centen): string
{
    return '€ ' . number_format(intdiv($centen, 100), 0, ',', '.');
}

/**
 * Kolomgrafiek inkomsten en uitgaven per maand, als SVG. Hover en toetsenbord tonen per
 * maand beide bedragen (beheer.js); alle bedragen staan ook in de tabel eronder.
 */
function grafiek_per_maand(array $perMaand): string
{
    $breed = 720;
    $hoog = 260;
    $links = 64;
    $rechts = 8;
    $boven = 12;
    $onder = 28;
    $plotB = $breed - $links - $rechts;
    $plotH = $hoog - $boven - $onder;
    $max = 0;
    foreach ($perMaand as $m) {
        $max = max($max, $m['inkomsten'], $m['uitgaven']);
    }
    $stap = mooie_stap($max);
    $top = max($stap, (int) ceil($max / $stap) * $stap);
    $y = fn (int $bedrag) => $boven + $plotH - ($bedrag / $top) * $plotH;
    $band = $plotB / max(1, count($perMaand));
    $balk = min(22, ($band - 10) / 2);

    $svg = '<svg class="grafiek__svg" viewBox="0 0 ' . $breed . ' ' . $hoog . '" role="img" aria-labelledby="grafiek-titel grafiek-uitleg">';
    // Rasterlijnen en y-as
    for ($waarde = 0; $waarde <= $top; $waarde += $stap) {
        $py = round($y($waarde), 1);
        $svg .= '<line class="grafiek__raster" x1="' . $links . '" x2="' . ($breed - $rechts) . '" y1="' . $py . '" y2="' . $py . '"/>';
        $svg .= '<text class="grafiek__as" x="' . ($links - 8) . '" y="' . ($py + 4) . '" text-anchor="end">' . e(euro_rond($waarde)) . '</text>';
    }
    // Kolom met afgeronde bovenkant (4px), recht op de basislijn
    $kolom = function (float $x, int $bedrag, string $klasse) use ($y, $boven, $plotH, $balk): string {
        if ($bedrag <= 0) {
            return '';
        }
        $yTop = $y($bedrag);
        $h = $boven + $plotH - $yTop;
        $r = min(4, $h, $balk / 2);
        $x2 = $x + $balk;
        $basis = $boven + $plotH;
        $d = sprintf('M%.1f %.1fV%.1fQ%.1f %.1f %.1f %.1fH%.1fQ%.1f %.1f %.1f %.1fV%.1fZ', $x, $basis, $yTop + $r, $x, $yTop, $x + $r, $yTop, $x2 - $r, $x2, $yTop, $x2, $yTop + $r, $basis);
        return '<path class="' . $klasse . '" d="' . $d . '"/>';
    };
    $i = 0;
    foreach ($perMaand as $maand => $m) {
        $x0 = $links + $i * $band;
        $midden = $x0 + $band / 2;
        $label = ucfirst(datum_nl($maand . '-01', 'MMMM y'));
        $tip = $label . ': inkomsten ' . geld($m['inkomsten']) . ', uitgaven ' . geld($m['uitgaven']) . ', resultaat ' . geld($m['inkomsten'] - $m['uitgaven']);
        $svg .= '<g class="grafiek__maand" tabindex="0" role="img" aria-label="' . e($tip) . '" data-tip-titel="' . e($label) . '" data-tip-in="' . e(geld($m['inkomsten'])) . '" data-tip-uit="' . e(geld($m['uitgaven'])) . '">';
        $svg .= '<rect class="grafiek__vlak" x="' . round($x0, 1) . '" y="' . $boven . '" width="' . round($band, 1) . '" height="' . $plotH . '"/>';
        $svg .= $kolom($midden - $balk - 1, $m['inkomsten'], 'grafiek__kolom grafiek__kolom--in');
        $svg .= $kolom($midden + 1, $m['uitgaven'], 'grafiek__kolom grafiek__kolom--uit');
        $svg .= '<text class="grafiek__as" x="' . round($midden, 1) . '" y="' . ($hoog - 8) . '" text-anchor="middle">' . e(datum_nl($maand . '-01', 'MMM')) . '</text>';
        $svg .= '</g>';
        $i++;
    }
    $svg .= '<line class="grafiek__basis" x1="' . $links . '" x2="' . ($breed - $rechts) . '" y1="' . ($boven + $plotH) . '" y2="' . ($boven + $plotH) . '"/>';
    return $svg . '</svg>';
}
