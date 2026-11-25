<?php
/*
 * Een wachtwoord kiezen via een link uit een e-mail: na het aanmelden op de website, bij het activeren van een
 * account, een uitnodiging of "Wachtwoord vergeten". Met ?taal=en in het Engels (de link uit de Engelse
 * aanmeldmail); Mijn BSO zelf is Nederlands.
 */
require __DIR__ . '/inc/bootstrap.php';

$token = get_str('token');
$gebruiker = zoek_wachtwoordtoken($token);
$fout = null;

if ($gebruiker && is_post()) {
    csrf_controleer();
    $fout = controleer_wachtwoord((string) ($_POST['wachtwoord'] ?? ''), (string) ($_POST['wachtwoord2'] ?? ''));
    if ($fout === null) {
        q('UPDATE gebruikers SET wachtwoord_hash = ? WHERE id = ?', [password_hash((string) $_POST['wachtwoord'], PASSWORD_DEFAULT), $gebruiker['id']]);
        q('UPDATE tokens SET gebruikt = 1 WHERE id = ?', [$gebruiker['token_id']]);
        beeindig_andere_sessies((int) $gebruiker['id']);
        log_actie('Wachtwoord ingesteld', '', (int) $gebruiker['id'], 'beveiliging', 'gebruiker:' . $gebruiker['id']);
        na_wachtwoord((int) $gebruiker['id']);
        flash('succes', t('Je wachtwoord is ingesteld. Welkom!', 'Your password is set. Welcome!'));
        redirect(mfa_wacht() ? 'inloggen-code.php' : 'index.php');
    }
}

$taalParam = taal() === 'en' ? '&taal=en' : '';
pagina_begin(t('Wachtwoord instellen', 'Set your password'), '', ['publiek' => true]);
?>
<div class="auth">
  <div class="panel">
    <h1><?= e(t('Wachtwoord instellen', 'Set your password')) ?></h1>
<?php if (!$gebruiker): ?>
    <p><?= e(t('Deze link is verlopen of al gebruikt. Vraag een nieuwe link aan.', 'This link has expired or has already been used. Request a new link.')) ?></p>
    <a class="btn" href="wachtwoord-vergeten.php"><?= e(t('Nieuwe link aanvragen', 'Request a new link')) ?></a>
<?php else: ?>
    <p><?= e(t('Hoi ', 'Hi ')) ?><?= e(explode(' ', (string) $gebruiker['naam'])[0]) ?>! <?= t('Kies een wachtwoord voor', 'Choose a password for') ?> <strong><?= e($gebruiker['email']) ?></strong>.<?= $gebruiker['status'] === 'nieuw' ? ' ' . e(t('Daarmee log je in op Mijn BSO. Dagen kiezen kan zodra we kennis hebben gemaakt.', "You'll use it to log in to Mijn BSO (in Dutch). You can pick days once we've met.")) : '' ?></p>
<?php if ($fout): ?>
    <div class="melding melding--fout" role="alert" tabindex="-1" data-focus><?= icoon('alert') ?><p><?= e($fout) ?></p></div>
<?php endif; ?>
    <form class="form" method="post" action="wachtwoord-instellen.php?token=<?= e($token . $taalParam) ?>">
      <?= csrf_veld() ?>
      <input type="text" name="username" value="<?= e($gebruiker['email']) ?>" autocomplete="username" hidden>
      <div class="field">
        <label for="wachtwoord"><?= e(t('Nieuw wachtwoord', 'New password')) ?> <span class="field__hint">(<?= e(t('minstens ' . MIN_WACHTWOORD_LENGTE . ' tekens', 'at least ' . MIN_WACHTWOORD_LENGTE . ' characters')) ?>)</span></label>
        <input id="wachtwoord" name="wachtwoord" type="password" required autocomplete="new-password" minlength="<?= MIN_WACHTWOORD_LENGTE ?>">
      </div>
      <div class="field">
        <label for="wachtwoord2"><?= e(t('Nog een keer', 'Once more')) ?></label>
        <input id="wachtwoord2" name="wachtwoord2" type="password" required autocomplete="new-password">
      </div>
      <button class="btn" type="submit"><?= e(t('Wachtwoord opslaan', 'Save password')) ?></button>
    </form>
<?php endif; ?>
  </div>
</div>
<?php
pagina_einde();
