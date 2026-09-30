# Website BSO VCK

Statische website voor **BSO VCK**, buitenschoolse opvang in Amsterdam (statutaire naam: Je Dag in Beeld). Gebouwd volgens de bouwinstructies in [`CLAUDE.md`](CLAUDE.md).

- Gewone HTML, CSS en een klein beetje JavaScript. Geen framework en geen build-stap.
- Met een beheeromgeving en ouderportaal in `beheer/` (PHP + SQLite): aanmelden als klant met kinderen, automatische incasso via Mollie, facturen online, groepsagenda met maximale groepsgrootte die twee kanten op werkt, kindvolgsysteem, foto's en berichten. Met toegang per groep en per verzorger (gecontroleerd gezag), tweestapsverificatie, versleutelde opslag en back-ups, een controleerbaar logboek, privacyverzoeken voor ouders, bewaartermijnen per soort gegevens en strikt gescheiden organisaties. Zie [`docs/beheeromgeving.md`](docs/beheeromgeving.md).
- Mobile-first en getest op 360, 768 en 1280 px breed, zonder horizontaal scrollen.
- Lighthouse (mobiel) op home, diensten en contact: prestaties 99, toegankelijkheid 100, best practices 100, SEO 100.
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
| `assets/fonts/` | Manrope (variabel, lokaal gehost, OFL-licentie) |
| `assets/fotos/` | Foto's in AVIF en WebP (nu nog stockfoto's van Pexels), met de bronbestanden in `bron/`. Fotografen, licentie en de fotolijst staan in [`BRONNEN.md`](assets/fotos/BRONNEN.md). |
| `assets/img/` | Favicon, app-iconen en de deelafbeelding (`og-image.jpg`) |
| `tools/fotos.mjs` | Hulpscript om beelden om te zetten (`npm run fotos`). Niet nodig om de site te draaien. |
| `tools/beheer-demodata.php` | Vult een lege test-omgeving van de beheeromgeving met verzonnen demogegevens |
| `docs/beheeromgeving.md` | Handleiding van de beheeromgeving: functies, de verplichte eisen (toegang, beveiliging, logboek, ouderrechten, bewaarbeleid), installatie, back-ups, Mollie en TODO's |

Header, footer en de iconenset staan in elke pagina. Pas je iets aan in de navigatie of footer, doe dat dan op alle pagina's (zoek en vervang).

## Nog te doen (TODO)

Alles wat nog niet bekend was, staat als `<!-- TODO: ... -->` in de code. Zoek op `TODO` voor de exacte plekken. Er is niets verzonnen: geen prijzen, namen, reviews of adresgegevens.

**Gegevens van de klant**
- [ ] Adres (straat, huisnummer, postcode). Dit moet op de contactpagina, in de footer, bij de routelink, in de kaart-URL en in de schema.org-gegevens.
- [ ] Informatie over parkeren, fietsenstalling en openbaar vervoer (contactpagina).
- [ ] KvK-nummer (footer).
- [ ] Links naar social media (footer).
- [ ] Openingstijden controleren: zijn 09:00–17:00 de opvangtijden of de kantoortijden? Een BSO is meestal na schooltijd tot 18:00 of 18:30 uur open.
- [ ] Uurtarief, wat er precies bij de prijs zit en eventuele extra kosten (Prijzen).
- [ ] Opzegtermijn, minimale afname en betaalwijze (Prijzen, Voorwaarden).
- [ ] LRK-nummer, en bevestigen dat ouders kinderopvangtoeslag kunnen aanvragen (Prijzen, Veelgestelde vragen).
- [ ] Exacte leeftijdsgrenzen (Diensten, Veelgestelde vragen).
- [ ] Welke sporten en activiteiten, en of er een eigen buitenruimte is (Diensten).
- [ ] Vakantieopvang, opvang op studiedagen en ophalen van school: aanbieden of niet? (Diensten)
- [ ] Oprichtingsverhaal, en waar "VCK" voor staat (Over ons).
- [ ] Teamleden: foto, naam, rol en een leuk weetje (Team, Over ons).
- [ ] Reviews van ouders, alleen echte en met toestemming (Home).
- [ ] Algemene voorwaarden (Voorwaarden).

**Beeld**
- [ ] De tijdelijke stockfoto's (Pexels) stap voor stap vervangen door eigen foto's van het team, de kinderen (met toestemming van de ouders) en de locatie, en de alt-teksten aanpassen. Zie [`assets/fotos/BRONNEN.md`](assets/fotos/BRONNEN.md).
- [ ] Teamportretten (Team) en de teamfoto (Over ons). Hier staan bewust geen stockfoto's: die zouden lijken op echte teamleden.

**Techniek**
- [ ] Formulierendienst instellen (bijvoorbeeld Formspree of Basin) en de endpoint-URL in `contact.html` invullen. Berichten moeten naar info@vckbso.nl gaan. Tot die tijd opent het contactformulier het e-mailprogramma van de bezoeker, met alle ingevulde gegevens als nette samenvatting.
- [ ] Privacyverklaring laten controleren: bewaartermijnen, verwerkers (formulierendienst, hosting, Mollie) en de verwerking van gegevens van kinderen in Mijn BSO.
- [ ] Domein `bsovck.nl` registreren. Wordt het een ander domein, pas dan de URL's aan in de `<head>` van elke pagina, in `sitemap.xml` en in `robots.txt`.
- [ ] Engelse teksten na akkoord van de klant op de Nederlandse teksten. Nu staat er alleen een Engelse startpagina.
- [ ] Beheeromgeving live zetten: hosting met PHP 8.2+ en https, `beheer/config.php` met twee sleutels, cron voor onderhoud en back-ups, Mollie koppelen, uurtarief en groepen instellen. Bewaartermijnen laten bevestigen. De volledige lijst staat in [`docs/beheeromgeving.md`](docs/beheeromgeving.md#nog-te-doen-todo).

## Live zetten

De website zelf werkt op elke statische host, maar voor de beheeromgeving (`beheer/`) is hosting met **PHP 8.2 of hoger** en https nodig. Gewone Nederlandse webhosting met PHP is genoeg. Upload de map zonder `node_modules/`, `tools/`, `assets/fotos/bron/` en `package*.json`. Stel `404.html` in als foutpagina. Volg daarna de stappen in [`docs/beheeromgeving.md`](docs/beheeromgeving.md#live-zetten).

**Let op bij het koppelen van het domein:** er is al e-mail in gebruik (info@vckbso.nl). Het gewenste websitedomein is bsovck.nl, terwijl de e-mail op vckbso.nl draait. Controleer dit met de klant. Zet je de website op een domein waar al e-mail op draait, wijzig dan alleen de DNS-records voor de website (A/AAAA of CNAME). Laat de MX-, SPF-, DKIM- en DMARC-records ongemoeid, anders stopt de e-mail.
