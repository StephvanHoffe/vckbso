<?php
/*
 * Mijn privacy (ouders en verzorgers): je rechten onder de AVG.
 * - Je eigen gegevens direct downloaden.
 * - Een verzoek indienen (inzage, correctie, verwijdering, beperking, bezwaar,
 *   overdracht) voor jezelf, of namens een kind als je gecontroleerd gezag hebt.
 * - De bewaartermijnen per soort gegevens.
 */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_login(['ouder']);
$namensKinderen = array_values(array_filter(kinderen_van_verzorger((int) $gebruiker['id'], null, false), fn ($k) => mag_namens_kind((int) $k['id'], (int) $gebruiker['id'])));
$fouten = [];

if (is_post()) {
    csrf_controleer();
    $actie = invoer('actie');

    if ($actie === 'download') {
        $data = export_gebruiker((int) $gebruiker['id']);
        foreach ($namensKinderen as $kind) {
            $data['gegevens_van_' . strtolower($kind['voornaam']) . '_' . $kind['id']] = export_kind((int) $kind['id'], false, (int) $gebruiker['id']);
        }
        log_export('Eigen gegevens gedownload', 'gebruiker:' . $gebruiker['id']);
        foreach ($namensKinderen as $kind) {
            log_export('Gegevens gedownload door verzorger', 'kind:' . $kind['id']);
        }
        stuur_download(export_json($data), 'mijn-gegevens-bso-vck-' . date('Y-m-d') . '.json');
    }

    if ($actie === 'verzoek') {
        $soort = invoer('soort');
        $over = invoer('over');
        $toelichting = mb_substr(invoer('toelichting'), 0, 3000);
        $kindId = null;
        if (!array_key_exists($soort, VERZOEK_SOORTEN)) {
            $fouten['soort'] = 'Kies wat je wilt.';
        }
        if ($over !== 'mij') {
            $kindId = (int) $over;
            // Namens een kind alleen met gecontroleerd gezag
            if (!mag_namens_kind($kindId, (int) $gebruiker['id'])) {
                $fouten['over'] = 'Je kunt alleen een verzoek doen namens een kind als we je gezag hebben gecontroleerd.';
            }
        }
        if (in_array($soort, ['correctie', 'beperking', 'bezwaar', 'verwijdering'], true) && $toelichting === '') {
            $fouten['toelichting'] = 'Vertel ons om welke gegevens het gaat en wat je wilt.';
        }
        if (!$fouten) {
            $overNaam = $kindId ? kindnaam(rij('SELECT * FROM kinderen WHERE id = ?', [$kindId])) : $gebruiker['naam'];
            q('INSERT INTO avg_verzoeken (gebruiker_id, kind_id, over_naam, soort, toelichting, deadline, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, ?)', [
                $gebruiker['id'], $kindId, $overNaam, $soort, versleutel($toelichting), date('Y-m-d', strtotime('+1 month')), nu(),
            ]);
            $id = laatste_id();
            log_actie('Privacyverzoek ingediend', $soort . ' (verzoek ' . $id . ')', null, 'avg', $kindId ? 'kind:' . $kindId : 'gebruiker:' . $gebruiker['id']);
            mail_team('Nieuw privacyverzoek', "Er is een nieuw privacyverzoek ({$soort}). Reageer binnen een maand:\n" . app_url('verzoeken.php?id=' . $id));
            flash('succes', 'Je verzoek is ontvangen. We reageren binnen een maand, meestal veel sneller.');
            redirect('privacy.php');
        }
    }
}

$verzoeken = rijen('SELECT * FROM avg_verzoeken WHERE gebruiker_id = ? ORDER BY id DESC', [$gebruiker['id']]);

pagina_begin('Mijn privacy', 'privacy.php');
pagina_kop('Mijn privacy', 'Jij bepaalt mee wat er met je gegevens gebeurt, en met die van je kind. Hier regel je dat.');
?>
<?= foutensamenvatting($fouten) ?>
<div class="kolommen">
  <div class="stapel">
    <section class="panel" aria-labelledby="download-titel">
      <h2 id="download-titel">Je gegevens downloaden</h2>
      <p>Download direct een bestand met je account, berichten en facturen<?= $namensKinderen ? ', en de gegevens van ' . e(implode(' en ', array_map(fn ($k) => $k['voornaam'], $namensKinderen))) : '' ?>. Het bestand is in een open formaat (JSON), zodat je het ook kunt meenemen naar een andere opvang.</p>
      <form method="post"><?= csrf_veld() ?><input type="hidden" name="actie" value="download"><button class="btn btn--secondary" type="submit"><?= icoon('download') ?>Download mijn gegevens</button></form>
      <p class="muted" style="margin-top: var(--space-s)">Wil je ook de interne aantekeningen van de begeleiders zien? Dien dan hieronder een inzageverzoek in.</p>
    </section>

    <section class="panel" aria-labelledby="verzoek-titel">
      <h2 id="verzoek-titel">Een verzoek indienen</h2>
      <p>Je eigen gegevens kun je meestal zelf aanpassen bij <a href="profiel.php">Mijn gegevens</a> en <a href="kinderen.php">Mijn kinderen</a>. Voor al het andere dien je hier een verzoek in. We reageren binnen een maand.</p>
      <form class="form" method="post">
        <?= csrf_veld() ?><input type="hidden" name="actie" value="verzoek">
        <div class="field">
          <label for="soort">Wat wil je?</label>
          <select id="soort" name="soort"<?= aria_fout($fouten, 'soort') ?>>
