<?php
/*
 * Eén foto tonen of downloaden, alleen voor wie hem mag zien.
 * ?id=12&maat=klein|groot  → het beeld zelf
 * ?id=12&weergave=1        → pagina met de grote foto
 */
require __DIR__ . '/inc/bootstrap.php';

$foto = foto_met_toegang(get_int('id'));

if (get_str('weergave') === '1') {
    $kinderen = rijen('SELECT k.* FROM foto_kinderen fk JOIN kinderen k ON k.id = fk.kind_id WHERE fk.foto_id = ?' . (is_team() ? '' : ' AND k.ouder_id = ' . (int) huidige_gebruiker()['id']) . ' ORDER BY k.voornaam', [$foto['id']]);
    pagina_begin("Foto", 'fotos.php');
    echo '<a class="terug-link" href="fotos.php">' . icoon('back') . "Alle foto's</a>";
    ?>
<div class="panel">
  <figure class="foto-groot">
    <img src="foto.php?id=<?= (int) $foto['id'] ?>&amp;maat=groot" alt="<?= e($foto['bijschrift'] !== '' ? $foto['bijschrift'] : 'Foto van ' . datum_nl($foto['aangemaakt_op'])) ?>" width="<?= (int) $foto['breedte'] ?>" height="<?= (int) $foto['hoogte'] ?>">
    <figcaption>
<?php if ($foto['bijschrift'] !== ''): ?><p><strong><?= e($foto['bijschrift']) ?></strong></p><?php endif; ?>
      <p class="muted"><?= e(ucfirst(datum_nl($foto['aangemaakt_op'], 'EEEE d MMMM y'))) ?><?= $kinderen ? ' · ' . e(implode(', ', array_map(fn ($k) => $k['voornaam'], $kinderen))) : '' ?></p>
      <a class="btn btn--secondary btn--small" href="foto.php?id=<?= (int) $foto['id'] ?>&amp;maat=groot&amp;download=1"><?= icoon('download') ?>Downloaden</a>
    </figcaption>
  </figure>
</div>
<?php
    pagina_einde();
    exit;
}

$pad = foto_map() . '/' . $foto['bestand'] . (get_str('maat') === 'klein' ? '_klein.jpg' : '.jpg');
if (!is_file($pad)) {
    niet_gevonden('Deze foto is niet (meer) beschikbaar.');
}
header_remove('Cache-Control');
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($pad));
header('Cache-Control: private, max-age=86400');
if (get_str('download') === '1') {
    header('Content-Disposition: attachment; filename="bso-vck-' . date('Y-m-d', strtotime($foto['aangemaakt_op'])) . '-' . (int) $foto['id'] . '.jpg"');
}
readfile($pad);
