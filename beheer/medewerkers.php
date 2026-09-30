<?php
/*
 * Team: collega's uitnodigen en per persoon de rechten instellen. Elk menuonderdeel
 * staat per persoon aan of uit; daarnaast de groepen (welke kinderen iemand ziet)
 * en twee extra rechten. Wijzigingen komen in het logboek.
 */
require __DIR__ . '/inc/bootstrap.php';

$ik = vereis_recht('team');
$fouten = [];
$naam = $email = '';
$nieuweRechten = RECHTEN_BEGELEIDER;
$nieuweGroepen = [];
$uitnodigingslink = null;

/** Gekozen rechten en groepen uit het formulier. */
function gekozen_rechten(): array
{
    return array_values(array_intersect(alle_rechten(), (array) ($_POST['rechten'] ?? [])));
}

function gekozen_groepen(): array
{
    return array_values(array_filter(array_map('intval', (array) ($_POST['groepen'] ?? [])), fn ($id) => groep($id) !== null));
}

if (is_post()) {
    csrf_controleer();
    $actie = invoer('actie');

    if ($actie === 'uitnodigen') {
        $naam = invoer('naam');
        $email = invoer('email');
        $nieuweRechten = gekozen_rechten();
        $nieuweGroepen = gekozen_groepen();
        if ($naam === '') {
            $fouten['naam'] = 'Vul de naam in.';
        }
        if (!geldig_email($email)) {
            $fouten['email'] = 'Vul een geldig e-mailadres in.';
        } elseif (waarde('SELECT id FROM gebruikers WHERE email = ?', [$email])) {
            $fouten['email'] = 'Er is al een account met dit e-mailadres.';
        }
        if (!$fouten) {
            q("INSERT INTO gebruikers (rol, status, naam, email, aangemaakt_op) VALUES ('medewerker', 'actief', ?, ?, ?)", [$naam, $email, nu()]);
            $id = laatste_id();
            zet_rechten($id, $nieuweRechten);
            foreach ($nieuweGroepen as $groepId) {
                q('INSERT OR IGNORE INTO medewerker_groepen (gebruiker_id, groep_id) VALUES (?, ?)', [$id, $groepId]);
            }
            $uitnodigingslink = maak_wachtwoordlink($id, 72);
            stuur_mail($email, 'Je account voor de beheeromgeving van BSO VCK', "Hoi {$naam},\n\nJe bent uitgenodigd voor de beheeromgeving van BSO VCK. Via deze link kies je je wachtwoord (de link werkt 3 dagen):\n\n{$uitnodigingslink}\n\nTot snel!");
            log_actie('Collega uitgenodigd', rechten_label($nieuweRechten), null, 'beveiliging', 'gebruiker:' . $id);
            flash('succes', "{$naam} is uitgenodigd en krijgt een e-mail met een link om een wachtwoord te kiezen.");
            $_SESSION['laatste_uitnodiging'] = $uitnodigingslink;
            redirect('medewerkers.php');
        }
    } else {
        $persoon = rij("SELECT * FROM gebruikers WHERE id = ? AND rol IN ('beheerder', 'medewerker')", [(int) invoer('gebruiker')]);
        if (!$persoon) {
            niet_gevonden();
        }
        $zelf = (int) $persoon['id'] === (int) $ik['id'];
        if ($zelf && in_array($actie, ['stoppen', 'mfa_reset'], true)) {
            flash('fout', 'Je kunt je eigen account niet stoppen of je eigen tweestapsverificatie resetten. Vraag dat aan een collega.');
            redirect('medewerkers.php#persoon-' . $persoon['id']);
        }
        $wie = 'gebruiker:' . $persoon['id'];
        if ($actie === 'rechten') {
            $rechten = gekozen_rechten();
            if ($zelf && !in_array('team', $rechten, true)) {
                flash('fout', 'Je kunt je eigen toegang tot Team niet uitzetten. Vraag dat aan een collega die ook het team beheert.');
                redirect('medewerkers.php#persoon-' . $persoon['id']);
            }
            $melding = zet_rechten((int) $persoon['id'], $rechten);
            if ($melding !== null) {
                flash('fout', $melding);
                redirect('medewerkers.php#persoon-' . $persoon['id']);
            }
            $tot = invoer('tot');
            $tot = geldige_datum($tot) ? $tot : null;
            $groepen = gekozen_groepen();
            $oudeGroepen = array_map('intval', array_column(rijen('SELECT groep_id FROM medewerker_groepen WHERE gebruiker_id = ? ORDER BY groep_id', [$persoon['id']]), 'groep_id'));
            transactie(function () use ($persoon, $tot, $groepen) {
                q('DELETE FROM medewerker_groepen WHERE gebruiker_id = ?', [$persoon['id']]);
                foreach ($groepen as $groepId) {
                    q('INSERT INTO medewerker_groepen (gebruiker_id, groep_id, tot) VALUES (?, ?, ?)', [$persoon['id'], $groepId, $tot]);
                }
            });
            sort($groepen);
            if ($groepen !== $oudeGroepen || $tot) {
                log_actie('Groepen gewijzigd', implode(',', $groepen) . ($tot ? " tot {$tot}" : ''), null, 'beveiliging', $wie);
            }
            flash('succes', 'De rechten van ' . $persoon['naam'] . ' zijn opgeslagen.');
        } elseif ($actie === 'mfa_reset') {
            mfa_zet_uit((int) $persoon['id'], 'reset door collega');
            flash('succes', 'De tweestapsverificatie van ' . $persoon['naam'] . ' is gereset. Bij de volgende keer inloggen stelt ' . $persoon['naam'] . ' hem opnieuw in.');
        } elseif ($actie === 'stoppen') {
            if (team_mag('team', $persoon) && (int) waarde("SELECT COUNT(*) FROM gebruikers g JOIN team_rechten r ON r.gebruiker_id = g.id AND r.recht = 'team' WHERE g.status = 'actief' AND g.id != ?", [$persoon['id']]) === 0) {
                flash('fout', 'Er moet altijd minstens één actieve collega zijn die het team kan beheren.');
                redirect('medewerkers.php');
            }
            q("UPDATE gebruikers SET status = 'gestopt' WHERE id = ?", [$persoon['id']]);
            log_actie('Collega gestopt', '', null, 'beveiliging', $wie);
            flash('succes', $persoon['naam'] . ' kan niet meer inloggen.');
        } elseif ($actie === 'activeren') {
            q("UPDATE gebruikers SET status = 'actief' WHERE id = ?", [$persoon['id']]);
            log_actie('Collega geactiveerd', '', null, 'beveiliging', $wie);
            flash('succes', $persoon['naam'] . ' kan weer inloggen.');
        } elseif ($actie === 'link') {
            $_SESSION['laatste_uitnodiging'] = maak_wachtwoordlink((int) $persoon['id'], 72);
            stuur_mail($persoon['email'], 'Nieuwe link voor de beheeromgeving van BSO VCK', "Hoi {$persoon['naam']},\n\nVia deze link kies je (opnieuw) je wachtwoord. De link werkt 3 dagen:\n\n{$_SESSION['laatste_uitnodiging']}");
            log_actie('Nieuwe wachtwoordlink', '', null, 'beveiliging', $wie);
            flash('succes', 'Er is een nieuwe link gemaakt en gemaild naar ' . $persoon['naam'] . '.');
        }
        redirect('medewerkers.php#persoon-' . $persoon['id']);
    }
}

