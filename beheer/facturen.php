<?php
/*
 * Facturen. Beheerder: maandfacturen maken en de automatische incasso starten.
 * Ouder: eigen facturen bekijken, printen en eventueel los betalen.
 */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_login();
if (is_team($gebruiker)) {
    vereis_recht('facturen');
}

/* ---------- Ouder ---------- */
if (is_ouder($gebruiker)) {
    $facturen = rijen('SELECT * FROM facturen WHERE ouder_id = ? ORDER BY datum DESC, id DESC', [$gebruiker['id']]);
    pagina_begin('Facturen', 'facturen.php');
    pagina_kop('Facturen', 'Al je facturen op één plek. Met een machtiging worden ze automatisch betaald.');
    if (!in_array($gebruiker['mandaat_status'], ['geldig', 'demo'], true)) {
        echo '<p class="melding">' . icoon('info') . '<span>Je hebt nog geen machtiging voor automatische incasso. <a href="machtiging.php">Regel het in een paar klikken</a>, dan hoef je nergens meer aan te denken.</span></p>';
    }
    ?>
<section class="panel" aria-labelledby="lijst-titel">
  <h2 id="lijst-titel" class="visually-hidden">Je facturen</h2>
<?php if (!$facturen): ?>
  <p class="leeg">Je hebt nog geen facturen. Je krijgt elke maand een factuur voor de opvang van de maand ervoor.</p>
<?php else: ?>
  <div class="tabel">
    <table>
      <thead><tr><th scope="col">Factuur</th><th scope="col">Maand</th><th scope="col">Datum</th><th scope="col" class="rechts">Bedrag</th><th scope="col">Status</th></tr></thead>
      <tbody>
<?php foreach ($facturen as $factuur): ?>
        <tr>
          <th scope="row"><a href="factuur.php?id=<?= (int) $factuur['id'] ?>"><?= e($factuur['nummer']) ?></a></th>
          <td><?= e(datum_nl($factuur['periode'] . '-01', 'MMMM y')) ?></td>
          <td><?= e(datum_nl($factuur['datum'])) ?></td>
          <td class="rechts"><?= e(geld((int) $factuur['bedrag_cent'])) ?></td>
          <td><?= status_badge($factuur['status']) ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</section>
<?php
    pagina_einde();
    exit;
}

/* ---------- Beheerder ---------- */

if (is_post()) {
    csrf_controleer();
    $actie = invoer('actie');
    $terug = veilig_terug(invoer('terug', 'facturen.php'));

    if ($actie === 'maken') {
        $periode = invoer('periode');
        try {
            if ($periode >= date('Y-m')) {
                throw new RuntimeException('Je kunt alleen facturen maken voor een maand die al voorbij is.');
            }
            [$aantal, $totaal] = maak_facturen($periode);
            flash($aantal ? 'succes' : 'info', $aantal
                ? "{$aantal} " . ($aantal === 1 ? 'factuur' : 'facturen') . ' gemaakt voor ' . datum_nl($periode . '-01', 'MMMM y') . ', samen ' . geld($totaal) . '. Controleer ze en start daarna de incasso.'
                : 'Er is niets te factureren voor ' . datum_nl($periode . '-01', 'MMMM y') . ' (of de facturen bestaan al).');
            if ($aantal) {
                foreach (rijen("SELECT DISTINCT ouder_id FROM facturen WHERE periode = ? AND aangemaakt_op >= ?", [$periode, date('Y-m-d H:i:s', time() - 60)]) as $rij) {
                    systeembericht((int) $rij['ouder_id'], 'Je factuur voor ' . datum_nl($periode . '-01', 'MMMM y') . ' staat klaar bij Facturen.');
                }
            }
            redirect('facturen.php?periode=' . $periode);
        } catch (RuntimeException | InvalidArgumentException $fout) {
            flash('fout', $fout->getMessage());
        }
        redirect('facturen.php');
    }

    if ($actie === 'incasso_alle') {
        $periode = invoer('periode');
        $open = rijen("SELECT f.* FROM facturen f JOIN gebruikers g ON g.id = f.ouder_id WHERE f.status = 'open' AND f.periode = ? AND g.mandaat_status IN ('geldig', 'demo')", [$periode]);
        $gelukt = 0;
        $fouten = [];
        foreach ($open as $factuur) {
            try {
                start_incasso($factuur);
                $gelukt++;
            } catch (RuntimeException $fout) {
                $fouten[] = $factuur['nummer'] . ': ' . $fout->getMessage();
            }
        }
        flash($gelukt ? 'succes' : 'info', $gelukt ? "Incasso gestart voor {$gelukt} " . ($gelukt === 1 ? 'factuur' : 'facturen') . '.' : 'Er waren geen open facturen met een geldige machtiging.');
        if ($fouten) {
            flash('fout', implode(' ', $fouten));
        }
        redirect('facturen.php?periode=' . rawurlencode($periode));
    }

    $factuur = rij('SELECT * FROM facturen WHERE id = ?', [(int) invoer('factuur')]);
    if (!$factuur) {
        niet_gevonden();
    }
    if ($actie === 'incasso') {
        try {
            start_incasso($factuur);
            flash('succes', 'Incasso gestart voor factuur ' . $factuur['nummer'] . '.');
        } catch (RuntimeException $fout) {
            flash('fout', $fout->getMessage());
        }
    } elseif ($actie === 'betaald') {
        q("UPDATE facturen SET status = 'betaald', betaald_op = ?, notitie = 'Handmatig als betaald gemarkeerd' WHERE id = ?", [nu(), $factuur['id']]);
        log_actie('Factuur handmatig betaald', $factuur['nummer']);
        flash('succes', 'Factuur ' . $factuur['nummer'] . ' staat op betaald.');
    } elseif ($actie === 'annuleren' && $factuur['status'] !== 'betaald') {
        transactie(function () use ($factuur) {
            q("UPDATE facturen SET status = 'geannuleerd' WHERE id = ?", [$factuur['id']]);
            // De dagen kunnen opnieuw gefactureerd worden (na een correctie)
            q("UPDATE inschrijvingen SET gefactureerd = 0 WHERE kind_id IN (SELECT id FROM kinderen WHERE ouder_id = ?) AND substr(datum, 1, 7) = ?", [$factuur['ouder_id'], $factuur['periode']]);
        });
        log_actie('Factuur geannuleerd', $factuur['nummer']);
        flash('succes', 'Factuur ' . $factuur['nummer'] . ' is geannuleerd. Je kunt voor deze maand opnieuw een factuur maken.');
    }
    redirect($terug);
}

