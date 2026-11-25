# Beeld: bronnen en fotolijst

## Huidige stand (25 november 2026)

**Alle foto's op de website zijn eigen foto's van Sporty.** De tijdelijke stockfoto's van Pexels (oktober en november 2026) zijn op 24 november 2026 vervangen en uit de site gehaald, op verzoek van de klant ("bij voorkeur geen neppe foto's meer"). Alleen de momentopname van Mijn BSO gebruikt nog vier stockfoto's (zie onderaan); die staan niet op de website.

Wel van de klant zelf: de officiële logo's (`assets/img/sporty-*.svg`), Henny de hond (`assets/img/henny/`), de juichfilmpjes van het team (zie onder) en de foto's hieronder. In de HTML staat bij elke foto `data-foto="…"` (de plek) en de opmerking "Eigen foto van Sporty".

**De originelen.** Sporty stuurde de foto's eerst in de chat (verkleind) en daarna op 24 november 2026 de originelen via WeTransfer: 38 foto's ("Foto 1.jpg" t/m "Foto 38.jpg", 2600 tot 7000 px breed) en een filmpje ("Sporty 2.MP4", staand, 32 seconden, met geluid). De bestanden in `bron/` zijn daaruit gemaakt: zonder metadata (dus zonder de locatie of het toestel), omgezet naar sRGB en hooguit 2000 px breed. `npm run fotos` maakt daar de AVIF- en WebP-versies van (480 tot 1600 px). De originelen zelf staan niet in de repository; Sporty heeft ze.

**Privacy:** op de foto's staan herkenbare kinderen. TODO: Sporty bevestigt dat de ouders schriftelijk toestemming hebben gegeven voor de website (zie "Privacy bij foto's van kinderen").

### Eigen foto's van Sporty

| Bestand in `bron/` | Origineel | Wat staat erop | Waar op de site (`data-foto`) |
|---|---|---|---|
| `schot.jpg` | Foto 11 | Drie kinderen in oranje Sporty-shirts op het veld; een jongen schiet een oranje bal weg | Home en binnenkort-pagina, de grote foto bovenaan (`held`) |
| `ballonnen-rennen.jpg` | Foto 25 | Kinderen rennen over het kunstgras tijdens een spel met waterballonnen | Home, brede foto onder de weetjes (`bewegen`) |
| `coach-voetbal.jpg` | Foto 20 | Een coach met een bal kijkt toe hoe een jongen tegen de oranje bal trapt | Een middag bij Sporty, bovenaan (`middag`) |
| `bakfiets.jpg` | Sporty 2.MP4 (beeld op 11 seconden, 16:10 uitgesneden, 1080 px) | Een coach fietst met een bakfiets vol zwaaiende kinderen | Een middag bij Sporty, stap 1 ophalen (`middag-1`) |
| `samen-eten.jpg` | Foto 3 | Een coach tussen de kinderen aan lange tafels in de kantine | Een middag bij Sporty, stap 2 (`middag-2`) |
| `ballon-plons.jpg` | Foto 35 | Een waterballon spat uiteen tussen spelende kinderen | Een middag bij Sporty, stap 3 (`middag-3`) |
| `bekers.jpg` | Foto 2 (staand, 4:3 uitgesneden) | Een jongen bouwt aan tafel een toren van bekertjes | Een middag bij Sporty, stap 4 (`middag-4`) |
| `ijsje-doel.jpg` | Foto 21 | Kinderen eten een waterijsje bij het doel | Een middag bij Sporty, stap 5 (`middag-5`) |
| `lach-oranje-bal.jpg` | Foto 16 | Een lachende jongen in een oranje Sporty-shirt trapt tegen een oranje bal | Alles over Sporty, visie (`visie`) |
| `spel-emmer.jpg` | Foto 26 | Een coach en een groep kinderen spelen een renspel met een emmer water | Alles over Sporty, Onze droom (`tim-en-sureyo`). TODO: een foto van Tim en Sureyo samen |
| `ballonnen-gooien.jpg` | Foto 34 | Drie kinderen rennen en gooien met waterballonnen | Welkom bij de club, bovenaan (`club`) |
| `rennen-lachen.jpg` | Foto 31 (staand, 5:4 uitgesneden) | Een jongen rent lachend over het veld | Welkom bij de club, de erepoort (`community-1`). TODO: later een foto van de erepoort |
| `coach-kletsen.jpg` | Foto 6 | Een coach zit met een groep kinderen aan tafel | Welkom bij de club, samen bepalen (`community-2`) |
| `emmer-water.jpg` | Foto 36 | Een jongen gooit een emmer water over een vriend heen | Welkom bij de club, momenten (`community-3`) |
| `water-inschenken.jpg` | Foto 7 | Een coach schenkt water in voor de kinderen in de kantine | Welkom bij de club, ouders (`community-4`). TODO: later een foto van ouders bij een Sporty-evenement |
| `trappen-pionnen.jpg` | Foto 10 | Drie kinderen in oranje Sporty-shirts oefenen schieten tussen de pionnen | Proefdag (`proefdag`) |
| `samen-voetballen.jpg` | Foto 15 | Kinderen voetballen samen op het sportveld | Abonnement en kosten (`abonnement`) |
| `ballon-coach.jpg` | Foto 33 | Een lachende coach speelt met de kinderen een spel met waterballonnen | Pauzebegeleiding (`pauze`) |
| `ijsje.jpg` | Foto 22 (staand, 4:3 uitgesneden) | Een jongen in een wit Sporty-shirt met een waterijsje | Vakantie- en studiedagen (`vakantie`) |
| `coach-aan-tafel.jpg` | Foto 4 | Een coach in een oranje shirt bij de kinderen aan tafel, met de prijzenkast | Word Sporty coach (`coach`) |
| `boterham.jpg` | Foto 1 | Een coach en een jongen eten samen een boterham | Contact (`welkom`) |
| `pionnen-zetten.jpg` | Foto 23 | Een coach zet pionnen neer op het grote kunstgrasveld | Locaties, bovenaan (`locatie`) |
| `clubhuis.jpg` | (feedback 2.0, 1086 px) | Het blauwe clubhuis van buiten, met twee Sporty-vlaggen en een picknicktafel | Locaties, algemene foto van de locatie (`locatie-overzicht`) |
| `speelkamer.jpg` | (feedback 2.0, 1086 px) | De speelkamer met het oranje Sporty-tafelkleed, bouwblokken en knutselspullen | Locaties (`ruimte-2`, Speelkamer) |
| `kantine.jpg` | (feedback 2.0, 2016 px) | De kantine met tafels, tafelvoetbal, de bar en uitzicht op de velden | Locaties (`ruimte-3`, Kantine) |
| `chillruimte.jpg` | (in de chat, 25 november 2026, 1088 px, staand, 4:3 uitgesneden) | De chillruimte: een groene muur met het oranje Sporty-doek, een oranje zitzak, een oranje bank en een dartbord | Locaties (`ruimte-1`, Chillruimte) |

