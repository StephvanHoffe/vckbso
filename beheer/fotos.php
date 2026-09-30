<?php
/*
 * Foto's. Begeleiders sturen foto's naar de ouders van de kinderen die erop
 * staan. Ouders zien alleen foto's waar hun eigen kind aan gekoppeld is.
 */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_login();

/* ---------- Ouder ---------- */
if (is_ouder($gebruiker)) {
    $kinderen = rijen('SELECT * FROM kinderen WHERE ouder_id = ? ORDER BY voornaam', [$gebruiker['id']]);
    $filter = get_int('kind');
    $ids = array_map(fn ($k) => (int) $k['id'], $kinderen);
    if ($filter && in_array($filter, $ids, true)) {
        $ids = [$filter];
    }
    $fotos = fotos_van($ids, 120);

    pagina_begin("Foto's", 'fotos.php');
    pagina_kop("Foto's", 'Leuke momenten van de BSO, gedeeld door de begeleiders. Alleen jij ziet de foto\'s van jouw kind.');
    if (count($kinderen) > 1) {
        echo '<ul class="kind-tabs" aria-label="Filter op kind"><li><a href="fotos.php"' . (!$filter ? ' aria-current="true"' : '') . '>Allemaal</a></li>';
        foreach ($kinderen as $k) {
            echo '<li><a href="fotos.php?kind=' . (int) $k['id'] . '"' . ($filter === (int) $k['id'] ? ' aria-current="true"' : '') . '>' . e($k['voornaam']) . '</a></li>';
        }
        echo '</ul>';
    }
    echo '<section class="panel" aria-label="Foto\'s">';
    if (!$fotos) {
        echo '<p class="leeg">Nog geen foto\'s. Zodra de begeleiders een foto delen, zie je hem hier.</p>';
    } else {
        echo '<ul class="fotogrid">';
        foreach ($fotos as $foto) {
            echo foto_kaart($foto);
        }
        echo '</ul>';
    }
    echo '</section>';
    pagina_einde();
    exit;
}

/* ---------- Team ---------- */

$fouten = [];
$bijschrift = '';
$gekozen = [];

