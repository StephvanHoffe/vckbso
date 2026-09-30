<?php
/*
 * Facturen: achteraf per maand, op basis van de dagen die een kind ingeschreven
 * stond (status 'bevestigd' of 'ziek'). Op tijd afgemelde dagen tellen niet mee.
 *
 * TODO: factuurbeleid bevestigen met de klant (achteraf of vooraf, ziekte en
 * te late afmeldingen wel of niet rekenen, vast contract per maand).
 */

declare(strict_types=1);

function factuur_instellingen_compleet(): bool
{
    return (int) instelling('uurtarief_cent', '0') > 0 && (float) str_replace(',', '.', instelling('uren_per_middag', '0')) > 0;
}

function volgend_factuurnummer(): string
{
    $volgnummer = max(1, (int) instelling('volgend_factuurnummer', '1'));
    instelling_zet('volgend_factuurnummer', (string) ($volgnummer + 1));
    return date('Y') . '-' . str_pad((string) $volgnummer, 4, '0', STR_PAD_LEFT);
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
        throw new RuntimeException('Stel eerst het uurtarief en het aantal uren per middag in bij Instellingen.');
    }
    $tarief = (int) instelling('uurtarief_cent');
    $urenPerDag = (float) str_replace(',', '.', instelling('uren_per_middag'));
    $termijn = max(0, (int) instelling('betaaltermijn_dagen', '14'));
    $van = $periode . '-01';
    $tot = date('Y-m-t', strtotime($van));

    return transactie(function () use ($periode, $van, $tot, $tarief, $urenPerDag, $termijn) {
        $regels = rijen(
            "SELECT k.ouder_id, k.id AS kind_id, k.voornaam, k.achternaam, COUNT(*) AS dagen, GROUP_CONCAT(i.id) AS inschrijving_ids
             FROM inschrijvingen i JOIN kinderen k ON k.id = i.kind_id
             WHERE i.datum BETWEEN ? AND ? AND i.status IN ('bevestigd', 'ziek') AND i.gefactureerd = 0
               AND NOT EXISTS (SELECT 1 FROM facturen f WHERE f.ouder_id = k.ouder_id AND f.periode = ? AND f.status != 'geannuleerd')
             GROUP BY k.id ORDER BY k.ouder_id, k.voornaam",
            [$van, $tot, $periode]
        );
        $perOuder = [];
        foreach ($regels as $regel) {
            $perOuder[$regel['ouder_id']][] = $regel;
        }

        $aantal = 0;
        $totaal = 0;
        $maandnaam = datum_nl($van, 'MMMM y');
        foreach ($perOuder as $ouderId => $kinderen) {
            $bedrag = 0;
            foreach ($kinderen as &$kind) {
                $kind['uren'] = $kind['dagen'] * $urenPerDag;
                $kind['bedrag'] = (int) round($kind['uren'] * $tarief);
                $bedrag += $kind['bedrag'];
            }
            unset($kind);

            q('INSERT INTO facturen (nummer, ouder_id, periode, datum, vervaldatum, bedrag_cent, status, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
                volgend_factuurnummer(), $ouderId, $periode, vandaag(), date('Y-m-d', strtotime("+{$termijn} days")), $bedrag, 'open', nu(),
            ]);
            $factuurId = laatste_id();
            foreach ($kinderen as $kind) {
                $omschrijving = sprintf('Buitenschoolse opvang %s, %s: %d %s × %s uur', trim($kind['voornaam'] . ' ' . $kind['achternaam']), $maandnaam, $kind['dagen'], $kind['dagen'] == 1 ? 'middag' : 'middagen', uren_nl($urenPerDag));
                q('INSERT INTO factuurregels (factuur_id, kind_id, omschrijving, aantal, eenheid, prijs_cent, bedrag_cent) VALUES (?, ?, ?, ?, ?, ?, ?)', [
                    $factuurId, $kind['kind_id'], $omschrijving, $kind['uren'], 'uur', $tarief, $kind['bedrag'],
                ]);
                $ids = array_map('intval', explode(',', $kind['inschrijving_ids']));
                q('UPDATE inschrijvingen SET gefactureerd = 1 WHERE id IN (' . implode(',', $ids) . ')');
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
