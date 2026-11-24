<?php
/*
 * Facturen: elke maand achteraf, per ouder één factuur (antwoorden van Sporty, november 2026).
 *
 * - Sporty Abonnement (52 weken): een vast bedrag per maand, los van welke dagen het kind er echt was:
 *   aantal vaste dagen per week x 27 uur x het uurtarief (EUR 9,75). Begint of stopt het abonnement halverwege
 *   de maand, dan naar rato van het aantal kalenderdagen (TODO: met Sporty bevestigen).
 * - Incidenteel: per afgenomen dag x het uurtarief (EUR 12): 4,5 uur op een schooldag, 10,5 uur op een vakantie-
 *   of studiedag (zo'n dag zet het team in de agenda op "vakantie- of studiedag"). Dagen met status bevestigd of
 *   ziek tellen mee, en dagen die minder dan 48 uur van tevoren zijn afgemeld ook: kosteloos afmelden kan tot
 *   48 uur van tevoren.
 * - Kinderen zonder abonnement (nog niet ingesteld bij het kind) krijgen geen factuurregel; facturen.php meldt ze.
 *   Een nieuwe aanmelding (ouder met status "nieuw") krijgt nog geen factuur: eerst zet het team de ouder op actief.
 * De tarieven en uren staan bij Instellingen; btw: Sporty is vrijgesteld van btw.
 */

declare(strict_types=1);

const ABONNEMENTEN = ['sporty-52' => 'Sporty Abonnement (52 weken)', 'incidenteel' => 'Incidenteel'];

/** De tarieven en uren voor de facturen, uit Instellingen. Uren als getal (4.5), tarieven in centen. */
function factuur_tarieven(): array
{
    $getal = fn (string $sleutel, string $standaard): float => (float) str_replace(',', '.', instelling($sleutel, $standaard));
    return [
        'abonnement_cent' => (int) instelling('abonnement_uurtarief_cent', '975'),
        'abonnement_uren' => $getal('abonnement_uren_per_dag', '27'),
        'incidenteel_cent' => (int) instelling('incidenteel_uurtarief_cent', '1200'),
        'uren_schooldag' => $getal('uren_schooldag', '4,5'),
        'uren_hele_dag' => $getal('uren_hele_dag', '10,5'),
        'kosteloos_uren' => max(0, (int) instelling('kosteloos_afmelden_uren', '48')),
    ];
}

function factuur_instellingen_compleet(): bool
{
    $t = factuur_tarieven();
    return $t['abonnement_cent'] > 0 && $t['abonnement_uren'] > 0 && $t['incidenteel_cent'] > 0 && $t['uren_schooldag'] > 0 && $t['uren_hele_dag'] > 0;
}

function volgend_factuurnummer(): string
{
    $volgnummer = max(1, (int) instelling('volgend_factuurnummer', '1'));
    instelling_zet('volgend_factuurnummer', (string) ($volgnummer + 1));
    return date('Y') . '-' . str_pad((string) $volgnummer, 4, '0', STR_PAD_LEFT);
}

/** De vaste dagen van een kind als weekdagnummers (1 = maandag ... 5 = vrijdag). */
function vaste_dagen(array $kind): array
{
    return array_values(array_intersect([1, 2, 3, 4, 5], array_map('intval', array_filter(explode(',', (string) ($kind['vaste_dagen'] ?? ''))))));
}

/** Het abonnement van een kind in woorden, bijvoorbeeld "Sporty Abonnement (52 weken), dinsdag en donderdag". */
function abonnement_tekst(array $kind): string
{
    $abonnement = (string) ($kind['abonnement'] ?? '');
    if (!isset(ABONNEMENTEN[$abonnement])) {
        return 'Nog niet ingesteld';
    }
    $dagen = vaste_dagen($kind);
    $tekst = ABONNEMENTEN[$abonnement];
    if ($dagen) {
        $tekst .= ', vaste dagen: ' . weekdagen_tekst($dagen);
    }
    if (!empty($kind['abonnement_vanaf'])) {
        $tekst .= ' (vanaf ' . datum_nl((string) $kind['abonnement_vanaf']) . ')';
    }
    return $tekst;
}

/** Is een dag van een groep een vakantie- of studiedag (een hele dag open)? */
function is_hele_dag(int $groepId, string $datum): bool
{
    return (bool) waarde('SELECT hele_dag FROM groep_uitzonderingen WHERE groep_id = ? AND datum = ?', [$groepId, $datum]);
}