if (is_post()) {
    // Groter dan post_max_size van de server? Dan komt er niets aan, ook de beveiligingscode niet
    if (!$_POST && !$_FILES && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('fout', "De upload was te groot voor de server. Kies minder foto's tegelijk.");
        redirect('fotos.php');
    }
    csrf_controleer();

    if (invoer('actie') === 'verwijderen') {
        $foto = rij('SELECT * FROM fotos WHERE id = ?', [(int) invoer('foto')]);
        if ($foto) {
            verwijder_fotobestanden($foto['bestand']);
            q('DELETE FROM fotos WHERE id = ?', [$foto['id']]);
            log_actie('Foto verwijderd', (string) $foto['id']);
            flash('succes', 'De foto is verwijderd.');
        }
        redirect(veilig_terug(invoer('terug', 'fotos.php')));
    }

    $bijschrift = mb_substr(invoer('bijschrift'), 0, 300);
    $gekozen = array_values(array_unique(array_map('intval', (array) ($_POST['kinderen'] ?? []))));
    $kinderen = $gekozen ? rijen('SELECT * FROM kinderen WHERE actief = 1 AND id IN (' . implode(',', $gekozen) . ')') : [];

    // Bestanden uit $_FILES['fotos'] omzetten naar een lijst per bestand
    $uploads = [];
    if (!empty($_FILES['fotos']['name']) && is_array($_FILES['fotos']['name'])) {
        foreach ($_FILES['fotos']['name'] as $i => $naam) {
            if (($_FILES['fotos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $uploads[] = [
                'name' => (string) $naam,
                'tmp_name' => $_FILES['fotos']['tmp_name'][$i],
                'error' => $_FILES['fotos']['error'][$i],
                'size' => $_FILES['fotos']['size'][$i],
            ];
        }
    }

    if (!$uploads) {
        $fouten['fotos'] = 'Kies minstens één foto.';
    }
    if (!$kinderen) {
        $fouten['kinderen'] = 'Kies de kinderen die op de foto staan. Alleen hun ouders krijgen de foto.';
    } elseif (count($kinderen) > 1) {
        $zonder = array_filter($kinderen, fn ($k) => !$k['foto_toestemming']);
        if ($zonder) {
            $fouten['kinderen'] = 'Deze kinderen mogen niet op groepsfoto\'s die naar andere ouders gaan: ' . implode(', ', array_map('kindnaam', $zonder)) . '. Deel een foto van hen alleen los, met alleen dat kind gekozen.';
        }
    }

    if (!$fouten) {
        $gelukt = 0;
        $mislukt = [];
        foreach ($uploads as $upload) {
            try {
                [$bestand, $b, $h] = bewaar_foto($upload);
                transactie(function () use ($bestand, $b, $h, $bijschrift, $gebruiker, $kinderen) {
                    q('INSERT INTO fotos (bestand, breedte, hoogte, bijschrift, geupload_door, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?)', [$bestand, $b, $h, $bijschrift, $gebruiker['id'], nu()]);
                    $fotoId = laatste_id();
                    foreach ($kinderen as $kind) {
                        q('INSERT INTO foto_kinderen (foto_id, kind_id) VALUES (?, ?)', [$fotoId, $kind['id']]);
                    }
                });
                $gelukt++;
            } catch (RuntimeException $fout) {
                $mislukt[] = mb_substr($upload['name'], 0, 60) . ' ' . $fout->getMessage();
            }
        }
        if ($gelukt) {
            // Eén berichtje per gezin, niet per foto
            $perOuder = [];
            foreach ($kinderen as $kind) {
                $perOuder[$kind['ouder_id']][] = $kind['voornaam'];
            }
            foreach ($perOuder as $ouderId => $namen) {
                systeembericht((int) $ouderId, ($gelukt === 1 ? 'Er staat een nieuwe foto' : "Er staan {$gelukt} nieuwe foto's") . ' van ' . implode(' en ', $namen) . " voor je klaar bij Foto's.");
            }
            log_actie("Foto's gedeeld", "{$gelukt} foto('s) met " . count($perOuder) . ' gezin(nen)');
            flash('succes', ($gelukt === 1 ? '1 foto is' : "{$gelukt} foto's zijn") . ' gedeeld met de ouders van ' . implode(', ', array_map(fn ($k) => $k['voornaam'], $kinderen)) . '.');
        }
        if ($mislukt) {
            flash('fout', 'Niet gelukt: ' . implode('; ', $mislukt));
        }
        redirect('fotos.php');
    }
}

$groepen = rijen('SELECT * FROM groepen WHERE actief = 1 ORDER BY naam');
$perGroep = [];
foreach (rijen("SELECT k.* FROM kinderen k JOIN gebruikers g ON g.id = k.ouder_id WHERE k.actief = 1 AND g.status != 'gestopt' ORDER BY k.voornaam") as $kind) {
    $perGroep[$kind['groep_id'] ?? 0][] = $kind;
}
$recent = rijen(
    "SELECT f.*, (SELECT GROUP_CONCAT(k.voornaam, ', ') FROM foto_kinderen fk JOIN kinderen k ON k.id = fk.kind_id WHERE fk.foto_id = f.id) AS kinderen
     FROM fotos f ORDER BY f.aangemaakt_op DESC, f.id DESC LIMIT 24"
);

pagina_begin("Foto's", 'fotos.php');
pagina_kop("Foto's delen", 'Kies een of meer foto\'s en de kinderen die erop staan. Alleen hun ouders krijgen de foto\'s te zien.');
?>
<div class="kolommen">
  <section class="panel" aria-labelledby="upload-titel">
    <h2 id="upload-titel">Nieuwe foto's</h2>
    <?= foutensamenvatting($fouten) ?>
    <form class="form" method="post" action="fotos.php" enctype="multipart/form-data">
      <?= csrf_veld() ?>
      <div class="field">
        <label for="fotos">Foto's <span class="field__hint">(JPG, PNG of WebP, maximaal 15 MB per foto)</span></label>
        <input id="fotos" name="fotos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required data-teller="fotos-teller"<?= aria_fout($fouten, 'fotos') ?>>
        <p class="field__hint" id="fotos-teller" aria-live="polite" style="margin: 0.4rem 0 0"></p>
        <?= veldfout($fouten, 'fotos') ?>
      </div>
      <fieldset class="kindkeuze"<?= !empty($fouten['kinderen']) ? ' aria-describedby="kinderen-fout"' : '' ?>>
        <legend class="legend-label">Wie staan erop?</legend>
        <?= veldfout($fouten, 'kinderen') ?>
<?php if (!$perGroep): ?>
        <p class="leeg">Er zijn nog geen kinderen.</p>
<?php endif; ?>
<?php foreach (array_merge($groepen, isset($perGroep[0]) ? [['id' => 0, 'naam' => 'Nog geen groep']] : []) as $groep):
    if (empty($perGroep[$groep['id']])) {
        continue;
    }
    $lijstId = 'groep-kinderen-' . (int) $groep['id'];
    ?>
        <fieldset>
          <legend class="legend-label" style="font-weight: 600"><?= e($groep['naam']) ?> <button class="link-button" type="button" data-alles-aan="<?= $lijstId ?>" hidden style="min-height: 0; font-size: var(--step--1)">alles aan/uit</button></legend>
          <div class="choices" id="<?= $lijstId ?>">
<?php foreach ($perGroep[$groep['id']] as $kind): ?>
            <label class="choice<?= $kind['foto_toestemming'] ? '' : ' choice--uit' ?>"><input type="checkbox" name="kinderen[]" value="<?= (int) $kind['id'] ?>"<?= in_array((int) $kind['id'], $gekozen, true) ? ' checked' : '' ?>><?= e(kindnaam($kind)) ?><?= $kind['foto_toestemming'] ? '' : '<span class="visually-hidden"> (alleen losse foto\'s)</span>' ?></label>
<?php endforeach; ?>
          </div>
        </fieldset>
<?php endforeach; ?>
        <p class="field__hint" style="margin: 0">Een gestippelde rand betekent: geen toestemming voor groepsfoto's. Van die kinderen deel je alleen foto's waar ze alleen op staan.</p>
      </fieldset>
      <div class="field">
        <label for="bijschrift">Bijschrift <span class="field__hint">(optioneel)</span></label>
        <input id="bijschrift" name="bijschrift" maxlength="300" value="<?= e($bijschrift) ?>" placeholder="Bijvoorbeeld: Wat een doelpunten vandaag!">
      </div>
      <div class="form__acties"><button class="btn" type="submit"><?= icoon('send') ?>Delen met de ouders</button></div>
    </form>
  </section>
  <section class="panel" aria-labelledby="recent-titel">
    <h2 id="recent-titel">Onlangs gedeeld</h2>
<?php if (!$recent): ?>
    <p class="leeg">Nog geen foto's gedeeld.</p>
<?php else: ?>
    <ul class="fotogrid"><?php foreach ($recent as $foto) { echo foto_kaart($foto, true); } ?></ul>
<?php endif; ?>
  </section>
</div>
<?php
pagina_einde();
