<?php
require __DIR__ . '/inc/bootstrap.php';

$verstuurd = false;
$email = '';
$fout = null;

if (is_post()) {
    csrf_controleer();
    $email = invoer('email');
    if (!geldig_email($email)) {
        $fout = 'Vul een geldig e-mailadres in.';
    } elseif (te_veel_verzoeken('w:' . hash('sha256', mb_strtolower($email)), 3) || te_veel_verzoeken('wi:' . ip_adres(), 10)) {
        $fout = 'Te veel aanvragen. Probeer het over een kwartier opnieuw.';
    } else {
        $gebruiker = rij("SELECT * FROM gebruikers WHERE email = ? AND status != 'gestopt'", [$email]);
        if ($gebruiker) {
            $link = maak_wachtwoordlink((int) $gebruiker['id'], 2);
            stuur_mail($gebruiker['email'], 'Nieuw wachtwoord instellen', "Hoi {$gebruiker['naam']},\n\nJe hebt gevraagd om een nieuw wachtwoord voor Sporty. Via deze link stel je het in (de link werkt 2 uur):\n\n{$link}\n\nHeb je dit niet zelf gevraagd? Dan kun je deze e-mail negeren.");
            log_actie('Wachtwoordlink aangevraagd', '', (int) $gebruiker['id']);
        }
        $verstuurd = true; // altijd dezelfde melding, zodat niemand kan zien welke adressen bestaan
    }
}

pagina_begin('Wachtwoord vergeten', '', ['publiek' => true]);
?>
<div class="auth">
  <div class="panel">
    <h1>Wachtwoord vergeten?</h1>
<?php if ($verstuurd): ?>
    <div class="melding melding--succes" role="status"><?= icoon('check') ?><p>Als er een account is met het adres <?= e($email) ?>, krijg je binnen een paar minuten een e-mail met een link. Kijk ook even in je spam.</p></div>
    <a class="btn btn--secondary" href="inloggen.php"><?= icoon('back') ?>Terug naar inloggen</a>
<?php else: ?>
    <p>Vul je e-mailadres in. Je krijgt dan een link om een nieuw wachtwoord in te stellen.</p>
<?php if ($fout): ?>
    <div class="melding melding--fout" role="alert"><?= icoon('alert') ?><p><?= e($fout) ?></p></div>
<?php endif; ?>
    <form class="form" method="post">
      <?= csrf_veld() ?>
      <div class="field">
        <label for="email">E-mailadres</label>
        <input id="email" name="email" type="email" autocomplete="email" required value="<?= e($email) ?>">
      </div>
      <div class="form__acties">
        <button class="btn" type="submit">Stuur de link</button>
        <a href="inloggen.php">Terug naar inloggen</a>
      </div>
    </form>
<?php endif; ?>
  </div>
</div>
<?php
pagina_einde();