$periode = preg_match('/^\d{4}-\d{2}$/', get_str('periode')) ? get_str('periode') : date('Y-m', strtotime('first day of last month'));
$facturen = rijen(
    'SELECT f.*, g.naam, g.mandaat_status FROM facturen f JOIN gebruikers g ON g.id = f.ouder_id WHERE f.periode = ? ORDER BY f.nummer',
    [$periode]
);
$totaal = array_sum(array_map(fn ($f) => $f['status'] === 'geannuleerd' ? 0 : (int) $f['bedrag_cent'], $facturen));
$openMetMachtiging = count(array_filter($facturen, fn ($f) => $f['status'] === 'open' && in_array($f['mandaat_status'], ['geldig', 'demo'], true)));
$maanden = [];
for ($i = 1; $i <= 12; $i++) {
    $maanden[] = date('Y-m', strtotime("first day of -{$i} month"));
}

pagina_begin('Facturen', 'facturen.php');
pagina_kop('Facturen', 'Maak na afloop van een maand de facturen en start daarna de automatische incasso.');
if (!factuur_instellingen_compleet()) {
    echo '<p class="melding melding--fout">' . icoon('alert') . '<span>Het uurtarief of het aantal uren per middag is nog niet ingesteld. <a href="instellingen.php">Vul ze in bij Instellingen</a> voordat je facturen maakt.</span></p>';
}
?>
<div class="kolommen">
  <section class="panel" aria-labelledby="lijst-titel">
    <div class="panel__kop">
      <h2 id="lijst-titel">Facturen <?= e(datum_nl($periode . '-01', 'MMMM y')) ?></h2>
      <form class="inline-form" method="get"><label class="visually-hidden" for="periode">Maand</label><select id="periode" name="periode">
<?php foreach (array_unique(array_merge([$periode], $maanden)) as $m): ?>
        <option value="<?= e($m) ?>"<?= $m === $periode ? ' selected' : '' ?>><?= e(datum_nl($m . '-01', 'MMMM y')) ?></option>
<?php endforeach; ?>
      </select><button class="btn btn--secondary btn--mini" type="submit">Toon</button></form>
    </div>
<?php if (!$facturen): ?>
    <p class="leeg">Nog geen facturen voor deze maand.</p>
<?php else: ?>
    <div class="tabel">
      <table>
        <thead><tr><th scope="col">Nummer</th><th scope="col">Ouder</th><th scope="col" class="rechts">Bedrag</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Acties</span></th></tr></thead>
        <tbody>
<?php foreach ($facturen as $factuur): ?>
          <tr>
            <th scope="row"><a href="factuur.php?id=<?= (int) $factuur['id'] ?>"><?= e($factuur['nummer']) ?></a></th>
            <td><a href="ouder.php?id=<?= (int) $factuur['ouder_id'] ?>"><?= e($factuur['naam']) ?></a><br><?= status_badge($factuur['mandaat_status']) ?></td>
            <td class="rechts"><?= e(geld((int) $factuur['bedrag_cent'])) ?></td>
            <td><?= status_badge($factuur['status']) ?></td>
            <td>
              <div class="btn-group">
