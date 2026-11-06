<?php
/*
 * Op welke dagen Sporty open is. Sporty opent op maandag 4 januari 2027 en is tot de zomervakantie alleen
 * op dinsdag en donderdag open; vanaf maandag 12 juli 2027 vijf dagen per week (teksten van november 2026).
 *
 * Gebruikt door het aanmeldformulier (aanvraag.php) en de proefdagen. Verandert er iets, pas dan ook de
 * data-attributen in aanmelden.html aan (data-dagen-open, data-alle-dagen-vanaf en data-start-vanaf).
 */

declare(strict_types=1);

const SPORTY_OPEN_VANAF = '2027-01-04';
const SPORTY_ALLE_DAGEN_VANAF = '2027-07-12';
/** Weekdagen (1 = maandag) waarop Sporty open is tot SPORTY_ALLE_DAGEN_VANAF. */
const SPORTY_EERSTE_DAGEN = [2, 4];

/** De weekdagen (1 = maandag ... 5 = vrijdag) waarop Sporty open is op deze datum (standaard: vandaag). */
function open_weekdagen(?string $datum = null): array
{
    return ($datum ?? vandaag()) < SPORTY_ALLE_DAGEN_VANAF ? SPORTY_EERSTE_DAGEN : [1, 2, 3, 4, 5];
}

/** Is Sporty open op deze datum (schooldag-weekdag, en niet vóór de opening)? Feestdagen telt dit niet mee. */
function is_open_dag(string $datum): bool
{
    return geldige_datum($datum) && $datum >= SPORTY_OPEN_VANAF && in_array((int) date('N', strtotime($datum)), open_weekdagen($datum), true);
}

/** "dinsdag en donderdag" */
function weekdagen_tekst(array $dagen): string
{
    $namen = array_map(fn (int $d) => WEEKDAGEN[$d] ?? (string) $d, array_values($dagen));
    return count($namen) > 1 ? implode(', ', array_slice($namen, 0, -1)) . ' en ' . end($namen) : (string) ($namen[0] ?? '');
}
