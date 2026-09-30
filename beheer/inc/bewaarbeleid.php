<?php
/*
 * Bewaarbeleid: per soort gegevens een eigen bewaartermijn. Ontwikkelingsgegevens
 * gaan snel weg na vertrek; de financiële administratie blijft zeven jaar
 * (fiscale bewaarplicht). De termijnen stelt de beheerder in; de opschoning draait
 * dagelijks (cron: php beheer/cli/onderhoud.php, of anders vanzelf bij gebruik).
 *
 * Handmatige verwijderingen (AVG-verzoeken, gegevens wissen) komen in een
 * verwijderregister. Na het terugzetten van een back-up worden ze opnieuw
 * uitgevoerd (php beheer/cli/onderhoud.php --na-herstel), zodat verwijderde
 * gegevens niet via een back-up terugkomen.
 */

declare(strict_types=1);

/** sleutel => [omschrijving, eenheid, uitleg] */
const BEWAARTERMIJNEN = [
    'bewaar_ontwikkeling_maanden' => ['Ontwikkelingsgegevens: observaties uit het kindvolgsysteem', 'maanden', 'na vertrek van het kind'],
    'bewaar_kinddossier_maanden' => ['Kinddossier: gegevens, bijzonderheden, ophaalpersonen, toestemmingen en verzorgers', 'maanden', 'na vertrek van het kind; daarna blijft alleen een geanonimiseerde regel voor de financiële administratie'],
    'bewaar_fotos_maanden' => ["Foto's", 'maanden', 'na plaatsen, en uiterlijk bij het wissen van het kinddossier'],
    'bewaar_berichten_maanden' => ['Berichten en ziekmeldingen', 'maanden', 'na verzenden'],
    'bewaar_aanmeldingen_maanden' => ['Aanmeldingen die niet tot plaatsing leidden', 'maanden', 'na aanmelden'],
    'bewaar_account_maanden' => ['Account en contactgegevens van gestopte klanten', 'maanden', 'na vertrek; naam en adres blijven bij de facturen'],
    'bewaar_financieel_jaren' => ['Financiële administratie: facturen, betalingen en de opvangdagen waarop ze zijn gebaseerd', 'jaar', 'fiscale bewaarplicht'],
    'bewaar_logboek_maanden' => ['Logboek: inzage, wijzigingen, exports en inloggen', 'maanden', ''],
];

function bewaartermijn(string $sleutel): int
{
    return max(0, (int) instelling($sleutel, '0'));
}

/** Datum/tijd die $aantal maanden (of jaren) geleden is. */
function grens(int $aantal, string $eenheid = 'months'): string
{
    return date('Y-m-d H:i:s', strtotime("-{$aantal} {$eenheid}"));
}

