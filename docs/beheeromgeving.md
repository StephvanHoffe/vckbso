# Beheeromgeving en Mijn BSO (`/beheer/`)

De beheeromgeving is een eigen webapplicatie naast de statische website. Ouders gebruiken hem als **Mijn BSO**, het team als **Beheer**. Alles staat in de map `beheer/`.

## Wat kan het?

| Wens van de klant | Waar | Hoe het werkt |
|---|---|---|
| Aanmelden op de website en als klant in het systeem komen, met de kinderen eronder | `aanmelden.php` (knop op `aanmelden.html`) | Ouder maakt een account aan met eigen gegevens, een of meer kinderen, gewenste dagen en een wachtwoord. Het team krijgt een e-mail en ziet de aanmelding op het dashboard. |
| Automatische incasso bij de aanmelding | `machtiging.php` | Direct na het aanmelden geeft de ouder een SEPA-machtiging via Mollie: een eerste betaling van € 0,01 via de eigen bank. Daarna kan elke factuur automatisch worden afgeschreven. |
| Facturen online terugvinden | `facturen.php`, `factuur.php` | Beheerder maakt per maand de facturen (op basis van de ingeschreven dagen) en start de incasso. Ouders zien, printen en (bij een mislukte incasso) betalen hun facturen online. |
| Kindvolgsysteem | `kind.php` | Per kind een dossier: gegevens, allergieën, ophaalpersonen, aanwezigheid (binnen en opgehaald), foto's en observaties per ontwikkelgebied. Een observatie kan alleen voor het team zijn, of gedeeld worden met de ouders. |
| Foto's sturen naar de ouders van een kind | `fotos.php` | Begeleider kiest foto's en de kinderen die erop staan. Alleen hun verzorgers (met het recht "foto's") zien de foto. Groepsfoto's kan alleen met toestemming van alle ouders; kinderen zonder toestemming staan gemarkeerd. Locatiegegevens (EXIF) worden uit de foto gehaald. |
| Berichten heen en weer, bijvoorbeeld bij ziekte | `berichten.php` | Per gezin één gesprek met het team. Ouders kunnen hun kind ook ziek melden voor een of meer dagen; die dagen worden dan automatisch afgemeld. |
| Overzicht welke kinderen wanneer komen, met maximale groepsgrootte | `agenda.php`, `index.php`, `groepen.php` | Per groep een maximum. Weekoverzicht met bezetting per dag (kleur bij bijna vol en vol) en een dagoverzicht met namen, allergieën en knoppen voor binnen, opgehaald, ziek en afmelden. |
| Digitale agenda die twee kanten op werkt | `agenda.php` | Ouders kiezen zelf dagen (los of vaste weekdagen voor een periode) en melden af tot een ingestelde tijd op de dag zelf. Vol? Dan op de wachtlijst; bij een afmelding schuift de volgende automatisch door en krijgt een bericht. Het team kan ook zelf kinderen toevoegen, afmelden, boven het maximum plaatsen, een dag sluiten of de groepsgrootte voor één dag aanpassen. Ouders krijgen daar automatisch bericht van. |

## De verplichte eisen en hoe ze zijn ingevuld

