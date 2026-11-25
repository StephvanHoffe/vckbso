<?php
/*
 * Blijf op de hoogte (november 2026): ouders die interesse hebben laten op de website hun naam, e-mailadres
 * en eventueel telefoonnummer achter (blijf-op-de-hoogte.html) en krijgen een Sporty-kennismakingspakket.
 * Sinds feedback 2.0 (november 2026) ook via een pop-up op elke pagina (js/pop-up.js), met de naam van het
 * kind (niet verplicht) en het huisadres om het Sporty-pakket op te sturen. Die twee staan versleuteld in de
 * database; het adres wissen we INTERESSE_ADRES_NA_PAKKET_DAGEN dagen nadat het pakket is verstuurd.
 *
 * Met dubbele bevestiging: pas na een klik op de link in de e-mail staat iemand op de lijst. Zo kan niemand
 * een ander aanmelden. Afmelden kan altijd, met de link onder elke e-mail; de gegevens worden dan gewist.
 * Het team ziet de lijst bij "Op de hoogte" (interesse.php), kan een update sturen en een CSV maken.
 */

declare(strict_types=1);

/** Niet bevestigd: na zoveel dagen gewist. */
const INTERESSE_ONBEVESTIGD_DAGEN = 14;
const MAX_INTERESSE_PER_KWARTIER = 5;
/** Het adres is alleen nodig voor het pakket: zoveel dagen nadat het is verstuurd, wissen we het. */
const INTERESSE_ADRES_NA_PAKKET_DAGEN = 30;
/** Waar iemand zich aanmeldde: de pagina Blijf op de hoogte, de pop-up of de binnenkort-pagina (coming soon). */
const INTERESSE_BRONNEN = ['website' => 'Pagina Blijf op de hoogte', 'pop-up' => 'Pop-up', 'binnenkort' => 'Binnenkort-pagina'];

/** Een nieuwe geheime code voor de links in de e-mails (bevestigen en afmelden). */
function interesse_token(): string
{
    return bin2hex(random_bytes(24));
}

function interesse_hash(string $token): string
{
    return hash('sha256', $token);
}

function interesse_link(string $soort, string $token, string $taal = 'nl'): string
{
    return app_url('op-de-hoogte.php?' . $soort . '=' . $token . ($taal === 'en' ? '&taal=en' : ''));
}

/** De aanmelding bij een code uit een link, of null. */
function interesse_via_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        return null;
    }
    return rij('SELECT * FROM interesse WHERE token_hash = ?', [interesse_hash($token)]) ?: null;
}

/** De persoonlijke afmeldlink van iemand op de lijst (voor onder elke e-mail). */
function interesse_afmeldlink(array $rij): string
{
    return interesse_link('afmelden', ontsleutel((string) $rij['token']), (string) ($rij['taal'] ?? 'nl'));
}

/** Het huisadres van een aanmelding (ontsleuteld): ['straat' => …, 'postcode' => …, 'plaats' => …], of een lege lijst. */
function interesse_adres(array $rij): array
{
    $json = json_decode(ontsleutel((string) ($rij['adres'] ?? '')) ?: 'null', true);
    return is_array($json) ? array_map('strval', array_intersect_key($json, array_flip(['straat', 'postcode', 'plaats']))) : [];
}

/** Het adres op één regel, bijvoorbeeld "Voorlandpad 17, 1098 TZ Amsterdam". */
function interesse_adres_tekst(array $rij): string
{
    $a = interesse_adres($rij);
    return $a ? trim(($a['straat'] ?? '') . ', ' . trim(($a['postcode'] ?? '') . ' ' . ($a['plaats'] ?? '')), ', ') : '';
}

/**
 * Meldt iemand aan (of opnieuw, met een nieuwe link) en stuurt de bevestigingsmail.
 * Staat het adres al bevestigd op de lijst, dan krijgt die persoon alleen een korte mail.
 * $pakket: de naam van het kind en het huisadres (straat, postcode, plaats) voor het Sporty-pakket.
 */
function interesse_aanmelden(string $naam, string $email, string $telefoon, string $bron = 'website', array $pakket = []): void
{
    $kindNaam = (string) ($pakket['kind_naam'] ?? '');
    $adres = array_filter(['straat' => (string) ($pakket['straat'] ?? ''), 'postcode' => (string) ($pakket['postcode'] ?? ''), 'plaats' => (string) ($pakket['plaats'] ?? '')], fn ($v) => $v !== '');
    $adresVersleuteld = $adres ? versleutel(json_encode($adres, JSON_UNESCAPED_UNICODE)) : '';
    $kindVersleuteld = $kindNaam !== '' ? versleutel($kindNaam) : '';
    $voornaam = explode(' ', trim($naam))[0];
    // Via de binnenkort-pagina: een mail zodra de website live is (inc/binnenkort.php)
    $liveMail = $bron === 'binnenkort' ? 1 : 0;
    $bestaand = rij('SELECT * FROM interesse WHERE lower(email) = lower(?)', [$email]);
    if ($bestaand && $bestaand['bevestigd_op']) {
        if ($liveMail && !(int) $bestaand['live_mail']) {
            q('UPDATE interesse SET live_mail = 1 WHERE id = ?', [$bestaand['id']]);
        }
        stuur_mailtekst($email, 'op_de_hoogte_al_op_lijst', ['voornaam' => $voornaam, 'afmeldlink' => interesse_afmeldlink($bestaand)]);
        return;
    }
    $token = interesse_token();
    if ($bestaand) {
        q('UPDATE interesse SET naam = ?, telefoon = ?, kind_naam = ?, adres = ?, bron = ?, live_mail = max(live_mail, ?), token_hash = ?, token = ?, taal = ?, aangemaakt_op = ? WHERE id = ?', [$naam, $telefoon, $kindVersleuteld, $adresVersleuteld, $bron, $liveMail, interesse_hash($token), versleutel($token), taal(), nu(), $bestaand['id']]);
    } else {
        q('INSERT INTO interesse (naam, email, telefoon, kind_naam, adres, token_hash, token, bron, live_mail, taal, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$naam, $email, $telefoon, $kindVersleuteld, $adresVersleuteld, interesse_hash($token), versleutel($token), $bron, $liveMail, taal(), nu()]);
    }
    // De bevestigingsmail is akkoord (Sporty, november 2026); aan te passen onder E-mails. Het pakket gaat per post.
    stuur_mailtekst($email, 'op_de_hoogte_bevestigen', ['voornaam' => $voornaam, 'link' => interesse_link('bevestig', $token, taal()), 'dagen' => INTERESSE_ONBEVESTIGD_DAGEN]);
}

/** Wist een aanmelding helemaal (afmelden, of door het team). */
function interesse_verwijder(array $rij, string $reden): void
{
    q('DELETE FROM interesse WHERE id = ?', [$rij['id']]);
    log_actie('Blijf op de hoogte: gegevens gewist', $reden, null, 'wijziging', 'interesse:' . $rij['id']);
}

/** Aantal bevestigde aanmeldingen dat het kennismakingspakket nog niet heeft gekregen (voor het tellertje in het menu). */
function open_interesse(): int
{
    return (int) waarde('SELECT COUNT(*) FROM interesse WHERE bevestigd_op IS NOT NULL AND pakket_op IS NULL');
}
