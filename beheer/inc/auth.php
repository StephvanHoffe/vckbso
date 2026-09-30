<?php
/* Inloggen, sessies, rollen en wachtwoordlinks. */

declare(strict_types=1);

const SESSIE_MAX_INACTIEF = 2 * 3600;   // na 2 uur niets doen opnieuw inloggen
const MAX_INLOGPOGINGEN = 5;            // per e-mailadres en per IP ...
const INLOG_BLOKKADE_MINUTEN = 15;      // ... binnen dit aantal minuten
const MIN_WACHTWOORD_LENGTE = 10;

function start_sessie(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('vck_beheer');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => basis_pad(),
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $laatst = $_SESSION['laatst_actief'] ?? 0;
    if (!empty($_SESSION['gebruiker_id']) && time() - $laatst > SESSIE_MAX_INACTIEF) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('info', 'Je was een tijdje niet actief. Log opnieuw in.');
    }
    $_SESSION['laatst_actief'] = time();
}

/** De ingelogde gebruiker. Met $vernieuw opnieuw uit de database (na een wijziging). */
function huidige_gebruiker(bool $vernieuw = false): ?array
{
    static $cache = [];
    $id = $_SESSION['gebruiker_id'] ?? null;
    if (!$id) {
        return null;
    }
    if ($vernieuw || !array_key_exists($id, $cache)) {
        $cache[$id] = rij("SELECT * FROM gebruikers WHERE id = ? AND status != 'gestopt'", [$id]);
    }
    return $cache[$id];
}

function is_team(?array $gebruiker = null): bool
{
    $gebruiker ??= huidige_gebruiker();
    return $gebruiker !== null && in_array($gebruiker['rol'], ['beheerder', 'medewerker'], true);
}

function is_beheerder(?array $gebruiker = null): bool
{
    $gebruiker ??= huidige_gebruiker();
    return $gebruiker !== null && $gebruiker['rol'] === 'beheerder';
}

function is_ouder(?array $gebruiker = null): bool
{
    $gebruiker ??= huidige_gebruiker();
    return $gebruiker !== null && $gebruiker['rol'] === 'ouder';
}

/**
 * Pagina alleen voor ingelogde gebruikers, eventueel met bepaalde rollen.
 * Geeft de ingelogde gebruiker terug.
 */
function vereis_login(array $rollen = []): array
{
    $gebruiker = huidige_gebruiker();
    if (!$gebruiker) {
        $terug = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php') . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']);
        redirect('inloggen.php?terug=' . rawurlencode($terug));
    }
    if ($rollen && !in_array($gebruiker['rol'], $rollen, true)) {
        geen_toegang();
    }
    return $gebruiker;
}

function vereis_team(): array
{
    return vereis_login(['beheerder', 'medewerker']);
}

function vereis_beheerder(): array
{
    return vereis_login(['beheerder']);
}

/** Alleen een relatief pad binnen de beheeromgeving als terug-adres. */
function veilig_terug(string $terug): string
{
    return preg_match('/^[a-z0-9-]+\.php(\?[A-Za-z0-9=&%_.+-]*)?$/', $terug) ? $terug : 'index.php';
}

