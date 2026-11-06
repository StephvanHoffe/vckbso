<?php
/*
 * Blijf op de hoogte (november 2026): ouders die interesse hebben laten op de website hun naam, e-mailadres
 * en eventueel telefoonnummer achter (blijf-op-de-hoogte.html) en krijgen een Sporty-kennismakingspakket.
 *
 * Met dubbele bevestiging: pas na een klik op de link in de e-mail staat iemand op de lijst. Zo kan niemand
 * een ander aanmelden. Afmelden kan altijd, met de link onder elke e-mail; de gegevens worden dan gewist.
 * Het team ziet de lijst bij "Op de hoogte" (interesse.php), kan een update sturen en een CSV maken.
 */

declare(strict_types=1);

/** Niet bevestigd: na zoveel dagen gewist. */
const INTERESSE_ONBEVESTIGD_DAGEN = 14;
const MAX_INTERESSE_PER_KWARTIER = 5;

/** Een nieuwe geheime code voor de links in de e-mails (bevestigen en afmelden). */
function interesse_token(): string
{
    return bin2hex(random_bytes(24));
}

function interesse_hash(string $token): string
{
    return hash('sha256', $token);
}

function interesse_link(string $soort, string $token): string
{
    return app_url('op-de-hoogte.php?' . $soort . '=' . $token);
}

/** De aanmelding bij een code uit een link, of null. */
function interesse_via_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        return null;
    }
    return rij('SELECT * FROM interesse WHERE token_hash = ?', [interesse_hash($token)]) ?: null;
}

/** De afmeldregel onder elke e-mail aan iemand op de lijst. */
function interesse_voet(array $rij): string
{
    $token = ontsleutel((string) $rij['token']);
    return "\n\n--\nJe krijgt deze e-mail omdat je je bij Sporty hebt aangemeld om op de hoogte te blijven.\nGeen e-mails meer? Meld je hier af: " . interesse_link('afmelden', $token);
}

/**
 * Meldt iemand aan (of opnieuw, met een nieuwe link) en stuurt de bevestigingsmail.
 * Staat het adres al bevestigd op de lijst, dan krijgt die persoon alleen een korte mail.
 */
function interesse_aanmelden(string $naam, string $email, string $telefoon, string $bron = 'website'): void
{
    $voornaam = explode(' ', trim($naam))[0];
    $bestaand = rij('SELECT * FROM interesse WHERE lower(email) = lower(?)', [$email]);
    if ($bestaand && $bestaand['bevestigd_op']) {
        stuur_mail($email, 'Je staat al op de lijst van Sporty', "Hoi {$voornaam},\n\nJe hebt je net opnieuw aangemeld om op de hoogte te blijven van Sporty, maar je stond al op de lijst. Je hoeft dus niets te doen!" . interesse_voet($bestaand));
        return;
    }
    $token = interesse_token();
    if ($bestaand) {
        q('UPDATE interesse SET naam = ?, telefoon = ?, token_hash = ?, token = ?, aangemaakt_op = ? WHERE id = ?', [$naam, $telefoon, interesse_hash($token), versleutel($token), nu(), $bestaand['id']]);
    } else {
        q('INSERT INTO interesse (naam, email, telefoon, token_hash, token, bron, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, ?)', [$naam, $email, $telefoon, interesse_hash($token), versleutel($token), $bron, nu()]);
    }
    // TODO: tekst van de bevestigingsmail laten controleren door Sporty, en laten weten wat er in het kennismakingspakket zit en hoe ouders het krijgen
    stuur_mail($email, 'Bevestig je aanmelding bij Sporty', "Hoi {$voornaam},\n\n"
        . "Leuk dat je op de hoogte wilt blijven van Sporty! Bevestig je aanmelding met deze link:\n" . interesse_link('bevestig', $token) . "\n\n"
        . "Daarna houden we je op de hoogte van het laatste nieuws, en krijg je een Sporty-kennismakingspakket. We laten je weten hoe je het pakket krijgt.\n\n"
        . "Heb je je niet zelf aangemeld? Dan hoef je niets te doen. Zonder bevestiging wissen we je gegevens binnen " . INTERESSE_ONBEVESTIGD_DAGEN . " dagen.\n\n"
        . "Tot snel!\nTeam Sporty (en Henny: woef!)");
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
