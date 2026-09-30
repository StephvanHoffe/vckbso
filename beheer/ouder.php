<?php
/* Eén ouder (klant): gegevens, aanmelding goedkeuren, kinderen indelen, facturen (team). */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_team();
$ouder = rij("SELECT * FROM gebruikers WHERE id = ? AND rol = 'ouder'", [get_int('id')]);
if (!$ouder) {
    niet_gevonden();
}
$terug = 'ouder.php?id=' . (int) $ouder['id'];
$fouten = [];

if (is_post()) {
    csrf_controleer();
    $actie = invoer('actie');

    if ($actie === 'activeren' && $ouder['status'] !== 'actief') {
        q("UPDATE gebruikers SET status = 'actief' WHERE id = ?", [$ouder['id']]);
        systeembericht((int) $ouder['id'], 'Welkom bij BSO VCK! Je account is actief. Zodra je kind in een groep zit, kies je in de agenda zelf de dagen. Heb je vragen? Stuur ons hier gerust een berichtje.');
        stuur_mail($ouder['email'], 'Welkom bij BSO VCK', "Hoi {$ouder['naam']},\n\nWelkom bij BSO VCK! Je account is actief. Log in om de dagen voor je kind te kiezen:\n" . app_url('agenda.php') . "\n\nTot snel!");
        log_actie('Ouder geactiveerd', $ouder['naam']);
        flash('succes', $ouder['naam'] . ' is actief. Zet de kinderen nu in een groep, als dat nog niet is gebeurd.');
    } elseif ($actie === 'stoppen' && $ouder['status'] !== 'gestopt') {
        q("UPDATE gebruikers SET status = 'gestopt' WHERE id = ?", [$ouder['id']]);
        foreach (rijen("SELECT i.* FROM inschrijvingen i JOIN kinderen k ON k.id = i.kind_id WHERE k.ouder_id = ? AND i.datum > ? AND i.status IN ('bevestigd', 'wachtlijst')", [$ouder['id'], vandaag()]) as $inschrijving) {
            meld_af($inschrijving);
        }
        log_actie('Ouder gestopt', $ouder['naam']);
        flash('succes', $ouder['naam'] . ' is gestopt. Toekomstige dagen zijn afgemeld en de ouder kan niet meer inloggen.');
    } elseif ($actie === 'gegevens') {
        $velden = [
            'naam' => invoer('naam'), 'email' => invoer('email'), 'telefoon' => invoer('telefoon'),
            'straat' => invoer('straat'), 'postcode' => invoer('postcode'), 'plaats' => invoer('plaats'),
        ];
        if ($velden['naam'] === '') {
            $fouten['naam'] = 'Vul de naam in.';
        }
        if (!geldig_email($velden['email'])) {
            $fouten['email'] = 'Vul een geldig e-mailadres in.';
        } elseif (waarde('SELECT id FROM gebruikers WHERE email = ? AND id != ?', [$velden['email'], $ouder['id']])) {
            $fouten['email'] = 'Dit e-mailadres hoort al bij een ander account.';
        }
        if ($velden['postcode'] !== '' && !geldige_postcode($velden['postcode'])) {
            $fouten['postcode'] = 'Vul een geldige postcode in.';
        }
        if (!$fouten) {
            $velden['postcode'] = $velden['postcode'] !== '' ? normaliseer_postcode($velden['postcode']) : '';
            q('UPDATE gebruikers SET naam = ?, email = ?, telefoon = ?, straat = ?, postcode = ?, plaats = ? WHERE id = ?', [...array_values($velden), $ouder['id']]);
            log_actie('Oudergegevens gewijzigd', $velden['naam']);
            flash('succes', 'De gegevens zijn opgeslagen.');
        }
    } elseif ($actie === 'groep') {
        $kind = rij('SELECT * FROM kinderen WHERE id = ? AND ouder_id = ?', [(int) invoer('kind'), $ouder['id']]);
        $groepId = invoer('groep') === '' ? null : (int) invoer('groep');
        if ($kind && ($groepId === null || groep($groepId))) {
            q('UPDATE kinderen SET groep_id = ? WHERE id = ?', [$groepId, $kind['id']]);
            log_actie('Kind ingedeeld', $kind['voornaam']);
            flash('succes', $kind['voornaam'] . ' is ingedeeld.');
        }
    } elseif ($actie === 'wissen' && is_beheerder($gebruiker) && $ouder['status'] === 'gestopt') {
        wis_oudergegevens($ouder);
        flash('succes', 'De gegevens van ' . $ouder['naam'] . ' en de kinderen zijn gewist. Naam, adres en facturen blijven bewaard vanwege de fiscale bewaarplicht.');
    } elseif ($actie === 'mandaat_intrekken' && is_beheerder($gebruiker)) {
        q("UPDATE gebruikers SET mandaat_status = 'ingetrokken' WHERE id = ?", [$ouder['id']]);
        log_actie('Machtiging ingetrokken', $ouder['naam']);
        flash('succes', 'De machtiging staat op ingetrokken. Er worden geen incasso\'s meer gestart voor deze ouder. Trek hem ook in bij Mollie als dat nodig is.');
    }
    if (!$fouten) {
        redirect($terug);
    }
}

