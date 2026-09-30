<?php
/* Beveiligingsstatus (beheerder): staat alles goed? Met concrete actiepunten. */
require __DIR__ . '/inc/bootstrap.php';

vereis_recht('instellingen');

// Einde van de beveiligingsupdates per PHP-versie (bron: php.net/supported-versions)
const PHP_ONDERSTEUND_TOT = ['8.2' => '2026-12-31', '8.3' => '2027-12-31', '8.4' => '2028-12-31', '8.5' => '2029-12-31'];

$punten = [];
$voeg = function (string $status, string $onderwerp, string $uitleg) use (&$punten): void {
    $punten[] = [$status, $onderwerp, $uitleg];
};

$voeg(is_https() ? 'ok' : (is_lokaal() ? 'let op' : 'probleem'), 'Beveiligde verbinding (https)', is_https() ? 'Alle verkeer is versleuteld.' : 'Lokaal testen zonder https. Op de echte omgeving is https verplicht.');

$sleutelOk = trim((string) cfg('sleutel', '')) !== '';
$voeg($sleutelOk ? 'ok' : 'probleem', 'Versleuteling van gevoelige gegevens', $sleutelOk ? 'Bijzonderheden, observaties, berichten, verzoeken, foto\'s, bonnetjes en exports worden versleuteld opgeslagen met een eigen sleutel.' : 'Ontwikkelmodus: de sleutel staat in de datamap. Zet een eigen sleutel in config.php.');

$data = realpath(data_dir()) ?: data_dir();
$webroot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
$inWebroot = $webroot !== '' && str_starts_with($data, $webroot);
$voeg($inWebroot ? 'let op' : 'ok', 'Datamap buiten de website', $inWebroot ? 'De datamap staat in de webroot en is alleen met .htaccess afgeschermd. Zet data_dir liefst buiten de webroot.' : 'De database en foto\'s staan buiten de publieke map.');

$versie = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
$tot = PHP_ONDERSTEUND_TOT[$versie] ?? null;
$phpStatus = $tot === null ? 'let op' : ($tot < vandaag() ? 'probleem' : ($tot < date('Y-m-d', strtotime('+3 months')) ? 'let op' : 'ok'));
$voeg($phpStatus, 'Bijgewerkte software (PHP ' . PHP_VERSION . ')', $tot ? 'Beveiligingsupdates tot ' . datum_nl($tot) . '. Houd PHP bij met de updates van je hosting en stap op tijd over naar een nieuwere versie.' : 'Onbekende PHP-versie; controleer op php.net of deze nog updates krijgt.');
$voeg('ok', 'Geen externe afhankelijkheden', 'De beheeromgeving gebruikt geen pakketten van derden, alleen PHP zelf en één meegeleverde QR-bibliotheek (zie assets/vendor/README.md).');

$zonderMfa = rijen("SELECT naam FROM gebruikers WHERE rol IN ('beheerder', 'medewerker') AND status != 'gestopt' AND mfa_actief = 0");
$voeg($zonderMfa ? 'let op' : 'ok', 'Tweestapsverificatie team', $zonderMfa ? 'Nog niet ingesteld (moet bij de volgende keer inloggen): ' . implode(', ', array_column($zonderMfa, 'naam')) . '.' : 'Iedereen in het team gebruikt tweestapsverificatie.');
$oudersMfa = (int) waarde("SELECT COUNT(*) FROM gebruikers WHERE rol = 'ouder' AND status != 'gestopt' AND mfa_actief = 1");
$ouders = (int) waarde("SELECT COUNT(*) FROM gebruikers WHERE rol = 'ouder' AND status != 'gestopt'");
$voeg('ok', 'Tweestapsverificatie ouders', instelling('mfa_ouders') === 'verplicht' ? 'Verplicht voor ouders.' : "Aanbevolen; {$oudersMfa} van {$ouders} ouders gebruiken het.");

