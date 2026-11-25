<?php
/*
 * E-mail versturen. Zet in e-mails nooit gevoelige details over kinderen:
 * alleen dat er iets nieuws is, met een link naar de beheeromgeving.
 * De teksten van alle e-mails staan in mailteksten.php en zijn aan te passen onder E-mails.
 * Elke e-mail komt in de tabel mail_log (E-mails → Verzonden): wanneer, aan wie, welke e-mail en of het lukte,
 * zonder de inhoud (daar staan links in om een wachtwoord te kiezen).
 */

declare(strict_types=1);

/** Hoe Mijn BSO e-mail verstuurt: 'log' (alleen in data/mail.log, om te testen), 'mail' (de mailfunctie van de server) of 'smtp' (eigen mailserver). */
function mail_methode(): string
{
    $methode = (string) koppeling('mail.methode');
    return in_array($methode, ['log', 'mail', 'smtp'], true) ? $methode : 'log';
}

/** De foutmelding van de laatste e-mail die niet verstuurd kon worden (in dit verzoek). */
function mail_laatste_fout(): string
{
    return (string) ($GLOBALS['mail_fout'] ?? '');
}

/** Status van een e-mail in mail_log => [label, badge-klasse]. */
const MAIL_STATUSSEN = [
    'verstuurd' => ['Verstuurd', 'geldig'],
    'mislukt' => ['Mislukt', 'mislukt'],
    'testmodus' => ['Niet verstuurd: testmodus', 'demo'],
    'overgeslagen' => ['Overgeslagen', 'wachtlijst'],
];

/** Legt een e-mail vast in mail_log (zonder inhoud). Mag het versturen nooit laten mislukken. */
function mail_log_schrijf(string $aan, string $onderwerp, string $sleutel, string $methode, string $status, string $fout = ''): void
{
    try {
        q('INSERT INTO mail_log (tijd, aan, onderwerp, sleutel, methode, status, fout) VALUES (?, ?, ?, ?, ?, ?, ?)', [
            nu(), mb_substr($aan, 0, 190), mb_substr($onderwerp, 0, 200), $sleutel, $methode, $status, mb_substr($fout, 0, 500),
        ]);
    } catch (Throwable $e) {
        error_log('mail_log: ' . $e->getMessage());
    }
}

/**
 * Een e-mail die bewust niet is verstuurd, bijvoorbeeld omdat er net al 3 e-mails naar hetzelfde adres gingen
 * (tegen misbruik van de formulieren). Zo zie je onder E-mails → Verzonden waarom iemand niets kreeg.
 */
function mail_overgeslagen(string $aan, string $sleutel, string $reden): void
{
    $naam = MAILTEKSTEN[$sleutel]['naam'] ?? $sleutel;
    mail_log_schrijf($aan, $naam, $sleutel, mail_methode(), 'overgeslagen', $reden);
}

/** Reden bij te_veel_mails_naar(), voor mail_overgeslagen(). */
const MAIL_REDEN_TE_VEEL = 'Er gingen net al 3 e-mails naar dit adres (binnen een kwartier). Dat is de grens tegen misbruik van de formulieren op de website.';

/**
 * Verstuurt een e-mail en legt hem vast in mail_log. $sleutel is de e-mail uit MAILTEKSTEN (zoals
 * 'aanmelding_ontvangen'), of iets als 'test' voor een testmail.
 */
