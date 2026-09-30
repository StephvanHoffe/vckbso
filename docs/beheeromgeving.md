# Beheeromgeving en Mijn BSO (`/beheer/`)

De beheeromgeving is een eigen webapplicatie naast de statische website. Ouders gebruiken hem als **Mijn BSO**, het team als **Beheer**. Alles staat in de map `beheer/`.

## Wat kan het?

| Wens van de klant | Waar | Hoe het werkt |
|---|---|---|
| Aanmelden op de website en als klant in het systeem komen, met de kinderen eronder | `aanmelden.php` (knop op `aanmelden.html`) | Ouder maakt een account aan met eigen gegevens, een of meer kinderen, gewenste dagen en een wachtwoord. Het team krijgt een e-mail en ziet de aanmelding op het dashboard. |
| Automatische incasso bij de aanmelding | `machtiging.php` | Direct na het aanmelden geeft de ouder een SEPA-machtiging via Mollie: een eerste betaling van € 0,01 via de eigen bank. Daarna kan elke factuur automatisch worden afgeschreven. |
| Facturen online terugvinden | `facturen.php`, `factuur.php` | Beheerder maakt per maand de facturen (op basis van de ingeschreven dagen) en start de incasso. Ouders zien, printen en (bij een mislukte incasso) betalen hun facturen online. |
| Kindvolgsysteem | `kind.php` | Per kind een dossier: gegevens, allergieën, ophaalpersonen, aanwezigheid (binnen en opgehaald), foto's en observaties per ontwikkelgebied. Een observatie kan alleen voor het team zijn, of gedeeld worden met de ouders. |
| Foto's sturen naar de ouders van een kind | `fotos.php` | Begeleider kiest foto's en de kinderen die erop staan. Alleen hun ouders zien de foto. Groepsfoto's kan alleen met toestemming van alle ouders; kinderen zonder toestemming staan gemarkeerd. Locatiegegevens (EXIF) worden uit de foto gehaald. |
| Berichten heen en weer, bijvoorbeeld bij ziekte | `berichten.php` | Per gezin één gesprek met het team. Ouders kunnen hun kind ook ziek melden voor een of meer dagen; die dagen worden dan automatisch afgemeld. |
| Overzicht welke kinderen wanneer komen, met maximale groepsgrootte | `agenda.php`, `index.php`, `groepen.php` | Per groep een maximum. Weekoverzicht met bezetting per dag (kleur bij bijna vol en vol) en een dagoverzicht met namen, allergieën en knoppen voor binnen, opgehaald, ziek en afmelden. |
| Digitale agenda die twee kanten op werkt | `agenda.php` | Ouders kiezen zelf dagen (los of vaste weekdagen voor een periode) en melden af tot een ingestelde tijd op de dag zelf. Vol? Dan op de wachtlijst; bij een afmelding schuift de volgende automatisch door en krijgt een bericht. Het team kan ook zelf kinderen toevoegen, afmelden, boven het maximum plaatsen, een dag sluiten of de groepsgrootte voor één dag aanpassen. Ouders krijgen daar automatisch bericht van. |

### Rollen

- **Ouder**: eigen kinderen, agenda, berichten, foto's, facturen en eigen gegevens.
- **Medewerker (begeleider)**: vandaag-overzicht, agenda, kinderen, kindvolg, ouders (contactgegevens), berichten en foto's. Geen facturen of instellingen.
- **Beheerder**: alles, plus groepen, facturen, het team, instellingen en het logboek.

### Een nieuwe klant, stap voor stap

1. Ouder meldt zich aan via de website en geeft de machtiging af.
2. Het team ziet de aanmelding op "Vandaag" en onder Ouders → Nieuwe aanmeldingen.
3. Na de kennismaking: kinderen in een groep zetten en op **Account activeren** klikken. De ouder krijgt een welkomstbericht.
4. De ouder kiest de dagen in de agenda.
5. Na afloop van de maand: Facturen → maand kiezen → **Facturen maken** → controleren → **Incasso starten**.

## Techniek

- PHP 8.1 of hoger, zonder framework, zonder Composer en zonder build-stap. Extensies: `pdo_sqlite`, `gd`, `curl`, `intl`, `mbstring`, `fileinfo` en liefst `exif`. Die zitten bij vrijwel elke Nederlandse webhosting standaard aan.
- Database: SQLite, één bestand in de datamap. Het schema wordt automatisch aangemaakt en bijgewerkt (`beheer/inc/database.php`).
- Opmaak: dezelfde design tokens en formulierstijlen als de website (`design/tokens.css`, `css/style.css`) plus `beheer/assets/beheer.css`.
- Alles werkt zonder JavaScript; `beheer/assets/beheer.js` maakt het alleen prettiger.

| Map of bestand | Inhoud |
|---|---|
| `beheer/*.php` | De pagina's |
| `beheer/inc/` | Gedeelde code: configuratie, database, inloggen, agenda, facturen, Mollie, e-mail, foto's, opmaak. Niet rechtstreeks bereikbaar. |
| `beheer/data/` | Database, foto's en `mail.log`. Niet rechtstreeks bereikbaar. Staat niet in git. |
| `beheer/config.voorbeeld.php` | Voorbeeld van de configuratie |
| `tools/beheer-demodata.php` | Vult een lege test-omgeving met verzonnen demogegevens |

### Beveiliging en privacy

