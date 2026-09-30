<?php
/* Onderdelen die op meer pagina's terugkomen. */

declare(strict_types=1);

/**
 * Dagoverzicht voor het team: per groep wie er komt, met knoppen voor
 * aanwezig, opgehaald, ziek en afmelden. $terug is de pagina om naar terug te gaan.
 */
function toon_dagoverzicht(string $datum, array $groepen, string $terug): void
{
    if (!$groepen) {
        echo '<div class="panel"><p class="leeg">Er zijn nog geen groepen. ' . (team_mag('groepen') ? '<a href="groepen.php">Maak eerst een groep aan.</a>' : 'Vraag een collega met toegang tot Groepen om groepen aan te maken.') . '</p></div>';
        return;
    }
    foreach ($groepen as $groep) {
        $dag = dagoverzicht($groep, $datum);
        $kleur = 'kleur--' . $groep['kleur'];
        ?>
<section class="panel groepkaart" aria-labelledby="groep-<?= (int) $groep['id'] ?>-titel">
  <div class="panel__kop">
    <h2 id="groep-<?= (int) $groep['id'] ?>-titel"><span class="groep-kleur <?= e($kleur) ?>" aria-hidden="true"></span><?= e($groep['naam']) ?></h2>
<?php if ($dag['open']): ?>
    <p class="badge badge--<?= $dag['bezetting'] >= $dag['capaciteit'] ? 'ziek' : 'bevestigd' ?>"><?= $dag['bezetting'] ?> van <?= $dag['capaciteit'] ?> plekken<?= $dag['wachtlijst'] ? ' · ' . $dag['wachtlijst'] . ' op de wachtlijst' : '' ?></p>
<?php else: ?>
    <p class="badge badge--gesloten">Gesloten</p>
<?php endif; ?>
  </div>
<?php if ($dag['uitzondering'] && $dag['uitzondering']['notitie'] !== ''): ?>
  <p class="note"><?= icoon('info') ?><span><?= e($dag['uitzondering']['notitie']) ?></span></p>
<?php endif; ?>
<?php if (!$dag['kinderen']): ?>
  <p class="leeg"><?= $dag['open'] ? 'Nog geen kinderen ingeschreven voor deze dag.' : 'De groep is vandaag dicht.' ?></p>
<?php else: ?>
  <ul class="kindlijst">
<?php foreach ($dag['kinderen'] as $rij): ?>
    <li class="kindrij kindrij--<?= e($rij['status']) ?>">
      <div>
        <div class="kindrij__naam"><?= link_als('kinderen', 'kind.php?id=' . (int) $rij['kind_id'], e(kindnaam($rij))) ?> <?= status_badge($rij['status']) ?>
<?php if ($rij['aanwezig_om']): ?> <span class="badge badge--bevestigd"><?= icoon('check') ?>Binnen <?= e(tijd_nl($rij['aanwezig_om'])) ?></span><?php endif; ?>
<?php if ($rij['opgehaald_om']): ?> <span class="badge"><?= icoon('home') ?>Opgehaald <?= e(tijd_nl($rij['opgehaald_om'])) ?></span><?php endif; ?>
        </div>
        <div class="kindrij__info">
<?php if (($jaar = leeftijd($rij['geboortedatum'], $datum)) !== null): ?><span><?= $jaar ?> jaar</span><?php endif; ?>
          <span><?= icoon('phone') ?> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $rij['oudertelefoon'])) ?>"><?= e($rij['oudernaam']) ?></a></span>
<?php if ($rij['ophaalpersonen'] !== ''): ?><span>Ophalen: <?= e($rij['ophaalpersonen']) ?></span><?php endif; ?>
        </div>
<?php if ($rij['bijzonderheden'] !== ''): ?>
        <p class="let-op"><?= icoon('alert') ?><span><?= e($rij['bijzonderheden']) ?></span></p>
<?php endif; ?>
      </div>
      <div class="btn-group">
<?= inschrijving_knoppen($rij, $datum, $terug) ?>
      </div>
    </li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php if ($dag['open']): ?>
<?= kind_toevoegen_formulier($groep, $datum, $terug) ?>
<?php endif; ?>
<?= uitzondering_formulier($groep, $datum, $dag, $terug) ?>
</section>
<?php
    }
}

function actieknop(int $inschrijvingId, string $actie, string $label, string $terug, string $klasse = 'btn btn--secondary btn--mini', string $bevestig = ''): string
{
    return '<form class="inline-form" method="post" action="agenda-actie.php"' . ($bevestig ? ' data-bevestig="' . e($bevestig) . '"' : '') . '>'
        . csrf_veld()
        . '<input type="hidden" name="actie" value="' . e($actie) . '">'
        . '<input type="hidden" name="inschrijving" value="' . $inschrijvingId . '">'
        . '<input type="hidden" name="terug" value="' . e($terug) . '">'
        . '<button class="' . e($klasse) . '" type="submit">' . $label . '</button></form>';
}

