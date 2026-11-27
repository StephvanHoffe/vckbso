# Beheeromgeving en Mijn BSO (`/beheer/`)

De beheeromgeving is een eigen webapplicatie naast de statische website. Ouders gebruiken hem als **Mijn BSO**, het team als **Beheer**. Alles staat in de map `beheer/`.

## Wat kan het?

| Wens van de klant | Waar | Hoe het werkt |
|---|---|---|
| Aanmelden op de website en als klant in het systeem komen, met de kinderen eronder | `aanvraag.php` (formulier "Kom bij de club" op `aanmelden.html`), `ouder.php` | Sinds november 2026 vrijblijvend, in 7 stappen: gegevens kind, school, abonnement, dagen, gegevens ouder, partner en controleren. Er ontstaat een ouder met status "nieuw" (nog zonder wachtwoord) met de kinderen eronder; het team krijgt een e-mail en ziet de aanmelding bij Ouders. Na de kennismaking activeert het team het account; de ouder krijgt dan een link om een wachtwoord te kiezen en geeft daarna de machtiging. Zie [Aanmelden via de website](#aanmelden-via-de-website). |
| Dagen die vol zitten, niet aan te vinken bij het aanmelden | `groepen.php` (blok "Dagen die vol zitten"), `aanvraag.php` | Het team vinkt bij Groepen aan welke weekdagen vol zitten. In het aanmeldformulier staan die dagen dan grijs met "(vol)" en zijn ze niet aan te vinken; ouders lezen dat ze in de opmerkingen om de wachtlijst kunnen vragen. Raakt een dag vol terwijl een ouder het formulier invult, dan weigert de server die dag met een duidelijke melding. Bestaande klanten merken er niets van: in de agenda geldt gewoon de maximale groepsgrootte. Elke wijziging staat in het logboek. |
| Automatische incasso bij de aanmelding | `machtiging.php` | Direct na het aanmelden geeft de ouder een SEPA-machtiging op een beveiligde pagina van Stripe: via de eigen bank met iDEAL \| Wero (€ 0,01 die Stripe meteen terugbetaalt) of met het IBAN. Daarna kan elke factuur automatisch worden afgeschreven. Zie [Stripe koppelen](#stripe-koppelen). |
| Facturen online terugvinden | `facturen.php`, `factuur.php` | Beheerder maakt per maand achteraf de facturen en start de incasso: het Sporty Abonnement als vast bedrag per maand, Incidenteel per afgenomen dag (zie [Facturen](#facturen)). Ouders zien, printen en (bij een mislukte incasso) betalen hun facturen online. |
| Kindvolgsysteem | `kind.php` | Per kind een dossier: gegevens, allergieën, ophaalpersonen, aanwezigheid (binnen en opgehaald), foto's en observaties per ontwikkelgebied. Een observatie kan alleen voor het team zijn, of gedeeld worden met de ouders. |
| Verborgen notities per kind, alleen voor het team (november 2026) | `kind.php` (blok "Verborgen notities"), `kinderen.php` (kolom naast "Let op") | Voor opmerkingen die niet per se voor ouders zijn, zoals een kind dat niet goed met een ander kind kan. Iedereen van het team met toegang tot het kind leest en schrijft ze; ouders zien ze nergens in Mijn BSO en ook niet in de download van hun eigen gegevens. Versleuteld opgeslagen, met wie ze het laatst aanpaste; elke wijziging staat in het logboek (zonder de tekst). Let op: bij een formeel inzageverzoek (AVG) heeft een ouder wel recht op deze notities. Daarom staan ze in de export die het team bij Privacyverzoeken eerst controleert. Schrijf ze dus feitelijk en respectvol. Ze worden gewist met het dossier. |
| Foto's sturen naar de ouders van een kind | `fotos.php` | Begeleider kiest foto's en de kinderen die erop staan. Alleen hun verzorgers (met het recht "foto's") zien de foto. Per kind kiest wie gezag heeft: ook groepsfoto's, alleen foto's waar het kind alleen op staat, of geen foto's (zie [Fototoestemming](#fototoestemming)). Kinderen met "geen foto's" of zonder ingevulde keuze zijn niet aan te vinken (en de server weigert ze); groepsfoto's kunnen alleen met toestemming van alle ouders. Locatiegegevens (EXIF) worden uit de foto gehaald. |
| Berichten heen en weer, bijvoorbeeld bij ziekte | `berichten.php` | Per gezin één gesprek met het team. Ouders kunnen hun kind ook ziek melden voor een of meer dagen; die dagen worden dan automatisch afgemeld. |
| Overzicht welke kinderen wanneer komen, met maximale groepsgrootte | `agenda.php`, `index.php`, `groepen.php` | Per groep een maximum. Weekoverzicht met bezetting per dag (kleur bij bijna vol en vol) en een dagoverzicht met namen, allergieën en knoppen voor binnen, opgehaald, ziek en afmelden. |
| Digitale agenda die twee kanten op werkt | `agenda.php` | Ouders kiezen zelf dagen (los of vaste weekdagen voor een periode) en melden af tot een ingestelde tijd op de dag zelf. Vol? Dan op de wachtlijst; bij een afmelding schuift de volgende automatisch door en krijgt een bericht. Het team kan ook zelf kinderen toevoegen, afmelden, boven het maximum plaatsen, een dag sluiten of de groepsgrootte voor één dag aanpassen. Ouders krijgen daar automatisch bericht van. |
| Financiële inkomsten en uitgaven inzien | `financien.php` | Per jaar of maand: inkomsten, uitgaven, resultaat en wat nog te ontvangen is, een grafiek per maand en een verdeling per categorie. Betaalde facturen tellen automatisch mee als inkomsten; overige inkomsten en alle uitgaven voer je in, met een bonnetje (pdf of foto). Export als CSV voor de boekhouder. Zie [Financiën](#financiën). |
| Ouders kiezen zelf een moment voor een gratis proefdag (november 2026) | `proefdagen.php`, `proefdag.php` (formulier op `proefdag.html`) | Het team zet momenten met een aantal plekken klaar; ouders kiezen er een op de website en krijgen een bevestiging. Zie [Proefdagen](#proefdagen). |
| Blijf op de hoogte, met een kennismakingspakket (november 2026) | `op-de-hoogte.php` (formulier op `blijf-op-de-hoogte.html` en de pop-up op de website), `interesse.php` (menu "Op de hoogte") | Ouders laten hun naam, e-mail, huisadres en eventueel telefoonnummer en de naam van hun kind achter (sinds feedback 2.0 ook via een pop-up na 10 seconden; kind en adres versleuteld, migratie 22; het adres wordt 30 dagen na het versturen van het pakket gewist; de lijst laat zien of iemand via de pagina of de pop-up kwam) en bevestigen dat met de link in een e-mail (dubbele bevestiging: zo kan niemand een ander aanmelden). Pas dan staan ze op de lijst en krijgt het team een e-mail. Het team vinkt aan wie het kennismakingspakket heeft gekregen (het tellertje in het menu laat zien hoeveel er nog wachten), stuurt iedereen een update (elk een eigen e-mail met een persoonlijke afmeldlink) en kan de lijst als CSV downloaden. Afmelden wist de gegevens meteen; niet bevestigd na 14 dagen weg, verder na de bewaartermijn (standaard 24 maanden). Links in e-mails openen eerst een pagina met een knop, zodat een virusscanner die links vooraf opent niets bevestigt of afmeldt. |
| Binnenkort-pagina en livegang (coming soon, november 2026) | `inc/binnenkort.php`, `interesse.php` (bovenaan bij "Op de hoogte"), `voorproef.php`, `cli/onderhoud.php` | Zolang `binnenkort.aan` in de webmap staat, zien bezoekers alleen `binnenkort.html` (en `en/coming-soon.html`), met hetzelfde formulier als Blijf op de hoogte (bron "binnenkort", migratie 24). **Website live zetten** (recht Instellingen) haalt het bestand weg en mailt automatisch iedereen die zich daar aanmeldde en dat bevestigde (mailtekst "Binnenkort-pagina: de website is live", NL en EN). Wie later bevestigt, krijgt de mail meteen; mislukte mails stuurt het dagelijkse onderhoud opnieuw; niemand krijgt hem twee keer. "Bekijk de echte website alvast" zet een cookie zodat het team de site vooraf ziet. Terug naar de binnenkort-pagina kan ook. Zie `docs/live-zetten-vimexx.md`. |
| Koppelingen beheren (november 2026) | `koppelingen.php` (Koppelingen) | Stripe (sleutels, betaalmethoden, of de webhook binnenkomt) en het versturen van e-mail (eigen mailserver via SMTP, de mailfunctie van de server of testmodus) instellen in Mijn BSO, met een testknop. Sleutels en wachtwoorden worden versleuteld opgeslagen. Zie [Koppelingen](#koppelingen). |
| Alle e-mails zelf aanpassen en zien wat er is verstuurd (november 2026) | `mailteksten.php` en `verzonden-emails.php` (E-mails), standaardteksten in `inc/mailteksten.php` | Zie [E-mails](#e-mails-aanpassen). |
| Collega's aanmaken en per persoon rechten geven | `medewerkers.php` (Team) | Per collega staat elk menuonderdeel aan of uit, plus de groepen en twee extra rechten. Zie [Rechten per persoon](#rechten-per-persoon). |

## De verplichte eisen en hoe ze zijn ingevuld

| Eis | Invulling |
|---|---|
| **Toegang per rol**: medewerkers zien alleen de kinderen waarvoor zij toegang nodig hebben | Per collega staat elk onderdeel van de beheeromgeving aan of uit (zie [Rechten per persoon](#rechten-per-persoon)); wat uit staat, is ook niet te openen. Daarnaast is elke collega gekoppeld aan een of meer groepen, eventueel tijdelijk tot een einddatum (bijvoorbeeld voor een invaller). Kinderen, dossiers, ouders, berichten, foto's, de agenda en het dagoverzicht tonen alleen de eigen groepen; een kind uit een andere groep geeft "niet gevonden". Alleen wie het extra recht "Alle groepen" heeft, ziet alle kinderen, ook nieuwe aanmeldingen zonder groep. |
| **Toegang per organisatie**: gegevens van meerdere opvangorganisaties strikt gescheiden | Elke organisatie krijgt een eigen domein, database, fotomap, sessiemap, encryptiesleutel, back-upsleutel en eventueel Stripe-account (zie [Meerdere organisaties](#meerdere-organisaties)). Er is geen gedeelde database waarin een fout filter gegevens kan laten doorlekken. Een onbekend domein krijgt geen toegang; twee organisaties met dezelfde map of sleutel weigert het systeem. |
| **Persoonlijke accounts** | Iedereen logt in met een eigen e-mailadres. Team en tweede verzorgers worden per persoon uitgenodigd met een eenmalige link. Er zijn geen gedeelde accounts. |
| **Meervoudige authenticatie** | Tweestapsverificatie met een authenticator-app (TOTP) en 10 eenmalige herstelcodes. Verplicht voor het hele team (kan niet worden overgeslagen); voor ouders aanbevolen of verplicht, naar keuze van de beheerder. Een code werkt maar één keer. |
| **Versleuteling** | Https met HSTS. Gevoelige velden (bijzonderheden en allergieën, ophaalpersonen, observaties, berichten, opmerkingen bij de aanmelding, privacyverzoeken, motivaties van sollicitanten, het MFA-geheim, de sleutels en wachtwoorden onder Koppelingen) en alle foto's, cv's en exports worden versleuteld opgeslagen (libsodium, XSalsa20-Poly1305) met een sleutel die niet in de database staat. Wachtwoorden en herstelcodes alleen als hash. |
| **Veilige back-ups** | `php beheer/cli/backup.php` maakt dagelijks een versleutelde back-up (XChaCha20-Poly1305, eigen sleutel) van database, foto's, bonnetjes en verwijderregister. Back-ups worden na 30 dagen automatisch verwijderd. Een kopie gaat naar objectopslag bij een andere partij in de EU, met object lock: niemand kan hem eerder wissen, ook een inbreker op de server niet (zie [Externe back-up](#externe-back-up)). |
| **Bijgewerkte software** | PHP 8.2 of hoger, geen pakketten van derden (alleen één meegeleverde QR-bibliotheek). De pagina Beveiligingsstatus waarschuwt als de PHP-versie binnen drie maanden geen beveiligingsupdates meer krijgt. |
| **Controleerbaarheid** | Het logboek registreert inzage (dossier bekeken, foto's, berichten, facturen), wijzigingen, exports, inloggen en beveiligingsacties, per kind of ouder te filteren. Elke regel is met een hashketen aan de vorige gekoppeld: aanpassen of weghalen valt op bij "Controleer logboek". Het logboek zelf is als CSV te exporteren (dat wordt ook gelogd). Bewaartermijn standaard 24 maanden. |
| **Ouderrechten: inzage, correctie, verwijdering** | Onder **Mijn privacy** downloadt een ouder direct de eigen gegevens (en die van kinderen waarover gecontroleerd gezag bestaat), en dient verzoeken in: inzage, correctie, verwijdering, beperking, bezwaar of overdracht. De beheerder handelt ze af onder **Privacyverzoeken**, met de wettelijke termijn van een maand in beeld, een volledige export (ook interne notities en wie het dossier heeft ingezien) en knoppen om een kinddossier of oudergegevens te wissen. Gegevens die wettelijk bewaard moeten worden (de financiële administratie) blijven staan. |
| **Wie is bevoegd namens het kind** | Per kind staan de verzorgers met hun relatie, of ze gezag hebben, en hun rechten (dagen kiezen, dossier inzien, foto's, berichten). Gezag geldt pas als de beheerder het heeft gecontroleerd (bijvoorbeeld met een uittreksel uit het gezagsregister) en vastgelegd. Alleen iemand met gecontroleerd gezag kan gegevens en toestemmingen wijzigen of een privacyverzoek namens het kind doen. Een account wordt pas geactiveerd als bij elk kind het gezag is gecontroleerd. Een oma of nieuwe partner kan bijvoorbeeld wel foto's zien, maar niets beslissen. |
| **Bewaarbeleid per gegevenscategorie** | Per categorie een eigen termijn (zie [Bewaartermijnen](#bewaartermijnen)), dagelijks automatisch uitgevoerd. Ontwikkelingsgegevens gaan snel weg na vertrek, de financiële administratie blijft 7 jaar. |
| **Verwijderen, ook in de back-upcyclus** | Back-ups vervallen na 30 dagen, dus gewiste gegevens zijn uiterlijk dan ook uit de back-ups verdwenen. Handmatige verwijderingen komen in een verwijderregister (alleen soort en nummer); na het terugzetten van een back-up voert `onderhoud.php --na-herstel` ze opnieuw uit. |

### Rechten per persoon

Onder **Team** zet je per collega elk menuonderdeel aan of uit. Een onderdeel dat uit staat, verdwijnt uit het menu én is niet te openen: de pagina's controleren het zelf (anders "Geen toegang").

| Onderdeel | Wat je ermee kunt |
|---|---|
| Vandaag | Wie er vandaag komt, aanwezigheid en ziekmeldingen |
| Agenda | Week- en dagoverzicht, inschrijven, afmelden en dagen aanpassen |
| Kinderen | Kinddossiers en het kindvolgsysteem |
| Ouders | Contactgegevens van ouders en verzorgers |
| Berichten | Berichten en ziekmeldingen van ouders |
| Foto's | Foto's delen met ouders |
| Groepen | Groepen, maximale groepsgrootte en dagen die vol zitten voor nieuwe aanmeldingen |
| Facturen | Facturen maken en incasso starten |
| Financiën | Inkomsten, uitgaven en bonnetjes |
| Team | Collega's uitnodigen en rechten geven |
| Proefdagen | Momenten klaarzetten voor een gratis proefdag en de boekingen van ouders |
| Sollicitaties | Sollicitaties (cv en motivatie) van de website (Word Sporty coach) |
| Privacyverzoeken | Privacyverzoeken van ouders afhandelen |
| E-mails | De teksten van alle e-mails aanpassen, en zien wat er is verstuurd (en wat niet lukte) |
| Koppelingen | Stripe en e-mail koppelen, met sleutels en wachtwoorden. Geef dit alleen aan wie het echt nodig heeft. |
| Instellingen | Instellingen, logboek en beveiligingsstatus |

Daarnaast twee extra rechten:

- **Alle groepen**: ziet alle kinderen en ouders, ook van kinderen die nog niet in een groep zitten. Zonder dit recht alleen de gekoppelde groepen.
- **Klanten beheren**: aanmeldingen activeren, gegevens van kinderen wijzigen, gezag vastleggen, verzorgers koppelen, kinderen indelen en klanten stoppen of hun gegevens wissen. Werkt samen met Ouders en Kinderen.

Snelknoppen: **Alles aan** (beheerder), **Begeleider** (Vandaag, Agenda, Kinderen, Ouders, Berichten en Foto's; wat medewerkers vóór deze wijziging ook konden) en **Alles uit**. Bij de invoering hebben bestaande beheerders alle rechten gekregen en bestaande medewerkers het pakket voor begeleiders, dus niemand verloor of kreeg toegang.

Zo raak je nooit buitengesloten: je kunt je eigen toegang tot Team niet uitzetten, en er blijft altijd minstens één actieve collega die het team beheert. Iemand zonder onderdelen ziet na het inloggen een uitleg; iemand zonder Vandaag komt op het eerste onderdeel dat wel aan staat. Elke wijziging staat in het logboek (soort Beveiliging).

**Ouders en verzorgers** zien in Mijn BSO alleen de kinderen waaraan ze gekoppeld zijn, met de rechten die per kind zijn ingesteld. De contracthouder ziet ook de facturen.

## Sollicitaties

Het formulier op **Word Sporty coach** (`word-sporty-coach.html`) verstuurt sollicitaties naar de eigen beheeromgeving (`beheer/solliciteren.php`): gratis en zonder externe formulierendienst.

- **Wat er binnenkomt:** naam, e-mail, telefoon, het cv en de motivatie (allebei pdf of Word, elk hooguit 5 MB). Het soort bestand wordt aan de inhoud gecontroleerd, niet alleen aan de naam. Sollicitaties uit de eerste versie van het formulier (Werken bij, tot november 2026) hebben ook een woonplaats, functie, dagen, link en een motivatie als tekst; die blijven gewoon zichtbaar.
- **Veilig bewaard:** het cv en de motivatie staan versleuteld in de datamap (`sollicitaties/`, gaat mee in de back-up), een motivatie als tekst, de link en de notities van het team versleuteld in de database.
- **Melding:** het team krijgt een e-mail op het teamadres (onder Koppelingen), zonder persoonsgegevens, met een link naar de sollicitatie. De sollicitant krijgt een ontvangstbevestiging.
- **Afhandelen:** onder **Sollicitaties** (eigen recht, standaard alleen voor beheerders) zie je alle sollicitaties, open je het cv en de motivatie (staat in het logboek als inzage), zet je de status (Nieuw, Uitgenodigd, Geen plek, Aangenomen), schrijf je een notitie en kun je een sollicitatie meteen verwijderen. Het menu toont hoeveel nieuwe er zijn.
- **Bewaren:** elke sollicitatie toont de datum waarop hij vanzelf wordt verwijderd (zie de tabel bij Bewaarbeleid).
- **Tegen spam:** een verborgen veld, alleen vanaf de eigen website, en hooguit 5 verstuurde sollicitaties per kwartier vanaf hetzelfde adres.
- **Uploadgrootte:** de server moet bestanden van 5 MB accepteren (`upload_max_filesize`; zie de instellingen voor foto's bij de installatie).
- **Zonder beheeromgeving** (zoals op de statische voorbeeldsite op Vercel) opent het formulier het e-mailprogramma van de bezoeker, met de sollicitatie erin; het cv en de motivatie voegt die dan zelf als bijlage toe.

## Aanmelden via de website

Ouders melden hun kind vrijblijvend aan op de pagina **Kom bij de club** (`aanmelden.html`), in 7 stappen met één onderwerp per scherm. Henny rent over de voortgangsbalk mee.

1. **Gegevens kind:** voornaam, tussenvoegsel, achternaam, voorletters, geslacht, geboortedatum, gewenste startdatum, groep of klas, en allergieën of medicijnen. Meer kinderen kan (tot 6).
2. **School** per kind (een lijst met scholen kan erbij zodra Sporty die aanlevert), en de sportvereniging van het kind (niet verplicht; feedback 2.0), zodat het team weet of het kind na Sporty naar de sportclub moet. Die staat bij de ouder onder "Gegevens uit het aanmeldformulier".
3. **Abonnement** per kind: het Sporty Abonnement (52 weken, € 9,75 per uur) of Incidenteel (€ 12 per uur), zie `AANVRAAG_ABONNEMENTEN` in `inc/aanvraag.php`.
4. **Dagen** per kind, met een toelichting. Dagen die vol zitten (Groepen) zijn niet aan te vinken. Bij Incidenteel zijn vaste dagen niet verplicht ("Geen vaste dagen (incidenteel)" bij de ouder).
5. **Gegevens ouder:** naam, voorletters, geslacht, adres, e-mail, telefoon, hoe je ons vond en opmerkingen.
6. **Partner:** ja of nee, en zo ja naam en contactgegevens.
7. **Controleren** met een overzicht en een potloodje per blok, drie vinkjes en de knop "Verstuur vrijblijvend".

- **Wat er gebeurt:** `aanvraag.php` controleert alles opnieuw en maakt een ouder met status "nieuw" (zonder wachtwoord) met de kinderen eronder. Gegevens die alleen in het formulier staan (voorletters, geslacht, groep, startdatum en dagen per kind, partner, hoe gevonden) staan versleuteld in de kolom `aanvraag` en zijn te zien bij de ouder onder "Gegevens uit het aanmeldformulier". Allergieën komen in de bijzonderheden van het kind. De ouder krijgt meteen een bevestiging per e-mail (voorlopige tekst, TODO) met een link om direct een wachtwoord voor Mijn BSO te kiezen (een week geldig; bij een Engelse aanmelding is de wachtwoordpagina Engels), het team een melding zonder persoonsgegevens. Via die link is ook het e-mailadres bevestigd. Een ouder met status "nieuw" kan inloggen en ziet "Zo gaat het verder" (en kan de machtiging al geven), maar kiest pas dagen als het team het account heeft geactiveerd. Een wachtwoordveld in het formulier zelf zit er bewust niet in: dan kan iemand een account maken op andermans e-mailadres, en een tikfout in het adres maakt het account onbereikbaar.
- **Afronden:** na de kennismaking controleert het team het gezag, zet de kinderen in een groep en activeert het account. Heeft de ouder nog geen wachtwoord, dan krijgt die opnieuw een link (een week geldig), anders een welkomstmail; daarna vraagt Mijn BSO om de machtiging voor automatische incasso. De partner nodig je eventueel uit als verzorger in het dossier van het kind.
- **Onthouden:** wat een ouder invult, bewaart de browser op dat apparaat (localStorage), zodat de ouder later verder kan. Na het versturen wordt het gewist.
- **Meten:** per stap telt `aanvraag.php` anoniem hoe vaak die is bereikt (zonder cookies of persoonsgegevens). Bij Ouders → Nieuwe aanmeldingen staat het overzicht van de afgelopen 30 dagen. De tellingen blijven een jaar.
- **Zonder JavaScript** is het één lang formulier dat gewoon verstuurd kan worden. `aanmelden.php` (de oude aanmeldpagina) stuurt door naar het nieuwe formulier.

## Proefdagen

Op de pagina **Proefdag** (`proefdag.html`) kiezen ouders zelf een moment voor een gratis proefdag. De momenten zet het team klaar onder **Proefdagen** (eigen recht, standaard voor beheerders).

- **Momenten klaarzetten:** datum, begin- en eindtijd en het aantal plekken. Vaste tijden zijn er niet (Sporty, 25 november 2026: het gaat in overleg en kinderen zijn altijd welkom); standaard staat de middag in het clubhuis klaar, 15.30–18.30 uur. Met "Herhalen" zet je in één keer hetzelfde moment voor een aantal weken achter elkaar klaar. Een moment kan alleen op een dag dat Sporty open is: tot 12 juli 2027 dinsdag en donderdag, daarna maandag t/m vrijdag (`beheer/inc/openingsdagen.php`). Ouders zien de momenten vanaf morgen en tot 12 weken vooruit in een agenda (maandag t/m vrijdag): alleen dagen met een moment waarop nog plek is, zijn aan te klikken; daaronder kiezen ze de tijd. Het aantal plekken pas je later nog aan; een moment zonder boekingen kun je verwijderen.
- **Boeken:** ouders vullen hun naam, e-mail en telefoon in, de naam en leeftijd van hun kind (4 t/m 12 jaar) en eventueel een opmerking. De controle op een vrije plek en de boeking gebeuren in één transactie, dus twee ouders kunnen nooit samen de laatste plek boeken. De ouder krijgt een bevestiging per e-mail, het team een melding zonder persoonsgegevens (`team_email` uit `config.php`). De tekst van de bevestigingsmail staat in `beheer/proefdag.php` (TODO: laten controleren door Sporty).
- **Afhandelen:** per moment zie je de boekingen met contactgegevens en de opmerking (versleuteld opgeslagen; bekijken komt in het logboek als inzage). Zet de status op Geweest, Niet gekomen of Geannuleerd. Het menu toont hoeveel proefdagen er gepland staan.
- **Bewaren:** een boeking wordt 4 weken na de proefdag vanzelf verwijderd, en een oud moment zonder boekingen ook.
- **Tegen spam:** een verborgen veld, alleen vanaf de eigen website, en hooguit 5 boekingen per kwartier vanaf hetzelfde adres.
- **Zonder beheeromgeving, of zonder momenten:** dan ziet de bezoeker een knop naar het contactformulier (met het onderwerp proefdag al gekozen) en het telefoonnummer. Op de voorbeeldsite op Vercel staan verzonnen momenten (`voorbeeld/beheer/proefdag-momenten.json`); boeken slaat daar niets op.

## E-mails aanpassen

Onder **E-mails** (een eigen onderdeel in het menu, recht E-mails; wie het recht Instellingen had, kreeg het erbij) staan alle 33 e-mails die Mijn BSO verstuurt: 13 aan ouders, sollicitanten en andere bezoekers, 11 aan het team (meldingen en uitnodigingen voor collega's), de ondertekening onder elke e-mail en 8 Engelse versies (zie [De Engelse website](#de-engelse-website)). Per e-mail zie je wanneer hij gaat.

- **Aanpassen:** onderwerp en tekst, als gewone tekst zonder opmaak. Invulvelden tussen accolades, zoals `{voornaam}` of `{link}`, vult Mijn BSO bij het versturen in. Per e-mail staat welke velden er zijn, met een voorbeeld.
- **Controle bij opslaan:** een invulveld dat niet bestaat (een tikfout als `{vornaam}`) wordt geweigerd, en verplichte velden, zoals de link om een wachtwoord te kiezen of de afmeldlink, moeten blijven staan.
- **Voorbeeld en testmail:** "Voorbeeld bekijken" toont de e-mail met voorbeeldgegevens; "Testmail naar mij" stuurt hem naar je eigen adres. Beide zonder op te slaan.
- **Terug naar de standaard:** met één klik, en het overzicht laat zien welke e-mails zijn aangepast (met datum). Elke wijziging staat in het logboek.
- **Verplichte link in een oude tekst:** ontbreekt een verplicht veld in een tekst die eerder is aangepast (zoals `{link}` in de aanmeldmail, verplicht sinds november 2026), dan zet Mijn BSO de waarde er onderaan bij. Bij de volgende keer opslaan vraagt de controle om het veld zelf in de tekst te zetten.
- **Techniek:** de standaardteksten staan in `beheer/inc/mailteksten.php` (`MAILTEKSTEN`), een aangepaste tekst in de tabel `mail_teksten` (migratie 14). Verstuur een e-mail altijd met `stuur_mailtekst()` of `mail_team_tekst()`; `npm test` controleert dat er geen e-mail buiten deze lijst om gaat en dat de standaardteksten kloppen.

### Verzonden e-mails

Onder **E-mails → Verzonden e-mails** (`verzonden-emails.php`) staat elke e-mail die Mijn BSO verstuurt: wanneer, aan wie, welke e-mail (met een link naar de tekst) en de uitkomst. Filter op uitkomst, zoek op adres of onderwerp, of klik op een adres voor alle e-mails aan dat adres. In het overzicht van de e-mails staat per e-mail hoe vaak hij de afgelopen 30 dagen ging, en bij elke e-mail de laatste vijf keer.

| Uitkomst | Betekenis |
|---|---|
| Verstuurd | De mailserver heeft de e-mail aangenomen. Komt hij niet aan, dan staat hij vaak bij de ongewenste e-mail. |
| Mislukt | Niet verstuurd, met de reden van de mailserver (bijvoorbeeld een verkeerd wachtwoord, of een servernaam die niet bij het certificaat past). |
| Testmodus | Koppelingen → E-mail versturen staat op Testmodus: er gaat niets de deur uit, de e-mail staat alleen in `data/mail.log`. |
| Overgeslagen | Bewust niet verstuurd: er gingen binnen een kwartier al 3 e-mails naar hetzelfde adres (de grens tegen misbruik van de formulieren, `te_veel_mails_naar()`). Een aanmelding wordt dan wel bewaard, een proefdag en Blijf op de hoogte niet. |

- **Waarschuwing:** staat e-mail in testmodus, of mislukte er het afgelopen etmaal een e-mail zonder dat er daarna een lukte, dan staat er een waarschuwing op Vandaag en bij E-mails (voor wie het recht E-mails of Koppelingen heeft).
- **Privacy:** de inhoud wordt niet bewaard (daar staan links in om een wachtwoord te kiezen); de tekst zie je bij de e-mail zelf. Bewaartermijn: zie [Bewaartermijnen](#bewaartermijnen). Bij het wissen van een ouder verdwijnen ook de verzonden e-mails aan dat adres, en een inzageverzoek bevat ze.
- **Techniek:** tabel `mail_log` (migratie 26), vastgelegd in `stuur_mail()` (`inc/mail.php`); `mail_overgeslagen()` voor bewust overgeslagen e-mails, `mail_waarschuwing()` voor de waarschuwing.

## De Engelse website

Sinds november 2026 staat de hele website ook in het Engels (`/en/`, zie [`docs/engels.md`](engels.md)). Mijn BSO zelf blijft Nederlands, maar wie een formulier op de Engelse site invult, krijgt alles wat daarna komt in het Engels.

- **Welke formulieren:** Kom bij de club (`aanvraag.php`), proefdag (`proefdag.php`), Blijf op de hoogte (`op-de-hoogte.php`, ook de pagina's om te bevestigen en af te melden) en solliciteren (`solliciteren.php`). De Engelse formulieren sturen `taal=en` mee.
- **Wat er Engels is:** foutmeldingen, de pagina na versturen en de bevestigingsmail, met een Engelse ondertekening en een link naar de Engelse site. Updates aan de lijst Op de hoogte gaan naar Engelse inschrijvers met de Engelse ondertekening en afmeldpagina; de tekst van de update zelf schrijf je één keer (Interesse toont hoeveel inschrijvers Engels zijn). Meldingen aan het team blijven Nederlands.
- **Engelse e-mails aanpassen:** onder E-mails, groep "In het Engels". Pas je een Nederlandse tekst aan, pas dan de Engelse ook aan.
- **Waar je het ziet:** bij de ouder ("Aangemeld via de Engelse website"), bij proefdagen, sollicitaties en Op de hoogte (label "Engels").
- **Techniek:** `taal()`, `t()` en `website_pagina()` in `beheer/inc/helpers.php`; de taal staat bij de aanvraag (versleuteld, in `aanvraag`) en in de kolom `taal` van `interesse`, `proefdag_boekingen` en `sollicitaties` (migratie 20). De Engelse mailteksten hebben dezelfde sleutel met `_en` erachter; `stuur_mailtekst()` kiest ze vanzelf. `npm test` controleert dat elke Engelse e-mail dezelfde invulvelden heeft als de Nederlandse.

## Koppelingen

Onder **Koppelingen** (eigen recht, standaard alleen beheerders) stel je in waarmee Mijn BSO verbonden is. Je hebt daarvoor geen toegang tot de server nodig.

| Koppeling | Wat je instelt | Testen |
|---|---|---|
| **Stripe** | Geheime sleutel en webhookgeheim; de betaalmethoden voor de machtiging (iDEAL \| Wero, IBAN) en voor los betalen (iDEAL \| Wero, Bancontact, creditcard). Het adres van de webhook en de gebeurtenissen staan erbij om over te nemen. | Een nieuwe sleutel wordt eerst bij Stripe getest; een foute of publieke sleutel (pk_) wordt geweigerd. Daarnaast "Verbinding testen", en je ziet wanneer de laatste melding van Stripe binnenkwam, of geweigerd werd omdat het webhookgeheim niet klopt. |
| **Externe back-up** | Objectopslag bij een andere partij in de EU (S3-standaard, bijvoorbeeld Scaleway of Hetzner): adres (endpoint), regio, bucket, map, adresvorm en een toegangssleutel die alleen mag uploaden. | "Opslaan en testen" zet eerst een klein testbestand in de opslag; lukt dat niet, dan wordt er niets opgeslagen. Je ziet wanneer de laatste kopie is verstuurd en de laatste fout. |
| **E-mail versturen** | Eigen mailserver (SMTP, aanbevolen), de mailfunctie van de webserver of testmodus (alleen `data/mail.log`); afzenderadres, afzendernaam en teamadres; bij SMTP de server, poort, beveiliging (STARTTLS of SSL/TLS), gebruikersnaam en wachtwoord. | "Opslaan en testmail naar mij", met een begrijpelijke foutmelding als het niet lukt (bijvoorbeeld een fout wachtwoord). Je ziet wanneer de laatste e-mail is verstuurd en de laatste fout. |

- **Veilig opgeslagen:** sleutels en wachtwoorden staan versleuteld in de database (tabel `koppelingen`, migratie 17) en zijn na het opslaan niet meer te lezen: je ziet alleen het begin en de laatste vier tekens (`sk_live_…9a3f`), een wachtwoord helemaal niet. Ze komen niet in het logboek; daar staat alleen wát er is gewijzigd en door wie.
- **Extra bevestiging:** elke wijziging onder Koppelingen (ook een afzenderadres of mailserver), en ontkoppelen, vraagt om je eigen wachtwoord; na 5 foute pogingen is dat een kwartier geblokkeerd. Wie de Stripe-sleutel kan veranderen, bepaalt waar het geld naartoe gaat; wie de mailserver kan veranderen, kan wachtwoordlinks onderscheppen. Verander je de mailserver of gebruikersnaam, dan vul je ook het wachtwoord van de mailbox opnieuw in: zo kan niemand het opgeslagen wachtwoord naar een andere server laten sturen.
- **Geen interne adressen:** een mailserver of opslag in het interne netwerk van de server (zoals 127.0.0.1 of 192.168.x.x) wordt geweigerd, en de opslag moet https gebruiken.
- **SMTP zonder extra software:** Mijn BSO verstuurt zelf via de mailserver, altijd versleuteld (STARTTLS op poort 587 of SSL/TLS op poort 465) en met controle van het certificaat. Vraag de gegevens op bij jullie e-mailprovider; met tweestapsverificatie bij de provider gebruik je een app-wachtwoord. Zorg dat SPF en DKIM van het domein deze mailserver toestaan (bij de mailbox van het eigen domein is dat meestal al zo).
- **config.php gaat voor:** wat in `config.php` staat (bijvoorbeeld bij meerdere organisaties), geldt en is onder Koppelingen alleen te zien. Zo kan een systeembeheerder iets vastzetten.
- **Techniek:** `beheer/inc/koppelingen.php` (`koppeling()`, `koppeling_zet()`), `beheer/inc/smtp.php` en `beheer/koppelingen.php`. Lees een koppeling altijd met `koppeling()`, niet met `cfg()`; `npm test` controleert dat.

## Fototoestemming

Per kind geeft wie gezag heeft aan of de begeleiders foto's van het kind mogen delen:

| Keuze | Wat er gebeurt |
|---|---|
| Ja, ook op groepsfoto's | Het kind kan op elke foto. Een groepsfoto gaat naar de ouders van alle kinderen erop. |
| Ja, maar alleen foto's waar het kind alleen op staat | Alleen losse foto's, die alleen naar de eigen verzorgers gaan. In de keuzelijst heeft het kind een gestippelde rand. |
| Nee, liever geen foto's | Het kind is bij Foto's niet aan te vinken ("geen foto's"). |
| Nog niet ingevuld | Net als "nee": niet aan te vinken ("nog geen toestemming"). De ouder ziet een herinnering op het overzicht en bij het kind. |

- **Waar:** ouders kiezen bij het toevoegen van een kind (verplicht) en bij het kind onder Gegevens wijzigen. Een beheerder kan het vastleggen op verzoek van wie gezag heeft, bijvoorbeeld na de kennismaking bij een aanmelding via de website. De keuze staat bij het kind, bij Kinderen (kolom Let op) en bij de ouder.
- **Afdwingen:** ook de server weigert een foto met een kind zonder toestemming, of een groepsfoto met een kind dat alleen eigen foto's mag.
- **Intrekken:** wie minder toestaat dan eerst, kan meteen de foto's laten weghalen die al gedeeld zijn: bij "nee" alle foto's van het kind, bij "alleen eigen foto's" de groepsfoto's. Die verdwijnen ook bij andere ouders. De wijziging staat in het logboek en het team krijgt een melding.
- **Techniek:** `kinderen.fotos_toegestaan` (NULL = nog niet ingevuld, 0 = nee, 1 = ja) met `fotos_toegestaan_op`, naast `foto_toestemming` voor groepsfoto's (migratie 15). Wie al toestemming had gegeven voor groepsfoto's, heeft bij de overstap "ja, ook op groepsfoto's"; de andere kinderen staan op "nog niet ingevuld". Functies in `beheer/inc/fotos.php` (`foto_keuze()`, `mag_op_foto()`).

## Financiën

Onder **Financiën** zie je per jaar of per maand wat er binnenkwam en wat eruit ging (kasbasis):

- **Inkomsten**: betaalde facturen tellen automatisch mee, op de dag van betaling. Andere inkomsten (bijvoorbeeld een subsidie of een bijdrage die buiten Mijn BSO is betaald) voer je zelf in.
- **Uitgaven**: voer je zelf in, met categorie, datum, bedrag, omschrijving en eventueel een bonnetje (pdf, jpg of png, maximaal 10 MB). Bonnetjes worden versleuteld opgeslagen.
- **Overzicht**: tegels met inkomsten, uitgaven, resultaat en wat nog te ontvangen is (openstaande facturen), een grafiek per maand (ook als tabel), en een verdeling per categorie.
- **Export**: "Exporteren voor de boekhouder" geeft een CSV-bestand (puntkomma's, opent in Excel) met alle boekingen en betaalde facturen van de gekozen periode. Elke export komt in het logboek.
- Boekingen en bonnetjes horen bij de financiële administratie: ze worden net zo lang bewaard als de facturen (minimaal 7 jaar) en daarna automatisch verwijderd.

Bedragen voer je in zoals ze op de bon staan. Financiën houdt btw niet apart bij; laat de boekhouder bepalen hoe de export wordt verwerkt.

### Een nieuwe klant, stap voor stap

1. Ouder meldt zich aan via de website en geeft de machtiging af. Bij de aanmelding geeft de ouder aan of die het gezag heeft.
2. Het team ziet de aanmelding op "Vandaag" en onder Ouders → Nieuwe aanmeldingen.
3. Bij de kennismaking: gezag controleren (bijvoorbeeld uittreksel gezagsregister) en vastleggen in het dossier van elk kind, onder **Verzorgers en gezag**. Kind in een groep zetten en het **abonnement** nalopen (Gegevens wijzigen → Abonnement: Sporty Abonnement met de vaste dagen, of Incidenteel, en de ingangsdatum). Wat de ouder in het aanmeldformulier koos, staat er al ingevuld.
4. Op **Account activeren** klikken. De ouder krijgt een welkomstbericht.
5. Eventueel een tweede verzorger toevoegen in het dossier van het kind (bijvoorbeeld de andere ouder), met de juiste rechten.
6. De ouder kiest de dagen in de agenda.
7. Na afloop van de maand: Facturen → maand kiezen → **Facturen maken** → controleren → **Incasso starten**.

## Facturen

Elke maand maakt de beheerder achteraf de facturen (Facturen → maand kiezen → **Facturen maken**), per ouder één factuur met een regel per kind. Hoe er gerekend wordt (antwoorden van Sporty, november 2026; code in `beheer/inc/facturen.php`, test in `tests/facturen.test.mjs`):

| Abonnement (bij het kind) | Op de factuur |
|---|---|
| **Sporty Abonnement (52 weken)** | Een vast bedrag per maand: aantal vaste dagen per week × 27 uur × € 9,75 (1 dag € 263,25, 2 dagen € 526,50). Afgemeld of ziek maakt niet uit. Begint of stopt het abonnement halverwege de maand (ingangsdatum bij het kind, of "komt naar de BSO" uitgevinkt), dan naar rato van het aantal kalenderdagen. |
| **Incidenteel** | Per afgenomen dag × € 12 per uur: 4,5 uur op een schooldag, 10,5 uur op een vakantie- of studiedag. Komt, ziek en minder dan 48 uur van tevoren afgemeld tellen mee; op tijd afgemeld niet. |
| Nog niet ingesteld | Geen factuurregel. Bij Facturen staat dan een melding met de namen, zodat je het abonnement bij het kind kunt invullen. |

- **Vakantiedagen bij het Sporty Abonnement (27 november 2026):** op de website staat nu "Vakantiedagen die je tijdig aan ons doorgeeft, brengen we uiteraard niet in rekening." Mijn BSO rekent het abonnement nog als vast bedrag per maand en trekt die dagen nog niet af. TODO, te bevestigen door Sporty: hoe ver van tevoren is "tijdig", en hoeveel gaat er per dag af (4,5 uur op een schooldag, 10,5 uur op een vakantiedag, × € 9,75)?
- **Vakantie- of studiedag:** zet zo'n dag in de agenda (dagoverzicht van de groep → **Deze dag aanpassen** → "Vakantie- of studiedag"). Ouders zien in hun agenda "Vakantiedag, hele dag".
- **Tarieven en uren** staan bij **Instellingen → Tarieven en afspraken** (uurtarieven, 27 uur per maand per vaste dag, 4,5 en 10,5 uur, kosteloos afmelden tot 48 uur). Ze staan bij een nieuwe installatie al goed (migratie 23).
- Een nieuwe aanmelding (ouder met status "nieuw") krijgt nog geen factuur; eerst activeer je het account.
- Een factuur annuleren zet de dagen van die maand weer open, zodat je na een correctie opnieuw een factuur kunt maken.
- Sporty is vrijgesteld van btw; dat staat op de factuur.

## Bewaartermijnen

De beheerder stelt de termijnen in onder **Instellingen → Bewaartermijnen**. Dit zijn de standaardwaarden; laat ze bevestigen (zie TODO).

| Categorie | Standaard | Wat er gebeurt |
|---|---|---|
| Ontwikkelingsgegevens (observaties) | 3 maanden na vertrek | Verwijderd |
| Kinddossier (gegevens, bijzonderheden, ophaalpersonen, toestemmingen, verzorgers) | 3 maanden na vertrek | Geanonimiseerd; alleen een regel zonder naam blijft voor de financiële administratie |
| Foto's | 12 maanden na plaatsen | Verwijderd, ook de bestanden |
| Berichten en ziekmeldingen | 24 maanden na verzenden | Verwijderd |
| Aanmeldingen die niet tot plaatsing leidden | 6 maanden | Account en kinderen verwijderd |
| Account en contactgegevens van gestopte klanten | 3 maanden na vertrek | Gewist; naam en adres blijven bij de facturen |
| Financiële administratie (facturen, betalingen, opvangdagen, boekingen en bonnetjes onder Financiën) | 7 jaar | Verwijderd. Korter dan 7 jaar kan niet (fiscale bewaarplicht) |
| Logboek | 24 maanden | Verwijderd |
| Verzonden e-mails (wanneer, aan wie, welke e-mail, uitkomst; zonder inhoud) | 3 maanden | Verwijderd |
| Sollicitaties (gegevens, cv en motivatie) | 4 weken na afronden (geen plek of aangenomen); met toestemming van de sollicitant een jaar na binnenkomst; nog niet afgerond: na 3 maanden (met toestemming na een jaar) | Verwijderd, ook het cv. Vaste termijnen, zoals in de privacyverklaring |
| Proefdagen (boekingen) | 4 weken na de proefdag | Verwijderd. Vaste termijn, zoals in de privacyverklaring |
| Exports voor een inzageverzoek | 30 dagen | Verwijderd |
| Back-ups | 30 dagen | Verwijderd |

Het bewaarbeleid draait dagelijks via cron (`php beheer/cli/onderhoud.php`). Zonder cron draait het vanzelf bij het eerste bezoek van de dag.

Om per ongeluk (of kwaadwillig) massaal wissen te voorkomen, heeft elke termijn een minimum: 1 maand (ook de verzonden e-mails), het logboek 12 maanden en de financiële administratie 7 jaar. Een termijn korter maken vraagt om je eigen wachtwoord en komt in het logboek.

## Logboek

Instellingen → Logboek. Filter op soort (inzage, wijziging, export, inloggen, beveiliging, AVG-verzoek, bewaarbeleid), op teamlid, of op één kind of ouder (link "Logboek" onderaan het dossier van het kind of de ouder). Met **Controleer logboek** wordt de hashketen nagerekend. Met **Exporteren (CSV)** download je de selectie.

## Back-ups en herstel

```sh
php beheer/cli/backup.php                                    # maakt backup-<organisatie>-<datum>.vckbak
php beheer/cli/herstel.php <back-upbestand> <lege map>       # ontsleutelt en pakt uit
php beheer/cli/onderhoud.php --na-herstel                    # verwijderingen opnieuw uitvoeren
```

Terugzetten, met de website even offline:

1. Pak de back-up uit in een lege map met `herstel.php`.
2. Zet de huidige datamap opzij (niet weggooien).
3. Zet `beheer.sqlite` en `fotos/` uit de back-up in de datamap.
4. Gebruik het **nieuwste** `verwijderregister.jsonl`: uit de opzijgezette datamap als dat er nog is, anders dat uit de back-up.
5. Draai `php beheer/cli/onderhoud.php --na-herstel`. Zo worden gegevens die na de back-up zijn gewist, opnieuw gewist.

Bewaar de back-upsleutel apart, bijvoorbeeld in een wachtwoordkluis. Test het terugzetten minstens één keer per jaar.

- De back-up wordt eerst als kopie van de database gemaakt in de afgeschermde back-upmap (niet in de gedeelde tijdelijke map van de server), meteen versleuteld en de kopie weggehaald; ook als het script halverwege stopt. Alle bestanden zijn alleen leesbaar voor de eigenaar.

### Externe back-up

Na de back-up stuurt `backup.php` het versleutelde bestand naar objectopslag bij een andere partij in de EU (in te stellen onder **Koppelingen → Externe back-up**). Zo is er nog een kopie als de server kwijt of gehackt is.

- **De aanbieder kan niets lezen:** het bestand is al versleuteld met de back-upsleutel, en die staat niet bij de aanbieder.
- **Niet te wissen:** zet bij de aanbieder object lock aan (modus "compliance", standaard 30 dagen). Dan kan niemand een back-up eerder verwijderen of overschrijven, ook iemand met de sleutel van de server niet.
- **Toch op tijd weg:** laat de aanbieder bestanden na 31 dagen automatisch verwijderen. Zo verdwijnen gewiste gegevens ook daar, net als op de server.
- **Een sleutel die alleen mag uploaden:** geef Mijn BSO geen sleutel waarmee hij kan lezen of wissen.
- **Mislukt het?** Dan krijgt het team een e-mail (tekst aan te passen onder E-mails), staat de fout onder Koppelingen en in de Beveiligingsstatus, en stopt `backup.php` met code 2, zodat ook cron een melding geeft.
- Techniek: `beheer/inc/externe_backup.php`, S3 met AWS Signature Version 4, zonder extra software.

## Meerdere organisaties

Eén installatie kan meerdere opvangorganisaties bedienen. Zet ze in `config.php` onder `organisaties` (zie `config.voorbeeld.php`). Per organisatie:

- eigen domein(en) in `hosts`: het domein bepaalt de organisatie, een onbekend domein krijgt "niet gevonden";
- eigen `data_dir` (database, foto's, bonnetjes, exports, sessies, verwijderregister) en eigen `sleutel`;
- eigen back-upmap en back-upsleutel, eventueel een eigen Stripe-account en e-mailafzender.

Cron-regels per organisatie: voeg `--organisatie=naam` toe, bijvoorbeeld `php beheer/cli/backup.php --organisatie=vck`.

## Techniek

- PHP 8.2 of hoger, zonder framework, zonder Composer en zonder build-stap. Extensies: `pdo_sqlite`, `sodium`, `gd`, `intl`, `mbstring`, `curl` (voor Stripe) en liefst `exif`. Die zitten bij vrijwel elke Nederlandse webhosting standaard aan; ontbreekt er een, dan meldt de beheeromgeving dat.
- Database: SQLite, één bestand in de datamap. Het schema wordt automatisch aangemaakt en bijgewerkt (`beheer/inc/database.php`). Een bestaande database van een eerdere versie wordt bij de update vanzelf omgezet, inclusief het versleutelen van bestaande gegevens en foto's.
- Opmaak: dezelfde design tokens en formulierstijlen als de website (`design/tokens.css`, `css/style.css`) plus `beheer/assets/beheer.css`.
- Alles werkt zonder JavaScript; `beheer/assets/beheer.js` maakt het alleen prettiger (en tekent de QR-code voor tweestapsverificatie).

| Map of bestand | Inhoud |
|---|---|
| `beheer/*.php` | De pagina's |
| `beheer/inc/` | Gedeelde code: configuratie, organisaties, versleuteling, database, inloggen, tweestapsverificatie, toegang, agenda, berichten, facturen, Stripe, e-mail, foto's, exports, bewaarbeleid, opmaak. Niet rechtstreeks bereikbaar. |
| `beheer/cli/` | Opdrachtregel: back-up, herstel en dagelijks onderhoud. Niet via de website bereikbaar. |
| `beheer/data/` | Standaard-datamap: database, foto's, bonnetjes, exports, sessies en `mail.log`. Niet rechtstreeks bereikbaar. Staat niet in git. Liefst buiten de webroot zetten. |
| `beheer/assets/vendor/` | Meegeleverde QR-code-bibliotheek (MIT-licentie) |
| `beheer/config.voorbeeld.php` | Voorbeeld van de configuratie |
| `tools/beheer-demodata.php` | Vult een lege test-omgeving met verzonnen demogegevens |

### Overige beveiliging

- Alleen via https (wordt afgedwongen buiten `localhost`), met HSTS en strikte beveiligingsheaders (CSP, geen frames, geen caching van pagina's).
- Wachtwoorden met `password_hash` (bcrypt), minimaal 10 tekens. Na 5 foute pogingen per e-mailadres of IP-adres 15 minuten geblokkeerd; ook voor de code van tweestapsverificatie (bij inloggen, nieuwe herstelcodes en uitzetten) en voor je eigen wachtwoord als bevestiging (bij Koppelingen, kortere bewaartermijnen en wachtwoord wijzigen).
- Sessies verlopen na 2 uur zonder activiteit en hoe dan ook na 12 uur. Wie zijn wachtwoord wijzigt of opnieuw instelt, of wiens tweestapsverificatie door de beheerder wordt gereset, wordt op alle andere apparaten uitgelogd.
- **Openbare formulieren** (aanmelden, proefdag, op de hoogte, solliciteren): hooguit een paar per kwartier per IP-adres, hooguit 3 mails per kwartier naar hetzelfde e-mailadres, geen links of e-mailadressen in namen (die gaan mee in onze bevestigingsmails), en hooguit 3 openstaande proefdagen per e-mailadres. Aanmelden met een e-mailadres dat al bekend is, geeft hetzelfde antwoord als een nieuwe aanmelding; de eigenaar van dat adres krijgt een mail. Zo kan niemand uitproberen wie er klant is.
- **Toegang per groep** geldt ook voor berichten: een medewerker die alleen eigen groepen ziet, ziet geen berichten over kinderen in andere groepen, en geen nieuwe aanmeldingen (die hebben nog geen groep). Wie een kind mag ophalen, passen ouders met gezag aan, of een collega die klanten beheert; past het team allergieën of ophaalpersonen aan, dan krijgen de ouders een bericht. Een observatie delen met ouders kan alleen wie hem schreef, of een collega die klanten beheert.
- Foutmeldingen komen nooit op het scherm, alleen in het foutenlog van de server (behalve in ontwikkelmodus).
- `X-Forwarded-Proto` wordt alleen vertrouwd met `'achter_proxy' => true` in de configuratie; anders kan iedereen doen alsof een verbinding beveiligd is.
- De configuratie met de sleutels staat bij voorkeur buiten de webmap: `mijnbso-config.php` naast `public_html` (zie `docs/live-zetten-vimexx.md`). De database en het mailtestlog zijn alleen leesbaar voor de eigenaar.
- Elk formulier heeft een CSRF-token. Alle uitvoer wordt ge-escaped. Alle databasequery's gebruiken prepared statements.
- Foto's staan buiten de publieke map en worden alleen via `foto.php` geleverd, na controle, en niet in de browsercache bewaard. Bij het opslaan wordt de foto opnieuw opgeslagen als JPEG, waardoor EXIF (zoals gps-locatie) verdwijnt.
- E-mails bevatten geen inhoud over kinderen, alleen een seintje met een link.
- **Beveiligingsstatus** (Instellingen → Beveiligingsstatus) laat zien of alles in orde is: https, sleutel, sleutels buiten de webmap, datamap, PHP-versie, tweestapsverificatie, toegang per groep, gecontroleerd gezag, back-ups, externe back-up, bewaarbeleid en privacyverzoeken.

## Live zetten

**Keuze (november 2026): alles bij Vimexx, op een Managed VPS.** Het stappenplan daarvoor, met het uploadpakket (`npm run pakket`), staat in [`docs/live-zetten-vimexx.md`](live-zetten-vimexx.md). Hieronder de algemene stappen voor elke hosting.

1. **Hosting met PHP 8.2+** en https (de statische website alleen kan op elke host, de beheeromgeving niet). Upload de hele site inclusief `beheer/`.
2. Maak twee sleutels, één voor de gegevens en één voor de back-ups:
   ```sh
   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
   ```
   Bewaar ze **ook buiten de server**, bijvoorbeeld in een wachtwoordkluis. Zonder de sleutels zijn de gegevens en back-ups onbruikbaar.
3. Kopieer `beheer/config.voorbeeld.php` naar `beheer/config.php` (bij Vimexx: naar `mijnbso-config.php` naast `public_html`, zie [`live-zetten-vimexx.md`](live-zetten-vimexx.md)) en vul in:
   - `app_url`: `https://mijn.bsosporty.nl/beheer` (Mijn BSO op een eigen subdomein; alles op één server: `https://bsosporty.nl/beheer`)
   - `website_url`: `https://bsosporty.nl` als de website op Vercel staat (weglaten als alles op één server staat)
   - `data_dir`: een map **buiten de webroot**, bijvoorbeeld `/home/account/beheer-data`
   - `sleutel`: de eerste sleutel. Nooit meer wijzigen.
   - `backup.map` (buiten de webroot) en `backup.sleutel`: de tweede sleutel
   - `installatiecode`: een lange, willekeurige code

   Stripe en e-mail stel je later in Mijn BSO in, onder Koppelingen (stap 8).
4. Stel twee dagelijkse taken (cron) in, bijvoorbeeld:
   ```cron
   15 3 * * * php /pad/naar/site/beheer/cli/onderhoud.php
   45 3 * * * php /pad/naar/site/beheer/cli/backup.php
   ```
   Kopieer de back-upmap daarnaast dagelijks naar een andere plek (in de EU), bijvoorbeeld met de back-updienst van de hosting.
5. Ga naar `https://mijn.bsosporty.nl/beheer/`. Je komt op de installatiepagina: vul de installatiecode in, maak het eerste beheerdersaccount aan en stel tweestapsverificatie in. Maak daarna de `installatiecode` weer leeg.
6. Vul bij de **Instellingen** het IBAN in (de naam op de rekening, BSO Sporty, staat al klaar) en controleer de rest (KvK en LRK staan al ingevuld; controleer de tarieven, afmeldtijd, bewaartermijnen, tweestapsverificatie voor ouders) en maak de **groepen** aan.
7. Nodig de begeleiders uit onder **Team** en koppel ze aan hun groep(en).
8. Stel onder **Koppelingen** het versturen van e-mail in (eigen mailserver, met een testmail) en koppel **Stripe** (zie hieronder). Zonder Stripe draait alles in demo-modus: er wordt niets echt afgeschreven; zonder e-mailinstelling worden e-mails niet verstuurd.
9. Controleer **Instellingen → Beveiligingsstatus**: alles moet op "In orde" staan.

### Apache en nginx

Op Apache regelen de `.htaccess`-bestanden dat `beheer/inc/`, `beheer/cli/` en `beheer/data/` niet bereikbaar zijn. Op nginx moet dat in de serverconfiguratie:

```nginx
location ~ ^/beheer/(inc|cli|data)/ { deny all; return 404; }
location = /beheer/config.php { deny all; return 404; }
location ~ ^/beheer/.+\.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php-fpm.sock; }
client_max_body_size 64m;
```

Zet voor foto's van telefoons in PHP: `upload_max_filesize = 16M`, `post_max_size = 64M`. Met `mod_php` gebeurt dat via `beheer/.htaccess`, met PHP-FPM (zoals bij DirectAdmin) via `beheer/.user.ini`.

### Stripe koppelen

Mijn BSO gebruikt Stripe voor de machtiging (SEPA-incasso), de maandelijkse incasso's en het los betalen van een factuur. Er is geen extra software nodig: Mijn BSO praat rechtstreeks met de API van Stripe.

1. Maak een account aan op [stripe.com](https://stripe.com/nl) en rond de bedrijfscontrole af. Zet onder **Instellingen → Betaalmethoden** **iDEAL | Wero** en **SEPA-incasso** (SEPA Direct Debit) aan. Stel bij **Instellingen → Openbare gegevens** de naam in die ouders op hun afschrift en op de betaalpagina zien.
2. Vul in Mijn BSO onder **Koppelingen** de **test**-sleutel in (`sk_test_...`, onder Ontwikkelaars → API-sleutels); Mijn BSO test hem meteen. Een beperkte sleutel (`rk_test_...`) kan ook, met schrijfrechten op Customers, Checkout Sessions, PaymentIntents en PaymentMethods en leesrechten op SetupIntents, Charges en Mandates.
3. Maak onder **Ontwikkelaars → Webhooks** een webhook aan naar `https://mijn.bsosporty.nl/beheer/stripe-webhook.php` (het adres van Mijn BSO) met deze gebeurtenissen: `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `checkout.session.expired`, `payment_intent.processing`, `payment_intent.succeeded`, `payment_intent.payment_failed`, `payment_intent.canceled`, `charge.refunded`, `charge.dispute.created` en `mandate.updated`. Zet het ondertekeningsgeheim (`whsec_...`) onder Koppelingen bij Webhookgeheim. Het adres en de lijst staan daar ook, om over te nemen.
4. Probeer het hele proces in testmodus: aanmelden, machtiging (kies op de testpagina van de bank "Authorize", of gebruik het test-IBAN `NL39 RABO 0300 0652 64`), facturen maken en de incasso starten. Een SEPA-incasso staat in testmodus na een paar minuten op betaald. Met het test-IBAN `NL91 ABNA 0417 1643 00` mislukt de incasso; dan moet de factuur op "mislukt" komen en de ouder een bericht krijgen.
5. Werkt alles? Vul onder Koppelingen de **live**-sleutel en het geheim van een live-webhook in. Bovenaan staat dan "Live", en na de eerste echte melding zie je wanneer die binnenkwam.

Zo werkt het:

- **Machtiging:** de ouder komt op een pagina van Stripe (Checkout) en kiest de eigen bank (iDEAL | Wero: Stripe schrijft € 0,01 af en betaalt die meteen terug) of vult het IBAN in. Stripe maakt daarvan een SEPA-machtiging. Mijn BSO bewaart alleen het land, de laatste vier cijfers van het IBAN en de naam van de rekeninghouder.
- **Incasso:** "Incasso starten" bij Facturen maakt een betaling op die machtiging. Een SEPA-incasso duurt een paar werkdagen; de factuur staat zolang op "incasso" en gaat daarna vanzelf op "betaald". Een dubbele klik geeft geen dubbele afschrijving.
- **Mislukt of teruggeboekt:** de factuur gaat op "mislukt" en de ouder krijgt een bericht met de mogelijkheid om de factuur los te betalen (iDEAL | Wero). Een terugbetaling die jullie zelf doen in Stripe, komt alleen in het logboek; pas de factuur dan zelf aan.
- **Intrekken:** trekt de beheerder de machtiging in (bij de ouder), dan gebeurt dat ook bij Stripe. Trekt de ouder hem in bij de bank, dan meldt Stripe dat en krijgt het team een melding.
- **Lukt een machtiging of betaling niet?** De ouder ziet een vriendelijke melding. Het team ziet de echte melding van Stripe, met uitleg in gewone taal (bijvoorbeeld: zet iDEAL | Wero en SEPA-incasso aan in het dashboard), bij Koppelingen onder "Laatste fout" en als waarschuwing op Vandaag (voor wie Koppelingen mag beheren), tot er weer iets lukt. **Verbinding en machtiging testen** op Koppelingen test de sleutel én zet een machtiging klaar (zonder klant, meteen weer gesloten): zo blijkt meteen of de betaalmethoden aan staan. Functies `stripe_fout_vastleggen()`, `stripe_fout_uitleg()` en `stripe_waarschuwing()`.
- **Vaste API-versie (november 2026):** Mijn BSO stuurt bij elk verzoek `Stripe-Version: 2024-06-20` mee (`STRIPE_API_VERSIE`), de versie waarvoor de code is geschreven. Zonder vaste versie gebruikt Stripe de standaardversie van het account; een nieuw account krijgt de nieuwste, waarin de velden van Checkout anders kunnen heten. Een andere versie kan met `'stripe' => ['api_versie' => '…']` in de configuratie, maar test dan eerst alles in testmodus.
- **Klant onbekend bij Stripe** (bijvoorbeeld na het wisselen van de testsleutel naar de livesleutel): Mijn BSO maakt vanzelf een nieuwe klant aan en probeert het één keer opnieuw.
- **Veiligheid:** de webhook controleert de handtekening van Stripe en haalt de status daarna altijd zelf op. Na terugkomst van de betaalpagina haalt Mijn BSO de status ook meteen op, dus lokaal testen werkt zonder webhook.
- **Overstap van Mollie (november 2026):** machtigingen die nog via Mollie waren afgegeven, werken niet bij Stripe. Die ouders zien weer "Geef nu je machtiging af" (migratie 16). Zolang Mijn BSO nog niet live staat, merkt niemand daar iets van. Functies in `beheer/inc/stripe.php`.

### Lokaal uitproberen

```sh
cp beheer/config.voorbeeld.php beheer/config.php   # vul een installatiecode in en zet 'ontwikkelmodus' => true
php -S localhost:8000                              # in de hoofdmap van het project
php tools/beheer-demodata.php                      # optioneel: demogegevens
```

Open daarna `http://localhost:8000/beheer/`. Met de demogegevens log je in met bijvoorbeeld `beheerder@demo.bsosporty.nl` en wachtwoord `demo-wachtwoord`; bij de eerste keer stel je tweestapsverificatie in. E-mails komen in `beheer/data/mail.log`. In ontwikkelmodus staat de sleutel in de datamap; gebruik dat nooit op de echte omgeving.

## Nog te doen (TODO)

**Van de klant**
- [ ] Twee aannames bij de facturen bevestigen (zie [Facturen](#facturen)): een abonnement dat halverwege de maand begint of stopt, rekenen we naar rato van het aantal kalenderdagen; en extra dagen bij een Sporty Abonnement (aangevraagd via WhatsApp of e-mail) worden niet automatisch gefactureerd.
- [ ] IBAN invullen en KvK- en LRK-nummer controleren (Instellingen; het IBAN staat bewust niet in de code, t.n.v. BSO Sporty staat al klaar). Het LRK-nummer moet op de factuur staan voor de kinderopvangtoeslag.
- [ ] Afmeldtijd in de agenda bevestigen (nu 12:00 uur op de dag zelf; kosteloos tot 48 uur van tevoren, bevestigd in november 2026).
- [ ] Categorieën van Financiën afstemmen met de boekhouder (`beheer/inc/financien.php`), en bepalen of btw apart moet worden bijgehouden.
- [ ] Per collega de rechten nalopen onder Team: wie mag Facturen, Financiën, Sollicitaties, Privacyverzoeken en Klanten beheren?
- [ ] Werkafspraak voor het controleren van gezag: welk bewijs vraag je (uittreksel gezagsregister, geboorteakte, beschikking) en wie controleert het?

**Privacy (AVG)**
- [ ] DPIA uitvoeren: het systeem verwerkt gegevens van kinderen, waaronder gezondheidsgegevens (allergieën, ziekte) en foto's.
- [ ] Bewaartermijnen laten bevestigen door de klant en een privacyjurist. De standaardwaarden hierboven zijn een voorstel, geen juridisch advies.
- [ ] Verwerkersovereenkomsten met de hostingpartij, de back-upopslag en Stripe (Stripe heeft een standaard verwerkersovereenkomst in het dashboard). Vimexx: de standaardovereenkomst komt vanzelf tot stand met het hostingcontract (ontvangen op 25 november 2026); die noemt alleen niet-bijzondere persoonsgegevens, dus vraag Vimexx te bevestigen dat ook gezondheidsgegevens (allergieën, medicijnen) eronder vallen. Zie `docs/live-zetten-vimexx.md`, stap 2.
- [ ] Toestemmingsformulier voor foto's op website en social media (los van de toestemming voor groepsfoto's in Mijn BSO).
- [ ] Procedure voor een datalek vastleggen (melden bij de Autoriteit Persoonsgegevens binnen 72 uur).

**Techniek**
- [ ] Hosting met PHP 8.2+ en https, datamap en `mijnbso-config.php` buiten de webroot, twee sleutels (ook in een wachtwoordkluis), cron voor onderhoud en back-up (zie `docs/live-zetten-vimexx.md`).
- [ ] Account voor de externe back-up bij een Europese aanbieder van objectopslag, met object lock en een sleutel die alleen mag uploaden; instellen onder Koppelingen.
- [ ] Terugzetten van een back-up één keer testen op een testomgeving, ook eentje uit de externe opslag.
- [ ] Vóór de livegang een onafhankelijke beveiligingstest (pentest) van Mijn BSO laten doen; het systeem bevat gegevens van kinderen.
- [ ] Stripe-account met iDEAL | Wero en SEPA-incasso, eerst testen met de testsleutel en een testwebhook (zie [Stripe koppelen](#stripe-koppelen)).
- [ ] E-mail via een betrouwbare SMTP-dienst als de `mail()`-functie van de hosting in de spam belandt (nu: `mail.methode = mail`).
- [ ] PHP 8.2 krijgt beveiligingsupdates tot eind 2026: kies bij de hosting liefst PHP 8.3 of nieuwer.
- [ ] Jaaropgave voor de kinderopvangtoeslag per ouder (overzicht van uren en bedragen per kind per jaar).
- [ ] Mijn BSO zelf in het Engels (nu Nederlands; de openbare formulieren en hun e-mails zijn al Engels voor wie de Engelse website gebruikt).
