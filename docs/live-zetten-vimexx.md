# Live zetten: de website op Vercel, Mijn BSO bij Vimexx

Sinds november 2026 staat Sporty op twee plekken:

| Wat | Waar | Adres |
|---|---|---|
| **De website** (alle pagina's, ook de binnenkort-pagina) | **Vercel** | `https://bsosporty.nl` |
| **Mijn BSO** (ouders, kinderen, agenda, foto's, facturen) en de **mail** | **Vimexx** (DirectAdmin) | `https://mijn.bsosporty.nl/beheer/` |

De gegevens van ouders en kinderen staan alleen bij Vimexx, buiten de openbare map. Vercel heeft alleen de pagina's van de website. De formulieren op de website (aanmelden, proefdag, Blijf op de hoogte, solliciteren) sturen hun gegevens rechtstreeks naar Mijn BSO op mijn.bsosporty.nl.

Vervang hieronder `GEBRUIKER` door de gebruikersnaam in DirectAdmin. Getest op dezelfde soort opzet: Apache met PHP 8.3 via PHP-FPM, met Mijn BSO in een subdomeinmap zoals DirectAdmin die maakt (`public_html/mijn`). Sinds 25 november 2026 staat mijn.bsosporty.nl als eigen domein in DirectAdmin (map `domains/mijn.bsosporty.nl/public_html`); Mijn BSO vindt de configuratie in beide gevallen naast de `public_html` waar het in staat. Ook getest: de formulieren van "bsosporty.nl" naar "mijn.bsosporty.nl" in de browser, met de beveiliging die browsers daarvoor gebruiken (CORS).

## Hoe het werkt

- **De binnenkort-pagina (coming soon).** Zolang de website niet live is, zien bezoekers van bsosporty.nl alleen de binnenkort-pagina (`binnenkort.html`, Engels `en/coming-soon.html`). Daar laten ouders hun gegevens achter, net als bij Blijf op de hoogte, met een bevestiging per e-mail. Andere pagina's sturen tijdelijk door naar de binnenkort-pagina. Dat regelt `middleware.js` op Vercel: die vraagt aan Mijn BSO of de website live is (`https://mijn.bsosporty.nl/beheer/website-status.php`).
- **Aanmeldingen** komen in Mijn BSO onder **Op de hoogte**, met "via binnenkort-pagina".
- **De echte website alvast bekijken:** Mijn BSO → Op de hoogte → "Bekijk de echte website alvast". Dat zet een cookie in jouw browser (gemak, geen beveiliging: de inhoud is niet geheim).
- **Live zetten:** Mijn BSO → Op de hoogte → **Website live zetten** (met het recht Instellingen). Binnen een minuut ziet iedereen de hele website, en iedereen die zich op de binnenkort-pagina aanmeldde en dat heeft bevestigd, krijgt **automatisch een mail** dat de website live is (Nederlands of Engels; aan te passen onder Instellingen → E-mails → "Binnenkort-pagina: de website is live"). Wie zich daarna nog bevestigt, krijgt de mail meteen; mislukt er een, dan probeert het dagelijkse onderhoud het opnieuw. Niemand krijgt hem twee keer. Terug naar de binnenkort-pagina kan ook ("Toch terug naar de binnenkort-pagina").
- **Is Mijn BSO even niet bereikbaar**, dan onthoudt de website de laatst bekende stand; weet hij niets, dan toont hij de binnenkort-pagina. Na de livegang zet de ontwikkelaar in `middleware.js` de stand `STANDAARD` op `"live"` (staat als TODO in het bestand), dan hangt de website daar niet meer van af.
- **Op Vercel staat alleen wat de website nodig heeft** (zie `.vercelignore`): geen documentatie, broncode van Mijn BSO, tests of ontwerpbestanden. `bsosporty.nl/beheer/…` stuurt door naar mijn.bsosporty.nl (`vercel.json`). Het voorbeeld van Mijn BSO (`/voorbeeld/`) is alleen te zien op het Vercel-adres van het project, niet op bsosporty.nl.

## Wie doet wat

| Wie | Wat |
|---|---|
| **Sporty** | Vercel Pro afsluiten, de verwerkersovereenkomsten met Vercel en Vimexx, de sleutels veilig bewaren |
| **Beheerder van de site** (wie toegang heeft tot Vercel en DirectAdmin) | Stap 1 tot en met 7 |
| **Ontwikkelaar** | Het pakket voor Mijn BSO maken (`npm run pakket -- --mijnbso`) en de website bijwerken (een push naar de branch die Vercel live zet) |

## 1. Vercel (de website)

- [ ] **Abonnement:** het gratis Hobby-abonnement van Vercel is alleen voor persoonlijk, niet-commercieel gebruik. Sluit voor Sporty **Vercel Pro** af.
- [ ] **Domeinen** (Project → Settings → Domains): maak `bsosporty.nl` het hoofdadres en laat `www.bsosporty.nl` daarnaar doorsturen (Edit bij www → "Redirect to bsosporty.nl"). De site, de sitemap en Google gebruiken het adres zonder www.
- [ ] **Branch** (Project → Settings → Git → Production Branch): de branch waar de ontwikkelaar de website op zet. Controleer na een wijziging dat bsosporty.nl de nieuwe versie toont.
- [ ] **DNS** (bij Vimexx, in DirectAdmin → DNS-beheer): `@` (A) → `216.150.1.1` en `www` (CNAME) → het adres dat Vercel noemt (`…vercel-dns-017.com.`). Vercel zet "Invalid Configuration" vanzelf op groen zodra de wijziging overal bekend is (meestal binnen een paar uur; klik dan op Refresh).
- [ ] **Verwerkersovereenkomst:** Vercel heeft een standaard verwerkersovereenkomst (Data Processing Addendum); Vercel ziet de IP-adressen van bezoekers van de website.

## 2. mijn.bsosporty.nl als eigen domein (Vimexx, DirectAdmin)

- [x] **Verwerkersovereenkomst Vimexx** (ontvangen op 25 november 2026): de standaardovereenkomst van Vimexx (https://www.vimexx.nl/storage/Verwerkersovereenkomst-Vimexx-14-05-2018.pdf, versie van 22 september 2020). Die komt vanzelf tot stand met het hostingcontract; ondertekenen hoeft niet. Bewaar een kopie bij de administratie. Wat erin staat: gegevens blijven binnen de EU (art. 4), Vimexx meldt een datalek "binnen een redelijke termijn" en Sporty meldt het zelf binnen 72 uur bij de Autoriteit Persoonsgegevens (art. 7), en na het opzeggen heb je 30 dagen om je gegevens op te halen (art. 12).
- [ ] **Let op, bijzondere persoonsgegevens:** artikel 3 noemt alleen "niet bijzondere persoonsgegevens". Mijn BSO bewaart ook allergieën en medicijnen van kinderen (gezondheidsgegevens; versleuteld, maar de sleutel staat op dezelfde server). Vraag Vimexx schriftelijk te bevestigen dat de overeenkomst ook voor die gegevens geldt, en neem het mee in de controle van de privacyverklaring.
- [ ] **Vraag Vimexx** (als dat nog niet bekend is): PHP 8.2 of hoger met de extensies `pdo_sqlite`, `sodium`, `gd`, `intl`, `mbstring`, `curl` (liefst ook `exif`); cronjobs; het pad naar PHP voor cronjobs (bijvoorbeeld `/usr/local/bin/php`); SSH; tweestapsverificatie op het klantaccount; hoe vaak ze back-ups maken; de verwerkersovereenkomst.
- [ ] Zet **tweestapsverificatie** aan op het klantaccount van Vimexx en in DirectAdmin.
- [ ] Voeg **mijn.bsosporty.nl als eigen domein** toe, volgens de handleiding van Vimexx "[Subdomein als volwaardig domein toevoegen](https://www.vimexx.nl/help/subdomein-als-volwaardig-domein-toevoegen)" (25 november 2026). Als gewoon subdomein van bsosporty.nl lukte het certificaat niet: DirectAdmin nam bsosporty.nl mee in de aanvraag, en dat wijst naar Vercel. Stond `mijn` al als subdomein? Verwijder het eerst onder Subdomeinbeheer (de bestanden mogen blijven staan). Daarna: Domeininstellingen → Voeg een domein toe → `mijn.bsosporty.nl`. De map wordt `domains/mijn.bsosporty.nl/public_html`.
- [ ] DirectAdmin → **DNS-beheer van bsosporty.nl** (niet van mijn.bsosporty.nl): vier records, naar de server van Vimexx (dezelfde adressen als `ftp` en `mail`; op 25 november 2026 stonden ze er al): `mijn` A `185.104.29.40`, `mijn` AAAA `2a06:2ec0:1::97`, `www.mijn` A `185.104.29.40`, `www.mijn` AAAA `2a06:2ec0:1::97`. Neem **niet** het adres van `@` over: dat wijst naar Vercel (216.150.1.1). De mailrecords (MX, SPF, `x._domainkey`, `_dmarc`, `mail`, `smtp`, `pop`) laat je zoals ze zijn.
- [ ] Kies **PHP 8.2 of hoger** voor het domein mijn.bsosporty.nl (getest met 8.3).
- [ ] **Certificaat:** DirectAdmin → SSL-certificaten, domein **mijn.bsosporty.nl** → Let's Encrypt, met `mijn.bsosporty.nl` en `www.mijn.bsosporty.nl` (andere namen zoals `mail.mijn.bsosporty.nl` uit). Doe dit zodra de records overal bekend zijn (2 à 4 uur), en vóór het uploaden: de `.htaccess` van het pakket stuurt alles naar https.

## 3. Mijn BSO uploaden

- [ ] De ontwikkelaar maakt het pakket: `npm run pakket -- --mijnbso` geeft `pakket/mijn-bso.zip`. Daarin zit alleen Mijn BSO (`beheer/`) met de opmaak die het gebruikt (css, lettertypen, logo's), een eigen `.htaccess` (https, en `mijn.bsosporty.nl` zonder pad gaat naar `/beheer/`) en een `robots.txt` (niet in zoekmachines). Geen gegevens en geen echte configuratie.
- [ ] Verwijder in `domains/mijn.bsosporty.nl/public_html` de standaardpagina van DirectAdmin (`index.html`), als die er staat.
- [ ] Upload de zip naar `domains/mijn.bsosporty.nl/public_html` en pak hem daar uit (Bestandsbeheer → uitpakken).
- [ ] Stond Mijn BSO eerder in `domains/bsosporty.nl/public_html/mijn`? Verwijder die map als de nieuwe werkt, zodat Mijn BSO maar op één plek staat.
- [ ] Let op dat de verborgen bestanden `.htaccess` (in de map zelf en in `beheer/`) en `beheer/.user.ini` erbij staan.

## 4. Mappen, sleutels en configuratie

- [ ] Maak **buiten** `public_html` twee mappen:
  - `/home/GEBRUIKER/beheer-data` (database, foto's, sessies)
  - `/home/GEBRUIKER/beheer-backups` (de dagelijkse versleutelde back-ups)
- [ ] Maak via SSH twee sleutels (twee keer uitvoeren, twee verschillende uitkomsten):
  ```sh
  php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
  ```
  Bewaar ze **meteen** in een wachtwoordkluis, met een tweede kopie op een veilige plek. Zonder deze sleutels zijn de gegevens en back-ups onbruikbaar, ook voor Sporty zelf. De eerste sleutel verander je daarna nooit meer.
- [ ] Kopieer `public_html/beheer/config.voorbeeld.php` naar `/home/GEBRUIKER/domains/mijn.bsosporty.nl/mijnbso-config.php`, dus **naast** `public_html` en niet erin, en vul in. Mijn BSO zoekt de configuratie daar. (Stond hij al in `domains/bsosporty.nl` voor het oude subdomein? Verplaats hem, met dezelfde sleutels.) Zet de rechten op 600:
  ```php
  return [
      'app_url' => 'https://mijn.bsosporty.nl/beheer',
      'website_url' => 'https://bsosporty.nl',
      'data_dir' => '/home/GEBRUIKER/beheer-data',
      'sleutel' => 'EERSTE-SLEUTEL',
      'ontwikkelmodus' => false,
      'achter_proxy' => true,  // Vimexx: een server vóór Apache handelt https af (anders: te veel doorverwijzingen)
      'installatiecode' => 'EEN-LANGE-WILLEKEURIGE-CODE',
      'backup' => ['map' => '/home/GEBRUIKER/beheer-backups', 'sleutel' => 'TWEEDE-SLEUTEL', 'bewaardagen' => 30],
  ];
  ```
  `website_url` vertelt Mijn BSO dat de website op Vercel staat: links gaan daarheen, de formulieren van de website worden toegelaten, en "Website live zetten" werkt via de middleware. Stripe, e-mail en de externe back-up vul je hier níet in; dat doe je in Mijn BSO onder Koppelingen (stap 6). `achter_proxy` is nodig bij Vimexx (25 november 2026): daar handelt een server vóór Apache de https-verbinding af, zodat Apache en PHP zelf "http" zien. Zonder deze regel, en zonder de regel met `X-Forwarded-Proto` in de `.htaccess` van het pakket, krijg je "te veel doorverwijzingen".

## 5. Geplande taken (cronjobs) in DirectAdmin

- [ ] Voeg twee dagelijkse taken toe (pas het pad naar PHP aan als Vimexx een ander noemt):
  ```cron
  15 3 * * * /usr/local/bin/php /home/GEBRUIKER/domains/mijn.bsosporty.nl/public_html/beheer/cli/onderhoud.php
  45 3 * * * /usr/local/bin/php /home/GEBRUIKER/domains/mijn.bsosporty.nl/public_html/beheer/cli/backup.php
  ```
  Het onderhoud wist gegevens volgens de bewaartermijnen (en stuurt na de livegang de mail aan wie zich op de binnenkort-pagina later nog bevestigde); de back-up maakt elke nacht een versleutelde kopie en ruimt kopieën ouder dan 30 dagen op.

## 6. Mijn BSO installeren en instellen

- [ ] Ga naar `https://mijn.bsosporty.nl/beheer/`, vul de installatiecode in, maak het eerste beheerdersaccount en stel tweestapsverificatie in.
- [ ] Maak `installatiecode` in `mijnbso-config.php` daarna weer leeg.
- [ ] **Instellingen:** KvK, LRK, IBAN, controleer de tarieven (Sporty Abonnement en Incidenteel), afmeldtijd, bewaartermijnen, tweestapsverificatie voor ouders. Maak de **groepen** aan en nodig het team uit onder **Team**.
- [ ] **Koppelingen → E-mail versturen:** de eigen mailserver bij Vimexx (SMTP met info@bsosporty.nl, server `mail.bsosporty.nl`), en stuur een testmail.
- [ ] **Koppelingen → Stripe:** eerst de testsleutel en een testwebhook naar `https://mijn.bsosporty.nl/beheer/stripe-webhook.php`; als alles werkt de live-sleutel. Zie "Stripe koppelen" in `docs/beheeromgeving.md`.
- [ ] **Koppelingen → Externe back-up:** objectopslag bij een andere partij in de EU, met object lock. Zie "Externe back-up" in `docs/beheeromgeving.md`.

## 7. Controleren

- [ ] **Instellingen → Beveiligingsstatus:** alles op "In orde" (ook "Sleutels buiten de webmap").
- [ ] `https://bsosporty.nl/` toont de binnenkort-pagina, `https://bsosporty.nl/contact.html` stuurt daarheen, en `https://www.bsosporty.nl` komt uit op `https://bsosporty.nl`.
- [ ] Een aanmelding op de binnenkort-pagina komt binnen bij Op de hoogte, en de bevestigingsmail komt aan.
- [ ] `https://bsosporty.nl/beheer/` stuurt door naar `https://mijn.bsosporty.nl/beheer/`.
- [ ] `https://mijn.bsosporty.nl/beheer/inc/bootstrap.php` geeft "Forbidden" (403).
- [ ] Met "Bekijk de echte website alvast": een proefdag boeken en een aanmelding via Kom bij de club komen binnen in Mijn BSO.
- [ ] "Wachtwoord vergeten" stuurt een e-mail die aankomt.
- [ ] De volgende ochtend staat er een back-up in `/home/GEBRUIKER/beheer-backups` (en in de externe opslag).

## Daarna

- **Live gaan:** Mijn BSO → Op de hoogte → **Website live zetten**. Controleer na een minuut dat `https://bsosporty.nl/` de homepage toont, en meld de sitemap aan bij Google Search Console (zie `docs/seo.md`). Laat de ontwikkelaar daarna `STANDAARD` in `middleware.js` op `"live"` zetten.
- **Een nieuwe versie van de website:** de ontwikkelaar pusht naar de branch die Vercel live zet; Vercel zet hem vanzelf online.
- **Een nieuwe versie van Mijn BSO:** maak een nieuw pakket (`npm run pakket -- --mijnbso`) en pak het uit over `domains/mijn.bsosporty.nl/public_html` heen. `mijnbso-config.php` en de gegevens blijven staan, want die staan buiten `public_html`.
- **Terugzetten oefenen:** zet minstens één keer per jaar een back-up terug op een testomgeving (zie "Back-ups en herstel" in `docs/beheeromgeving.md`), ook eentje uit de externe opslag.

## Alternatief: alles op één server bij Vimexx

Wil Sporty later toch alles bij Vimexx (zonder Vercel), dan kan dat ook: `npm run pakket -- --binnenkort` maakt een pakket met de website én Mijn BSO voor `domains/bsosporty.nl/public_html`. De binnenkort-pagina werkt dan via `.htaccess` en het bestand `binnenkort.aan` (Website live zetten haalt het weg). In de configuratie staat dan `'app_url' => 'https://bsosporty.nl/beheer'` en géén `website_url`; `@` en `www` wijzen dan naar de server bij Vimexx, en het certificaat is voor `bsosporty.nl` en `www.bsosporty.nl`. De cronjobs staan dan in `public_html/beheer/cli/`. Haal `binnenkort.aan` dan niet zelf weg: dan gaat de website wel live, maar gaat er geen mail uit.
