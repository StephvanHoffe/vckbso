<?php
/* Ouders (klanten): lijst met status en machtiging (team). */
require __DIR__ . '/inc/bootstrap.php';

vereis_team();

$status = get_str('status', 'actief');
$zoek = get_str('zoek');
$waar = ["g.rol = 'ouder'"];
$params = [];
if (in_array($status, ['nieuw', 'actief', 'gestopt'], true)) {
    $waar[] = 'g.status = ?';
    $params[] = $status;
}
if ($zoek !== '') {
    $waar[] = '(g.naam LIKE ? OR g.email LIKE ? OR EXISTS (SELECT 1 FROM kinderen k WHERE k.ouder_id = g.id AND k.voornaam LIKE ?))';
    array_push($params, "%{$zoek}%", "%{$zoek}%", "%{$zoek}%");
}
$ouders = rijen(
    "SELECT g.*, (SELECT GROUP_CONCAT(k.voornaam, ', ') FROM kinderen k WHERE k.ouder_id = g.id AND k.actief = 1) AS kinderen
     FROM gebruikers g WHERE " . implode(' AND ', $waar) . ' ORDER BY g.status = \'nieuw\' DESC, g.naam',
    $params
);
$aantallen = [];
foreach (rijen("SELECT status, COUNT(*) AS n FROM gebruikers WHERE rol = 'ouder' GROUP BY status") as $rij) {
    $aantallen[$rij['status']] = (int) $rij['n'];
}

pagina_begin('Ouders', 'ouders.php');
pagina_kop('Ouders', 'Alle klanten met hun kinderen, status en machtiging. Nieuwe aanmeldingen keur je hier goed na de kennismaking.');
?>
<section class="panel" aria-labelledby="lijst-titel">
  <h2 id="lijst-titel" class="visually-hidden">Lijst met ouders</h2>
  <ul class="kind-tabs" aria-label="Filter op status">
<?php foreach (['nieuw' => 'Nieuwe aanmeldingen', 'actief' => 'Actief', 'gestopt' => 'Gestopt', 'alle' => 'Alle'] as $sleutel => $label): ?>
    <li><a href="ouders.php?status=<?= $sleutel ?>"<?= $status === $sleutel ? ' aria-current="true"' : '' ?>><?= e($label) ?><?= $sleutel !== 'alle' ? ' (' . ($aantallen[$sleutel] ?? 0) . ')' : '' ?></a></li>
<?php endforeach; ?>
  </ul>
  <form class="filters" method="get">
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <div class="field"><label for="zoek">Zoeken</label><input id="zoek" name="zoek" type="search" value="<?= e($zoek) ?>" placeholder="Naam, e-mail of naam van kind"></div>
    <button class="btn btn--secondary btn--small" type="submit">Zoek</button>
  </form>
<?php if (!$ouders): ?>
  <p class="leeg">Geen ouders gevonden.</p>
<?php else: ?>
  <div class="tabel">
    <table>
      <thead><tr><th scope="col">Naam</th><th scope="col">Kinderen</th><th scope="col">Contact</th><th scope="col">Status</th><th scope="col">Incasso</th></tr></thead>
      <tbody>
<?php foreach ($ouders as $ouder): ?>
        <tr>
          <th scope="row"><a href="ouder.php?id=<?= (int) $ouder['id'] ?>"><?= e($ouder['naam']) ?></a></th>
          <td><?= e($ouder['kinderen'] ?? '-') ?></td>
          <td><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $ouder['telefoon'])) ?>"><?= e($ouder['telefoon']) ?></a><br><a href="mailto:<?= e($ouder['email']) ?>"><?= e($ouder['email']) ?></a></td>
          <td><?= status_badge($ouder['status']) ?><?php if ($ouder['status'] === 'nieuw'): ?><br><small class="muted"><?= e(moment_nl($ouder['aangemaakt_op'])) ?></small><?php endif; ?></td>
          <td><?= status_badge($ouder['mandaat_status']) ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</section>
<?php
pagina_einde();
