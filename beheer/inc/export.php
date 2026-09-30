<?php
/*
 * Exports voor het inzagerecht en het recht op overdraagbaarheid (AVG art. 15 en 20).
 * Een export is een leesbaar JSON-bestand met alle gegevens over een kind of
 * een ouder. Exports worden versleuteld bewaard en na 30 dagen verwijderd.
 */

declare(strict_types=1);

const EXPORT_GELDIG_DAGEN = 30;

const VERZOEK_SOORTEN = [
    'inzage' => 'Inzage: alle gegevens die jullie bewaren',
    'correctie' => 'Correctie: gegevens kloppen niet',
    'verwijdering' => 'Verwijdering: gegevens wissen',
    'beperking' => 'Beperking: gegevens tijdelijk niet gebruiken',
    'bezwaar' => 'Bezwaar tegen een bepaald gebruik',
    'overdracht' => 'Overdracht: gegevens in een bestand meenemen',
];

const VERZOEK_STATUSSEN = ['ontvangen' => 'Ontvangen', 'in_behandeling' => 'In behandeling', 'afgerond' => 'Afgerond', 'afgewezen' => 'Afgewezen'];

function verzoek_badge(string $status): string
{
    $klasse = ['ontvangen' => 'nieuw', 'in_behandeling' => 'wachtlijst', 'afgerond' => 'bevestigd', 'afgewezen' => 'ziek'][$status] ?? 'nieuw';
    return '<span class="badge badge--' . $klasse . '">' . e(VERZOEK_STATUSSEN[$status] ?? $status) . '</span>';
}

/**
 * Alle gegevens over een kind. $volledig = true voor een formeel inzageverzoek
 * (ook observaties die niet met de ouders gedeeld zijn). Berichten alleen uit het
 * gesprek van de aanvrager: berichten van andere verzorgers zijn hun gegevens.
 */
function export_kind(int $kindId, bool $volledig, ?int $aanvragerId = null): array
{
    $kind = rij('SELECT k.*, g.naam AS groepnaam FROM kinderen k LEFT JOIN groepen g ON g.id = k.groep_id WHERE k.id = ?', [$kindId]);
    if (!$kind) {
        return [];
    }
    $kind = ontsleutel_kolommen($kind, ['bijzonderheden', 'ophaalpersonen'], true);
    $observaties = ontsleutel_kolommen(rijen('SELECT datum, gebied, tekst, gedeeld FROM observaties WHERE kind_id = ?' . ($volledig ? '' : ' AND gedeeld = 1') . ' ORDER BY datum', [$kindId]), ['tekst']);
    $berichten = $aanvragerId ? ontsleutel_kolommen(rijen('SELECT aangemaakt_op, soort, tekst FROM berichten WHERE kind_id = ? AND ouder_id = ? ORDER BY aangemaakt_op', [$kindId, $aanvragerId]), ['tekst']) : [];

    return [
        'toelichting' => 'Alle gegevens die ' . instelling('bedrijfsnaam', 'Sporty') . ' over dit kind bewaart, gemaakt op ' . datum_nl(nu(), "d MMMM y 'om' HH:mm") . '.',
        'kind' => [
            'voornaam' => $kind['voornaam'],
            'achternaam' => $kind['achternaam'],
            'geboortedatum' => $kind['geboortedatum'],
            'school' => $kind['school'],
            'groep' => $kind['groepnaam'],
            'bijzonderheden' => $kind['bijzonderheden'],
            'mag_opgehaald_worden_door' => $kind['ophaalpersonen'],
            'toestemming_groepsfotos' => (bool) $kind['foto_toestemming'],
            'komt_naar_de_bso' => (bool) $kind['actief'],
            'aangemeld_op' => $kind['aangemaakt_op'],
            'gestopt_op' => $kind['gestopt_op'],
        ],
        'verzorgers' => array_map(fn ($v) => [
            'naam' => $v['naam'],
            'relatie' => RELATIES[$v['relatie']] ?? $v['relatie'],
            'gezag' => (bool) $v['gezag'],
            'gezag_gecontroleerd' => gezag_gecontroleerd($v),
        ], verzorgers_van_kind($kindId)),
        'opvangdagen' => rijen('SELECT i.datum, i.status, g.naam AS groep, i.aanwezig_om AS binnen, i.opgehaald_om AS opgehaald FROM inschrijvingen i LEFT JOIN groepen g ON g.id = i.groep_id WHERE i.kind_id = ? ORDER BY i.datum', [$kindId]),
        'observaties' => array_map(fn ($o) => [
            'datum' => $o['datum'],
            'ontwikkelgebied' => ONTWIKKELGEBIEDEN[$o['gebied']] ?? $o['gebied'],
            'observatie' => $o['tekst'],
            'gedeeld_met_ouders' => (bool) $o['gedeeld'],
            'door' => 'Team ' . instelling('bedrijfsnaam', 'Sporty'),
        ], $observaties),
        'fotos' => array_map(fn ($f) => ['geplaatst_op' => $f['aangemaakt_op'], 'bijschrift' => $f['bijschrift']], rijen('SELECT f.aangemaakt_op, f.bijschrift FROM fotos f JOIN foto_kinderen fk ON fk.foto_id = f.id WHERE fk.kind_id = ? ORDER BY f.aangemaakt_op', [$kindId])),
        'fotos_toelichting' => "De foto's zelf kun je bekijken en downloaden bij Foto's in Mijn BSO.",
        'berichten_over_dit_kind' => $berichten,
        'inzage_door_het_team' => array_map(fn ($l) => ['wanneer' => $l['aangemaakt_op'], 'wat' => $l['actie'], 'rol' => $l['rol'] ?? 'systeem'], rijen("SELECT l.aangemaakt_op, l.actie, g.rol FROM logboek l LEFT JOIN gebruikers g ON g.id = l.gebruiker_id WHERE l.onderwerp = ? AND l.soort IN ('inzage', 'export', 'wijziging') ORDER BY l.id", ['kind:' . $kindId])),
    ];
}

