<?php
/*
 * Database (SQLite). Het schema wordt automatisch aangemaakt en bijgewerkt:
 * elke migratie hieronder draait precies één keer (PRAGMA user_version).
 */

declare(strict_types=1);

function data_dir(): string
{
    $map = rtrim((string) cfg('data_dir'), '/');
    if (!is_dir($map)) {
        if (!mkdir($map, 0770, true) && !is_dir($map)) {
            throw new RuntimeException('De datamap kan niet worden aangemaakt: ' . $map);
        }
    }
    // Extra afscherming voor Apache als de map toch in de webroot staat
    if (!is_file($map . '/.htaccess')) {
        file_put_contents($map . '/.htaccess', "Require all denied\n");
    }
    return $map;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . data_dir() . '/beheer.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    migreer($pdo);
    return $pdo;
}

function q(string $sql, array $parameters = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($parameters);
    return $stmt;
}

function rij(string $sql, array $parameters = []): ?array
{
    $rij = q($sql, $parameters)->fetch();
    return $rij === false ? null : $rij;
}

function rijen(string $sql, array $parameters = []): array
{
    return q($sql, $parameters)->fetchAll();
}

function waarde(string $sql, array $parameters = []): mixed
{
    $waarde = q($sql, $parameters)->fetchColumn();
    return $waarde === false ? null : $waarde;
}

function laatste_id(): int
{
    return (int) db()->lastInsertId();
}

/**
 * Voer $werk uit binnen één transactie. BEGIN IMMEDIATE zorgt dat twee ouders
 * niet tegelijk de laatste plek in een groep kunnen boeken.
 */
function transactie(callable $werk): mixed
{
    // Zelf bijhouden of we al in een transactie zitten: PDO::inTransaction() ziet een BEGIN via exec()
    // niet op elke PHP-versie, en een tweede BEGIN geeft dan een fout.
    static $diepte = 0;
    $pdo = db();
    if ($diepte > 0 || $pdo->inTransaction()) {
        return $werk();
    }
    $pdo->exec('BEGIN IMMEDIATE');
    $diepte++;
    try {
        $resultaat = $werk();
        $pdo->exec('COMMIT');
        return $resultaat;
    } catch (Throwable $fout) {
        $pdo->exec('ROLLBACK');
        throw $fout;
    } finally {
        $diepte--;
    }
}

