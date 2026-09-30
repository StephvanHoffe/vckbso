<?php
/*
 * Wie mag welke kinderen zien?
 *
 * Team
 * - Beheerder: alle kinderen van de organisatie.
 * - Medewerker: alleen kinderen in de groepen waaraan hij of zij gekoppeld is
 *   (eventueel tijdelijk, met een einddatum voor invallers).
 *
 * Ouders en andere verzorgers
 * - Toegang loopt via een koppeling per kind (kind_verzorgers) met rechten:
 *   agenda, dossier, foto's en berichten.
 * - Beslissen namens het kind (toestemming foto's, gegevens wijzigen, AVG-verzoeken)
 *   mag alleen wie gezag heeft én bij wie het team dat heeft gecontroleerd.
 *   Alleen "ouder zijn" is dus niet genoeg.
 */

declare(strict_types=1);

const RECHTEN = [
    'agenda' => 'Dagen kiezen en afmelden',
    'dossier' => 'Gegevens en gedeelde observaties inzien',
    'fotos' => "Foto's ontvangen",
    'berichten' => 'Berichten over het kind',
];

const RELATIES = [
    'ouder' => 'Ouder',
    'voogd' => 'Voogd',
    'pleegouder' => 'Pleegouder',
    'stiefouder' => 'Partner van een ouder',
    'grootouder' => 'Grootouder',
    'anders' => 'Anders',
];

const GEZAG_BRONNEN = [
    'gezagsregister' => 'Uittreksel gezagsregister',
    'geboorteakte' => 'Geboorteakte (gehuwd of geregistreerd partner bij geboorte)',
    'beschikking' => 'Rechterlijke beschikking',
    'instelling' => 'Verklaring voogdij-instelling',
    'anders' => 'Anders (zie logboek)',
];

/* ---------- Team: groepen ---------- */

/** Groep-id's die de medewerker mag zien, of null voor "alle" (beheerder). */
function team_groep_ids(?array $gebruiker = null): ?array
{
    static $cache = [];
    $gebruiker ??= huidige_gebruiker();
    if (!$gebruiker || !is_team($gebruiker)) {
        return [];
    }
    if (is_beheerder($gebruiker)) {
        return null;
    }
    return $cache[$gebruiker['id']] ??= array_map('intval', array_column(rijen(
        'SELECT groep_id FROM medewerker_groepen WHERE gebruiker_id = ? AND (tot IS NULL OR tot >= ?)',
        [$gebruiker['id'], vandaag()]
    ), 'groep_id'));
}

/** SQL-voorwaarde op een groepkolom, bijvoorbeeld "k.groep_id IN (1,3)". */
function groep_voorwaarde(string $kolom): string
{
    $ids = team_groep_ids();
    if ($ids === null) {
        return '1 = 1';
    }
    return $ids ? $kolom . ' IN (' . implode(',', array_map('intval', $ids)) . ')' : '0 = 1';
}

function team_mag_groep(int $groepId): bool
{
    $ids = team_groep_ids();
    return $ids === null || in_array($groepId, $ids, true);
}

function team_mag_kind(array $kind): bool
{
    $ids = team_groep_ids();
    return $ids === null || ($kind['groep_id'] !== null && in_array((int) $kind['groep_id'], $ids, true));
}

/** SQL-voorwaarde: de ouder (kolom met gebruiker-id) hoort bij een kind in de eigen groepen. */
function ouder_voorwaarde(string $ouderKolom): string
{
    if (team_groep_ids() === null) {
        return '1 = 1';
    }
    return "EXISTS (SELECT 1 FROM kinderen tk WHERE " . groep_voorwaarde('tk.groep_id') . " AND tk.geanonimiseerd_op IS NULL
            AND (tk.ouder_id = {$ouderKolom} OR EXISTS (SELECT 1 FROM kind_verzorgers tv WHERE tv.kind_id = tk.id AND tv.gebruiker_id = {$ouderKolom})))";
}

function team_mag_ouder(int $ouderId): bool
{
    return (bool) waarde('SELECT 1 FROM gebruikers o WHERE o.id = ? AND ' . ouder_voorwaarde('o.id'), [$ouderId]);
}

/** Actieve groepen die de ingelogde medewerker mag zien. */
function zichtbare_groepen(): array
{
    return array_values(array_filter(actieve_groepen(), fn ($g) => team_mag_groep((int) $g['id'])));
}

/* ---------- Verzorgers per kind ---------- */

function verzorger_koppeling(int $kindId, int $gebruikerId): ?array
{
    return rij('SELECT * FROM kind_verzorgers WHERE kind_id = ? AND gebruiker_id = ?', [$kindId, $gebruikerId]);
}

function heeft_recht(int $kindId, int $gebruikerId, string $recht): bool
{
    if (!array_key_exists($recht, RECHTEN)) {
        return false;
    }
    $koppeling = verzorger_koppeling($kindId, $gebruikerId);
    return $koppeling !== null && (int) $koppeling['recht_' . $recht] === 1;
}

