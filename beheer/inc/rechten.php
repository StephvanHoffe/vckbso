<?php
/*
 * Rechten van het team, per persoon. Elk menuonderdeel staat per persoon aan of uit,
 * plus twee extra rechten: alle groepen zien en klanten beheren.
 *
 * Een onderdeel dat uit staat, verdwijnt uit het menu én is niet te openen (de pagina's
 * controleren het zelf met vereis_recht). Wat iemand binnen een onderdeel ziet, hangt
 * daarnaast af van de groepen (zie toegang.php).
 */

declare(strict_types=1);

/** Menuonderdelen van het team: sleutel => [pagina, menulabel, icoon, uitleg]. De volgorde is die van het menu. */
const ONDERDELEN = [
    'vandaag' => ['index.php', 'Vandaag', 'home', 'Wie er vandaag komt, aanwezigheid en ziekmeldingen'],
    'agenda' => ['agenda.php', 'Agenda', 'calendar', 'Week- en dagoverzicht, inschrijven, afmelden en dagen aanpassen'],
    'kinderen' => ['kinderen.php', 'Kinderen', 'smile', 'Kinddossiers en het kindvolgsysteem'],
    'ouders' => ['ouders.php', 'Ouders', 'users', 'Contactgegevens van ouders en verzorgers'],
    'berichten' => ['berichten.php', 'Berichten', 'chat', 'Berichten en ziekmeldingen van ouders'],
    'fotos' => ['fotos.php', "Foto's", 'camera', "Foto's delen met ouders"],
    'groepen' => ['groepen.php', 'Groepen', 'grid', 'Groepen en maximale groepsgrootte'],
    'facturen' => ['facturen.php', 'Facturen', 'euro', 'Facturen maken en incasso starten'],
    'financien' => ['financien.php', 'Financiën', 'chart', 'Inkomsten, uitgaven en bonnetjes'],
    'team' => ['medewerkers.php', 'Team', 'key', "Collega's uitnodigen en rechten geven"],
    'verzoeken' => ['verzoeken.php', 'Privacyverzoeken', 'shield', 'Privacyverzoeken van ouders afhandelen'],
    'instellingen' => ['instellingen.php', 'Instellingen', 'gear', 'Instellingen, logboek en beveiligingsstatus'],
];

/** Extra rechten, los van het menu. */
const EXTRA_RECHTEN = [
    'alle_groepen' => ['Alle groepen', 'Ziet alle kinderen en ouders, ook van kinderen die nog niet in een groep zitten. Zonder dit recht alleen de gekoppelde groepen.'],
    'klanten_beheren' => ['Klanten beheren', 'Aanmeldingen activeren, gegevens van kinderen wijzigen, gezag vastleggen, verzorgers koppelen, kinderen indelen en klanten stoppen of hun gegevens wissen.'],
];

/** Het vaste pakket voor een begeleider (ook wat bestaande medewerkers bij de invoering kregen). */
const RECHTEN_BEGELEIDER = ['vandaag', 'agenda', 'kinderen', 'ouders', 'berichten', 'fotos'];

function alle_rechten(): array
{
    return array_merge(array_keys(ONDERDELEN), array_keys(EXTRA_RECHTEN));
}

/** De rechten van een teamlid (lijst met sleutels). */
function rechten_van(int $gebruikerId, bool $vernieuw = false): array
{
    static $cache = [];
    if ($vernieuw || !isset($cache[$gebruikerId])) {
        $cache[$gebruikerId] = array_column(rijen('SELECT recht FROM team_rechten WHERE gebruiker_id = ?', [$gebruikerId]), 'recht');
    }
    return $cache[$gebruikerId];
}

/** Mag dit teamlid dit onderdeel of extra recht gebruiken? Ouders nooit. */
function team_mag(string $recht, ?array $gebruiker = null): bool
{
    $gebruiker ??= huidige_gebruiker();
    return $gebruiker !== null && is_team($gebruiker) && in_array($recht, rechten_van((int) $gebruiker['id']), true);
}

