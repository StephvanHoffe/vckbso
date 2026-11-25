<?php
/*
 * Verzonden e-mails (E-mails → Verzonden). Voor teamleden met het recht "E-mails".
 * Per e-mail: wanneer, aan wie, welke e-mail, en of hij is verstuurd, mislukt (met de reden), alleen in
 * testmodus stond, of bewust is overgeslagen. De inhoud bewaren we niet: daar staan links in om een
 * wachtwoord te kiezen. De tekst van elke e-mail zie je via de naam (E-mails aanpassen).
 * Wordt bewaard zolang de bewaartermijn onder Instellingen zegt (standaard 3 maanden).
 */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_recht('emails');
const VERZONDEN_PER_PAGINA = 50;

$status = get_str('status');
$status = isset(MAIL_STATUSSEN[$status]) ? $status : 'alle';
$zoek = mb_substr(get_str('zoek'), 0, 100);
$soort = get_str('soort');
$soort = isset(MAILTEKSTEN[$soort]) || $soort === 'test' ? $soort : '';
$pagina = max(1, (int) get_str('pagina', '1'));

$waar = [];
$params = [];
if ($status !== 'alle') {
    $waar[] = 'status = ?';
    $params[] = $status;
}
if ($soort !== '') {
    $waar[] = 'sleutel = ?';
    $params[] = $soort;
}
if ($zoek !== '') {
    $patroon = '%' . strtr($zoek, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
    $waar[] = "(aan LIKE ? ESCAPE '\\' OR onderwerp LIKE ? ESCAPE '\\')";
    array_push($params, $patroon, $patroon);
}
$sqlWaar = $waar ? ' WHERE ' . implode(' AND ', $waar) : '';
$totaal = (int) waarde('SELECT COUNT(*) FROM mail_log' . $sqlWaar, $params);
$rijen = rijen('SELECT * FROM mail_log' . $sqlWaar . ' ORDER BY id DESC LIMIT ' . VERZONDEN_PER_PAGINA . ' OFFSET ' . (($pagina - 1) * VERZONDEN_PER_PAGINA), $params);
$aantallen = [];
foreach (rijen('SELECT status, COUNT(*) AS aantal FROM mail_log GROUP BY status') as $rij) {
    $aantallen[$rij['status']] = (int) $rij['aantal'];
}

/** De naam van de e-mail (met een link naar de tekst), of "Testmail". */
function verzonden_naam(string $sleutel): string
{
    if (isset(MAILTEKSTEN[$sleutel])) {
        return '<a href="mailteksten.php?sleutel=' . e(rawurlencode($sleutel)) . '">' . e(MAILTEKSTEN[$sleutel]['naam']) . '</a>';
    }
    return $sleutel === 'test' ? 'Testmail' : 'Overige e-mail';
}

function verzonden_url(array $wijzig): string
{
    global $status, $zoek, $soort;
    $q = array_filter(array_merge(['status' => $status !== 'alle' ? $status : '', 'soort' => $soort, 'zoek' => $zoek], $wijzig), fn ($w) => $w !== '' && $w !== null && $w !== 1);
    return 'verzonden-emails.php' . ($q ? '?' . http_build_query($q) : '');
}

$methode = mail_methode();
$methodeTekst = ['log' => 'Testmodus: er gaat niets de deur uit', 'mail' => 'Via de mailfunctie van de webserver', 'smtp' => 'Via de eigen mailserver (' . (string) koppeling('smtp.host') . ')'][$methode];

pagina_begin('Verzonden e-mails', 'mailteksten.php');
pagina_kop('Verzonden e-mails', 'Elke e-mail die Mijn BSO verstuurt, met de uitkomst: verstuurd, mislukt (met de reden), alleen in testmodus, of overgeslagen. De inhoud wordt niet bewaard; die zie je bij de tekst van de e-mail. Wordt ' . bewaartermijn('bewaar_maillog_maanden') . ' maanden bewaard.',
    '<a class="btn btn--secondary btn--small" href="mailteksten.php">' . icoon('mail') . 'Teksten aanpassen</a>');
echo mail_waarschuwing_html($gebruiker);
?>
<p class="muted">Versturen: <strong><?= e($methodeTekst) ?></strong><?= team_mag('koppelingen', $gebruiker) ? ' · <a href="koppelingen.php#email">Instellen bij Koppelingen</a>' : '' ?></p>
<section class="panel" aria-labelledby="lijst-titel">
  <h2 id="lijst-titel" class="visually-hidden">Lijst met e-mails</h2>
  <ul class="kind-tabs" aria-label="Filter op uitkomst">
    <li><a href="<?= e(verzonden_url(['status' => '', 'pagina' => null])) ?>"<?= $status === 'alle' ? ' aria-current="true"' : '' ?>>Alle (<?= array_sum($aantallen) ?>)</a></li>
<?php foreach (MAIL_STATUSSEN as $sleutel => [$label]): ?>
    <li><a href="<?= e(verzonden_url(['status' => $sleutel, 'pagina' => null])) ?>"<?= $status === $sleutel ? ' aria-current="true"' : '' ?>><?= e($sleutel === 'testmodus' ? 'Testmodus' : $label) ?> (<?= $aantallen[$sleutel] ?? 0 ?>)</a></li>
<?php endforeach; ?>
  </ul>
  <form class="filters" method="get">
<?php if ($status !== 'alle'): ?>
    <input type="hidden" name="status" value="<?= e($status) ?>">
<?php endif; ?>
<?php if ($soort !== ''): ?>
    <input type="hidden" name="soort" value="<?= e($soort) ?>">
<?php endif; ?>
    <div class="field"><label for="zoek">Zoeken</label><input id="zoek" name="zoek" type="search" value="<?= e($zoek) ?>" placeholder="E-mailadres of onderwerp"></div>
    <button class="btn btn--secondary btn--small" type="submit">Zoek</button>
  </form>
<?php if ($soort !== '' || $zoek !== ''): ?>
  <p class="muted">Alleen <?= $soort !== '' ? 'de e-mail "' . e($soort === 'test' ? 'Testmail' : MAILTEKSTEN[$soort]['naam']) . '"' : '' ?><?= $soort !== '' && $zoek !== '' ? ', met ' : ($zoek !== '' ? 'e-mails met ' : '') ?><?= $zoek !== '' ? '"' . e($zoek) . '"' : '' ?>. <a href="<?= e(verzonden_url(['soort' => '', 'zoek' => '', 'pagina' => null])) ?>">Alles tonen</a></p>
<?php endif; ?>
<?php if (!$rijen): ?>
  <p class="leeg"><?= $zoek !== '' || $status !== 'alle' ? 'Geen e-mails gevonden met deze zoekopdracht.' : 'Er zijn nog geen e-mails verstuurd.' ?></p>
<?php else: ?>
  <div class="tabel">
    <table>
      <thead><tr><th scope="col">Wanneer</th><th scope="col">Aan</th><th scope="col">E-mail</th><th scope="col">Uitkomst</th></tr></thead>
      <tbody>
<?php foreach ($rijen as $rij): [$label, $klasse] = MAIL_STATUSSEN[$rij['status']] ?? [$rij['status'], 'geen']; ?>
        <tr>
          <td style="white-space: nowrap"><?= e(datum_nl($rij['tijd'], 'd MMM')) ?><br><small class="muted"><?= e(substr($rij['tijd'], 11, 5)) ?> uur</small></td>
          <td><a href="<?= e(verzonden_url(['zoek' => $rij['aan'], 'pagina' => null])) ?>" title="Alle e-mails aan dit adres"><?= e($rij['aan']) ?></a></td>
          <td><?= verzonden_naam($rij['sleutel']) ?><br><small class="muted"><?= e($rij['onderwerp']) ?></small></td>
          <td><span class="badge badge--<?= e($klasse) ?>"><?= e($label) ?></span><?php if ($rij['fout'] !== ''): ?><br><small class="muted"><?= e($rij['fout']) ?></small><?php endif; ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php if ($totaal > VERZONDEN_PER_PAGINA): ?>
  <nav class="btn-group" aria-label="Meer e-mails" style="margin-top: var(--space-m)">
<?php if ($pagina > 1): ?>
    <a class="btn btn--secondary btn--small" href="<?= e(verzonden_url(['pagina' => $pagina - 1])) ?>"><?= icoon('back') ?>Nieuwere e-mails</a>
<?php endif; ?>
<?php if ($pagina * VERZONDEN_PER_PAGINA < $totaal): ?>
    <a class="btn btn--secondary btn--small" href="<?= e(verzonden_url(['pagina' => $pagina + 1])) ?>">Oudere e-mails<?= icoon('arrow') ?></a>
<?php endif; ?>
  </nav>
<?php endif; ?>
<?php endif; ?>
</section>
<section class="panel" aria-labelledby="uitleg-titel">
  <h2 id="uitleg-titel">Wat betekent de uitkomst?</h2>
  <dl class="gegevens">
    <dt><span class="badge badge--geldig">Verstuurd</span></dt><dd>De mailserver heeft de e-mail aangenomen. Komt hij niet aan? Laat de ontvanger dan ook in de map met ongewenste e-mail kijken.</dd>
    <dt><span class="badge badge--mislukt">Mislukt</span></dt><dd>De e-mail kon niet worden verstuurd. De reden staat eronder. Meestal klopt er iets niet bij Koppelingen → E-mail versturen, zoals de mailserver of het wachtwoord van de mailbox.</dd>
    <dt><span class="badge badge--demo">Testmodus</span></dt><dd>Mijn BSO staat in testmodus en verstuurt niets. Kies bij Koppelingen → E-mail versturen een mailserver.</dd>
    <dt><span class="badge badge--wachtlijst">Overgeslagen</span></dt><dd>Bewust niet verstuurd, bijvoorbeeld omdat er binnen een kwartier al 3 e-mails naar hetzelfde adres gingen. Dat is de grens tegen misbruik van de formulieren op de website.</dd>
  </dl>
</section>
<?php
pagina_einde();
