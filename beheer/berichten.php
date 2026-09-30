<?php
/*
 * Berichten heen en weer tussen ouder en team, per gezin één gesprek.
 * Ouders kunnen hier ook hun kind ziek melden.
 */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_login();
$team = is_team($gebruiker);

if ($team) {
    $ouderId = get_int('ouder') ?: (int) invoer('ouder');
    $ouder = $ouderId ? rij("SELECT * FROM gebruikers WHERE id = ? AND rol = 'ouder' AND gewist_op IS NULL", [$ouderId]) : null;
    if ($ouderId && (!$ouder || !team_mag_ouder((int) $ouder['id']))) {
        niet_gevonden();
    }
} else {
    $ouder = $gebruiker;
}

if (is_post() && $ouder) {
    csrf_controleer();
    $actie = invoer('actie', 'bericht');
    $terug = $team ? 'berichten.php?ouder=' . (int) $ouder['id'] : 'berichten.php';

    if ($actie === 'bericht') {
        $tekst = mb_substr(invoer('tekst'), 0, 5000);
        if ($tekst === '') {
            flash('fout', 'Je bericht is nog leeg.');
            redirect($terug);
        }
        $kindId = (int) invoer('kind') ?: null;
        if ($kindId && !heeft_recht($kindId, (int) $ouder['id'], 'berichten')) {
            $kindId = null;
        }
        nieuw_bericht((int) $ouder['id'], (int) $gebruiker['id'], $kindId, 'bericht', $tekst, !$team, $team);
        if ($team) {
            log_actie('Bericht gestuurd', '', null, 'wijziging', 'gebruiker:' . $ouder['id']);
        }
        if ($team) {
            stuur_mail($ouder['email'], 'Nieuw bericht van BSO VCK', "Hoi {$ouder['naam']},\n\nJe hebt een nieuw bericht van BSO VCK. Lees het hier:\n" . app_url('berichten.php'));
        } else {
            mail_team('Nieuw bericht van ' . $ouder['naam'], 'Er is een nieuw bericht van ' . $ouder['naam'] . ":\n" . app_url('berichten.php?ouder=' . $ouder['id']));
        }
        flash('succes', 'Je bericht is verstuurd.');
        redirect($terug . '#gesprek');
    }

    if ($actie === 'ziek' && !$team) {
        $kind = heeft_recht((int) invoer('kind'), (int) $ouder['id'], 'agenda') ? rij('SELECT * FROM kinderen WHERE id = ?', [(int) invoer('kind')]) : null;
        $van = invoer('van');
        $tot = invoer('tot') ?: $van;
        if (!$kind || !geldige_datum($van) || !geldige_datum($tot) || $van < vandaag() || $tot < $van || $tot > date('Y-m-d', strtotime('+30 days'))) {
            flash('fout', 'Kies je kind en een geldige periode (vanaf vandaag, maximaal 30 dagen vooruit).');
            redirect('berichten.php#ziekmelden');
        }
        $aantal = 0;
        foreach (rijen("SELECT * FROM inschrijvingen WHERE kind_id = ? AND datum BETWEEN ? AND ? AND status IN ('bevestigd', 'wachtlijst')", [$kind['id'], $van, $tot]) as $inschrijving) {
            // Op de wachtlijst hoef je geen plek vast te houden: afmelden. Anders: ziek.
            meld_af($inschrijving, $inschrijving['status'] === 'wachtlijst' ? 'afgemeld' : 'ziek');
            $aantal++;
        }
        $periode = $van === $tot ? datum_nl($van, 'EEEE d MMMM') : datum_nl($van, 'd MMMM') . ' t/m ' . datum_nl($tot, 'd MMMM');
        $toelichting = mb_substr(invoer('toelichting'), 0, 1000);
        nieuw_bericht((int) $ouder['id'], (int) $gebruiker['id'], (int) $kind['id'], 'ziekmelding', $kind['voornaam'] . ' is ziek (' . $periode . ').' . ($toelichting !== '' ? "\n" . $toelichting : ''), true, false);
        mail_team('Ziekmelding: ' . $kind['voornaam'], $ouder['naam'] . ' heeft ' . $kind['voornaam'] . ' ziek gemeld (' . $periode . ').' . "\n" . app_url('berichten.php?ouder=' . $ouder['id']));
        log_actie('Ziekmelding', $van . ' t/m ' . $tot, null, 'wijziging', 'kind:' . $kind['id']);
        flash('succes', $kind['voornaam'] . ' is ziek gemeld' . ($aantal ? ' en afgemeld voor ' . $aantal . ' ' . ($aantal === 1 ? 'dag' : 'dagen') : '') . '. Beterschap!');
        redirect('berichten.php#gesprek');
    }
}

