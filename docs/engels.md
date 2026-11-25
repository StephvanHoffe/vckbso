# De Engelse website

Sinds november 2026 staat de hele website ook in het Engels, onder `/en/` (op verzoek van Sporty, nadat de Nederlandse teksten waren goedgekeurd). Mijn BSO (`/beheer/`) blijft Nederlands; links daarnaartoe zeggen dat erbij ("in Dutch").

- **Welke pagina bij welke hoort:** `tools/site/talen.mjs`. De taalknop in de kop, de `hreflang`-links en de sitemap komen daaruit.
- **Kop en voet:** `tools/site/kop.en.html` en `tools/site/voet.en.html`, ingevuld met `npm run kop-en-voet` (net als de Nederlandse).
- **Teksten in scripts** (formulieren, rekentool, proefdagagenda): de scripts kijken naar `<html lang="en">` en kiezen dan de Engelse tekst.
- **Formulieren:** de Engelse formulieren sturen `taal=en` mee. Mijn BSO antwoordt dan in het Engels (foutmeldingen en de pagina na versturen) en stuurt de Engelse bevestigingsmail. Die mails staan in Mijn BSO onder E-mails, met "(Engels)" in de naam.
- **Nieuwe of gewijzigde tekst:** pas altijd beide talen aan. `npm test` controleert dat elke pagina een Engelse tegenhanger heeft en dat de links kloppen.

## Woordenlijst

Brits Engels (favourite, practise, colour), in dezelfde toon als de Nederlandse site: informeel ("you"), enthousiast, met humor, maar duidelijk. Namen en vaste termen:

| Nederlands | Engels |
|---|---|
| BSO, buitenschoolse opvang | after-school care (BSO); bij de eerste keer op een pagina "after-school care (BSO)" |
| Sporty, BSO Sporty | Sporty, BSO Sporty (niet vertalen) |
| Let's Play | Let's Play |
| Kom bij de club | Join the club |
| (Boek een) gratis proefdag | (Book a) free trial day |
| Blijf op de hoogte | Stay in the loop |
| Sporty-kennismakingspakket | Sporty welcome goodies (het cadeautje bij Blijf op de hoogte) |
| Sporty-pakket (in de pop-up) | Sporty goodies |
| Incidenteel (abonnement) | Flexible (incidenteel) |
| Sportvereniging | sports club |
| welkomstpakket (bij het abonnement) | welcome pack |
| Alles over Sporty / Visie en droom | About Sporty / Our vision and dream |
| Abonnement en kosten | Membership and costs |
| abonnement | membership |
| kinderopvangtoeslag | childcare benefit (kinderopvangtoeslag) |
| rekentool | cost calculator |
| Vakantie- en studiedagen | Holidays and study days (studiedag: "study day, when school is closed for teacher training") |
| Welkom bij de club | Welcome to the club |
| Pauzebegeleiding voor scholen | Lunch-break supervision for schools |
| Een middag bij Sporty | An afternoon at Sporty |
| Locaties | Locations |
| clubhuis | clubhouse |
| Team Sporty | Team Sporty |
| Sporty coach, Word Sporty coach | Sporty coach, Become a Sporty coach |
| Contact en veelgestelde vragen | Contact and FAQ |
| Klachtenregeling | Complaints procedure |
| Privacyverklaring | Privacy statement |
| Algemene voorwaarden | Terms and conditions |
| Plezier · Vriendschap · Groei | Fun · Friendship · Growth |
| Henny de hond | Henny the dog |
| Sporty-kid | Sporty kid |
| Mijn BSO | Mijn BSO (our parent portal, in Dutch) |
| Inloggen | Log in |
| groep (op de basisschool) | school year (groep) |
| Geschillencommissie Kinderopvang | Dutch Childcare Disputes Committee (Geschillencommissie Kinderopvang) |
| LRK-nummer | LRK number (Dutch National Childcare Register) |
| KvK-nummer | Chamber of Commerce (KvK) number |
| GGD-inspectierapport | GGD inspection report |
| Sportpark Middenmeer, Watergraafsmeer, Amsterdam-Oost | Sportpark Middenmeer, Watergraafsmeer, Amsterdam East |
| Ouder van een Sporty-kid | Parent of a Sporty kid |

Notatie: tijden als 18:30, datums als Monday 4 January 2027, bedragen als €12.50, telefoon als +31 6 18 26 67 06 (de link blijft `tel:+31618266706`).
