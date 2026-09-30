<?php
/*
 * Groepsagenda: wie komt wanneer, met een maximale groepsgrootte.
 * Werkt twee kanten op: ouders schrijven hun kind in of melden af,
 * het team ziet dat direct en kan zelf ook inschrijven, afmelden of bevestigen.
 *
 * Een plek telt mee als de status 'bevestigd' of 'ziek' is. Vol? Dan komt het
 * kind op de wachtlijst en schuift het automatisch door als er iemand afmeldt.
 */

declare(strict_types=1);

const TELLENDE_STATUSSEN = "('bevestigd', 'ziek')";

function groep(int $groepId): ?array
{
    return rij('SELECT * FROM groepen WHERE id = ?', [$groepId]);
}

function actieve_groepen(): array
{
    return rijen('SELECT * FROM groepen WHERE actief = 1 ORDER BY naam');
}

/** De weekdagen (1 = maandag) waarop een groep open is. */
function groep_dagen(array $groep): array
{
    return array_map('intval', array_filter(explode(',', (string) $groep['dagen'])));
}

function uitzondering(int $groepId, string $datum): ?array
{
    return rij('SELECT * FROM groep_uitzonderingen WHERE groep_id = ? AND datum = ?', [$groepId, $datum]);
}

function groep_open_op(array $groep, string $datum): bool
{
    if (!$groep['actief'] || !in_array(weekdag($datum), groep_dagen($groep), true)) {
        return false;
    }
    $uitzondering = uitzondering((int) $groep['id'], $datum);
    return !($uitzondering && $uitzondering['gesloten']);
}

function capaciteit(array $groep, string $datum): int
{
    $uitzondering = uitzondering((int) $groep['id'], $datum);
    return (int) ($uitzondering['max_kinderen'] ?? $groep['max_kinderen']);
}

function bezetting(int $groepId, string $datum): int
{
    return (int) waarde('SELECT COUNT(*) FROM inschrijvingen WHERE groep_id = ? AND datum = ? AND status IN ' . TELLENDE_STATUSSEN, [$groepId, $datum]);
}

function aantal_wachtlijst(int $groepId, string $datum): int
{
    return (int) waarde("SELECT COUNT(*) FROM inschrijvingen WHERE groep_id = ? AND datum = ? AND status = 'wachtlijst'", [$groepId, $datum]);
}

/**
 * Schrijft een kind in voor een dag in zijn groep.
 * Geeft de nieuwe status terug: 'bevestigd', 'wachtlijst', of null als de groep dicht is.
 * Met $boven_max (alleen voor het team) mag het kind er ook bij als de groep vol is.
 */
function schrijf_in(array $kind, string $datum, int $doorId, bool $boven_max = false): ?string
{
    if (!$kind['groep_id'] || !$kind['actief']) {
        return null;
    }
    return transactie(function () use ($kind, $datum, $doorId, $boven_max) {
        $groep = groep((int) $kind['groep_id']);
        if (!$groep || !groep_open_op($groep, $datum)) {
            return null;
        }
        $bestaand = rij('SELECT * FROM inschrijvingen WHERE kind_id = ? AND datum = ?', [$kind['id'], $datum]);
        if ($bestaand && in_array($bestaand['status'], ['bevestigd', 'ziek', 'wachtlijst'], true)) {
            if ($bestaand['status'] === 'wachtlijst' && $boven_max) {
                q("UPDATE inschrijvingen SET status = 'bevestigd', gewijzigd_op = ? WHERE id = ?", [nu(), $bestaand['id']]);
                return 'bevestigd';
            }
            return $bestaand['status'];
        }
        $vrij = bezetting((int) $groep['id'], $datum) < capaciteit($groep, $datum);
        $status = ($vrij || $boven_max) ? 'bevestigd' : 'wachtlijst';
        if ($bestaand) {
            q('UPDATE inschrijvingen SET status = ?, groep_id = ?, aanwezig_om = NULL, opgehaald_om = NULL, aangemaakt_op = ?, gewijzigd_op = ? WHERE id = ?', [
                $status, $groep['id'], nu(), nu(), $bestaand['id'],
            ]);
        } else {
            q('INSERT INTO inschrijvingen (kind_id, groep_id, datum, status, aangemaakt_door, aangemaakt_op, gewijzigd_op) VALUES (?, ?, ?, ?, ?, ?, ?)', [
                $kind['id'], $groep['id'], $datum, $status, $doorId, nu(), nu(),
            ]);
        }
        return $status;
    });
}

/**
 * Meldt een kind af voor een dag. Staat er iemand op de wachtlijst,
 * dan krijgt die de plek en een bericht.
 */