$uitnodigingslink = $_SESSION['laatste_uitnodiging'] ?? null;
unset($_SESSION['laatste_uitnodiging']);
$team = rijen("SELECT * FROM gebruikers WHERE rol IN ('beheerder', 'medewerker') ORDER BY status = 'gestopt', naam");
$alleGroepen = rijen('SELECT * FROM groepen WHERE actief = 1 ORDER BY naam');
$koppelingen = [];
foreach (rijen('SELECT * FROM medewerker_groepen') as $rij) {
    $koppelingen[$rij['gebruiker_id']][$rij['groep_id']] = $rij['tot'];
}

/** Het formulierdeel met onderdelen, extra rechten en groepen (voor een collega of een uitnodiging). */
function rechten_velden(string $voorvoegsel, array $rechten, array $groepen, ?string $tot, array $alleGroepen): void
{
    ?>
      <fieldset class="rechten">
        <legend class="legend-label">Onderdelen in het menu</legend>
        <div class="rechten__snel" hidden data-rechten-snel>
          <span class="muted">Snel kiezen:</span>
          <button type="button" class="link-button" data-rechten-preset="alles">Alles aan</button>
          <button type="button" class="link-button" data-rechten-preset="begeleider">Begeleider</button>
          <button type="button" class="link-button" data-rechten-preset="niets">Alles uit</button>
        </div>
        <ul class="rechten__lijst">
<?php foreach (ONDERDELEN as $sleutel => [$pagina, $label, $ico, $uitleg]): $id = $voorvoegsel . '-' . $sleutel; ?>
          <li class="schakel">
            <input type="checkbox" id="<?= e($id) ?>" name="rechten[]" value="<?= e($sleutel) ?>"<?= in_array($sleutel, $rechten, true) ? ' checked' : '' ?> data-begeleider="<?= in_array($sleutel, RECHTEN_BEGELEIDER, true) ? '1' : '0' ?>" aria-describedby="<?= e($id) ?>-uitleg">
            <label for="<?= e($id) ?>"><?= icoon($ico) ?><?= e($label) ?></label>
            <span class="schakel__uitleg" id="<?= e($id) ?>-uitleg"><?= e($uitleg) ?></span>
          </li>
<?php endforeach; ?>
        </ul>
      </fieldset>
      <fieldset class="rechten">
        <legend class="legend-label">Extra rechten</legend>
        <ul class="rechten__lijst">
<?php foreach (EXTRA_RECHTEN as $sleutel => [$label, $uitleg]): $id = $voorvoegsel . '-' . $sleutel; ?>
          <li class="schakel">
            <input type="checkbox" id="<?= e($id) ?>" name="rechten[]" value="<?= e($sleutel) ?>"<?= in_array($sleutel, $rechten, true) ? ' checked' : '' ?> data-begeleider="0" aria-describedby="<?= e($id) ?>-uitleg">
            <label for="<?= e($id) ?>"><?= e($label) ?></label>
            <span class="schakel__uitleg" id="<?= e($id) ?>-uitleg"><?= e($uitleg) ?></span>
          </li>
<?php endforeach; ?>
        </ul>
      </fieldset>
      <fieldset class="rechten">
        <legend class="legend-label">Groepen <span class="field__hint">(welke kinderen, als Alle groepen uit staat)</span></legend>
<?php if ($alleGroepen): ?>
        <div class="choices">
<?php foreach ($alleGroepen as $g): ?>
          <label class="choice"><input type="checkbox" name="groepen[]" value="<?= (int) $g['id'] ?>"<?= in_array((int) $g['id'], $groepen, true) ? ' checked' : '' ?>><?= e($g['naam']) ?></label>
<?php endforeach; ?>
        </div>
        <div class="field field--klein"><label for="<?= e($voorvoegsel) ?>-tot">Tijdelijk, tot en met <span class="field__hint">(optioneel, bijvoorbeeld voor een invaller)</span></label><input id="<?= e($voorvoegsel) ?>-tot" name="tot" type="date" value="<?= e((string) $tot) ?>"></div>
<?php else: ?>
        <p class="muted" style="margin: 0">Er zijn nog geen groepen.</p>
<?php endif; ?>
      </fieldset>
    <?php
}

