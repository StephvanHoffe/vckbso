<?php
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
        log_actie('Wachtwoord ingesteld', '', (int) $gebruiker['id']);
        log_in_als((int) $gebruiker['id']);
        flash('succes', 'Je wachtwoord is ingesteld. Welkom!');
        redirect('index.php');
    }
}

pagina_begin('Wachtwoord instellen', '', ['publiek' => true]);
?>
<div class="auth">
  <div class="panel">
    <h1>Wachtwoord instellen</h1>
<?php if (!$gebruiker): ?>
    <p>Deze link is verlopen of al gebruikt. Vraag een nieuwe link aan.</p>
    <a class="btn" href="wachtwoord-vergeten.php">Nieuwe link aanvragen</a>
<?php else: ?>
    <p>Hoi <?= e($gebruiker['naam']) ?>! Kies een wachtwoord voor <strong><?= e($gebruiker['email']) ?></strong>.</p>
<?php if ($fout): ?>
    <div class="melding melding--fout" role="alert" tabindex="-1" data-focus><?= icoon('alert') ?><p><?= e($fout) ?></p></div>
<?php endif; ?>
    <form class="form" method="post" action="wachtwoord-instellen.php?token=<?= e($token) ?>">
      <?= csrf_veld() ?>
      <input type="text" name="username" value="<?= e($gebruiker['email']) ?>" autocomplete="username" hidden>
      <div class="field">
        <label for="wachtwoord">Nieuw wachtwoord <span class="field__hint">(minstens <?= MIN_WACHTWOORD_LENGTE ?> tekens)</span></label>
        <input id="wachtwoord" name="wachtwoord" type="password" required autocomplete="new-password" minlength="<?= MIN_WACHTWOORD_LENGTE ?>">
      </div>
      <div class="field">
        <label for="wachtwoord2">Nog een keer</label>
        <input id="wachtwoord2" name="wachtwoord2" type="password" required autocomplete="new-password">
      </div>
      <button class="btn" type="submit">Wachtwoord opslaan</button>
    </form>
<?php endif; ?>
  </div>
</div>
<?php
pagina_einde();
