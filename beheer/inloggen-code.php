<?php
/* Tweede stap van het inloggen: de code uit de authenticator-app (of een herstelcode). */
require __DIR__ . '/inc/bootstrap.php';

$gebruikerId = mfa_wacht();
if (!$gebruikerId) {
    redirect('inloggen.php');
}
$terug = veilig_terug(get_str('terug', 'index.php'));
$fout = null;

if (is_post()) {
    csrf_controleer();
    $gebruiker = rij("SELECT * FROM gebruikers WHERE id = ? AND status != 'gestopt'", [$gebruikerId]);
    if (!$gebruiker || recente_pogingen('m:' . $gebruikerId) >= MAX_INLOGPOGINGEN) {
        unset($_SESSION['mfa_wacht']);
        log_actie('Inlogcode geblokkeerd', 'te veel pogingen', $gebruikerId, 'beveiliging', 'gebruiker:' . $gebruikerId);
        flash('fout', 'Te veel foute codes. Wacht een kwartier en log opnieuw in.');
        redirect('inloggen.php');
    }
    if (mfa_controleer($gebruiker, invoer('code'))) {
        log_in_als($gebruikerId, 'wachtwoord + tweestapsverificatie');
        if (mfa_herstelcodes_over($gebruikerId) <= 2) {
            flash('info', 'Je hebt bijna geen herstelcodes meer. Maak nieuwe aan bij Mijn gegevens → Tweestapsverificatie.');
        }
        redirect($terug);
    }
    registreer_poging('m:' . $gebruikerId);
    log_actie('Inlogcode onjuist', '', $gebruikerId, 'beveiliging', 'gebruiker:' . $gebruikerId);
    $fout = 'Deze code klopt niet. Kijk in je authenticator-app voor de actuele code, of gebruik een herstelcode.';
}

pagina_begin('Code invullen', '', ['publiek' => true]);
?>
<div class="auth">
  <div class="panel">
    <h1>Nog één stap</h1>
    <p>Vul de code van 6 cijfers in uit je authenticator-app. Kwijt? Gebruik dan een van je herstelcodes.</p>
<?php if ($fout): ?>
    <div class="melding melding--fout" role="alert" tabindex="-1" data-focus><?= icoon('alert') ?><p><?= e($fout) ?></p></div>
<?php endif; ?>
    <form class="form" method="post" action="inloggen-code.php?terug=<?= e(rawurlencode($terug)) ?>">
      <?= csrf_veld() ?>
      <div class="field field--klein">
        <label for="code">Code</label>
        <input id="code" name="code" required autocomplete="one-time-code" inputmode="numeric" maxlength="9" autofocus<?= $fout ? ' aria-invalid="true"' : '' ?>>
      </div>
      <div class="form__acties">
        <button class="btn" type="submit">Inloggen<?= icoon('arrow', 'icon--arrow') ?></button>
        <a href="inloggen.php">Annuleren</a>
      </div>
    </form>
    <p class="muted" style="margin-top: var(--space-l)">Geen toegang meer tot je app én geen herstelcodes? Neem contact op met de beheerder van BSO VCK.</p>
  </div>
</div>
<?php
pagina_einde();
