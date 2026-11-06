<?php
/*
 * Alle e-mails die Mijn BSO verstuurt, met hun standaardtekst. Het team past ze aan onder Instellingen →
 * E-mails (mailteksten.php); een aangepaste tekst staat in de tabel mail_teksten en gaat voor de standaard.
 *
 * In een tekst staan invulvelden tussen accolades, zoals {voornaam} of {link}. Die vult Mijn BSO bij het
 * versturen in. Per e-mail staat welke velden er zijn, met een voorbeeld, en welke er verplicht in moeten
 * (bijvoorbeeld de link om een wachtwoord te kiezen: zonder die link heeft de e-mail geen zin).
 *
 * Een nieuwe e-mail toevoegen: zet hem hieronder in MAILTEKSTEN en verstuur hem met stuur_mailtekst() of
 * mail_team_tekst(). Zet in e-mails nooit gevoelige gegevens over kinderen (zie mail.php).
 */

declare(strict_types=1);

const MAILTEKST_GROEPEN = [
    'ouders' => ['Aan ouders, sollicitanten en andere bezoekers', 'Deze e-mails gaan naar ouders en andere mensen buiten het team.'],
    'team' => ['Aan het team', 'Meldingen voor het team en uitnodigingen voor collega\'s. De meldingen gaan naar het teamadres onder Koppelingen.'],
    'algemeen' => ['Onder elke e-mail', 'Deze tekst staat onderaan elke e-mail die Mijn BSO verstuurt.'],
];

/*
 * sleutel => [
 *   'groep' => sleutel uit MAILTEKST_GROEPEN, 'naam' => naam in het overzicht, 'wanneer' => wanneer de e-mail gaat,
 *   'velden' => [veld => [uitleg, voorbeeld]], 'verplicht' => velden die in de tekst moeten staan,
 *   'onderwerp' => standaard onderwerp (null: geen onderwerp, zoals bij de ondertekening), 'tekst' => standaardtekst,
 * ]
 */
