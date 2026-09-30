<?php
/* Team (beheerder): medewerkers uitnodigen, rol wijzigen en toegang intrekken. */
require __DIR__ . '/inc/bootstrap.php';

$ik = vereis_beheerder();
$fouten = [];
$naam = $email = '';
$rol = 'medewerker';
$uitnodigingslink = null;

if (is_post()) {
    csrf_controleer();
    $actie = invoer('actie');

    if ($actie === 'uitnodigen') {
        $naam = invoer('naam');
        $email = invoer('email');
        $rol = invoer('rol') === 'beheerder' ? 'beheerder' : 'medewerker';
        if ($naam === '') {
            $fouten['naam'] = 'Vul de naam in.';
        }
        if (!geldig_email($email)) {
            $fouten['email'] = 'Vul een geldig e-mailadres in.';
        } elseif (waarde('SELECT id FROM gebruikers WHERE email = ?', [$email])) {
            $fouten['email'] = 'Er is al een account met dit e-mailadres.';
        }
        if (!$fouten) {
            q("INSERT INTO gebruikers (rol, status, naam, email, aangemaakt_op) VALUES (?, 'actief', ?, ?, ?)", [$rol, $naam, $email, nu()]);
            $id = laatste_id();
            $uitnodigingslink = maak_wachtwoordlink($id, 72);
            stuur_mail($email, 'Je account voor de beheeromgeving van BSO VCK', "Hoi {$naam},\n\nJe bent uitgenodigd voor de beheeromgeving van BSO VCK. Via deze link kies je je wachtwoord (de link werkt 3 dagen):\n\n{$uitnodigingslink}\n\nTot snel!");
            log_actie('Medewerker uitgenodigd', "{$naam} ({$rol})");
            flash('succes', "{$naam} is uitgenodigd en krijgt een e-mail met een link om een wachtwoord te kiezen.");
            $_SESSION['laatste_uitnodiging'] = $uitnodigingslink;
            redirect('medewerkers.php');
        }
    } else {
        $persoon = rij("SELECT * FROM gebruikers WHERE id = ? AND rol IN ('beheerder', 'medewerker')", [(int) invoer('gebruiker')]);
        if (!$persoon) {
            niet_gevonden();
        }
        if ((int) $persoon['id'] === (int) $ik['id'] && in_array($actie, ['stoppen', 'rol'], true)) {
            flash('fout', 'Je kunt je eigen account niet stoppen of je eigen rol wijzigen. Vraag dat aan een andere beheerder.');
            redirect('medewerkers.php');
        }
        if ($actie === 'stoppen') {
            q("UPDATE gebruikers SET status = 'gestopt' WHERE id = ?", [$persoon['id']]);
            log_actie('Medewerker gestopt', $persoon['naam']);
            flash('succes', $persoon['naam'] . ' kan niet meer inloggen.');
        } elseif ($actie === 'activeren') {
            q("UPDATE gebruikers SET status = 'actief' WHERE id = ?", [$persoon['id']]);
            log_actie('Medewerker geactiveerd', $persoon['naam']);
            flash('succes', $persoon['naam'] . ' kan weer inloggen.');
        } elseif ($actie === 'rol') {
            $nieuweRol = $persoon['rol'] === 'beheerder' ? 'medewerker' : 'beheerder';
            q('UPDATE gebruikers SET rol = ? WHERE id = ?', [$nieuweRol, $persoon['id']]);
            log_actie('Rol gewijzigd', $persoon['naam'] . ' → ' . $nieuweRol);
            flash('succes', $persoon['naam'] . ' is nu ' . $nieuweRol . '.');
        } elseif ($actie === 'link') {
            $_SESSION['laatste_uitnodiging'] = maak_wachtwoordlink((int) $persoon['id'], 72);
            stuur_mail($persoon['email'], 'Nieuwe link voor de beheeromgeving van BSO VCK', "Hoi {$persoon['naam']},\n\nVia deze link kies je (opnieuw) je wachtwoord. De link werkt 3 dagen:\n\n{$_SESSION['laatste_uitnodiging']}");
            log_actie('Nieuwe wachtwoordlink', $persoon['naam']);
            flash('succes', 'Er is een nieuwe link gemaakt en gemaild naar ' . $persoon['naam'] . '.');
        }
        redirect('medewerkers.php');
    }
}

$uitnodigingslink = $_SESSION['laatste_uitnodiging'] ?? null;
unset($_SESSION['laatste_uitnodiging']);
$team = rijen("SELECT * FROM gebruikers WHERE rol IN ('beheerder', 'medewerker') ORDER BY status = 'gestopt', rol, naam");

