<?php
/*
 * Logboek (beheerder): wie heeft wanneer welke gegevens ingezien, gewijzigd of
 * geëxporteerd. Filteren op soort, persoon of onderwerp (bijvoorbeeld één kind),
 * exporteren als CSV en de integriteit controleren (hashketen).
 */
require __DIR__ . '/inc/bootstrap.php';

vereis_beheerder();

$soort = array_key_exists(get_str('soort'), LOG_SOORTEN) ? get_str('soort') : '';
$onderwerp = preg_match('/^(kind|gebruiker):\d+$/', get_str('onderwerp')) ? get_str('onderwerp') : '';
$wie = get_int('wie');
$waar = ['1 = 1'];
$params = [];
if ($soort !== '') {
    $waar[] = 'l.soort = ?';
    $params[] = $soort;
}
if ($onderwerp !== '') {
    $waar[] = 'l.onderwerp = ?';
    $params[] = $onderwerp;
}
if ($wie) {
    $waar[] = 'l.gebruiker_id = ?';
    $params[] = $wie;
}
$sql = 'SELECT l.*, g.naam, g.rol FROM logboek l LEFT JOIN gebruikers g ON g.id = l.gebruiker_id WHERE ' . implode(' AND ', $waar) . ' ORDER BY l.id DESC';

if (get_str('csv') === '1') {
    log_export('Logboek geëxporteerd', $onderwerp, trim("soort={$soort} wie={$wie}"));
    $uit = fopen('php://temp', 'w+');
    fputcsv($uit, ['wanneer', 'soort', 'actie', 'details', 'onderwerp', 'wie', 'rol', 'ip'], ';');
    foreach (rijen($sql . ' LIMIT 50000', $params) as $r) {
        fputcsv($uit, [$r['aangemaakt_op'], $r['soort'], $r['actie'], $r['details'], $r['onderwerp'], $r['naam'] ?? '', $r['rol'] ?? '', $r['ip']], ';');
    }
    rewind($uit);
    stuur_download((string) stream_get_contents($uit), 'logboek-' . date('Y-m-d') . '.csv', 'text/csv');
}

$controle = null;
if (is_post()) {
    csrf_controleer();
    $controle = controleer_logboek();
    log_actie('Logboek gecontroleerd', $controle['ok'] ? 'in orde' : 'afwijking bij regel ' . $controle['fout_id'], null, 'beveiliging');
}

$regels = rijen($sql . ' LIMIT 300', $params);
$onderwerpNaam = '';
if (preg_match('/^kind:(\d+)$/', $onderwerp, $m)) {
    $onderwerpNaam = 'kind: ' . (($k = rij('SELECT * FROM kinderen WHERE id = ?', [$m[1]])) ? kindnaam($k) : 'verwijderd');
} elseif (preg_match('/^gebruiker:(\d+)$/', $onderwerp, $m)) {
    $onderwerpNaam = 'persoon: ' . ((string) waarde('SELECT naam FROM gebruikers WHERE id = ?', [$m[1]]) ?: 'verwijderd');
}

pagina_begin('Logboek', 'instellingen.php');
echo '<a class="terug-link" href="instellingen.php">' . icoon('back') . 'Instellingen</a>';
pagina_kop('Logboek', 'Inzage, wijzigingen, exports en inlogpogingen. Wordt ' . bewaartermijn('bewaar_logboek_maanden') . ' maanden bewaard.' . ($onderwerpNaam ? ' Gefilterd op ' . e($onderwerpNaam) . '.' : ''),
    '<form class="inline-form" method="post">' . csrf_veld() . '<button class="btn btn--secondary btn--small" type="submit">' . icoon('shield') . 'Controleer logboek</button></form>'
    . '<a class="btn btn--secondary btn--small" href="logboek.php?' . e(http_build_query(['soort' => $soort, 'onderwerp' => $onderwerp, 'wie' => $wie ?: null, 'csv' => 1])) . '">' . icoon('download') . 'CSV</a>');
?>
<?php if ($controle): ?>
<div class="melding melding--<?= $controle['ok'] ? 'succes' : 'fout' ?>" role="status"><?= icoon($controle['ok'] ? 'check' : 'alert') ?><p><?= $controle['ok']
    ? 'Het logboek is in orde: ' . $controle['aantal'] . ' regels gecontroleerd, niets gewijzigd of weggehaald.'
    : 'Let op: het logboek is aangepast of er ontbreken regels (eerste afwijking bij regel ' . (int) $controle['fout_id'] . '). Neem contact op met je technisch beheerder.' ?></p></div>
<?php endif; ?>
<section class="panel" aria-label="Logboek">
  <form class="filters" method="get">
    <div class="field"><label for="soort">Soort</label><select id="soort" name="soort"><option value="">Alles</option>
<?php foreach (LOG_SOORTEN as $sleutel => $label): ?>
      <option value="<?= e($sleutel) ?>"<?= $soort === $sleutel ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
    </select></div>
    <div class="field"><label for="wie">Door</label><select id="wie" name="wie"><option value="">Iedereen</option>
<?php foreach (rijen("SELECT id, naam FROM gebruikers WHERE rol IN ('beheerder', 'medewerker') ORDER BY naam") as $p): ?>
      <option value="<?= (int) $p['id'] ?>"<?= $wie === (int) $p['id'] ? ' selected' : '' ?>><?= e($p['naam']) ?></option>
<?php endforeach; ?>
    </select></div>
<?php if ($onderwerp): ?><input type="hidden" name="onderwerp" value="<?= e($onderwerp) ?>"><?php endif; ?>
    <button class="btn btn--secondary btn--small" type="submit">Toon</button>
<?php if ($onderwerp || $soort || $wie): ?><a href="logboek.php">Alle regels</a><?php endif; ?>
  </form>
<?php if (!$regels): ?>
  <p class="leeg">Geen regels gevonden.</p>
<?php else: ?>
  <div class="tabel">
    <table>
      <thead><tr><th scope="col">Wanneer</th><th scope="col">Wie</th><th scope="col">Soort</th><th scope="col">Actie</th><th scope="col">Over</th><th scope="col">Details</th></tr></thead>
      <tbody>
<?php foreach ($regels as $r): ?>
        <tr>
          <td><?= e(moment_nl($r['aangemaakt_op'])) ?></td>
          <td><?= e($r['naam'] ?? '-') ?><?= $r['rol'] ? ' <small class="muted">(' . e($r['rol']) . ')</small>' : '' ?></td>
          <td><?= e(LOG_SOORTEN[$r['soort']] ?? $r['soort']) ?></td>
          <td><?= e($r['actie']) ?></td>
          <td><?= $r['onderwerp'] !== '' ? '<a href="logboek.php?onderwerp=' . e(rawurlencode($r['onderwerp'])) . '">' . e($r['onderwerp']) . '</a>' : '' ?></td>
          <td><?= e($r['details']) ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</section>
<?php
pagina_einde();
