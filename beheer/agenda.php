<?php
/*
 * Agenda. Ouders: dagen kiezen en afmelden per kind.
 * Team: weekoverzicht per groep en dagoverzicht met wie er komt.
 */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_login();

if (is_ouder($gebruiker)) {
    agenda_ouder($gebruiker);
} else {
    agenda_team();
}

/* ======================= Ouders ======================= */

function agenda_ouder(array $ouder): void
{
    $kinderen = rijen('SELECT k.*, g.naam AS groepnaam, g.dagen AS groepdagen FROM kinderen k LEFT JOIN groepen g ON g.id = k.groep_id WHERE k.ouder_id = ? AND k.actief = 1 ORDER BY k.voornaam', [$ouder['id']]);
    $kindId = get_int('kind') ?: (int) (invoer('kind') ?: 0);
    $kind = $kinderen[0] ?? null;
    foreach ($kinderen as $k) {
        if ((int) $k['id'] === $kindId) {
            $kind = $k;
            break;
        }
    }
    $maand = preg_match('/^\d{4}-\d{2}$/', get_str('maand')) ? get_str('maand') : date('Y-m');
    $mag_boeken = $ouder['status'] === 'actief' && $kind && $kind['groep_id'];

    if (is_post() && $kind) {
        csrf_controleer();
        if (!$mag_boeken) {
            flash('fout', 'Je kunt nog geen dagen kiezen. Na de kennismaking zetten we je kind in een groep.');
            redirect('agenda.php?kind=' . $kind['id']);
        }
        $terug = 'agenda.php?kind=' . $kind['id'] . '&maand=' . $maand;
        $actie = invoer('actie');
        $datum = invoer('datum');

        if ($actie === 'aanmelden' && geldige_datum($datum)) {
            if (!ouder_mag_wijzigen($datum) || $datum > date('Y-m-d', strtotime('+1 year'))) {
                flash('fout', 'Voor deze dag kun je je kind niet meer zelf aanmelden. Stuur ons een berichtje, dan kijken we wat er kan.');
            } else {
                $status = schrijf_in($kind, $datum, (int) $ouder['id']);
                if ($status === 'bevestigd') {
                    flash('succes', 'Gelukt! ' . $kind['voornaam'] . ' komt op ' . datum_nl($datum, 'EEEE d MMMM') . '.');
                } elseif ($status === 'wachtlijst') {
                    flash('info', 'De groep is vol op ' . datum_nl($datum, 'EEEE d MMMM') . '. ' . $kind['voornaam'] . ' staat op de wachtlijst. Komt er een plekje vrij, dan krijg je automatisch bericht.');
                } else {
                    flash('fout', 'Op deze dag is de groep gesloten.');
                }
            }
        } elseif (in_array($actie, ['afmelden', 'ziek'], true)) {
            $inschrijving = rij('SELECT * FROM inschrijvingen WHERE id = ? AND kind_id = ?', [(int) invoer('inschrijving'), $kind['id']]);
            if (!$inschrijving) {
                niet_gevonden();
            }
            if ($actie === 'ziek' && $inschrijving['datum'] === vandaag() && $inschrijving['status'] === 'bevestigd') {
                meld_af($inschrijving, 'ziek');
                q("INSERT INTO berichten (ouder_id, afzender_id, kind_id, soort, tekst, gelezen_ouder, gelezen_team, aangemaakt_op) VALUES (?, ?, ?, 'ziekmelding', ?, 1, 0, ?)", [
                    $ouder['id'], $ouder['id'], $kind['id'], $kind['voornaam'] . ' is vandaag ziek.', nu(),
                ]);
                flash('succes', $kind['voornaam'] . ' is ziek gemeld voor vandaag. Beterschap!');
            } elseif ($actie === 'afmelden' && ouder_mag_wijzigen($inschrijving['datum']) && in_array($inschrijving['status'], ['bevestigd', 'wachtlijst'], true)) {
                meld_af($inschrijving);
                flash('succes', $kind['voornaam'] . ' is afgemeld voor ' . datum_nl($inschrijving['datum'], 'EEEE d MMMM') . '.');
            } else {
                flash('fout', 'Afmelden kan niet meer voor deze dag. Is je kind ziek? Meld het dan ziek, of stuur ons een berichtje.');
            }
        } elseif ($actie === 'vaste_dagen') {
            $dagen = array_values(array_intersect(array_map('intval', (array) ($_POST['dagen'] ?? [])), groep_dagen(groep((int) $kind['groep_id']))));
            $van = invoer('van');
            $tot = invoer('tot');
            if (!$dagen || !geldige_datum($van) || !geldige_datum($tot) || $tot < $van) {
                flash('fout', 'Kies minstens één dag en een geldige periode.');
            } else {
                $van = max($van, vandaag());
                $tot = min($tot, date('Y-m-d', strtotime('+1 year')));
                $telling = ['bevestigd' => 0, 'wachtlijst' => 0, 'dicht' => 0];
                foreach (datums_tussen($van, $tot) as $d) {
                    if (!in_array(weekdag($d), $dagen, true) || !ouder_mag_wijzigen($d)) {
                        continue;
                    }
                    $status = schrijf_in($kind, $d, (int) $ouder['id']);
                    $telling[$status ?? 'dicht']++;
                }
                $tekst = $kind['voornaam'] . ' is ingeschreven: ' . $telling['bevestigd'] . ' ' . ($telling['bevestigd'] === 1 ? 'dag' : 'dagen') . ' bevestigd';
                if ($telling['wachtlijst']) {
                    $tekst .= ', ' . $telling['wachtlijst'] . ' op de wachtlijst';
                }
                if ($telling['dicht']) {
                    $tekst .= ', ' . $telling['dicht'] . ' overgeslagen omdat de groep dan dicht is';
                }
                flash('succes', $tekst . '.');
                log_actie('Vaste dagen gekozen', $kind['voornaam'] . " {$van} t/m {$tot}");
            }
        }
        redirect($terug);
    }

    pagina_begin('Agenda', 'agenda.php');
    pagina_kop('Agenda', 'Kies de dagen waarop je kind komt, of meld je kind af. Afmelden kan tot ' . e(instelling('afmelden_tot', '12:00')) . ' uur op de dag zelf.');

    if (!$kinderen) {
        echo '<div class="panel"><p class="leeg">Er staan nog geen kinderen in je account. <a href="kinderen.php">Voeg een kind toe</a>.</p></div>';
        pagina_einde();
        return;
    }
    if (count($kinderen) > 1) {
        echo '<ul class="kind-tabs" aria-label="Kies een kind">';
        foreach ($kinderen as $k) {
            echo '<li><a href="agenda.php?kind=' . (int) $k['id'] . '&amp;maand=' . e($maand) . '"' . ((int) $k['id'] === (int) $kind['id'] ? ' aria-current="true"' : '') . '>' . e($k['voornaam']) . '</a></li>';
        }
        echo '</ul>';
    }

    if (!$mag_boeken) {
        echo '<div class="panel"><h2>Bijna zover!</h2><p>' . ($ouder['status'] !== 'actief'
            ? 'We hebben je aanmelding ontvangen. Na de kennismaking zetten we ' . e($kind['voornaam']) . ' in een groep. Daarna kies je hier zelf de dagen.'
            : e($kind['voornaam']) . ' zit nog niet in een groep. Zodra we dat geregeld hebben, kies je hier de dagen.') . '</p><p><a class="btn btn--secondary" href="berichten.php">' . icoon('chat') . 'Stuur ons een bericht</a></p></div>';
        pagina_einde();
        return;
    }

    $groep = groep((int) $kind['groep_id']);
    $eerste = $maand . '-01';
    $laatste = date('Y-m-t', strtotime($eerste));
    $vorige = date('Y-m', strtotime($eerste . ' -1 month'));
    $volgende = date('Y-m', strtotime($eerste . ' +1 month'));
    $inschrijvingen = [];
    foreach (rijen('SELECT * FROM inschrijvingen WHERE kind_id = ? AND datum BETWEEN ? AND ?', [$kind['id'], $eerste, $laatste]) as $rij) {
        $inschrijvingen[$rij['datum']] = $rij;
    }
    $basis = 'agenda.php?kind=' . (int) $kind['id'];
    ?>
<div class="kolommen">
  <section class="panel" aria-labelledby="maand-titel">
    <div class="maandnav">
      <a class="btn btn--secondary btn--mini" href="<?= e($basis . '&maand=' . $vorige) ?>"><?= icoon('back') ?><span class="visually-hidden">Vorige maand</span></a>
      <h2 id="maand-titel"><?= e(datum_nl($eerste, 'MMMM y')) ?></h2>
      <a class="btn btn--secondary btn--mini" href="<?= e($basis . '&maand=' . $volgende) ?>"><?= icoon('arrow') ?><span class="visually-hidden">Volgende maand</span></a>
    </div>
    <p class="muted"><?= e($kind['voornaam']) ?> zit in groep <strong><?= e($groep['naam']) ?></strong> (<?= e($groep['begintijd']) ?>–<?= e($groep['eindtijd']) ?> uur).</p>
    <ol class="kalender">
<?php foreach ([1, 2, 3, 4, 5] as $w): ?>
      <li class="kalender__kop" aria-hidden="true"><?= e(ucfirst(WEEKDAGEN[$w])) ?></li>
<?php endforeach; ?>
<?php
    $eersteWeekdag = weekdag($eerste);
    if ($eersteWeekdag <= 5) {
        for ($i = 1; $i < $eersteWeekdag; $i++) {
            echo '<li class="kalender__dag kalender__dag--leeg" aria-hidden="true"></li>';
        }
    }
    foreach (datums_tussen($eerste, $laatste) as $datum) {
        $wd = weekdag($datum);
        if ($wd > 5) {
            continue;
        }
        $klassen = 'kalender__dag' . ($datum === vandaag() ? ' kalender__dag--vandaag' : '') . ($datum < vandaag() ? ' kalender__dag--verleden' : '');
        $inschrijving = $inschrijvingen[$datum] ?? null;
        $open = groep_open_op($groep, $datum);
        $wijzigbaar = ouder_mag_wijzigen($datum);
        echo '<li class="' . $klassen . '"><span class="kalender__datum">' . e(datum_nl($datum, 'd MMM')) . '<small>' . e(WEEKDAGEN[$wd]) . '</small></span>';

        if ($inschrijving && $inschrijving['status'] !== 'afgemeld') {
            echo status_badge($inschrijving['status']);
            if ($datum === vandaag() && $inschrijving['status'] === 'bevestigd') {
                echo ouder_knop('ziek', $kind, $maand, ['inschrijving' => $inschrijving['id']], icoon('thermo') . 'Ziek melden', 'btn btn--secondary btn--mini');
            }
            if ($wijzigbaar && in_array($inschrijving['status'], ['bevestigd', 'wachtlijst'], true)) {
                echo ouder_knop('afmelden', $kind, $maand, ['inschrijving' => $inschrijving['id']], 'Afmelden<span class="visually-hidden"> ' . e(datum_nl($datum, 'd MMMM')) . '</span>', 'btn btn--gevaar btn--mini');
            }
        } elseif (!$open) {
            $u = uitzondering((int) $groep['id'], $datum);
            echo '<span class="badge badge--gesloten">Gesloten</span>' . ($u && $u['notitie'] !== '' ? '<small class="muted">' . e($u['notitie']) . '</small>' : '');
        } elseif ($datum >= vandaag() && $wijzigbaar) {
            if ($inschrijving) {
                echo status_badge('afgemeld');
            }
            $vrij = capaciteit($groep, $datum) - bezetting((int) $groep['id'], $datum);
            if ($vrij > 0) {
                echo '<small class="muted">' . ($vrij === 1 ? 'Nog 1 plekje' : 'Nog ' . $vrij . ' plekjes') . '</small>';
                echo ouder_knop('aanmelden', $kind, $maand, ['datum' => $datum], icoon('plus') . 'Aanmelden<span class="visually-hidden"> ' . e(datum_nl($datum, 'd MMMM')) . '</span>', 'btn btn--mini');
            } else {
                echo '<small class="muted">Vol</small>';
                echo ouder_knop('aanmelden', $kind, $maand, ['datum' => $datum], 'Wachtlijst<span class="visually-hidden"> ' . e(datum_nl($datum, 'd MMMM')) . '</span>', 'btn btn--secondary btn--mini');
            }
        } elseif ($inschrijving) {
            echo status_badge('afgemeld');
        }
        echo '</li>';
    }
    ?>
    </ol>
    <ul class="legenda" aria-label="Uitleg">
      <li><?= status_badge('bevestigd') ?> je kind komt</li>
      <li><?= status_badge('wachtlijst') ?> je krijgt bericht als er plek is</li>
      <li><?= status_badge('ziek') ?> ziek gemeld</li>
    </ul>
  </section>

  <div class="stapel">
    <section class="panel" aria-labelledby="vast-titel">
      <h2 id="vast-titel">Vaste dagen kiezen</h2>
      <p>Komt <?= e($kind['voornaam']) ?> elke week op dezelfde dagen? Schrijf in één keer in voor een hele periode.</p>
      <form class="form" method="post" action="<?= e($basis . '&maand=' . $maand) ?>">
        <?= csrf_veld() ?>
        <input type="hidden" name="actie" value="vaste_dagen">
        <input type="hidden" name="kind" value="<?= (int) $kind['id'] ?>">
        <fieldset>
          <legend class="legend-label">Welke dagen?</legend>
          <div class="choices">
<?php foreach (groep_dagen($groep) as $w): ?>
            <label class="choice"><input type="checkbox" name="dagen[]" value="<?= $w ?>"><?= e(ucfirst(WEEKDAGEN[$w])) ?></label>
<?php endforeach; ?>
          </div>
        </fieldset>
        <div class="form__row">
          <div class="field">
            <label for="van">Vanaf</label>
            <input id="van" name="van" type="date" min="<?= vandaag() ?>" value="<?= e(max(vandaag(), $eerste)) ?>" required>
          </div>
          <div class="field">
            <label for="tot">Tot en met</label>
            <input id="tot" name="tot" type="date" min="<?= vandaag() ?>" max="<?= date('Y-m-d', strtotime('+1 year')) ?>" value="<?= e(date('Y-m-d', strtotime(max(vandaag(), $eerste) . ' +3 months'))) ?>" required>
          </div>
        </div>
        <div class="form__acties"><button class="btn" type="submit">Inschrijven</button></div>
      </form>
    </section>
<?php
    $komend = rijen("SELECT * FROM inschrijvingen WHERE kind_id = ? AND datum >= ? AND status != 'afgemeld' ORDER BY datum LIMIT 8", [$kind['id'], vandaag()]);
    ?>
    <section class="panel" aria-labelledby="komend-titel">
      <h2 id="komend-titel">Binnenkort</h2>
<?php if (!$komend): ?>
      <p class="leeg"><?= e($kind['voornaam']) ?> staat nog niet ingeschreven voor de komende tijd.</p>
<?php else: ?>
      <ul class="kindlijst">
<?php foreach ($komend as $rij): ?>
        <li class="kindrij"><span><?= e(ucfirst(datum_nl($rij['datum'], 'EEEE d MMMM'))) ?></span><?= status_badge($rij['status']) ?></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </section>
  </div>
</div>
<?php
    pagina_einde();
}