function inschrijving_knoppen(array $rij, string $datum, string $terug): string
{
    $id = (int) $rij['id'];
    $html = '';
    $naam = e($rij['voornaam']);
    switch ($rij['status']) {
        case 'bevestigd':
            if ($datum === vandaag()) {
                if (!$rij['aanwezig_om']) {
                    $html .= actieknop($id, 'aanwezig', icoon('check') . 'Binnen<span class="visually-hidden"> ' . $naam . '</span>', $terug, 'btn btn--mini');
                } elseif (!$rij['opgehaald_om']) {
                    $html .= actieknop($id, 'opgehaald', icoon('home') . 'Opgehaald<span class="visually-hidden"> ' . $naam . '</span>', $terug, 'btn btn--mini');
                }
            }
            if (!$rij['aanwezig_om']) {
                $html .= actieknop($id, 'ziek', icoon('thermo') . 'Ziek<span class="visually-hidden"> ' . $naam . '</span>', $terug);
                $html .= actieknop($id, 'afmelden', 'Afmelden<span class="visually-hidden"> ' . $naam . '</span>', $terug, 'btn btn--gevaar btn--mini', 'Weet je zeker dat je ' . $rij['voornaam'] . ' wilt afmelden voor deze dag?');
            }
            break;
        case 'wachtlijst':
            $html .= actieknop($id, 'bevestigen', icoon('plus') . 'Toch plaatsen<span class="visually-hidden"> ' . $naam . '</span>', $terug, 'btn btn--mini', 'De groep is vol. Wil je ' . $rij['voornaam'] . ' toch plaatsen, boven het maximum?');
            $html .= actieknop($id, 'afmelden', 'Van wachtlijst<span class="visually-hidden"> ' . $naam . '</span>', $terug, 'btn btn--gevaar btn--mini');
            break;
        case 'ziek':
            $html .= actieknop($id, 'beter', 'Toch gekomen<span class="visually-hidden"> ' . $naam . '</span>', $terug);
            break;
        case 'afgemeld':
            if ($datum >= vandaag()) {
                $html .= actieknop($id, 'herstel', 'Toch aanmelden<span class="visually-hidden"> ' . $naam . '</span>', $terug);
            }
            break;
    }
    return $html;
}

function kind_toevoegen_formulier(array $groep, string $datum, string $terug): string
{
    $kinderen = rijen(
        "SELECT k.* FROM kinderen k JOIN gebruikers g ON g.id = k.ouder_id
         WHERE k.groep_id = ? AND k.actief = 1 AND g.status = 'actief'
           AND NOT EXISTS (SELECT 1 FROM inschrijvingen i WHERE i.kind_id = k.id AND i.datum = ? AND i.status != 'afgemeld')
         ORDER BY k.voornaam",
        [$groep['id'], $datum]
    );
    if (!$kinderen) {
        return '';
    }
    $id = 'toevoegen-' . (int) $groep['id'];
    $html = '<details class="uitklap"><summary>' . icoon('plus') . 'Kind toevoegen aan deze dag</summary>'
        . '<form class="form" method="post" action="agenda-actie.php">' . csrf_veld()
        . '<input type="hidden" name="actie" value="toevoegen"><input type="hidden" name="datum" value="' . e($datum) . '"><input type="hidden" name="terug" value="' . e($terug) . '">'
        . '<div class="field field--klein"><label for="' . $id . '">Kind</label><select id="' . $id . '" name="kind">';
    foreach ($kinderen as $kind) {
        $html .= '<option value="' . (int) $kind['id'] . '">' . e(kindnaam($kind)) . '</option>';
    }
    $html .= '</select></div>'
        . '<div class="field"><div class="consent"><input id="' . $id . '-max" type="checkbox" name="boven_max" value="1"><label for="' . $id . '-max">Ook plaatsen als de groep vol is</label></div></div>'
        . '<div class="form__acties"><button class="btn btn--small" type="submit">Toevoegen</button></div></form></details>';
    return $html;
}

