<?php
/*
 * Tweestapsverificatie instellen of beheren. Verplicht voor het team (en voor
 * ouders als de beheerder dat instelt): zolang het niet is ingesteld, stuurt
 * elke pagina hierheen.
 */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_login();
$verplicht = mfa_verplicht($gebruiker);
$fout = null;

if (is_post()) {
    csrf_controleer();
    $actie = invoer('actie');

    if ($actie === 'aanzetten' && !$gebruiker['mfa_actief'] && !empty($_SESSION['mfa_nieuw'])) {
        $stap = totp_controleer($_SESSION['mfa_nieuw'], invoer('code'));
        if ($stap === null) {
            $fout = 'Deze code klopt niet. Controleer of de tijd op je telefoon goed staat en probeer de nieuwste code.';
        } else {
            $_SESSION['mfa_herstelcodes'] = mfa_zet_aan((int) $gebruiker['id'], $_SESSION['mfa_nieuw'], $stap);
            unset($_SESSION['mfa_nieuw']);
            session_regenerate_id(true);
            redirect('tweestaps.php');
        }
    } elseif (in_array($actie, ['herstelcodes', 'uitzetten'], true) && $gebruiker['mfa_actief']) {
        if (!mfa_controleer($gebruiker, invoer('code'))) {
            $fout = 'Deze code klopt niet.';
        } elseif ($actie === 'herstelcodes') {
            $_SESSION['mfa_herstelcodes'] = mfa_herstelcodes_maken((int) $gebruiker['id']);
            log_actie('Nieuwe herstelcodes gemaakt', '', null, 'beveiliging', 'gebruiker:' . $gebruiker['id']);
            redirect('tweestaps.php');
        } elseif (!$verplicht) {
            mfa_zet_uit((int) $gebruiker['id'], 'door gebruiker zelf');
            flash('succes', 'Tweestapsverificatie staat uit.');
            redirect('profiel.php');
        }
    }
    $gebruiker = huidige_gebruiker(true);
}

$herstelcodes = $_SESSION['mfa_herstelcodes'] ?? null;
unset($_SESSION['mfa_herstelcodes']);
if (!$gebruiker['mfa_actief'] && empty($_SESSION['mfa_nieuw'])) {
    $_SESSION['mfa_nieuw'] = mfa_nieuw_geheim();
}

pagina_begin('Tweestapsverificatie', 'profiel.php', ['scripts' => ['assets/vendor/qrcode.js']]);
pagina_kop("Twee\u{00AD}staps\u{00AD}verificatie", 'Naast je wachtwoord vul je bij het inloggen een code in uit een app op je telefoon. Zo kan niemand anders inloggen, ook niet als je wachtwoord uitlekt.');
?>
<?php if ($fout): ?>
<div class="melding melding--fout" role="alert" tabindex="-1" data-focus><?= icoon('alert') ?><p><?= e($fout) ?></p></div>
<?php endif; ?>
<?php if ($herstelcodes): ?>
<section class="panel" aria-labelledby="codes-titel" style="background: var(--color-sun-soft)">
  <h2 id="codes-titel">Bewaar je herstelcodes</h2>
  <p>Ben je je telefoon kwijt? Dan log je in met een van deze codes. Elke code werkt één keer. <strong>Je ziet ze maar één keer</strong>: schrijf ze op of bewaar ze in je wachtwoordmanager.</p>
  <ul class="herstelcodes"><?php foreach ($herstelcodes as $code): ?><li><code><?= e($code) ?></code></li><?php endforeach; ?></ul>
  <p><a class="btn" href="index.php">Ik heb ze bewaard, verder</a></p>
</section>
<?php elseif ($gebruiker['mfa_actief']): ?>
<div class="kolommen kolommen--gelijk">
  <section class="panel" aria-labelledby="status-titel">
    <h2 id="status-titel">Staat aan</h2>
    <p><span class="badge badge--geldig">Aan</span> Tweestapsverificatie is actief. Je hebt nog <?= mfa_herstelcodes_over((int) $gebruiker['id']) ?> herstelcodes.</p>
    <form class="form" method="post">
      <?= csrf_veld() ?>
      <div class="field field--klein"><label for="code-codes">Code uit je app</label><input id="code-codes" name="code" required autocomplete="one-time-code" inputmode="numeric"></div>
      <div class="form__acties">
        <button class="btn btn--secondary btn--small" type="submit" name="actie" value="herstelcodes">Nieuwe herstelcodes maken</button>
<?php if (!$verplicht): ?>
        <button class="btn btn--gevaar btn--small" type="submit" name="actie" value="uitzetten">Tweestapsverificatie uitzetten</button>
<?php endif; ?>
      </div>
    </form>
<?php if ($verplicht): ?>
    <p class="muted">Voor jouw account is tweestapsverificatie verplicht. Nieuwe telefoon? Vraag de beheerder om hem opnieuw in te stellen.</p>
<?php endif; ?>
  </section>
</div>
<?php else: ?>
<?php if ($verplicht): ?>
<p class="melding"><?= icoon('shield') ?><span>Voor jouw account is tweestapsverificatie verplicht, omdat je gegevens van kinderen kunt zien. Stel het nu in; het duurt een minuutje.</span></p>
<?php endif; ?>
<div class="kolommen kolommen--gelijk">
  <section class="panel" aria-labelledby="stap1-titel">
    <h2 id="stap1-titel">1. Scan de code</h2>
    <p>Installeer een authenticator-app, bijvoorbeeld Microsoft Authenticator, Google Authenticator of de wachtwoordmanager die je al gebruikt. Scan daarmee deze QR-code:</p>
    <div class="qr" data-qr="<?= e(mfa_uri($_SESSION['mfa_nieuw'], $gebruiker['email'])) ?>" role="img" aria-label="QR-code voor je authenticator-app"></div>
    <p>Lukt scannen niet? Vul dan deze sleutel in je app in:</p>
    <p><code class="sleutel"><?= e(trim(chunk_split($_SESSION['mfa_nieuw'], 4, ' '))) ?></code></p>
  </section>
  <section class="panel" aria-labelledby="stap2-titel">
    <h2 id="stap2-titel">2. Vul de code in</h2>
    <p>Je app laat nu een code van 6 cijfers zien. Vul die hier in om te bevestigen.</p>
    <form class="form" method="post">
      <?= csrf_veld() ?><input type="hidden" name="actie" value="aanzetten">
      <div class="field field--klein"><label for="code">Code</label><input id="code" name="code" required autocomplete="one-time-code" inputmode="numeric" maxlength="7"<?= $fout ? ' aria-invalid="true"' : '' ?>></div>
      <div class="form__acties"><button class="btn" type="submit">Aanzetten</button></div>
    </form>
  </section>
</div>
<?php endif; ?>
<?php
pagina_einde();