const MAILTEKSTEN = [
    // ---------- Aan ouders, sollicitanten en andere bezoekers ----------
    'aanmelding_ontvangen' => [
        'groep' => 'ouders',
        'naam' => 'Aanmelding ontvangen',
        'wanneer' => 'Direct nadat een ouder het aanmeldformulier "Kom bij de club" op de website heeft verstuurd.',
        'velden' => ['voornaam' => ['Voornaam van de ouder', 'Sam'], 'kinderen' => ['Voornamen van de aangemelde kinderen', 'Noor en Daan']],
        'verplicht' => [],
        'onderwerp' => 'Je aanmelding bij Sporty is binnen!',
        'tekst' => "Hoi {voornaam},\n\nWat leuk dat je {kinderen} hebt aangemeld bij Sporty! We hebben je aanmelding goed ontvangen. Aanmelden is vrijblijvend: je zit nog nergens aan vast.\n\nWe bekijken de mogelijkheden en nemen snel contact met je op om kennis te maken en je aanmelding af te ronden. Daarna krijg je een link om in te loggen in Mijn BSO.\n\nAlvast zin in? Kom gerust een keer langs op Sportpark Middenmeer, of boek een gratis proefdag.\n\nTot snel!\nTeam Sporty (en Henny: woef!)",
    ],
    'account_klaar' => [
        'groep' => 'ouders',
        'naam' => 'Welkom: account staat klaar (met wachtwoordlink)',
        'wanneer' => 'Als het team een nieuwe aanmelding activeert en de ouder nog geen wachtwoord heeft (aangemeld via de website).',
        'velden' => ['naam' => ['Naam van de ouder', 'Sam de Jong'], 'link' => ['Link om een wachtwoord te kiezen (een week geldig)', 'https://…/wachtwoord-instellen.php?…']],
        'verplicht' => ['link'],
        'onderwerp' => 'Welkom bij Sporty: je account staat klaar',
        'tekst' => "Hoi {naam},\n\nWelkom bij de club! Je aanmelding is rond en je account in Mijn BSO staat klaar. Kies via deze link een wachtwoord (de link is een week geldig):\n{link}\n\nDaarna regel je in een paar klikken de automatische incasso, en kies je zelf de dagen voor je kind.\n\nTot snel!",
    ],
    'account_actief' => [
        'groep' => 'ouders',
        'naam' => 'Welkom: account is actief',
        'wanneer' => 'Als het team een ouder activeert die al een wachtwoord heeft.',
        'velden' => ['naam' => ['Naam van de ouder', 'Sam de Jong'], 'link' => ['Link naar de agenda in Mijn BSO', 'https://…/agenda.php']],
        'verplicht' => ['link'],
        'onderwerp' => 'Welkom bij Sporty',
        'tekst' => "Hoi {naam},\n\nWelkom bij Sporty! Je account is actief. Log in om de dagen voor je kind te kiezen:\n{link}\n\nTot snel!",
    ],
    'verzorger_uitnodiging' => [
        'groep' => 'ouders',
        'naam' => 'Uitnodiging voor een extra verzorger',
        'wanneer' => 'Als het team bij een kind een nieuwe verzorger koppelt (bijvoorbeeld de andere ouder of een opa of oma).',
        'velden' => ['naam' => ['Naam van de verzorger', 'Oma Joke'], 'kind' => ['Voornaam van het kind', 'Noor'], 'link' => ['Link om een wachtwoord te kiezen (3 dagen geldig)', 'https://…/wachtwoord-instellen.php?…']],
        'verplicht' => ['link'],
        'onderwerp' => 'Je account bij Sporty',
        'tekst' => "Hoi {naam},\n\nJe bent bij Sporty gekoppeld als verzorger van {kind}. Via deze link kies je een wachtwoord voor Mijn BSO (de link werkt 3 dagen):\n\n{link}\n\nTot snel!",
    ],
    'wachtwoord_vergeten' => [
        'groep' => 'ouders',
        'naam' => 'Wachtwoord vergeten',
        'wanneer' => 'Als iemand (ouder of teamlid) bij het inloggen op "Wachtwoord vergeten" klikt.',
        'velden' => ['naam' => ['Naam van de gebruiker', 'Sam de Jong'], 'link' => ['Link om een nieuw wachtwoord te kiezen (2 uur geldig)', 'https://…/wachtwoord-instellen.php?…']],
        'verplicht' => ['link'],
        'onderwerp' => 'Nieuw wachtwoord instellen',
        'tekst' => "Hoi {naam},\n\nJe hebt gevraagd om een nieuw wachtwoord voor Sporty. Via deze link stel je het in (de link werkt 2 uur):\n\n{link}\n\nHeb je dit niet zelf gevraagd? Dan kun je deze e-mail negeren.",
    ],
    'nieuw_bericht' => [
        'groep' => 'ouders',
        'naam' => 'Nieuw bericht van het team',
        'wanneer' => 'Als het team in Mijn BSO een bericht aan een ouder stuurt. Het bericht zelf staat niet in de e-mail.',
        'velden' => ['naam' => ['Naam van de ouder', 'Sam de Jong'], 'link' => ['Link naar de berichten in Mijn BSO', 'https://…/berichten.php']],
        'verplicht' => ['link'],
        'onderwerp' => 'Nieuw bericht van Sporty',
        'tekst' => "Hoi {naam},\n\nJe hebt een nieuw bericht van Sporty. Lees het hier:\n{link}",
    ],
    'privacyverzoek_reactie' => [
        'groep' => 'ouders',
        'naam' => 'Reactie op een privacyverzoek',
        'wanneer' => 'Als het team een privacyverzoek van een ouder heeft afgehandeld.',
        'velden' => ['naam' => ['Naam van de ouder', 'Sam de Jong'], 'link' => ['Link naar Privacy in Mijn BSO', 'https://…/privacy.php']],
        'verplicht' => ['link'],
        'onderwerp' => 'Reactie op je privacyverzoek',
        'tekst' => "Hoi {naam},\n\nWe hebben je privacyverzoek behandeld. Je leest onze reactie in Mijn BSO bij Privacy:\n{link}",
    ],
    'proefdag_bevestiging' => [
        'groep' => 'ouders',
        'naam' => 'Proefdag geboekt',
        'wanneer' => 'Direct nadat een ouder op de website een proefdag heeft geboekt.',
        'velden' => ['voornaam' => ['Voornaam van de ouder', 'Sam'], 'kind' => ['Voornaam van het kind', 'Noor'], 'wanneer' => ['Dag en tijd van de proefdag', 'dinsdag 10 november, 15:00–16:30 uur'], 'adres' => ['Adres van het clubhuis', 'Sporty, Voorlandpad 17, 1098 TZ Amsterdam (Sportpark Middenmeer)']],
        'verplicht' => ['wanneer'],
        'onderwerp' => 'Je proefdag bij Sporty staat gepland!',
        'tekst' => "Hoi {voornaam},\n\nWat leuk dat jullie komen kennismaken! Je gratis proefdag staat gepland op {wanneer}.\n\nWaar: {adres}\n\nWe laten je het clubhuis zien, je maakt kennis met de coaches en je kunt al je vragen stellen. Neem {kind} vooral mee: zin om mee te doen? Dan kan dat meteen, met een spelletje.\n\nKun je toch niet? Laat het ons dan even weten door op deze mail te reageren of ons te bellen.\n\nTot dan!\nTeam Sporty (en Henny, die al staat te kwispelen)",
    ],
    'sollicitatie_ontvangen' => [
        'groep' => 'ouders',
        'naam' => 'Sollicitatie ontvangen',
        'wanneer' => 'Direct nadat iemand via Word Sporty coach heeft gesolliciteerd.',
        'velden' => [
            'voornaam' => ['Voornaam van de sollicitant', 'Lisa'],
            'bijlagen' => ['Wat er is meegestuurd: "je cv" of "je cv en motivatie"', 'je cv en motivatie'],
            'bewaren' => ['Hoe lang we de sollicitatie bewaren (hangt af van de keuze van de sollicitant)', 'Is er geen plek voor je, dan verwijderen we je gegevens uiterlijk 4 weken nadat we je dat hebben laten weten.'],
        ],
        'verplicht' => ['bewaren'],
        'onderwerp' => 'We hebben je sollicitatie ontvangen',
        'tekst' => "Hoi {voornaam},\n\nWat leuk dat je Sporty coach wilt worden! We hebben je sollicitatie goed ontvangen, met {bijlagen}. We lezen alles en nemen zo snel mogelijk contact met je op.\n\n{bewaren}\n\nGroetjes,\nTeam Sporty",
    ],
    'op_de_hoogte_bevestigen' => [
        'groep' => 'ouders',
        'naam' => 'Blijf op de hoogte: aanmelding bevestigen',
        'wanneer' => 'Direct nadat iemand zich op de website heeft aangemeld om op de hoogte te blijven.',
        'velden' => ['voornaam' => ['Voornaam', 'Fatima'], 'link' => ['Link om de aanmelding te bevestigen', 'https://…/op-de-hoogte.php?bevestig=…'], 'dagen' => ['Na hoeveel dagen we niet-bevestigde aanmeldingen wissen', '14']],
        'verplicht' => ['link'],
        'onderwerp' => 'Bevestig je aanmelding bij Sporty',
        'tekst' => "Hoi {voornaam},\n\nLeuk dat je op de hoogte wilt blijven van Sporty! Bevestig je aanmelding met deze link:\n{link}\n\nDaarna houden we je op de hoogte van het laatste nieuws, en krijg je een Sporty-kennismakingspakket. We laten je weten hoe je het pakket krijgt.\n\nHeb je je niet zelf aangemeld? Dan hoef je niets te doen. Zonder bevestiging wissen we je gegevens binnen {dagen} dagen.\n\nTot snel!\nTeam Sporty (en Henny: woef!)",
    ],
    'op_de_hoogte_al_op_lijst' => [
        'groep' => 'ouders',
        'naam' => 'Blijf op de hoogte: staat al op de lijst',
        'wanneer' => 'Als iemand zich opnieuw aanmeldt terwijl het e-mailadres al bevestigd op de lijst staat.',
        'velden' => ['voornaam' => ['Voornaam', 'Fatima'], 'afmeldlink' => ['Persoonlijke link om je af te melden', 'https://…/op-de-hoogte.php?afmelden=…']],
        'verplicht' => ['afmeldlink'],
        'onderwerp' => 'Je staat al op de lijst van Sporty',
        'tekst' => "Hoi {voornaam},\n\nJe hebt je net opnieuw aangemeld om op de hoogte te blijven van Sporty, maar je stond al op de lijst. Je hoeft dus niets te doen!\n\n--\nJe krijgt deze e-mail omdat je je bij Sporty hebt aangemeld om op de hoogte te blijven.\nGeen e-mails meer? Meld je hier af: {afmeldlink}",
    ],
    'op_de_hoogte_update' => [
        'groep' => 'ouders',
        'naam' => 'Blijf op de hoogte: update van het team',
        'wanneer' => 'Als het team onder "Op de hoogte" een update verstuurt. Onderwerp en bericht schrijf je daar per keer; hier pas je aan wat eromheen staat.',
        'velden' => ['voornaam' => ['Voornaam', 'Fatima'], 'onderwerp' => ['Het onderwerp dat je bij de update invult', 'Sporty opent op 4 januari!'], 'bericht' => ['Het bericht dat je bij de update schrijft', 'We openen op maandag 4 januari 2027. …'], 'afmeldlink' => ['Persoonlijke link om je af te melden', 'https://…/op-de-hoogte.php?afmelden=…']],
        'verplicht' => ['bericht', 'afmeldlink'],
        'onderwerp' => '{onderwerp}',
        'tekst' => "Hoi {voornaam},\n\n{bericht}\n\n--\nJe krijgt deze e-mail omdat je je bij Sporty hebt aangemeld om op de hoogte te blijven.\nGeen e-mails meer? Meld je hier af: {afmeldlink}",
    ],

    // ---------- Aan het team ----------
    'medewerker_uitnodiging' => [
        'groep' => 'team',
        'naam' => 'Uitnodiging voor een collega',
        'wanneer' => 'Als je onder Team een nieuwe collega uitnodigt.',
        'velden' => ['naam' => ['Naam van de collega', 'Tim'], 'link' => ['Link om een wachtwoord te kiezen (3 dagen geldig)', 'https://…/wachtwoord-instellen.php?…']],
        'verplicht' => ['link'],
        'onderwerp' => 'Je account voor de beheeromgeving van Sporty',
        'tekst' => "Hoi {naam},\n\nJe bent uitgenodigd voor de beheeromgeving van Sporty. Via deze link kies je je wachtwoord (de link werkt 3 dagen):\n\n{link}\n\nTot snel!",
    ],
    'medewerker_nieuwe_link' => [
        'groep' => 'team',
        'naam' => 'Nieuwe wachtwoordlink voor een collega',
        'wanneer' => 'Als je onder Team voor een collega een nieuwe link maakt om (opnieuw) een wachtwoord te kiezen.',
        'velden' => ['naam' => ['Naam van de collega', 'Tim'], 'link' => ['Link om een wachtwoord te kiezen (3 dagen geldig)', 'https://…/wachtwoord-instellen.php?…']],
        'verplicht' => ['link'],
        'onderwerp' => 'Nieuwe link voor de beheeromgeving van Sporty',
        'tekst' => "Hoi {naam},\n\nVia deze link kies je (opnieuw) je wachtwoord. De link werkt 3 dagen:\n\n{link}",
    ],
    'team_aanmelding' => [
        'groep' => 'team',
        'naam' => 'Melding: nieuwe aanmelding',
        'wanneer' => 'Als een ouder het aanmeldformulier op de website heeft verstuurd.',
        'velden' => ['aantal' => ['Aantal aangemelde kinderen', '2 kinderen'], 'link' => ['Link naar de aanmelding in Mijn BSO', 'https://…/ouder.php?id=…']],
        'verplicht' => ['link'],
        'onderwerp' => 'Nieuwe aanmelding via de website',
        'tekst' => "Er is een nieuwe aanmelding binnen met {aantal}.\n\nBekijk de aanmelding in de beheeromgeving:\n{link}",
    ],
    'team_proefdag' => [
        'groep' => 'team',
        'naam' => 'Melding: proefdag geboekt',
        'wanneer' => 'Als een ouder op de website een proefdag heeft geboekt.',
        'velden' => ['wanneer' => ['Dag en tijd van de proefdag', 'dinsdag 10 november, 15:00–16:30 uur'], 'link' => ['Link naar Proefdagen in Mijn BSO', 'https://…/proefdagen.php']],
        'verplicht' => ['link'],
        'onderwerp' => 'Nieuwe proefdag geboekt',
        'tekst' => "Er is een proefdag geboekt via de website, op {wanneer}.\n\nBekijk de boeking in de beheeromgeving bij Proefdagen:\n{link}",
    ],
    'team_sollicitatie' => [
        'groep' => 'team',
        'naam' => 'Melding: nieuwe sollicitatie',
        'wanneer' => 'Als iemand via Word Sporty coach heeft gesolliciteerd.',
        'velden' => ['functie' => ['De gekozen functie, of "niet ingevuld"', 'Sporty coach'], 'link' => ['Link naar de sollicitatie in Mijn BSO', 'https://…/sollicitaties.php?id=…']],
        'verplicht' => ['link'],
        'onderwerp' => 'Nieuwe sollicitatie via de website',
        'tekst' => "Er is een nieuwe sollicitatie als Sporty coach binnengekomen.\nFunctie: {functie}\n\nBekijk hem in de beheeromgeving bij Sollicitaties:\n{link}",
    ],
    'team_op_de_hoogte' => [
        'groep' => 'team',
        'naam' => 'Melding: aanmelding Blijf op de hoogte',
        'wanneer' => 'Als iemand de aanmelding voor Blijf op de hoogte heeft bevestigd.',
        'velden' => ['link' => ['Link naar "Op de hoogte" in Mijn BSO', 'https://…/interesse.php']],
        'verplicht' => ['link'],
        'onderwerp' => 'Nieuwe aanmelding: Blijf op de hoogte',
        'tekst' => "Er heeft zich iemand aangemeld om op de hoogte te blijven. Vergeet het kennismakingspakket niet!\n\nBekijk de lijst in de beheeromgeving:\n{link}",
    ],
    'team_bericht' => [
        'groep' => 'team',
        'naam' => 'Melding: nieuw bericht van een ouder',
        'wanneer' => 'Als een ouder in Mijn BSO een bericht stuurt. Het bericht zelf staat niet in de e-mail.',
        'velden' => ['ouder' => ['Naam van de ouder', 'Sam de Jong'], 'link' => ['Link naar het gesprek in Mijn BSO', 'https://…/berichten.php?ouder=…']],
        'verplicht' => ['link'],
        'onderwerp' => 'Nieuw bericht van {ouder}',
        'tekst' => "Er is een nieuw bericht van {ouder}:\n{link}",
    ],
    'team_ziekmelding' => [
        'groep' => 'team',
        'naam' => 'Melding: ziekmelding',
        'wanneer' => 'Als een ouder in Mijn BSO een kind ziek meldt.',
        'velden' => ['ouder' => ['Naam van de ouder', 'Sam de Jong'], 'kind' => ['Voornaam van het kind', 'Noor'], 'periode' => ['Dag of periode', 'dinsdag 10 november'], 'link' => ['Link naar het gesprek in Mijn BSO', 'https://…/berichten.php?ouder=…']],
        'verplicht' => ['link'],
        'onderwerp' => 'Ziekmelding: {kind}',
        'tekst' => "{ouder} heeft {kind} ziek gemeld ({periode}).\n{link}",
    ],
    'team_opzegging' => [
        'groep' => 'team',
        'naam' => 'Melding: opzegging',
        'wanneer' => 'Als een ouder in Mijn BSO de opvang opzegt.',
        'velden' => ['ouder' => ['Naam van de ouder', 'Sam de Jong'], 'link' => ['Link naar het gesprek in Mijn BSO', 'https://…/berichten.php?ouder=…']],
        'verplicht' => ['link'],
        'onderwerp' => 'Opzegging van {ouder}',
        'tekst' => "{ouder} wil de opvang opzeggen. Zie de berichten:\n{link}",
    ],
    'team_privacyverzoek' => [
        'groep' => 'team',
        'naam' => 'Melding: nieuw privacyverzoek',
        'wanneer' => 'Als een ouder in Mijn BSO een privacyverzoek indient.',
        'velden' => ['soort' => ['Soort verzoek', 'inzage'], 'link' => ['Link naar het verzoek in Mijn BSO', 'https://…/verzoeken.php?id=…']],
        'verplicht' => ['link'],
        'onderwerp' => 'Nieuw privacyverzoek',
        'tekst' => "Er is een nieuw privacyverzoek ({soort}). Reageer binnen een maand:\n{link}",
    ],

    // ---------- Onder elke e-mail ----------
    'ondertekening' => [
        'groep' => 'algemeen',
        'naam' => 'Ondertekening',
        'wanneer' => 'Onder elke e-mail die Mijn BSO verstuurt.',
        'velden' => ['bedrijfsnaam' => ['De naam uit Instellingen', 'Sporty'], 'website' => ['Adres van Mijn BSO', 'https://…/beheer/']],
        'verplicht' => [],
        'onderwerp' => null,
        'tekst' => "--\n{bedrijfsnaam}\n{website}",
    ],
];

