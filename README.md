# Website Sporty

Statische website voor **Sporty**, buitenschoolse opvang in Amsterdam (statutaire naam: Je Dag in Beeld). Gebouwd volgens de bouwinstructies in [`CLAUDE.md`](CLAUDE.md).

- Gewone HTML, CSS en een klein beetje JavaScript. Geen framework en geen build-stap.
- Huisstijl volgens het moodboard van de klant (`design/moodboard-sporty.jpg`): Sporty-oranje, wit en crème, Poppins, slogans in handgeschreven hoofdletters en het logo met drie kinderen en een lachje.
- Intro op de homepage: de drie kinderen uit het logo rennen in beeld en vormen samen het logo. Eén keer per bezoek (en bij opnieuw laden), over te slaan met een toets, klik, tik of scroll, en niet bij de instelling "minder beweging" of zonder JavaScript.
- Met een beheeromgeving en ouderportaal in `beheer/` (PHP + SQLite): aanmelden als klant met kinderen, automatische incasso via Mollie, facturen online, groepsagenda met maximale groepsgrootte die twee kanten op werkt, kindvolgsysteem, foto's en berichten, en een overzicht van inkomsten en uitgaven. Met rechten per collega per menuonderdeel, toegang per groep en per verzorger (gecontroleerd gezag), tweestapsverificatie, versleutelde opslag en back-ups, een controleerbaar logboek, privacyverzoeken voor ouders, bewaartermijnen per soort gegevens en strikt gescheiden organisaties. Zie [`docs/beheeromgeving.md`](docs/beheeromgeving.md).
- Mobile-first en getest op 360, 768 en 1280 px breed, zonder horizontaal scrollen.
- Lighthouse (mobiel): prestaties 96 op de homepage (met de intro), 98 op diensten en 97 op contact; toegankelijkheid, best practices en SEO 100.
- Automatische toegankelijkheidstest (axe, WCAG 2.1 AA): geen overtredingen op alle pagina's.
- Privacy: geen tracking, lettertypen lokaal, Google Maps laadt pas na een klik.

## Bekijken

Open `index.html` in je browser, of start een lokale server:

```sh
npx serve .
```

De beheeromgeving (`/beheer/`) heeft PHP nodig:

```sh
cp beheer/config.voorbeeld.php beheer/config.php   # vul een installatiecode in en zet 'ontwikkelmodus' => true
php -S localhost:8000
php tools/beheer-demodata.php                      # optioneel: demogegevens om uit te proberen
```

## Voorbeeld op Vercel

De website staat als voorbeeld op Vercel: https://sporty-sigma.vercel.app. Vercel draait alleen statische bestanden, geen PHP. Daarom staat er een doorklikbare momentopname van Mijn BSO in `voorbeeld/`, met verzonnen gegevens:

- **Overzichtspagina:** https://sporty-sigma.vercel.app/voorbeeld/ ("De website en Mijn BSO om door te klikken"), met de website en de beheeromgeving als beheerder, begeleider en ouder.
- **Links naar Mijn BSO** (Inloggen, Aanmelden) gaan op Vercel naar dezelfde pagina's in het voorbeeld. Dat regelt `vercel.json`; op gewone PHP-hosting doet dat bestand niets.
- **Niet in Google:** het voorbeeld heeft `noindex`.
- **Opnieuw maken** na wijzigingen in de website of de beheeromgeving: `npm run voorbeeld` (PHP 8.2+ nodig). Het script maakt een tijdelijke demo-omgeving, legt alle schermen vast en ruimt daarna alles op. `npm test` meldt het als het voorbeeld verouderd is of als een link niet klopt.
- **Welke versie Vercel toont:** Vercel publiceert de productiebranch van deze repository (in Vercel: Settings → Git → Production Branch). Wijzigingen staan pas op sporty-sigma.vercel.app als ze in die branch staan.

## Structuur

