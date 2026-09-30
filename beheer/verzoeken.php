<?php
/*
 * Privacyverzoeken afhandelen (beheerder). Wettelijke termijn: een maand.
 * Per verzoek: controleer of de aanvrager bevoegd is (gezag), maak zo nodig een
 * volledige export, voer correcties of verwijdering uit waar dat wettelijk kan,
 * en rond af met een reactie aan de aanvrager.
 */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_beheerder();
$verzoek = get_int('id') ? rij('SELECT v.*, g.naam AS aanvrager, g.email AS aanvrager_email FROM avg_verzoeken v LEFT JOIN gebruikers g ON g.id = v.gebruiker_id WHERE v.id = ?', [get_int('id')]) : null;
if (get_int('id') && !$verzoek) {
    niet_gevonden();
}

if (is_post() && $verzoek) {
    csrf_controleer();
    $actie = invoer('actie');
    $terug = 'verzoeken.php?id=' . (int) $verzoek['id'];
    $onderwerp = $verzoek['kind_id'] ? 'kind:' . $verzoek['kind_id'] : 'gebruiker:' . $verzoek['gebruiker_id'];
    // Namens een kind: de aanvrager moet (nog steeds) gecontroleerd gezag hebben
    $bevoegd = !$verzoek['kind_id'] || ($verzoek['gebruiker_id'] && mag_namens_kind((int) $verzoek['kind_id'], (int) $verzoek['gebruiker_id']));

    if ($actie === 'behandelen') {
        q("UPDATE avg_verzoeken SET status = 'in_behandeling' WHERE id = ? AND status = 'ontvangen'", [$verzoek['id']]);
        log_actie('Privacyverzoek in behandeling', 'verzoek ' . $verzoek['id'], null, 'avg', $onderwerp);
    } elseif ($actie === 'export' && $bevoegd) {
        $data = $verzoek['kind_id']
            ? export_kind((int) $verzoek['kind_id'], true, (int) $verzoek['gebruiker_id'])
            : export_gebruiker((int) $verzoek['gebruiker_id']);
        export_verwijder($verzoek['export_bestand']);
        q('UPDATE avg_verzoeken SET export_bestand = ?, export_verloopt_op = ? WHERE id = ?', [export_opslaan($data), date('Y-m-d H:i:s', strtotime('+' . EXPORT_GELDIG_DAGEN . ' days')), $verzoek['id']]);
        log_export('Volledige export gemaakt voor privacyverzoek', $onderwerp, 'verzoek ' . $verzoek['id']);
        flash('succes', 'De export is klaar. Controleer hem en rond het verzoek daarna af; de aanvrager kan hem dan downloaden.');
    } elseif ($actie === 'wis_kind' && $bevoegd && $verzoek['kind_id']) {
        wis_kinddossier((int) $verzoek['kind_id'], 'AVG-verzoek ' . $verzoek['id']);
        q('UPDATE kinderen SET actief = 0 WHERE id = ?', [$verzoek['kind_id']]);
        flash('succes', 'Het dossier van het kind is gewist. De financiële administratie (facturen, opvangdagen) blijft bewaard zolang de wet dat vraagt.');
    } elseif ($actie === 'wis_account' && !$verzoek['kind_id'] && $verzoek['gebruiker_id']) {
        $ouder = rij('SELECT * FROM gebruikers WHERE id = ?', [$verzoek['gebruiker_id']]);
        if ($ouder && $ouder['status'] === 'gestopt') {
            wis_oudergegevens($ouder, 'AVG-verzoek ' . $verzoek['id']);
            flash('succes', 'De gegevens zijn gewist. Naam, adres en facturen blijven bewaard vanwege de fiscale bewaarplicht.');
        } else {
            flash('fout', 'Zet de klant eerst op "gestopt" (bij Ouders), dan kun je de gegevens wissen.');
        }
    } elseif (in_array($actie, ['afronden', 'afwijzen'], true)) {
        $reactie = mb_substr(invoer('reactie'), 0, 3000);
        if ($reactie === '') {
            flash('fout', 'Schrijf een reactie aan de aanvrager.');
            redirect($terug);
        }
        $status = $actie === 'afronden' ? 'afgerond' : 'afgewezen';
        q('UPDATE avg_verzoeken SET status = ?, reactie = ?, afgehandeld_door = ?, afgehandeld_op = ? WHERE id = ?', [$status, versleutel($reactie), $gebruiker['id'], nu(), $verzoek['id']]);
        if ($verzoek['gebruiker_id']) {
            systeembericht((int) $verzoek['gebruiker_id'], 'Je privacyverzoek is ' . ($status === 'afgerond' ? 'afgehandeld' : 'beoordeeld') . '. Je leest onze reactie bij "Privacy".');
            if ($verzoek['aanvrager_email']) {
                stuur_mail($verzoek['aanvrager_email'], 'Reactie op je privacyverzoek', "Hoi {$verzoek['aanvrager']},\n\nWe hebben je privacyverzoek behandeld. Je leest onze reactie in Mijn BSO bij Privacy:\n" . app_url('privacy.php'));
            }
        }
        log_actie('Privacyverzoek ' . $status, 'verzoek ' . $verzoek['id'], null, 'avg', $onderwerp);
        flash('succes', 'Het verzoek is ' . $status . '.');
    }
    redirect($terug);
}

