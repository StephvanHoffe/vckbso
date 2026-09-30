<?php
require __DIR__ . '/inc/bootstrap.php';

if (huidige_gebruiker()) {
    redirect('index.php');
}
if ((int) waarde("SELECT COUNT(*) FROM gebruikers WHERE rol = 'beheerder'") === 0) {
    redirect('installeren.php');
}

$terug = veilig_terug(get_str('terug', 'index.php'));
$email = '';
$fout = null;

if (is_post()) {
    csrf_controleer();
    $email = invoer('email');
    $fout = probeer_in_te_loggen($email, (string) ($_POST['wachtwoord'] ?? ''));
    if ($fout === null) {
        redirect(mfa_wacht() ? 'inloggen-code.php?terug=' . rawurlencode($terug) : $terug);
    }
}

pagina_begin('Inloggen', '', ['publiek' => true]);
?>
<div class="auth">
  <div class="panel">
    <h1>Inloggen</h1>
    <p>Voor ouders en voor het team van BSO VCK.</p>
<?php if ($fout): ?>
    <div class="melding melding--fout" role="alert" tabindex="-1" data-focus><?= icoon('alert') ?><p><?= e($fout) ?></p></div>
<?php endif; ?>
    <form class="form" method="post" action="inloggen.php?terug=<?= e(rawurlencode($terug)) ?>">
      <?= csrf_veld() ?>
      <div class="field">
        <label for="email">E-mailadres</label>
        <input id="email" name="email" type="email" autocomplete="username" required value="<?= e($email) ?>"<?= $fout ? ' aria-invalid="true"' : '' ?>>
      </div>
      <div class="field">
        <label for="wachtwoord">Wachtwoord</label>
        <input id="wachtwoord" name="wachtwoord" type="password" autocomplete="current-password" required<?= $fout ? ' aria-invalid="true"' : '' ?>>
      </div>
      <div class="form__acties">
        <button class="btn" type="submit">Inloggen<?= icoon('arrow', 'icon--arrow') ?></button>
        <a href="wachtwoord-vergeten.php">Wachtwoord vergeten?</a>
      </div>
    </form>
    <hr class="scheiding">
    <p><strong>Nog geen account?</strong> Meld je kind aan, dan maak je meteen een account aan.</p>
    <a class="btn btn--secondary" href="aanmelden.php"><?= icoon('plus') ?>Kind aanmelden</a>
  </div>
</div>
<?php
pagina_einde();
