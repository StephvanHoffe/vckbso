<?php
/*
 * Eenmalig: de eerste beheerder aanmaken. Werkt alleen zolang er nog geen
 * beheerder is, en alleen met de installatiecode uit config.php.
 */
require __DIR__ . '/inc/bootstrap.php';

if ((int) waarde("SELECT COUNT(*) FROM gebruikers WHERE rol = 'beheerder'") > 0) {
    redirect('inloggen.php');
}

$code = (string) cfg('installatiecode', '');
$fouten = [];
$naam = $email = '';

if (is_post() && $code !== '') {
    csrf_controleer();
    $naam = invoer('naam');
    $email = invoer('email');
    if (!hash_equals($code, (string) ($_POST['code'] ?? ''))) {
        $fouten['code'] = 'De installatiecode klopt niet.';
    }
    if ($naam === '') {
        $fouten['naam'] = 'Vul je naam in.';
    }
    if (!geldig_email($email)) {
        $fouten['email'] = 'Vul een geldig e-mailadres in.';
    }
    if ($f = controleer_wachtwoord((string) ($_POST['wachtwoord'] ?? ''), (string) ($_POST['wachtwoord2'] ?? ''))) {
        $fouten['wachtwoord'] = $f;
    }
    if (!$fouten) {
        q("INSERT INTO gebruikers (rol, status, naam, email, wachtwoord_hash, aangemaakt_op) VALUES ('beheerder', 'actief', ?, ?, ?, ?)", [
            $naam, $email, password_hash((string) $_POST['wachtwoord'], PASSWORD_DEFAULT), nu(),
        ]);
        $id = laatste_id();
        log_actie('Beheeromgeving geïnstalleerd', $email, $id);
        log_in_als($id);
        flash('succes', 'Welkom! De beheeromgeving is klaar. Begin met de instellingen en maak daarna de groepen aan.');
        redirect('instellingen.php');
    }
}

pagina_begin('Installeren', '', ['publiek' => true]);
?>
<div class="auth">
  <div class="panel">
    <h1>Beheeromgeving installeren</h1>
<?php if ($code === ''): ?>
    <p>Zet eerst een <strong>installatiecode</strong> in <code>beheer/config.php</code> (kopieer <code>config.voorbeeld.php</code>). Vernieuw daarna deze pagina.</p>
    <p class="muted">Zo kan alleen iemand met toegang tot de server de eerste beheerder aanmaken.</p>
<?php else: ?>
    <p>Maak het eerste beheerdersaccount aan. Daarmee kun je daarna medewerkers uitnodigen.</p>
    <?= foutensamenvatting($fouten) ?>
    <form class="form" method="post">
      <?= csrf_veld() ?>
      <div class="field">
        <label for="code">Installatiecode (uit config.php)</label>
        <input id="code" name="code" type="password" required autocomplete="off"<?= aria_fout($fouten, 'code') ?>>
        <?= veldfout($fouten, 'code') ?>
      </div>
      <div class="field">
        <label for="naam">Je naam</label>
        <input id="naam" name="naam" required autocomplete="name" value="<?= e($naam) ?>"<?= aria_fout($fouten, 'naam') ?>>
        <?= veldfout($fouten, 'naam') ?>
      </div>
      <div class="field">
        <label for="email">E-mailadres</label>
        <input id="email" name="email" type="email" required autocomplete="email" value="<?= e($email) ?>"<?= aria_fout($fouten, 'email') ?>>
        <?= veldfout($fouten, 'email') ?>
      </div>
      <div class="field">
        <label for="wachtwoord">Wachtwoord <span class="field__hint">(minstens <?= MIN_WACHTWOORD_LENGTE ?> tekens)</span></label>
        <input id="wachtwoord" name="wachtwoord" type="password" required autocomplete="new-password" minlength="<?= MIN_WACHTWOORD_LENGTE ?>"<?= aria_fout($fouten, 'wachtwoord') ?>>
        <?= veldfout($fouten, 'wachtwoord') ?>
      </div>
      <div class="field">
        <label for="wachtwoord2">Wachtwoord nog een keer</label>
        <input id="wachtwoord2" name="wachtwoord2" type="password" required autocomplete="new-password">
      </div>
      <button class="btn" type="submit">Beheerder aanmaken</button>
    </form>
<?php endif; ?>
  </div>
</div>
<?php
pagina_einde();