function stuur_mail(string $aan, string $onderwerp, string $tekst, string $taal = 'nl', string $sleutel = ''): bool
{
    $GLOBALS['mail_fout'] = '';
    $onderwerp = trim((string) preg_replace('/[\r\n\t]+/', ' ', $onderwerp));
    [, $ondertekening] = $taal === 'en'
        ? mailtekst('ondertekening_en', ['bedrijfsnaam' => instelling('bedrijfsnaam', 'Sporty'), 'website' => website_url() . '/en/'])
        : mailtekst('ondertekening', ['bedrijfsnaam' => instelling('bedrijfsnaam', 'Sporty'), 'website' => basis_url() . '/']);
    $tekst .= "\n\n" . $ondertekening . "\n";
    $methode = mail_methode();

    if ($methode === 'log') {
        $regel = '[' . nu() . "] Aan: {$aan}\nOnderwerp: {$onderwerp}\n\n{$tekst}\n" . str_repeat('-', 60) . "\n";
        $log = data_dir() . '/mail.log';
        $gelukt = file_put_contents($log, $regel, FILE_APPEND | LOCK_EX) !== false;
        @chmod($log, 0600); // er staan persoonsgegevens en links in
        mail_log_schrijf($aan, $onderwerp, $sleutel, $methode, 'testmodus', 'Mijn BSO staat in testmodus: de e-mail staat alleen in data/mail.log op de server. Kies bij Koppelingen → E-mail versturen een mailserver.');
        return $gelukt;
    }

    $van = (string) koppeling('mail.van');
    $vanNaam = (string) koppeling('mail.van_naam');
    $antwoordAan = (string) koppeling('team_email');
    if ($methode === 'smtp') {
        try {
            smtp_verstuur(smtp_instellingen(), $van, $vanNaam, $aan, $antwoordAan, $onderwerp, $tekst);
            $gelukt = true;
        } catch (RuntimeException $fout) {
            $GLOBALS['mail_fout'] = $fout->getMessage();
            $gelukt = false;
        }
    } else {
        $headers = [
            'From' => mb_encode_mimeheader($vanNaam, 'UTF-8', 'Q') . " <{$van}>",
            'Reply-To' => $antwoordAan,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
        ];
        $gelukt = mail($aan, mb_encode_mimeheader($onderwerp, 'UTF-8', 'Q'), $tekst, $headers, '-f' . $van);
        if (!$gelukt) {
            $GLOBALS['mail_fout'] = 'De mailfunctie van de server weigerde de e-mail.';
        }
    }
    mail_log_schrijf($aan, $onderwerp, $sleutel, $methode, $gelukt ? 'verstuurd' : 'mislukt', $gelukt ? '' : mail_laatste_fout());
    // Bijhouden voor de pagina Koppelingen (zonder ontvanger of inhoud)
    if ($gelukt) {
        instelling_zet('mail_laatst_verstuurd', nu());
    } else {
        error_log('E-mail niet verstuurd: ' . mail_laatste_fout());
        instelling_zet('mail_laatste_fout', nu() . ' ' . mb_substr(mail_laatste_fout(), 0, 300));
    }
    return $gelukt;
}

function mail_team(string $onderwerp, string $tekst, string $sleutel = ''): void
{
    $adres = (string) koppeling('team_email');
    if ($adres !== '') {
        stuur_mail($adres, $onderwerp, $tekst, 'nl', $sleutel);
    }
}

/**
 * Hoe het ervoor staat met e-mail, voor een waarschuwing bovenaan (Vandaag en E-mails): null als alles goed
 * is, anders [soort, tekst]. Testmodus: er gaat niets de deur uit. Mislukt: e-mails van het afgelopen etmaal
 * die niet verstuurd konden worden.
 */
function mail_waarschuwing(): ?array
{
    if (mail_methode() === 'log') {
        return ['testmodus', 'Mijn BSO verstuurt nog geen e-mails: het versturen staat in testmodus. Ouders krijgen dus geen bevestiging, wachtwoordlink of melding. Kies een mailserver bij Koppelingen → E-mail versturen.'];
    }
    $mislukt = (int) waarde("SELECT COUNT(*) FROM mail_log WHERE status = 'mislukt' AND tijd >= ?", [date('Y-m-d H:i:s', time() - 86400)]);
    $laatsteGoed = (string) waarde("SELECT MAX(tijd) FROM mail_log WHERE status = 'verstuurd'");
    $laatsteFout = (string) waarde("SELECT MAX(tijd) FROM mail_log WHERE status = 'mislukt'");
    if ($mislukt > 0 && $laatsteFout > $laatsteGoed) {
        return ['mislukt', ($mislukt === 1 ? '1 e-mail kon' : $mislukt . ' e-mails konden') . ' in het afgelopen etmaal niet worden verstuurd, en daarna is er nog niets gelukt. Controleer de instellingen bij Koppelingen → E-mail versturen.'];
    }
    return null;
}
