<?php
/*
 * Aanmelden als klant: ouderaccount + kinderen. Daarna geeft de ouder een
 * machtiging voor automatische incasso (machtiging.php). Het team plant een
 * kennismaking, zet de kinderen in een groep en activeert het account.
 */
require __DIR__ . '/inc/bootstrap.php';

if ($ingelogd = huidige_gebruiker()) {
    redirect(is_ouder($ingelogd) ? 'kinderen.php' : 'index.php');
}

const MAX_KINDEREN_AANMELDEN = 6;

$waarden = [
    'naam' => '', 'email' => '', 'telefoon' => '', 'straat' => '', 'postcode' => '', 'plaats' => 'Amsterdam',
    'contactvoorkeur' => 'Maakt niet uit', 'startdatum' => '', 'opmerkingen' => '', 'dagen' => [],
];
$kinderen = [['voornaam' => '', 'achternaam' => '', 'geboortedatum' => '', 'school' => '', 'bijzonderheden' => '', 'foto_toestemming' => '']];
$fouten = [];

if (is_post()) {
    csrf_controleer();
    foreach (['naam', 'email', 'telefoon', 'straat', 'postcode', 'plaats', 'contactvoorkeur', 'startdatum', 'opmerkingen'] as $veld) {
        $waarden[$veld] = invoer($veld);
    }
    $waarden['dagen'] = array_values(array_intersect(array_map('intval', (array) ($_POST['dagen'] ?? [])), [1, 2, 3, 4, 5]));

    $kinderen = [];
    foreach (array_slice((array) ($_POST['kinderen'] ?? []), 0, MAX_KINDEREN_AANMELDEN) as $invoerKind) {
        if (!is_array($invoerKind)) {
            continue;
        }
        $kind = [];
        foreach (['voornaam', 'achternaam', 'geboortedatum', 'school', 'bijzonderheden'] as $veld) {
            $kind[$veld] = trim((string) ($invoerKind[$veld] ?? ''));
        }
        $kind['foto_toestemming'] = !empty($invoerKind['foto_toestemming']) ? '1' : '';
        $kinderen[] = $kind;
    }
    if (!$kinderen) {
        $kinderen = [['voornaam' => '', 'achternaam' => '', 'geboortedatum' => '', 'school' => '', 'bijzonderheden' => '', 'foto_toestemming' => '']];
    }

    // Spam: dit verborgen veld vullen alleen robots in; en niet te veel aanmeldingen vanaf één adres
    if (invoer('website') !== '') {
        redirect('aanmelden.php');
    }
    if (te_veel_verzoeken('a:' . ip_adres(), 10)) {
        $fouten['algemeen'] = 'Er zijn net erg veel aanmeldingen verstuurd vanaf dit netwerk. Probeer het over een kwartier opnieuw, of neem contact met ons op.';
    }

    if ($waarden['naam'] === '') {
        $fouten['naam'] = 'Vul je naam in.';
    }
    if (!geldig_email($waarden['email'])) {
        $fouten['email'] = 'Vul een geldig e-mailadres in, bijvoorbeeld naam@voorbeeld.nl.';
    } elseif (waarde('SELECT id FROM gebruikers WHERE email = ?', [$waarden['email']])) {
        $fouten['email'] = 'Er is al een account met dit e-mailadres. Log in of vraag een nieuw wachtwoord aan.';
    }
    if (!geldig_telefoon($waarden['telefoon'])) {
        $fouten['telefoon'] = 'Vul een geldig telefoonnummer in, bijvoorbeeld 06 12 34 56 78.';
    }
    if ($waarden['straat'] === '') {
        $fouten['straat'] = 'Vul je straat en huisnummer in. Die hebben we nodig voor de facturen.';
    }
    if (!geldige_postcode($waarden['postcode'])) {
        $fouten['postcode'] = 'Vul een geldige postcode in, bijvoorbeeld 1012 AB.';
    }
    if ($waarden['plaats'] === '') {
        $fouten['plaats'] = 'Vul je woonplaats in.';
    }
    if ($waarden['startdatum'] !== '' && (!geldige_datum($waarden['startdatum']) || $waarden['startdatum'] < vandaag())) {
        $fouten['startdatum'] = 'Kies een startdatum vanaf vandaag.';
    }
    foreach ($kinderen as $i => $kind) {
        $nr = $i + 1;
        if ($kind['voornaam'] === '') {
            $fouten["kind{$i}_voornaam"] = "Vul de voornaam van kind {$nr} in.";
        }
        if ($kind['achternaam'] === '') {
            $fouten["kind{$i}_achternaam"] = "Vul de achternaam van kind {$nr} in.";
        }
        if (!geldige_datum($kind['geboortedatum']) || $kind['geboortedatum'] > vandaag() || $kind['geboortedatum'] < date('Y-m-d', strtotime('-16 years'))) {
            $fouten["kind{$i}_geboortedatum"] = "Vul een geldige geboortedatum in voor kind {$nr}.";
        }
    }
    if ($w = controleer_wachtwoord((string) ($_POST['wachtwoord'] ?? ''), (string) ($_POST['wachtwoord2'] ?? ''))) {
        $fouten['wachtwoord'] = $w;
    }
    if (empty($_POST['akkoord'])) {
        $fouten['akkoord'] = 'Vink aan dat je akkoord gaat met de privacyverklaring en de voorwaarden.';
    }
    if (empty($_POST['incasso'])) {
        $fouten['incasso'] = 'Vink aan dat je een machtiging voor automatische incasso wilt afgeven.';
    }

    if (!$fouten) {
        $ouderId = transactie(function () use ($waarden, $kinderen) {
            q("INSERT INTO gebruikers (rol, status, naam, email, telefoon, straat, postcode, plaats, contactvoorkeur, wachtwoord_hash, gewenste_dagen, gewenste_startdatum, opmerkingen, aangemaakt_op)
               VALUES ('ouder', 'nieuw', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
                $waarden['naam'], $waarden['email'], $waarden['telefoon'], $waarden['straat'], normaliseer_postcode($waarden['postcode']), $waarden['plaats'],
                $waarden['contactvoorkeur'], password_hash((string) $_POST['wachtwoord'], PASSWORD_DEFAULT),
                implode(',', $waarden['dagen']), $waarden['startdatum'] ?: null, $waarden['opmerkingen'], nu(),
            ]);
            $ouderId = laatste_id();
            foreach ($kinderen as $kind) {
                q('INSERT INTO kinderen (ouder_id, voornaam, achternaam, geboortedatum, school, bijzonderheden, foto_toestemming, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
                    $ouderId, $kind['voornaam'], $kind['achternaam'], $kind['geboortedatum'], $kind['school'], $kind['bijzonderheden'], $kind['foto_toestemming'] ? 1 : 0, nu(),
                ]);
            }
            return $ouderId;
        });
        log_actie('Aangemeld via de website', count($kinderen) . ' kind(eren)', $ouderId);
        mail_team('Nieuwe aanmelding via de website', "Er is een nieuwe aanmelding binnen van {$waarden['naam']} met " . count($kinderen) . " kind(eren).\n\nBekijk de aanmelding in de beheeromgeving:\n" . app_url('ouder.php?id=' . $ouderId));
        stuur_mail($waarden['email'], 'Je aanmelding bij BSO VCK', "Hoi {$waarden['naam']},\n\nWat leuk dat je je kind hebt aangemeld bij BSO VCK! We hebben je aanmelding goed ontvangen en nemen snel contact met je op voor een kennismaking.\n\nJe kunt inloggen op " . app_url('') . " met je e-mailadres en het wachtwoord dat je hebt gekozen.\n\nTot snel!");
        log_in_als($ouderId);
        flash('succes', 'Joepie, je account is aangemaakt! Nog één stap: de machtiging voor automatische incasso.');
        redirect('machtiging.php');
    }
}

