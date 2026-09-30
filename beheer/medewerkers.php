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
            // Medewerkers direct aan de gekozen groepen koppelen
            foreach (array_map('intval', (array) ($_POST['groepen'] ?? [])) as $groepId) {
                if (groep($groepId)) {
                    q('INSERT OR IGNORE INTO medewerker_groepen (gebruiker_id, groep_id) VALUES (?, ?)', [$id, $groepId]);
                }
            }
            log_actie('Medewerker uitgenodigd', $rol, null, 'beveiliging', 'gebruiker:' . $id);
            flash('succes', "{$naam} is uitgenodigd en krijgt een e-mail met een link om een wachtwoord te kiezen.");
            $_SESSION['laatste_uitnodiging'] = $uitnodigingslink;
            redirect('medewerkers.php');
        }
    } else {
        $persoon = rij("SELECT * FROM gebruikers WHERE id = ? AND rol IN ('beheerder', 'medewerker')", [(int) invoer('gebruiker')]);
        if (!$persoon) {
            niet_gevonden();
        }
        if ((int) $persoon['id'] === (int) $ik['id'] && in_array($actie, ['stoppen', 'rol', 'mfa_reset'], true)) {
            flash('fout', 'Je kunt je eigen account niet stoppen of je eigen rol wijzigen. Vraag dat aan een andere beheerder.');
            redirect('medewerkers.php');
        }
        $wie = 'gebruiker:' . $persoon['id'];
        if ($actie === 'groepen') {
            $tot = invoer('tot');
            $tot = geldige_datum($tot) ? $tot : null;
            transactie(function () use ($persoon, $tot) {
                q('DELETE FROM medewerker_groepen WHERE gebruiker_id = ?', [$persoon['id']]);
                foreach (array_map('intval', (array) ($_POST['groepen'] ?? [])) as $groepId) {
                    if (groep($groepId)) {
                        q('INSERT INTO medewerker_groepen (gebruiker_id, groep_id, tot) VALUES (?, ?, ?)', [$persoon['id'], $groepId, $tot]);
                    }
                }
            });
            log_actie('Groepen van medewerker gewijzigd', implode(',', array_map('intval', (array) ($_POST['groepen'] ?? []))) . ($tot ? " tot {$tot}" : ''), null, 'beveiliging', $wie);
            flash('succes', 'De groepen van ' . $persoon['naam'] . ' zijn opgeslagen.');
        } elseif ($actie === 'mfa_reset') {
            mfa_zet_uit((int) $persoon['id'], 'reset door beheerder');
            flash('succes', 'De tweestapsverificatie van ' . $persoon['naam'] . ' is gereset. Bij de volgende keer inloggen stelt ' . $persoon['naam'] . ' hem opnieuw in.');
        } elseif ($actie === 'stoppen') {
            q("UPDATE gebruikers SET status = 'gestopt' WHERE id = ?", [$persoon['id']]);
            log_actie('Medewerker gestopt', '', null, 'beveiliging', $wie);
            flash('succes', $persoon['naam'] . ' kan niet meer inloggen.');
        } elseif ($actie === 'activeren') {
            q("UPDATE gebruikers SET status = 'actief' WHERE id = ?", [$persoon['id']]);
            log_actie('Medewerker geactiveerd', '', null, 'beveiliging', $wie);
            flash('succes', $persoon['naam'] . ' kan weer inloggen.');
        } elseif ($actie === 'rol') {
            $nieuweRol = $persoon['rol'] === 'beheerder' ? 'medewerker' : 'beheerder';
            q('UPDATE gebruikers SET rol = ? WHERE id = ?', [$nieuweRol, $persoon['id']]);
            log_actie('Rol gewijzigd', $nieuweRol, null, 'beveiliging', $wie);
            flash('succes', $persoon['naam'] . ' is nu ' . $nieuweRol . '.');
        } elseif ($actie === 'link') {
            $_SESSION['laatste_uitnodiging'] = maak_wachtwoordlink((int) $persoon['id'], 72);
            stuur_mail($persoon['email'], 'Nieuwe link voor de beheeromgeving van BSO VCK', "Hoi {$persoon['naam']},\n\nVia deze link kies je (opnieuw) je wachtwoord. De link werkt 3 dagen:\n\n{$_SESSION['laatste_uitnodiging']}");
            log_actie('Nieuwe wachtwoordlink', '', null, 'beveiliging', $wie);
            flash('succes', 'Er is een nieuwe link gemaakt en gemaild naar ' . $persoon['naam'] . '.');
        }
        redirect('medewerkers.php');
    }
}

$uitnodigingslink = $_SESSION['laatste_uitnodiging'] ?? null;
unset($_SESSION['laatste_uitnodiging']);
$team = rijen("SELECT * FROM gebruikers WHERE rol IN ('beheerder', 'medewerker') ORDER BY status = 'gestopt', rol, naam");
$alleGroepen = rijen('SELECT * FROM groepen WHERE actief = 1 ORDER BY naam');
$koppelingen = [];
foreach (rijen('SELECT * FROM medewerker_groepen') as $rij) {
    $koppelingen[$rij['gebruiker_id']][$rij['groep_id']] = $rij['tot'];
}

