<?php
/*
 * E-mail versturen via een eigen mailserver (SMTP), zonder extra software.
 * - SSL/TLS (meestal poort 465) of STARTTLS (meestal poort 587); zonder versleuteling versturen kan niet.
 * - Inloggen met AUTH PLAIN of AUTH LOGIN.
 * - Het certificaat van de mailserver wordt altijd gecontroleerd.
 * - De tekst gaat in base64: zo is elke regel kort, blijven é en € heel en begint er geen regel met een punt.
 */

declare(strict_types=1);

function smtp_instellingen(): array
{
    return [
        'host' => trim((string) koppeling('smtp.host')),
        'poort' => (int) koppeling('smtp.poort'),
        'beveiliging' => koppeling('smtp.beveiliging') === 'ssl' ? 'ssl' : 'starttls',
        'gebruiker' => (string) koppeling('smtp.gebruiker'),
        'wachtwoord' => (string) koppeling('smtp.wachtwoord'),
    ];
}

function smtp_adres_ok(string $adres): bool
{
    return $adres !== '' && !preg_match('/[\r\n<>]/', $adres) && filter_var($adres, FILTER_VALIDATE_EMAIL) !== false;
}

/** Een naam in de kop van een e-mail, zoals "Sporty" of =?UTF-8?Q?...?= voor letters als é. */
function smtp_naam(string $naam): string
{
    $naam = trim((string) preg_replace('/[\r\n\t]+/', ' ', $naam));
    if (preg_match('/[^\x20-\x7e]/', $naam)) {
        return mb_encode_mimeheader($naam, 'UTF-8', 'Q');
    }
    return '"' . addcslashes($naam, '"\\') . '"';
}

/** Het hele bericht: kop en inhoud, met CRLF. */
function smtp_bericht(string $van, string $vanNaam, string $aan, string $antwoordAan, string $onderwerp, string $tekst): string
{
    $domein = substr((string) strrchr($van, '@'), 1) ?: 'localhost';
    $koppen = [
        'Date: ' . date('r'),
        'From: ' . ($vanNaam !== '' ? smtp_naam($vanNaam) . ' ' : '') . '<' . $van . '>',
        'To: <' . $aan . '>',
    ];
    if ($antwoordAan !== '' && $antwoordAan !== $van) {
        $koppen[] = 'Reply-To: <' . $antwoordAan . '>';
    }
    array_push($koppen,
        'Subject: ' . mb_encode_mimeheader(trim((string) preg_replace('/[\r\n\t]+/', ' ', $onderwerp)), 'UTF-8', 'Q'),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domein . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    );
    $inhoud = str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $tekst));
    return implode("\r\n", $koppen) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($inhoud), 76, "\r\n"));
}

/** Verstuurt één e-mail via SMTP. Gooit een RuntimeException met een begrijpelijke uitleg als het niet lukt. */
function smtp_verstuur(array $server, string $van, string $vanNaam, string $aan, string $antwoordAan, string $onderwerp, string $tekst): void
{
    foreach (['afzender' => $van, 'ontvanger' => $aan] as $wat => $adres) {
        if (!smtp_adres_ok($adres)) {
            throw new RuntimeException("Het e-mailadres van de {$wat} klopt niet.");
        }
    }
    if ($antwoordAan !== '' && !smtp_adres_ok($antwoordAan)) {
        $antwoordAan = '';
    }
    if ($server['host'] === '' || $server['poort'] < 1 || $server['poort'] > 65535) {
        throw new RuntimeException('Vul de mailserver en de poort in.');
    }
    $context = stream_context_create(['ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'peer_name' => $server['host'],
        'SNI_enabled' => true,
    ]]);
    $schema = $server['beveiliging'] === 'ssl' ? 'ssl://' : 'tcp://';
    $verbinding = @stream_socket_client($schema . $server['host'] . ':' . $server['poort'], $foutnummer, $fouttekst, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$verbinding) {
        throw new RuntimeException("Geen verbinding met {$server['host']} op poort {$server['poort']}" . ($fouttekst ? " ({$fouttekst})" : '') . '. Kloppen de server, de poort en de beveiliging?');
    }
    stream_set_timeout($verbinding, 20);

    $lees = function () use ($verbinding): array {
        $antwoord = '';
        while (($regel = fgets($verbinding, 2048)) !== false) {
            $antwoord .= $regel;
            if (strlen($regel) < 4 || $regel[3] !== '-') {
                break;
            }
        }
        if ($antwoord === '') {
            throw new RuntimeException(stream_get_meta_data($verbinding)['timed_out'] ? 'De mailserver reageert niet (time-out).' : 'De mailserver verbrak de verbinding.');
        }
        return [(int) substr($antwoord, 0, 3), trim($antwoord)];
    };
    $stuur = function (string $opdracht, array $verwacht, string $wat) use ($verbinding, $lees): string {
        fwrite($verbinding, $opdracht . "\r\n");
        [$code, $antwoord] = $lees();
        if (!in_array($code, $verwacht, true)) {
            throw new RuntimeException("De mailserver weigerde {$wat}: " . mb_substr(preg_replace('/\s+/', ' ', $antwoord), 0, 200));
        }
        return $antwoord;
    };

    try {
        [$code, $welkom] = $lees();
        if ($code !== 220) {
            throw new RuntimeException('De mailserver gaf geen welkom: ' . mb_substr($welkom, 0, 200));
        }
        $ehlo = 'EHLO ' . ((string) parse_url(basis_url(), PHP_URL_HOST) ?: 'localhost');
        $mogelijk = $stuur($ehlo, [250], 'de begroeting (EHLO)');
        if ($server['beveiliging'] === 'starttls') {
            if (!preg_match('/^250[ -]STARTTLS/mi', $mogelijk)) {
                throw new RuntimeException('Deze mailserver biedt op deze poort geen STARTTLS aan. Kies SSL/TLS (meestal poort 465).');
            }
            $stuur('STARTTLS', [220], 'het beveiligen van de verbinding');
            if (!@stream_socket_enable_crypto($verbinding, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('Het beveiligen van de verbinding lukte niet. Klopt de naam van de mailserver met zijn certificaat?');
            }
            $mogelijk = $stuur($ehlo, [250], 'de begroeting (EHLO)');
        }
        if ($server['gebruiker'] !== '') {
            if (preg_match('/^250[ -]AUTH[ =][^\r\n]*\bPLAIN\b/mi', $mogelijk)) {
                $stuur('AUTH PLAIN ' . base64_encode("\0" . $server['gebruiker'] . "\0" . $server['wachtwoord']), [235], 'het inloggen (gebruikersnaam of wachtwoord)');
            } else {
                $stuur('AUTH LOGIN', [334], 'het inloggen');
                $stuur(base64_encode($server['gebruiker']), [334], 'de gebruikersnaam');
                $stuur(base64_encode($server['wachtwoord']), [235], 'het inloggen (gebruikersnaam of wachtwoord)');
            }
        }
        $stuur('MAIL FROM:<' . $van . '>', [250], 'de afzender');
        $stuur('RCPT TO:<' . $aan . '>', [250, 251], 'de ontvanger');
        $stuur('DATA', [354], 'het bericht');
        $stuur(smtp_bericht($van, $vanNaam, $aan, $antwoordAan, $onderwerp, $tekst) . "\r\n.", [250], 'het bericht');
        try {
            $stuur('QUIT', [221], 'het afsluiten');
        } catch (RuntimeException) {
            // Het bericht is al aangenomen
        }
    } finally {
        fclose($verbinding);
    }
}
