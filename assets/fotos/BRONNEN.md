# Beeld: bronnen en fotolijst

## Huidige stand

Alle beelden op de site zijn **tijdelijke illustraties** die speciaal voor BSO VCK zijn gemaakt in de huisstijlkleuren. Ze komen niet van een externe bron, dus er gelden geen licentievoorwaarden van derden.

Waarom illustraties? Bij het bouwen waren Unsplash en Pexels niet bereikbaar. Bovendien wil de klant vooral eigen beeld laten zien: "mezelf en mijn team, klanten in actie, sfeer en omgeving". Dat kan alleen met eigen foto's.

| Bestand in `bron/` | Soort | Bron | Licentie |
|---|---|---|---|
| `hero.svg` | Illustratie (placeholder) | Eigen werk, gemaakt voor deze site | Vrij te gebruiken door BSO VCK |
| `sport.svg` | Illustratie (placeholder) | Eigen werk, gemaakt voor deze site | Vrij te gebruiken door BSO VCK |
| `aandacht.svg` | Illustratie (placeholder) | Eigen werk, gemaakt voor deze site | Vrij te gebruiken door BSO VCK |
| `samen.svg` | Illustratie (placeholder) | Eigen werk, gemaakt voor deze site | Vrij te gebruiken door BSO VCK |
| `amsterdam.svg` | Illustratie (placeholder) | Eigen werk, gemaakt voor deze site | Vrij te gebruiken door BSO VCK |

Vul deze tabel aan zodra er echte foto's zijn: bestandsnaam, fotograaf, bron (URL) en licentie. Gebruik je een stockfoto, noteer dan ook de link naar de pagina van de foto.

## Fotolijst: welke foto komt waar?

Lever foto's liefst **minimaal 1600 px breed** aan, liggend (4:3). De website snijdt ze zelf bij.

| Naam | Wat moet erop staan | Waar op de site |
|---|---|---|
| `hero` | Kinderen die buiten sporten of spelen, bijvoorbeeld voetbal op een veld. Blij, in beweging, veel licht. | Home (grote foto bovenaan), Diensten ("Sporten en bewegen"), Veelgestelde vragen, 404, deelafbeelding |
| `sport` | Een sport- of bewegingsactiviteit van dichtbij: een bal, pionnen, een begeleider die meedoet. | Home ("Wat we doen"), Over ons ("Onze aanpak"), Diensten (bovenaan) |
| `aandacht` | Een begeleider die op ooghoogte met een kind praat, samen tekent of iets uitlegt. | Home ("Over ons"), Diensten ("Aandacht en ontwikkeling"), Team (bovenaan) |
| `samen` | Een groepje kinderen dat samen speelt, lacht of aan tafel zit. Een gezellige ruimte. | Afsluitend blok op elke pagina, Over ons ("Ons verhaal"), Prijzen, Diensten ("Samen en gezellig") |
| `amsterdam` | De locatie zelf: het gebouw, de ingang of de buurt. | Over ons (bovenaan), Contact (bovenaan en achter de kaart) |
| Teamportretten | Per teamlid een vriendelijke, vierkante portretfoto. | Team (nu nog placeholder-avatars in de HTML) |
| Teamfoto | De eigenaar met het hele team. | Over ons ("De mensen erachter", nu nog een placeholder in de HTML) |

Zoektermen als je tijdelijk stockfoto's wilt gebruiken: *kids playing football outside*, *children playing together*, *child drawing with teacher*, *after school club*. Kies foto's die echt bij een Amsterdamse BSO passen, zonder clichés.

## Privacy bij foto's van kinderen

- Zet herkenbare kinderen alleen online met **schriftelijke toestemming van hun ouders** (AVG). Leg die toestemming vast.
- Laat bij twijfel kinderen van achteren of onherkenbaar zien, of kies voor handen en details.
- Vermeld geen namen van kinderen bij foto's.

## Een illustratie vervangen door een foto

1. Zet de foto als `.jpg` of `.png` in `assets/fotos/bron/` met dezelfde naam (bijvoorbeeld `hero.jpg`).
2. Verwijder de bijbehorende `.svg` uit `bron/`.
3. Draai `npm install` (eenmalig) en daarna `npm run fotos`. Het script maakt AVIF- en WebP-versies in 480, 800, 1200 en 1600 px breed.
4. Pas in de HTML de `alt`-tekst aan. Zoek daarvoor op `TODO: placeholder-illustratie` en op de naam van het beeld.