pagina_begin('Privacyverzoeken', 'verzoeken.php');

if ($verzoek) {
    $bevoegd = !$verzoek['kind_id'] || ($verzoek['gebruiker_id'] && mag_namens_kind((int) $verzoek['kind_id'], (int) $verzoek['gebruiker_id']));
    $koppeling = $verzoek['kind_id'] && $verzoek['gebruiker_id'] ? verzorger_koppeling((int) $verzoek['kind_id'], (int) $verzoek['gebruiker_id']) : null;
    $telaat = in_array($verzoek['status'], ['ontvangen', 'in_behandeling'], true) && $verzoek['deadline'] < vandaag();
    echo '<a class="terug-link" href="verzoeken.php">' . icoon('back') . 'Alle verzoeken</a>';
    pagina_kop(ucfirst($verzoek['soort']) . ' · ' . $verzoek['over_naam'], verzoek_badge($verzoek['status']) . ($telaat ? ' <span class="badge badge--ziek">Termijn verstreken</span>' : ''));
    ?>
<div class="kolommen">
  <section class="panel" aria-labelledby="verzoek-titel">
    <h2 id="verzoek-titel">Verzoek</h2>
    <dl class="gegevens">
      <dt>Soort</dt><dd><?= e(VERZOEK_SOORTEN[$verzoek['soort']] ?? $verzoek['soort']) ?></dd>
      <dt>Aanvrager</dt><dd><?= $verzoek['gebruiker_id'] ? '<a href="ouder.php?id=' . (int) $verzoek['gebruiker_id'] . '">' . e($verzoek['aanvrager']) . '</a>' : 'Account verwijderd' ?></dd>
      <dt>Over</dt><dd><?= $verzoek['kind_id'] ? 'Kind: <a href="kind.php?id=' . (int) $verzoek['kind_id'] . '">' . e($verzoek['over_naam']) . '</a>' : 'De aanvrager zelf' ?></dd>
      <dt>Ingediend</dt><dd><?= e(moment_nl($verzoek['aangemaakt_op'])) ?></dd>
      <dt>Reageren voor</dt><dd><?= e(datum_nl($verzoek['deadline'])) ?> <span class="muted">(een maand; verlengen met twee maanden mag alleen bij complexe verzoeken, en dat moet je de aanvrager binnen de eerste maand laten weten)</span></dd>
      <dt>Toelichting</dt><dd><?= e(ontsleutel($verzoek['toelichting']) ?: '-') ?></dd>
    </dl>
<?php if ($verzoek['kind_id']): ?>
    <p class="<?= $bevoegd ? 'note' : 'let-op' ?>" style="margin-top: var(--space-m)"><?= icoon($bevoegd ? 'check' : 'alert') ?><span><?= $bevoegd
        ? 'Bevoegd: de aanvrager heeft gezag, gecontroleerd op ' . e(datum_nl($koppeling['gezag_gecontroleerd_op'])) . '.'
        : 'Niet (meer) bevoegd: de aanvrager heeft geen gecontroleerd gezag over dit kind. Controleer eerst het gezag in het dossier van het kind, of wijs het verzoek af.' ?></span></p>
<?php endif; ?>
<?php if ($verzoek['reactie'] !== ''): ?>
    <h3 style="margin-top: var(--space-l)">Reactie</h3>
    <p style="white-space: pre-line"><?= e(ontsleutel($verzoek['reactie'])) ?></p>
<?php endif; ?>
  </section>
<?php if (in_array($verzoek['status'], ['ontvangen', 'in_behandeling'], true)): ?>
  <section class="panel" aria-labelledby="acties-titel">
    <h2 id="acties-titel">Afhandelen</h2>
    <div class="btn-group">
<?php if ($verzoek['status'] === 'ontvangen'): ?>
      <form method="post"><?= csrf_veld() ?><input type="hidden" name="actie" value="behandelen"><button class="btn btn--secondary btn--small" type="submit">In behandeling nemen</button></form>
<?php endif; ?>
<?php if ($bevoegd && in_array($verzoek['soort'], ['inzage', 'overdracht'], true)): ?>
      <form method="post"><?= csrf_veld() ?><input type="hidden" name="actie" value="export"><button class="btn btn--small" type="submit"><?= icoon('download') ?><?= $verzoek['export_bestand'] ? 'Export opnieuw maken' : 'Volledige export maken' ?></button></form>
<?php endif; ?>
<?php if ($verzoek['export_bestand']): ?>
      <a class="btn btn--secondary btn--small" href="export.php?verzoek=<?= (int) $verzoek['id'] ?>">Export controleren</a>
<?php endif; ?>
<?php if ($bevoegd && $verzoek['soort'] === 'verwijdering' && $verzoek['kind_id']): ?>
      <form method="post" data-bevestig="Het dossier van dit kind wissen (observaties, foto's, gegevens, verzorgers)? Dit kan niet ongedaan worden gemaakt. Facturen en opvangdagen blijven bewaard zolang de wet dat vraagt."><?= csrf_veld() ?><input type="hidden" name="actie" value="wis_kind"><button class="btn btn--gevaar btn--small" type="submit"><?= icoon('trash') ?>Kinddossier wissen</button></form>
<?php endif; ?>
<?php if ($verzoek['soort'] === 'verwijdering' && !$verzoek['kind_id']): ?>
      <form method="post" data-bevestig="De gegevens van deze ouder wissen? Naam, adres en facturen blijven bewaard."><?= csrf_veld() ?><input type="hidden" name="actie" value="wis_account"><button class="btn btn--gevaar btn--small" type="submit"><?= icoon('trash') ?>Gegevens ouder wissen</button></form>
<?php endif; ?>
    </div>
    <p class="muted">Correctie of beperking? Pas de gegevens aan in het dossier van het kind of bij de ouder, en leg in je reactie uit wat je hebt gedaan. Verwijderen kan niet voor gegevens die we wettelijk moeten bewaren (zoals de financiële administratie): leg dat dan uit.</p>
    <form class="form" method="post">
      <?= csrf_veld() ?>
      <div class="field"><label for="reactie">Reactie aan de aanvrager</label><textarea id="reactie" name="reactie" rows="5" required></textarea></div>
      <div class="form__acties">
        <button class="btn" type="submit" name="actie" value="afronden">Afronden</button>
        <button class="btn btn--gevaar" type="submit" name="actie" value="afwijzen">Afwijzen</button>
      </div>
    </form>
  </section>
<?php endif; ?>
</div>
<?php
    pagina_einde();
    exit;
}

