<?php
/*
 * Versleutelde back-up van de database, de foto's en het verwijderregister.
 *
 *   php beheer/cli/backup.php        dagelijks via cron, bijvoorbeeld om 02:30
 *
 * - Versleuteld met een aparte back-upsleutel (config: backup.sleutel), anders dan
 *   de encryptiesleutel van de gegevens. Bewaar beide sleutels op een veilige plek
 *   buiten de server (bijvoorbeeld een wachtwoordkluis), anders is een back-up
 *   onbruikbaar.
 * - Oude back-ups worden na backup.bewaardagen (standaard 30) verwijderd. Zo
 *   verdwijnen gewiste gegevens ook uit de back-ups.
 * - Kopieer de map met back-ups daarnaast naar een tweede locatie in de EU.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../inc/bootstrap.php';

const BACKUP_BLOK = 1048576; // 1 MB per versleuteld blok

$map = rtrim((string) cfg('backup.map', ''), '/');
if ($map === '') {
    stop_met_fout('Stel backup.map in config.php in: een map buiten de webroot, bijvoorbeeld /home/account/backups.');
}
$sleutel = base64_decode((string) cfg('backup.sleutel', ''), true);
if ($sleutel === false || strlen($sleutel) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
    stop_met_fout('Stel een geldige backup.sleutel in config.php in. Maak er een met: php -r "echo base64_encode(random_bytes(32));"');
}
if (hash_equals($sleutel, sleutel())) {
    stop_met_fout('De back-upsleutel moet anders zijn dan de encryptiesleutel van de gegevens.');
}
if (!is_dir($map) && !mkdir($map, 0700, true)) {
    stop_met_fout("De map {$map} kan niet worden aangemaakt.");
}

// 1. Consistente kopie van de database (ook als er net iemand iets opslaat)
$kopie = sys_get_temp_dir() . '/vck-backup-' . bin2hex(random_bytes(6)) . '.sqlite';
db()->exec('VACUUM INTO ' . db()->quote($kopie));

// 2. Welke bestanden gaan mee?
$bestanden = ['beheer.sqlite' => $kopie];
// Foto's en bonnetjes (Financiën); beide al versleuteld op schijf
foreach (['fotos', 'bijlagen'] as $submap) {
    $bronmap = data_dir() . '/' . $submap;
    if (!is_dir($bronmap)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($bronmap, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $bestand) {
        if ($bestand->isFile()) {
            $bestanden[$submap . '/' . substr($bestand->getPathname(), strlen($bronmap) + 1)] = $bestand->getPathname();
        }
    }
}
if (is_file(verwijderregister_pad())) {
    $bestanden['verwijderregister.jsonl'] = verwijderregister_pad();
}

// 3. Versleuteld wegschrijven (XChaCha20-Poly1305, in blokken)
$naam = 'backup-' . preg_replace('/[^a-z0-9]/', '', strtolower((string) cfg('organisatie'))) . '-' . date('Ymd-His') . '.vckbak';
$tijdelijk = $map . '/' . $naam . '.tmp';
$uit = fopen($tijdelijk, 'wb');
chmod($tijdelijk, 0600);
[$staat, $kop] = sodium_crypto_secretstream_xchacha20poly1305_init_push($sleutel);
fwrite($uit, "VCKBAK1\n" . $kop);
$buffer = '';
$schrijf = function (string $data, bool $laatste = false) use (&$buffer, &$staat, $uit): void {
    $buffer .= $data;
    while (strlen($buffer) >= BACKUP_BLOK || ($laatste && $buffer !== '')) {
        $blok = substr($buffer, 0, BACKUP_BLOK);
        $buffer = (string) substr($buffer, strlen($blok));
        $tag = ($laatste && $buffer === '') ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
        $versleuteld = sodium_crypto_secretstream_xchacha20poly1305_push($staat, $blok, '', $tag);
        fwrite($uit, pack('N', strlen($versleuteld)) . $versleuteld);
    }
};
$totaal = 0;
foreach ($bestanden as $relatief => $pad) {
    $grootte = filesize($pad);
    $totaal += $grootte;
    $schrijf(pack('n', strlen($relatief)) . $relatief . pack('J', $grootte));
    $in = fopen($pad, 'rb');
    while (!feof($in)) {
        $schrijf((string) fread($in, BACKUP_BLOK));
    }
    fclose($in);
}
$schrijf(pack('n', 0), true); // einde
fclose($uit);
rename($tijdelijk, $map . '/' . $naam);
unlink($kopie);

// 4. Oude back-ups van deze organisatie opruimen
$dagen = max(1, (int) cfg('backup.bewaardagen', 30));
$opgeruimd = 0;
foreach (glob($map . '/backup-' . preg_replace('/[^a-z0-9]/', '', strtolower((string) cfg('organisatie'))) . '-*.vckbak') ?: [] as $oud) {
    if (filemtime($oud) < time() - $dagen * 86400) {
        unlink($oud);
        $opgeruimd++;
    }
}

instelling_zet('backup_laatst', nu());
log_actie('Back-up gemaakt', $naam . ', ' . count($bestanden) . ' bestanden', null, 'beveiliging');
echo "Back-up gemaakt: {$map}/{$naam} (" . count($bestanden) . ' bestanden, ' . round($totaal / 1048576, 1) . " MB)\n";
echo "Oude back-ups verwijderd: {$opgeruimd}\n";