pagina_begin('Team', 'medewerkers.php');
pagina_kop('Team', 'Iedereen heeft een eigen, persoonlijk account met tweestapsverificatie. Medewerkers zien alleen de kinderen in de groepen waaraan ze gekoppeld zijn (eventueel tijdelijk, voor invallers). Beheerders zien alles, plus groepen, facturen en instellingen.');
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
            <td><?= e(ucfirst($persoon['rol'])) ?><br><small class="muted"><?= $persoon['mfa_actief'] ? 'Tweestaps aan' : 'Tweestaps nog instellen' ?></small>
<?php if ($persoon['rol'] === 'medewerker'): ?>
              <details class="uitklap" style="margin-top: var(--space-2xs)">
                <summary>Groepen<?= !empty($koppelingen[$persoon['id']]) ? ' (' . count($koppelingen[$persoon['id']]) . ')' : ' (geen)' ?></summary>
                <form class="form" method="post"><?= csrf_veld() ?><input type="hidden" name="gebruiker" value="<?= (int) $persoon['id'] ?>"><input type="hidden" name="actie" value="groepen">
                  <div class="choices">
<?php foreach ($alleGroepen as $g): ?>
                    <label class="choice"><input type="checkbox" name="groepen[]" value="<?= (int) $g['id'] ?>"<?= array_key_exists($g['id'], $koppelingen[$persoon['id']] ?? []) ? ' checked' : '' ?>><?= e($g['naam']) ?></label>
<?php endforeach; ?>
                  </div>
                  <div class="field field--klein"><label for="tot-<?= (int) $persoon['id'] ?>">Tijdelijk, tot en met <span class="field__hint">(optioneel)</span></label><input id="tot-<?= (int) $persoon['id'] ?>" name="tot" type="date" value="<?= e((string) (current($koppelingen[$persoon['id']] ?? []) ?: '')) ?>"></div>
                  <button class="btn btn--secondary btn--mini" type="submit">Groepen opslaan</button>
                </form>
              </details>
<?php endif; ?>
            </td>
            <td><?= status_badge($persoon['status']) ?><br><small class="muted"><?= $persoon['wachtwoord_hash'] ? ($persoon['laatst_ingelogd'] ? 'Ingelogd ' . e(moment_nl($persoon['laatst_ingelogd'])) : 'Nog niet ingelogd') : 'Uitnodiging nog niet gebruikt' ?></small></td>
            <td>
<?php if ((int) $persoon['id'] !== (int) $ik['id']): ?>
              <div class="btn-group">
                <form class="inline-form" method="post"><?= csrf_veld() ?><input type="hidden" name="gebruiker" value="<?= (int) $persoon['id'] ?>"><input type="hidden" name="actie" value="rol"><button class="btn btn--secondary btn--mini" type="submit">Maak <?= $persoon['rol'] === 'beheerder' ? 'medewerker' : 'beheerder' ?><span class="visually-hidden"> (<?= e($persoon['naam']) ?>)</span></button></form>
                <form class="inline-form" method="post"><?= csrf_veld() ?><input type="hidden" name="gebruiker" value="<?= (int) $persoon['id'] ?>"><input type="hidden" name="actie" value="link"><button class="btn btn--secondary btn--mini" type="submit">Nieuwe link<span class="visually-hidden"> voor <?= e($persoon['naam']) ?></span></button></form>
<?php if ($persoon['mfa_actief']): ?>
                <form class="inline-form" method="post" data-bevestig="Tweestapsverificatie van <?= e($persoon['naam']) ?> resetten? Doe dit alleen als je zeker weet dat het om deze persoon gaat (bijvoorbeeld persoonlijk gesproken)."><?= csrf_veld() ?><input type="hidden" name="gebruiker" value="<?= (int) $persoon['id'] ?>"><input type="hidden" name="actie" value="mfa_reset"><button class="btn btn--secondary btn--mini" type="submit">Tweestaps resetten<span class="visually-hidden"> voor <?= e($persoon['naam']) ?></span></button></form>
<?php endif; ?>
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
      <fieldset><legend class="legend-label">Groepen <span class="field__hint">(voor medewerkers)</span></legend><div class="choices">
<?php foreach ($alleGroepen as $g): ?>
        <label class="choice"><input type="checkbox" name="groepen[]" value="<?= (int) $g['id'] ?>"><?= e($g['naam']) ?></label>
<?php endforeach; ?>
      </div></fieldset>
      <p class="field__hint" style="margin: 0">Gebruik altijd een persoonlijk e-mailadres, geen gedeeld adres zoals info@.</p>
      <div class="form__acties"><button class="btn" type="submit"><?= icoon('send') ?>Uitnodiging sturen</button></div>
    </form>
  </section>
</div>
<?php
pagina_einde();