function meld_af(array $inschrijving, string $nieuweStatus = 'afgemeld'): void
{
    transactie(function () use ($inschrijving, $nieuweStatus) {
        q('UPDATE inschrijvingen SET status = ?, gewijzigd_op = ? WHERE id = ?', [$nieuweStatus, nu(), $inschrijving['id']]);
        if ($nieuweStatus === 'afgemeld' && $inschrijving['status'] !== 'wachtlijst') {
            vul_plek_vanuit_wachtlijst((int) $inschrijving['groep_id'], $inschrijving['datum']);
        }
    });
}

function vul_plek_vanuit_wachtlijst(int $groepId, string $datum): void
{
    $groep = groep($groepId);
    if (!$groep || $datum < vandaag()) {
        return;
    }
    while (bezetting($groepId, $datum) < capaciteit($groep, $datum)) {
        $volgende = rij(
            "SELECT i.*, k.voornaam, k.ouder_id FROM inschrijvingen i JOIN kinderen k ON k.id = i.kind_id
             WHERE i.groep_id = ? AND i.datum = ? AND i.status = 'wachtlijst' ORDER BY i.aangemaakt_op, i.id LIMIT 1",
            [$groepId, $datum]
        );
        if (!$volgende) {
            return;
        }
        q("UPDATE inschrijvingen SET status = 'bevestigd', gewijzigd_op = ? WHERE id = ?", [nu(), $volgende['id']]);
        systeembericht((int) $volgende['ouder_id'], 'Goed nieuws! Er is een plekje vrijgekomen: ' . $volgende['voornaam'] . ' kan op ' . datum_nl($datum, 'EEEE d MMMM') . ' komen. Kan het toch niet? Meld je kind dan af in de agenda.', (int) $volgende['kind_id']);
    }
}

/** Mag een ouder deze dag nog zelf aan- of afmelden? Tot de ingestelde tijd op de dag zelf. */
function ouder_mag_wijzigen(string $datum): bool
{
    if ($datum > vandaag()) {
        return true;
    }
    if ($datum < vandaag()) {
        return false;
    }
    $tot = instelling('afmelden_tot', '12:00');
    return geldige_tijd($tot) && date('H:i') < $tot;
}

/**
 * Overzicht van één groep op één dag: capaciteit, bezetting en de kinderen.
 */
function dagoverzicht(array $groep, string $datum): array
{
    $kinderen = rijen(
        "SELECT i.*, k.voornaam, k.achternaam, k.bijzonderheden, k.ophaalpersonen, k.geboortedatum, k.ouder_id, g.naam AS oudernaam, g.telefoon AS oudertelefoon
         FROM inschrijvingen i
         JOIN kinderen k ON k.id = i.kind_id
         JOIN gebruikers g ON g.id = k.ouder_id
         WHERE i.groep_id = ? AND i.datum = ?
         ORDER BY CASE i.status WHEN 'bevestigd' THEN 0 WHEN 'ziek' THEN 1 WHEN 'wachtlijst' THEN 2 ELSE 3 END, i.aangemaakt_op, k.voornaam",
        [$groep['id'], $datum]
    );
    return [
        'open' => groep_open_op($groep, $datum),
        'capaciteit' => capaciteit($groep, $datum),
        'bezetting' => bezetting((int) $groep['id'], $datum),
        'wachtlijst' => aantal_wachtlijst((int) $groep['id'], $datum),
        'uitzondering' => uitzondering((int) $groep['id'], $datum),
        'kinderen' => $kinderen,
    ];
}

/** Maandag van de week waarin $datum valt. */
function maandag_van(string $datum): string
{
    return date('Y-m-d', strtotime($datum . ' -' . (weekdag($datum) - 1) . ' days'));
}

/** Alle datums van $van t/m $tot (Y-m-d). */
function datums_tussen(string $van, string $tot): array
{
    $datums = [];
    for ($d = strtotime($van); $d <= strtotime($tot); $d = strtotime('+1 day', $d)) {
        $datums[] = date('Y-m-d', $d);
    }
    return $datums;
}

/* ---------- Berichten van het systeem (bijvoorbeeld wachtlijst of mislukte incasso) ---------- */

function systeembericht(int $ouderId, string $tekst, ?int $kindId = null): void
{
    q("INSERT INTO berichten (ouder_id, afzender_id, kind_id, soort, tekst, gelezen_ouder, gelezen_team, aangemaakt_op) VALUES (?, NULL, ?, 'systeem', ?, 0, 1, ?)", [
        $ouderId, $kindId, $tekst, nu(),
    ]);
}

function ongelezen_voor_team(): int
{
    return (int) waarde('SELECT COUNT(*) FROM berichten WHERE gelezen_team = 0');
}

function ongelezen_voor_ouder(int $ouderId): int
{
    return (int) waarde('SELECT COUNT(*) FROM berichten WHERE ouder_id = ? AND gelezen_ouder = 0', [$ouderId]);
}