function ouder_knop(string $actie, array $kind, string $maand, array $velden, string $label, string $klasse): string
{
    $html = '<form method="post" action="agenda.php?kind=' . (int) $kind['id'] . '&amp;maand=' . e($maand) . '">' . csrf_veld()
        . '<input type="hidden" name="actie" value="' . e($actie) . '"><input type="hidden" name="kind" value="' . (int) $kind['id'] . '">';
    foreach ($velden as $naam => $waarde) {
        $html .= '<input type="hidden" name="' . e($naam) . '" value="' . e((string) $waarde) . '">';
    }
    return $html . '<button class="' . e($klasse) . '" type="submit">' . $label . '</button></form>';
}

/* ======================= Team ======================= */

function agenda_team(): void
{
    $datum = get_str('datum');
    if ($datum !== '' && geldige_datum($datum)) {
        agenda_team_dag($datum);
        return;
    }
    $week = get_str('week');
    $maandag = maandag_van(geldige_datum($week) ? $week : vandaag());
    $dagen = datums_tussen($maandag, date('Y-m-d', strtotime($maandag . ' +4 days')));
    $groepen = actieve_groepen();

    pagina_begin('Agenda', 'agenda.php');
    pagina_kop('Agenda', 'Per groep en per dag: hoeveel kinderen er komen en hoeveel plek er nog is. Klik op een dag voor de namen.',
        '<a class="btn btn--secondary btn--small" href="agenda.php?datum=' . vandaag() . '">' . icoon('calendar') . 'Vandaag</a>');
    ?>
<section class="panel" aria-labelledby="week-titel">
  <div class="maandnav">
    <a class="btn btn--secondary btn--mini" href="agenda.php?week=<?= e(date('Y-m-d', strtotime($maandag . ' -7 days'))) ?>"><?= icoon('back') ?><span class="visually-hidden">Vorige week</span></a>
    <h2 id="week-titel">Week <?= (int) date('W', strtotime($maandag)) ?> <span class="muted" style="font-size: var(--step-0); font-weight: 600; text-transform: none;"><?= e(datum_nl($maandag, 'd MMM')) ?> – <?= e(datum_nl(end($dagen), 'd MMM y')) ?></span></h2>
    <a class="btn btn--secondary btn--mini" href="agenda.php?week=<?= e(date('Y-m-d', strtotime($maandag . ' +7 days'))) ?>"><?= icoon('arrow') ?><span class="visually-hidden">Volgende week</span></a>
  </div>
<?php if (!$groepen): ?>
  <p class="leeg">Er zijn nog geen groepen. <?= is_beheerder() ? '<a href="groepen.php">Maak eerst een groep aan.</a>' : '' ?></p>
<?php else: ?>
  <div class="tabel week">
    <table>
      <caption class="visually-hidden">Bezetting per groep in week <?= (int) date('W', strtotime($maandag)) ?></caption>
      <thead>
        <tr><th scope="col">Groep</th>
<?php foreach ($dagen as $d): ?>
          <th scope="col"><a href="agenda.php?datum=<?= e($d) ?>"><?= e(ucfirst(datum_kort($d))) ?></a></th>
<?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
<?php foreach ($groepen as $groep): ?>
        <tr>
          <th scope="row"><span class="groep-kleur kleur--<?= e($groep['kleur']) ?>" aria-hidden="true"></span><?= e($groep['naam']) ?><br><small class="muted">max <?= (int) $groep['max_kinderen'] ?></small></th>
<?php foreach ($dagen as $d):
    if (!groep_open_op($groep, $d)): ?>
          <td><span class="bezetting bezetting--dicht">Gesloten</span></td>
<?php else:
    $cap = capaciteit($groep, $d);
    $bez = bezetting((int) $groep['id'], $d);
    $wacht = aantal_wachtlijst((int) $groep['id'], $d);
    $klasse = $bez >= $cap ? 'vol' : ($bez >= $cap * 0.8 ? 'bijna' : 'ruim');
    ?>
          <td><a class="bezetting bezetting--<?= $klasse ?>" href="agenda.php?datum=<?= e($d) ?>#groep-<?= (int) $groep['id'] ?>-titel"><span><?= $bez ?> / <?= $cap ?><span class="visually-hidden"> kinderen</span></span><span class="bezetting__balk" aria-hidden="true"><span style="width: <?= min(100, (int) round($bez / max(1, $cap) * 100)) ?>%"></span></span><?php if ($wacht): ?><small><?= $wacht ?> wachtlijst</small><?php endif; ?></a></td>
<?php endif; endforeach; ?>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <ul class="legenda" aria-label="Uitleg kleuren">
    <li><span class="badge badge--bevestigd">Ruim plek</span></li>
    <li><span class="badge badge--wachtlijst">Bijna vol (80%)</span></li>
    <li><span class="badge badge--ziek">Vol</span></li>
  </ul>
<?php endif; ?>
</section>
<?php
    pagina_einde();
}