/* ---------- Weergave ---------- */

if ($team && !$ouder) {
    $gesprekken = rijen(
        "SELECT g.id, g.naam, MAX(b.aangemaakt_op) AS laatste, SUM(b.gelezen_team = 0) AS ongelezen,
                (SELECT tekst FROM berichten b2 WHERE b2.ouder_id = g.id ORDER BY b2.aangemaakt_op DESC, b2.id DESC LIMIT 1) AS voorbeeld
         FROM berichten b JOIN gebruikers g ON g.id = b.ouder_id
         WHERE " . ouder_voorwaarde('g.id') . "
         GROUP BY g.id ORDER BY ongelezen > 0 DESC, laatste DESC LIMIT 200"
    );
    $gesprekken = ontsleutel_kolommen($gesprekken, ['voorbeeld']);
    $ouders = rijen("SELECT g.id, g.naam FROM gebruikers g WHERE g.rol = 'ouder' AND g.status != 'gestopt' AND " . ouder_voorwaarde('g.id') . ' ORDER BY g.naam');

    pagina_begin('Berichten', 'berichten.php');
    pagina_kop('Berichten', 'Alle gesprekken met ouders. Ongelezen gesprekken staan bovenaan.');
    ?>
<div class="kolommen">
  <section class="panel" aria-labelledby="gesprekken-titel">
    <h2 id="gesprekken-titel">Gesprekken</h2>
<?php if (!$gesprekken): ?>
    <p class="leeg">Nog geen berichten.</p>
<?php else: ?>
    <ul class="gesprekken">
<?php foreach ($gesprekken as $g): ?>
      <li><a href="berichten.php?ouder=<?= (int) $g['id'] ?>#gesprek"<?= $g['ongelezen'] ? ' class="ongelezen"' : '' ?>><strong><?= e($g['naam']) ?><?= $g['ongelezen'] ? '<span class="visually-hidden"> (' . (int) $g['ongelezen'] . ' ongelezen)</span>' : '' ?></strong><span class="muted"><?= e(moment_nl($g['laatste'])) ?></span><span class="voorbeeld"><?= e($g['voorbeeld']) ?></span></a></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </section>
  <section class="panel" aria-labelledby="nieuw-titel">
    <h2 id="nieuw-titel">Nieuw bericht</h2>
    <form class="form" method="get">
      <div class="field"><label for="ouder">Aan</label><select id="ouder" name="ouder">
<?php foreach ($ouders as $o): ?>
        <option value="<?= (int) $o['id'] ?>"><?= e($o['naam']) ?></option>
<?php endforeach; ?>
      </select></div>
      <div class="form__acties"><button class="btn" type="submit">Open gesprek</button></div>
    </form>
  </section>
</div>
<?php
    pagina_einde();
    exit;
}

// Gesprek openen = gelezen; inzage door het team wordt vastgelegd
if ($team) {
    log_inzage('Berichten bekeken', 'gebruiker:' . $ouder['id']);
}
q($team ? 'UPDATE berichten SET gelezen_team = 1 WHERE ouder_id = ? AND gelezen_team = 0' : 'UPDATE berichten SET gelezen_ouder = 1 WHERE ouder_id = ? AND gelezen_ouder = 0', [$ouder['id']]);

$berichten = rijen(
    'SELECT b.*, a.naam AS afzender, a.rol AS afzender_rol, k.voornaam AS kind FROM berichten b
     LEFT JOIN gebruikers a ON a.id = b.afzender_id LEFT JOIN kinderen k ON k.id = b.kind_id
     WHERE b.ouder_id = ? ORDER BY b.aangemaakt_op, b.id',
    [$ouder['id']]
);
$berichten = ontsleutel_kolommen($berichten, ['tekst']);
$kinderen = kinderen_van_verzorger((int) $ouder['id'], 'berichten');
$ziekKinderen = kinderen_van_verzorger((int) $ouder['id'], 'agenda');

pagina_begin('Berichten', 'berichten.php');
if ($team) {
    echo '<a class="terug-link" href="berichten.php">' . icoon('back') . 'Alle gesprekken</a>';
    pagina_kop('Gesprek met ' . $ouder['naam'], e(implode(', ', array_map('kindnaam', $kinderen))), '<a class="btn btn--secondary btn--small" href="ouder.php?id=' . (int) $ouder['id'] . '">' . icoon('user') . 'Gegevens</a>');
} else {
    pagina_kop('Berichten', 'Heb je een vraag, is er iets bijzonders of is je kind ziek? Laat het ons weten. We reageren zo snel mogelijk.');
}
?>
<div class="kolommen">
  <section class="panel" id="gesprek" aria-labelledby="gesprek-titel">
    <h2 id="gesprek-titel"><?= $team ? 'Berichten' : 'Gesprek met het team' ?></h2>