Nu niet gebruikt (lijken op een gekozen foto): Foto 5, 8 en 9 (een coach in de kantine), 12, 13, 14, 17, 18 en 19 (schoten op doel), 24 (de coach zet pionnen neer), 27, 28, 29 en 30 (het spel bij het doel en een rennende jongen), 32 (een spattende ballon) en 37 en 38 (de emmer water). Het filmpje "Sporty 2.MP4" staat op de homepage (zie "Het filmpje op de homepage" hieronder); het beeld van de bakfiets komt eruit.

## Eigen foto's: wat willen we nog?

| Plek (`data-foto`) | Wat moet erop staan |
|---|---|
| `tim-en-sureyo` (Alles over Sporty) | Tim en Sureyo samen. |
| `community-1`, `community-4` (Welkom bij de club) | De erepoort, en ouders bij een Sporty-evenement. |
| `locatie-overzicht`, `ruimte-1`, `ruimte-2` (Locaties) | Grotere versies van het clubhuis, de chillruimte en de speelkamer (nu 1086 tot 1088 px). |
| Team | De foto's van Tim en Anass als bestand (zie hieronder). |

Getekende elementen (sterren, pijlen, kroontjes, hartjes) en handgeschreven krabbels staan óp de beelden, net als in het voorbeeld "Team Sporty" van de klant.

## Een foto vervangen

1. Zet de nieuwe foto als `.jpg` of `.png` in `assets/fotos/bron/` (zonder metadata, liefst 1600 tot 2000 px breed). Met dezelfde naam als de huidige foto overschrijf je die; een nieuwe naam kan ook.
2. Draai `npm install` (eenmalig) en daarna `npm run fotos -- <naam>`. Het script maakt de AVIF- en WebP-versies (480, 800, 1200 en 1600 px, of kleiner als de foto kleiner is).
3. Pas in de HTML de bestandsnamen, de `alt`-tekst en de `width` en `height` aan (zoek op `data-foto="…"`), in beide talen.
4. Werk de tabel hierboven bij.

## Juichfilmpjes van het team

Op de teampagina staat per coach een kort filmpje van een voetbaljuichmoment (aangeleverd op 24 november 2026), met een foto als stilstaand beeld. Op de homepage (het teamblok) staat alleen dat stilstaande beeld, in een kleinere versie (`<naam>-poster-320.webp`). `js/main.js` speelt het filmpje in een lus af zolang het in beeld is, met een pauzeknop, en niet vanzelf als iemand "minder beweging" heeft ingesteld. Het filmpje laadt pas als het in beeld komt.

