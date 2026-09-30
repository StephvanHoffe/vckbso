<?php
/* Startpagina na het inloggen: overzicht voor ouders, of "vandaag" voor het team. */
require __DIR__ . '/inc/bootstrap.php';

if ((int) waarde("SELECT COUNT(*) FROM gebruikers WHERE rol = 'beheerder'") === 0) {
    redirect('installeren.php');
}
$gebruiker = vereis_login();
$voornaam = explode(' ', $gebruiker['naam'])[0];

if (is_ouder($gebruiker)) {
    $kinderen = kinderen_van_verzorger((int) $gebruiker['id']);
    $fotoKinderen = array_map(fn ($k) => (int) $k['id'], kinderen_van_verzorger((int) $gebruiker['id'], 'fotos'));
    $zonderGecontroleerdGezag = array_filter($kinderen, fn ($k) => (int) $k['v_gezag'] === 1 && empty($k['v_gezag_gecontroleerd_op']));
    $ongelezen = ongelezen_voor_ouder((int) $gebruiker['id']);
    $openFacturen = rijen("SELECT * FROM facturen WHERE ouder_id = ? AND status IN ('open', 'mislukt') ORDER BY datum DESC", [$gebruiker['id']]);
    $fotos = fotos_van($fotoKinderen, 4);
    $machtigingOk = in_array($gebruiker['mandaat_status'], ['geldig', 'demo'], true);

    pagina_begin('Overzicht', 'index.php');
    pagina_kop("Hoi {$voornaam}!", 'Fijn dat je er bent. Hier zie je in één oogopslag hoe het ervoor staat.',
        '<a class="btn" href="agenda.php">' . icoon('calendar') . 'Dagen kiezen</a><a class="btn btn--secondary" href="berichten.php#ziekmelden">' . icoon('thermo') . 'Ziek melden</a>');

    if ($gebruiker['status'] === 'nieuw' || !$machtigingOk) {
        ?>
<section class="panel" aria-labelledby="stappen-titel">
  <h2 id="stappen-titel">Zo gaat het verder</h2>
  <ol class="mini-steps">
    <li><span><strong>Aanmelding ontvangen</strong> <?= status_badge('bevestigd') ?><br><span class="muted">Je account is aangemaakt.</span></span></li>
    <li><span><strong>Automatische incasso</strong> <?= status_badge($gebruiker['mandaat_status']) ?><br><?= $machtigingOk ? '<span class="muted">Geregeld, je hoeft er niets meer voor te doen.</span>' : '<a href="machtiging.php">Geef nu je machtiging af</a>' ?></span></li>
    <li><span><strong>Kennismaken</strong><?= $gebruiker['status'] === 'actief' ? ' ' . status_badge('bevestigd') : '' ?><br><span class="muted"><?= $gebruiker['status'] === 'actief' ? 'Gedaan, welkom bij BSO VCK!' : 'We nemen snel contact met je op voor een kennismaking. Neem dan een bewijs van gezag mee, bijvoorbeeld een uittreksel uit het gezagsregister.' ?></span></span></li>
    <li><span><strong>Dagen kiezen</strong><br><span class="muted">Daarna kies je in de <a href="agenda.php">agenda</a> zelf de dagen.</span></span></li>
  </ol>
</section>
<?php
    }
    ?>
<ul class="tegels">
  <li><a class="tegel" href="berichten.php" style="--tegel-bg: var(--color-sun-soft)"><span class="tegel__getal"><?= $ongelezen ?></span><span class="tegel__label"><?= $ongelezen === 1 ? 'nieuw bericht' : 'nieuwe berichten' ?></span></a></li>
  <li><a class="tegel" href="facturen.php" style="--tegel-bg: var(--color-mint-soft)"><span class="tegel__getal"><?= count($openFacturen) ?></span><span class="tegel__label">open <?= count($openFacturen) === 1 ? 'factuur' : 'facturen' ?></span></a></li>
  <li><a class="tegel" href="fotos.php" style="--tegel-bg: var(--color-coral-soft)"><span class="tegel__getal"><?= (int) ($fotoKinderen ? waarde('SELECT COUNT(DISTINCT foto_id) FROM foto_kinderen WHERE kind_id IN (' . implode(',', $fotoKinderen) . ')') : 0) ?></span><span class="tegel__label">foto's</span></a></li>
</ul>
<?php if ($zonderGecontroleerdGezag && $gebruiker['status'] === 'actief'): ?>
<p class="melding"><?= icoon('info') ?><span>Voor <?= e(implode(' en ', array_map(fn ($k) => $k['voornaam'], $zonderGecontroleerdGezag))) ?> hebben we je gezag nog niet gecontroleerd. Tot die tijd kun je de gegevens en toestemmingen niet zelf wijzigen. Neem bij je volgende bezoek een uittreksel uit het gezagsregister mee.</span></p>
<?php endif; ?>
<div class="kolommen">
  <section class="panel" aria-labelledby="kinderen-titel">
    <div class="panel__kop"><h2 id="kinderen-titel">Je kinderen</h2><a href="kinderen.php">Alle gegevens</a></div>
<?php if (!$kinderen): ?>
    <p class="leeg">Nog geen kinderen in je account. <a href="kinderen.php">Voeg een kind toe</a>.</p>
<?php else: ?>
    <ul class="kindlijst">
<?php foreach ($kinderen as $kind):
    $volgende = rij("SELECT * FROM inschrijvingen WHERE kind_id = ? AND datum >= ? AND status IN ('bevestigd', 'wachtlijst') ORDER BY datum LIMIT 1", [$kind['id'], vandaag()]);
    ?>
      <li class="kindrij">
        <div>
          <div class="kindrij__naam"><a href="kind.php?id=<?= (int) $kind['id'] ?>"><?= e(kindnaam($kind)) ?></a></div>
          <div class="kindrij__info">
            <span><?= $kind['groepnaam'] ? 'Groep ' . e($kind['groepnaam']) : 'Nog geen groep' ?></span>
            <span><?= $volgende ? 'Volgende keer: ' . e(datum_nl($volgende['datum'], 'EEEE d MMMM')) . ($volgende['status'] === 'wachtlijst' ? ' (wachtlijst)' : '') : 'Nog geen dagen gekozen' ?></span>
          </div>
        </div>
        <a class="btn btn--secondary btn--mini" href="agenda.php?kind=<?= (int) $kind['id'] ?>">Agenda<span class="visually-hidden"> van <?= e($kind['voornaam']) ?></span></a>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </section>
  <section class="panel" aria-labelledby="fotos-titel">
    <div class="panel__kop"><h2 id="fotos-titel">Nieuwste foto's</h2><a href="fotos.php">Alle foto's</a></div>
<?php if (!$fotos): ?>
    <p class="leeg">Nog geen foto's. De begeleiders delen hier foto's van leuke momenten.</p>
<?php else: ?>
    <ul class="fotogrid"><?php foreach ($fotos as $foto) { echo foto_kaart($foto); } ?></ul>
<?php endif; ?>
  </section>
</div>
<?php
    pagina_einde();
    exit;
}