$kinderen = rijen('SELECT k.*, g.naam AS groepnaam FROM kinderen k LEFT JOIN groepen g ON g.id = k.groep_id WHERE k.ouder_id = ? ORDER BY k.actief DESC, k.voornaam', [$ouder['id']]);
$facturen = is_beheerder($gebruiker) ? rijen('SELECT * FROM facturen WHERE ouder_id = ? ORDER BY datum DESC LIMIT 12', [$ouder['id']]) : [];
$gewensteDagen = array_map(fn ($d) => WEEKDAGEN[(int) $d] ?? '', array_filter(explode(',', $ouder['gewenste_dagen'])));

pagina_begin($ouder['naam'], 'ouders.php');
echo '<a class="terug-link" href="ouders.php">' . icoon('back') . 'Alle ouders</a>';
pagina_kop($ouder['naam'], status_badge($ouder['status']) . ' ' . status_badge($ouder['mandaat_status']),
    '<a class="btn btn--secondary btn--small" href="berichten.php?ouder=' . (int) $ouder['id'] . '">' . icoon('chat') . 'Berichten</a>');
?>
<?= foutensamenvatting($fouten) ?>
<?php if ($ouder['status'] === 'nieuw'): ?>
<section class="panel" aria-labelledby="aanmelding-titel" style="background: var(--color-sun-soft)">
  <h2 id="aanmelding-titel">Nieuwe aanmelding</h2>
  <dl class="gegevens">
    <dt>Aangemeld</dt><dd><?= e(moment_nl($ouder['aangemaakt_op'])) ?></dd>
    <dt>Contact via</dt><dd><?= e($ouder['contactvoorkeur'] ?: '-') ?></dd>
    <dt>Gewenste dagen</dt><dd><?= e($gewensteDagen ? implode(', ', $gewensteDagen) : '-') ?></dd>
    <dt>Gewenste start</dt><dd><?= e($ouder['gewenste_startdatum'] ? datum_nl($ouder['gewenste_startdatum']) : '-') ?></dd>
    <dt>Opmerkingen</dt><dd><?= e($ouder['opmerkingen'] ?: '-') ?></dd>
  </dl>
  <p>Na de kennismaking: zet de kinderen hieronder in een groep en activeer het account. Daarna kan de ouder zelf dagen kiezen.</p>
  <form method="post" action="<?= e($terug) ?>"><?= csrf_veld() ?><input type="hidden" name="actie" value="activeren"><button class="btn" type="submit"><?= icoon('check') ?>Account activeren</button></form>
</section>
<?php endif; ?>
<div class="kolommen">
  <div class="stapel">
    <section class="panel" aria-labelledby="kinderen-titel">
      <h2 id="kinderen-titel">Kinderen</h2>
      <ul class="kindlijst">
