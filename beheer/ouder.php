<?php
/* Eén ouder (klant): gegevens, aanmelding goedkeuren, kinderen indelen, facturen (team). */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_recht('ouders');
$ouder = rij("SELECT * FROM gebruikers WHERE id = ? AND rol = 'ouder'", [get_int('id')]);
if (!$ouder || !team_mag_ouder((int) $ouder['id'])) {
    niet_gevonden();
}
// Klantbeheer (activeren, stoppen, gegevens, wissen): met het recht klanten beheren
$beheerder = team_mag('klanten_beheren', $gebruiker);
$terug = 'ouder.php?id=' . (int) $ouder['id'];
$onderwerp = 'gebruiker:' . (int) $ouder['id'];
$fouten = [];

// Kinderen waar deze ouder contracthouder of verzorger van is (medewerker: alleen binnen de eigen groepen)
$kinderen = rijen(
    'SELECT k.*, g.naam AS groepnaam, v.relatie AS v_relatie, v.gezag AS v_gezag, v.gezag_gecontroleerd_op AS v_gezag_gecontroleerd_op
     FROM kinderen k LEFT JOIN groepen g ON g.id = k.groep_id LEFT JOIN kind_verzorgers v ON v.kind_id = k.id AND v.gebruiker_id = ?
     WHERE (k.ouder_id = ? OR v.gebruiker_id IS NOT NULL) AND k.geanonimiseerd_op IS NULL AND ' . groep_voorwaarde('k.groep_id') . '
     ORDER BY k.actief DESC, k.voornaam',
    [$ouder['id'], $ouder['id']]
);
$kinderen = ontsleutel_kolommen($kinderen, ['bijzonderheden']);
// Activeren kan pas als bij elk kind (waarvan deze ouder contracthouder is) iemand met gecontroleerd gezag staat
$zonderGezag = array_filter($kinderen, fn ($k) => (int) $k['ouder_id'] === (int) $ouder['id'] && $k['actief']
    && !waarde('SELECT 1 FROM kind_verzorgers WHERE kind_id = ? AND gezag = 1 AND gezag_gecontroleerd_op IS NOT NULL', [$k['id']]));

