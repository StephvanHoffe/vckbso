# SEO: vindbaarheid van Sporty

Stand van zaken na de eerste SEO-optimalisatie (30 september 2026). De techniek staat goed; wat nu het meeste verschil maakt, ligt vooral bij de gegevens van de BSO zelf (zie [Nog te doen](#nog-te-doen-door-sporty)).

## Wat er al geregeld is

**Per pagina**
- Een unieke titel (hooguit 60 tekens) en meta description (hooguit 160 tekens) met de zoekwoorden waar ouders op zoeken: *BSO*, *buitenschoolse opvang*, *Amsterdam*, *kinderopvangtoeslag* en *aanmelden*. Overal staat de naam Sporty in.
- Een canonical-URL, Open Graph-tags en een deelafbeelding (`assets/img/og-image.jpg`) voor een nette weergave als iemand een link deelt via WhatsApp, Facebook of LinkedIn.
- Eén `h1` per pagina, een logische kopstructuur, alt-teksten bij alle foto's en een zichtbaar kruimelpad.
- De homepage en de Engelse startpagina verwijzen naar elkaar met `hreflang` (nl, en en x-default).

**Gestructureerde gegevens (schema.org, JSON-LD)**
- `ChildCare` op de homepage en de contactpagina: naam, statutaire naam, telefoon, e-mail, plaats, werkgebied en openingstijden.
- `WebSite` op de homepage, zodat Google "Sporty" als sitenaam kan tonen.
- `BreadcrumbList` op elke subpagina, gelijk aan het zichtbare kruimelpad.
- `FAQPage` op de pagina met veelgestelde vragen, gelijk aan de zichtbare vragen.

Deze gegevens worden gemaakt door `tools/structured-data.mjs` (`npm run seo`). Het kruimelpad en de vragen komen uit de HTML zelf, zodat ze altijd kloppen met wat bezoekers zien.

**Techniek**
- `sitemap.xml` met alle pagina's (en de taalversies), en `robots.txt` met een verwijzing naar de sitemap.
- De beheeromgeving (Mijn BSO) stuurt `X-Robots-Tag: noindex, nofollow` mee en staat daarom níet meer als `Disallow` in `robots.txt`: alleen zo ziet een zoekmachine dat die pagina's niet in de zoekresultaten horen.
- De foutpagina (`404.html`) heeft `noindex` en geen canonical.
- Snel en mobielvriendelijk: AVIF/WebP in meerdere formaten, `loading="lazy"` onder de vouw, `fetchpriority="high"` op de grote foto bovenaan, lokaal gehoste letters en bijna geen JavaScript.
- Interne links met duidelijke linkteksten, onder meer van de homepage en de veelgestelde vragen naar de rekentool kinderopvangtoeslag.

**Automatisch gecontroleerd** met `npm test` (`tests/seo.test.mjs`): lengte en uniekheid van titels en descriptions, canonical, Open Graph en sitemap in overeenstemming, geen `noindex` in de sitemap, `hreflang`, één `h1`, alt-teksten en afmetingen bij foto's, geldige en actuele schema.org-gegevens, en dat de oude naam nergens meer op de website staat.

## Nog te doen door Sporty

Op volgorde van effect. Deze punten staan ook als TODO in de code.

1. **Domeinnaam bij de nieuwe naam.** `bsovck.nl` past niet meer bij Sporty. Kies en registreer een domein en vervang daarna `https://bsovck.nl` overal (zoek en vervang: de `<head>` van elke pagina, `sitemap.xml`, `robots.txt` en `SITE` in `tools/structured-data.mjs`), en draai `npm run seo` en `npm test`. Op de website staat het e-mailadres info@sporty.nl. Controleer dat het domein sporty.nl echt van Sporty is: op 6 oktober 2026 stond er een andere website op (Sporty.nl, over sport en fitness). Is dat niet zo, kies dan een e-mailadres op het eigen domein.
2. **Adres.** Lokale vindbaarheid ("bso amsterdam", "bso in de buurt") hangt vooral af van een vast adres dat overal hetzelfde is: website, schema.org-gegevens (`tools/structured-data.mjs`), Google Bedrijfsprofiel en vermeldingen elders. Noem op de website ook de wijk of het stadsdeel en de scholen waar jullie kinderen ophalen: daar zoeken ouders op.
3. **Google Bedrijfsprofiel** aanmaken en verifiëren (business.google.com), met dezelfde naam, hetzelfde adres en telefoonnummer, de openingstijden, eigen foto's en een link naar de website. Vraag tevreden ouders om een review daar. Alleen echte reviews.
4. **Google Search Console en Bing Webmaster Tools**: domein verifiëren en `sitemap.xml` indienen. Daar zie je ook op welke zoekwoorden ouders jullie vinden.
5. **LRK-nummer** op de website zetten (Prijzen, Veelgestelde vragen) zodra de registratie in het Landelijk Register Kinderopvang rond is. Ouders zoeken ook in het LRK, en de vermelding daar versterkt het vertrouwen.
6. **Vermeldingen en links** van anderen: scholen in de buurt, sportverenigingen waar jullie mee samenwerken, wijkwebsites en vergelijkingssites voor kinderopvang. Zorg dat naam, adres en telefoonnummer overal precies gelijk zijn.
7. **Social media**: de links in de footer invullen en ze als `sameAs` toevoegen in `tools/structured-data.mjs`.
8. **Eigen foto's** in plaats van de stockfoto's, met een beschrijvende bestandsnaam en alt-tekst.
9. **Meer inhoud** die ouders zoeken, zodra het aanbod vaststaat: vakantieopvang, studiedagen, ophalen van school, welke sporten jullie doen. Een eigen pagina per onderwerp scoort beter dan één lange pagina.
10. **Engelse pagina's** uitbreiden: veel ouders in Amsterdam zoeken in het Engels ("after-school care Amsterdam").

## Onderhoud

| Wanneer | Wat |
|---|---|
| Vraag, pagina of gegevens van de BSO gewijzigd | `npm run seo` en daarna `npm test` |
| Naam, slogan of hoofdfoto gewijzigd | `npm run og-image` (maakt `assets/img/og-image.jpg` opnieuw; vereist Playwright) |
| Nieuwe pagina | Titel, description, canonical en Open Graph-tags in de `<head>`, de pagina toevoegen aan `sitemap.xml` en aan `PAGINAS` in `tools/structured-data.mjs`, dan `npm run seo` en `npm test` |
| Grote inhoudelijke wijziging | `lastmod` van die pagina in `sitemap.xml` bijwerken |