<?php foreach ($kinderen as $kind): ?>
        <li class="kindrij">
          <div>
            <div class="kindrij__naam"><a href="kind.php?id=<?= (int) $kind['id'] ?>"><?= e(kindnaam($kind)) ?></a><?= $kind['actief'] ? '' : ' ' . status_badge('gestopt') ?></div>
            <div class="kindrij__info"><span><?= leeftijd($kind['geboortedatum']) ?> jaar</span><span><?= e($kind['school'] ?: 'School onbekend') ?></span></div>
<?php if ($kind['bijzonderheden'] !== ''): ?><p class="let-op"><?= icoon('alert') ?><span><?= e($kind['bijzonderheden']) ?></span></p><?php endif; ?>
          </div>
          <form class="inline-form groepkeuze" method="post" action="<?= e($terug) ?>">
            <?= csrf_veld() ?><input type="hidden" name="actie" value="groep"><input type="hidden" name="kind" value="<?= (int) $kind['id'] ?>">
            <label class="visually-hidden" for="groep-<?= (int) $kind['id'] ?>">Groep van <?= e($kind['voornaam']) ?></label>
            <select id="groep-<?= (int) $kind['id'] ?>" name="groep"><?= groep_opties($kind['groep_id'] !== null ? (int) $kind['groep_id'] : null) ?></select>
            <button class="btn btn--secondary btn--mini" type="submit">Opslaan<span class="visually-hidden"> groep van <?= e($kind['voornaam']) ?></span></button>
          </form>
        </li>
<?php endforeach; ?>
      </ul>
    </section>
<?php if (is_beheerder($gebruiker)): ?>
    <section class="panel" aria-labelledby="facturen-titel">
      <div class="panel__kop"><h2 id="facturen-titel">Facturen</h2><a href="facturen.php">Alle facturen</a></div>
<?php if (!$facturen): ?>
      <p class="leeg">Nog geen facturen.</p>
<?php else: ?>
      <div class="tabel"><table>
        <thead><tr><th scope="col">Nummer</th><th scope="col">Maand</th><th scope="col" class="rechts">Bedrag</th><th scope="col">Status</th></tr></thead>
        <tbody>
<?php foreach ($facturen as $factuur): ?>
          <tr><td><a href="factuur.php?id=<?= (int) $factuur['id'] ?>"><?= e($factuur['nummer']) ?></a></td><td><?= e(datum_nl($factuur['periode'] . '-01', 'MMMM y')) ?></td><td class="rechts"><?= e(geld((int) $factuur['bedrag_cent'])) ?></td><td><?= status_badge($factuur['status']) ?></td></tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
<?php endif; ?>
    </section>