function kind_velden(int|string $i, array $kind, array $fouten): string
{
    $nummer = is_int($i) ? $i + 1 : 1;
    $id = 'kind' . $i;
    ob_start();
    ?>
            <div class="repeat-item" data-kind>
              <fieldset>
                <legend class="legend-label">Kind <span data-kind-nummer><?= $nummer ?></span></legend>
                <div class="form__row">
                  <div class="field">
                    <label for="<?= $id ?>-voornaam">Voornaam *</label>
                    <input id="<?= $id ?>-voornaam" name="kinderen[<?= $i ?>][voornaam]" required autocomplete="off" value="<?= e($kind['voornaam']) ?>"<?= aria_fout($fouten, "{$id}_voornaam") ?>>
                    <?= veldfout($fouten, "{$id}_voornaam") ?>
                  </div>
                  <div class="field">
                    <label for="<?= $id ?>-achternaam">Achternaam *</label>
                    <input id="<?= $id ?>-achternaam" name="kinderen[<?= $i ?>][achternaam]" required autocomplete="off" value="<?= e($kind['achternaam']) ?>"<?= aria_fout($fouten, "{$id}_achternaam") ?>>
                    <?= veldfout($fouten, "{$id}_achternaam") ?>
                  </div>
                </div>
                <div class="form__row">
                  <div class="field">
                    <label for="<?= $id ?>-geboortedatum">Geboortedatum *</label>
                    <input id="<?= $id ?>-geboortedatum" name="kinderen[<?= $i ?>][geboortedatum]" type="date" required max="<?= vandaag() ?>" value="<?= e($kind['geboortedatum']) ?>"<?= aria_fout($fouten, "{$id}_geboortedatum") ?>>
                    <?= veldfout($fouten, "{$id}_geboortedatum") ?>
                  </div>
                  <div class="field">
                    <label for="<?= $id ?>-school">Basisschool <span class="field__hint">(optioneel)</span></label>
                    <input id="<?= $id ?>-school" name="kinderen[<?= $i ?>][school]" autocomplete="off" value="<?= e($kind['school']) ?>">
                  </div>
                </div>
                <div class="field">
                  <label for="<?= $id ?>-bijzonderheden">Allergieën, medicijnen of andere bijzonderheden <span class="field__hint">(optioneel)</span></label>
                  <textarea id="<?= $id ?>-bijzonderheden" name="kinderen[<?= $i ?>][bijzonderheden]" rows="3"><?= e($kind['bijzonderheden']) ?></textarea>
                </div>
                <div class="field">
                  <div class="consent">
                    <input id="<?= $id ?>-foto" name="kinderen[<?= $i ?>][foto_toestemming]" type="checkbox" value="1"<?= $kind['foto_toestemming'] ? ' checked' : '' ?>>
                    <label for="<?= $id ?>-foto">Mijn kind mag op groepsfoto's die ook naar de andere ouders van die kinderen gaan. <span class="field__hint">(Foto's waar alleen jouw kind op staat, krijg jij altijd. Je kunt dit later aanpassen.)</span></label>
                  </div>
                </div>
                <button class="link-button" type="button" data-kind-verwijderen hidden><?= icoon('minus') ?>Verwijder dit kind</button>
              </fieldset>
            </div>