<?php if (in_array($factuur['status'], ['open', 'mislukt'], true) && in_array($factuur['mandaat_status'], ['geldig', 'demo'], true)): ?>
                <form class="inline-form" method="post"><?= csrf_veld() ?><input type="hidden" name="actie" value="incasso"><input type="hidden" name="factuur" value="<?= (int) $factuur['id'] ?>"><input type="hidden" name="terug" value="facturen.php?periode=<?= e($periode) ?>"><button class="btn btn--mini" type="submit">Incasso<span class="visually-hidden"> <?= e($factuur['nummer']) ?></span></button></form>
<?php endif; ?>
<?php if (in_array($factuur['status'], ['open', 'mislukt', 'incasso'], true)): ?>
                <form class="inline-form" method="post" data-bevestig="Factuur <?= e($factuur['nummer']) ?> als betaald markeren?"><?= csrf_veld() ?><input type="hidden" name="actie" value="betaald"><input type="hidden" name="factuur" value="<?= (int) $factuur['id'] ?>"><input type="hidden" name="terug" value="facturen.php?periode=<?= e($periode) ?>"><button class="btn btn--secondary btn--mini" type="submit">Betaald<span class="visually-hidden"> <?= e($factuur['nummer']) ?></span></button></form>
<?php endif; ?>
<?php if (in_array($factuur['status'], ['open', 'mislukt'], true)): ?>
                <form class="inline-form" method="post" data-bevestig="Factuur <?= e($factuur['nummer']) ?> annuleren? Daarna kun je voor deze ouder en maand een nieuwe factuur maken."><?= csrf_veld() ?><input type="hidden" name="actie" value="annuleren"><input type="hidden" name="factuur" value="<?= (int) $factuur['id'] ?>"><input type="hidden" name="terug" value="facturen.php?periode=<?= e($periode) ?>"><button class="btn btn--gevaar btn--mini" type="submit">Annuleren<span class="visually-hidden"> <?= e($factuur['nummer']) ?></span></button></form>
<?php endif; ?>
              </div>
            </td>
          </tr>
<?php endforeach; ?>
        </tbody>
        <tfoot><tr><th scope="row" colspan="2">Totaal</th><td class="rechts"><strong><?= e(geld($totaal)) ?></strong></td><td colspan="2"></td></tr></tfoot>
      </table>
    </div>
<?php if ($openMetMachtiging): ?>
    <form method="post" data-bevestig="Incasso starten voor <?= $openMetMachtiging ?> open <?= $openMetMachtiging === 1 ? 'factuur' : 'facturen' ?>?" style="margin-top: var(--space-m)">
      <?= csrf_veld() ?><input type="hidden" name="actie" value="incasso_alle"><input type="hidden" name="periode" value="<?= e($periode) ?>">
      <button class="btn" type="submit"><?= icoon('bank') ?>Incasso starten voor <?= $openMetMachtiging ?> open <?= $openMetMachtiging === 1 ? 'factuur' : 'facturen' ?></button>
    </form>
<?php endif; ?>
<?php endif; ?>
  </section>
  <section class="panel" aria-labelledby="maken-titel">
    <h2 id="maken-titel">Facturen maken</h2>
    <p>Voor elke ouder één factuur met de dagen dat de kinderen die maand ingeschreven stonden (komt of ziek). Op tijd afgemelde dagen tellen niet mee.</p>
    <dl class="gegevens">
      <dt>Uurtarief</dt><dd><?= instelling('uurtarief_cent') !== '' ? e(geld((int) instelling('uurtarief_cent'))) : '<a href="instellingen.php">Nog instellen</a>' ?></dd>
      <dt>Uren per middag</dt><dd><?= instelling('uren_per_middag') !== '' ? e(instelling('uren_per_middag')) : '<a href="instellingen.php">Nog instellen</a>' ?></dd>
    </dl>
    <form class="form" method="post" style="margin-top: var(--space-m)">
      <?= csrf_veld() ?>
      <input type="hidden" name="actie" value="maken">
      <div class="field field--klein"><label for="maken-periode">Maand</label><select id="maken-periode" name="periode">
<?php foreach ($maanden as $m): ?>
        <option value="<?= e($m) ?>"><?= e(datum_nl($m . '-01', 'MMMM y')) ?></option>
<?php endforeach; ?>
      </select></div>
      <div class="form__acties"><button class="btn" type="submit"<?= factuur_instellingen_compleet() ? '' : ' disabled' ?>>Facturen maken</button></div>
    </form>
  </section>
</div>
<?php
pagina_einde();