/** Voert het bewaarbeleid uit. Geeft per categorie het aantal verwijderde items terug. */
function voer_bewaarbeleid_uit(): array
{
    $telling = [];

    // 1. Ontwikkelingsgegevens na vertrek
    $grens = grens(bewaartermijn('bewaar_ontwikkeling_maanden'));
    $telling['observaties'] = q('DELETE FROM observaties WHERE kind_id IN (SELECT id FROM kinderen WHERE actief = 0 AND gestopt_op IS NOT NULL AND gestopt_op < ?)', [$grens])->rowCount();

    // 2. Kinddossiers na vertrek anonimiseren
    $grens = grens(bewaartermijn('bewaar_kinddossier_maanden'));
    $telling['kinddossiers'] = 0;
    foreach (rijen('SELECT id FROM kinderen WHERE actief = 0 AND gestopt_op IS NOT NULL AND gestopt_op < ? AND geanonimiseerd_op IS NULL', [$grens]) as $kind) {
        wis_kinddossier((int) $kind['id'], 'bewaartermijn', false);
        $telling['kinddossiers']++;
    }

    // 3. Foto's
    $grens = grens(bewaartermijn('bewaar_fotos_maanden'));
    $telling['fotos'] = 0;
    foreach (rijen('SELECT * FROM fotos WHERE aangemaakt_op < ?', [$grens]) as $foto) {
        verwijder_foto($foto);
        $telling['fotos']++;
    }

    // 4. Berichten
    $telling['berichten'] = q('DELETE FROM berichten WHERE aangemaakt_op < ?', [grens(bewaartermijn('bewaar_berichten_maanden'))])->rowCount();

    // 5. Aanmeldingen die nooit zijn geactiveerd
    $telling['aanmeldingen'] = 0;
    foreach (rijen("SELECT id FROM gebruikers WHERE rol = 'ouder' AND status = 'nieuw' AND aangemaakt_op < ? AND NOT EXISTS (SELECT 1 FROM facturen f WHERE f.ouder_id = gebruikers.id)", [grens(bewaartermijn('bewaar_aanmeldingen_maanden'))]) as $rij) {
        verwijder_account_volledig((int) $rij['id']);
        $telling['aanmeldingen']++;
    }

    // 6. Gestopte klanten: account en contactgegevens wissen
    $telling['accounts'] = 0;
    foreach (rijen("SELECT * FROM gebruikers WHERE rol = 'ouder' AND status = 'gestopt' AND gestopt_op IS NOT NULL AND gestopt_op < ? AND gewist_op IS NULL", [grens(bewaartermijn('bewaar_account_maanden'))]) as $ouder) {
        wis_oudergegevens($ouder, 'bewaartermijn', false);
        $telling['accounts']++;
    }

    // 7. Financiële administratie na de fiscale bewaartermijn
    $grens = grens(bewaartermijn('bewaar_financieel_jaren'), 'years');
    $telling['facturen'] = q('DELETE FROM facturen WHERE datum < ?', [substr($grens, 0, 10)])->rowCount();
    q('DELETE FROM betalingen WHERE aangemaakt_op < ?', [$grens]);
    $telling['opvangdagen'] = q('DELETE FROM inschrijvingen WHERE datum < ?', [substr($grens, 0, 10)])->rowCount();
    // Geanonimiseerde kinderen zonder opvangdagen en gewiste accounts zonder facturen hebben geen functie meer
    q('DELETE FROM kinderen WHERE geanonimiseerd_op IS NOT NULL AND NOT EXISTS (SELECT 1 FROM inschrijvingen i WHERE i.kind_id = kinderen.id)');
    $telling['accounts_verwijderd'] = q("DELETE FROM gebruikers WHERE rol = 'ouder' AND gewist_op IS NOT NULL
        AND NOT EXISTS (SELECT 1 FROM facturen f WHERE f.ouder_id = gebruikers.id)
        AND NOT EXISTS (SELECT 1 FROM kinderen k WHERE k.ouder_id = gebruikers.id)")->rowCount();
    q("DELETE FROM avg_verzoeken WHERE status IN ('afgerond', 'afgewezen') AND afgehandeld_op < ?", [grens(bewaartermijn('bewaar_logboek_maanden'))]);

    // 8. Logboek
    $telling['logboek'] = q('DELETE FROM logboek WHERE aangemaakt_op < ?', [grens(bewaartermijn('bewaar_logboek_maanden'))])->rowCount();

    // 9. Kortlevende gegevens: links, inlogpogingen, exports, e-maillog
    q('DELETE FROM tokens WHERE verloopt_op < ? OR gebruikt = 1', [nu()]);
    q('DELETE FROM inlogpogingen WHERE tijd < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    foreach (rijen('SELECT id, export_bestand FROM avg_verzoeken WHERE export_bestand IS NOT NULL AND export_verloopt_op < ?', [nu()]) as $rij) {
        export_verwijder($rij['export_bestand']);
        q('UPDATE avg_verzoeken SET export_bestand = NULL WHERE id = ?', [$rij['id']]);
    }
    foreach (glob(data_dir() . '/exports/*.json') ?: [] as $bestand) {
        if (filemtime($bestand) < time() - EXPORT_GELDIG_DAGEN * 86400) {
            unlink($bestand);
        }
    }
    schoon_maillog(30);

    instelling_zet('bewaarbeleid_laatst', nu());
    log_actie('Bewaarbeleid uitgevoerd', json_encode(array_filter($telling)), null, 'bewaarbeleid');
    return $telling;
}

/** Draait het bewaarbeleid als dat vandaag nog niet is gebeurd (terugval als er geen cron is). */
function bewaarbeleid_indien_nodig(): void
{
    $laatst = instelling('bewaarbeleid_laatst');
    if ($laatst !== '' && strtotime($laatst) > time() - 86400) {
        return;
    }
    // Voorkom dat twee verzoeken tegelijk opschonen
    $mag = transactie(function () {
        $laatst = (string) waarde("SELECT waarde FROM instellingen WHERE sleutel = 'bewaarbeleid_laatst'");
        if ($laatst !== '' && strtotime($laatst) > time() - 86400) {
            return false;
        }
        instelling_zet('bewaarbeleid_laatst', nu());
        return true;
    });
    if ($mag) {
        try {
            voer_bewaarbeleid_uit();
        } catch (Throwable $fout) {
            error_log('Bewaarbeleid: ' . $fout->getMessage());
        }
    }
}

/* ---------- Wissen ---------- */

function verwijder_foto(array $foto): void
{
    verwijder_fotobestanden($foto['bestand']);
    q('DELETE FROM fotos WHERE id = ?', [$foto['id']]);
}

/**
 * Wist het dossier van een kind: observaties, foto's (waar geen ander kind op staat),
 * verzorgers, bijzonderheden en naam. Opvangdagen blijven (geanonimiseerd) bewaard
 * zolang de financiële administratie dat nodig heeft.
 */
function wis_kinddossier(int $kindId, string $reden, bool $registreer = true): void
{
    transactie(function () use ($kindId) {
        $wezen = rijen('SELECT f.* FROM fotos f WHERE EXISTS (SELECT 1 FROM foto_kinderen fk WHERE fk.foto_id = f.id AND fk.kind_id = ?)
                        AND NOT EXISTS (SELECT 1 FROM foto_kinderen fk WHERE fk.foto_id = f.id AND fk.kind_id != ?)', [$kindId, $kindId]);
        foreach ($wezen as $foto) {
            verwijder_foto($foto);
        }
        q('DELETE FROM foto_kinderen WHERE kind_id = ?', [$kindId]);
        q('DELETE FROM observaties WHERE kind_id = ?', [$kindId]);
        q('DELETE FROM kind_verzorgers WHERE kind_id = ?', [$kindId]);
        q('UPDATE berichten SET kind_id = NULL WHERE kind_id = ?', [$kindId]);
        q("UPDATE inschrijvingen SET status = 'afgemeld', gewijzigd_op = ? WHERE kind_id = ? AND datum > ? AND status IN ('bevestigd', 'wachtlijst')", [nu(), $kindId, vandaag()]);
        q("UPDATE kinderen SET voornaam = 'Verwijderd', achternaam = '', geboortedatum = NULL, school = '', bijzonderheden = '', ophaalpersonen = '',
           foto_toestemming = 0, actief = 0, groep_id = NULL, gestopt_op = COALESCE(gestopt_op, ?), geanonimiseerd_op = ? WHERE id = ?", [nu(), nu(), $kindId]);
    });
    if ($registreer) {
        registreer_verwijdering('kinddossier', $kindId, $reden);
    }
    log_actie('Kinddossier gewist', $reden, null, 'bewaarbeleid', 'kind:' . $kindId);
}

/**
 * Wist de gegevens van een gestopte ouder: kinddossiers (als contracthouder), koppelingen
 * met andere kinderen, berichten, verzoeken en contactgegevens. Naam, adres en facturen
 * blijven bewaard vanwege de fiscale bewaarplicht.
 */
function wis_oudergegevens(array $ouder, string $reden = 'handmatig', bool $registreer = true): void
{
    foreach (rijen('SELECT id FROM kinderen WHERE ouder_id = ? AND geanonimiseerd_op IS NULL', [$ouder['id']]) as $kind) {
        wis_kinddossier((int) $kind['id'], $reden, $registreer);
    }
    transactie(function () use ($ouder) {
        q('DELETE FROM kind_verzorgers WHERE gebruiker_id = ?', [$ouder['id']]);
        q('DELETE FROM berichten WHERE ouder_id = ?', [$ouder['id']]);
        q('DELETE FROM tokens WHERE gebruiker_id = ?', [$ouder['id']]);
        q('DELETE FROM mfa_herstelcodes WHERE gebruiker_id = ?', [$ouder['id']]);
        q("UPDATE gebruikers SET status = 'gestopt', email = ?, telefoon = '', wachtwoord_hash = NULL, contactvoorkeur = '', gewenste_dagen = '', gewenste_startdatum = NULL,
           opmerkingen = '', mandaat_naam = '', mandaat_rekening = '', mfa_geheim = NULL, mfa_actief = 0, gestopt_op = COALESCE(gestopt_op, ?), gewist_op = ? WHERE id = ?", [
            'gewist-' . $ouder['id'] . '@verwijderd.invalid', nu(), nu(), $ouder['id'],
        ]);
    });
    if ($registreer) {
        registreer_verwijdering('ouder', (int) $ouder['id'], $reden);
    }
    log_actie('Oudergegevens gewist', $reden, null, 'bewaarbeleid', 'gebruiker:' . $ouder['id']);
}

/** Verwijdert een account zonder facturen helemaal, met de kinderen (aanmelding die nooit klant werd). */
function verwijder_account_volledig(int $gebruikerId): void
{
    transactie(function () use ($gebruikerId) {
        foreach (rijen('SELECT id FROM kinderen WHERE ouder_id = ?', [$gebruikerId]) as $kind) {
            wis_kinddossier((int) $kind['id'], 'aanmelding verlopen', false);
            q('DELETE FROM kinderen WHERE id = ?', [$kind['id']]);
        }
        q('DELETE FROM gebruikers WHERE id = ? AND NOT EXISTS (SELECT 1 FROM facturen f WHERE f.ouder_id = ?)', [$gebruikerId, $gebruikerId]);
    });
    registreer_verwijdering('account', $gebruikerId, 'aanmelding verlopen');
    log_actie('Aanmelding verwijderd', 'bewaartermijn verlopen', null, 'bewaarbeleid', 'gebruiker:' . $gebruikerId);
}

/* ---------- Verwijderregister (voor na het terugzetten van een back-up) ---------- */

function verwijderregister_pad(): string
{
    return data_dir() . '/verwijderregister.jsonl';
}

/** Alleen soort en nummer, geen persoonsgegevens. */
function registreer_verwijdering(string $soort, int $id, string $reden): void
{
    file_put_contents(verwijderregister_pad(), json_encode(['soort' => $soort, 'id' => $id, 'reden' => $reden, 'op' => nu()]) . "\n", FILE_APPEND | LOCK_EX);
}

/** Voert alle geregistreerde verwijderingen opnieuw uit (na het terugzetten van een back-up). */
function herhaal_verwijderingen(): int
{
    $pad = verwijderregister_pad();
    if (!is_file($pad)) {
        return 0;
    }
    $aantal = 0;
    foreach (file($pad, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $regel) {
        $item = json_decode($regel, true);
        if (!is_array($item)) {
            continue;
        }
        $id = (int) $item['id'];
        if ($item['soort'] === 'kinddossier' && waarde('SELECT 1 FROM kinderen WHERE id = ? AND geanonimiseerd_op IS NULL', [$id])) {
            wis_kinddossier($id, 'opnieuw na herstel back-up', false);
            $aantal++;
        } elseif ($item['soort'] === 'ouder' && ($ouder = rij('SELECT * FROM gebruikers WHERE id = ? AND gewist_op IS NULL', [$id]))) {
            wis_oudergegevens($ouder, 'opnieuw na herstel back-up', false);
            $aantal++;
        } elseif ($item['soort'] === 'account' && waarde('SELECT 1 FROM gebruikers WHERE id = ?', [$id])) {
            verwijder_account_volledig($id);
            $aantal++;
        } elseif ($item['soort'] === 'foto' && ($foto = rij('SELECT * FROM fotos WHERE id = ?', [$id]))) {
            verwijder_foto($foto);
            $aantal++;
        }
    }
    log_actie('Verwijderingen herhaald na herstel', (string) $aantal, null, 'bewaarbeleid');
    return $aantal;
}

function schoon_maillog(int $dagen): void
{
    $pad = data_dir() . '/mail.log';
    if (!is_file($pad)) {
        return;
    }
    if (filemtime($pad) < time() - $dagen * 86400) {
        unlink($pad); // niets recents meer in
        return;
    }
    $grens = date('Y-m-d H:i:s', time() - $dagen * 86400);
    $blokken = preg_split('/^-{60}\n/m', (string) file_get_contents($pad));
    $houden = array_filter($blokken, fn ($b) => preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', ltrim($b), $m) && $m[1] >= $grens);
    file_put_contents($pad, $houden ? implode(str_repeat('-', 60) . "\n", $houden) . str_repeat('-', 60) . "\n" : '', LOCK_EX);
}