<?php
    return (string) ob_get_clean();
}

pagina_begin('Kind aanmelden', '', ['publiek' => true]);
?>
<div class="auth auth--breed">
  <div class="panel">
    <p class="eyebrow">Aanmelden</p>
    <h1>Meld je kind aan bij BSO VCK</h1>
    <p>Wat leuk dat je voor ons kiest! Je maakt meteen een account aan. Daarmee kies je straks zelf de dagen in de agenda, stuur je ons berichtjes en vind je je facturen terug.</p>
    <p class="muted">Velden met een * zijn verplicht. Heb je al een account? <a href="inloggen.php">Log dan in</a>.</p>
    <?= foutensamenvatting($fouten) ?>
    <form class="form form--sections" method="post" action="aanmelden.php" novalidate>
      <?= csrf_veld() ?>
      <fieldset>
        <legend class="legend-title">Jouw gegevens</legend>
        <div class="form__row">
          <div class="field">
            <label for="naam">Je naam *</label>
            <input id="naam" name="naam" required autocomplete="name" value="<?= e($waarden['naam']) ?>"<?= aria_fout($fouten, 'naam') ?>>
            <?= veldfout($fouten, 'naam') ?>
          </div>
          <div class="field">
            <label for="email">E-mailadres *</label>
            <input id="email" name="email" type="email" required autocomplete="email" value="<?= e($waarden['email']) ?>"<?= aria_fout($fouten, 'email') ?>>
            <?= veldfout($fouten, 'email') ?>
          </div>
        </div>
        <div class="form__row">
          <div class="field">
            <label for="telefoon">Telefoonnummer *</label>
            <input id="telefoon" name="telefoon" type="tel" required autocomplete="tel" inputmode="tel" value="<?= e($waarden['telefoon']) ?>"<?= aria_fout($fouten, 'telefoon') ?>>
            <?= veldfout($fouten, 'telefoon') ?>
          </div>
          <div class="field">
            <label for="contactvoorkeur">Hoe bereiken we je het liefst?</label>
            <select id="contactvoorkeur" name="contactvoorkeur">
