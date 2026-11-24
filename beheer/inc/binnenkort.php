<?php
/*
 * De binnenkort-pagina (coming soon, november 2026): zolang de website nog niet live is, zien bezoekers op het
 * domein alleen binnenkort.html (en en/coming-soon.html). Daar laten ouders hun gegevens achter, net als bij
 * Blijf op de hoogte (bron "binnenkort", met dubbele bevestiging). Gaat de website live, dan krijgt iedereen die
 * zich daar aanmeldde en dat heeft bevestigd automatisch een mail ("website_live" in inc/mailteksten.php).
 *
 * De schakelaar:
 * - Alles op één server (website en Mijn BSO samen): het bestand binnenkort.aan in de webmap (public_html);
 *   .htaccess kijkt of het er staat.
 * - Website elders (website_url in de configuratie; de website op Vercel, Mijn BSO op mijn.bsosporty.nl, sinds
 *   november 2026): de instelling website_live_op. De middleware van de website (middleware.js) vraagt die op via
 *   website-status.php.
 * "Website live zetten" (bij Op de hoogte, met het recht Instellingen) zet de schakelaar om, onthoudt wanneer de
 * site live ging en stuurt de mails. Wie zich daarna nog bevestigt, krijgt de mail meteen; lukt een mail niet,
 * dan probeert het dagelijkse onderhoud (cli/onderhoud.php) het opnieuw.
 * Het team kan de echte website vooraf bekijken met voorproef.php (een cookie, alleen gemak, geen beveiliging).
 */

declare(strict_types=1);

const BINNENKORT_BESTAND = 'binnenkort.aan';
const VOORPROEF_COOKIE = 'sporty_voorproef';
/** Zoveel mails per keer (de rest volgt bij de volgende klik of het dagelijkse onderhoud). */
const LIVE_MAILS_PER_KEER = 300;

/** De map van de website (public_html): de map boven beheer/, tenzij de configuratie een andere noemt. */
function website_map(): string
{
    $map = (string) cfg('website_map', '');
    return rtrim($map !== '' ? $map : dirname((string) realpath(BEHEER_DIR)), '/');
}

/** Staat de binnenkort-pagina aan? (website elders: nog niet live gezet; anders: staat binnenkort.aan in de webmap) */
function binnenkort_aan(): bool
{
    if (website_elders()) {
        return website_live_op() === null;
    }
    return is_file(website_map() . '/' . BINNENKORT_BESTAND);
}

/** Wanneer de website via Mijn BSO live is gezet, of null. */
function website_live_op(): ?string
{
    $op = instelling('website_live_op', '');
    return $op !== '' ? $op : null;
}

/** Het adres van de website in de taal van de ontvanger, bijvoorbeeld https://bsosporty.nl/ of …/en/. */
function website_adres(string $taal = 'nl'): string
{
    return website_url() . '/' . ($taal === 'en' ? 'en/' : '');
}

/** Aanmeldingen van de binnenkort-pagina die bevestigd zijn en de live-mail nog niet hebben gekregen. */
function live_mail_wachtend(): array
{
    return rijen('SELECT * FROM interesse WHERE live_mail = 1 AND bevestigd_op IS NOT NULL AND live_gemaild_op IS NULL ORDER BY id');
}

/** Stuurt één aanmelding de mail dat de website live is (in de taal waarin die persoon zich aanmeldde). */
function stuur_live_mail(array $rij): bool
{
    $vorigeTaal = taal();
    $taal = (string) ($rij['taal'] ?? 'nl');
    taal($taal);
    try {
        $gelukt = stuur_mailtekst((string) $rij['email'], 'website_live', [
            'voornaam' => explode(' ', trim((string) $rij['naam']))[0],
            'website' => website_adres($taal),
            'afmeldlink' => interesse_afmeldlink($rij),
        ]);
    } finally {
        taal($vorigeTaal);
    }
    if ($gelukt) {
        q('UPDATE interesse SET live_gemaild_op = ? WHERE id = ? AND live_gemaild_op IS NULL', [nu(), $rij['id']]);
    }
    return $gelukt;
}

/**
 * Stuurt de live-mail aan iedereen die erop wacht, maar alleen als de website live is gezet.
 * Geeft [verstuurd, mislukt, nog over] terug.
 */
function stuur_live_mails(int $max = LIVE_MAILS_PER_KEER): array
{
    if (website_live_op() === null) {
        return [0, 0, 0];
    }
    @set_time_limit(0);
    $wachtend = live_mail_wachtend();
    $verstuurd = 0;
    $mislukt = 0;
    foreach (array_slice($wachtend, 0, $max) as $rij) {
        stuur_live_mail($rij) ? $verstuurd++ : $mislukt++;
    }
    if ($verstuurd || $mislukt) {
        log_actie('Website live: mail aan Blijf op de hoogte', "{$verstuurd} verstuurd" . ($mislukt ? ", {$mislukt} mislukt" : ''));
    }
    return [$verstuurd, $mislukt, max(0, count($wachtend) - $verstuurd)];
}

/**
 * Zet de website live: haalt binnenkort.aan weg, onthoudt het moment en mailt iedereen van de binnenkort-pagina.
 * Lukt het weghalen niet (rechten op de server), dan gaat er niets live en ook geen mail uit.
 * Geeft [verstuurd, mislukt, nog over] terug.
 */
function zet_website_live(): array
{
    $bestand = website_map() . '/' . BINNENKORT_BESTAND;
    if (!website_elders() && is_file($bestand) && !@unlink($bestand)) {
        throw new RuntimeException('Het bestand ' . BINNENKORT_BESTAND . ' kon niet worden weggehaald. Haal het weg via Bestandsbeheer in DirectAdmin (in public_html) en klik daarna nog een keer op de knop.');
    }
    clearstatcache();
    if (website_live_op() === null) {
        instelling_zet('website_live_op', nu());
        log_actie('Website live gezet', 'de binnenkort-pagina staat uit');
    }
    return stuur_live_mails();
}

/** Zet de binnenkort-pagina (weer) aan. Wie de live-mail al kreeg, krijgt hem bij de volgende livegang niet nog eens. */
function zet_binnenkort_aan(): void
{
    $bestand = website_map() . '/' . BINNENKORT_BESTAND;
    if (!website_elders() && !is_file($bestand) && @file_put_contents($bestand, "De website staat op de binnenkort-pagina. Live zetten: Mijn BSO → Op de hoogte.\n") === false) {
        throw new RuntimeException('Het bestand ' . BINNENKORT_BESTAND . ' kon niet worden gemaakt in ' . website_map() . '. Zet het er via Bestandsbeheer in DirectAdmin neer (een leeg bestand is genoeg).');
    }
    instelling_zet('website_live_op', '');
    log_actie('Binnenkort-pagina aangezet', 'de website is alleen nog via de voorproef te zien');
}
