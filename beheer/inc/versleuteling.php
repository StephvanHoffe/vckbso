<?php
/*
 * Versleuteling van gevoelige gegevens (libsodium, XSalsa20-Poly1305).
 *
 * Versleuteld opgeslagen: bijzonderheden van kinderen (gezondheid), observaties
 * (ontwikkeling), berichten, AVG-verzoeken, geheimen voor tweestapsverificatie,
 * foto's en exports. De sleutel staat in config.php, dus niet in de database en
 * niet in de back-ups: wie alleen de database of een back-up heeft, kan deze
 * gegevens niet lezen. Elke organisatie heeft een eigen sleutel.
 */

declare(strict_types=1);

const VERSLEUTELD_PREFIX = 'enc:v1:';
const BESTAND_MAGIC = "VCKENC1\n";

function sleutel(): string
{
    static $sleutel = null;
    if ($sleutel !== null) {
        return $sleutel;
    }
    $b64 = trim((string) cfg('sleutel', ''));
    if ($b64 !== '') {
        $ruw = base64_decode($b64, true);
        if ($ruw === false || strlen($ruw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            stop_met_fout('De encryptiesleutel in config.php is ongeldig. Maak een nieuwe met: php -r "echo base64_encode(random_bytes(32));"');
        }
        return $sleutel = $ruw;
    }
    if (!cfg('ontwikkelmodus', false)) {
        stop_met_fout('De encryptiesleutel ontbreekt in config.php (instelling "sleutel"). Zonder sleutel start de beheeromgeving niet.');
    }
    // Alleen om lokaal te testen: een sleutel in de datamap
    $pad = data_dir() . '/.ontwikkelsleutel';
    if (!is_file($pad)) {
        file_put_contents($pad, base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)), LOCK_EX);
        chmod($pad, 0600);
    }
    return $sleutel = (string) base64_decode(trim((string) file_get_contents($pad)), true);
}

/** Versleutelt tekst voor opslag. Lege tekst blijft leeg, zodat "is er iets ingevuld?" blijft werken. */
function versleutel(?string $tekst): string
{
    if ($tekst === null || $tekst === '') {
        return '';
    }
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return VERSLEUTELD_PREFIX . base64_encode($nonce . sodium_crypto_secretbox($tekst, $nonce, sleutel()));
}

/** Ontsleutelt een opgeslagen waarde. Oude, onversleutelde waarden komen ongewijzigd terug. */
function ontsleutel(?string $waarde): string
{
    if ($waarde === null || $waarde === '') {
        return '';
    }
    if (!str_starts_with($waarde, VERSLEUTELD_PREFIX)) {
        return $waarde;
    }
    $ruw = base64_decode(substr($waarde, strlen(VERSLEUTELD_PREFIX)), true);
    if ($ruw === false || strlen($ruw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
        return '[onleesbaar]';
    }
    $tekst = sodium_crypto_secretbox_open(substr($ruw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($ruw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), sleutel());
    return $tekst === false ? '[onleesbaar]' : $tekst;
}

function is_versleuteld(?string $waarde): bool
{
    return $waarde !== null && str_starts_with($waarde, VERSLEUTELD_PREFIX);
}

/** Ontsleutelt de genoemde kolommen in een rij of een lijst rijen. */
function ontsleutel_kolommen(array $rijen, array $kolommen, bool $enkeleRij = false): array
{
    $lijst = $enkeleRij ? [$rijen] : $rijen;
    foreach ($lijst as &$rij) {
        foreach ($kolommen as $kolom) {
            if (array_key_exists($kolom, $rij)) {
                $rij[$kolom] = ontsleutel($rij[$kolom]);
            }
        }
    }
    unset($rij);
    return $enkeleRij ? $lijst[0] : $lijst;
}

/* ---------- Bestanden (foto's, exports) ---------- */

function versleutel_bestand(string $pad, string $inhoud): void
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $tijdelijk = $pad . '.tmp';
    file_put_contents($tijdelijk, BESTAND_MAGIC . $nonce . sodium_crypto_secretbox($inhoud, $nonce, sleutel()), LOCK_EX);
    chmod($tijdelijk, 0600);
    rename($tijdelijk, $pad);
}

function lees_versleuteld_bestand(string $pad): ?string
{
    $data = @file_get_contents($pad);
    if ($data === false) {
        return null;
    }
    if (!str_starts_with($data, BESTAND_MAGIC)) {
        return $data; // ouder, onversleuteld bestand
    }
    $data = substr($data, strlen(BESTAND_MAGIC));
    $inhoud = sodium_crypto_secretbox_open(substr($data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), sleutel());
    return $inhoud === false ? null : $inhoud;
}

/** Foutpagina (of foutmelding op de opdrachtregel) en stoppen. */
function stop_met_fout(string $tekst): never
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $tekst . "\n");
        exit(1);
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="nl"><meta charset="utf-8"><title>Beheeromgeving niet beschikbaar</title><body style="font-family:sans-serif;max-width:40rem;margin:4rem auto;padding:0 1rem"><h1>Even niet beschikbaar</h1><p>' . htmlspecialchars($tekst, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
    exit;
}
