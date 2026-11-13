<?php
/* Inloggen, sessies, rollen en wachtwoordlinks. */

declare(strict_types=1);

const SESSIE_MAX_INACTIEF = 2 * 3600;   // na 2 uur niets doen opnieuw inloggen
const SESSIE_MAX_DUUR = 12 * 3600;      // en hoe dan ook na 12 uur
const MAX_CONTROLEPOGINGEN = 5;         // eigen wachtwoord of code opnieuw invullen, binnen een sessie
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
    // Eigen sessiecookie en eigen sessiemap per organisatie (in de eigen datamap)
    session_name('vck_' . preg_replace('/[^a-z0-9]/', '', strtolower((string) cfg('organisatie', 'standaard'))));
    $sessiemap = data_dir() . '/sessies';
    if (!is_dir($sessiemap)) {
        mkdir($sessiemap, 0700, true);
    }
    session_save_path($sessiemap);
    ini_set('session.gc_maxlifetime', (string) SESSIE_MAX_INACTIEF);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => basis_pad(),
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Een sessie hoort bij één organisatie; anders opnieuw beginnen
    if (isset($_SESSION['organisatie']) && $_SESSION['organisatie'] !== cfg('organisatie')) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $laatst = $_SESSION['laatst_actief'] ?? 0;
    if (!empty($_SESSION['gebruiker_id']) && time() - $laatst > SESSIE_MAX_INACTIEF) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('info', 'Je was een tijdje niet actief. Log opnieuw in.');
    } elseif (!empty($_SESSION['gebruiker_id']) && time() - (int) ($_SESSION['ingelogd_op'] ?? 0) > SESSIE_MAX_DUUR) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('info', 'Je bent al een hele dag ingelogd. Log voor de zekerheid opnieuw in.');
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
        // Wachtwoord gewijzigd of tweestapsverificatie gereset? Dan geldt deze sessie niet meer.
        if ($cache[$id] && (int) $cache[$id]['sessie_versie'] !== (int) ($_SESSION['sessie_versie'] ?? -1)) {
            $cache[$id] = null;
            $_SESSION = [];
            session_regenerate_id(true);
        }
    }
    return $cache[$id];
}

function is_team(?array $gebruiker = null): bool
{
    $gebruiker ??= huidige_gebruiker();
    return $gebruiker !== null && in_array($gebruiker['rol'], ['beheerder', 'medewerker'], true);
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
    // Tweestapsverificatie verplicht (team, en ouders als de beheerder dat instelt)? Eerst instellen.
    $pagina = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!$gebruiker['mfa_actief'] && mfa_verplicht($gebruiker) && !in_array($pagina, ['tweestaps.php', 'uitloggen.php'], true)) {
        redirect('tweestaps.php');
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
    if ($aantal >= $max) {
        // Al geblokkeerd: niet verder bijschrijven, anders groeit de tabel bij een aanval onbeperkt
        return true;
    }
    q('INSERT INTO inlogpogingen (sleutel, tijd) VALUES (?, ?)', [$sleutel, nu()]);
    return false;
}

/** Aantal geregistreerde pogingen met deze sleutel in het afgelopen kwartier. */
function recente_pogingen(string $sleutel): int
{
    return (int) waarde('SELECT COUNT(*) FROM inlogpogingen WHERE sleutel = ? AND tijd >= ?', [$sleutel, date('Y-m-d H:i:s', time() - INLOG_BLOKKADE_MINUTEN * 60)]);
}

function registreer_poging(string $sleutel): void
{
    q('INSERT INTO inlogpogingen (sleutel, tijd) VALUES (?, ?)', [$sleutel, nu()]);
}

function registreer_mislukte_poging(string $email): void
{
    q('INSERT INTO inlogpogingen (sleutel, tijd) VALUES (?, ?), (?, ?)', [
        'e:' . hash('sha256', mb_strtolower($email)), nu(),
        'i:' . ip_adres(), nu(),
    ]);
}

/**
 * Probeert in te loggen. Geeft een foutmelding terug, of null als het wachtwoord klopt.
 * Heeft de gebruiker tweestapsverificatie, dan is hij daarna nog niet ingelogd:
 * de code volgt op inloggen-code.php (zie mfa_wacht()).
 */