/** Is een afmelding te laat om kosteloos te zijn? (minder dan 48 uur voor het begin van de dag) */
function te_laat_afgemeld(array $inschrijving, bool $heleDag, int $kosteloosUren): bool
{
    $begin = strtotime($inschrijving['datum'] . ($heleDag ? ' 08:00' : ' 14:00'));
    return strtotime((string) $inschrijving['gewijzigd_op']) > $begin - $kosteloosUren * 3600;
}

/**
 * De factuurregels van één kind voor een maand: [['omschrijving', 'uren', 'prijs_cent', 'bedrag_cent'], ...]
 * plus de ids van de inschrijvingen die daarmee gefactureerd zijn.
 */
function factuurregels_kind(array $kind, string $van, string $tot, array $t): array
{
    $naam = trim($kind['voornaam'] . ' ' . $kind['achternaam']);
    $maandnaam = datum_nl($van, 'MMMM y');
    $inschrijvingen = rijen('SELECT * FROM inschrijvingen WHERE kind_id = ? AND datum BETWEEN ? AND ? AND gefactureerd = 0 ORDER BY datum', [$kind['id'], $van, $tot]);
    $regels = [];
    $ids = [];

    if ($kind['abonnement'] === 'sporty-52') {
        $dagen = vaste_dagen($kind);
        $begin = max($van, (string) ($kind['abonnement_vanaf'] ?: $van));
        $eind = min($tot, $kind['gestopt_op'] ? substr((string) $kind['gestopt_op'], 0, 10) : $tot);
        if ($dagen && $begin <= $eind) {
            $dagenInMaand = (int) date('t', strtotime($van));
            $gedekt = (int) round((strtotime($eind) - strtotime($begin)) / 86400) + 1;
            $deel = min(1, $gedekt / $dagenInMaand);
            $uren = round(count($dagen) * $t['abonnement_uren'] * $deel, 2);
            $omschrijving = sprintf('Sporty Abonnement %s, %s: %d vaste %s per week × %s uur', $naam, $maandnaam, count($dagen), count($dagen) === 1 ? 'dag' : 'dagen', uren_nl($t['abonnement_uren']));
            if ($deel < 1) {
                $omschrijving .= sprintf(' (naar rato: %d van de %d dagen)', $gedekt, $dagenInMaand);
            }
            $regels[] = ['omschrijving' => $omschrijving, 'uren' => $uren, 'prijs_cent' => $t['abonnement_cent'], 'bedrag_cent' => (int) round(count($dagen) * $t['abonnement_uren'] * $t['abonnement_cent'] * $deel)];
            // De dagen van deze maand zijn met het vaste bedrag betaald
            $ids = array_map(fn ($i) => (int) $i['id'], $inschrijvingen);
        }
        return [$regels, $ids];
    }

    if ($kind['abonnement'] === 'incidenteel') {
        $tel = ['school' => [0, 0], 'hele' => [0, 0]]; // [dagen, waarvan te laat afgemeld]
        foreach ($inschrijvingen as $i) {
            $heleDag = is_hele_dag((int) $i['groep_id'], $i['datum']);
            $soort = $heleDag ? 'hele' : 'school';
            if (in_array($i['status'], ['bevestigd', 'ziek'], true)) {
                $tel[$soort][0]++;
                $ids[] = (int) $i['id'];
            } elseif ($i['status'] === 'afgemeld' && te_laat_afgemeld($i, $heleDag, $t['kosteloos_uren'])) {
                $tel[$soort][0]++;
                $tel[$soort][1]++;
                $ids[] = (int) $i['id'];
            }
        }
        foreach (['school' => ['schooldag', 'schooldagen', $t['uren_schooldag']], 'hele' => ['vakantie- of studiedag', 'vakantie- of studiedagen', $t['uren_hele_dag']]] as $soort => [$enkel, $meer, $urenPerDag]) {
            [$aantal, $laat] = $tel[$soort];
            if ($aantal === 0) {
                continue;
            }
            $omschrijving = sprintf('Incidenteel %s, %s: %d %s × %s uur', $naam, $maandnaam, $aantal, $aantal === 1 ? $enkel : $meer, uren_nl($urenPerDag));
            if ($laat > 0) {
                $omschrijving .= sprintf(' (waarvan %d minder dan %d uur van tevoren afgemeld)', $laat, $t['kosteloos_uren']);
            }
            $uren = $aantal * $urenPerDag;
            $regels[] = ['omschrijving' => $omschrijving, 'uren' => $uren, 'prijs_cent' => $t['incidenteel_cent'], 'bedrag_cent' => (int) round($uren * $t['incidenteel_cent'])];
        }
    }
    return [$regels, $ids];
}