function inlog_geblokkeerd(string $email): bool
{
    $sinds = date('Y-m-d H:i:s', time() - INLOG_BLOKKADE_MINUTEN * 60);
    q('DELETE FROM inlogpogingen WHERE tijd < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    $perEmail = (int) waarde('SELECT COUNT(*) FROM inlogpogingen WHERE sleutel = ? AND tijd >= ?', ['e:' . hash('sha256', mb_strtolower($email)), $sinds]);
    $perIp = (int) waarde('SELECT COUNT(*) FROM inlogpogingen WHERE sleutel = ? AND tijd >= ?', ['i:' . ip_adres(), $sinds]);
    return $perEmail >= MAX_INLOGPOGINGEN || $perIp >= MAX_INLOGPOGINGEN * 4;
}

/**
 * Rem op herhaalde verzoeken (bijvoorbeeld wachtwoordlinks): telt dit verzoek
 * mee en geeft true als er binnen het kwartier al $max waren.
 */
function te_veel_verzoeken(string $sleutel, int $max): bool
{
    $sinds = date('Y-m-d H:i:s', time() - INLOG_BLOKKADE_MINUTEN * 60);
    $aantal = (int) waarde('SELECT COUNT(*) FROM inlogpogingen WHERE sleutel = ? AND tijd >= ?', [$sleutel, $sinds]);
    q('INSERT INTO inlogpogingen (sleutel, tijd) VALUES (?, ?)', [$sleutel, nu()]);
    return $aantal >= $max;
}

function registreer_mislukte_poging(string $email): void
{
    q('INSERT INTO inlogpogingen (sleutel, tijd) VALUES (?, ?), (?, ?)', [
        'e:' . hash('sha256', mb_strtolower($email)), nu(),
        'i:' . ip_adres(), nu(),
    ]);
}

/** Probeert in te loggen. Geeft een foutmelding terug, of null als het gelukt is. */
function probeer_in_te_loggen(string $email, string $wachtwoord): ?string
{
    if (inlog_geblokkeerd($email)) {
        return 'Te veel pogingen. Wacht ' . INLOG_BLOKKADE_MINUTEN . ' minuten en probeer het dan opnieuw, of vraag een nieuw wachtwoord aan.';
    }
    $gebruiker = rij('SELECT * FROM gebruikers WHERE email = ?', [$email]);
    // Ook zonder bestaand account een hash controleren, zodat de responstijd niets verraadt
    $hash = $gebruiker['wachtwoord_hash'] ?? '$2y$12$RwGnUuQlm.UHd3LuB7b31.y9TGGWJzH2KvTgHHV8FPK.uJqxq.p2K';
    $klopt = password_verify($wachtwoord, $hash);
    if (!$gebruiker || !$klopt || !$gebruiker['wachtwoord_hash']) {
        registreer_mislukte_poging($email);
        return 'Dit e-mailadres en wachtwoord horen niet bij elkaar. Controleer ze en probeer het opnieuw.';
    }
    if ($gebruiker['status'] === 'gestopt') {
        return 'Dit account is niet meer actief. Neem contact op met BSO VCK als je denkt dat dit niet klopt.';
    }
    if (password_needs_rehash($gebruiker['wachtwoord_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE gebruikers SET wachtwoord_hash = ? WHERE id = ?', [password_hash($wachtwoord, PASSWORD_DEFAULT), $gebruiker['id']]);
    }
    log_in_als((int) $gebruiker['id']);
    return null;
}

function log_in_als(int $gebruikerId): void
{
    session_regenerate_id(true);
    $_SESSION['gebruiker_id'] = $gebruikerId;
    $_SESSION['laatst_actief'] = time();
    unset($_SESSION['csrf']);
    q('UPDATE gebruikers SET laatst_ingelogd = ? WHERE id = ?', [nu(), $gebruikerId]);
    log_actie('Ingelogd', '', $gebruikerId);
}

function log_uit(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/** Geeft een foutmelding als het wachtwoord niet goed genoeg is. */
function controleer_wachtwoord(string $wachtwoord, string $herhaling): ?string
{
    if (mb_strlen($wachtwoord) < MIN_WACHTWOORD_LENGTE) {
        return 'Kies een wachtwoord van minstens ' . MIN_WACHTWOORD_LENGTE . ' tekens. Een zin van een paar woorden is makkelijk te onthouden.';
    }
    if (mb_strlen($wachtwoord) > 200) {
        return 'Dit wachtwoord is te lang.';
    }
    if ($wachtwoord !== $herhaling) {
        return 'De twee wachtwoorden zijn niet hetzelfde.';
    }
    return null;
}

/* ---------- Links om een wachtwoord in te stellen (uitnodiging of vergeten) ---------- */

function maak_wachtwoordlink(int $gebruikerId, int $geldigUren = 24): string
{
    $token = bin2hex(random_bytes(32));
    q("UPDATE tokens SET gebruikt = 1 WHERE gebruiker_id = ? AND soort = 'wachtwoord'", [$gebruikerId]);
    q("INSERT INTO tokens (gebruiker_id, soort, token_hash, verloopt_op) VALUES (?, 'wachtwoord', ?, ?)", [
        $gebruikerId,
        hash('sha256', $token),
        date('Y-m-d H:i:s', time() + $geldigUren * 3600),
    ]);
    return app_url('wachtwoord-instellen.php?token=' . $token);
}

function zoek_wachtwoordtoken(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    return rij(
        "SELECT t.id AS token_id, g.* FROM tokens t JOIN gebruikers g ON g.id = t.gebruiker_id
         WHERE t.token_hash = ? AND t.soort = 'wachtwoord' AND t.gebruikt = 0 AND t.verloopt_op > ? AND g.status != 'gestopt'",
        [hash('sha256', $token), nu()]
    );
}

/* ---------- Toegang tot gegevens van kinderen ---------- */

/** Het kind, als de ingelogde gebruiker het mag zien (team: alle kinderen, ouder: eigen kinderen). */
function kind_met_toegang(int $kindId): array
{
    $gebruiker = vereis_login();
    $kind = rij('SELECT k.*, g.naam AS groepnaam FROM kinderen k LEFT JOIN groepen g ON g.id = k.groep_id WHERE k.id = ?', [$kindId]);
    if (!$kind) {
        niet_gevonden();
    }
    if (!is_team($gebruiker) && (int) $kind['ouder_id'] !== (int) $gebruiker['id']) {
        niet_gevonden();
    }
    return $kind;
}
