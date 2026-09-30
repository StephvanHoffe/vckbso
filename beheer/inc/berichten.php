<?php
/* Berichten opslaan (versleuteld) en tellen. Per ouder- of verzorgeraccount één gesprek met het team. */

declare(strict_types=1);

function nieuw_bericht(int $ouderId, ?int $afzenderId, ?int $kindId, string $soort, string $tekst, bool $gelezenOuder, bool $gelezenTeam): int
{
    q('INSERT INTO berichten (ouder_id, afzender_id, kind_id, soort, tekst, gelezen_ouder, gelezen_team, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
        $ouderId, $afzenderId, $kindId, $soort, versleutel($tekst), $gelezenOuder ? 1 : 0, $gelezenTeam ? 1 : 0, nu(),
    ]);
    return laatste_id();
}

/** Bericht van het systeem aan één ouder of verzorger. */
function systeembericht(int $ouderId, string $tekst, ?int $kindId = null): void
{
    nieuw_bericht($ouderId, null, $kindId, 'systeem', $tekst, false, true);
}

/** Bericht van het systeem aan alle verzorgers van een kind met een bepaald recht. */
function bericht_aan_verzorgers(int $kindId, string $recht, string $tekst): void
{
    foreach (verzorgers_met_recht($kindId, $recht) as $gebruikerId) {
        systeembericht($gebruikerId, $tekst, $kindId);
    }
}

/** Melding van een ouder die alleen het team hoeft te zien (bijvoorbeeld "gegevens gewijzigd"). */
function melding_voor_team(int $ouderId, string $tekst, ?int $kindId = null): void
{
    nieuw_bericht($ouderId, $ouderId, $kindId, 'systeem', $tekst, true, false);
}

/** Ongelezen berichten binnen de gesprekken die dit teamlid mag zien. */
function ongelezen_voor_team(): int
{
    return (int) waarde('SELECT COUNT(*) FROM berichten b WHERE b.gelezen_team = 0 AND ' . ouder_voorwaarde('b.ouder_id'));
}

function ongelezen_voor_ouder(int $ouderId): int
{
    return (int) waarde('SELECT COUNT(*) FROM berichten WHERE ouder_id = ? AND gelezen_ouder = 0', [$ouderId]);
}