/** Kinderen die in een maand bij Sporty waren (of een abonnement hadden), maar nog geen abonnement hebben. */
function kinderen_zonder_abonnement(string $periode): array
{
    $van = $periode . '-01';
    $tot = date('Y-m-t', strtotime($van));
    return rijen(
        "SELECT DISTINCT k.id, k.voornaam, k.achternaam FROM kinderen k JOIN inschrijvingen i ON i.kind_id = k.id
         WHERE k.abonnement NOT IN ('sporty-52', 'incidenteel') AND k.geanonimiseerd_op IS NULL
           AND i.datum BETWEEN ? AND ? AND i.status IN ('bevestigd', 'ziek') ORDER BY k.voornaam",
        [$van, $tot]
    );
}

/**
 * Maakt de facturen voor een maand ('2026-10'). Ouders die al een factuur voor
 * die maand hebben, worden overgeslagen. Geeft [aantal, totaal in centen] terug.
 */
function maak_facturen(string $periode): array
{
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode)) {
        throw new InvalidArgumentException('Ongeldige maand.');
    }
    if (!factuur_instellingen_compleet()) {
        throw new RuntimeException('Stel eerst de tarieven en uren in bij Instellingen.');
    }
    $t = factuur_tarieven();
    $termijn = max(0, (int) instelling('betaaltermijn_dagen', '14'));
    $van = $periode . '-01';
    $tot = date('Y-m-t', strtotime($van));

    return transactie(function () use ($periode, $van, $tot, $t, $termijn) {
        $kinderen = rijen(
            "SELECT k.* FROM kinderen k JOIN gebruikers g ON g.id = k.ouder_id
             WHERE k.abonnement IN ('sporty-52', 'incidenteel') AND k.geanonimiseerd_op IS NULL AND g.status != 'nieuw'
               AND (k.actief = 1 OR k.gestopt_op >= ?)
               AND NOT EXISTS (SELECT 1 FROM facturen f WHERE f.ouder_id = k.ouder_id AND f.periode = ? AND f.status != 'geannuleerd')
             ORDER BY k.ouder_id, k.voornaam",
            [$van, $periode]
        );
        $perOuder = [];
        foreach ($kinderen as $kind) {
            [$regels, $ids] = factuurregels_kind($kind, $van, $tot, $t);
            if ($regels) {
                $perOuder[$kind['ouder_id']][] = ['kind' => $kind, 'regels' => $regels, 'ids' => $ids];
            }
        }

        $aantal = 0;
        $totaal = 0;
        foreach ($perOuder as $ouderId => $kinderenVanOuder) {
            $bedrag = array_sum(array_map(fn ($k) => array_sum(array_column($k['regels'], 'bedrag_cent')), $kinderenVanOuder));
            q('INSERT INTO facturen (nummer, ouder_id, periode, datum, vervaldatum, bedrag_cent, status, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
                volgend_factuurnummer(), $ouderId, $periode, vandaag(), date('Y-m-d', strtotime("+{$termijn} days")), $bedrag, 'open', nu(),
            ]);
            $factuurId = laatste_id();
            foreach ($kinderenVanOuder as $k) {
                foreach ($k['regels'] as $regel) {
                    q('INSERT INTO factuurregels (factuur_id, kind_id, omschrijving, aantal, eenheid, prijs_cent, bedrag_cent) VALUES (?, ?, ?, ?, ?, ?, ?)', [
                        $factuurId, $k['kind']['id'], $regel['omschrijving'], $regel['uren'], 'uur', $regel['prijs_cent'], $regel['bedrag_cent'],
                    ]);
                }
                if ($k['ids']) {
                    q('UPDATE inschrijvingen SET gefactureerd = 1 WHERE id IN (' . implode(',', array_map('intval', $k['ids'])) . ')');
                }
            }
            $aantal++;
            $totaal += $bedrag;
        }
        log_actie('Facturen gemaakt', "{$periode}: {$aantal} facturen, " . geld($totaal));
        return [$aantal, $totaal];
    });
}

function factuur_met_toegang(int $factuurId): array
{
    $gebruiker = vereis_login();
    $factuur = rij('SELECT f.*, g.naam, g.email, g.straat, g.postcode, g.plaats, g.mandaat_status, g.mandaat_rekening FROM facturen f JOIN gebruikers g ON g.id = f.ouder_id WHERE f.id = ?', [$factuurId]);
    if (!$factuur || (!team_mag('facturen', $gebruiker) && (int) $factuur['ouder_id'] !== (int) $gebruiker['id'])) {
        niet_gevonden();
    }
    return $factuur;
}