if (is_post()) {
    csrf_controleer();
    vereis_recht('klanten_beheren');
    $actie = invoer('actie');

    if ($actie === 'activeren' && $ouder['status'] !== 'actief' && $zonderGezag) {
        flash('fout', 'Controleer eerst het gezag bij ' . implode(' en ', array_map(fn ($k) => $k['voornaam'], $zonderGezag)) . ' (in het dossier van het kind, onder Verzorgers en gezag).');
    } elseif ($actie === 'activeren' && $ouder['status'] !== 'actief') {
        q("UPDATE gebruikers SET status = 'actief', gestopt_op = NULL WHERE id = ?", [$ouder['id']]);
        systeembericht((int) $ouder['id'], 'Welkom bij Sporty! Je account is actief. Zodra je kind in een groep zit, kies je in de agenda zelf de dagen. Heb je vragen? Stuur ons hier gerust een berichtje.');
        stuur_mail($ouder['email'], 'Welkom bij Sporty', "Hoi {$ouder['naam']},\n\nWelkom bij Sporty! Je account is actief. Log in om de dagen voor je kind te kiezen:\n" . app_url('agenda.php') . "\n\nTot snel!");
        log_actie('Ouder geactiveerd', '', null, 'wijziging', $onderwerp);
        flash('succes', $ouder['naam'] . ' is actief. Zet de kinderen nu in een groep, als dat nog niet is gebeurd.');
    } elseif ($actie === 'stoppen' && $ouder['status'] !== 'gestopt') {
        q("UPDATE gebruikers SET status = 'gestopt', gestopt_op = ? WHERE id = ?", [nu(), $ouder['id']]);
        foreach (rijen("SELECT i.* FROM inschrijvingen i JOIN kinderen k ON k.id = i.kind_id WHERE k.ouder_id = ? AND i.datum > ? AND i.status IN ('bevestigd', 'wachtlijst')", [$ouder['id'], vandaag()]) as $inschrijving) {
            meld_af($inschrijving);
        }
        // Kinderen van deze contracthouder stoppen ook; vanaf nu lopen de bewaartermijnen
        q('UPDATE kinderen SET actief = 0, gestopt_op = COALESCE(gestopt_op, ?) WHERE ouder_id = ? AND actief = 1', [nu(), $ouder['id']]);
        log_actie('Ouder gestopt', '', null, 'wijziging', $onderwerp);
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
            log_actie('Oudergegevens gewijzigd', '', null, 'wijziging', $onderwerp);
            flash('succes', 'De gegevens zijn opgeslagen.');
        }
    } elseif ($actie === 'groep') {
        $kind = rij('SELECT * FROM kinderen WHERE id = ? AND ouder_id = ?', [(int) invoer('kind'), $ouder['id']]);
        $groepId = invoer('groep') === '' ? null : (int) invoer('groep');
        // Alleen binnen de eigen groepen (tenzij alle groepen)
        if ($kind && team_mag_kind($kind) && ($groepId === null || (groep($groepId) && team_mag_groep($groepId)))) {
            q('UPDATE kinderen SET groep_id = ? WHERE id = ?', [$groepId, $kind['id']]);
            log_actie('Kind ingedeeld', $groepId ? groep($groepId)['naam'] : 'geen groep', null, 'wijziging', 'kind:' . $kind['id']);
            flash('succes', $kind['voornaam'] . ' is ingedeeld.');
        }
    } elseif ($actie === 'wissen' && $ouder['status'] === 'gestopt') {
        wis_oudergegevens($ouder, 'handmatig door beheerder');
        flash('succes', 'De gegevens van ' . $ouder['naam'] . ' en de kinderen zijn gewist. Naam, adres en facturen blijven bewaard vanwege de fiscale bewaarplicht.');
    } elseif ($actie === 'mandaat_intrekken') {
        q("UPDATE gebruikers SET mandaat_status = 'ingetrokken' WHERE id = ?", [$ouder['id']]);
        log_actie('Machtiging ingetrokken', '', null, 'wijziging', $onderwerp);
        flash('succes', 'De machtiging staat op ingetrokken. Er worden geen incasso\'s meer gestart voor deze ouder. Trek hem ook in bij Mollie als dat nodig is.');
    }
    if (!$fouten) {
        redirect($terug);
    }
}

log_inzage('Oudergegevens bekeken', $onderwerp);
$facturen = team_mag('facturen', $gebruiker) ? rijen('SELECT * FROM facturen WHERE ouder_id = ? ORDER BY datum DESC LIMIT 12', [$ouder['id']]) : [];
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
  <p>Na de kennismaking: controleer bij elk kind het gezag (in het dossier van het kind, onder Verzorgers en gezag), zet de kinderen in een groep en activeer het account. Daarna kan de ouder zelf dagen kiezen.</p>
<?php if ($zonderGezag): ?>
  <p class="let-op"><?= icoon('alert') ?><span>Gezag nog niet gecontroleerd bij: <?= e(implode(', ', array_map(fn ($k) => $k['voornaam'], $zonderGezag))) ?>.</span></p>
<?php endif; ?>
<?php if ($beheerder): ?>
  <form method="post" action="<?= e($terug) ?>"><?= csrf_veld() ?><input type="hidden" name="actie" value="activeren"><button class="btn" type="submit"<?= $zonderGezag ? ' disabled' : '' ?>><?= icoon('check') ?>Account activeren</button></form>
<?php endif; ?>
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
            <div class="kindrij__info"><span><?= leeftijd($kind['geboortedatum']) ?> jaar</span><span><?= e($kind['school'] ?: 'School onbekend') ?></span>
              <span><?= (int) $kind['ouder_id'] === (int) $ouder['id'] ? 'Contracthouder' : 'Verzorger' ?><?= $kind['v_relatie'] ? ' (' . e(mb_strtolower(RELATIES[$kind['v_relatie']] ?? '')) . ')' : '' ?>, <?= (int) $kind['v_gezag'] ? ($kind['v_gezag_gecontroleerd_op'] ? 'gezag gecontroleerd' : 'gezag nog controleren') : 'geen gezag' ?></span></div>
