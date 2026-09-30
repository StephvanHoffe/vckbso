# Beheeromgeving (`/beheer/`): voorstel

De klant wil naast de website een beheeromgeving met een kindvolgsysteem. Dit document beschrijft wat er gevraagd is, waarom het niet in de eerste oplevering zit en hoe je het het beste kunt aanpakken.

## Wat de klant wil

Volgens de briefing ("een pre"):

- **Aanmelden via de website.** Ouders schrijven zich in en komen als klant in het systeem, met hun kinderen eronder.
- **Automatische incasso.** Bij de aanmelding geeft de ouder een machtiging af, zodat betalen vanzelf gaat. Facturen zijn online terug te vinden.
- **Kindvolgsysteem.** Elk kind individueel volgen en de ontwikkeling bijhouden.
- **Foto's delen.** Begeleiders sturen foto's via de beheeromgeving naar de ouders van dat ene kind.
- **Berichten.** Ouders en begeleiders kunnen elkaar berichten sturen, bijvoorbeeld bij een ziekmelding.
- **Digitale groepsagenda.** Medewerkers zien in één overzicht welke kinderen wanneer komen. Per groep stel je een maximale groepsgrootte in. Ouders schrijven hun kind zelf in op dagen. De agenda werkt dus twee kanten op.

## Waarom dit niet in de eerste oplevering zit

De bouwinstructies vragen om een **statische website** zonder framework en zonder build-stap. Wat hierboven staat, is een complete webapplicatie met accounts, een database en betalingen. Daar komt veel bij kijken:

- **Gegevens van kinderen** (ontwikkeling, foto's, gezondheid bij ziekmeldingen) vragen extra zorg onder de AVG. Denk aan een DPIA, toestemming van ouders voor foto's, bewaartermijnen, verwerkersovereenkomsten en hosting in de EU.
- **Beveiliging:** inloggen voor ouders en medewerkers, tweestapsverificatie voor medewerkers, rollen en rechten (een ouder ziet alleen zijn of haar eigen kind), logging en back-ups.
- **Betalen:** SEPA-machtigingen, incasso's, mislukte afschrijvingen en facturen. Facturen en jaaropgaven moeten ook bruikbaar zijn voor de kinderopvangtoeslag van ouders.

Dat is een apart project, dat je los van de website wilt kunnen onderhouden en beveiligen.

## Twee routes

### Route A: een bestaand kinderopvangpakket (eerst vergelijken)

Er zijn Nederlandse softwarepakketten voor kinderopvang die precies deze functies bieden: planning met groepsgroottes, een ouderportaal of ouderapp met foto's en berichten, een kindvolgsysteem en facturatie met automatische incasso. Voorbeelden om te vergelijken zijn Konnect, Flexkids, KindPlanner en Bitcare. Dat zijn namen ter indicatie: vergelijk zelf de actuele functies, prijzen en voorwaarden.

- **Voordelen:** snel te gebruiken, beveiliging en AVG zijn grotendeels geregeld, wettelijke eisen zitten er al in en ouders krijgen meestal een app.
- **Nadelen:** maandelijkse kosten en minder maatwerk.
- **Koppeling met de website:** een knop "Inloggen voor ouders" in de header en een link naar het aanmeldformulier van het pakket.

### Route B: maatwerk

Een eigen applicatie op `/beheer/` (of op een subdomein, bijvoorbeeld `mijn.bsovck.nl`) met:

- een backend met database en inlogsysteem voor ouders en medewerkers;
- een betaalprovider die SEPA-incasso met machtigingen ondersteunt (bijvoorbeeld Mollie of GoCardless);
- de onderdelen ouder, kind, groep, dagdeel, inschrijving, observatie (kindvolg), bericht, foto, factuur en machtiging.

- **Voordelen:** helemaal naar eigen wens.
- **Nadelen:** hoge bouwkosten, langere doorlooptijd, en blijvend onderhoud en beveiliging.

## Aanbevolen volgende stappen

1. Vraag bij twee of drie leveranciers van kinderopvangsoftware een demo aan en vergelijk die met de wensenlijst hierboven.
2. Regel de privacy: een DPIA, een toestemmingsformulier voor foto's en verwerkersovereenkomsten.
3. Koppel de gekozen oplossing aan de website. De plekken waar dat moet, staan als `TODO` in de HTML: inschrijven (Diensten, Veelgestelde vragen), berichten en foto's (Veelgestelde vragen) en betalen (Prijzen).

## Wat al klaarstaat op de website

- Een aanmeldpagina (`aanmelden.html`), bereikbaar via de knop "Aanmelden" in de header. Ouders vullen hun eigen gegevens in, een of meer kinderen (voornaam, geboortedatum, school), gewenste dagen, startdatum en opmerkingen. De aanmelding komt nu per e-mail binnen via een formulierendienst. Zodra de beheeromgeving er is, kan dezelfde knop naar het aanmeldproces daar wijzen, inclusief de machtiging voor automatische incasso.
- Het contactformulier heeft het onderwerp "Ik wil mijn kind inschrijven".
- De stappen "Zo werkt het" op de pagina Diensten beschrijven de inschrijving op een manier die later met online aanmelden kan worden uitgebreid.