- Alleen via https (wordt afgedwongen buiten `localhost`), met HSTS en strikte beveiligingsheaders (CSP, geen frames, geen caching van pagina's).
- Wachtwoorden met `password_hash` (bcrypt), minimaal 10 tekens. Na 5 foute pogingen per e-mailadres 15 minuten geblokkeerd. Sessies verlopen na 2 uur zonder activiteit.
- Elk formulier heeft een CSRF-token. Alle uitvoer wordt ge-escaped. Alle databasequery's gebruiken prepared statements.
- Toegangscontrole per pagina en per gegeven: een ouder kan alleen de eigen kinderen, foto's en facturen openen (anders "niet gevonden").
- Foto's staan buiten de publieke map en worden alleen via `foto.php` geleverd, na controle. Bij het opslaan wordt de foto opnieuw opgeslagen als JPEG, waardoor EXIF (zoals gps-locatie) verdwijnt.
- E-mails bevatten geen inhoud over kinderen, alleen een seintje met een link.
- Logboek (Instellingen → Logboek) van inloggen en wijzigingen.
- Gegevens wissen: bij een gestopte klant kan de beheerder alle gegevens van ouder en kinderen wissen. Naam, adres en facturen blijven bewaard (fiscale bewaarplicht).

## Live zetten

1. **Hosting met PHP 8.1+** (de statische website alleen kan op elke host, de beheeromgeving niet). Upload de hele site inclusief `beheer/`.
2. Kopieer `beheer/config.voorbeeld.php` naar `beheer/config.php` en vul in:
   - `app_url`: bijvoorbeeld `https://bsovck.nl/beheer`
   - `data_dir`: liefst een map **buiten de webroot**, bijvoorbeeld `/home/account/beheer-data`
   - `installatiecode`: een lange, willekeurige code
   - `mail.methode`: `mail` (of laat op `log` zolang je test)
3. Ga naar `https://bsovck.nl/beheer/`. Je komt op de installatiepagina: vul de installatiecode in en maak het eerste beheerdersaccount aan. Maak daarna de `installatiecode` weer leeg.
4. Vul de **Instellingen** in (KvK, LRK, IBAN, uurtarief, uren per middag, afmeldtijd) en maak de **groepen** aan.
5. Nodig de begeleiders uit onder **Team**.
6. Koppel **Mollie** (zie hieronder). Zonder Mollie draait alles in demo-modus: er wordt niets echt afgeschreven.
7. Zorg voor **dagelijkse back-ups** van de datamap (database en foto's), versleuteld en in de EU.

### Apache en nginx

Op Apache regelen de `.htaccess`-bestanden dat `beheer/inc/` en `beheer/data/` niet bereikbaar zijn. Op nginx moet dat in de serverconfiguratie:

```nginx
location ~ ^/beheer/(inc|data)/ { deny all; return 404; }
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
cp beheer/config.voorbeeld.php beheer/config.php   # vul een installatiecode in
php -S localhost:8000                              # in de hoofdmap van het project
php tools/beheer-demodata.php                      # optioneel: demogegevens
```

Open daarna `http://localhost:8000/beheer/`. Met de demogegevens log je in met bijvoorbeeld `beheerder@demo.bsovck.nl` en wachtwoord `demo-wachtwoord`. E-mails komen in `beheer/data/mail.log`.

## Nog te doen (TODO)

**Van de klant**
- [ ] Uurtarief en aantal uren per middag invullen (Instellingen). Nu leeg, dus facturen maken kan nog niet.
- [ ] Factuurbeleid bevestigen: nu factureren we achteraf per maand, per ingeschreven dag. Ziekte telt mee, op tijd afmelden niet. Wil de klant vaste contracten per maand, vooraf factureren of te laat afmelden meerekenen? Dat moet dan worden aangepast in `beheer/inc/facturen.php`.
- [ ] KvK-, LRK- en IBAN-nummer invullen (Instellingen). Het LRK-nummer moet op de factuur staan voor de kinderopvangtoeslag.
- [ ] Afmeldtijd bevestigen (nu 12:00 uur op de dag zelf).
- [ ] Btw-regel op de factuur laten controleren door de boekhouder (nu: "Kinderopvang is vrijgesteld van btw").

**Privacy (AVG)**
- [ ] DPIA uitvoeren: het systeem verwerkt gegevens van kinderen, waaronder gezondheidsgegevens (allergieën, ziekte) en foto's.
- [ ] Verwerkersovereenkomsten met de hostingpartij en Mollie.
- [ ] Bewaartermijnen vaststellen en in de privacyverklaring zetten. Wissen gaat nu met de hand per gestopte klant.
- [ ] Toestemmingsformulier voor foto's op website en social media (los van de toestemming voor groepsfoto's in Mijn BSO).

**Techniek**
- [ ] Hosting met PHP en https, datamap buiten de webroot, dagelijkse back-ups.
- [ ] Mollie-account, eerst testen met de test-sleutel.
- [ ] E-mail via een betrouwbare SMTP-dienst als de `mail()`-functie van de hosting in de spam belandt (nu: `mail.methode = mail`).
- [ ] Tweestapsverificatie voor medewerkers (aanbevolen, want zij kunnen alle kinddossiers zien).
- [ ] Jaaropgave voor de kinderopvangtoeslag per ouder (overzicht van uren en bedragen per kind per jaar).
- [ ] Engelse versie van Mijn BSO (nu alleen Nederlands).
