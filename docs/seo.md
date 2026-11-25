# SEO: vindbaarheid van Sporty

Stand van zaken na de tweede SEO-ronde (13 november 2026). De techniek staat goed (Lighthouse: SEO, toegankelijkheid en techniek 100, snelheid 97 met compressie). Wat nu het meeste verschil maakt, ligt buiten de website: het Google Bedrijfsprofiel, een echt telefoonnummer en vermeldingen elders. Zie [Nodig van Sporty](#nodig-van-sporty) en [Buiten de website regelen](#buiten-de-website-regelen).

## Wat er al geregeld is

**Per pagina (Nederlands en Engels)**
- Een unieke titel (hooguit 60 tekens) en meta description (hooguit 160 tekens) met de zoekwoorden waar ouders op zoeken: *BSO*, *buitenschoolse opvang*, *vakantieopvang*, *Watergraafsmeer*, *Amsterdam-Oost*, *kinderopvangtoeslag* en *aanmelden*. Op de homepage staat het zoekwoord vooraan, de naam Sporty achteraan.
- De kop (`h1`) zegt waar de pagina over gaat: het label boven de grappige kop hoort bij de kop zelf, bijvoorbeeld "BSO in de Watergraafsmeer, Amsterdam-Oost · Ons clubhuis op Sportpark Middenmeer". Op de homepage staat "Sportieve BSO in de Watergraafsmeer, Amsterdam-Oost" zichtbaar boven "Let's play" (er staat geen verborgen tekst meer in de kop).
- De openingsdatum (4 januari 2027) staat op de homepage (als link naar de locatiepagina), in de description van de homepage en op de locatiepagina. **Na de opening aanpassen** (TODO in `index.html` en `en/index.html`).
- De locatiepagina heeft een blok "In het kort": wat Sporty is, voor wie, open dagen, tijden, groepsgrootte en tarief.
- Een canonical-URL, Open Graph-tags en een deelafbeelding per taal (`assets/img/og-image.jpg` en `og-image-en.jpg`) voor WhatsApp, Facebook en LinkedIn.
- Elke pagina en zijn Engelse versie verwijzen naar elkaar met `hreflang` (nl, en en x-default).
- Eén adres per pagina: de homepage is `/` (en `/en/`); `/index.html` stuurt blijvend door. De oude adressen `community.html` en `abonnementen.html` sturen door naar `welkom-bij-de-club.html` en `abonnement-en-kosten.html`.

**Gestructureerde gegevens (schema.org, JSON-LD)**
- `ChildCare` op de homepage, de locatiepagina en Contact, in beide talen: naam, telefoon, e-mail, adres, coördinaten, kaartlink, prijsindicatie, werkgebied en telefonische bereikbaarheid. De openingstijden volgen zodra de begintijd op schooldagen bekend is (TODO).
- `WebSite` op de homepage, zodat Google "Sporty" als sitenaam kan tonen, en een `WebPage` in de juiste taal op elke pagina.
- `BreadcrumbList` gelijk aan het zichtbare kruimelpad, en `FAQPage` op Contact. Let op: Google toont veelgestelde vragen sinds 2023 alleen nog uitgeklapt bij websites van de overheid en de zorg; de gegevens kunnen geen kwaad, maar verwacht er geen extra ruimte in Google van.

Deze gegevens en `sitemap.xml` worden gemaakt door `tools/structured-data.mjs` (`npm run seo`). Het kruimelpad en de vragen komen uit de HTML zelf, zodat ze altijd kloppen met wat bezoekers zien.

**Techniek**
- `sitemap.xml` met alle pagina's en hun taalversies. De datum (`lastmod`) verandert alleen als de inhoud van een pagina echt verandert (titel, description of de inhoud tussen `<main>` en `</main>`); de vingerafdrukken staan in `tools/site/lastmod.json`. Een nieuwe kop of voet telt niet mee.
- `robots.txt` met een verwijzing naar de sitemap. Mijn BSO stuurt `X-Robots-Tag: noindex, nofollow` mee.
- Het voorbeeld op Vercel stuurt voor alles `X-Robots-Tag: noindex` mee: het mag niet concurreren met de echte website.
- De foutpagina (`404.html`) heeft `noindex` en geen canonical.
- Snel en mobielvriendelijk: AVIF/WebP in meerdere formaten, `loading="lazy"` onder de vouw, `fetchpriority="high"` op de grote foto bovenaan, lokaal gehoste letters en bijna geen JavaScript. Zet compressie aan op de server (staat in `.htaccess`); zonder compressie zakt de snelheidsscore naar ongeveer 88.

**Automatisch gecontroleerd** met `npm test` (`tests/seo.test.mjs`): titels en descriptions, canonical, Open Graph en sitemap, geen `noindex` in de sitemap, `hreflang`, één `h1`, alt-teksten, actuele schema.org-gegevens en sitemap, de juiste deelafbeelding per taal, interne links (ook naar mappen), geen links naar `index.html`, de doorverwijzingen in `.htaccess` en `vercel.json`, en dat de oude naam nergens meer staat.

## Nodig van Sporty

Deze informatie is nodig om de site af te maken. Ze staat ook als TODO in de code.

- [x] **Het echte telefoonnummer:** 06 18 26 67 06 (feedback 2.0), hetzelfde als WhatsApp, overal op de site en in de gegevens voor Google. Naam, adres en telefoon moeten op de website, in het Google Bedrijfsprofiel en elders precies gelijk zijn.
- [x] **Hoe laat Sporty op schooldagen opengaat:** vanaf 14.00 uur (de tijden van de middag, feedback 2.0), in de gegevens voor Google met de open dagen tot en vanaf 12 juli 2027. TODO: bevestigen dat dat ook op woensdag zo is.
- [ ] **De scholen waar jullie ophalen**, en per school: wie haalt op, hoe laat, lopend, op de fiets of met de bakfiets, en hoe lang de tocht is. Daarmee maken we een pagina "Ophalen van school" (ouders zoeken op de school van hun kind).
- [ ] **Bereikbaarheid van het clubhuis**: tram of bus en halte, fietsen parkeren, auto parkeren, en hoe halen en brengen op het sportpark werkt.
- [ ] **Eigen foto's**: binnen sinds 24 november 2026: het clubhuis van buiten, de speelkamer, de kantine en (25 november 2026) de chillruimte. Nog nodig: de sportvelden, het team, en kinderen in actie (alleen met toestemming van de ouders). Die gaan op de site en in het Google Bedrijfsprofiel.
- [x] **LRK-nummer en KvK-nummer:** 236315389 en 42064267 (feedback 2.0), in de footer en in de gegevens voor Google.
- [x] **Links naar social media:** Instagram, TikTok en Facebook, in de footer en in de gegevens voor Google (`sameAs`).
- [ ] **Herkomst van de reviews op de homepage.** Sporty is nog niet open; leg vast van wie de ervaringen komen (bijvoorbeeld proefdagen of eerder werk van het team) en of de namen erbij mogen. Anders kunnen ze beter weg tot na de opening.
- [ ] **Akkoord op de nieuwe teksten**: het label "Sportieve BSO in de Watergraafsmeer, Amsterdam-Oost" boven "Let's play", "Nieuw in de Watergraafsmeer: open vanaf 4 januari 2027", het blok "In het kort" op de locatiepagina, de labels boven de paginakoppen en de nieuwe deelafbeelding.
- [ ] Optioneel: **is er nog plek?** Zo ja, dan mag "nog plek, geen wachtlijst" op de site: daar zoeken veel ouders in Amsterdam op. **Spreken de coaches Engels?** Dan noemen we dat op de Engelse site ("English-speaking after-school care").
- [ ] Optioneel: **vragen die ouders vaak stellen** of onderwerpen voor een paar artikelen (bijvoorbeeld "Wat kost een BSO in Amsterdam?", "Kinderopvangtoeslag voor de BSO uitgelegd", "Sport-BSO of sportclub?").

## Buiten de website regelen

Op volgorde: eerst wat vóór de livegang moet, dan wat het meeste oplevert.

1. **Domein en mailbox.** Registreer `bsosporty.nl` en maak de mailbox info@bsosporty.nl aan en test hem. De site gebruikt beide al overal. Zie stap 0 in [`live-zetten-vimexx.md`](live-zetten-vimexx.md).
2. **De site live zetten** bij Vimexx volgens [`live-zetten-vimexx.md`](live-zetten-vimexx.md). Controleer daarna dat `https://bsosporty.nl/index.html` doorstuurt naar `https://bsosporty.nl/` en dat `www.` doorstuurt naar het adres zonder `www`.
3. **Google Bedrijfsprofiel** (business.google.com). Dit heeft het meeste effect op "BSO Watergraafsmeer" en "BSO in de buurt".
   - Naam: precies "Sporty", zonder extra zoekwoorden (dat mag niet van Google). Adres: Voorlandpad 17, 1098 TZ Amsterdam. Telefoon: het echte nummer. Website: `https://bsosporty.nl/`.
   - Hoofdcategorie: de categorie die het best past bij buitenschoolse opvang (bijvoorbeeld Kinderopvang).
   - Vul de openingsdatum in (4 januari 2027). Voor zover bekend toont Google een nieuw bedrijf tot 90 dagen voor de opening als "binnenkort geopend"; controleer dat bij het aanmaken.
   - Verifieer het profiel op de manier die Google vraagt (bijvoorbeeld een video van de locatie). Voeg daarna openingstijden, een korte beschrijving en eigen foto's toe.
   - Na de opening: vraag tevreden ouders om een review (Google geeft een link om te delen) en reageer op elke review. Alleen echte reviews; nooit reviews kopen of laten schrijven.
4. **Google Search Console** (search.google.com/search-console): voeg `bsosporty.nl` toe als domein (verifiëren met een TXT-record in de DNS bij Vimexx), dien `https://bsosporty.nl/sitemap.xml` in en kijk na een paar dagen of de Nederlandse en Engelse pagina's geïndexeerd zijn. Daar zie je ook op welke zoekwoorden ouders Sporty vinden.
5. **Bing Webmaster Tools** (bing.com/webmasters): importeer de site uit Search Console. Bing levert ook de zoekresultaten van andere zoekmachines.
6. **Apple Business Connect** (businessconnect.apple.com): dezelfde gegevens voor Apple Kaarten op iPhones.
7. **LRK-registratie** loopt via de gemeente Amsterdam (met een inspectie door de GGD). Zet het LRK-nummer daarna op de website; ouders zoeken ook in het register.
8. **Social media** aanmaken of bijwerken met precies dezelfde naam, hetzelfde adres en telefoonnummer en een link naar de website. Geef de links door; dan komen ze in de footer en in de gegevens voor Google (`sameAs`).
9. **Vermeldingen en links van anderen**: scholen in de buurt (website of nieuwsbrief), de verenigingen op Sportpark Middenmeer, wijkwebsites en -kranten van de Watergraafsmeer en Amsterdam-Oost, en overzichtssites van kinderopvang. Overal dezelfde naam, hetzelfde adres en telefoonnummer.
10. **Na de opening** (4 januari 2027): laat "Nieuw … open vanaf 4 januari 2027" op de homepage en in de description aanpassen (TODO in `index.html` en `en/index.html`), en zet de openingstijden in het Google Bedrijfsprofiel goed voor de periode tot en vanaf 12 juli 2027.

## Extra pagina's voor zoektermen (advies)

- **Eén pagina per school: alleen met echte, eigen inhoud.** Bijna dezelfde pagina's waarin alleen de schoolnaam verandert, ziet Google als "doorway pages" (spam). Begin daarom met één pagina **"Ophalen van school"**: per school een blok met hoe het ophalen werkt. Groeit dat per school uit tot een flinke eigen tekst, dan kan een school een eigen pagina krijgen.
- **"Sportieve BSO"**: een pagina "Wat is een sport-BSO?" met echte informatie (wat kinderen doen, wat het oplevert, ook voor kinderen die niet sportief zijn) kan helpen, zodra de teksten er zijn.
- **De rekentool** als eigen pagina ("Kinderopvangtoeslag voor de BSO berekenen") kan ouders trekken die nog geen BSO hebben gekozen.

## Onderhoud

| Wanneer | Wat |
|---|---|
| Tekst, vraag of gegevens van de BSO gewijzigd | `npm run seo` (schema.org en sitemap met nieuwe datum) en daarna `npm test` |
| Naam, slogan, kernwaarden of de kop van de homepage gewijzigd | `npm run og-image` (maakt beide deelafbeeldingen opnieuw; vereist Playwright) |
| Nieuwe pagina | Titel, description, canonical en Open Graph-tags in de `<head>`; de pagina en de Engelse versie in `tools/site/talen.mjs` en `PAGINAS` in `tools/structured-data.mjs`; dan `npm run kop-en-voet`, `npm run seo` en `npm test` |
| Pagina hernoemd | Doorverwijzing van het oude adres in `.htaccess` en `vercel.json` (`npm test` controleert dat) |