/* ---------- Team ---------- */

$datum = vandaag();
$groepen = zichtbare_groepen();
$totaal = $ziek = $wacht = 0;
foreach ($groepen as $groep) {
    if (groep_open_op($groep, $datum)) {
        $totaal += (int) waarde("SELECT COUNT(*) FROM inschrijvingen WHERE groep_id = ? AND datum = ? AND status = 'bevestigd'", [$groep['id'], $datum]);
        $ziek += (int) waarde("SELECT COUNT(*) FROM inschrijvingen WHERE groep_id = ? AND datum = ? AND status = 'ziek'", [$groep['id'], $datum]);
        $wacht += aantal_wachtlijst((int) $groep['id'], $datum);
    }
}
// Nieuwe aanmeldingen en kinderen zonder groep: alleen de beheerder (intake)
$nieuw = is_beheerder($gebruiker) ? rijen("SELECT g.*, (SELECT COUNT(*) FROM kinderen k WHERE k.ouder_id = g.id) AS aantal_kinderen FROM gebruikers g WHERE g.rol = 'ouder' AND g.status = 'nieuw' ORDER BY g.aangemaakt_op") : [];
$ongelezen = ongelezen_voor_team();
$zonderGroep = is_beheerder($gebruiker) ? (int) waarde("SELECT COUNT(*) FROM kinderen k JOIN gebruikers g ON g.id = k.ouder_id WHERE k.groep_id IS NULL AND k.actief = 1 AND g.status = 'actief'") : 0;