function agenda_team_dag(string $datum): void
{
    $vorige = date('Y-m-d', strtotime($datum . (weekdag($datum) === 1 ? ' -3 days' : ' -1 day')));
    $volgende = date('Y-m-d', strtotime($datum . (weekdag($datum) === 5 ? ' +3 days' : ' +1 day')));
    pagina_begin('Agenda ' . datum_nl($datum), 'agenda.php');
    echo '<a class="terug-link" href="agenda.php?week=' . e(maandag_van($datum)) . '">' . icoon('back') . 'Naar het weekoverzicht</a>';
    pagina_kop(ucfirst(datum_nl($datum, 'EEEE d MMMM y')), '',
        '<a class="btn btn--secondary btn--mini" href="agenda.php?datum=' . e($vorige) . '">' . icoon('back') . '<span class="visually-hidden">Vorige dag</span></a>'
        . '<form class="inline-form" method="get" action="agenda.php"><label class="visually-hidden" for="naar-datum">Ga naar datum</label><input class="datumkiezer" id="naar-datum" type="date" name="datum" value="' . e($datum) . '"><button class="btn btn--secondary btn--mini" type="submit">Ga</button></form>'
        . '<a class="btn btn--secondary btn--mini" href="agenda.php?datum=' . e($volgende) . '">' . icoon('arrow') . '<span class="visually-hidden">Volgende dag</span></a>');
    toon_dagoverzicht($datum, actieve_groepen(), 'agenda.php?datum=' . $datum);
    pagina_einde();
}