pagina_begin('Team', 'medewerkers.php');
pagina_kop('Team', 'Iedereen heeft een eigen account met tweestapsverificatie. Per collega zet je aan welke onderdelen van de beheeromgeving die mag gebruiken en welke groepen die ziet. Wat uit staat, verdwijnt uit het menu en is ook niet te openen.');
?>
<?php if ($uitnodigingslink): ?>
<div class="melding" role="status"><?= icoon('info') ?><div><p>Komt de e-mail niet aan? Stuur deze link dan zelf door (werkt 3 dagen, één keer te gebruiken):</p><p><code style="word-break: break-all"><?= e($uitnodigingslink) ?></code></p></div></div>
<?php endif; ?>
<div class="kolommen">
  <section class="stapel" aria-labelledby="team-titel">
    <h2 id="team-titel" class="visually-hidden">Collega's</h2>
<?php foreach ($team as $persoon):
    $rechten = rechten_van((int) $persoon['id']);
    $zelf = (int) $persoon['id'] === (int) $ik['id'];
    $groepenVan = array_map('intval', array_keys($koppelingen[$persoon['id']] ?? []));
    $onderdelenAan = array_values(array_filter(ONDERDELEN, fn ($s) => in_array($s, $rechten, true), ARRAY_FILTER_USE_KEY));
