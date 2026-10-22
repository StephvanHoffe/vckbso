# Beeld: foto's, placeholders en bronnen

## Huidige stand (22 oktober 2026)

De klant levert de fotografie later aan ("Voor nu kun je op de website placeholders voor de foto's plaatsen"). Daarom staan er op de site **geen stockfoto's meer**, maar duidelijke fotovakken: een zwart (of oranje) vlak met een camera, "Foto volgt" en wat er op de foto moet komen. In de HTML herken je ze aan `class="… fotovak"` en `data-foto="naam"`.

De stockfoto's van Pexels die er eerder stonden, zijn op 22 oktober 2026 weggehaald (ze staan nog in de git-geschiedenis). Alleen vier ervan worden nog gebruikt, als verzonnen voorbeeldfoto's in de momentopname van Mijn BSO (`tools/voorbeeld/demofotos/`, zie onderaan).

Wel op de site: de officiële logo's (`assets/img/sporty-*.svg`) en Henny de hond (`assets/img/henny/`), allebei aangeleverd door de klant.

## Welke foto komt waar?

Lever foto's liefst **minimaal 1600 px breed** aan. De website snijdt ze zelf bij; in de kaders blijft vooral het midden zichtbaar.

| `data-foto` | Pagina | Wat moet erop staan |
|---|---|---|
| `hero` | Home (NL en EN), bovenaan | Een brede foto van kinderen die samen sporten of met de armen om elkaar heen staan, liefst in oranje Sporty-kleding. Staand of vierkant werkt het best. |
| `rennen` | Home (NL en EN), "Na school nog energie voor tien?" | Kinderen die rennen, springen of juichen. Staand (4:5). |
| `ophalen` | Home, "Zo ziet een middag bij Sporty eruit" | Een coach van Sporty die kinderen ophaalt bij school. |
| `sporten` | Home, idem | Een balspel of andere sport. |
| `samen` | Home, idem | Kinderen die samen even bijkomen en kletsen. |
| `team` | Home ("Team Sporty") en Team (bovenaan) | Het hele team, of een GIF van het team dat een doelpunt viert. |
| `team-1`, `team-2`, … | Team | Per teamlid een GIF of kort filmpje van een voetbaljuichmoment (zie hieronder). |
| `binnen`, `luisteren`, `basketbal` | Over ons | Het team aan het werk: spelen met de kinderen, op ooghoogte luisteren, samen sporten. |
| `plezier`, `voetbal`, `schilderen`, `gezellig` | Programma (diensten) | Bewegen, aandacht van een begeleider en samen gezellig. |
| `knutselen` | Prijzen (bovenaan) | Sfeer bij Sporty. |
| `vragen` | Veelgestelde vragen (bovenaan) | Kinderen die enthousiast hun vinger opsteken, of een begeleider die iets uitlegt. |
| `welkom` | Contact (bovenaan) | Een warm welkom: kinderen en een coach bij de ingang of op het veld. |

Getekende elementen (sterren, pijlen, kroontjes, hartjes) en handgeschreven krabbels staan al óp de fotovakken. Die blijven staan als de foto's komen, net als in het voorbeeld "Team Sporty" van de klant.

## Een fotovak vervangen door een foto

1. Zet de foto als `.jpg` of `.png` in `assets/fotos/bron/` met de naam uit de tabel (bijvoorbeeld `hero.jpg`).
2. Draai `npm install` (eenmalig) en daarna `npm run fotos`. Het script maakt AVIF- en WebP-versies in 480, 800, 1200 en 1600 px breed in `assets/fotos/`.
3. Vervang in de HTML het fotovak (`<div class="frame__img fotovak" data-foto="hero">…</div>`) door de foto, met een Nederlandse `alt`-tekst die zegt wat er te zien is:
   ```html
   <div class="frame__img">
     <picture>
       <source type="image/avif" srcset="assets/fotos/hero-480.avif 480w, assets/fotos/hero-800.avif 800w, assets/fotos/hero-1200.avif 1200w, assets/fotos/hero-1600.avif 1600w" sizes="(min-width: 64em) 40vw, 100vw">
       <img src="assets/fotos/hero-800.webp" srcset="assets/fotos/hero-480.webp 480w, assets/fotos/hero-800.webp 800w, assets/fotos/hero-1200.webp 1200w, assets/fotos/hero-1600.webp 1600w" sizes="(min-width: 64em) 40vw, 100vw" width="1600" height="1067" alt="…" loading="lazy" decoding="async">
     </picture>
   </div>
   ```
   Laat `loading="lazy"` weg bij de foto bovenaan de homepage (die moet meteen laden). In een programmakaart heet het kader `foto-card__img` in plaats van `frame__img`.

## GIF's van het team

Lever de juichmomenten liefst aan als kort filmpje (MP4, een paar seconden): dat is veel kleiner dan een GIF en laadt sneller. Op de teampagina staat in een opmerking welke code je gebruikt; `js/main.js` speelt het filmpje dan in een lus af met een pauzeknop, en niet vanzelf als iemand "minder beweging" heeft ingesteld.

## Privacy bij foto's van kinderen

- Zet herkenbare kinderen alleen online met **schriftelijke toestemming van hun ouders** (AVG). Leg die toestemming vast.
- Laat bij twijfel kinderen van achteren of onherkenbaar zien, of kies voor handen en details.
- Vermeld geen namen van kinderen bij foto's.

## Voorbeeldfoto's in de momentopname van Mijn BSO

De momentopname van Mijn BSO (`npm run voorbeeld`, map `voorbeeld/`) laat zien hoe ouders foto's van de middag zien. Daarvoor gebruiken we vier stockfoto's van [Pexels](https://www.pexels.com/) (gratis, ook commercieel; naamsvermelding niet verplicht, zie de [Pexels-licentie](https://www.pexels.com/license/)). Ze staan niet op de website zelf.

| Bestand in `tools/voorbeeld/demofotos/` | Foto (Pexels) | Fotograaf |
|---|---|---|
| `voetbal.jpg` | [Young Soccer Player Kicking Ball on Green Field](https://www.pexels.com/photo/person-field-playing-school-972513/) | Markus Spiske |
| `schilderen.jpg` | [Children Painting in the Art Class](https://www.pexels.com/photo/children-painting-in-the-art-class-8382373/) | Pavel Danilyuk |
| `knutselen.jpg` | [Girl in Brown Apron Doing Rainbow Painting](https://www.pexels.com/photo/girl-in-brown-apron-doing-rainbow-painting-7025567/) | Vlada Karpovich |
| `bal.jpg` | [Colorful Soccer Ball on Grass Field in Sunlight](https://www.pexels.com/photo/colorful-soccer-ball-on-grass-field-in-sunlight-30188923/) | Pablo Gómez |