| Eis | Invulling |
|---|---|
| **Toegang per rol**: medewerkers zien alleen de kinderen waarvoor zij toegang nodig hebben | Elke medewerker is gekoppeld aan een of meer groepen (Team → Groepen), eventueel tijdelijk tot een einddatum (bijvoorbeeld voor een invaller). Kinderen, dossiers, ouders, berichten, foto's, de agenda en het dagoverzicht tonen alleen de eigen groepen. Een kind uit een andere groep geeft "niet gevonden". Kinderen zonder groep (nieuwe aanmeldingen), facturen, instellingen, het logboek en privacyverzoeken zijn alleen voor de beheerder. |
| **Toegang per organisatie**: gegevens van meerdere opvangorganisaties strikt gescheiden | Elke organisatie krijgt een eigen domein, database, fotomap, sessiemap, encryptiesleutel, back-upsleutel en eventueel Mollie-account (zie [Meerdere organisaties](#meerdere-organisaties)). Er is geen gedeelde database waarin een fout filter gegevens kan laten doorlekken. Een onbekend domein krijgt geen toegang; twee organisaties met dezelfde map of sleutel weigert het systeem. |
| **Persoonlijke accounts** | Iedereen logt in met een eigen e-mailadres. Team en tweede verzorgers worden per persoon uitgenodigd met een eenmalige link. Er zijn geen gedeelde accounts. |
| **Meervoudige authenticatie** | Tweestapsverificatie met een authenticator-app (TOTP) en 10 eenmalige herstelcodes. Verplicht voor het hele team (kan niet worden overgeslagen); voor ouders aanbevolen of verplicht, naar keuze van de beheerder. Een code werkt maar één keer. |
| **Versleuteling** | Https met HSTS. Gevoelige velden (bijzonderheden en allergieën, ophaalpersonen, observaties, berichten, privacyverzoeken, het MFA-geheim) en alle foto's en exports worden versleuteld opgeslagen (libsodium, XSalsa20-Poly1305) met een sleutel die niet in de database staat. Wachtwoorden en herstelcodes alleen als hash. |
| **Veilige back-ups** | `php beheer/cli/backup.php` maakt dagelijks een versleutelde back-up (XChaCha20-Poly1305, eigen sleutel) van database, foto's en verwijderregister. Back-ups worden na 30 dagen automatisch verwijderd. |
| **Bijgewerkte software** | PHP 8.2 of hoger, geen pakketten van derden (alleen één meegeleverde QR-bibliotheek). De pagina Beveiligingsstatus waarschuwt als de PHP-versie binnen drie maanden geen beveiligingsupdates meer krijgt. |
| **Controleerbaarheid** | Het logboek registreert inzage (dossier bekeken, foto's, berichten, facturen), wijzigingen, exports, inloggen en beveiligingsacties, per kind of ouder te filteren. Elke regel is met een hashketen aan de vorige gekoppeld: aanpassen of weghalen valt op bij "Controleer logboek". Het logboek zelf is als CSV te exporteren (dat wordt ook gelogd). Bewaartermijn standaard 24 maanden. |
| **Ouderrechten: inzage, correctie, verwijdering** | Onder **Mijn privacy** downloadt een ouder direct de eigen gegevens (en die van kinderen waarover gecontroleerd gezag bestaat), en dient verzoeken in: inzage, correctie, verwijdering, beperking, bezwaar of overdracht. De beheerder handelt ze af onder **Privacyverzoeken**, met de wettelijke termijn van een maand in beeld, een volledige export (ook interne notities en wie het dossier heeft ingezien) en knoppen om een kinddossier of oudergegevens te wissen. Gegevens die wettelijk bewaard moeten worden (de financiële administratie) blijven staan. |
| **Wie is bevoegd namens het kind** | Per kind staan de verzorgers met hun relatie, of ze gezag hebben, en hun rechten (dagen kiezen, dossier inzien, foto's, berichten). Gezag geldt pas als de beheerder het heeft gecontroleerd (bijvoorbeeld met een uittreksel uit het gezagsregister) en vastgelegd. Alleen iemand met gecontroleerd gezag kan gegevens en toestemmingen wijzigen of een privacyverzoek namens het kind doen. Een account wordt pas geactiveerd als bij elk kind het gezag is gecontroleerd. Een oma of nieuwe partner kan bijvoorbeeld wel foto's zien, maar niets beslissen. |
| **Bewaarbeleid per gegevenscategorie** | Per categorie een eigen termijn (zie [Bewaartermijnen](#bewaartermijnen)), dagelijks automatisch uitgevoerd. Ontwikkelingsgegevens gaan snel weg na vertrek, de financiële administratie blijft 7 jaar. |
| **Verwijderen, ook in de back-upcyclus** | Back-ups vervallen na 30 dagen, dus gewiste gegevens zijn uiterlijk dan ook uit de back-ups verdwenen. Handmatige verwijderingen komen in een verwijderregister (alleen soort en nummer); na het terugzetten van een back-up voert `onderhoud.php --na-herstel` ze opnieuw uit. |

### Rollen

- **Ouder of verzorger**: de kinderen waaraan diegene gekoppeld is, met de rechten die per kind zijn ingesteld. De contracthouder ziet ook de facturen.
- **Medewerker (begeleider)**: alleen de kinderen, ouders, berichten en foto's van de eigen groep(en). Vandaag-overzicht, agenda en kindvolg. Geen facturen, instellingen, logboek of privacyverzoeken.
- **Beheerder**: alles, plus groepen, facturen, het team, instellingen, het logboek, privacyverzoeken en het controleren van gezag.

### Een nieuwe klant, stap voor stap

1. Ouder meldt zich aan via de website en geeft de machtiging af. Bij de aanmelding geeft de ouder aan of die het gezag heeft.
2. Het team ziet de aanmelding op "Vandaag" en onder Ouders → Nieuwe aanmeldingen.
3. Bij de kennismaking: gezag controleren (bijvoorbeeld uittreksel gezagsregister) en vastleggen in het dossier van elk kind, onder **Verzorgers en gezag**. Kind in een groep zetten.
4. Op **Account activeren** klikken. De ouder krijgt een welkomstbericht.
5. Eventueel een tweede verzorger toevoegen in het dossier van het kind (bijvoorbeeld de andere ouder), met de juiste rechten.
6. De ouder kiest de dagen in de agenda.
7. Na afloop van de maand: Facturen → maand kiezen → **Facturen maken** → controleren → **Incasso starten**.

## Bewaartermijnen

De beheerder stelt de termijnen in onder **Instellingen → Bewaartermijnen**. Dit zijn de standaardwaarden; laat ze bevestigen (zie TODO).

| Categorie | Standaard | Wat er gebeurt |
|---|---|---|
| Ontwikkelingsgegevens (observaties) | 3 maanden na vertrek | Verwijderd |
| Kinddossier (gegevens, bijzonderheden, ophaalpersonen, toestemmingen, verzorgers) | 3 maanden na vertrek | Geanonimiseerd; alleen een regel zonder naam blijft voor de financiële administratie |
| Foto's | 12 maanden na plaatsen | Verwijderd, ook de bestanden |
| Berichten en ziekmeldingen | 24 maanden na verzenden | Verwijderd |
| Aanmeldingen die niet tot plaatsing leidden | 6 maanden | Account en kinderen verwijderd |
| Account en contactgegevens van gestopte klanten | 3 maanden na vertrek | Gewist; naam en adres blijven bij de facturen |
| Financiële administratie (facturen, betalingen, opvangdagen) | 7 jaar | Verwijderd. Korter dan 7 jaar kan niet (fiscale bewaarplicht) |
| Logboek | 24 maanden | Verwijderd |
| Exports voor een inzageverzoek | 30 dagen | Verwijderd |
| Back-ups | 30 dagen | Verwijderd |

Het bewaarbeleid draait dagelijks via cron (`php beheer/cli/onderhoud.php`). Zonder cron draait het vanzelf bij het eerste bezoek van de dag.

## Logboek

Instellingen → Logboek. Filter op soort (inzage, wijziging, export, inloggen, beveiliging, AVG-verzoek, bewaarbeleid), op teamlid, of op één kind of ouder (link "Logboek" onderaan het dossier van het kind of de ouder). Met **Controleer logboek** wordt de hashketen nagerekend. Met **Exporteren (CSV)** download je de selectie.

## Back-ups en herstel

```sh
php beheer/cli/backup.php                                    # maakt backup-<organisatie>-<datum>.vckbak
php beheer/cli/herstel.php <back-upbestand> <lege map>       # ontsleutelt en pakt uit
php beheer/cli/onderhoud.php --na-herstel                    # verwijderingen opnieuw uitvoeren
```

Terugzetten, met de website even offline:

1. Pak de back-up uit in een lege map met `herstel.php`.
2. Zet de huidige datamap opzij (niet weggooien).
3. Zet `beheer.sqlite` en `fotos/` uit de back-up in de datamap.
4. Gebruik het **nieuwste** `verwijderregister.jsonl`: uit de opzijgezette datamap als dat er nog is, anders dat uit de back-up.
5. Draai `php beheer/cli/onderhoud.php --na-herstel`. Zo worden gegevens die na de back-up zijn gewist, opnieuw gewist.

Bewaar de back-ups op een andere plek dan de server (in de EU) en de back-upsleutel apart, bijvoorbeeld in een wachtwoordkluis. Test het terugzetten minstens één keer per jaar.

## Meerdere organisaties

Eén installatie kan meerdere opvangorganisaties bedienen. Zet ze in `config.php` onder `organisaties` (zie `config.voorbeeld.php`). Per organisatie:

- eigen domein(en) in `hosts`: het domein bepaalt de organisatie, een onbekend domein krijgt "niet gevonden";
- eigen `data_dir` (database, foto's, exports, sessies, verwijderregister) en eigen `sleutel`;
- eigen back-upmap en back-upsleutel, eventueel een eigen Mollie-account en e-mailafzender.

Cron-regels per organisatie: voeg `--organisatie=naam` toe, bijvoorbeeld `php beheer/cli/backup.php --organisatie=vck`.

## Techniek

- PHP 8.2 of hoger, zonder framework, zonder Composer en zonder build-stap. Extensies: `pdo_sqlite`, `sodium`, `gd`, `intl`, `mbstring`, `curl` (voor Mollie) en liefst `exif`. Die zitten bij vrijwel elke Nederlandse webhosting standaard aan; ontbreekt er een, dan meldt de beheeromgeving dat.
- Database: SQLite, één bestand in de datamap. Het schema wordt automatisch aangemaakt en bijgewerkt (`beheer/inc/database.php`). Een bestaande database van een eerdere versie wordt bij de update vanzelf omgezet, inclusief het versleutelen van bestaande gegevens en foto's.
- Opmaak: dezelfde design tokens en formulierstijlen als de website (`design/tokens.css`, `css/style.css`) plus `beheer/assets/beheer.css`.
- Alles werkt zonder JavaScript; `beheer/assets/beheer.js` maakt het alleen prettiger (en tekent de QR-code voor tweestapsverificatie).

| Map of bestand | Inhoud |
|---|---|
| `beheer/*.php` | De pagina's |
| `beheer/inc/` | Gedeelde code: configuratie, organisaties, versleuteling, database, inloggen, tweestapsverificatie, toegang, agenda, berichten, facturen, Mollie, e-mail, foto's, exports, bewaarbeleid, opmaak. Niet rechtstreeks bereikbaar. |
| `beheer/cli/` | Opdrachtregel: back-up, herstel en dagelijks onderhoud. Niet via de website bereikbaar. |
| `beheer/data/` | Standaard-datamap: database, foto's, exports, sessies en `mail.log`. Niet rechtstreeks bereikbaar. Staat niet in git. Liefst buiten de webroot zetten. |
| `beheer/assets/vendor/` | Meegeleverde QR-code-bibliotheek (MIT-licentie) |
| `beheer/config.voorbeeld.php` | Voorbeeld van de configuratie |
| `tools/beheer-demodata.php` | Vult een lege test-omgeving met verzonnen demogegevens |

### Overige beveiliging

- Alleen via https (wordt afgedwongen buiten `localhost`), met HSTS en strikte beveiligingsheaders (CSP, geen frames, geen caching van pagina's).
- Wachtwoorden met `password_hash` (bcrypt), minimaal 10 tekens. Na 5 foute pogingen per e-mailadres of IP-adres 15 minuten geblokkeerd; ook voor de code van tweestapsverificatie. Sessies verlopen na 2 uur zonder activiteit.
- Elk formulier heeft een CSRF-token. Alle uitvoer wordt ge-escaped. Alle databasequery's gebruiken prepared statements.
- Foto's staan buiten de publieke map en worden alleen via `foto.php` geleverd, na controle. Bij het opslaan wordt de foto opnieuw opgeslagen als JPEG, waardoor EXIF (zoals gps-locatie) verdwijnt.
- E-mails bevatten geen inhoud over kinderen, alleen een seintje met een link.
- **Beveiligingsstatus** (Instellingen → Beveiligingsstatus) laat zien of alles in orde is: https, sleutel, datamap, PHP-versie, tweestapsverificatie, toegang per groep, gecontroleerd gezag, back-ups, bewaarbeleid en privacyverzoeken.

## Live zetten

1. **Hosting met PHP 8.2+** en https (de statische website alleen kan op elke host, de beheeromgeving niet). Upload de hele site inclusief `beheer/`.
2. Maak twee sleutels, één voor de gegevens en één voor de back-ups:
   ```sh
   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
   ```
   Bewaar ze **ook buiten de server**, bijvoorbeeld in een wachtwoordkluis. Zonder de sleutels zijn de gegevens en back-ups onbruikbaar.
3. Kopieer `beheer/config.voorbeeld.php` naar `beheer/config.php` en vul in:
   - `app_url`: bijvoorbeeld `https://bsovck.nl/beheer`
   - `data_dir`: een map **buiten de webroot**, bijvoorbeeld `/home/account/beheer-data`
   - `sleutel`: de eerste sleutel. Nooit meer wijzigen.
   - `backup.map` (buiten de webroot) en `backup.sleutel`: de tweede sleutel
   - `installatiecode`: een lange, willekeurige code
   - `mail.methode`: `mail` (of laat op `log` zolang je test)
4. Stel twee dagelijkse taken (cron) in, bijvoorbeeld:
   ```cron
   15 3 * * * php /pad/naar/site/beheer/cli/onderhoud.php
   45 3 * * * php /pad/naar/site/beheer/cli/backup.php
   ```
   Kopieer de back-upmap daarnaast dagelijks naar een andere plek (in de EU), bijvoorbeeld met de back-updienst van de hosting.
5. Ga naar `https://bsovck.nl/beheer/`. Je komt op de installatiepagina: vul de installatiecode in, maak het eerste beheerdersaccount aan en stel tweestapsverificatie in. Maak daarna de `installatiecode` weer leeg.
6. Vul de **Instellingen** in (KvK, LRK, IBAN, uurtarief, uren per middag, afmeldtijd, bewaartermijnen, tweestapsverificatie voor ouders) en maak de **groepen** aan.
7. Nodig de begeleiders uit onder **Team** en koppel ze aan hun groep(en).
8. Koppel **Mollie** (zie hieronder). Zonder Mollie draait alles in demo-modus: er wordt niets echt afgeschreven.
9. Controleer **Instellingen → Beveiligingsstatus**: alles moet op "In orde" staan.

### Apache en nginx

Op Apache regelen de `.htaccess`-bestanden dat `beheer/inc/`, `beheer/cli/` en `beheer/data/` niet bereikbaar zijn. Op nginx moet dat in de serverconfiguratie:

```nginx
location ~ ^/beheer/(inc|cli|data)/ { deny all; return 404; }
location = /beheer/config.php { deny all; return 404; }
location ~ ^/beheer/.+\.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php-fpm.sock; }
client_max_body_size 64m;
```

Zet voor foto's van telefoons in PHP: `upload_max_filesize = 16M`, `post_max_size = 64M`.

### Mollie koppelen

1. Maak een account aan op [mollie.com](https://www.mollie.com/) en activeer **iDEAL** en **SEPA-incasso** (SEPA Direct Debit). Mollie controleert daarvoor eerst het bedrijf.
2. Zet de **test**-API-sleutel (`test_...`) in `config.php` bij `mollie.api_key` en probeer het hele proces: aanmelden, machtiging, facturen maken, incasso starten.
3. Werkt alles? Vervang de test-sleutel door de **live**-sleutel.

Mollie meldt de uitkomst van betalingen via `https://bsovck.nl/beheer/mollie-webhook.php`. Dat gaat vanzelf; de webhook moet wel via internet bereikbaar zijn. Een mislukte incasso of terugboeking zet de factuur op "mislukt" en de ouder krijgt een bericht met de mogelijkheid om los te betalen.

### Lokaal uitproberen

```sh
cp beheer/config.voorbeeld.php beheer/config.php   # vul een installatiecode in en zet 'ontwikkelmodus' => true
php -S localhost:8000                              # in de hoofdmap van het project
php tools/beheer-demodata.php                      # optioneel: demogegevens
```

Open daarna `http://localhost:8000/beheer/`. Met de demogegevens log je in met bijvoorbeeld `beheerder@demo.bsovck.nl` en wachtwoord `demo-wachtwoord`; bij de eerste keer stel je tweestapsverificatie in. E-mails komen in `beheer/data/mail.log`. In ontwikkelmodus staat de sleutel in de datamap; gebruik dat nooit op de echte omgeving.

## Nog te doen (TODO)

**Van de klant**
- [ ] Uurtarief en aantal uren per middag invullen (Instellingen). Nu leeg, dus facturen maken kan nog niet.
- [ ] Factuurbeleid bevestigen: nu factureren we achteraf per maand, per ingeschreven dag. Ziekte telt mee, op tijd afmelden niet. Wil de klant vaste contracten per maand, vooraf factureren of te laat afmelden meerekenen? Dat moet dan worden aangepast in `beheer/inc/facturen.php`.
- [ ] KvK-, LRK- en IBAN-nummer invullen (Instellingen). Het LRK-nummer moet op de factuur staan voor de kinderopvangtoeslag.
- [ ] Afmeldtijd bevestigen (nu 12:00 uur op de dag zelf).
- [ ] Btw-regel op de factuur laten controleren door de boekhouder (nu: "Kinderopvang is vrijgesteld van btw").
- [ ] Werkafspraak voor het controleren van gezag: welk bewijs vraag je (uittreksel gezagsregister, geboorteakte, beschikking) en wie controleert het?

**Privacy (AVG)**
- [ ] DPIA uitvoeren: het systeem verwerkt gegevens van kinderen, waaronder gezondheidsgegevens (allergieën, ziekte) en foto's.
- [ ] Bewaartermijnen laten bevestigen door de klant en een privacyjurist. De standaardwaarden hierboven zijn een voorstel, geen juridisch advies.
- [ ] Verwerkersovereenkomsten met de hostingpartij, de back-upopslag en Mollie.
- [ ] Toestemmingsformulier voor foto's op website en social media (los van de toestemming voor groepsfoto's in Mijn BSO).
- [ ] Procedure voor een datalek vastleggen (melden bij de Autoriteit Persoonsgegevens binnen 72 uur).

**Techniek**
- [ ] Hosting met PHP 8.2+ en https, datamap buiten de webroot, twee sleutels (ook in een wachtwoordkluis), cron voor onderhoud en back-up, back-ups naar een tweede locatie.
- [ ] Terugzetten van een back-up één keer testen op een testomgeving.
- [ ] Mollie-account, eerst testen met de test-sleutel.
- [ ] E-mail via een betrouwbare SMTP-dienst als de `mail()`-functie van de hosting in de spam belandt (nu: `mail.methode = mail`).
- [ ] PHP 8.2 krijgt beveiligingsupdates tot eind 2026: kies bij de hosting liefst PHP 8.3 of nieuwer.
- [ ] Jaaropgave voor de kinderopvangtoeslag per ouder (overzicht van uren en bedragen per kind per jaar).
- [ ] Engelse versie van Mijn BSO (nu alleen Nederlands).
