# Live zetten bij Vimexx (Managed VPS)

De website en Mijn BSO komen samen op één **Managed VPS bij Vimexx** (datacenter BIT in Ede). Vimexx doet het onderhoud en de beveiliging van de server; het beheerpaneel is DirectAdmin. De gegevens van ouders en kinderen staan alleen op deze server, buiten de openbare map.

Vervang hieronder `JOUWDOMEIN.nl` door het echte domein en `GEBRUIKER` door de gebruikersnaam in DirectAdmin.

Getest op dezelfde soort opzet (Apache met PHP 8.3 via PHP-FPM, zoals DirectAdmin): het uploadpakket, de doorverwijzingen naar https en zonder www, de beveiligingskoppen, de afscherming van code, configuratie en gegevens, de installatie, de inlogcookie (alleen via https), de uploadlimieten voor foto's, het dagelijkse onderhoud en de versleutelde back-up.

## Wie doet wat

| Wie | Wat |
|---|---|
| **Sporty** | Managed VPS bestellen, de verwerkersovereenkomst met Vimexx tekenen, de sleutels veilig bewaren |
| **Beheerder van de site** (wie toegang krijgt tot DirectAdmin) | Stap 2 tot en met 8 |
| **Ontwikkelaar** | Het domein in de site zetten (stap 0) en het uploadpakket maken (`npm run pakket`) |

## 0. Vooraf

- [ ] **Domein kiezen.** In de site staat nog `bsovck.nl` (sitemap, robots.txt, de kop van elke pagina en de gegevens voor Google). Geef het echte domein door; de ontwikkelaar past het overal aan, draait `npm run seo` en `npm test` en maakt daarna het pakket. Zie ook `docs/seo.md`.
- [ ] **Vraag Vimexx** (bijvoorbeeld via de klantenservice):
  1. Is PHP 8.2 of hoger beschikbaar, met de extensies `pdo_sqlite`, `sodium`, `gd`, `intl`, `mbstring`, `curl` (en liefst `exif`)?
  2. Zijn SSH en cronjobs beschikbaar, en wat is het pad naar PHP voor cronjobs (bijvoorbeeld `/usr/local/bin/php`)?
  3. Kan tweestapsverificatie aan op het klantaccount en in DirectAdmin?
  4. Hoe vaak maakt Vimexx back-ups van de VPS, hoe lang bewaren jullie die en waar staan ze?
  5. Graag de verwerkersovereenkomst om te tekenen.

## 1. Bestellen en beveiligen

- [ ] Bestel een **Managed VPS** (het pakket Start is ruim genoeg om mee te beginnen).
- [ ] Zet **tweestapsverificatie** aan op het klantaccount van Vimexx en in DirectAdmin.
- [ ] Teken de **verwerkersovereenkomst**.
- [ ] Zet niets anders op deze server (geen andere websites of WordPress): één lek daarin geeft anders toegang tot alles.

## 2. Domein, PHP en certificaat in DirectAdmin

