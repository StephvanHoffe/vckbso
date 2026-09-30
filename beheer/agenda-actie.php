<?php
/* Acties van het team in de agenda: aanwezig, opgehaald, ziek, afmelden, toevoegen en dagen aanpassen. */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_team();
if (!is_post()) {
    redirect('agenda.php');
}
csrf_controleer();

$terug = veilig_terug(invoer('terug', 'agenda.php'));
$actie = invoer('actie');

if (in_array($actie, ['aanwezig', 'opgehaald', 'ziek', 'afmelden', 'bevestigen', 'beter', 'herstel'], true)) {
    $inschrijving = rij('SELECT i.*, k.voornaam, k.achternaam, k.ouder_id, k.groep_id AS kind_groep, k.actief FROM inschrijvingen i JOIN kinderen k ON k.id = i.kind_id WHERE i.id = ?', [(int) invoer('inschrijving')]);
    if (!$inschrijving) {
        niet_gevonden();
    }
    $naam = $inschrijving['voornaam'];
    $dag = datum_nl($inschrijving['datum'], 'EEEE d MMMM');

    switch ($actie) {
        case 'aanwezig':
            q('UPDATE inschrijvingen SET aanwezig_om = ?, gewijzigd_op = ? WHERE id = ? AND aanwezig_om IS NULL', [nu(), nu(), $inschrijving['id']]);
            flash('succes', "{$naam} is binnen.");
            break;
        case 'opgehaald':
            q('UPDATE inschrijvingen SET opgehaald_om = ?, gewijzigd_op = ? WHERE id = ? AND opgehaald_om IS NULL', [nu(), nu(), $inschrijving['id']]);
            flash('succes', "{$naam} is opgehaald.");
            break;
        case 'ziek':
            meld_af($inschrijving, 'ziek');
            systeembericht((int) $inschrijving['ouder_id'], "{$naam} staat voor {$dag} ziek gemeld. Beterschap!", (int) $inschrijving['kind_id']);
            flash('succes', "{$naam} is ziek gemeld.");
            break;
        case 'afmelden':
            meld_af($inschrijving);
            systeembericht((int) $inschrijving['ouder_id'], "{$naam} is door ons afgemeld voor {$dag}. Vragen? Stuur ons gerust een berichtje.", (int) $inschrijving['kind_id']);
            flash('succes', "{$naam} is afgemeld voor {$dag}.");
            break;
        case 'bevestigen':
            q("UPDATE inschrijvingen SET status = 'bevestigd', gewijzigd_op = ? WHERE id = ? AND status = 'wachtlijst'", [nu(), $inschrijving['id']]);
            systeembericht((int) $inschrijving['ouder_id'], "Goed nieuws: {$naam} kan op {$dag} komen!", (int) $inschrijving['kind_id']);
            flash('succes', "{$naam} is geplaatst.");
            break;
        case 'beter':
            q("UPDATE inschrijvingen SET status = 'bevestigd', gewijzigd_op = ? WHERE id = ? AND status = 'ziek'", [nu(), $inschrijving['id']]);
            flash('succes', "{$naam} staat weer op 'komt'.");
            break;
        case 'herstel':
            $kind = rij('SELECT * FROM kinderen WHERE id = ?', [$inschrijving['kind_id']]);
            $status = $kind ? schrijf_in($kind, $inschrijving['datum'], (int) $gebruiker['id']) : null;
            flash($status ? 'succes' : 'fout', $status ? "{$naam}: " . mb_strtolower(status_label($status)) . '.' : 'Dat lukte niet: de groep is dicht of het kind heeft geen groep.');
            break;
    }
    log_actie('Agenda: ' . $actie, "{$naam} {$inschrijving['datum']}");
    redirect($terug);
}

if ($actie === 'toevoegen') {
    $kind = rij('SELECT * FROM kinderen WHERE id = ?', [(int) invoer('kind')]);
    $datum = invoer('datum');
    if (!$kind || !geldige_datum($datum)) {
        niet_gevonden();
    }
    $status = schrijf_in($kind, $datum, (int) $gebruiker['id'], invoer('boven_max') === '1');
    if ($status === null) {
        flash('fout', 'Dat lukte niet: de groep is op deze dag dicht of het kind heeft geen groep.');
    } else {
        systeembericht((int) $kind['ouder_id'], $kind['voornaam'] . ' staat ingeschreven voor ' . datum_nl($datum, 'EEEE d MMMM') . ($status === 'wachtlijst' ? ' (op de wachtlijst).' : '.'), (int) $kind['id']);
        flash('succes', $kind['voornaam'] . ': ' . mb_strtolower(status_label($status)) . '.');
        log_actie('Agenda: toegevoegd', $kind['voornaam'] . ' ' . $datum . ' ' . $status);
    }
    redirect($terug);
}

if ($actie === 'uitzondering') {
    $groep = groep((int) invoer('groep'));
    $datum = invoer('datum');
    if (!$groep || !geldige_datum($datum)) {
        niet_gevonden();
    }
    if (invoer('verwijderen') === '1') {
        q('DELETE FROM groep_uitzonderingen WHERE groep_id = ? AND datum = ?', [$groep['id'], $datum]);
        vul_plek_vanuit_wachtlijst((int) $groep['id'], $datum);
        flash('succes', 'Deze dag is weer normaal.');
        redirect($terug);
    }
    $gesloten = invoer('gesloten') === '1' ? 1 : 0;
    $max = invoer('max_kinderen');
    $max = ctype_digit($max) && (int) $max > 0 ? (int) $max : null;
    $notitie = mb_substr(invoer('notitie'), 0, 200);
    q('INSERT INTO groep_uitzonderingen (groep_id, datum, gesloten, max_kinderen, notitie) VALUES (?, ?, ?, ?, ?)
       ON CONFLICT(groep_id, datum) DO UPDATE SET gesloten = excluded.gesloten, max_kinderen = excluded.max_kinderen, notitie = excluded.notitie', [
        $groep['id'], $datum, $gesloten, $max, $notitie,
    ]);
    if ($gesloten) {
        $getroffen = rijen("SELECT i.*, k.voornaam, k.ouder_id FROM inschrijvingen i JOIN kinderen k ON k.id = i.kind_id WHERE i.groep_id = ? AND i.datum = ? AND i.status IN ('bevestigd', 'wachtlijst')", [$groep['id'], $datum]);
        foreach ($getroffen as $inschrijving) {
            q("UPDATE inschrijvingen SET status = 'afgemeld', gewijzigd_op = ? WHERE id = ?", [nu(), $inschrijving['id']]);
            systeembericht((int) $inschrijving['ouder_id'], 'Let op: ' . $groep['naam'] . ' is op ' . datum_nl($datum, 'EEEE d MMMM') . ' gesloten' . ($notitie !== '' ? " ({$notitie})" : '') . '. ' . $inschrijving['voornaam'] . ' is daarom afgemeld voor die dag.', (int) $inschrijving['kind_id']);
        }
        flash('succes', 'De groep is gesloten op ' . datum_nl($datum) . '.' . ($getroffen ? ' ' . count($getroffen) . ' kind(eren) afgemeld; hun ouders hebben een bericht gekregen.' : ''));
    } else {
        vul_plek_vanuit_wachtlijst((int) $groep['id'], $datum);
        flash('succes', 'De dag is aangepast.');
    }
    log_actie('Agenda: dag aangepast', $groep['naam'] . ' ' . $datum . ($gesloten ? ' gesloten' : ' max ' . ($max ?? '-')));
    redirect($terug);
}

redirect($terug);
