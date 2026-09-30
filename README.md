# Website BSO VCK

Statische website voor **BSO VCK**, buitenschoolse opvang in Amsterdam (statutaire naam: Je Dag in Beeld). Gebouwd volgens de bouwinstructies in [`CLAUDE.md`](CLAUDE.md).

- Gewone HTML, CSS en een klein beetje JavaScript. Geen framework en geen build-stap.
- Mobile-first en getest op 360, 768 en 1280 px breed, zonder horizontaal scrollen.
- Lighthouse (mobiel) op home, diensten en contact: prestaties 99, toegankelijkheid 100, best practices 100, SEO 100.
- Automatische toegankelijkheidstest (axe, WCAG 2.1 AA): geen overtredingen op alle pagina's.
- Privacy: geen tracking, lettertypen lokaal, Google Maps laadt pas na een klik.

## Bekijken

Open `index.html` in je browser, of start een lokale server:

```sh
npx serve .
```

## Structuur

| Pad | Inhoud |
|---|---|
| `index.html`, `over-ons.html`, `diensten.html`, `prijzen.html`, `team.html`, `veelgestelde-vragen.html`, `contact.html` | De pagina's uit de sitemap |
| `aanmelden.html` | Aanmeldformulier voor ouders (een of meer kinderen, gewenste dagen, startdatum). Bereikbaar via de knop "Aanmelden" in de header. |
| `privacy.html`, `voorwaarden.html`, `404.html` | Privacyverklaring, algemene voorwaarden en foutpagina |
| `en/index.html` | Eerste opzet van de Engelse versie (taalkeuze NL/EN in de header) |
| `design/tokens.css` | Design tokens: kleuren, letters, ruimte, vormen en beweging. Ook de `@font-face`-regels. |
| `css/style.css` | Alle opmaak, mobile-first |
| `js/main.js` | Menu, in beeld komen bij scrollen, kaart na klik, formuliercontrole (contact en aanmelden), kinderen toevoegen in het aanmeldformulier |
| `assets/fonts/` | Manrope (variabel, lokaal gehost, OFL-licentie) |
| `assets/fotos/` | Beelden in AVIF en WebP. Bronnen en de fotolijst staan in [`BRONNEN.md`](assets/fotos/BRONNEN.md). |
| `assets/img/` | Favicon, app-iconen en de deelafbeelding (`og-image.png`) |
| `tools/fotos.mjs` | Hulpscript om beelden om te zetten (`npm run fotos`). Niet nodig om de site te draaien. |
| `docs/beheeromgeving.md` | Voorstel voor de gewenste beheeromgeving met kindvolgsysteem |

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
- [ ] Placeholder-illustraties vervangen door eigen foto's en de alt-teksten aanpassen. Zie [`assets/fotos/BRONNEN.md`](assets/fotos/BRONNEN.md).

**Techniek**
- [ ] Formulierendienst instellen (bijvoorbeeld Formspree of Basin) en de endpoint-URL's in `contact.html` en `aanmelden.html` invullen. Berichten en aanmeldingen moeten naar info@vckbso.nl gaan. Tot die tijd openen de formulieren het e-mailprogramma van de bezoeker, met alle ingevulde gegevens als nette samenvatting.
- [ ] Privacyverklaring laten controleren: bewaartermijn, naam van de formulierendienst en een verwerkersovereenkomst. Het aanmeldformulier vraagt ook gegevens van kinderen (voornaam, geboortedatum, school).
- [ ] Domein `bsovck.nl` registreren. Wordt het een ander domein, pas dan de URL's aan in de `<head>` van elke pagina, in `sitemap.xml` en in `robots.txt`.
- [ ] Engelse teksten na akkoord van de klant op de Nederlandse teksten. Nu staat er alleen een Engelse startpagina.
- [ ] Beheeromgeving met kindvolgsysteem, aanmelding, incasso, foto's, berichten en groepsagenda. Zie [`docs/beheeromgeving.md`](docs/beheeromgeving.md).

## Live zetten

De site werkt op elke statische host (bijvoorbeeld Netlify, Cloudflare Pages, GitHub Pages of gewone webhosting). Upload de map zonder `node_modules/`, `tools/` en `package*.json`. Stel `404.html` in als foutpagina.

**Let op bij het koppelen van het domein:** er is al e-mail in gebruik (info@vckbso.nl). Het gewenste websitedomein is bsovck.nl, terwijl de e-mail op vckbso.nl draait. Controleer dit met de klant. Zet je de website op een domein waar al e-mail op draait, wijzig dan alleen de DNS-records voor de website (A/AAAA of CNAME). Laat de MX-, SPF-, DKIM- en DMARC-records ongemoeid, anders stopt de e-mail.