<?php if ($kind['bijzonderheden'] !== ''): ?><p class="let-op"><?= icoon('alert') ?><span><?= e($kind['bijzonderheden']) ?></span></p><?php endif; ?>
          </div>
<?php if ($beheerder && (int) $kind['ouder_id'] === (int) $ouder['id']): ?>
          <form class="inline-form groepkeuze" method="post" action="<?= e($terug) ?>">
            <?= csrf_veld() ?><input type="hidden" name="actie" value="groep"><input type="hidden" name="kind" value="<?= (int) $kind['id'] ?>">
            <label class="visually-hidden" for="groep-<?= (int) $kind['id'] ?>">Groep van <?= e($kind['voornaam']) ?></label>
            <select id="groep-<?= (int) $kind['id'] ?>" name="groep"><?= groep_opties($kind['groep_id'] !== null ? (int) $kind['groep_id'] : null) ?></select>
            <button class="btn btn--secondary btn--mini" type="submit">Opslaan<span class="visually-hidden"> groep van <?= e($kind['voornaam']) ?></span></button>
          </form>
<?php else: ?>
          <span class="muted"><?= e($kind['groepnaam'] ?? 'Nog geen groep') ?></span>
<?php endif; ?>
        </li>
<?php endforeach; ?>
      </ul>
    </section>
<?php if (team_mag('facturen', $gebruiker)): ?>
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
<?php if ($beheerder): ?>
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
<?php endif; ?>
    </section>
<?php if ($beheerder): ?>
    <section class="panel" aria-labelledby="account-titel">
      <h2 id="account-titel">Account</h2>
      <div class="btn-group">
<?php if ($ouder['status'] === 'gestopt'): ?>
        <form method="post" action="<?= e($terug) ?>"><?= csrf_veld() ?><input type="hidden" name="actie" value="activeren"><button class="btn btn--secondary btn--small" type="submit">Weer activeren</button></form>
<?php else: ?>
        <form method="post" action="<?= e($terug) ?>" data-bevestig="Weet je zeker dat <?= e($ouder['naam']) ?> stopt? Alle toekomstige dagen worden afgemeld en de ouder kan niet meer inloggen."><?= csrf_veld() ?><input type="hidden" name="actie" value="stoppen"><button class="btn btn--gevaar btn--small" type="submit">Klant is gestopt</button></form>
<?php endif; ?>
<?php if ($ouder['status'] === 'gestopt' && !$ouder['gewist_op']): ?>
        <form method="post" action="<?= e($terug) ?>" data-bevestig="Alle gegevens van <?= e($ouder['naam']) ?> en de kinderen wissen (dossiers, observaties, foto's, berichten en aanwezigheid)? Dit kan niet ongedaan worden gemaakt. Naam, adres en facturen blijven bewaard."><?= csrf_veld() ?><input type="hidden" name="actie" value="wissen"><button class="btn btn--gevaar btn--small" type="submit"><?= icoon('trash') ?>Gegevens wissen (AVG)</button></form>
<?php endif; ?>
<?php if (in_array($ouder['mandaat_status'], ['geldig', 'demo'], true)): ?>
        <form method="post" action="<?= e($terug) ?>" data-bevestig="Machtiging intrekken? Er worden dan geen incasso's meer gestart."><?= csrf_veld() ?><input type="hidden" name="actie" value="mandaat_intrekken"><button class="btn btn--gevaar btn--small" type="submit">Machtiging intrekken</button></form>
<?php endif; ?>
      </div>
      <p class="muted" style="margin-top: var(--space-s)"><a href="logboek.php?onderwerp=<?= e(rawurlencode($onderwerp)) ?>">Logboek van deze ouder</a></p>
    </section>
<?php endif; ?>
  </div>
</div>
<?php
pagina_einde();