<?php foreach (['Bellen', 'Mailen', 'Berichtje in de app', 'Maakt niet uit'] as $optie): ?>
              <option<?= $waarden['contactvoorkeur'] === $optie ? ' selected' : '' ?>><?= e($optie) ?></option>
<?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field">
          <label for="straat">Straat en huisnummer *</label>
          <input id="straat" name="straat" required autocomplete="street-address" value="<?= e($waarden['straat']) ?>"<?= aria_fout($fouten, 'straat') ?>>
          <?= veldfout($fouten, 'straat') ?>
        </div>
        <div class="form__row">
          <div class="field">
            <label for="postcode">Postcode *</label>
            <input id="postcode" name="postcode" required autocomplete="postal-code" value="<?= e($waarden['postcode']) ?>"<?= aria_fout($fouten, 'postcode') ?>>
            <?= veldfout($fouten, 'postcode') ?>
          </div>
          <div class="field">
            <label for="plaats">Woonplaats *</label>
            <input id="plaats" name="plaats" required autocomplete="address-level2" value="<?= e($waarden['plaats']) ?>"<?= aria_fout($fouten, 'plaats') ?>>
            <?= veldfout($fouten, 'plaats') ?>
          </div>
        </div>
      </fieldset>

      <fieldset data-kinderen data-max="<?= MAX_KINDEREN_AANMELDEN ?>">
        <legend class="legend-title">Je kind of kinderen</legend>
        <div class="repeat-list" data-kinderen-lijst>
<?php foreach ($kinderen as $i => $kind): ?>
<?= kind_velden($i, $kind, $fouten) ?>
<?php endforeach; ?>
        </div>
        <template><?= kind_velden('__INDEX__', ['voornaam' => '', 'achternaam' => '', 'geboortedatum' => '', 'school' => '', 'bijzonderheden' => '', 'foto_toestemming' => ''], []) ?></template>
        <button class="btn btn--secondary btn--small" type="button" data-kind-toevoegen hidden><?= icoon('plus') ?>Nog een kind aanmelden</button>
      </fieldset>

      <fieldset>
        <legend class="legend-title">Opvang</legend>
        <fieldset>
          <legend class="legend-label">Op welke dagen denk je opvang nodig te hebben? <span class="field__hint">(optioneel)</span></legend>
          <div class="choices">
<?php foreach ([1, 2, 3, 4, 5] as $dag): ?>
            <label class="choice"><input type="checkbox" name="dagen[]" value="<?= $dag ?>"<?= in_array($dag, $waarden['dagen'], true) ? ' checked' : '' ?>><?= e(ucfirst(WEEKDAGEN[$dag])) ?></label>