<?php foreach (VERZOEK_SOORTEN as $sleutel => $label): ?>
            <option value="<?= e($sleutel) ?>"<?= invoer('soort') === $sleutel ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
          </select>
          <?= veldfout($fouten, 'soort') ?>
        </div>
        <div class="field">
          <label for="over">Over wie?</label>
          <select id="over" name="over"<?= aria_fout($fouten, 'over') ?>>
            <option value="mij">Over mij</option>
<?php foreach ($namensKinderen as $kind): ?>
            <option value="<?= (int) $kind['id'] ?>"<?= invoer('over') === (string) $kind['id'] ? ' selected' : '' ?>>Namens <?= e($kind['voornaam']) ?></option>
<?php endforeach; ?>
          </select>
          <?= veldfout($fouten, 'over') ?>
          <p class="field__hint" style="margin: 0.4rem 0 0">Namens een kind kan alleen wie het gezag heeft, en nadat we dat hebben gecontroleerd.</p>
        </div>
        <div class="field">
          <label for="toelichting">Toelichting</label>
          <textarea id="toelichting" name="toelichting" rows="4"<?= aria_fout($fouten, 'toelichting') ?>><?= e(invoer('toelichting')) ?></textarea>
          <?= veldfout($fouten, 'toelichting') ?>
        </div>
        <div class="form__acties"><button class="btn" type="submit"><?= icoon('send') ?>Verzoek versturen</button></div>
      </form>
    </section>

<?php if ($verzoeken): ?>
    <section class="panel" aria-labelledby="mijn-titel">
      <h2 id="mijn-titel">Je verzoeken</h2>
      <ul class="kindlijst">
<?php foreach ($verzoeken as $v): ?>
        <li class="kindrij" style="grid-template-columns: 1fr">
          <div>
            <div class="kindrij__naam"><?= e(ucfirst(explode(':', VERZOEK_SOORTEN[$v['soort']])[0])) ?> · <?= e($v['over_naam']) ?> <?= verzoek_badge($v['status']) ?></div>
            <div class="kindrij__info"><span>Ingediend <?= e(datum_nl($v['aangemaakt_op'])) ?></span><span>Uiterlijk <?= e(datum_nl($v['deadline'])) ?></span></div>
<?php if ($v['reactie'] !== ''): ?><p style="margin: var(--space-2xs) 0 0; white-space: pre-line"><?= e(ontsleutel($v['reactie'])) ?></p><?php endif; ?>
<?php if ($v['export_bestand'] && $v['status'] === 'afgerond'): ?>
            <p style="margin: var(--space-2xs) 0 0"><a class="btn btn--secondary btn--mini" href="export.php?verzoek=<?= (int) $v['id'] ?>"><?= icoon('download') ?>Download de gegevens</a> <small class="muted">tot <?= e(datum_nl($v['export_verloopt_op'])) ?></small></p>
<?php endif; ?>
          </div>
        </li>
<?php endforeach; ?>
      </ul>
    </section>
<?php endif; ?>
  </div>

  <section class="panel" aria-labelledby="bewaar-titel">
    <h2 id="bewaar-titel">Hoe lang bewaren we wat?</h2>
    <dl class="gegevens" style="grid-template-columns: 1fr">
<?php foreach (BEWAARTERMIJNEN as $sleutel => [$omschrijving, $eenheid, $uitleg]): ?>
      <dt><?= e($omschrijving) ?></dt>
      <dd><?= bewaartermijn($sleutel) ?> <?= e($eenheid) ?><?= $uitleg ? ' ' . e($uitleg) : '' ?></dd>
<?php endforeach; ?>
    </dl>
    <p class="muted">Verwijderde gegevens verdwijnen ook uit onze back-ups, uiterlijk <?= (int) cfg('backup.bewaardagen', 30) ?> dagen later. Meer lees je in de <a href="../privacy.html">privacyverklaring</a>.</p>
  </section>
</div>
<?php
pagina_einde();