?>
    <article class="panel collega" id="persoon-<?= (int) $persoon['id'] ?>" aria-labelledby="naam-<?= (int) $persoon['id'] ?>">
      <div class="collega__kop">
        <div>
          <h3 class="collega__naam" id="naam-<?= (int) $persoon['id'] ?>"><?= e($persoon['naam']) ?><?= $zelf ? ' <span class="badge">Dit ben jij</span>' : '' ?></h3>
          <p class="muted collega__info"><?= e($persoon['email']) ?> · <?= $persoon['wachtwoord_hash'] ? ($persoon['laatst_ingelogd'] ? 'ingelogd ' . e(moment_nl($persoon['laatst_ingelogd'])) : 'nog niet ingelogd') : 'uitnodiging nog niet gebruikt' ?></p>
        </div>
        <div class="collega__badges">
          <?= status_badge($persoon['status']) ?>
          <span class="badge<?= $persoon['mfa_actief'] ? ' badge--geldig' : ' badge--wachtlijst' ?>"><?= $persoon['mfa_actief'] ? 'Tweestaps aan' : 'Tweestaps nog instellen' ?></span>
          <span class="badge"><?= e(rechten_label($rechten)) ?></span>
        </div>
      </div>
      <p class="collega__onderdelen"><?= $onderdelenAan ? e(implode(' · ', array_column($onderdelenAan, 1))) : 'Nog geen onderdelen aan' ?><?= in_array('alle_groepen', $rechten, true) ? ' · alle groepen' : ($groepenVan ? ' · ' . e(implode(', ', array_map(fn ($id) => groep($id)['naam'] ?? '', $groepenVan))) : '') ?></p>
      <details class="uitklap">
        <summary><?= icoon('key') ?>Rechten en groepen<span class="visually-hidden"> van <?= e($persoon['naam']) ?></span></summary>
        <form class="form" method="post" action="medewerkers.php" data-rechten-form>
          <?= csrf_veld() ?><input type="hidden" name="gebruiker" value="<?= (int) $persoon['id'] ?>"><input type="hidden" name="actie" value="rechten">
          <?php rechten_velden('p' . (int) $persoon['id'], $rechten, $groepenVan, current($koppelingen[$persoon['id']] ?? []) ?: null, $alleGroepen); ?>
<?php if ($zelf): ?>
          <p class="muted" style="margin: 0">Je eigen toegang tot Team kun je niet uitzetten; dat doet een collega.</p>
<?php endif; ?>
          <div class="form__acties"><button class="btn btn--small" type="submit">Rechten opslaan<span class="visually-hidden"> voor <?= e($persoon['naam']) ?></span></button></div>
        </form>
      </details>
<?php if (!$zelf): ?>
      <div class="btn-group">
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
<?php endif; ?>
    </article>
<?php endforeach; ?>
  </section>
  <section class="panel" aria-labelledby="nieuw-titel">
    <h2 id="nieuw-titel">Collega uitnodigen</h2>
    <?= foutensamenvatting($fouten) ?>
    <form class="form" method="post" data-rechten-form>
      <?= csrf_veld() ?>
      <input type="hidden" name="actie" value="uitnodigen">
      <div class="field"><label for="naam">Naam</label><input id="naam" name="naam" required value="<?= e($naam) ?>"<?= aria_fout($fouten, 'naam') ?>><?= veldfout($fouten, 'naam') ?></div>
      <div class="field"><label for="email">E-mailadres</label><input id="email" name="email" type="email" required value="<?= e($email) ?>"<?= aria_fout($fouten, 'email') ?>><?= veldfout($fouten, 'email') ?></div>
      <?php rechten_velden('nieuw', $nieuweRechten, $nieuweGroepen, null, $alleGroepen); ?>
      <p class="field__hint" style="margin: 0">Gebruik altijd een persoonlijk e-mailadres, geen gedeeld adres zoals info@. Je collega stelt bij de eerste keer inloggen tweestapsverificatie in.</p>
      <div class="form__acties"><button class="btn" type="submit"><?= icoon('send') ?>Uitnodiging sturen</button></div>
    </form>
  </section>
</div>
<?php
pagina_einde();