<?php if (!$berichten): ?>
    <p class="leeg">Nog geen berichten. Stuur hieronder het eerste bericht.</p>
<?php else: ?>
    <ol class="gesprek" data-scroll-einde tabindex="0" aria-label="Berichten, oudste eerst">
<?php foreach ($berichten as $b):
    $eigen = (int) $b['afzender_id'] === (int) $gebruiker['id'] || ($team && $b['afzender_rol'] && $b['afzender_rol'] !== 'ouder' && $b['soort'] !== 'systeem');
    $klasse = 'bericht' . ($b['soort'] === 'systeem' ? ' bericht--systeem' : ($eigen ? ' bericht--eigen' : '')) . ($b['soort'] === 'ziekmelding' ? ' bericht--ziekmelding' : '');
    $wie = $b['soort'] === 'systeem' && $b['afzender_rol'] !== 'ouder' ? 'BSO VCK' : ($b['afzender_rol'] === 'ouder' ? ($team ? $b['afzender'] : 'Jij') : ($b['afzender'] ? explode(' ', $b['afzender'])[0] . ' van BSO VCK' : 'BSO VCK'));
    ?>
      <li class="<?= $klasse ?>">
        <span class="bericht__meta"><?= e($wie) ?> · <?= e(moment_nl($b['aangemaakt_op'])) ?><?= $b['soort'] === 'ziekmelding' ? ' · Ziekmelding' : '' ?><?= $b['kind'] && $b['soort'] === 'bericht' ? ' · over ' . e($b['kind']) : '' ?></span>
        <p><?= e($b['tekst']) ?></p>
      </li>
<?php endforeach; ?>
    </ol>
<?php endif; ?>
    <form class="form" method="post" action="berichten.php<?= $team ? '?ouder=' . (int) $ouder['id'] : '' ?>">
      <?= csrf_veld() ?>
      <input type="hidden" name="actie" value="bericht">
      <div class="field">
        <label for="tekst">Je bericht</label>
        <textarea id="tekst" name="tekst" rows="4" required maxlength="5000"></textarea>
      </div>
<?php if (count($kinderen) > 1): ?>
      <div class="field field--klein"><label for="bericht-kind">Over <span class="field__hint">(optioneel)</span></label><select id="bericht-kind" name="kind"><option value="">Alle kinderen</option>
<?php foreach ($kinderen as $k): ?>
        <option value="<?= (int) $k['id'] ?>"><?= e($k['voornaam']) ?></option>
<?php endforeach; ?>
      </select></div>
<?php endif; ?>
      <div class="form__acties"><button class="btn" type="submit"><?= icoon('send') ?>Versturen</button></div>
    </form>
  </section>
<?php if (!$team): ?>
  <section class="panel" id="ziekmelden" aria-labelledby="ziek-titel">
    <h2 id="ziek-titel">Ziek melden</h2>
<?php if (!$ziekKinderen): ?>
    <p class="leeg">Er zijn geen kinderen die jij ziek kunt melden.</p>
<?php else: ?>
    <p>Je kind wordt afgemeld voor de gekozen dagen en wij krijgen direct een seintje.</p>
    <form class="form" method="post" action="berichten.php">
      <?= csrf_veld() ?>
      <input type="hidden" name="actie" value="ziek">
      <div class="field"><label for="ziek-kind">Wie is er ziek?</label><select id="ziek-kind" name="kind">
<?php foreach ($ziekKinderen as $k): ?>
        <option value="<?= (int) $k['id'] ?>"><?= e($k['voornaam']) ?></option>
<?php endforeach; ?>
      </select></div>
      <div class="form__row">
        <div class="field"><label for="ziek-van">Vanaf</label><input id="ziek-van" name="van" type="date" min="<?= vandaag() ?>" value="<?= vandaag() ?>" required></div>
        <div class="field"><label for="ziek-tot">Tot en met <span class="field__hint">(optioneel)</span></label><input id="ziek-tot" name="tot" type="date" min="<?= vandaag() ?>" max="<?= date('Y-m-d', strtotime('+30 days')) ?>"></div>
      </div>
      <div class="field"><label for="toelichting">Toelichting <span class="field__hint">(optioneel)</span></label><textarea id="toelichting" name="toelichting" rows="2" style="min-height: 5rem"></textarea></div>
      <div class="form__acties"><button class="btn" type="submit">Ziek melden</button></div>
    </form>
<?php endif; ?>
  </section>
<?php endif; ?>
</div>
<?php
pagina_einde();