pagina_begin('Team', 'medewerkers.php');
pagina_kop('Team', 'Begeleiders en beheerders met toegang tot de beheeromgeving. Medewerkers zien de agenda, kinderen, ouders, berichten en foto\'s. Beheerders ook groepen, facturen en instellingen.');
?>
<?php if ($uitnodigingslink): ?>
<div class="melding" role="status"><?= icoon('info') ?><div><p>Komt de e-mail niet aan? Stuur deze link dan zelf door (werkt 3 dagen, één keer te gebruiken):</p><p><code style="word-break: break-all"><?= e($uitnodigingslink) ?></code></p></div></div>
<?php endif; ?>
<div class="kolommen">
  <section class="panel" aria-labelledby="team-titel">
    <h2 id="team-titel">Teamleden</h2>
    <div class="tabel">
      <table>
        <thead><tr><th scope="col">Naam</th><th scope="col">Rol</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Acties</span></th></tr></thead>
        <tbody>
<?php foreach ($team as $persoon): ?>
          <tr>
            <th scope="row"><?= e($persoon['naam']) ?><br><small class="muted"><?= e($persoon['email']) ?></small></th>
            <td><?= e(ucfirst($persoon['rol'])) ?></td>
            <td><?= status_badge($persoon['status']) ?><br><small class="muted"><?= $persoon['wachtwoord_hash'] ? ($persoon['laatst_ingelogd'] ? 'Ingelogd ' . e(moment_nl($persoon['laatst_ingelogd'])) : 'Nog niet ingelogd') : 'Uitnodiging nog niet gebruikt' ?></small></td>
            <td>
<?php if ((int) $persoon['id'] !== (int) $ik['id']): ?>
              <div class="btn-group">
                <form class="inline-form" method="post"><?= csrf_veld() ?><input type="hidden" name="gebruiker" value="<?= (int) $persoon['id'] ?>"><input type="hidden" name="actie" value="rol"><button class="btn btn--secondary btn--mini" type="submit">Maak <?= $persoon['rol'] === 'beheerder' ? 'medewerker' : 'beheerder' ?><span class="visually-hidden"> (<?= e($persoon['naam']) ?>)</span></button></form>
                <form class="inline-form" method="post"><?= csrf_veld() ?><input type="hidden" name="gebruiker" value="<?= (int) $persoon['id'] ?>"><input type="hidden" name="actie" value="link"><button class="btn btn--secondary btn--mini" type="submit">Nieuwe link<span class="visually-hidden"> voor <?= e($persoon['naam']) ?></span></button></form>
<?php if ($persoon['status'] === 'gestopt'): ?>
                <form class="inline-form" method="post"><?= csrf_veld() ?><input type="hidden" name="gebruiker" value="<?= (int) $persoon['id'] ?>"><input type="hidden" name="actie" value="activeren"><button class="btn btn--secondary btn--mini" type="submit">Weer toegang geven<span class="visually-hidden"> aan <?= e($persoon['naam']) ?></span></button></form>
<?php else: ?>
                <form class="inline-form" method="post" data-bevestig="Toegang van <?= e($persoon['naam']) ?> intrekken?"><?= csrf_veld() ?><input type="hidden" name="gebruiker" value="<?= (int) $persoon['id'] ?>"><input type="hidden" name="actie" value="stoppen"><button class="btn btn--gevaar btn--mini" type="submit">Toegang intrekken<span class="visually-hidden"> van <?= e($persoon['naam']) ?></span></button></form>
<?php endif; ?>
              </div>
<?php else: ?>
              <span class="muted">Dit ben jij</span>
<?php endif; ?>
            </td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="panel" aria-labelledby="nieuw-titel">
    <h2 id="nieuw-titel">Iemand uitnodigen</h2>
    <?= foutensamenvatting($fouten) ?>
    <form class="form" method="post">
      <?= csrf_veld() ?>
      <input type="hidden" name="actie" value="uitnodigen">
      <div class="field"><label for="naam">Naam</label><input id="naam" name="naam" required value="<?= e($naam) ?>"<?= aria_fout($fouten, 'naam') ?>><?= veldfout($fouten, 'naam') ?></div>
      <div class="field"><label for="email">E-mailadres</label><input id="email" name="email" type="email" required value="<?= e($email) ?>"<?= aria_fout($fouten, 'email') ?>><?= veldfout($fouten, 'email') ?></div>
      <div class="field field--klein"><label for="rol">Rol</label><select id="rol" name="rol"><option value="medewerker"<?= $rol === 'medewerker' ? ' selected' : '' ?>>Medewerker (begeleider)</option><option value="beheerder"<?= $rol === 'beheerder' ? ' selected' : '' ?>>Beheerder</option></select></div>
      <div class="form__acties"><button class="btn" type="submit"><?= icoon('send') ?>Uitnodiging sturen</button></div>
    </form>
  </section>
</div>
<?php
pagina_einde();
