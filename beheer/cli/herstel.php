<?php
/*
 * Een back-up ontsleutelen en uitpakken in een lege map.
 *
 *   php beheer/cli/herstel.php /pad/naar/backup-....vckbak /pad/naar/lege-map
 *
 * Daarna (met de website even offline):
 *   1. Zet de huidige datamap opzij (niet weggooien).
 *   2. Zet de uitgepakte bestanden (beheer.sqlite en fotos/) in de datamap.
 *   3. Gebruik het NIEUWSTE verwijderregister (verwijderregister.jsonl): uit de
 *      opzij gezette datamap als dat er nog is, anders dat uit de back-up.
 *   4. Draai: php beheer/cli/onderhoud.php --na-herstel
 *      Zo worden gegevens die na de back-up zijn gewist, opnieuw gewist.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../inc/bootstrap.php';

[$bron, $doel] = [$argv[1] ?? '', $argv[2] ?? ''];
if (!is_file($bron) || $doel === '' || str_starts_with($doel, '--')) {
    stop_met_fout('Gebruik: php beheer/cli/herstel.php <back-upbestand> <lege doelmap>');
}
if (is_dir($doel) && (new FilesystemIterator($doel))->valid()) {
    stop_met_fout('De doelmap is niet leeg. Kies een lege of nieuwe map.');
}
$sleutel = base64_decode((string) cfg('backup.sleutel', ''), true);
if ($sleutel === false || strlen($sleutel) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
    stop_met_fout('De back-upsleutel (backup.sleutel) ontbreekt of is ongeldig.');
}

$in = fopen($bron, 'rb');
if (fread($in, 8) !== "VCKBAK1\n") {
    stop_met_fout('Dit is geen back-up van de beheeromgeving.');
}
$staat = sodium_crypto_secretstream_xchacha20poly1305_init_pull(fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES), $sleutel);
$buffer = '';
$klaar = false;
$lees = function (int $aantal) use (&$buffer, &$staat, &$klaar, $in): string {
    while (strlen($buffer) < $aantal) {
        if ($klaar) {
            stop_met_fout('De back-up is onvolledig.');
        }
        $lengte = unpack('N', (string) fread($in, 4))[1] ?? 0;
        $resultaat = sodium_crypto_secretstream_xchacha20poly1305_pull($staat, (string) fread($in, $lengte));
        if ($resultaat === false) {
            stop_met_fout('Ontsleutelen mislukt: verkeerde sleutel of beschadigde back-up.');
        }
        [$blok, $tag] = $resultaat;
        $buffer .= $blok;
        $klaar = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
    }
    $uit = substr($buffer, 0, $aantal);
    $buffer = (string) substr($buffer, $aantal);
    return $uit;
};

$aantal = 0;
while (true) {
    $lengte = unpack('n', $lees(2))[1];
    if ($lengte === 0) {
        break;
    }
    $relatief = $lees($lengte);
    if (str_contains($relatief, '..') || str_starts_with($relatief, '/')) {
        stop_met_fout('Ongeldig pad in de back-up.');
    }
    $grootte = unpack('J', $lees(8))[1];
    $pad = rtrim($doel, '/') . '/' . $relatief;
    if (!is_dir(dirname($pad))) {
        mkdir(dirname($pad), 0700, true);
    }
    $uit = fopen($pad, 'wb');
    for ($rest = $grootte; $rest > 0; $rest -= $stuk) {
        $stuk = min($rest, 1048576);
        fwrite($uit, $lees($stuk));
    }
    fclose($uit);
    $aantal++;
}
fclose($in);
echo "Uitgepakt: {$aantal} bestanden in {$doel}\n";
echo "Volg nu de stappen bovenin dit script (datamap vervangen, nieuwste verwijderregister, onderhoud --na-herstel).\n";