$verzoeken = rijen("SELECT v.*, g.naam AS aanvrager FROM avg_verzoeken v LEFT JOIN gebruikers g ON g.id = v.gebruiker_id ORDER BY v.status IN ('afgerond', 'afgewezen'), v.deadline, v.id DESC LIMIT 200");
pagina_kop('Privacyverzoeken', 'Verzoeken van ouders over inzage, correctie, verwijdering, beperking, bezwaar of overdracht. Wettelijke termijn: een maand.');
?>
<section class="panel" aria-label="Verzoeken">
<?php if (!$verzoeken): ?>
  <p class="leeg">Nog geen privacyverzoeken.</p>
<?php else: ?>
  <div class="tabel">
    <table>
      <thead><tr><th scope="col">Verzoek</th><th scope="col">Aanvrager</th><th scope="col">Ingediend</th><th scope="col">Uiterlijk</th><th scope="col">Status</th></tr></thead>
      <tbody>
<?php foreach ($verzoeken as $v):
    $telaat = in_array($v['status'], ['ontvangen', 'in_behandeling'], true) && $v['deadline'] < vandaag(); ?>
        <tr>
          <th scope="row"><a href="verzoeken.php?id=<?= (int) $v['id'] ?>"><?= e(ucfirst($v['soort'])) ?> · <?= e($v['over_naam']) ?></a></th>
          <td><?= e($v['aanvrager'] ?? '-') ?></td>
          <td><?= e(datum_nl($v['aangemaakt_op'], 'd MMM y')) ?></td>
          <td><?= e(datum_nl($v['deadline'], 'd MMM y')) ?><?= $telaat ? ' <span class="badge badge--ziek">Te laat</span>' : '' ?></td>
          <td><?= verzoek_badge($v['status']) ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</section>
<?php
pagina_einde();