function uitzondering_formulier(array $groep, string $datum, array $dag, string $terug): string
{
    if (!in_array(weekdag($datum), groep_dagen($groep), true) || $datum < vandaag()) {
        return '';
    }
    $u = $dag['uitzondering'];
    $id = 'uitz-' . (int) $groep['id'];
    $html = '<details class="uitklap"><summary>' . icoon('gear') . 'Deze dag aanpassen (sluiten of andere groepsgrootte)</summary>'
        . '<form class="form" method="post" action="agenda-actie.php">' . csrf_veld()
        . '<input type="hidden" name="actie" value="uitzondering"><input type="hidden" name="groep" value="' . (int) $groep['id'] . '"><input type="hidden" name="datum" value="' . e($datum) . '"><input type="hidden" name="terug" value="' . e($terug) . '">'
        . '<div class="field"><div class="consent"><input id="' . $id . '-dicht" type="checkbox" name="gesloten" value="1"' . (!empty($u['gesloten']) ? ' checked' : '') . '><label for="' . $id . '-dicht">Groep is deze dag gesloten (bijvoorbeeld een feestdag)</label></div></div>'
        . '<div class="form__row"><div class="field"><label for="' . $id . '-max">Maximaal aantal kinderen deze dag <span class="field__hint">(normaal ' . (int) $groep['max_kinderen'] . ')</span></label><input id="' . $id . '-max" name="max_kinderen" type="number" min="1" max="200" value="' . e((string) ($u['max_kinderen'] ?? '')) . '"></div>'
        . '<div class="field"><label for="' . $id . '-notitie">Notitie <span class="field__hint">(zien ouders ook)</span></label><input id="' . $id . '-notitie" name="notitie" maxlength="200" value="' . e($u['notitie'] ?? '') . '"></div></div>'
        . '<p class="field__hint" style="margin:0">Sluit je een dag waarop al kinderen komen? Die worden dan afgemeld en hun ouders krijgen een bericht.</p>'
        . '<div class="form__acties"><button class="btn btn--small" type="submit">Opslaan</button>'
        . ($u ? '<button class="btn btn--secondary btn--small" type="submit" name="verwijderen" value="1">Terug naar normaal</button>' : '')
        . '</div></form></details>';
    return $html;
}

/** Keuzelijst met groepen. */
function groep_opties(?int $gekozen, bool $leegToegestaan = true, string $leegLabel = 'Nog geen groep'): string
{
    $html = $leegToegestaan ? '<option value="">' . e($leegLabel) . '</option>' : '';
    foreach (rijen('SELECT * FROM groepen ORDER BY actief DESC, naam') as $groep) {
        // Het team kiest alleen uit de groepen waar het toegang toe heeft
        if (is_team() && !team_mag_groep((int) $groep['id'])) {
            continue;
        }
        $html .= '<option value="' . (int) $groep['id'] . '"' . ((int) $gekozen === (int) $groep['id'] ? ' selected' : '') . '>' . e($groep['naam']) . ($groep['actief'] ? '' : ' (niet actief)') . '</option>';
    }
    return $html;
}

const ONTWIKKELGEBIEDEN = [
    'sociaal' => 'Sociaal-emotioneel',
    'motoriek' => 'Sport en bewegen',
    'taal' => 'Taal en communicatie',
    'zelfstandigheid' => 'Zelfstandigheid',
    'creativiteit' => 'Spel en creativiteit',
    'overig' => 'Overig',
];

/** Kleine foto (thumbnail) met link naar de grote versie. */
function foto_kaart(array $foto, bool $verwijderen = false, string $terug = 'fotos.php'): string
{
    $html = '<li class="fotokaart"><a href="foto.php?id=' . (int) $foto['id'] . '&amp;weergave=1"><img src="foto.php?id=' . (int) $foto['id'] . '&amp;maat=klein" alt="' . e($foto['bijschrift'] !== '' ? $foto['bijschrift'] : 'Foto van ' . datum_nl($foto['aangemaakt_op'], 'd MMMM')) . '" loading="lazy" width="480" height="360"></a>'
        . '<div class="fotokaart__tekst">';
    if ($foto['bijschrift'] !== '') {
        $html .= '<p>' . e($foto['bijschrift']) . '</p>';
    }
    $html .= '<p class="muted">' . e(moment_nl($foto['aangemaakt_op'])) . (!empty($foto['kinderen']) ? ' · ' . e($foto['kinderen']) : '') . '</p>';
    if ($verwijderen) {
        $html .= '<form method="post" action="fotos.php" data-bevestig="Deze foto verwijderen? Ouders zien hem dan ook niet meer.">' . csrf_veld()
            . '<input type="hidden" name="actie" value="verwijderen"><input type="hidden" name="foto" value="' . (int) $foto['id'] . '"><input type="hidden" name="terug" value="' . e($terug) . '">'
            . '<button class="link-button" type="submit">' . icoon('trash') . 'Verwijderen</button></form>';
    }
    return $html . '</div></li>';
}

/** Foto's (met namen van de kinderen), eventueel alleen van bepaalde kinderen. */
function fotos_van(array $kindIds, int $limiet = 60): array
{
    if (!$kindIds) {
        return [];
    }
    $plekken = implode(',', array_fill(0, count($kindIds), '?'));
    return rijen(
        "SELECT f.*, (SELECT GROUP_CONCAT(k.voornaam, ', ') FROM foto_kinderen fk2 JOIN kinderen k ON k.id = fk2.kind_id WHERE fk2.foto_id = f.id AND fk2.kind_id IN ($plekken)) AS kinderen
         FROM fotos f WHERE EXISTS (SELECT 1 FROM foto_kinderen fk WHERE fk.foto_id = f.id AND fk.kind_id IN ($plekken))
         ORDER BY f.aangemaakt_op DESC, f.id DESC LIMIT " . (int) $limiet,
        array_merge($kindIds, $kindIds)
    );
}