function migreer(PDO $pdo): void
{
    $migraties = [
        1 => <<<'SQL'
            CREATE TABLE gebruikers (
                id INTEGER PRIMARY KEY,
                rol TEXT NOT NULL CHECK (rol IN ('beheerder', 'medewerker', 'ouder')),
                status TEXT NOT NULL DEFAULT 'actief' CHECK (status IN ('nieuw', 'actief', 'gestopt')),
                naam TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE COLLATE NOCASE,
                telefoon TEXT NOT NULL DEFAULT '',
                straat TEXT NOT NULL DEFAULT '',
                postcode TEXT NOT NULL DEFAULT '',
                plaats TEXT NOT NULL DEFAULT '',
                contactvoorkeur TEXT NOT NULL DEFAULT '',
                wachtwoord_hash TEXT,
                gewenste_dagen TEXT NOT NULL DEFAULT '',
                gewenste_startdatum TEXT,
                opmerkingen TEXT NOT NULL DEFAULT '',
                mollie_klant_id TEXT,
                mandaat_id TEXT,
                mandaat_status TEXT NOT NULL DEFAULT 'geen',
                mandaat_rekening TEXT NOT NULL DEFAULT '',
                mandaat_naam TEXT NOT NULL DEFAULT '',
                mandaat_datum TEXT,
                laatst_ingelogd TEXT,
                aangemaakt_op TEXT NOT NULL
            );

            CREATE TABLE groepen (
                id INTEGER PRIMARY KEY,
                naam TEXT NOT NULL,
                omschrijving TEXT NOT NULL DEFAULT '',
                max_kinderen INTEGER NOT NULL CHECK (max_kinderen > 0),
                dagen TEXT NOT NULL DEFAULT '1,2,3,4,5',
                begintijd TEXT NOT NULL DEFAULT '14:00',
                eindtijd TEXT NOT NULL DEFAULT '17:00',
                kleur TEXT NOT NULL DEFAULT 'sun',
                actief INTEGER NOT NULL DEFAULT 1
            );

            CREATE TABLE kinderen (
                id INTEGER PRIMARY KEY,
                ouder_id INTEGER NOT NULL REFERENCES gebruikers(id) ON DELETE CASCADE,
                groep_id INTEGER REFERENCES groepen(id) ON DELETE SET NULL,
                voornaam TEXT NOT NULL,
                achternaam TEXT NOT NULL DEFAULT '',
                geboortedatum TEXT,
                school TEXT NOT NULL DEFAULT '',
                bijzonderheden TEXT NOT NULL DEFAULT '',
                ophaalpersonen TEXT NOT NULL DEFAULT '',
                foto_toestemming INTEGER NOT NULL DEFAULT 0,
                actief INTEGER NOT NULL DEFAULT 1,
                aangemaakt_op TEXT NOT NULL
            );
            CREATE INDEX kinderen_ouder ON kinderen(ouder_id);

            CREATE TABLE groep_uitzonderingen (
                id INTEGER PRIMARY KEY,
                groep_id INTEGER NOT NULL REFERENCES groepen(id) ON DELETE CASCADE,
                datum TEXT NOT NULL,
                gesloten INTEGER NOT NULL DEFAULT 0,
                max_kinderen INTEGER,
                notitie TEXT NOT NULL DEFAULT '',
                UNIQUE (groep_id, datum)
            );

            CREATE TABLE inschrijvingen (
                id INTEGER PRIMARY KEY,
                kind_id INTEGER NOT NULL REFERENCES kinderen(id) ON DELETE CASCADE,
                groep_id INTEGER NOT NULL REFERENCES groepen(id) ON DELETE CASCADE,
                datum TEXT NOT NULL,
                status TEXT NOT NULL CHECK (status IN ('bevestigd', 'wachtlijst', 'afgemeld', 'ziek')),
                aanwezig_om TEXT,
                opgehaald_om TEXT,
                gefactureerd INTEGER NOT NULL DEFAULT 0,
                aangemaakt_door INTEGER REFERENCES gebruikers(id) ON DELETE SET NULL,
                aangemaakt_op TEXT NOT NULL,
                gewijzigd_op TEXT NOT NULL,
                UNIQUE (kind_id, datum)
            );
            CREATE INDEX inschrijvingen_groep_datum ON inschrijvingen(groep_id, datum, status);

            CREATE TABLE berichten (
                id INTEGER PRIMARY KEY,
                ouder_id INTEGER NOT NULL REFERENCES gebruikers(id) ON DELETE CASCADE,
                afzender_id INTEGER REFERENCES gebruikers(id) ON DELETE SET NULL,
                kind_id INTEGER REFERENCES kinderen(id) ON DELETE SET NULL,
                soort TEXT NOT NULL DEFAULT 'bericht' CHECK (soort IN ('bericht', 'ziekmelding', 'systeem')),
                tekst TEXT NOT NULL,
                gelezen_ouder INTEGER NOT NULL DEFAULT 0,
                gelezen_team INTEGER NOT NULL DEFAULT 0,
                aangemaakt_op TEXT NOT NULL
            );
            CREATE INDEX berichten_ouder ON berichten(ouder_id, aangemaakt_op);

            CREATE TABLE fotos (
                id INTEGER PRIMARY KEY,
                bestand TEXT NOT NULL,
                breedte INTEGER NOT NULL,
                hoogte INTEGER NOT NULL,
                bijschrift TEXT NOT NULL DEFAULT '',
                geupload_door INTEGER REFERENCES gebruikers(id) ON DELETE SET NULL,
                aangemaakt_op TEXT NOT NULL
            );

            CREATE TABLE foto_kinderen (
                foto_id INTEGER NOT NULL REFERENCES fotos(id) ON DELETE CASCADE,
                kind_id INTEGER NOT NULL REFERENCES kinderen(id) ON DELETE CASCADE,
                PRIMARY KEY (foto_id, kind_id)
            );

            CREATE TABLE observaties (
                id INTEGER PRIMARY KEY,
                kind_id INTEGER NOT NULL REFERENCES kinderen(id) ON DELETE CASCADE,
                auteur_id INTEGER REFERENCES gebruikers(id) ON DELETE SET NULL,
                datum TEXT NOT NULL,
                gebied TEXT NOT NULL,
                tekst TEXT NOT NULL,
                gedeeld INTEGER NOT NULL DEFAULT 0,
                aangemaakt_op TEXT NOT NULL
            );
            CREATE INDEX observaties_kind ON observaties(kind_id, datum);

            CREATE TABLE facturen (
                id INTEGER PRIMARY KEY,
                nummer TEXT NOT NULL UNIQUE,
                ouder_id INTEGER NOT NULL REFERENCES gebruikers(id) ON DELETE RESTRICT,
                periode TEXT NOT NULL,
                datum TEXT NOT NULL,
                vervaldatum TEXT NOT NULL,
                bedrag_cent INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'incasso', 'betaald', 'mislukt', 'geannuleerd')),
                betaald_op TEXT,
                notitie TEXT NOT NULL DEFAULT '',
                aangemaakt_op TEXT NOT NULL
            );
            CREATE INDEX facturen_ouder_periode ON facturen(ouder_id, periode);

            CREATE TABLE factuurregels (
                id INTEGER PRIMARY KEY,
                factuur_id INTEGER NOT NULL REFERENCES facturen(id) ON DELETE CASCADE,
                kind_id INTEGER REFERENCES kinderen(id) ON DELETE SET NULL,
                omschrijving TEXT NOT NULL,
                aantal REAL NOT NULL,
                eenheid TEXT NOT NULL DEFAULT 'uur',
                prijs_cent INTEGER NOT NULL,
                bedrag_cent INTEGER NOT NULL
            );

            CREATE TABLE betalingen (
                id INTEGER PRIMARY KEY,
                mollie_id TEXT UNIQUE,
                soort TEXT NOT NULL CHECK (soort IN ('machtiging', 'incasso', 'eenmalig')),
                gebruiker_id INTEGER NOT NULL REFERENCES gebruikers(id) ON DELETE CASCADE,
                factuur_id INTEGER REFERENCES facturen(id) ON DELETE SET NULL,
                bedrag_cent INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'open',
                aangemaakt_op TEXT NOT NULL,
                bijgewerkt_op TEXT NOT NULL
            );

            CREATE TABLE tokens (
                id INTEGER PRIMARY KEY,
                gebruiker_id INTEGER NOT NULL REFERENCES gebruikers(id) ON DELETE CASCADE,
                soort TEXT NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                verloopt_op TEXT NOT NULL,
                gebruikt INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE inlogpogingen (
                id INTEGER PRIMARY KEY,
                sleutel TEXT NOT NULL,
                tijd TEXT NOT NULL
            );
            CREATE INDEX inlogpogingen_sleutel ON inlogpogingen(sleutel, tijd);

            CREATE TABLE instellingen (
                sleutel TEXT PRIMARY KEY,
                waarde TEXT NOT NULL
            );

            CREATE TABLE logboek (
                id INTEGER PRIMARY KEY,
                gebruiker_id INTEGER REFERENCES gebruikers(id) ON DELETE SET NULL,
                actie TEXT NOT NULL,
                details TEXT NOT NULL DEFAULT '',
                ip TEXT NOT NULL DEFAULT '',
                aangemaakt_op TEXT NOT NULL
            );

            INSERT INTO instellingen (sleutel, waarde) VALUES
                ('bedrijfsnaam', 'BSO VCK'),
                ('statutaire_naam', 'Je Dag in Beeld'),
                ('adres', ''),
                ('postcode_plaats', 'Amsterdam'),
                ('kvk', ''),
                ('lrk', ''),
                ('iban', ''),
                ('email', 'info@vckbso.nl'),
                ('telefoon', '06 12 34 56 78'),
                ('uurtarief_cent', ''),
                ('uren_per_middag', ''),
                ('afmelden_tot', '12:00'),
                ('betaaltermijn_dagen', '14'),
                ('volgend_factuurnummer', '1');
            SQL,

        // Toegang per groep en per kind, tweestapsverificatie, AVG-verzoeken, beveiligd logboek, bewaarbeleid
        2 => <<<'SQL'
            ALTER TABLE gebruikers ADD COLUMN mfa_geheim TEXT;
            ALTER TABLE gebruikers ADD COLUMN mfa_actief INTEGER NOT NULL DEFAULT 0;
            ALTER TABLE gebruikers ADD COLUMN mfa_laatste_stap INTEGER NOT NULL DEFAULT 0;
            ALTER TABLE gebruikers ADD COLUMN gestopt_op TEXT;
            ALTER TABLE gebruikers ADD COLUMN gewist_op TEXT;
            ALTER TABLE kinderen ADD COLUMN gestopt_op TEXT;
            ALTER TABLE kinderen ADD COLUMN geanonimiseerd_op TEXT;
            UPDATE gebruikers SET gestopt_op = datetime('now', 'localtime') WHERE status = 'gestopt';
            UPDATE kinderen SET gestopt_op = datetime('now', 'localtime') WHERE actief = 0;

            CREATE TABLE mfa_herstelcodes (
                id INTEGER PRIMARY KEY,
                gebruiker_id INTEGER NOT NULL REFERENCES gebruikers(id) ON DELETE CASCADE,
                code_hash TEXT NOT NULL,
                gebruikt_op TEXT
            );

            -- Medewerkers zien alleen kinderen in hun eigen groepen (eventueel tijdelijk, voor invallers)
            CREATE TABLE medewerker_groepen (
                gebruiker_id INTEGER NOT NULL REFERENCES gebruikers(id) ON DELETE CASCADE,
                groep_id INTEGER NOT NULL REFERENCES groepen(id) ON DELETE CASCADE,
                tot TEXT,
                PRIMARY KEY (gebruiker_id, groep_id)
            );

            -- Wie mag wat namens een kind? Ouder zijn alleen is niet genoeg: gezag wordt door het team gecontroleerd
            CREATE TABLE kind_verzorgers (
                kind_id INTEGER NOT NULL REFERENCES kinderen(id) ON DELETE CASCADE,
                gebruiker_id INTEGER NOT NULL REFERENCES gebruikers(id) ON DELETE CASCADE,
                relatie TEXT NOT NULL DEFAULT 'ouder',
                gezag INTEGER NOT NULL DEFAULT 0,
                gezag_gecontroleerd_op TEXT,
                gezag_gecontroleerd_door INTEGER REFERENCES gebruikers(id) ON DELETE SET NULL,
                gezag_bron TEXT NOT NULL DEFAULT '',
                recht_agenda INTEGER NOT NULL DEFAULT 1,
                recht_dossier INTEGER NOT NULL DEFAULT 1,
                recht_fotos INTEGER NOT NULL DEFAULT 1,
                recht_berichten INTEGER NOT NULL DEFAULT 1,
                aangemaakt_op TEXT NOT NULL,
                PRIMARY KEY (kind_id, gebruiker_id)
            );
            CREATE INDEX kind_verzorgers_gebruiker ON kind_verzorgers(gebruiker_id);
            INSERT INTO kind_verzorgers (kind_id, gebruiker_id, relatie, gezag, aangemaakt_op)
                SELECT id, ouder_id, 'ouder', 1, aangemaakt_op FROM kinderen;

            ALTER TABLE logboek ADD COLUMN soort TEXT NOT NULL DEFAULT 'wijziging';
            ALTER TABLE logboek ADD COLUMN onderwerp TEXT NOT NULL DEFAULT '';
            ALTER TABLE logboek ADD COLUMN vorige TEXT NOT NULL DEFAULT '';
            ALTER TABLE logboek ADD COLUMN hash TEXT NOT NULL DEFAULT '';
            CREATE INDEX logboek_onderwerp ON logboek(onderwerp);
            CREATE INDEX logboek_tijd ON logboek(aangemaakt_op);

            CREATE TABLE avg_verzoeken (
                id INTEGER PRIMARY KEY,
                gebruiker_id INTEGER REFERENCES gebruikers(id) ON DELETE SET NULL,
                kind_id INTEGER REFERENCES kinderen(id) ON DELETE SET NULL,
                over_naam TEXT NOT NULL DEFAULT '',
                soort TEXT NOT NULL CHECK (soort IN ('inzage', 'correctie', 'verwijdering', 'beperking', 'bezwaar', 'overdracht')),
                toelichting TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'ontvangen' CHECK (status IN ('ontvangen', 'in_behandeling', 'afgerond', 'afgewezen')),
                deadline TEXT NOT NULL,
                reactie TEXT NOT NULL DEFAULT '',
                afgehandeld_door INTEGER REFERENCES gebruikers(id) ON DELETE SET NULL,
                afgehandeld_op TEXT,
                export_bestand TEXT,
                export_verloopt_op TEXT,
                aangemaakt_op TEXT NOT NULL
            );

            INSERT INTO instellingen (sleutel, waarde) VALUES
                ('mfa_ouders', 'optioneel'),
                ('bewaar_ontwikkeling_maanden', '3'),
                ('bewaar_kinddossier_maanden', '3'),
                ('bewaar_fotos_maanden', '12'),
                ('bewaar_berichten_maanden', '24'),
                ('bewaar_aanmeldingen_maanden', '6'),
                ('bewaar_account_maanden', '3'),
                ('bewaar_financieel_jaren', '7'),
                ('bewaar_logboek_maanden', '24'),
                ('bewaarbeleid_laatst', ''),
                ('backup_laatst', '');
            SQL,

        // Bestaande gevoelige gegevens alsnog versleutelen
        3 => function (PDO $pdo): void {
            foreach ([['kinderen', 'bijzonderheden'], ['kinderen', 'ophaalpersonen'], ['observaties', 'tekst'], ['berichten', 'tekst']] as [$tabel, $kolom]) {
                $stmt = $pdo->query("SELECT id, {$kolom} AS waarde FROM {$tabel} WHERE {$kolom} != '' AND {$kolom} NOT LIKE 'enc:v1:%'");
                $update = $pdo->prepare("UPDATE {$tabel} SET {$kolom} = ? WHERE id = ?");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $rij) {
                    $update->execute([versleutel($rij['waarde']), $rij['id']]);
                }
            }
            foreach (glob(data_dir() . '/fotos/*/*/*.jpg') ?: [] as $pad) {
                $inhoud = (string) file_get_contents($pad);
                if (!str_starts_with($inhoud, BESTAND_MAGIC)) {
                    versleutel_bestand($pad, $inhoud);
                }
            }
        },

        // Rechten per persoon (per menuonderdeel) en de financiële administratie
        4 => function (PDO $pdo): void {
            $pdo->exec(<<<'SQL'
                CREATE TABLE team_rechten (
                    gebruiker_id INTEGER NOT NULL REFERENCES gebruikers(id) ON DELETE CASCADE,
                    recht TEXT NOT NULL,
                    PRIMARY KEY (gebruiker_id, recht)
                );
                CREATE TABLE boekingen (
                    id INTEGER PRIMARY KEY,
                    soort TEXT NOT NULL CHECK (soort IN ('inkomst', 'uitgave')),
                    datum TEXT NOT NULL,
                    categorie TEXT NOT NULL,
                    omschrijving TEXT NOT NULL DEFAULT '',
                    bedrag_cent INTEGER NOT NULL CHECK (bedrag_cent > 0),
                    bijlage TEXT,
                    bijlage_naam TEXT,
                    bijlage_type TEXT,
                    aangemaakt_door INTEGER REFERENCES gebruikers(id) ON DELETE SET NULL,
                    aangemaakt_op TEXT NOT NULL,
                    gewijzigd_op TEXT
                );
                CREATE INDEX boekingen_datum ON boekingen(datum);
                SQL);
            // Wat iemand nu mag, blijft zo: beheerders alles, medewerkers het vaste pakket voor begeleiders
            $insert = $pdo->prepare('INSERT OR IGNORE INTO team_rechten (gebruiker_id, recht) VALUES (?, ?)');
            foreach ($pdo->query("SELECT id, rol FROM gebruikers WHERE rol IN ('beheerder', 'medewerker')")->fetchAll(PDO::FETCH_ASSOC) as $persoon) {
                foreach ($persoon['rol'] === 'beheerder' ? alle_rechten() : RECHTEN_BEGELEIDER as $recht) {
                    $insert->execute([$persoon['id'], $recht]);
                }
            }
        },

        // Nieuwe naam: BSO VCK heet voortaan Sporty (alleen als de naam nog niet zelf was aangepast)
        5 => "UPDATE instellingen SET waarde = 'Sporty' WHERE sleutel = 'bedrijfsnaam' AND waarde = 'BSO VCK';",

        // Nieuw e-mailadres: info@vckbso.nl wordt info@sporty.nl (alleen als het adres nog niet zelf was aangepast)
        6 => "UPDATE instellingen SET waarde = 'info@sporty.nl' WHERE sleutel = 'email' AND waarde = 'info@vckbso.nl';",

        // Statutaire naam: Je Dag in Beeld wordt Sporty (23 oktober 2026; alleen als de naam nog niet zelf was aangepast)
        7 => "UPDATE instellingen SET waarde = 'Sporty' WHERE sleutel = 'statutaire_naam' AND waarde = 'Je Dag in Beeld';",

        // Open sollicitaties via de website (24 oktober 2026). Beheerders krijgen het nieuwe menuonderdeel meteen.
        8 => <<<'SQL'
            CREATE TABLE sollicitaties (
                id INTEGER PRIMARY KEY,
                status TEXT NOT NULL DEFAULT 'nieuw' CHECK (status IN ('nieuw', 'gesprek', 'afgewezen', 'aangenomen')),
                naam TEXT NOT NULL,
                email TEXT NOT NULL,
                telefoon TEXT NOT NULL DEFAULT '',
                woonplaats TEXT NOT NULL DEFAULT '',
                functie TEXT NOT NULL DEFAULT '',
                dagen TEXT NOT NULL DEFAULT '',
                motivatie TEXT NOT NULL DEFAULT '',
                link TEXT NOT NULL DEFAULT '',
                notitie TEXT NOT NULL DEFAULT '',
                cv_bestand TEXT,
                cv_naam TEXT,
                cv_type TEXT,
                cv_grootte INTEGER,
                bewaren INTEGER NOT NULL DEFAULT 0,
                aangemaakt_op TEXT NOT NULL,
                afgerond_op TEXT,
                verwijderen_op TEXT NOT NULL
            );
            CREATE INDEX sollicitaties_verwijderen ON sollicitaties(verwijderen_op);
            INSERT OR IGNORE INTO team_rechten (gebruiker_id, recht) SELECT id, 'sollicitaties' FROM gebruikers WHERE rol = 'beheerder';
            SQL,

        // Word Sporty coach (november 2026): de motivatie komt als bestand (pdf of Word), net als het cv
        9 => <<<'SQL'
            ALTER TABLE sollicitaties ADD COLUMN motivatie_bestand TEXT;
            ALTER TABLE sollicitaties ADD COLUMN motivatie_naam TEXT;
            ALTER TABLE sollicitaties ADD COLUMN motivatie_type TEXT;
            ALTER TABLE sollicitaties ADD COLUMN motivatie_grootte INTEGER;
            SQL,

        // Proefdagen boeken via de website (november 2026). Beheerders krijgen het nieuwe menuonderdeel meteen.
        10 => <<<'SQL'
            CREATE TABLE proefdag_momenten (
                id INTEGER PRIMARY KEY,
                datum TEXT NOT NULL,
                begin TEXT NOT NULL,
                eind TEXT NOT NULL,
                plekken INTEGER NOT NULL DEFAULT 3 CHECK (plekken BETWEEN 1 AND 50),
                aangemaakt_op TEXT NOT NULL
            );
            CREATE INDEX proefdag_momenten_datum ON proefdag_momenten(datum);
            CREATE TABLE proefdag_boekingen (
                id INTEGER PRIMARY KEY,
                moment_id INTEGER NOT NULL REFERENCES proefdag_momenten(id) ON DELETE CASCADE,
                status TEXT NOT NULL DEFAULT 'geboekt' CHECK (status IN ('geboekt', 'geweest', 'niet_gekomen', 'geannuleerd')),
                naam TEXT NOT NULL,
                email TEXT NOT NULL,
                telefoon TEXT NOT NULL,
                kind_naam TEXT NOT NULL,
                kind_leeftijd TEXT NOT NULL DEFAULT '',
                opmerking TEXT NOT NULL DEFAULT '',
                aangemaakt_op TEXT NOT NULL,
                verwijderen_op TEXT NOT NULL
            );
            CREATE INDEX proefdag_boekingen_moment ON proefdag_boekingen(moment_id);
            CREATE INDEX proefdag_boekingen_verwijderen ON proefdag_boekingen(verwijderen_op);
            INSERT OR IGNORE INTO team_rechten (gebruiker_id, recht) SELECT id, 'proefdagen' FROM gebruikers WHERE rol = 'beheerder';
            SQL,

        // Vrijblijvend aanmelden in 7 stappen via de website (november 2026): extra gegevens uit het formulier
        // (versleuteld, als JSON) en een anonieme telling per stap, om te zien waar ouders afhaken.
        11 => <<<'SQL'
            ALTER TABLE gebruikers ADD COLUMN aanvraag TEXT NOT NULL DEFAULT '';
            ALTER TABLE kinderen ADD COLUMN aanvraag TEXT NOT NULL DEFAULT '';
            CREATE TABLE aanvraag_stappen (
                datum TEXT NOT NULL,
                stap INTEGER NOT NULL,
                aantal INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (datum, stap)
            );
            SQL,

        // Verborgen notities per kind (november 2026): alleen voor het team, nooit zichtbaar voor ouders in
        // Mijn BSO. Versleuteld, met wie ze het laatst heeft aangepast en wanneer.
        12 => <<<'SQL'
            ALTER TABLE kinderen ADD COLUMN team_notities TEXT NOT NULL DEFAULT '';
            ALTER TABLE kinderen ADD COLUMN team_notities_op TEXT;
            ALTER TABLE kinderen ADD COLUMN team_notities_door INTEGER;
            SQL,

        // Blijf op de hoogte (november 2026): ouders met interesse, met dubbele bevestiging en een afmeldlink.
        // token_hash om een link terug te vinden; token (versleuteld) om de afmeldlink onder elke e-mail te zetten.
        13 => <<<'SQL'
            CREATE TABLE interesse (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                naam TEXT NOT NULL,
                email TEXT NOT NULL,
                telefoon TEXT NOT NULL DEFAULT '',
                token_hash TEXT NOT NULL UNIQUE,
                token TEXT NOT NULL,
                bron TEXT NOT NULL DEFAULT '',
                aangemaakt_op TEXT NOT NULL,
                bevestigd_op TEXT,
                pakket_op TEXT
            );
            CREATE INDEX interesse_email ON interesse(email);
            INSERT OR IGNORE INTO instellingen (sleutel, waarde) VALUES ('bewaar_interesse_maanden', '24');
            INSERT OR IGNORE INTO team_rechten (gebruiker_id, recht) SELECT id, 'interesse' FROM gebruikers WHERE rol = 'beheerder';
            SQL,

        // E-mails aanpassen (november 2026): de eigen tekst van een e-mail; zonder regel geldt de standaardtekst
        // uit inc/mailteksten.php.
        14 => <<<'SQL'
            CREATE TABLE mail_teksten (
                sleutel TEXT PRIMARY KEY,
                onderwerp TEXT,
                tekst TEXT NOT NULL,
                gewijzigd_op TEXT NOT NULL,
                gewijzigd_door INTEGER
            );
            SQL,

        // Fototoestemming per kind (november 2026): mogen er überhaupt foto's van het kind gedeeld worden?
        // NULL = nog niet ingevuld (dan niet), 0 = nee, 1 = ja. foto_toestemming blijft: ook op groepsfoto's.
        // Wie al toestemming gaf voor groepsfoto's, gaf daarmee ook toestemming voor foto's.
        15 => <<<'SQL'
            ALTER TABLE kinderen ADD COLUMN fotos_toegestaan INTEGER;
            ALTER TABLE kinderen ADD COLUMN fotos_toegestaan_op TEXT;
            UPDATE kinderen SET fotos_toegestaan = 1, fotos_toegestaan_op = aangemaakt_op WHERE foto_toestemming = 1;
            SQL,

        // Betalen via Stripe in plaats van Mollie (november 2026). extern_id is de Checkout-sessie (cs_...) of de
        // betaling (pi_...) bij Stripe; payment_intent de betaling die bij een Checkout-sessie hoort.
        // Een machtiging van Mollie werkt niet bij Stripe: die ouders geven de machtiging opnieuw af.
        16 => <<<'SQL'
            ALTER TABLE gebruikers RENAME COLUMN mollie_klant_id TO stripe_klant_id;
            UPDATE gebruikers SET stripe_klant_id = NULL;
            ALTER TABLE betalingen RENAME COLUMN mollie_id TO extern_id;
            ALTER TABLE betalingen ADD COLUMN payment_intent TEXT;
            CREATE INDEX betalingen_payment_intent ON betalingen(payment_intent);
            UPDATE gebruikers SET mandaat_status = 'geen', mandaat_id = NULL
                WHERE mandaat_status IN ('geldig', 'in_behandeling') AND (mandaat_id IS NULL OR mandaat_id NOT LIKE 'pm\_%' ESCAPE '\');
            SQL,

        // Koppelingen (november 2026): Stripe en e-mail instellen in Mijn BSO in plaats van in config.php.
        // De waarden staan versleuteld; beheerders krijgen het nieuwe menuonderdeel meteen.
        17 => <<<'SQL'
            CREATE TABLE koppelingen (
                sleutel TEXT PRIMARY KEY,
                waarde TEXT NOT NULL,
                gewijzigd_op TEXT NOT NULL,
                gewijzigd_door INTEGER
            );
            INSERT OR IGNORE INTO team_rechten (gebruiker_id, recht) SELECT id, 'koppelingen' FROM gebruikers WHERE rol = 'beheerder';
            SQL,
    ];

    $versie = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    foreach ($migraties as $nummer => $stap) {
        if ($nummer <= $versie) {
            continue;
        }
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            if (is_callable($stap)) {
                $stap($pdo);
            } else {
                $pdo->exec($stap);
            }
            $pdo->exec('PRAGMA user_version = ' . (int) $nummer);
            $pdo->exec('COMMIT');
        } catch (Throwable $fout) {
            $pdo->exec('ROLLBACK');
            throw $fout;
        }
    }
}
