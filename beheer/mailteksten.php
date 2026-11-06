<?php
/*
 * E-mails aanpassen (Instellingen → E-mails). Voor teamleden met het recht "Instellingen".
 * Per e-mail: onderwerp en tekst aanpassen, met invulvelden zoals {voornaam}; een voorbeeld bekijken met
 * voorbeeldgegevens; een testmail naar jezelf sturen; en terug naar de standaardtekst.
 * De standaardteksten en de invulvelden staan in inc/mailteksten.php.
 */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_recht('instellingen');
$sleutel = get_str('sleutel');
$def = $sleutel !== '' ? (MAILTEKSTEN[$sleutel] ?? null) : null;
if ($sleutel !== '' && !$def) {
    niet_gevonden('Deze e-mail bestaat niet.');
}
$fouten = [];
$voorbeeld = null;

if (is_post() && $def) {
    csrf_controleer();
    $actie = invoer('actie');
    $hier = 'mailteksten.php?sleutel=' . rawurlencode($sleutel);

    if ($actie === 'standaard') {
        q('DELETE FROM mail_teksten WHERE sleutel = ?', [$sleutel]);
        mailtekst_vernieuw();
        log_actie('E-mailtekst teruggezet naar de standaard', $def['naam']);
        flash('succes', 'De e-mail "' . $def['naam'] . '" gebruikt weer de standaardtekst.');
        redirect($hier);
    }

    $onderwerp = $def['onderwerp'] === null ? null : trim((string) preg_replace('/[\r\n\t]+/', ' ', mb_substr(invoer('onderwerp'), 0, 200)));
    $tekst = str_replace("\r\n", "\n", mb_substr(invoer('tekst'), 0, 10000));
    $fouten = mailtekst_controleer($sleutel, (string) $onderwerp, $tekst);

    if (!$fouten && $actie === 'opslaan') {
        if ($onderwerp === $def['onderwerp'] && $tekst === $def['tekst']) {
            q('DELETE FROM mail_teksten WHERE sleutel = ?', [$sleutel]);
        } else {
            q('INSERT INTO mail_teksten (sleutel, onderwerp, tekst, gewijzigd_op, gewijzigd_door) VALUES (?, ?, ?, ?, ?)
               ON CONFLICT(sleutel) DO UPDATE SET onderwerp = excluded.onderwerp, tekst = excluded.tekst, gewijzigd_op = excluded.gewijzigd_op, gewijzigd_door = excluded.gewijzigd_door',
                [$sleutel, $onderwerp, $tekst, nu(), (int) $gebruiker['id']]);
        }
        mailtekst_vernieuw();
        log_actie('E-mailtekst aangepast', $def['naam']);
        flash('succes', 'De e-mail "' . $def['naam'] . '" is opgeslagen. Vanaf nu gaat hij met deze tekst.');
        redirect($hier);
    }

    // Voorbeeld of testmail: met de tekst uit het formulier, nog niet opgeslagen
    if (!$fouten && in_array($actie, ['voorbeeld', 'test'], true)) {
        $waarden = mailtekst_voorbeeldwaarden($sleutel);
        $voorbeeld = [mailtekst_vul((string) $onderwerp, $waarden), mailtekst_vul($tekst, $waarden)];
        if ($actie === 'test') {
            $gelukt = stuur_mail((string) $gebruiker['email'], '[Test] ' . ($voorbeeld[0] !== '' ? $voorbeeld[0] : $def['naam']), $voorbeeld[1]);
            log_actie('Testmail verstuurd', $def['naam']);
            flash($gelukt ? 'succes' : 'fout', $gelukt
                ? 'De testmail is verstuurd naar ' . $gebruiker['email'] . ', met voorbeeldgegevens. Vergeet niet op te slaan als de tekst goed is.'
                : 'De testmail kon niet worden verstuurd. Controleer de e-mailinstellingen van de server.');
        }
    }
    $huidig = ['onderwerp' => $onderwerp, 'tekst' => $tekst];
} elseif ($def) {
    [$onderwerp, $tekst] = [mailtekst_eigen($sleutel)['onderwerp'] ?? $def['onderwerp'], mailtekst_eigen($sleutel)['tekst'] ?? $def['tekst']];
    $huidig = ['onderwerp' => $onderwerp, 'tekst' => $tekst];
}