<?php endforeach; ?>
          </div>
          <p class="muted" style="margin: 0;">Na de kennismaking kies je de dagen zelf in de agenda.</p>
        </fieldset>
        <div class="form__row">
          <div class="field">
            <label for="startdatum">Gewenste startdatum <span class="field__hint">(optioneel)</span></label>
            <input id="startdatum" name="startdatum" type="date" min="<?= vandaag() ?>" value="<?= e($waarden['startdatum']) ?>"<?= aria_fout($fouten, 'startdatum') ?>>
            <?= veldfout($fouten, 'startdatum') ?>
          </div>
        </div>
        <div class="field">
          <label for="opmerkingen">Opmerkingen of vragen <span class="field__hint">(optioneel)</span></label>
          <textarea id="opmerkingen" name="opmerkingen" rows="4"><?= e($waarden['opmerkingen']) ?></textarea>
        </div>
      </fieldset>

      <fieldset>
        <legend class="legend-title">Je account</legend>
        <div class="form__row">
          <div class="field">
            <label for="wachtwoord">Kies een wachtwoord * <span class="field__hint">(minstens <?= MIN_WACHTWOORD_LENGTE ?> tekens)</span></label>
            <input id="wachtwoord" name="wachtwoord" type="password" required autocomplete="new-password" minlength="<?= MIN_WACHTWOORD_LENGTE ?>"<?= aria_fout($fouten, 'wachtwoord') ?>>
            <?= veldfout($fouten, 'wachtwoord') ?>
          </div>
          <div class="field">
            <label for="wachtwoord2">Wachtwoord nog een keer *</label>
            <input id="wachtwoord2" name="wachtwoord2" type="password" required autocomplete="new-password">
          </div>
        </div>
      </fieldset>

      <fieldset>
        <legend class="legend-title">Betalen en akkoord</legend>
        <div class="note"><?= icoon('bank') ?><p>Betalen gaat automatisch: je facturen worden elke maand afgeschreven van je rekening. Na het aanmelden geef je daarvoor een machtiging af via je eigen bank (iDEAL, eenmalig € 0,01). Je facturen vind je altijd terug in je account.</p></div>
        <div class="hp" aria-hidden="true">
          <label for="website">Laat dit veld leeg</label>
          <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>
        <div class="field">
          <div class="consent">
            <input id="incasso" name="incasso" type="checkbox" value="1" required<?= !empty($_POST['incasso']) ? ' checked' : '' ?><?= aria_fout($fouten, 'incasso') ?>>
            <label for="incasso">Ik geef na het aanmelden een machtiging aan <?= e(instelling('statutaire_naam', 'Je Dag in Beeld')) ?> (BSO VCK) om de facturen voor de opvang automatisch af te schrijven. *</label>
          </div>
          <?= veldfout($fouten, 'incasso') ?>
        </div>
        <div class="field">
          <div class="consent">
            <input id="akkoord" name="akkoord" type="checkbox" value="1" required<?= !empty($_POST['akkoord']) ? ' checked' : '' ?><?= aria_fout($fouten, 'akkoord') ?>>
            <label for="akkoord">Ik heb de <a href="../privacy.html" target="_blank" rel="noopener">privacyverklaring</a> en de <a href="../voorwaarden.html" target="_blank" rel="noopener">algemene voorwaarden</a> gelezen en ga ermee akkoord. *</label>
          </div>
          <?= veldfout($fouten, 'akkoord') ?>
        </div>
        <p class="form__privacy">Een aanmelding is nog geen plaatsing. We nemen eerst contact met je op voor een kennismaking. Daarna zetten we je kind in een groep en kies je de dagen.</p>
      </fieldset>

      <div class="form__acties">
        <button class="btn" type="submit">Account aanmaken en aanmelden<?= icoon('arrow', 'icon--arrow') ?></button>
      </div>
    </form>
  </div>
</div>
<?php
pagina_einde();
