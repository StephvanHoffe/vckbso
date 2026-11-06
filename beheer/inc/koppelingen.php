<?php
/*
 * Koppelingen met andere diensten (Stripe, e-mail). Te beheren in Mijn BSO onder Koppelingen,
 * door wie het recht "Koppelingen" heeft.
 *
 * - De waarden staan versleuteld in de tabel koppelingen (migratie 17).
 * - Staat een waarde in config.php op de server, dan gaat die voor; in Mijn BSO is hij dan
 *   alleen te zien (en geheimen alleen gemaskeerd), niet te wijzigen.
 * - Geheimen (sleutels en wachtwoorden) zijn na het opslaan nooit meer leesbaar in Mijn BSO.
 */

declare(strict_types=1);

/** sleutel => [omschrijving, geheim?, standaardwaarde]. */
const KOPPELING_VELDEN = [
    'stripe.geheime_sleutel' => ['Geheime sleutel van Stripe', true, ''],
    'stripe.webhook_geheim' => ['Webhookgeheim van Stripe', true, ''],
    'stripe.methoden_machtiging' => ['Betaalmethoden voor de machtiging', false, 'ideal,sepa_debit'],
    'stripe.methoden_betalen' => ['Betaalmethoden voor los betalen', false, 'ideal'],
    'mail.methode' => ['Manier van e-mail versturen', false, 'log'],
    'mail.van' => ['Afzenderadres', false, 'info@sporty.nl'],
    'mail.van_naam' => ['Afzendernaam', false, 'Sporty'],
    'team_email' => ['Teamadres', false, 'info@sporty.nl'],
    'smtp.host' => ['Mailserver', false, ''],
    'smtp.poort' => ['Poort', false, '587'],
    'smtp.beveiliging' => ['Beveiliging', false, 'starttls'],
    'smtp.gebruiker' => ['Gebruikersnaam', false, ''],
    'smtp.wachtwoord' => ['Wachtwoord', true, ''],
];

/** De waarde uit config.php, of null als die daar niet (of leeg) staat. */
function koppeling_uit_config(string $sleutel): mixed
{
    $waarde = cfg($sleutel);
    return $waarde === null || $waarde === '' || $waarde === [] ? null : $waarde;
}

function koppeling_in_config(string $sleutel): bool
{
    return koppeling_uit_config($sleutel) !== null;
}

/** De opgeslagen koppelingen (ontsleuteld), met wie ze wanneer heeft gewijzigd. */
function koppelingen_opgeslagen(bool $vernieuw = false): array
{
    static $cache = null;
    if ($cache === null || $vernieuw) {
        $cache = [];
        foreach (rijen('SELECT * FROM koppelingen') as $rij) {
            $rij['waarde'] = ontsleutel($rij['waarde']);
            $cache[$rij['sleutel']] = $rij;
        }
    }
    return $cache;
}

/** De waarde van een koppeling: uit config.php, anders uit Mijn BSO, anders de standaardwaarde. */
function koppeling(string $sleutel): mixed
{
    $uitConfig = koppeling_uit_config($sleutel);
    if ($uitConfig !== null) {
        return $uitConfig;
    }
    $opgeslagen = koppelingen_opgeslagen()[$sleutel]['waarde'] ?? '';
    return $opgeslagen !== '' ? $opgeslagen : (KOPPELING_VELDEN[$sleutel][2] ?? null);
}

/** Een lijst (zoals betaalmethoden): uit config.php als array, uit Mijn BSO als "a,b". */
function koppeling_lijst(string $sleutel): array
{
    $waarde = koppeling($sleutel);
    $lijst = is_array($waarde) ? $waarde : explode(',', (string) $waarde);
    return array_values(array_unique(array_filter(array_map(fn ($w) => trim((string) $w), $lijst), fn ($w) => $w !== '')));
}

/** Slaat een koppeling op (versleuteld); een lege waarde haalt hem weg. Niet voor waarden uit config.php. */
function koppeling_zet(string $sleutel, string $waarde, int $door): void
{
    if (!isset(KOPPELING_VELDEN[$sleutel]) || koppeling_in_config($sleutel)) {
        return;
    }
    if ($waarde === '') {
        q('DELETE FROM koppelingen WHERE sleutel = ?', [$sleutel]);
    } else {
        q('INSERT INTO koppelingen (sleutel, waarde, gewijzigd_op, gewijzigd_door) VALUES (?, ?, ?, ?)
           ON CONFLICT(sleutel) DO UPDATE SET waarde = excluded.waarde, gewijzigd_op = excluded.gewijzigd_op, gewijzigd_door = excluded.gewijzigd_door',
            [$sleutel, versleutel($waarde), nu(), $door]);
    }
    koppelingen_opgeslagen(true);
}

/**
 * Een geheim zo tonen dat je hem herkent, maar niet kunt gebruiken: "sk_live_…3f9a".
 * Wachtwoorden helemaal niet, alleen dat er een is.
 */
function koppeling_masker(string $sleutel, string $waarde): string
{
    if ($waarde === '') {
        return '';
    }
    if ($sleutel === 'smtp.wachtwoord' || strlen($waarde) < 16) {
        return '••••••••';
    }
    $voorvoegsel = preg_match('/^((?:sk|rk|pk)_(?:live|test)_|whsec_)/', $waarde, $m) ? $m[1] : '';
    return $voorvoegsel . '…' . substr($waarde, -4);
}