/** De aangepaste tekst van een e-mail (onderwerp, tekst, wie en wanneer), of null als de standaardtekst geldt. */
function mailtekst_eigen(string $sleutel, bool $vernieuw = false): ?array
{
    static $cache = null;
    if ($vernieuw || $cache === null) {
        $cache = [];
        foreach (rijen('SELECT * FROM mail_teksten') as $rij) {
            $cache[$rij['sleutel']] = $rij;
        }
    }
    return $cache[$sleutel] ?? null;
}

/** Leegt de cache na een wijziging. */
function mailtekst_vernieuw(): void
{
    mailtekst_eigen('', true);
}

/** Vult de invulvelden in een tekst in. */
function mailtekst_vul(string $tekst, array $waarden): string
{
    $vervang = [];
    foreach ($waarden as $veld => $waarde) {
        $vervang['{' . $veld . '}'] = (string) $waarde;
    }
    return strtr($tekst, $vervang);
}

/** [onderwerp, tekst] van een e-mail, met de invulvelden ingevuld. */
function mailtekst(string $sleutel, array $waarden = []): array
{
    $standaard = MAILTEKSTEN[$sleutel] ?? throw new InvalidArgumentException("Onbekende e-mail: {$sleutel}");
    $eigen = mailtekst_eigen($sleutel);
    return [
        mailtekst_vul((string) ($eigen['onderwerp'] ?? $standaard['onderwerp'] ?? ''), $waarden),
        mailtekst_vul((string) ($eigen['tekst'] ?? $standaard['tekst']), $waarden),
    ];
}

