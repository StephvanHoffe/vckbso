<?php
/*
 * Externe back-up: na de dagelijkse back-up (cli/backup.php) gaat een kopie naar objectopslag bij een
 * andere partij in de EU, via de S3-standaard die vrijwel alle aanbieders gebruiken (bijvoorbeeld
 * Scaleway, Hetzner, IONOS of Exoscale). In te stellen onder Koppelingen.
 *
 * - De back-up is al versleuteld voordat hij de server verlaat (eigen back-upsleutel, die niet bij de
 *   aanbieder staat): de aanbieder kan de gegevens niet lezen.
 * - Zet bij de aanbieder object lock aan met een standaard bewaartermijn van 30 dagen en laat
 *   bestanden daarna automatisch verwijderen. Zo kan niemand een back-up eerder wissen (ook een
 *   inbreker op de server niet) en verdwijnen gewiste gegevens na 30 dagen ook daar.
 * - Geef Mijn BSO een sleutel die alleen mag uploaden (geen lezen of wissen).
 * - Ondertekend met AWS Signature Version 4, zonder extra software.
 */

declare(strict_types=1);

function externe_backup_instellingen(): array
{
    return [
        'endpoint' => rtrim(trim((string) koppeling('extern.endpoint')), '/'),
        'regio' => trim((string) koppeling('extern.regio')),
        'bucket' => trim((string) koppeling('extern.bucket')),
        'toegangssleutel' => trim((string) koppeling('extern.toegangssleutel')),
        'geheim' => (string) koppeling('extern.geheime_sleutel'),
        'map' => trim((string) koppeling('extern.map'), '/'),
        'adres' => koppeling('extern.adres') === 'pad' ? 'pad' : 'host',
    ];
}

function externe_backup_actief(): bool
{
    $i = externe_backup_instellingen();
    return $i['endpoint'] !== '' && $i['bucket'] !== '' && $i['toegangssleutel'] !== '' && $i['geheim'] !== '' && $i['regio'] !== '';
}

/** URI-codering volgens S3: alles behalve A-Z a-z 0-9 - _ . ~ (en / tussen mapnamen). */
function s3_codeer_pad(string $pad): string
{
    return implode('/', array_map('rawurlencode', explode('/', $pad)));
}

/**
 * De koppen (met de handtekening) voor een PUT van één object.
 * $nu is een Unix-tijd (voor tests); $inhoudHash en $md5 gaan over de inhoud van het bestand.
 * Geeft [url, koppen] terug.
 */
function s3_put_koppen(array $i, string $sleutel, string $inhoudHash, string $md5Base64, int $lengte, int $nu): array
{
    $host = (string) parse_url($i['endpoint'], PHP_URL_HOST);
    $poort = parse_url($i['endpoint'], PHP_URL_PORT);
    $schema = (string) parse_url($i['endpoint'], PHP_URL_SCHEME);
    if ($schema !== 'https' && !in_array($host, ['localhost', '127.0.0.1'], true)) {
        throw new RuntimeException('Het adres van de opslag moet met https:// beginnen.');
    }
    $pad = s3_codeer_pad($sleutel);
    if ($i['adres'] === 'pad') {
        $hostKop = $host . ($poort ? ':' . $poort : '');
        $uri = '/' . rawurlencode($i['bucket']) . '/' . $pad;
    } else {
        $hostKop = $i['bucket'] . '.' . $host . ($poort ? ':' . $poort : '');
        $uri = '/' . $pad;
    }
    $amzDatum = gmdate('Ymd\THis\Z', $nu);
    $dag = gmdate('Ymd', $nu);
    $koppen = [
        'content-length' => (string) $lengte,
        'content-md5' => $md5Base64,
        'content-type' => 'application/octet-stream',
        'host' => $hostKop,
        'x-amz-content-sha256' => $inhoudHash,
        'x-amz-date' => $amzDatum,
    ];
    ksort($koppen);
    $ondertekend = implode(';', array_keys($koppen));
    $canoniek = "PUT\n{$uri}\n\n"
        . implode('', array_map(fn ($k, $v) => $k . ':' . trim($v) . "\n", array_keys($koppen), $koppen))
        . "\n{$ondertekend}\n{$inhoudHash}";
    $bereik = "{$dag}/{$i['regio']}/s3/aws4_request";
    $teOndertekenen = "AWS4-HMAC-SHA256\n{$amzDatum}\n{$bereik}\n" . hash('sha256', $canoniek);
    $sleutelDag = hash_hmac('sha256', $dag, 'AWS4' . $i['geheim'], true);
    $sleutelRegio = hash_hmac('sha256', $i['regio'], $sleutelDag, true);
    $sleutelDienst = hash_hmac('sha256', 's3', $sleutelRegio, true);
    $ondertekenSleutel = hash_hmac('sha256', 'aws4_request', $sleutelDienst, true);
    $handtekening = hash_hmac('sha256', $teOndertekenen, $ondertekenSleutel);
    $koppen['authorization'] = "AWS4-HMAC-SHA256 Credential={$i['toegangssleutel']}/{$bereik}, SignedHeaders={$ondertekend}, Signature={$handtekening}";
    $url = $schema . '://' . $hostKop . $uri;
    return [$url, $koppen];
}

