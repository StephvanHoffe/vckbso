<?php
/* Logboek (beheerder): wie deed wat en wanneer. Handig bij vragen en voor de AVG. */
require __DIR__ . '/inc/bootstrap.php';

vereis_beheerder();

$regels = rijen('SELECT l.*, g.naam, g.rol FROM logboek l LEFT JOIN gebruikers g ON g.id = l.gebruiker_id ORDER BY l.id DESC LIMIT 300');

pagina_begin('Logboek', 'instellingen.php');
echo '<a class="terug-link" href="instellingen.php">' . icoon('back') . 'Instellingen</a>';
pagina_kop('Logboek', 'De laatste 300 acties in de beheeromgeving.');
?>
<section class="panel" aria-label="Logboek">
<?php if (!$regels): ?>
  <p class="leeg">Nog niets gelogd.</p>
<?php else: ?>
  <div class="tabel">
    <table>
      <thead><tr><th scope="col">Wanneer</th><th scope="col">Wie</th><th scope="col">Actie</th><th scope="col">Details</th></tr></thead>
      <tbody>
<?php foreach ($regels as $regel): ?>
        <tr><td><?= e(moment_nl($regel['aangemaakt_op'])) ?></td><td><?= e($regel['naam'] ?? '-') ?><?= $regel['rol'] ? ' <small class="muted">(' . e($regel['rol']) . ')</small>' : '' ?></td><td><?= e($regel['actie']) ?></td><td><?= e($regel['details']) ?></td></tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</section>
<?php
pagina_einde();
