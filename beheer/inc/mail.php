<?php
/*
 * E-mail versturen. Zet in e-mails nooit gevoelige details over kinderen:
 * alleen dat er iets nieuws is, met een link naar de beheeromgeving.
 * De teksten van alle e-mails staan in mailteksten.php en zijn aan te passen onder Instellingen → E-mails.
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

function stuur_mail(string $aan, string $onderwerp, string $tekst): bool
{
    $GLOBALS['mail_fout'] = '';
    $onderwerp = trim((string) preg_replace('/[\r\n\t]+/', ' ', $onderwerp));
    [, $ondertekening] = mailtekst('ondertekening', ['bedrijfsnaam' => instelling('bedrijfsnaam', 'Sporty'), 'website' => basis_url() . '/']);
    $tekst .= "\n\n" . $ondertekening . "\n";
    $methode = mail_methode();

    if ($methode === 'log') {
        $regel = '[' . nu() . "] Aan: {$aan}\nOnderwerp: {$onderwerp}\n\n{$tekst}\n" . str_repeat('-', 60) . "\n";
        return file_put_contents(data_dir() . '/mail.log', $regel, FILE_APPEND | LOCK_EX) !== false;
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
    // Bijhouden voor de pagina Koppelingen (zonder ontvanger of inhoud)
    if ($gelukt) {
        instelling_zet('mail_laatst_verstuurd', nu());
    } else {
        error_log('E-mail niet verstuurd: ' . mail_laatste_fout());
        instelling_zet('mail_laatste_fout', nu() . ' ' . mb_substr(mail_laatste_fout(), 0, 300));
    }
    return $gelukt;
}

function mail_team(string $onderwerp, string $tekst): void
{
    $adres = (string) koppeling('team_email');
    if ($adres !== '') {
        stuur_mail($adres, $onderwerp, $tekst);
    }
}