function gezag_gecontroleerd(?array $koppeling): bool
{
    return $koppeling !== null && (int) $koppeling['gezag'] === 1 && !empty($koppeling['gezag_gecontroleerd_op']);
}

/** Mag deze verzorger beslissen namens het kind? Alleen met gezag dat het team heeft gecontroleerd. */
function mag_namens_kind(int $kindId, int $gebruikerId): bool
{
    return gezag_gecontroleerd(verzorger_koppeling($kindId, $gebruikerId));
}

/**
 * Kinderen waar deze verzorger bij hoort, eventueel alleen met een bepaald recht.
 * Elke rij bevat de kindgegevens plus de koppeling (v_relatie, v_gezag, v_gezag_gecontroleerd_op, v_recht_*).
 */
function kinderen_van_verzorger(int $gebruikerId, ?string $recht = null, bool $alleenActief = true): array
{
    $waar = 'v.gebruiker_id = ? AND k.geanonimiseerd_op IS NULL';
    if ($recht !== null) {
        if (!array_key_exists($recht, RECHTEN)) {
            return [];
        }
        $waar .= " AND v.recht_{$recht} = 1";
    }
    if ($alleenActief) {
        $waar .= ' AND k.actief = 1';
    }
    return rijen(
        "SELECT k.*, g.naam AS groepnaam, g.dagen AS groepdagen, v.relatie AS v_relatie, v.gezag AS v_gezag, v.gezag_gecontroleerd_op AS v_gezag_gecontroleerd_op,
                v.recht_agenda AS v_recht_agenda, v.recht_dossier AS v_recht_dossier, v.recht_fotos AS v_recht_fotos, v.recht_berichten AS v_recht_berichten
         FROM kind_verzorgers v JOIN kinderen k ON k.id = v.kind_id LEFT JOIN groepen g ON g.id = k.groep_id
         WHERE {$waar} ORDER BY k.voornaam",
        [$gebruikerId]
    );
}

/** Gebruiker-id's van actieve verzorgers met een bepaald recht op dit kind. */
function verzorgers_met_recht(int $kindId, string $recht): array
{
    if (!array_key_exists($recht, RECHTEN)) {
        return [];
    }
    return array_map('intval', array_column(rijen(
        "SELECT v.gebruiker_id FROM kind_verzorgers v JOIN gebruikers g ON g.id = v.gebruiker_id WHERE v.kind_id = ? AND v.recht_{$recht} = 1 AND g.status != 'gestopt'",
        [$kindId]
    ), 'gebruiker_id'));
}

/** Alle verzorgers van een kind met naam en contactgegevens. */
function verzorgers_van_kind(int $kindId): array
{
    return rijen(
        'SELECT v.*, g.naam, g.email, g.telefoon, g.status, c.naam AS gecontroleerd_door_naam
         FROM kind_verzorgers v JOIN gebruikers g ON g.id = v.gebruiker_id LEFT JOIN gebruikers c ON c.id = v.gezag_gecontroleerd_door
         WHERE v.kind_id = ? ORDER BY v.gezag DESC, g.naam',
        [$kindId]
    );
}

function koppel_verzorger(int $kindId, int $gebruikerId, string $relatie, bool $gezag, array $rechten = ['agenda', 'dossier', 'fotos', 'berichten']): void
{
    q('INSERT INTO kind_verzorgers (kind_id, gebruiker_id, relatie, gezag, recht_agenda, recht_dossier, recht_fotos, recht_berichten, aangemaakt_op)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
       ON CONFLICT(kind_id, gebruiker_id) DO UPDATE SET relatie = excluded.relatie, gezag = excluded.gezag,
         recht_agenda = excluded.recht_agenda, recht_dossier = excluded.recht_dossier, recht_fotos = excluded.recht_fotos, recht_berichten = excluded.recht_berichten', [
        $kindId, $gebruikerId, array_key_exists($relatie, RELATIES) ? $relatie : 'anders', $gezag ? 1 : 0,
        in_array('agenda', $rechten, true) ? 1 : 0, in_array('dossier', $rechten, true) ? 1 : 0,
        in_array('fotos', $rechten, true) ? 1 : 0, in_array('berichten', $rechten, true) ? 1 : 0, nu(),
    ]);
}

/**
 * Het kind, als de ingelogde gebruiker het mag zien.
 * Team: binnen de eigen groepen. Ouder/verzorger: met het gevraagde recht.
 * Anders "niet gevonden" (we verklappen niet dat het kind bestaat).
 */
function kind_met_toegang(int $kindId, string $recht = 'dossier'): array
{
    $gebruiker = vereis_login();
    $kind = rij('SELECT k.*, g.naam AS groepnaam FROM kinderen k LEFT JOIN groepen g ON g.id = k.groep_id WHERE k.id = ? AND k.geanonimiseerd_op IS NULL', [$kindId]);
    if (!$kind) {
        niet_gevonden();
    }
    if (is_team($gebruiker)) {
        if (!team_mag_kind($kind)) {
            niet_gevonden();
        }
        return $kind;
    }
    if (!heeft_recht((int) $kind['id'], (int) $gebruiker['id'], $recht)) {
        niet_gevonden();
    }
    return $kind;
}
