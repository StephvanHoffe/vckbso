<?php
/*
 * Tweestapsverificatie met een authenticator-app (TOTP, RFC 6238), plus
 * eenmalige herstelcodes. Verplicht voor het team; voor ouders instelbaar.
 * Het geheim wordt versleuteld opgeslagen. Een code kan maar één keer worden
 * gebruikt (bescherming tegen hergebruik).
 */

declare(strict_types=1);

const MFA_PERIODE = 30;
const MFA_HERSTELCODES = 10;

function base32_codeer(string $data): string
{
    $alfabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($data) as $teken) {
        $bits .= str_pad(decbin(ord($teken)), 8, '0', STR_PAD_LEFT);
    }
    $uit = '';
    foreach (str_split($bits, 5) as $groep) {
        $uit .= $alfabet[bindec(str_pad($groep, 5, '0'))];
    }
    return $uit;
}

function base32_decodeer(string $tekst): string
{
    $alfabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $tekst = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $tekst));
    $bits = '';
    foreach (str_split($tekst) as $teken) {
        $bits .= str_pad(decbin(strpos($alfabet, $teken)), 5, '0', STR_PAD_LEFT);
    }
    $uit = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $uit .= chr(bindec($byte));
        }
    }
    return $uit;
}

function mfa_nieuw_geheim(): string
{
    return base32_codeer(random_bytes(20));
}

function totp_code(string $geheimBase32, int $stap): string
{
    $hash = hash_hmac('sha1', pack('J', $stap), base32_decodeer($geheimBase32), true);
    $offset = ord($hash[19]) & 0x0f;
    $getal = ((ord($hash[$offset]) & 0x7f) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
    return str_pad((string) ($getal % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Klopt de code voor dit geheim? Geeft de gebruikte tijdstap terug (±30 seconden speling), of null. */
function totp_controleer(string $geheimBase32, string $code, int $nietVoor = 0): ?int
{
    $code = preg_replace('/\s+/', '', $code);
    if (!preg_match('/^\d{6}$/', $code)) {
        return null;
    }
    $nu = intdiv(time(), MFA_PERIODE);
    for ($stap = $nu - 1; $stap <= $nu + 1; $stap++) {
        if ($stap > $nietVoor && hash_equals(totp_code($geheimBase32, $stap), $code)) {
            return $stap;
        }
    }
    return null;
}

function mfa_uri(string $geheimBase32, string $email): string
{
    $uitgever = instelling('bedrijfsnaam', 'Sporty');
    return 'otpauth://totp/' . rawurlencode($uitgever . ':' . $email) . '?secret=' . $geheimBase32 . '&issuer=' . rawurlencode($uitgever) . '&digits=6&period=' . MFA_PERIODE;
}

/** Moet deze gebruiker tweestapsverificatie gebruiken? */
function mfa_verplicht(array $gebruiker): bool
{
    return $gebruiker['rol'] !== 'ouder' || instelling('mfa_ouders', 'optioneel') === 'verplicht';
}

/** Controleert een code (authenticator of herstelcode) bij het inloggen. */
function mfa_controleer(array $gebruiker, string $code): bool
{
    $code = trim($code);
    if (preg_match('/^[a-z0-9]{4}-?[a-z0-9]{4}$/i', $code)) {
        return mfa_gebruik_herstelcode((int) $gebruiker['id'], $code);
    }
    $geheim = ontsleutel($gebruiker['mfa_geheim']);
    if ($geheim === '') {
        return false;
    }
    $stap = totp_controleer($geheim, $code, (int) $gebruiker['mfa_laatste_stap']);
    if ($stap === null) {
        return false;
    }
    q('UPDATE gebruikers SET mfa_laatste_stap = ? WHERE id = ?', [$stap, $gebruiker['id']]);
    return true;
}

function mfa_zet_aan(int $gebruikerId, string $geheimBase32, int $stap): array
{
    q('UPDATE gebruikers SET mfa_geheim = ?, mfa_actief = 1, mfa_laatste_stap = ? WHERE id = ?', [versleutel($geheimBase32), $stap, $gebruikerId]);
    log_actie('Tweestapsverificatie aangezet', '', $gebruikerId, 'beveiliging', 'gebruiker:' . $gebruikerId);
    return mfa_herstelcodes_maken($gebruikerId);
}

function mfa_zet_uit(int $gebruikerId, string $reden): void
{
    q('UPDATE gebruikers SET mfa_geheim = NULL, mfa_actief = 0, mfa_laatste_stap = 0 WHERE id = ?', [$gebruikerId]);
    q('DELETE FROM mfa_herstelcodes WHERE gebruiker_id = ?', [$gebruikerId]);
    log_actie('Tweestapsverificatie uitgezet', $reden, null, 'beveiliging', 'gebruiker:' . $gebruikerId);
}

/** Maakt nieuwe herstelcodes (de oude vervallen). Geeft de codes één keer leesbaar terug. */
function mfa_herstelcodes_maken(int $gebruikerId): array
{
    q('DELETE FROM mfa_herstelcodes WHERE gebruiker_id = ?', [$gebruikerId]);
    $codes = [];
    $tekens = 'abcdefghjkmnpqrstuvwxyz23456789';
    for ($i = 0; $i < MFA_HERSTELCODES; $i++) {
        $code = '';
        for ($j = 0; $j < 8; $j++) {
            $code .= $tekens[random_int(0, strlen($tekens) - 1)];
        }
        $codes[] = substr($code, 0, 4) . '-' . substr($code, 4);
        q('INSERT INTO mfa_herstelcodes (gebruiker_id, code_hash) VALUES (?, ?)', [$gebruikerId, hash('sha256', $code)]);
    }
    return $codes;
}

function mfa_gebruik_herstelcode(int $gebruikerId, string $code): bool
{
    $hash = hash('sha256', strtolower(str_replace('-', '', trim($code))));
    $rij = rij('SELECT id FROM mfa_herstelcodes WHERE gebruiker_id = ? AND code_hash = ? AND gebruikt_op IS NULL', [$gebruikerId, $hash]);
    if (!$rij) {
        return false;
    }
    q('UPDATE mfa_herstelcodes SET gebruikt_op = ? WHERE id = ?', [nu(), $rij['id']]);
    log_actie('Herstelcode gebruikt bij inloggen', '', $gebruikerId, 'beveiliging', 'gebruiker:' . $gebruikerId);
    return true;
}

function mfa_herstelcodes_over(int $gebruikerId): int
{
    return (int) waarde('SELECT COUNT(*) FROM mfa_herstelcodes WHERE gebruiker_id = ? AND gebruikt_op IS NULL', [$gebruikerId]);
}