| Teamlid | Filmpje | Stilstaand beeld |
|---|---|---|
| Sureyo | `juich-sureyo` | Zijn foto (`bron/filmpjes/team-sureyo.jpg`) |
| Tim | `juich-tim` | Voorlopig een beeld uit zijn filmpje (TODO: zijn foto als bestand) |
| Anass Boudinar | `juich-anas` | Voorlopig een beeld uit zijn filmpje (TODO: zijn foto als bestand) |

**Een filmpje of foto toevoegen of vervangen**
1. Zet het filmpje (MOV of MP4, staand, een paar seconden) of de foto in `assets/fotos/bron/filmpjes/`.
2. Zet een regel in `FILMPJES` in `tools/filmpjes.mjs`: de naam, het bronbestand, waar de uitsnede komt (`boven`) en het stilstaande beeld (een foto, eventueel met een uitsnede, of een seconde uit het filmpje).
3. Draai `npm run filmpjes` (vereist ffmpeg en `npm install`). Het script maakt in `assets/fotos/` een WebM, een MP4, een poster en een kleine poster voor de homepage, 4:5, zonder geluid en zonder metadata (dus ook zonder de locatie die een telefoon in een filmpje zet).
4. Nieuw teamlid: kopieer op `team.html` en `en/team.html` een kaart (`<li class="team-lid">`, met een eigen `id`) en pas naam, functie, tekst en de bestandsnamen aan. Doe hetzelfde in het teamblok op de homepage (`index.html` en `en/index.html`, sectie `#team`); `tests/team.test.mjs` controleert dat beide gelijk lopen.

De bronbestanden in `assets/fotos/bron/filmpjes/` zijn al bijgesneden tot 4:5 (1080 × 1350) en zonder geluid opgeslagen; de originele filmpjes van de telefoon heeft Sporty zelf. De map `assets/fotos/bron/` gaat niet mee in het uploadpakket.

## Het filmpje op de homepage

Op de homepage (NL en EN) staat na "Sporty is voor iedereen" het blok "Sporty in 30 seconden" met het eigen filmpje van Sporty ("Sporty 2.MP4", via WeTransfer op 24 november 2026): staand, 32 seconden, met geluid. Een coach vertelt wat Sporty is, met beelden van het ophalen met de bakfiets, de kantine en het voetballen.

| Bestand in `assets/fotos/` | Wat |
|---|---|
| `sporty-filmpje.webm`, `sporty-filmpje.mp4` | Het filmpje, 720 × 1280, zonder metadata (VP9 met Opus, en H.264 met AAC), elk ongeveer 4 MB |
| `sporty-filmpje-poster.webp` | Het stilstaande beeld (de coach met de bal, op 4 seconden) |
| `sporty-filmpje.nl.vtt`, `sporty-filmpje.en.vtt` | De ondertiteling (WCAG 1.2.2): de gesproken tekst (0 tot 10 seconden), daarna "[vrolijke muziek]". Uitgeschreven met spraakherkenning en nagelezen. TODO: Sporty controleert de tekst. |

Het filmpje speelt pas als je op afspelen drukt (`preload="none"`, geen autoplay), met de ondertiteling in de taal van de pagina aan. De pop-up "Blijf op de hoogte" wacht zolang er een filmpje met geluid speelt. Opnieuw maken: met ffmpeg in twee passes (ongeveer 950 kbit/s), zie de commit van 24 november 2026; `tests/filmpje.test.mjs` controleert de bestanden en de ondertiteling.

## Privacy bij foto's van kinderen

- Zet herkenbare kinderen alleen online met **schriftelijke toestemming van hun ouders** (AVG). Leg die toestemming vast.
- Laat bij twijfel kinderen van achteren of onherkenbaar zien, of kies voor handen en details.
- Vermeld geen namen van kinderen bij foto's.

## Voorbeeldfoto's in de momentopname van Mijn BSO

De momentopname van Mijn BSO (`npm run voorbeeld`, map `voorbeeld/`) laat zien hoe ouders foto's van de middag zien. Daarvoor gebruiken we vier stockfoto's van Pexels, in `tools/voorbeeld/demofotos/`. Ze staan niet op de website zelf.

| Bestand in `tools/voorbeeld/demofotos/` | Foto (Pexels) | Fotograaf |
|---|---|---|
| `voetbal.jpg` | [Young Soccer Player Kicking Ball on Green Field](https://www.pexels.com/photo/person-field-playing-school-972513/) | Markus Spiske |
| `schilderen.jpg` | [Children Painting in the Art Class](https://www.pexels.com/photo/children-painting-in-the-art-class-8382373/) | Pavel Danilyuk |
| `knutselen.jpg` | [Girl in Brown Apron Doing Rainbow Painting](https://www.pexels.com/photo/girl-in-brown-apron-doing-rainbow-painting-7025567/) | Vlada Karpovich |
| `bal.jpg` | [Colorful Soccer Ball on Grass Field in Sunlight](https://www.pexels.com/photo/colorful-soccer-ball-on-grass-field-in-sunlight-30188923/) | Pablo Gómez |
