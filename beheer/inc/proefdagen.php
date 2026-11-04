<?php
/*
 * Proefdagen: het team zet momenten klaar (datum, tijd en aantal plekken) en ouders kiezen
 * er zelf een op de website (proefdag.html, verstuurd naar proefdag.php). Het team ziet de
 * boekingen in de beheeromgeving bij Proefdagen (proefdagen.php).
 *
 * Bewaren: een boeking wordt 4 weken na de proefdag vanzelf verwijderd (bewaarbeleid);
 * een oud moment zonder boekingen ook. Meldt een ouder zich daarna aan, dan staan de
 * gegevens in de aanmelding.
 */

declare(strict_types=1);

const PROEFDAG_STATUSSEN = [
    'geboekt' => 'Geboekt',
    'geweest' => 'Geweest',
    'niet_gekomen' => 'Niet gekomen',
    'geannuleerd' => 'Geannuleerd',
];

/** De leeftijden die ouders kunnen kiezen (Sporty is er voor kinderen van 4 t/m 12 jaar). */
const PROEFDAG_LEEFTIJDEN = ['4', '5', '6', '7', '8', '9', '10', '11', '12'];

/** Hoe lang een boeking na de proefdag bewaard blijft. */
const PROEFDAG_BEWAAR_DAGEN = 28;

/** Spamrem: zoveel boekingen per kwartier vanaf hetzelfde adres. */
const MAX_PROEFDAGEN_PER_KWARTIER = 5;

/** Tot hoeveel weken vooruit ouders kunnen boeken. */
const PROEFDAG_WEKEN_VOORUIT = 12;

/** Aantal vrije plekken bij een moment. */
function proefdag_vrij(int $momentId): int
{
    return (int) waarde("SELECT m.plekken - (SELECT COUNT(*) FROM proefdag_boekingen b WHERE b.moment_id = m.id AND b.status = 'geboekt') FROM proefdag_momenten m WHERE m.id = ?", [$momentId]);
}

/**
 * De momenten die ouders nu kunnen kiezen: vanaf morgen, tot PROEFDAG_WEKEN_VOORUIT weken vooruit,
 * met nog minstens één plek vrij.
 */
function proefdag_momenten_open(): array
{
    $tot = date('Y-m-d', strtotime('+' . PROEFDAG_WEKEN_VOORUIT . ' weeks'));
    $rijen = rijen("SELECT m.id, m.datum, m.begin, m.eind, m.plekken - (SELECT COUNT(*) FROM proefdag_boekingen b WHERE b.moment_id = m.id AND b.status = 'geboekt') AS vrij
        FROM proefdag_momenten m WHERE m.datum > ? AND m.datum <= ? ORDER BY m.datum, m.begin LIMIT 60", [vandaag(), $tot]);
    return array_values(array_filter($rijen, fn ($rij) => (int) $rij['vrij'] > 0));
}

/** Een moment voor op de website en in e-mails, bijvoorbeeld "dinsdag 10 november, 15:00–16:30 uur". */
function proefdag_omschrijving(array $moment): string
{
    return datum_nl($moment['datum'], 'EEEE d MMMM') . ', ' . $moment['begin'] . '–' . $moment['eind'] . ' uur';
}

function proefdag_badge(string $status): string
{
    $klasse = ['geboekt' => 'bevestigd', 'geweest' => 'nieuw', 'niet_gekomen' => 'wachtlijst', 'geannuleerd' => 'ziek'][$status] ?? 'nieuw';
    return '<span class="badge badge--' . $klasse . '">' . e(PROEFDAG_STATUSSEN[$status] ?? $status) . '</span>';
}

/** Wanneer een boeking vanzelf wordt verwijderd: 4 weken na de proefdag. */
function proefdag_verwijderdatum(string $datum): string
{
    return date('Y-m-d 00:00:00', strtotime($datum . ' +' . PROEFDAG_BEWAAR_DAGEN . ' days'));
}

/** Verwijdert een boeking. $registreer = true bij handmatig verwijderen (komt dan niet terug via een back-up). */
function verwijder_proefdagboeking(array $boeking, string $reden, bool $registreer = true): void
{
    q('DELETE FROM proefdag_boekingen WHERE id = ?', [$boeking['id']]);
    if ($registreer) {
        registreer_verwijdering('proefdag', (int) $boeking['id'], $reden);
    }
    log_actie('Proefdagboeking verwijderd', 'proefdag ' . $boeking['id'] . ' (' . $reden . ')', null, $registreer ? 'wijziging' : 'bewaarbeleid', 'proefdag:' . $boeking['id']);
}

/** Aantal geboekte proefdagen vanaf vandaag (voor het menu). */
function komende_proefdagen(): int
{
    return (int) waarde("SELECT COUNT(*) FROM proefdag_boekingen b JOIN proefdag_momenten m ON m.id = b.moment_id WHERE b.status = 'geboekt' AND m.datum >= ?", [vandaag()]);
}