pagina_begin('Vandaag', 'index.php');
pagina_kop("Hoi {$voornaam}!", 'Vandaag is het ' . e(datum_nl($datum, 'EEEE d MMMM')) . '.',
    '<a class="btn btn--secondary btn--small" href="agenda.php">' . icoon('calendar') . 'Weekoverzicht</a><a class="btn btn--small" href="fotos.php">' . icoon('camera') . "Foto's delen</a>");
?>
<ul class="tegels">
  <li><a class="tegel" href="agenda.php?datum=<?= e($datum) ?>" style="--tegel-bg: var(--color-mint-soft)"><span class="tegel__getal"><?= $totaal ?></span><span class="tegel__label">kinderen vandaag</span></a></li>
  <li><span class="tegel" style="--tegel-bg: var(--color-coral-soft)"><span class="tegel__getal"><?= $ziek ?></span><span class="tegel__label">ziek gemeld</span></span></li>
  <li><span class="tegel" style="--tegel-bg: var(--color-sun-soft)"><span class="tegel__getal"><?= $wacht ?></span><span class="tegel__label">op de wachtlijst</span></span></li>
  <li><a class="tegel" href="berichten.php"><span class="tegel__getal"><?= $ongelezen ?></span><span class="tegel__label">ongelezen <?= $ongelezen === 1 ? 'bericht' : 'berichten' ?></span></a></li>
<?php if (is_beheerder($gebruiker)): ?>
  <li><a class="tegel" href="ouders.php?status=nieuw"><span class="tegel__getal"><?= count($nieuw) ?></span><span class="tegel__label">nieuwe <?= count($nieuw) === 1 ? 'aanmelding' : 'aanmeldingen' ?></span></a></li>
<?php endif; ?>
</ul>
<?php if ($nieuw): ?>
<section class="panel" aria-labelledby="nieuw-titel">
  <h2 id="nieuw-titel">Nieuwe aanmeldingen</h2>
  <ul class="kindlijst">
<?php foreach ($nieuw as $ouder): ?>
    <li class="kindrij">
      <div>
        <div class="kindrij__naam"><a href="ouder.php?id=<?= (int) $ouder['id'] ?>"><?= e($ouder['naam']) ?></a></div>
        <div class="kindrij__info"><span><?= (int) $ouder['aantal_kinderen'] ?> <?= (int) $ouder['aantal_kinderen'] === 1 ? 'kind' : 'kinderen' ?></span><span>aangemeld <?= e(moment_nl($ouder['aangemaakt_op'])) ?></span><span><?= status_badge($ouder['mandaat_status']) ?></span></div>
      </div>
      <a class="btn btn--mini" href="ouder.php?id=<?= (int) $ouder['id'] ?>">Bekijken<span class="visually-hidden"> <?= e($ouder['naam']) ?></span></a>
    </li>
<?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
<?php if ($zonderGroep): ?>
<p class="melding"><?= icoon('info') ?><span><?= $zonderGroep ?> <?= $zonderGroep === 1 ? 'kind van een actieve ouder zit' : 'kinderen van actieve ouders zitten' ?> nog niet in een groep. <a href="kinderen.php?groep=geen">Bekijk en deel in</a>.</span></p>
<?php endif; ?>
<h2 class="visually-hidden">Groepen vandaag</h2>
<?php
if (!$groepen && !is_beheerder($gebruiker)) {
    echo '<div class="panel"><p class="leeg">Je bent nog niet aan een groep gekoppeld, dus je ziet nog geen kinderen. Vraag de beheerder om je aan je groep(en) te koppelen.</p></div>';
} elseif (!in_array(weekdag($datum), [1, 2, 3, 4, 5], true)) {
    echo '<div class="panel"><p class="leeg">Het is weekend, de BSO is dicht. <a href="agenda.php">Bekijk de agenda van volgende week</a>.</p></div>';
} else {
    toon_dagoverzicht($datum, $groepen, 'index.php');
}
pagina_einde();