$zonderGroep = rijen("SELECT g.naam FROM gebruikers g WHERE g.rol IN ('beheerder', 'medewerker') AND g.status != 'gestopt'
    AND EXISTS (SELECT 1 FROM team_rechten r WHERE r.gebruiker_id = g.id AND r.recht IN ('vandaag', 'agenda', 'kinderen', 'ouders', 'berichten', 'fotos'))
    AND NOT EXISTS (SELECT 1 FROM team_rechten r WHERE r.gebruiker_id = g.id AND r.recht = 'alle_groepen')
    AND NOT EXISTS (SELECT 1 FROM medewerker_groepen m WHERE m.gebruiker_id = g.id AND (m.tot IS NULL OR m.tot >= ?))", [vandaag()]);
$metAlles = (int) waarde("SELECT COUNT(*) FROM gebruikers g WHERE g.rol IN ('beheerder', 'medewerker') AND g.status != 'gestopt' AND EXISTS (SELECT 1 FROM team_rechten r WHERE r.gebruiker_id = g.id AND r.recht = 'alle_groepen')");
$voeg($zonderGroep ? 'let op' : 'ok', 'Toegang per groep', $zonderGroep ? 'Deze collega\'s zijn aan geen groep gekoppeld en zien dus geen kinderen: ' . implode(', ', array_column($zonderGroep, 'naam')) . '.' : 'Wie niet alle groepen mag zien, ziet alleen de kinderen in de eigen groep(en). ' . $metAlles . ' ' . ($metAlles === 1 ? 'collega ziet' : "collega's zien") . ' alle groepen.');
$teamBeheer = (int) waarde("SELECT COUNT(*) FROM gebruikers g JOIN team_rechten r ON r.gebruiker_id = g.id AND r.recht = 'team' WHERE g.status = 'actief'");
$voeg($teamBeheer >= 2 ? 'ok' : 'let op', 'Rechten per persoon', ($teamBeheer >= 2 ? $teamBeheer . " collega's kunnen" : 'Maar één collega kan') . ' het team en de rechten beheren.' . ($teamBeheer < 2 ? ' Geef liefst een tweede collega het onderdeel Team, voor als de eerste er niet is.' : ''));

$teControleren = (int) waarde("SELECT COUNT(*) FROM kind_verzorgers v JOIN kinderen k ON k.id = v.kind_id WHERE v.gezag = 1 AND v.gezag_gecontroleerd_op IS NULL AND k.actief = 1");
$voeg($teControleren ? 'let op' : 'ok', 'Gezag gecontroleerd', $teControleren ? "Bij {$teControleren} verzorger(s) is het opgegeven gezag nog niet gecontroleerd. Zij kunnen niet namens het kind beslissen tot dat is gebeurd." : 'Bij alle verzorgers met gezag is dat gecontroleerd.');

$backup = instelling('backup_laatst');
$backupOk = $backup !== '' && strtotime($backup) > time() - 26 * 3600;
$voeg($backupOk ? 'ok' : 'probleem', 'Versleutelde back-up', $backup === '' ? 'Er is nog nooit een back-up gemaakt. Stel een dagelijkse taak in: php beheer/cli/backup.php' : ($backupOk ? 'Laatste back-up: ' . moment_nl($backup) . '. Back-ups worden ' . (int) cfg('backup.bewaardagen', 30) . ' dagen bewaard.' : 'De laatste back-up is van ' . moment_nl($backup) . '. Controleer de dagelijkse taak.'));

$bewaar = instelling('bewaarbeleid_laatst');
$voeg($bewaar !== '' && strtotime($bewaar) > time() - 2 * 86400 ? 'ok' : 'let op', 'Bewaarbeleid', $bewaar !== '' ? 'Laatst uitgevoerd: ' . moment_nl($bewaar) . '.' : 'Nog niet uitgevoerd.');

$teLaat = (int) waarde("SELECT COUNT(*) FROM avg_verzoeken WHERE status IN ('ontvangen', 'in_behandeling') AND deadline < ?", [vandaag()]);
$voeg($teLaat ? 'probleem' : 'ok', 'Privacyverzoeken op tijd', $teLaat ? "{$teLaat} verzoek(en) over de wettelijke termijn van een maand." : 'Geen verzoeken over de termijn.');

$voeg(mollie_actief() ? (mollie_testmodus() ? 'let op' : 'ok') : 'let op', 'Betalingen', mollie_actief() ? (mollie_testmodus() ? 'Mollie in testmodus.' : 'Mollie live gekoppeld.') : 'Demo-modus: Mollie is niet gekoppeld.');

$voeg('ok', 'Organisatie', 'Deze omgeving hoort bij organisatie "' . cfg('organisatie') . '" met een eigen database, fotomap en sleutel.');

pagina_begin('Beveiligingsstatus', 'instellingen.php', ['geen_demo_melding' => true]);
echo '<a class="terug-link" href="instellingen.php">' . icoon('back') . 'Instellingen</a>';
pagina_kop('Beveiligingsstatus', 'Een overzicht van de maatregelen die de gegevens van kinderen en ouders beschermen, en wat er nog aandacht nodig heeft.');
?>
<section class="panel" aria-label="Status">
  <ul class="kindlijst">
<?php foreach ($punten as [$status, $onderwerp, $uitleg]): ?>
    <li class="kindrij" style="grid-template-columns: 1fr">
      <div>
        <div class="kindrij__naam"><?= e($onderwerp) ?> <span class="badge badge--<?= $status === 'ok' ? 'bevestigd' : ($status === 'let op' ? 'wachtlijst' : 'ziek') ?>"><?= $status === 'ok' ? 'In orde' : ($status === 'let op' ? 'Let op' : 'Actie nodig') ?></span></div>
        <p style="margin: 0.25rem 0 0"><?= e($uitleg) ?></p>
      </div>
    </li>
<?php endforeach; ?>
  </ul>
</section>
<?php
pagina_einde();
