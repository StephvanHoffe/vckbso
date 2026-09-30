<?php
/* Groepen beheren: naam, maximale groepsgrootte, dagen en tijden (beheerder). */
require __DIR__ . '/inc/bootstrap.php';

vereis_beheerder();

const GROEP_KLEUREN = ['sun' => 'Geel', 'mint' => 'Mintgroen', 'coral' => 'Koraal', 'sand' => 'Zand', 'primary' => 'Terracotta'];

$bewerk = get_int('id') ? groep(get_int('id')) : null;
if (get_int('id') && !$bewerk) {
    niet_gevonden();
}
$waarden = $bewerk ?? ['naam' => '', 'omschrijving' => '', 'max_kinderen' => '', 'dagen' => '1,2,3,4,5', 'begintijd' => '14:00', 'eindtijd' => '17:00', 'kleur' => 'sun', 'actief' => 1];
$fouten = [];

if (is_post()) {
    csrf_controleer();
    $waarden = [
        'naam' => invoer('naam'),
        'omschrijving' => invoer('omschrijving'),
        'max_kinderen' => invoer('max_kinderen'),
        'dagen' => implode(',', array_values(array_intersect(array_map('intval', (array) ($_POST['dagen'] ?? [])), [1, 2, 3, 4, 5]))),
        'begintijd' => invoer('begintijd'),
        'eindtijd' => invoer('eindtijd'),
        'kleur' => array_key_exists(invoer('kleur'), GROEP_KLEUREN) ? invoer('kleur') : 'sun',
        'actief' => invoer('actief') === '1' ? 1 : 0,
    ];
    if ($waarden['naam'] === '') {
        $fouten['naam'] = 'Geef de groep een naam.';
    }
    if (!ctype_digit($waarden['max_kinderen']) || (int) $waarden['max_kinderen'] < 1 || (int) $waarden['max_kinderen'] > 200) {
        $fouten['max_kinderen'] = 'Vul een maximale groepsgrootte in (een getal vanaf 1).';
    }
    if ($waarden['dagen'] === '') {
        $fouten['dagen'] = 'Kies minstens één dag waarop de groep open is.';
    }
    if (!geldige_tijd($waarden['begintijd']) || !geldige_tijd($waarden['eindtijd']) || $waarden['eindtijd'] <= $waarden['begintijd']) {
        $fouten['tijden'] = 'Vul een geldige begin- en eindtijd in (bijvoorbeeld 14:00 en 17:00).';
    }
    if (!$fouten) {
        $params = [$waarden['naam'], $waarden['omschrijving'], (int) $waarden['max_kinderen'], $waarden['dagen'], $waarden['begintijd'], $waarden['eindtijd'], $waarden['kleur'], $waarden['actief']];
        if ($bewerk) {
            q('UPDATE groepen SET naam = ?, omschrijving = ?, max_kinderen = ?, dagen = ?, begintijd = ?, eindtijd = ?, kleur = ?, actief = ? WHERE id = ?', [...$params, $bewerk['id']]);
            // Meer plek? Dan schuiven kinderen van de wachtlijst door
            if ((int) $waarden['max_kinderen'] > (int) $bewerk['max_kinderen']) {
                foreach (array_column(rijen("SELECT DISTINCT datum FROM inschrijvingen WHERE groep_id = ? AND status = 'wachtlijst' AND datum >= ?", [$bewerk['id'], vandaag()]), 'datum') as $d) {
                    vul_plek_vanuit_wachtlijst((int) $bewerk['id'], $d);
                }
            }
            log_actie('Groep gewijzigd', $waarden['naam']);
            flash('succes', 'Groep ' . $waarden['naam'] . ' is opgeslagen.');
        } else {
            q('INSERT INTO groepen (naam, omschrijving, max_kinderen, dagen, begintijd, eindtijd, kleur, actief) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $params);
            log_actie('Groep aangemaakt', $waarden['naam']);
            flash('succes', 'Groep ' . $waarden['naam'] . ' is aangemaakt. Zet nu de kinderen in de groep via Kinderen.');
        }
        redirect('groepen.php');
    }
}

$groepen = rijen("SELECT g.*, (SELECT COUNT(*) FROM kinderen k WHERE k.groep_id = g.id AND k.actief = 1) AS aantal FROM groepen g ORDER BY g.actief DESC, g.naam");
$gekozenDagen = array_map('intval', array_filter(explode(',', (string) $waarden['dagen'])));

pagina_begin('Groepen', 'groepen.php');
pagina_kop('Groepen', 'Stel per groep de maximale groepsgrootte, de dagen en de tijden in. Een dag sluiten of tijdelijk meer of minder plekken? Dat doe je in de agenda bij die dag.');
?>
<div class="kolommen">
  <section class="panel" aria-labelledby="lijst-titel">
    <h2 id="lijst-titel">Alle groepen</h2>
<?php if (!$groepen): ?>
    <p class="leeg">Nog geen groepen. Maak rechts je eerste groep aan.</p>
<?php else: ?>
    <div class="tabel">
      <table>
        <thead><tr><th scope="col">Groep</th><th scope="col">Max.</th><th scope="col">Dagen</th><th scope="col">Tijden</th><th scope="col">Kinderen</th><th scope="col"><span class="visually-hidden">Acties</span></th></tr></thead>
        <tbody>
<?php foreach ($groepen as $groep): ?>
          <tr>
            <th scope="row"><span class="groep-kleur kleur--<?= e($groep['kleur']) ?>" aria-hidden="true"></span><?= e($groep['naam']) ?><?= $groep['actief'] ? '' : ' <span class="badge badge--gestopt">Niet actief</span>' ?><?php if ($groep['omschrijving'] !== ''): ?><br><small class="muted"><?= e($groep['omschrijving']) ?></small><?php endif; ?></th>
            <td><?= (int) $groep['max_kinderen'] ?></td>
            <td><?= e(implode(', ', array_map(fn ($d) => mb_substr(WEEKDAGEN[$d], 0, 2), groep_dagen($groep)))) ?></td>
            <td><?= e($groep['begintijd']) ?>–<?= e($groep['eindtijd']) ?></td>
            <td><a href="kinderen.php?groep=<?= (int) $groep['id'] ?>"><?= (int) $groep['aantal'] ?></a></td>
            <td><a class="btn btn--secondary btn--mini" href="groepen.php?id=<?= (int) $groep['id'] ?>#formulier">Bewerken<span class="visually-hidden"> <?= e($groep['naam']) ?></span></a></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
<?php endif; ?>
  </section>

  <section class="panel" id="formulier" aria-labelledby="form-titel">
    <h2 id="form-titel"><?= $bewerk ? 'Groep bewerken' : 'Nieuwe groep' ?></h2>
    <?= foutensamenvatting($fouten) ?>
    <form class="form" method="post" action="groepen.php<?= $bewerk ? '?id=' . (int) $bewerk['id'] : '' ?>">
      <?= csrf_veld() ?>
      <div class="field">
        <label for="naam">Naam</label>
        <input id="naam" name="naam" required maxlength="60" value="<?= e($waarden['naam']) ?>"<?= aria_fout($fouten, 'naam') ?>>
        <?= veldfout($fouten, 'naam') ?>
      </div>
      <div class="field">
        <label for="omschrijving">Omschrijving <span class="field__hint">(optioneel, bijvoorbeeld de leeftijd)</span></label>
        <input id="omschrijving" name="omschrijving" maxlength="120" value="<?= e($waarden['omschrijving']) ?>">
      </div>
      <div class="field field--klein">
        <label for="max_kinderen">Maximale groepsgrootte</label>
        <input id="max_kinderen" name="max_kinderen" type="number" min="1" max="200" required value="<?= e((string) $waarden['max_kinderen']) ?>"<?= aria_fout($fouten, 'max_kinderen') ?>>
        <?= veldfout($fouten, 'max_kinderen') ?>
      </div>
      <fieldset>
        <legend class="legend-label">Open op</legend>
        <div class="choices">
<?php foreach ([1, 2, 3, 4, 5] as $dag): ?>
          <label class="choice"><input type="checkbox" name="dagen[]" value="<?= $dag ?>"<?= in_array($dag, $gekozenDagen, true) ? ' checked' : '' ?>><?= e(ucfirst(WEEKDAGEN[$dag])) ?></label>
<?php endforeach; ?>
        </div>
        <?= veldfout($fouten, 'dagen') ?>
      </fieldset>
      <div class="form__row">
        <div class="field">
          <label for="begintijd">Van</label>
          <input id="begintijd" name="begintijd" type="time" required value="<?= e($waarden['begintijd']) ?>"<?= aria_fout($fouten, 'tijden') ?>>
        </div>
        <div class="field">
          <label for="eindtijd">Tot</label>
          <input id="eindtijd" name="eindtijd" type="time" required value="<?= e($waarden['eindtijd']) ?>"<?= aria_fout($fouten, 'tijden') ?>>
        </div>
      </div>
      <?= veldfout($fouten, 'tijden') ?>
      <div class="field field--klein">
        <label for="kleur">Kleur in de agenda</label>
        <select id="kleur" name="kleur">
<?php foreach (GROEP_KLEUREN as $sleutel => $label): ?>
          <option value="<?= e($sleutel) ?>"<?= $waarden['kleur'] === $sleutel ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <div class="consent"><input id="actief" name="actief" type="checkbox" value="1"<?= $waarden['actief'] ? ' checked' : '' ?>><label for="actief">Groep is actief (zichtbaar in de agenda)</label></div>
      </div>
      <div class="form__acties">
        <button class="btn" type="submit"><?= $bewerk ? 'Opslaan' : 'Groep aanmaken' ?></button>
<?php if ($bewerk): ?><a href="groepen.php">Annuleren</a><?php endif; ?>
      </div>
    </form>
  </section>
</div>
<?php
pagina_einde();