/* ---------- Eén e-mail aanpassen ---------- */

if ($def) {
    $eigen = mailtekst_eigen($sleutel);
    $door = $eigen && $eigen['gewijzigd_door'] ? rij('SELECT naam FROM gebruikers WHERE id = ?', [$eigen['gewijzigd_door']]) : null;
    pagina_begin($def['naam'] . ' · E-mails', 'instellingen.php');
    echo '<a class="terug-link" href="mailteksten.php">' . icoon('back') . 'Alle e-mails</a>';
    pagina_kop($def['naam'], e($def['wanneer']));
    ?>
<?= foutensamenvatting($fouten) ?>
<div class="kolommen">
  <section class="panel" aria-labelledby="tekst-titel">
    <div class="panel__kop"><h2 id="tekst-titel">Tekst</h2><?= $eigen ? '<span class="badge badge--actief">Aangepast</span>' : '<span class="badge">Standaardtekst</span>' ?></div>
<?php if ($eigen): ?>
    <p class="muted">Laatst aangepast <?= e(datum_nl($eigen['gewijzigd_op'], "d MMMM y 'om' HH:mm")) ?><?= $door ? ' door ' . e($door['naam']) : '' ?>.</p>
<?php endif; ?>
    <form class="form" method="post" action="mailteksten.php?sleutel=<?= e(rawurlencode($sleutel)) ?>">
      <?= csrf_veld() ?>
<?php if ($def['onderwerp'] !== null): ?>
      <div class="field"><label for="onderwerp">Onderwerp</label><input id="onderwerp" name="onderwerp" maxlength="200" required value="<?= e((string) $huidig['onderwerp']) ?>"<?= aria_fout($fouten, 'onderwerp') ?>><?= veldfout($fouten, 'onderwerp') ?></div>
<?php endif; ?>
      <div class="field">
        <label for="tekst">Tekst van de e-mail</label>
        <textarea id="tekst" name="tekst" rows="14" maxlength="10000" required class="mailtekst__invoer" aria-describedby="tekst-uitleg<?= isset($fouten['tekst']) ? ' tekst-fout' : '' ?>"<?= isset($fouten['tekst']) ? ' aria-invalid="true"' : '' ?>><?= e((string) $huidig['tekst']) ?></textarea>
        <?= veldfout($fouten, 'tekst') ?>
        <p class="field__hint" id="tekst-uitleg">Gewone tekst, zonder opmaak. Een witregel maak je met een lege regel. <?= $sleutel !== 'ondertekening' ? 'Onder elke e-mail komt vanzelf de <a href="mailteksten.php?sleutel=ondertekening">ondertekening</a>.' : '' ?></p>
      </div>
      <div class="form__acties">
        <button class="btn" type="submit" name="actie" value="opslaan"><?= icoon('check') ?>Opslaan</button>
        <button class="btn btn--secondary" type="submit" name="actie" value="voorbeeld">Voorbeeld bekijken</button>
        <button class="btn btn--secondary" type="submit" name="actie" value="test"><?= icoon('send') ?>Testmail naar mij</button>
      </div>
    </form>
<?php if ($eigen): ?>
    <form class="inline-form" method="post" action="mailteksten.php?sleutel=<?= e(rawurlencode($sleutel)) ?>" data-bevestig="Je eigen tekst weggooien en de standaardtekst weer gebruiken?" style="margin-top: var(--space-m)">
      <?= csrf_veld() ?><input type="hidden" name="actie" value="standaard">
      <button class="link-button" type="submit"><?= icoon('back') ?>Terug naar de standaardtekst</button>
    </form>
<?php endif; ?>
  </section>
  <div class="stapel">
    <section class="panel" aria-labelledby="velden-titel">
      <h2 id="velden-titel">Invulvelden</h2>
      <p class="muted">Zet een invulveld met de accolades in de tekst, dan vult Mijn BSO het bij het versturen in. Velden met een * moeten in de tekst blijven staan.</p>
      <dl class="gegevens mailtekst__velden">
<?php foreach ($def['velden'] as $veld => [$uitleg, $voorbeeldwaarde]): ?>
        <dt><code>{<?= e($veld) ?>}</code><?= in_array($veld, $def['verplicht'], true) ? ' *' : '' ?></dt>
        <dd><?= e($uitleg) ?><br><span class="muted">Bijvoorbeeld: <?= e($voorbeeldwaarde) ?></span></dd>
<?php endforeach; ?>
      </dl>
    </section>
<?php if ($voorbeeld): ?>
    <section class="panel" aria-labelledby="voorbeeld-titel" id="voorbeeld" tabindex="-1" data-focus>
      <h2 id="voorbeeld-titel">Voorbeeld</h2>
      <p class="muted">Met voorbeeldgegevens. Nog niet opgeslagen.</p>
<?php if ($def['onderwerp'] !== null): ?>
      <p><strong>Onderwerp:</strong> <?= e($voorbeeld[0]) ?></p>
<?php endif; ?>
      <pre class="mailtekst__voorbeeld"><?= e($voorbeeld[1]) ?><?php if ($sleutel !== 'ondertekening'): ?>


<?= e(mailtekst('ondertekening', ['bedrijfsnaam' => instelling('bedrijfsnaam', 'Sporty'), 'website' => basis_url() . '/'])[1]) ?><?php endif; ?></pre>
    </section>
<?php endif; ?>
    <p class="note"><?= icoon('info') ?><span>Zet in e-mails geen gevoelige gegevens over kinderen, zoals allergieën of gezondheid. Een e-mail kan in verkeerde handen komen; zulke informatie staat veilig in Mijn BSO.</span></p>
  </div>
</div>
<?php
    pagina_einde();
    exit;
}

