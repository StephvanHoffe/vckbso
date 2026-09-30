<?php
/*
 * E-mail versturen. Zet in e-mails nooit gevoelige details over kinderen:
 * alleen dat er iets nieuws is, met een link naar de beheeromgeving.
 */

declare(strict_types=1);

function stuur_mail(string $aan, string $onderwerp, string $tekst): bool
{
    $onderwerp = trim((string) preg_replace('/[\r\n\t]+/', ' ', $onderwerp));
    $tekst .= "\n\n--\n" . instelling('bedrijfsnaam', 'BSO VCK') . "\n" . basis_url() . "/\n";

    if (cfg('mail.methode') !== 'mail') {
        $regel = '[' . nu() . "] Aan: {$aan}\nOnderwerp: {$onderwerp}\n\n{$tekst}\n" . str_repeat('-', 60) . "\n";
        return file_put_contents(data_dir() . '/mail.log', $regel, FILE_APPEND | LOCK_EX) !== false;
    }

    $van = (string) cfg('mail.van');
    $vanNaam = mb_encode_mimeheader((string) cfg('mail.van_naam'), 'UTF-8', 'Q');
    $headers = [
        'From' => "{$vanNaam} <{$van}>",
        'Reply-To' => (string) cfg('team_email'),
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ];
    return mail($aan, mb_encode_mimeheader($onderwerp, 'UTF-8', 'Q'), $tekst, $headers, '-f' . $van);
}

function mail_team(string $onderwerp, string $tekst): void
{
    $adres = (string) cfg('team_email');
    if ($adres !== '') {
        stuur_mail($adres, $onderwerp, $tekst);
    }
}