- [ ] Voeg het domein toe in DirectAdmin (als het er nog niet staat).
- [ ] Kies **PHP 8.2 of hoger** (getest met 8.3).
- [ ] Het https-certificaat (Let's Encrypt) vraag je aan in stap 6, zodra het domein naar de server wijst.

## 3. Bestanden uploaden

- [ ] De ontwikkelaar maakt het pakket: `npm run pakket` geeft `pakket/sporty-website.zip`. Er zit alleen in wat online moet: geen gegevens, geen echte configuratie, geen voorbeeld en geen ontwikkelbestanden. Het script controleert ook dat elke link naar een bestaand bestand wijst.
- [ ] Verwijder in `domains/JOUWDOMEIN.nl/public_html` de standaardpagina van DirectAdmin (`index.html`).
- [ ] Upload de zip naar `domains/JOUWDOMEIN.nl/public_html` en pak hem daar uit (Bestandsbeheer → uitpakken).
- [ ] Let op dat de verborgen bestanden `.htaccess` (in de hoofdmap en in `beheer/`) en `beheer/.user.ini` erbij staan.

## 4. Mappen, sleutels en configuratie

- [ ] Maak **buiten** `public_html` twee mappen:
  - `/home/GEBRUIKER/beheer-data` (database, foto's, sessies)
  - `/home/GEBRUIKER/beheer-backups` (de dagelijkse versleutelde back-ups)
- [ ] Maak via SSH twee sleutels (twee keer uitvoeren, twee verschillende uitkomsten):
  ```sh
  php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
  ```
  Bewaar ze **meteen** in een wachtwoordkluis, met een tweede kopie op een veilige plek. Zonder deze sleutels zijn de gegevens en back-ups onbruikbaar, ook voor Sporty zelf. De eerste sleutel verander je daarna nooit meer.
- [ ] Kopieer `public_html/beheer/config.voorbeeld.php` naar `public_html/beheer/config.php` en vul in (de webserver geeft dit bestand nooit vrij):
  ```php
  return [
      'app_url' => 'https://JOUWDOMEIN.nl/beheer',
      'data_dir' => '/home/GEBRUIKER/beheer-data',
      'sleutel' => 'EERSTE-SLEUTEL',
      'ontwikkelmodus' => false,
      'installatiecode' => 'EEN-LANGE-WILLEKEURIGE-CODE',
      'backup' => ['map' => '/home/GEBRUIKER/beheer-backups', 'sleutel' => 'TWEEDE-SLEUTEL', 'bewaardagen' => 30],
  ];
  ```
  Stripe en e-mail vul je hier níet in; dat doe je in Mijn BSO onder Koppelingen (stap 7).

## 5. Geplande taken (cronjobs) in DirectAdmin

- [ ] Voeg twee dagelijkse taken toe (pas het pad naar PHP aan als Vimexx een ander noemt):
  ```cron
  15 3 * * * /usr/local/bin/php /home/GEBRUIKER/domains/JOUWDOMEIN.nl/public_html/beheer/cli/onderhoud.php
  45 3 * * * /usr/local/bin/php /home/GEBRUIKER/domains/JOUWDOMEIN.nl/public_html/beheer/cli/backup.php
  ```
  Het onderhoud wist gegevens volgens de bewaartermijnen; de back-up maakt elke nacht een versleutelde kopie en ruimt kopieën ouder dan 30 dagen op.

## 6. DNS bij Vimexx

- [ ] Verlaag een dag van tevoren de TTL van de records hieronder (bijvoorbeeld naar 300 seconden), dan gaat de overstap sneller.
- [ ] Zet het **A-record** van `JOUWDOMEIN.nl` (`@`) op het IP-adres van de VPS, en `www` op hetzelfde adres (A-record of CNAME naar `@`).
- [ ] **Laat de mailrecords staan** (MX, SPF, DKIM, DMARC): de e-mail op het domein is in gebruik en mag niet verstoord worden. Verstuurt Mijn BSO later via "Mailfunctie van de webserver", dan moet SPF het IP-adres van de VPS toestaan; via een eigen mailserver of Google Workspace (Koppelingen) hoeft er niets te veranderen.
- [ ] Wijst het domein naar de VPS (controleer met `https://JOUWDOMEIN.nl`), vraag dan in DirectAdmin het **Let's Encrypt-certificaat** aan voor `JOUWDOMEIN.nl` en `www.JOUWDOMEIN.nl`. Daarna stuurt de site alles vanzelf door naar https en naar het adres zonder www.

## 7. Mijn BSO installeren en instellen

- [ ] Ga naar `https://JOUWDOMEIN.nl/beheer/`, vul de installatiecode in, maak het eerste beheerdersaccount en stel tweestapsverificatie in.
- [ ] Maak `installatiecode` in `config.php` daarna weer leeg.
- [ ] **Instellingen:** KvK, LRK, IBAN, uurtarief, uren per middag, afmeldtijd, bewaartermijnen, tweestapsverificatie voor ouders. Maak de **groepen** aan en nodig het team uit onder **Team**.
- [ ] **Koppelingen → E-mail versturen:** kies de eigen mailserver (of Google Workspace) en stuur een testmail.
- [ ] **Koppelingen → Stripe:** eerst de testsleutel en een testwebhook naar `https://JOUWDOMEIN.nl/beheer/stripe-webhook.php`; als alles werkt de live-sleutel. Zie "Stripe koppelen" in `docs/beheeromgeving.md`.

## 8. Controleren

- [ ] **Instellingen → Beveiligingsstatus:** alles op "In orde".
- [ ] `http://JOUWDOMEIN.nl` en `https://www.JOUWDOMEIN.nl` komen uit op `https://JOUWDOMEIN.nl`.
- [ ] `https://JOUWDOMEIN.nl/beheer/config.php` geeft "Forbidden" (403).
- [ ] Een proefdag boeken en een aanmelding via de website komen binnen in Mijn BSO.
- [ ] "Wachtwoord vergeten" stuurt een e-mail die aankomt.
- [ ] De volgende ochtend staat er een back-up in `/home/GEBRUIKER/beheer-backups`.

## Daarna

- **Externe back-up:** zet de back-ups ook buiten de server, bij een andere partij in de EU (advies: Europese objectopslag met object lock). Nog te bouwen.
- **Een nieuwe versie zetten:** maak een nieuw pakket en pak het uit over `public_html` heen. `config.php` en de gegevens blijven staan, want die zitten niet in het pakket en de gegevens staan buiten `public_html`.
- **Vercel:** het voorbeeld op Vercel kan blijven bestaan als testomgeving of worden opgezegd. Zodra de DNS naar Vimexx wijst, komen bezoekers van het domein bij Vimexx uit.