/** Alle gegevens over een ouder of verzorger (het eigen account). */
function export_gebruiker(int $gebruikerId): array
{
    $g = rij('SELECT * FROM gebruikers WHERE id = ?', [$gebruikerId]);
    if (!$g) {
        return [];
    }
    return [
        'toelichting' => 'Alle gegevens die ' . instelling('bedrijfsnaam', 'Sporty') . ' over jou bewaart, gemaakt op ' . datum_nl(nu(), "d MMMM y 'om' HH:mm") . '.',
        'account' => [
            'naam' => $g['naam'], 'email' => $g['email'], 'telefoon' => $g['telefoon'],
            'adres' => trim($g['straat'] . ', ' . $g['postcode'] . ' ' . $g['plaats'], ', '),
            'contactvoorkeur' => $g['contactvoorkeur'], 'status' => $g['status'],
            'aangemaakt_op' => $g['aangemaakt_op'], 'laatst_ingelogd' => $g['laatst_ingelogd'],
            'tweestapsverificatie' => (bool) $g['mfa_actief'],
            'opgegeven_bij_aanmelding' => ['gewenste_dagen' => $g['gewenste_dagen'], 'gewenste_startdatum' => $g['gewenste_startdatum'], 'opmerkingen' => $g['opmerkingen']],
        ],
        'machtiging_automatische_incasso' => [
            'status' => status_label($g['mandaat_status']), 'rekening' => $g['mandaat_rekening'], 'op_naam_van' => $g['mandaat_naam'], 'sinds' => $g['mandaat_datum'],
        ],
        'kinderen' => array_map(fn ($k) => [
            'kind' => kindnaam($k), 'relatie' => RELATIES[$k['v_relatie']] ?? $k['v_relatie'], 'gezag' => (bool) $k['v_gezag'],
            'gezag_gecontroleerd' => !empty($k['v_gezag_gecontroleerd_op']),
            'rechten' => array_values(array_filter(array_keys(RECHTEN), fn ($r) => (int) $k['v_recht_' . $r] === 1)),
        ], kinderen_van_verzorger($gebruikerId, null, false)),
        'berichten' => array_map(fn ($b) => ['wanneer' => $b['aangemaakt_op'], 'soort' => $b['soort'], 'van' => (int) $b['afzender_id'] === $gebruikerId ? 'jij' : 'team', 'tekst' => ontsleutel($b['tekst'])], rijen('SELECT * FROM berichten WHERE ouder_id = ? ORDER BY aangemaakt_op', [$gebruikerId])),
        'facturen' => array_map(fn ($f) => [
            'nummer' => $f['nummer'], 'periode' => $f['periode'], 'datum' => $f['datum'], 'bedrag' => geld((int) $f['bedrag_cent']), 'status' => status_label($f['status']),
            'regels' => rijen('SELECT omschrijving, aantal AS uren, prijs_cent / 100.0 AS uurtarief, bedrag_cent / 100.0 AS bedrag FROM factuurregels WHERE factuur_id = ?', [$f['id']]),
        ], rijen('SELECT * FROM facturen WHERE ouder_id = ? ORDER BY datum', [$gebruikerId])),
        'privacyverzoeken' => rijen('SELECT soort, status, aangemaakt_op AS ingediend_op, afgehandeld_op FROM avg_verzoeken WHERE gebruiker_id = ? ORDER BY id', [$gebruikerId]),
        'logboek' => rijen("SELECT aangemaakt_op AS wanneer, soort, actie FROM logboek WHERE onderwerp = ? ORDER BY id", ['gebruiker:' . $gebruikerId]),
    ];
}

function export_json(array $data): string
{
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

/** Bewaart een export versleuteld. Geeft de bestandsnaam terug. */
function export_opslaan(array $data): string
{
    $map = data_dir() . '/exports';
    if (!is_dir($map)) {
        mkdir($map, 0770, true);
    }
    $naam = bin2hex(random_bytes(16)) . '.json';
    versleutel_bestand($map . '/' . $naam, export_json($data));
    return $naam;
}

function export_lees(string $naam): ?string
{
    if (!preg_match('/^[a-f0-9]{32}\.json$/', $naam)) {
        return null;
    }
    return lees_versleuteld_bestand(data_dir() . '/exports/' . $naam);
}

function export_verwijder(?string $naam): void
{
    if ($naam && preg_match('/^[a-f0-9]{32}\.json$/', $naam) && is_file(data_dir() . '/exports/' . $naam)) {
        unlink(data_dir() . '/exports/' . $naam);
    }
}

function stuur_download(string $inhoud, string $bestandsnaam, string $type = 'application/json'): never
{
    header_remove('Cache-Control');
    header('Cache-Control: no-store, private');
    header('Content-Type: ' . $type . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $bestandsnaam) . '"');
    header('Content-Length: ' . strlen($inhoud));
    echo $inhoud;
    exit;
}