function probeer_in_te_loggen(string $email, string $wachtwoord): ?string
{
    if (inlog_geblokkeerd($email)) {
        return 'Te veel pogingen. Wacht ' . INLOG_BLOKKADE_MINUTEN . ' minuten en probeer het dan opnieuw, of vraag een nieuw wachtwoord aan.';
    }
    $gebruiker = rij('SELECT * FROM gebruikers WHERE email = ?', [$email]);
    // Ook zonder bestaand account een hash controleren (met dezelfde kosten), zodat de responstijd niets verraadt
    $hash = $gebruiker['wachtwoord_hash'] ?? password_hash(random_bytes(16), PASSWORD_DEFAULT);
    $klopt = password_verify($wachtwoord, $hash);
    if (!$gebruiker || !$klopt || !$gebruiker['wachtwoord_hash']) {
        registreer_mislukte_poging($email);
        if ($gebruiker) {
            log_actie('Inloggen mislukt', 'verkeerd wachtwoord', (int) $gebruiker['id'], 'beveiliging', 'gebruiker:' . $gebruiker['id']);
        }
        return 'Dit e-mailadres en wachtwoord horen niet bij elkaar. Controleer ze en probeer het opnieuw.';
    }
    if ($gebruiker['status'] === 'gestopt') {
        return 'Dit account is niet meer actief. Neem contact op met Sporty als je denkt dat dit niet klopt.';
    }
    if (password_needs_rehash($gebruiker['wachtwoord_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE gebruikers SET wachtwoord_hash = ? WHERE id = ?', [password_hash($wachtwoord, PASSWORD_DEFAULT), $gebruiker['id']]);
    }
    na_wachtwoord((int) $gebruiker['id']);
    return null;
}

/** Wachtwoord (of wachtwoordlink) is goed: inloggen, of eerst nog de code vragen. */
function na_wachtwoord(int $gebruikerId): void
{
    $gebruiker = rij('SELECT * FROM gebruikers WHERE id = ?', [$gebruikerId]);
    if ($gebruiker && $gebruiker['mfa_actief']) {
        session_regenerate_id(true);
        $_SESSION['mfa_wacht'] = ['id' => $gebruikerId, 'sinds' => time()];
        return;
    }
    log_in_als($gebruikerId);
}

/** Id van de gebruiker die zijn wachtwoord goed heeft en nog de code moet invullen (maximaal 5 minuten). */
function mfa_wacht(): ?int
{
    $wacht = $_SESSION['mfa_wacht'] ?? null;
    if (!$wacht || time() - (int) $wacht['sinds'] > 300) {
        unset($_SESSION['mfa_wacht']);
        return null;
    }
    return (int) $wacht['id'];
}

function log_in_als(int $gebruikerId, string $methode = 'wachtwoord'): void
{
    session_regenerate_id(true);
    unset($_SESSION['mfa_wacht'], $_SESSION['csrf']);
    $_SESSION['gebruiker_id'] = $gebruikerId;
    $_SESSION['organisatie'] = cfg('organisatie');
    $_SESSION['laatst_actief'] = time();
    $_SESSION['ingelogd_op'] = time();
    $_SESSION['sessie_versie'] = (int) waarde('SELECT sessie_versie FROM gebruikers WHERE id = ?', [$gebruikerId]);
    q('UPDATE gebruikers SET laatst_ingelogd = ? WHERE id = ?', [nu(), $gebruikerId]);
    log_actie('Ingelogd', $methode, $gebruikerId, 'inloggen', 'gebruiker:' . $gebruikerId);
}

/**
 * Sluit alle andere sessies van deze gebruiker af (na een nieuw wachtwoord of een reset van de
 * tweestapsverificatie). Is het de ingelogde gebruiker zelf, dan blijft de huidige sessie geldig.
 */
function beeindig_andere_sessies(int $gebruikerId): void
{
    q('UPDATE gebruikers SET sessie_versie = sessie_versie + 1 WHERE id = ?', [$gebruikerId]);
    if ((int) ($_SESSION['gebruiker_id'] ?? 0) === $gebruikerId) {
        $_SESSION['sessie_versie'] = (int) waarde('SELECT sessie_versie FROM gebruikers WHERE id = ?', [$gebruikerId]);
        session_regenerate_id(true);
    }
}

/**
 * Rem op het raden van het eigen wachtwoord of een code binnen een sessie (bijvoorbeeld met een
 * gekaapte sessie). $soort onderscheidt de plek, zoals 'p' (wachtwoord) of 'm' (code).
 */
function te_veel_controles(string $soort, int $gebruikerId): bool
{
    return recente_pogingen($soort . ':' . $gebruikerId) >= MAX_CONTROLEPOGINGEN;
}

function mislukte_controle(string $soort, int $gebruikerId): void
{
    registreer_poging($soort . ':' . $gebruikerId);
    log_actie('Controle mislukt', $soort, $gebruikerId, 'beveiliging', 'gebruiker:' . $gebruikerId);
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