/** Uploadt één bestand. Gooit een RuntimeException met een begrijpelijke uitleg als het niet lukt. */
function externe_backup_upload(string $bestand, string $naam, ?array $instellingen = null): void
{
    $i = $instellingen ?? externe_backup_instellingen();
    if (!is_file($bestand)) {
        throw new RuntimeException('Het bestand ' . basename($bestand) . ' bestaat niet.');
    }
    $host = (string) parse_url($i['endpoint'], PHP_URL_HOST);
    if (function_exists('smtp_host_fout') && ($fout = smtp_host_fout($host)) !== null) {
        throw new RuntimeException($fout);
    }
    $sleutel = ($i['map'] !== '' ? $i['map'] . '/' : '') . $naam;
    $lengte = (int) filesize($bestand);
    [$url, $koppen] = s3_put_koppen($i, $sleutel, (string) hash_file('sha256', $bestand), base64_encode((string) md5_file($bestand, true)), $lengte, time());

    $in = fopen($bestand, 'rb');
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_UPLOAD => true,
        CURLOPT_INFILE => $in,
        CURLOPT_INFILESIZE => $lengte,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3600,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_HTTPHEADER => array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($koppen), $koppen),
    ]);
    $antwoord = curl_exec($curl);
    $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $fout = curl_error($curl);
    curl_close($curl);
    fclose($in);

    if ($antwoord === false) {
        throw new RuntimeException('De opslag is niet bereikbaar: ' . $fout);
    }
    if ($code < 200 || $code >= 300) {
        $melding = preg_match('#<Code>([^<]+)</Code>#', (string) $antwoord, $m) ? $m[1] : 'HTTP ' . $code;
        $uitleg = match ($melding) {
            'SignatureDoesNotMatch' => 'de geheime sleutel klopt niet.',
            'InvalidAccessKeyId' => 'de toegangssleutel is onbekend.',
            'AccessDenied' => 'deze sleutel mag hier niet uploaden. Controleer de rechten en de naam van de bucket.',
            'NoSuchBucket' => 'de bucket bestaat niet. Controleer de naam en de regio.',
            'AuthorizationHeaderMalformed' => 'de regio klopt niet.',
            default => 'de opslag gaf een fout (' . $melding . ').',
        };
        throw new RuntimeException('Uploaden mislukt: ' . $uitleg);
    }
}

/** Een klein testbestand uploaden, om de instellingen te controleren. */
function externe_backup_test(?array $instellingen = null): void
{
    $tijdelijk = tempnam(sys_get_temp_dir(), 'vck-test-');
    file_put_contents($tijdelijk, "Verbindingstest van Mijn BSO, " . nu() . ".\nDit bestand bevat geen gegevens.\n");
    try {
        externe_backup_upload($tijdelijk, 'verbindingstest/' . date('Ymd-His') . '.txt', $instellingen);
    } finally {
        unlink($tijdelijk);
    }
}

/** Stuurt een gemaakte back-up naar de externe opslag en houdt de uitkomst bij. Geeft true als het gelukt is. */
function externe_backup_verstuur(string $bestand, string $naam): bool
{
    try {
        externe_backup_upload($bestand, $naam);
        instelling_zet('extern_backup_laatst', nu());
        log_actie('Externe back-up verstuurd', $naam, null, 'beveiliging');
        return true;
    } catch (RuntimeException $fout) {
        instelling_zet('extern_backup_fout', nu() . ' ' . mb_substr($fout->getMessage(), 0, 300));
        log_actie('Externe back-up mislukt', mb_substr($fout->getMessage(), 0, 200), null, 'beveiliging');
        mail_team_tekst('team_backup_mislukt', ['fout' => $fout->getMessage(), 'link' => app_url('koppelingen.php#extern')]);
        return false;
    }
}