<?php endif; ?>
  </div>
  <div class="stapel">
    <section class="panel" aria-labelledby="contact-titel">
      <h2 id="contact-titel">Contactgegevens</h2>
      <dl class="gegevens">
        <dt>Telefoon</dt><dd><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $ouder['telefoon'])) ?>"><?= e($ouder['telefoon']) ?></a></dd>
        <dt>E-mail</dt><dd><a href="mailto:<?= e($ouder['email']) ?>"><?= e($ouder['email']) ?></a></dd>
        <dt>Adres</dt><dd><?= e($ouder['straat']) ?><br><?= e(trim($ouder['postcode'] . ' ' . $ouder['plaats'])) ?></dd>
        <dt>Incasso</dt><dd><?= status_badge($ouder['mandaat_status']) ?><?= $ouder['mandaat_rekening'] ? '<br>' . e($ouder['mandaat_rekening']) . ' (' . e($ouder['mandaat_naam']) . ')' : '' ?></dd>
        <dt>Laatst ingelogd</dt><dd><?= e($ouder['laatst_ingelogd'] ? moment_nl($ouder['laatst_ingelogd']) : 'Nog nooit') ?></dd>
      </dl>
      <details class="uitklap"<?= $fouten ? ' open' : '' ?>>
        <summary><?= icoon('gear') ?>Gegevens wijzigen</summary>
        <form class="form" method="post" action="<?= e($terug) ?>">
          <?= csrf_veld() ?><input type="hidden" name="actie" value="gegevens">
          <div class="field"><label for="naam">Naam</label><input id="naam" name="naam" required value="<?= e(invoer('naam', $ouder['naam'])) ?>"<?= aria_fout($fouten, 'naam') ?>><?= veldfout($fouten, 'naam') ?></div>
          <div class="field"><label for="email">E-mailadres</label><input id="email" name="email" type="email" required value="<?= e(invoer('email', $ouder['email'])) ?>"<?= aria_fout($fouten, 'email') ?>><?= veldfout($fouten, 'email') ?></div>
          <div class="field"><label for="telefoon">Telefoon</label><input id="telefoon" name="telefoon" type="tel" value="<?= e(invoer('telefoon', $ouder['telefoon'])) ?>"></div>
          <div class="field"><label for="straat">Straat en huisnummer</label><input id="straat" name="straat" value="<?= e(invoer('straat', $ouder['straat'])) ?>"></div>
          <div class="form__row">
            <div class="field"><label for="postcode">Postcode</label><input id="postcode" name="postcode" value="<?= e(invoer('postcode', $ouder['postcode'])) ?>"<?= aria_fout($fouten, 'postcode') ?>><?= veldfout($fouten, 'postcode') ?></div>
            <div class="field"><label for="plaats">Plaats</label><input id="plaats" name="plaats" value="<?= e(invoer('plaats', $ouder['plaats'])) ?>"></div>
          </div>
          <div class="form__acties"><button class="btn" type="submit">Opslaan</button></div>
        </form>
      </details>
    </section>
    <section class="panel" aria-labelledby="account-titel">
      <h2 id="account-titel">Account</h2>
      <div class="btn-group">
<?php if ($ouder['status'] === 'gestopt'): ?>
        <form method="post" action="<?= e($terug) ?>"><?= csrf_veld() ?><input type="hidden" name="actie" value="activeren"><button class="btn btn--secondary btn--small" type="submit">Weer activeren</button></form>
<?php else: ?>
        <form method="post" action="<?= e($terug) ?>" data-bevestig="Weet je zeker dat <?= e($ouder['naam']) ?> stopt? Alle toekomstige dagen worden afgemeld en de ouder kan niet meer inloggen."><?= csrf_veld() ?><input type="hidden" name="actie" value="stoppen"><button class="btn btn--gevaar btn--small" type="submit">Klant is gestopt</button></form>
<?php endif; ?>
<?php if (is_beheerder($gebruiker) && $ouder['status'] === 'gestopt' && $ouder['email'] !== 'gewist-' . $ouder['id'] . '@bsovck.invalid'): ?>
        <form method="post" action="<?= e($terug) ?>" data-bevestig="Alle gegevens van <?= e($ouder['naam']) ?> en de kinderen wissen (dossiers, observaties, foto's, berichten en aanwezigheid)? Dit kan niet ongedaan worden gemaakt. Naam, adres en facturen blijven bewaard."><?= csrf_veld() ?><input type="hidden" name="actie" value="wissen"><button class="btn btn--gevaar btn--small" type="submit"><?= icoon('trash') ?>Gegevens wissen (AVG)</button></form>
<?php endif; ?>
<?php if (is_beheerder($gebruiker) && in_array($ouder['mandaat_status'], ['geldig', 'demo'], true)): ?>
        <form method="post" action="<?= e($terug) ?>" data-bevestig="Machtiging intrekken? Er worden dan geen incasso's meer gestart."><?= csrf_veld() ?><input type="hidden" name="actie" value="mandaat_intrekken"><button class="btn btn--gevaar btn--small" type="submit">Machtiging intrekken</button></form>
<?php endif; ?>
      </div>
    </section>
  </div>
</div>
<?php
pagina_einde();