/* ---------- Overzicht van alle e-mails ---------- */

pagina_begin('E-mails', 'instellingen.php');
echo '<a class="terug-link" href="instellingen.php">' . icoon('back') . 'Instellingen</a>';
pagina_kop('E-mails', 'Alle e-mails die Mijn BSO verstuurt. Pas de tekst aan zodat hij klinkt zoals jullie dat willen; de standaardtekst kun je altijd terugzetten.');
foreach (MAILTEKST_GROEPEN as $groep => [$titel, $uitleg]):
    $mails = array_filter(MAILTEKSTEN, fn ($m) => $m['groep'] === $groep);
    ?>
<section class="panel" aria-labelledby="groep-<?= e($groep) ?>" style="margin-bottom: var(--space-l)">
  <h2 id="groep-<?= e($groep) ?>"><?= e($titel) ?></h2>
  <p class="muted"><?= e($uitleg) ?></p>
  <div class="tabel">
    <table>
      <thead><tr><th scope="col">E-mail</th><th scope="col">Wanneer</th><th scope="col">Tekst</th></tr></thead>
      <tbody>
<?php foreach ($mails as $s => $m): $eigen = mailtekst_eigen($s); ?>
        <tr>
          <th scope="row"><a href="mailteksten.php?sleutel=<?= e(rawurlencode($s)) ?>"><?= e($m['naam']) ?></a></th>
          <td><?= e($m['wanneer']) ?></td>
          <td><?= $eigen ? '<span class="badge badge--actief">Aangepast</span><br><small class="muted">' . e(datum_nl($eigen['gewijzigd_op'])) . '</small>' : '<span class="badge">Standaard</span>' ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endforeach;
pagina_einde();
