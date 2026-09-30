<?php
/* Mijn gegevens: contactgegevens, wachtwoord en (voor ouders) de machtiging. */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_login();
$ouder = is_ouder($gebruiker);
$fouten = [];

if (is_post()) {
    csrf_controleer();
    $actie = invoer('actie');

    if ($actie === 'gegevens') {
        $velden = ['naam' => invoer('naam'), 'telefoon' => invoer('telefoon')];
        if ($ouder) {
            $velden += ['straat' => invoer('straat'), 'postcode' => invoer('postcode'), 'plaats' => invoer('plaats'), 'contactvoorkeur' => mb_substr(invoer('contactvoorkeur'), 0, 40)];
        }
        if ($velden['naam'] === '') {
            $fouten['naam'] = 'Vul je naam in.';
        }
        if ($velden['telefoon'] !== '' && !geldig_telefoon($velden['telefoon'])) {
            $fouten['telefoon'] = 'Vul een geldig telefoonnummer in.';
        }
        if ($ouder && !geldige_postcode($velden['postcode'])) {
            $fouten['postcode'] = 'Vul een geldige postcode in.';
        }
        if (!$fouten) {
            if ($ouder) {
                $velden['postcode'] = normaliseer_postcode($velden['postcode']);
            }
            $sets = implode(', ', array_map(fn ($v) => "$v = ?", array_keys($velden)));
            q("UPDATE gebruikers SET $sets WHERE id = ?", [...array_values($velden), $gebruiker['id']]);
            log_actie('Eigen gegevens gewijzigd');
            flash('succes', 'Je gegevens zijn opgeslagen.');
            redirect('profiel.php');
        }
    }

    if ($actie === 'wachtwoord') {
        if (!password_verify((string) ($_POST['huidig'] ?? ''), (string) $gebruiker['wachtwoord_hash'])) {
            $fouten['huidig'] = 'Je huidige wachtwoord klopt niet.';
        } elseif ($f = controleer_wachtwoord((string) ($_POST['wachtwoord'] ?? ''), (string) ($_POST['wachtwoord2'] ?? ''))) {
            $fouten['wachtwoord'] = $f;
        }
        if (!$fouten) {
            q('UPDATE gebruikers SET wachtwoord_hash = ? WHERE id = ?', [password_hash((string) $_POST['wachtwoord'], PASSWORD_DEFAULT), $gebruiker['id']]);
            q("UPDATE tokens SET gebruikt = 1 WHERE gebruiker_id = ?", [$gebruiker['id']]);
            session_regenerate_id(true);
            log_actie('Wachtwoord gewijzigd');
            flash('succes', 'Je wachtwoord is gewijzigd.');
            redirect('profiel.php');
        }
    }

    if ($actie === 'opzeggen' && $ouder) {
        q("INSERT INTO berichten (ouder_id, afzender_id, soort, tekst, gelezen_ouder, gelezen_team, aangemaakt_op) VALUES (?, ?, 'bericht', ?, 1, 0, ?)", [
            $gebruiker['id'], $gebruiker['id'], 'Ik wil de opvang opzeggen. Willen jullie contact met me opnemen om de einddatum en de laatste factuur af te spreken?' . (invoer('toelichting') !== '' ? "\n\n" . mb_substr(invoer('toelichting'), 0, 1000) : ''), nu(),
        ]);
        mail_team('Opzegging van ' . $gebruiker['naam'], $gebruiker['naam'] . ' wil de opvang opzeggen. Zie de berichten:' . "\n" . app_url('berichten.php?ouder=' . $gebruiker['id']));
        flash('succes', 'We hebben je opzegging ontvangen en nemen contact met je op om alles af te ronden.');
        redirect('berichten.php');
    }
}

pagina_begin('Mijn gegevens', 'profiel.php');
pagina_kop('Mijn gegevens');
?>
<?= foutensamenvatting($fouten) ?>
<div class="kolommen kolommen--gelijk">
  <section class="panel" aria-labelledby="gegevens-titel">
    <h2 id="gegevens-titel">Contactgegevens</h2>
    <form class="form" method="post">
      <?= csrf_veld() ?><input type="hidden" name="actie" value="gegevens">
      <div class="field"><label for="naam">Naam</label><input id="naam" name="naam" required autocomplete="name" value="<?= e(invoer('naam', $gebruiker['naam'])) ?>"<?= aria_fout($fouten, 'naam') ?>><?= veldfout($fouten, 'naam') ?></div>
      <div class="field"><label for="email">E-mailadres</label><input id="email" type="email" value="<?= e($gebruiker['email']) ?>" readonly aria-describedby="email-hint"><p class="field__hint" id="email-hint" style="margin: 0.4rem 0 0">Wil je een ander e-mailadres gebruiken? Stuur ons een bericht.</p></div>
      <div class="field"><label for="telefoon">Telefoonnummer</label><input id="telefoon" name="telefoon" type="tel" autocomplete="tel" value="<?= e(invoer('telefoon', $gebruiker['telefoon'])) ?>"<?= aria_fout($fouten, 'telefoon') ?>><?= veldfout($fouten, 'telefoon') ?></div>