/** De voorbeeldwaarden van een e-mail, voor de voorbeeldweergave en de testmail. */
function mailtekst_voorbeeldwaarden(string $sleutel): array
{
    return array_map(fn ($veld) => $veld[1], MAILTEKSTEN[$sleutel]['velden']);
}

/**
 * Controleert een aangepaste tekst: niet leeg, de verplichte velden staan erin, en er staan geen velden in
 * die deze e-mail niet kent (een tikfout als {vornaam} zou anders letterlijk in de e-mail komen).
 * Geeft [veld => melding] terug.
 */
function mailtekst_controleer(string $sleutel, string $onderwerp, string $tekst): array
{
    $def = MAILTEKSTEN[$sleutel];
    $fouten = [];
    if ($def['onderwerp'] !== null && trim($onderwerp) === '') {
        $fouten['onderwerp'] = 'Vul een onderwerp in.';
    }
    if (trim($tekst) === '') {
        $fouten['tekst'] = 'Schrijf de tekst van de e-mail.';
    }
    foreach (['onderwerp' => $onderwerp, 'tekst' => $tekst] as $veld => $inhoud) {
        preg_match_all('/\{([^{}\s]{1,40})\}/u', $inhoud, $gevonden);
        $onbekend = array_values(array_diff(array_unique($gevonden[1]), array_keys($def['velden'])));
        if ($onbekend && !isset($fouten[$veld])) {
            $fouten[$veld] = (count($onbekend) === 1 ? 'Het invulveld {' . $onbekend[0] . '} bestaat niet' : 'De invulvelden {' . implode('}, {', $onbekend) . '} bestaan niet')
                . ' bij deze e-mail. Kies uit: {' . implode('}, {', array_keys($def['velden'])) . '}.';
        }
    }
    $ontbreekt = array_values(array_filter($def['verplicht'], fn ($veld) => !str_contains($tekst, '{' . $veld . '}')));
    if ($ontbreekt && !isset($fouten['tekst'])) {
        $fouten['tekst'] = (count($ontbreekt) === 1 ? 'Het invulveld {' . $ontbreekt[0] . '} moet in de tekst blijven staan' : 'De invulvelden {' . implode('} en {', $ontbreekt) . '} moeten in de tekst blijven staan')
            . ': zonder ' . (count($ontbreekt) === 1 ? 'dat veld' : 'die velden') . ' heeft de e-mail geen zin.';
    }
    return $fouten;
}

/** Stuurt een e-mail met de (eventueel aangepaste) tekst. */
function stuur_mailtekst(string $aan, string $sleutel, array $waarden = []): bool
{
    [$onderwerp, $tekst] = mailtekst($sleutel, $waarden);
    return stuur_mail($aan, $onderwerp, $tekst);
}

/** Stuurt een melding met de (eventueel aangepaste) tekst naar het teamadres. */
function mail_team_tekst(string $sleutel, array $waarden = []): void
{
    [$onderwerp, $tekst] = mailtekst($sleutel, $waarden);
    mail_team($onderwerp, $tekst);
}