| Pad | Inhoud |
|---|---|
| `index.html`, `over-ons.html`, `diensten.html`, `prijzen.html`, `team.html`, `veelgestelde-vragen.html`, `contact.html` | De pagina's uit de sitemap |
| `aanmelden.html` | Uitleg over aanmelden, met de knop naar het aanmeldproces in Mijn BSO (`beheer/aanmelden.php`). Bereikbaar via de knop "Aanmelden" in de header. |
| `beheer/` | Beheeromgeving voor het team en Mijn BSO voor ouders (PHP 8.2+, SQLite). Zie [`docs/beheeromgeving.md`](docs/beheeromgeving.md). |
| `beheer/cli/` | Dagelijks onderhoud (bewaartermijnen), versleutelde back-up en herstel, via cron of de opdrachtregel |
| `privacy.html`, `voorwaarden.html`, `404.html` | Privacyverklaring, algemene voorwaarden en foutpagina |
| `en/index.html` | Eerste opzet van de Engelse versie (taalkeuze NL/EN in de header) |
| `design/tokens.css` | Design tokens: kleuren, letters, ruimte, vormen en beweging. Ook de `@font-face`-regels. |
| `css/style.css` | Alle opmaak, mobile-first |
| `js/main.js` | Menu, in beeld komen bij scrollen, kaart na klik, formuliercontrole van het contactformulier |
| `js/rekentool/` | Rekentool kinderopvangtoeslag op de prijzenpagina: officiële bedragen per jaar (`toeslag-2026.js`), de tarieven van Sporty (`instellingen.js`), de berekening (`berekening.js`) en het formulier (`rekentool.js`). Zie [Rekentool](#rekentool-kinderopvangtoeslag). |
| `tests/` | Automatische tests (`npm test`): de rekentool, met de tabel uit de brochure van Dienst Toeslagen als tweede bron, en de SEO-basis van alle pagina's |
| `assets/fonts/` | Poppins en Caveat Brush (alleen hoofdletters), lokaal gehost, OFL-licentie |
| `design/moodboard-sporty.jpg` | Het moodboard van de klant: de huisstijl van de website |
| `assets/fotos/` | Foto's in AVIF en WebP (nu nog stockfoto's van Pexels), met de bronbestanden in `bron/`. Fotografen, licentie en de fotolijst staan in [`BRONNEN.md`](assets/fotos/BRONNEN.md). |
| `assets/img/` | Favicon, app-iconen en de deelafbeelding (`og-image.jpg`) |
| `tools/fotos.mjs` | Hulpscript om beelden om te zetten (`npm run fotos`). Niet nodig om de site te draaien. |
| `tools/structured-data.mjs` | Zet de schema.org-gegevens (JSON-LD) in de pagina's, met kruimelpad en veelgestelde vragen uit de zichtbare HTML (`npm run seo`) |
| `tools/logo/` | Maakt het logo (embleem en woordmerk), de favicon, de app-iconen en de intro op de homepage (`npm run logo`), nagetekend naar het moodboard |
| `tools/og-image.mjs` | Maakt de deelafbeelding `assets/img/og-image.jpg` opnieuw (`npm run og-image`, vereist Playwright) |
| `tools/beheer-demodata.php` | Vult een lege test-omgeving van de beheeromgeving met verzonnen demogegevens |
| `voorbeeld/` | Doorklikbare momentopname van Mijn BSO met verzonnen gegevens, voor Vercel. Gemaakt door `npm run voorbeeld`, niet met de hand aanpassen. Zie [Voorbeeld op Vercel](#voorbeeld-op-vercel). |
| `tools/voorbeeld/` | Bouwscript (`bouw.mjs`), extra demogegevens, de overzichtspagina en de voorbeeldbalk |
| `vercel.json` | Alleen voor Vercel: stuurt `/beheer/` door naar het voorbeeld en zet `noindex` op het voorbeeld |
| `docs/seo.md` | Wat er voor de vindbaarheid is gedaan, wat Sporty nog zelf moet regelen (domein, adres, Google Bedrijfsprofiel) en onderhoud |
| `docs/beheeromgeving.md` | Handleiding van de beheeromgeving: functies, de verplichte eisen (toegang, beveiliging, logboek, ouderrechten, bewaarbeleid), installatie, back-ups, Mollie en TODO's |

Header, footer en de iconenset staan in elke pagina. Pas je iets aan in de navigatie of footer, doe dat dan op alle pagina's (zoek en vervang).

## Rekentool kinderopvangtoeslag

Op de prijzenpagina (`prijzen.html#rekentool`) rekenen ouders uit wat ze per maand betalen na aftrek van de kinderopvangtoeslag. De berekening gebeurt in de browser; er wordt niets verstuurd of opgeslagen.

- **Hoe er gerekend wordt:** precies volgens de 7 stappen uit de brochure [Berekening kinderopvangtoeslag 2026](https://download.belastingdienst.nl/toeslagen/docs/berekening_kinderopvangtoeslag_tg0801z61fd.pdf) van Dienst Toeslagen: maximale uurprijs, maximaal 230 uur per kind per maand, het 1e kind is het kind met de meeste uren (bij gelijke uren de hoogste kosten), percentage uit de tabel bij het gezamenlijke toetsingsinkomen. Bedragen worden per stap op centen afgerond, zoals in de rekenvoorbeelden van de brochure. Er wordt alleen met hele getallen gerekend (centen), dus zonder afrondingsfouten.
- **Gecontroleerd met `npm test`:** de tabel is vergeleken met twee officiële bronnen (rijksoverheid.nl en de brochure), de 4 officiële rekenvoorbeelden komen tot op de cent uit, elke grens van alle 69 inkomensschijven is getest, en 20.000 willekeurige situaties geven dezelfde uitkomst als een tweede, apart geschreven berekening.
- **Wat de rekentool niet weet:** de uitkomst is een schatting. De Belastingdienst stelt de toeslag vast, en die hangt ook af van het aantal maanden dat jij en je partner werken. Dat staat ook bij de rekentool.
- **Tarieven van Sporty invullen:** in `js/rekentool/instellingen.js` (uurtarief, schoolweken, vakantieweken, uren per dag). Draai daarna `npm test`: de test meldt ontbrekende of ongeldige gegevens. Zolang er geen tarieven zijn, vult de bezoeker zelf een uurprijs en het aantal uren in.
- **Elk jaar in januari bijwerken:** maak `js/rekentool/toeslag-<jaar>.js` met de nieuwe tabel en maximale uurprijzen (bron: rijksoverheid.nl, "Bedragen kinderopvangtoeslag"), zet de nieuwe brochuretabel in `tests/bronnen/`, pas `jaar` aan in `instellingen.js`, zet het nieuwe bestand in `prijzen.html` en draai `npm test`. Het kabinet wil de toeslag vanaf 2029 vervangen door een nieuw stelsel; dan moet de rekentool opnieuw worden bekeken.

## Nog te doen (TODO)

Alles wat nog niet bekend was, staat als `<!-- TODO: ... -->` in de code. Zoek op `TODO` voor de exacte plekken. Er is niets verzonnen: geen prijzen, namen, reviews of adresgegevens.

**Gegevens van de klant**
- [ ] Adres (straat, huisnummer, postcode). Dit moet op de contactpagina, in de footer, bij de routelink, in de kaart-URL en in de schema.org-gegevens.
- [ ] Informatie over parkeren, fietsenstalling en openbaar vervoer (contactpagina).
- [ ] Bevestigen dat het domein sporty.nl van Sporty is en dat info@sporty.nl werkt (nu staat er een andere website op sporty.nl). Zie [Live zetten](#live-zetten).
- [ ] Originele logobestanden (SVG, of PNG in hoge resolutie) van de ontwerper. Het logo op de site is nagetekend naar het moodboard; vervang de bestanden in `assets/img/sporty-logo*.svg` en `sporty-woordmerk*.svg` en maak de iconen opnieuw.
- [ ] KvK-nummer (footer).
- [ ] Links naar social media (footer).
- [ ] Openingstijden controleren: zijn 09:00–17:00 de opvangtijden of de kantoortijden? Een BSO is meestal na schooltijd tot 18:00 of 18:30 uur open.
- [ ] Uurtarief, wat er precies bij de prijs zit en eventuele extra kosten (Prijzen).
- [ ] Rekentool: opvangvormen (40 of 52 weken, vakantieopvang), uurtarief per opvangvorm en uren opvang per dag invullen in `js/rekentool/instellingen.js`. Bevestigen hoe de uren per maand in het contract worden berekend (nu: uren per week × weken ÷ 12, op twee decimalen).
- [ ] Opzegtermijn, minimale afname en betaalwijze (Prijzen, Voorwaarden).
- [ ] LRK-nummer, en bevestigen dat ouders kinderopvangtoeslag kunnen aanvragen (Prijzen, Veelgestelde vragen).
- [ ] Exacte leeftijdsgrenzen (Diensten, Veelgestelde vragen).
- [ ] Welke sporten en activiteiten, en of er een eigen buitenruimte is (Diensten).
- [ ] Vakantieopvang, opvang op studiedagen en ophalen van school: aanbieden of niet? (Diensten)
- [ ] Oprichtingsverhaal (Over ons).
- [ ] Teamleden: foto, naam, rol en een leuk weetje (Team, Over ons).
- [ ] Reviews van ouders, alleen echte en met toestemming (Home).
- [ ] Algemene voorwaarden (Voorwaarden).

**Beeld**
- [ ] De tijdelijke stockfoto's (Pexels) stap voor stap vervangen door eigen foto's van het team, de kinderen (met toestemming van de ouders) en de locatie, en de alt-teksten aanpassen. Zie [`assets/fotos/BRONNEN.md`](assets/fotos/BRONNEN.md).
- [ ] Teamportretten (Team) en de teamfoto (Over ons). Hier staan bewust geen stockfoto's: die zouden lijken op echte teamleden.

**Techniek**
- [ ] Formulierendienst instellen (bijvoorbeeld Formspree of Basin) en de endpoint-URL in `contact.html` invullen. Berichten moeten naar info@sporty.nl gaan. Tot die tijd opent het contactformulier het e-mailprogramma van de bezoeker, met alle ingevulde gegevens als nette samenvatting.
- [ ] Privacyverklaring laten controleren: bewaartermijnen, verwerkers (formulierendienst, hosting, Mollie) en de verwerking van gegevens van kinderen in Mijn BSO.
- [ ] Domein kiezen bij de nieuwe naam Sporty (nu staat overal nog het eerder gewenste `bsovck.nl`). Vervang daarna `https://bsovck.nl` in de `<head>` van elke pagina, in `sitemap.xml`, `robots.txt` en `tools/structured-data.mjs`, en draai `npm run seo` en `npm test`.
- [ ] SEO-stappen die alleen Sporty zelf kan zetten: Google Bedrijfsprofiel, Search Console, LRK-nummer, vermeldingen bij scholen en verenigingen. Zie [`docs/seo.md`](docs/seo.md#nog-te-doen-door-sporty).
- [ ] Engelse teksten na akkoord van de klant op de Nederlandse teksten. Nu staat er alleen een Engelse startpagina.
- [ ] Beheeromgeving live zetten: hosting met PHP 8.2+ en https, `beheer/config.php` met twee sleutels, cron voor onderhoud en back-ups, Mollie koppelen, uurtarief en groepen instellen. Bewaartermijnen laten bevestigen. De volledige lijst staat in [`docs/beheeromgeving.md`](docs/beheeromgeving.md#nog-te-doen-todo).

## Live zetten

De website zelf werkt op elke statische host, maar voor de beheeromgeving (`beheer/`) is hosting met **PHP 8.2 of hoger** en https nodig. Gewone Nederlandse webhosting met PHP is genoeg. Upload de map zonder `node_modules/`, `tools/`, `tests/`, `voorbeeld/`, `assets/fotos/bron/`, `package*.json` en `vercel.json`. Stel `404.html` in als foutpagina. Volg daarna de stappen in [`docs/beheeromgeving.md`](docs/beheeromgeving.md#live-zetten).

**Let op met het e-mailadres:** op de website staat info@sporty.nl. Het domein sporty.nl was op 6 oktober 2026 in gebruik voor een andere website (Sporty.nl, over sport en fitness), met e-mail bij TransIP. Controleer vóór het live zetten dat sporty.nl echt van Sporty is en dat de mailbox info@sporty.nl bestaat. Anders komen berichten van ouders bij een ander terecht, of komen ze nergens aan. Het vorige adres info@vckbso.nl werkte ook niet: het domein vckbso.nl bestond niet. Het gewenste websitedomein is nog steeds bsovck.nl (zie `docs/seo.md`).

**Let op bij het koppelen van het domein:** zet je de website op een domein waar al e-mail op draait, wijzig dan alleen de DNS-records voor de website (A/AAAA of CNAME). Laat de MX-, SPF-, DKIM- en DMARC-records ongemoeid, anders stopt de e-mail.