<?php if ($ouder): ?>
      <div class="field"><label for="straat">Straat en huisnummer</label><input id="straat" name="straat" autocomplete="street-address" value="<?= e(invoer('straat', $gebruiker['straat'])) ?>"></div>
      <div class="form__row">
        <div class="field"><label for="postcode">Postcode</label><input id="postcode" name="postcode" autocomplete="postal-code" value="<?= e(invoer('postcode', $gebruiker['postcode'])) ?>"<?= aria_fout($fouten, 'postcode') ?>><?= veldfout($fouten, 'postcode') ?></div>
        <div class="field"><label for="plaats">Plaats</label><input id="plaats" name="plaats" autocomplete="address-level2" value="<?= e(invoer('plaats', $gebruiker['plaats'])) ?>"></div>
      </div>
      <div class="field"><label for="contactvoorkeur">Hoe bereiken we je het liefst?</label><select id="contactvoorkeur" name="contactvoorkeur">
<?php foreach (['Bellen', 'Mailen', 'Berichtje in de app', 'Maakt niet uit'] as $optie): ?>
        <option<?= $gebruiker['contactvoorkeur'] === $optie ? ' selected' : '' ?>><?= e($optie) ?></option>
<?php endforeach; ?>
      </select></div>
<?php endif; ?>
      <div class="form__acties"><button class="btn" type="submit">Opslaan</button></div>
    </form>
  </section>
  <div class="stapel">
    <section class="panel" aria-labelledby="wachtwoord-titel">
      <h2 id="wachtwoord-titel">Wachtwoord wijzigen</h2>
      <form class="form" method="post">
        <?= csrf_veld() ?><input type="hidden" name="actie" value="wachtwoord">
        <input type="text" name="username" value="<?= e($gebruiker['email']) ?>" autocomplete="username" hidden>
        <div class="field"><label for="huidig">Huidig wachtwoord</label><input id="huidig" name="huidig" type="password" required autocomplete="current-password"<?= aria_fout($fouten, 'huidig') ?>><?= veldfout($fouten, 'huidig') ?></div>
        <div class="field"><label for="wachtwoord">Nieuw wachtwoord <span class="field__hint">(minstens <?= MIN_WACHTWOORD_LENGTE ?> tekens)</span></label><input id="wachtwoord" name="wachtwoord" type="password" required autocomplete="new-password" minlength="<?= MIN_WACHTWOORD_LENGTE ?>"<?= aria_fout($fouten, 'wachtwoord') ?>><?= veldfout($fouten, 'wachtwoord') ?></div>
        <div class="field"><label for="wachtwoord2">Nieuw wachtwoord nog een keer</label><input id="wachtwoord2" name="wachtwoord2" type="password" required autocomplete="new-password"></div>
        <div class="form__acties"><button class="btn btn--secondary" type="submit">Wachtwoord wijzigen</button></div>
      </form>
    </section>
<?php if ($ouder): ?>
    <section class="panel" aria-labelledby="incasso-titel">
      <h2 id="incasso-titel">Automatische incasso</h2>
      <p><?= status_badge($gebruiker['mandaat_status']) ?><?= $gebruiker['mandaat_rekening'] ? ' ' . e($gebruiker['mandaat_rekening']) : '' ?></p>
      <a class="btn btn--secondary btn--small" href="machtiging.php"><?= icoon('bank') ?><?= in_array($gebruiker['mandaat_status'], ['geldig', 'demo'], true) ? 'Bekijk machtiging' : 'Machtiging afgeven' ?></a>
    </section>
    <section class="panel" aria-labelledby="opzeggen-titel">
      <h2 id="opzeggen-titel">Opvang opzeggen</h2>
      <p>Jammer! Laat het ons weten, dan spreken we samen de einddatum en de laatste factuur af.</p>
      <details class="uitklap">
        <summary>Ik wil opzeggen</summary>
        <form class="form" method="post" data-bevestig="Weet je zeker dat je de opvang wilt opzeggen?">
          <?= csrf_veld() ?><input type="hidden" name="actie" value="opzeggen">
          <div class="field"><label for="toelichting">Toelichting <span class="field__hint">(optioneel)</span></label><textarea id="toelichting" name="toelichting" rows="3" style="min-height: 6rem"></textarea></div>
          <div class="form__acties"><button class="btn btn--gevaar btn--small" type="submit">Opzegging versturen</button></div>
        </form>
      </details>
    </section>
<?php endif; ?>
  </div>
</div>
<?php
pagina_einde();