/**
 * Pagina (of actie) alleen voor teamleden met minstens één van deze rechten.
 * Geeft de ingelogde gebruiker terug.
 */
function vereis_recht(string ...$rechten): array
{
    $gebruiker = vereis_team();
    foreach ($rechten as $recht) {
        if (team_mag($recht, $gebruiker)) {
            return $gebruiker;
        }
    }
    geen_toegang('Je hebt geen toegang tot dit onderdeel. Heb je het nodig voor je werk? Vraag dan een collega die het team beheert om het voor je aan te zetten.');
}

/** De menuonderdelen die dit teamlid mag zien, in menuvolgorde. */
function onderdelen_van(array $gebruiker): array
{
    return array_filter(ONDERDELEN, fn ($sleutel) => team_mag($sleutel, $gebruiker), ARRAY_FILTER_USE_KEY);
}

/** De eerste pagina waar dit teamlid naartoe mag (na het inloggen), of null. */
function eerste_pagina(array $gebruiker): ?string
{
    $onderdelen = onderdelen_van($gebruiker);
    return $onderdelen ? reset($onderdelen)[0] : null;
}

/** Een herkenbare omschrijving: "Beheerder" (alles), "Begeleider" (het vaste pakket) of "Aangepast". */
function rechten_label(array $rechten): string
{
    $zelfde = fn (array $a, array $b) => !array_diff($a, $b) && !array_diff($b, $a);
    if ($zelfde($rechten, alle_rechten())) {
        return 'Beheerder: alles';
    }
    if ($zelfde($rechten, RECHTEN_BEGELEIDER)) {
        return 'Begeleider';
    }
    return $rechten ? 'Aangepast' : 'Geen toegang';
}

/**
 * Slaat de rechten van een teamlid op. Weigert (met een uitleg) als daardoor niemand
 * meer het team kan beheren. De rol in de database volgt: wie het team beheert, is beheerder.
 */
function zet_rechten(int $gebruikerId, array $rechten): ?string
{
    $rechten = array_values(array_intersect(alle_rechten(), $rechten));
    $anderenMetTeam = (int) waarde(
        "SELECT COUNT(*) FROM team_rechten r JOIN gebruikers g ON g.id = r.gebruiker_id
         WHERE r.recht = 'team' AND g.status = 'actief' AND g.rol IN ('beheerder', 'medewerker') AND g.id != ?",
        [$gebruikerId]
    );
    if (!in_array('team', $rechten, true) && $anderenMetTeam === 0) {
        return 'Er moet altijd minstens één actieve collega zijn die het team kan beheren. Geef eerst iemand anders het onderdeel Team.';
    }
    $oud = rechten_van($gebruikerId, true);
    transactie(function () use ($gebruikerId, $rechten) {
        q('DELETE FROM team_rechten WHERE gebruiker_id = ?', [$gebruikerId]);
        foreach ($rechten as $recht) {
            q('INSERT INTO team_rechten (gebruiker_id, recht) VALUES (?, ?)', [$gebruikerId, $recht]);
        }
        q('UPDATE gebruikers SET rol = ? WHERE id = ?', [in_array('team', $rechten, true) ? 'beheerder' : 'medewerker', $gebruikerId]);
    });
    rechten_van($gebruikerId, true);
    $erbij = array_diff($rechten, $oud);
    $eraf = array_diff($oud, $rechten);
    if ($erbij || $eraf) {
        log_actie('Rechten gewijzigd', trim(($erbij ? 'aan: ' . implode(', ', $erbij) : '') . ($erbij && $eraf ? '; ' : '') . ($eraf ? 'uit: ' . implode(', ', $eraf) : '')), null, 'beveiliging', 'gebruiker:' . $gebruikerId);
    }
    return null;
}

/** Een link naar een ander onderdeel, of alleen de tekst als dit teamlid daar niet bij mag. */
function link_als(string $recht, string $href, string $html): string
{
    return team_mag($recht) ? '<a href="' . e($href) . '">' . $html . '</a>' : $html;
}
