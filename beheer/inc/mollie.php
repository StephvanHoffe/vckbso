<?php
/*
 * Mollie: machtiging voor automatische incasso (SEPA) en betalingen.
 *
 * Werkwijze
 * 1. Bij het aanmelden doet de ouder een eerste betaling van € 0,01 via iDEAL.
 *    Mollie maakt daarmee een SEPA-machtiging aan voor die bankrekening.
 * 2. Voor elke factuur start de beheerder een incasso op die machtiging.
 * 3. Mollie meldt de uitkomst via de webhook (mollie-webhook.php).
 *
 * Zonder API-sleutel draait alles in demo-modus: er gaat geen geld over.
 * Documentatie: https://docs.mollie.com/docs/recurring-payments
 */

declare(strict_types=1);

const MOLLIE_API = 'https://api.mollie.com/v2/';

function mollie_actief(): bool
{
    return trim((string) cfg('mollie.api_key', '')) !== '';
}

function mollie_testmodus(): bool
{
    return str_starts_with((string) cfg('mollie.api_key', ''), 'test_');
}

/** Roept de Mollie API aan. Gooit een RuntimeException bij een fout. */
function mollie(string $methode, string $pad, ?array $data = null): array
{
    $curl = curl_init(MOLLIE_API . ltrim($pad, '/'));
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $methode,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . cfg('mollie.api_key'),
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    if ($data !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data, JSON_THROW_ON_ERROR));
    }
    $antwoord = curl_exec($curl);
    $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $fout = curl_error($curl);
    curl_close($curl);

    if ($antwoord === false) {
        throw new RuntimeException('Mollie is niet bereikbaar: ' . $fout);
    }
    $json = json_decode((string) $antwoord, true) ?: [];
    if ($code >= 400) {
        throw new RuntimeException('Mollie gaf een fout: ' . ($json['detail'] ?? $json['title'] ?? ('HTTP ' . $code)));
    }
    return $json;
}

function mollie_bedrag(int $cent): array
{
    return ['currency' => 'EUR', 'value' => number_format($cent / 100, 2, '.', '')];
}

/** Mollie accepteert geen webhook op localhost; lokaal testen gaat via de terugkeerpagina. */
function mollie_webhook_url(): ?string
{
    $host = (string) parse_url(basis_url(), PHP_URL_HOST);
    if ($host === 'localhost' || $host === '' || str_starts_with($host, '127.') || str_ends_with($host, '.test')) {
        return null;
    }
    return app_url('mollie-webhook.php');
}

function mollie_klant(array $ouder): string
{
    if (!empty($ouder['mollie_klant_id'])) {
        return $ouder['mollie_klant_id'];
    }
    $klant = mollie('POST', 'customers', [
        'name' => $ouder['naam'],
        'email' => $ouder['email'],
        'locale' => 'nl_NL',
        'metadata' => ['gebruiker_id' => (int) $ouder['id']],
    ]);
    q('UPDATE gebruikers SET mollie_klant_id = ? WHERE id = ?', [$klant['id'], $ouder['id']]);
    return $klant['id'];
}

function registreer_betaling(array $betaling, string $soort, int $gebruikerId, ?int $factuurId, int $cent): void
{
    q('INSERT INTO betalingen (mollie_id, soort, gebruiker_id, factuur_id, bedrag_cent, status, aangemaakt_op, bijgewerkt_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
        $betaling['id'], $soort, $gebruikerId, $factuurId, $cent, $betaling['status'] ?? 'open', nu(), nu(),
    ]);
}

/** Start de eerste betaling waarmee de ouder de machtiging afgeeft. Geeft de betaal-URL terug. */
function start_machtiging(array $ouder): string
{
    $data = [
        'amount' => mollie_bedrag(1),
        'customerId' => mollie_klant($ouder),
        'sequenceType' => 'first',
        'description' => 'Machtiging automatische incasso ' . instelling('bedrijfsnaam', 'Sporty'),
        'redirectUrl' => app_url('machtiging.php?terug=1'),
        'locale' => 'nl_NL',
        'metadata' => ['soort' => 'machtiging', 'gebruiker_id' => (int) $ouder['id']],
    ];
    if ($methode = (string) cfg('mollie.methode_machtiging', '')) {
        $data['method'] = $methode;
    }
    if ($webhook = mollie_webhook_url()) {
        $data['webhookUrl'] = $webhook;
    }
    $betaling = mollie('POST', 'payments', $data);
    registreer_betaling($betaling, 'machtiging', (int) $ouder['id'], null, 1);
    q("UPDATE gebruikers SET mandaat_status = 'in_behandeling' WHERE id = ? AND mandaat_status IN ('geen', 'ingetrokken')", [$ouder['id']]);
    log_actie('Machtiging gestart', 'Mollie ' . $betaling['id'], (int) $ouder['id']);
    return $betaling['_links']['checkout']['href'];
}

/** Incasso voor een factuur op de machtiging van de ouder. */
function start_incasso(array $factuur): void
{
    $ouder = rij('SELECT * FROM gebruikers WHERE id = ?', [$factuur['ouder_id']]);
    if (!$ouder || !in_array($ouder['mandaat_status'], ['geldig', 'demo'], true)) {
        throw new RuntimeException('Geen geldige machtiging voor ' . ($ouder['naam'] ?? 'deze ouder') . '.');
    }
    if ($ouder['mandaat_status'] === 'demo' || !mollie_actief()) {
        // Demo: er gaat geen geld over, de factuur wordt meteen als betaald gemarkeerd
        q("UPDATE facturen SET status = 'betaald', betaald_op = ?, notitie = 'Demo-modus: niet echt afgeschreven' WHERE id = ?", [nu(), $factuur['id']]);
        log_actie('Incasso (demo)', 'Factuur ' . $factuur['nummer']);
        return;
    }
    $data = [
        'amount' => mollie_bedrag((int) $factuur['bedrag_cent']),
        'customerId' => $ouder['mollie_klant_id'],
        'mandateId' => $ouder['mandaat_id'],
        'sequenceType' => 'recurring',
        'description' => 'Factuur ' . $factuur['nummer'] . ' ' . instelling('bedrijfsnaam', 'Sporty'),
        'metadata' => ['soort' => 'incasso', 'factuur_id' => (int) $factuur['id']],
    ];
    if ($webhook = mollie_webhook_url()) {
        $data['webhookUrl'] = $webhook;
    }
    $betaling = mollie('POST', 'payments', $data);
    registreer_betaling($betaling, 'incasso', (int) $ouder['id'], (int) $factuur['id'], (int) $factuur['bedrag_cent']);
    q("UPDATE facturen SET status = 'incasso' WHERE id = ?", [$factuur['id']]);
    log_actie('Incasso gestart', 'Factuur ' . $factuur['nummer'] . ', Mollie ' . $betaling['id']);
}

/** Losse betaling van een factuur (bijvoorbeeld na een mislukte incasso). Geeft de betaal-URL terug. */
function start_eenmalige_betaling(array $factuur): string
{
    $data = [
        'amount' => mollie_bedrag((int) $factuur['bedrag_cent']),
        'description' => 'Factuur ' . $factuur['nummer'] . ' ' . instelling('bedrijfsnaam', 'Sporty'),
        'redirectUrl' => app_url('factuur.php?id=' . (int) $factuur['id'] . '&betaald=1'),
        'locale' => 'nl_NL',
        'metadata' => ['soort' => 'eenmalig', 'factuur_id' => (int) $factuur['id']],
    ];
    if ($webhook = mollie_webhook_url()) {
        $data['webhookUrl'] = $webhook;
    }
    $betaling = mollie('POST', 'payments', $data);
    registreer_betaling($betaling, 'eenmalig', (int) $factuur['ouder_id'], (int) $factuur['id'], (int) $factuur['bedrag_cent']);
    return $betaling['_links']['checkout']['href'];
}

/**
 * Haalt de actuele status van een betaling op bij Mollie en verwerkt die.
 * Wordt aangeroepen door de webhook en na terugkomst van de betaalpagina.
 */
function verwerk_betaling(string $mollieId): void
{
    $lokaal = rij('SELECT * FROM betalingen WHERE mollie_id = ?', [$mollieId]);
    if (!$lokaal) {
        return; // Onbekende betaling: negeren
    }
    $betaling = mollie('GET', 'payments/' . rawurlencode($mollieId));
    $status = (string) $betaling['status'];
    q('UPDATE betalingen SET status = ?, bijgewerkt_op = ? WHERE id = ?', [$status, nu(), $lokaal['id']]);

    if ($lokaal['soort'] === 'machtiging') {
        if ($status === 'paid' && !empty($betaling['mandateId'])) {
            $mandaat = mollie('GET', 'customers/' . rawurlencode($betaling['customerId']) . '/mandates/' . rawurlencode($betaling['mandateId']));
            q("UPDATE gebruikers SET mandaat_id = ?, mandaat_status = 'geldig', mandaat_rekening = ?, mandaat_naam = ?, mandaat_datum = ? WHERE id = ?", [
                $mandaat['id'],
                masker_iban((string) ($mandaat['details']['consumerAccount'] ?? '')),
                (string) ($mandaat['details']['consumerName'] ?? ''),
                vandaag(),
                $lokaal['gebruiker_id'],
            ]);
            log_actie('Machtiging geldig', 'Mollie ' . $mandaat['id'], (int) $lokaal['gebruiker_id']);
        } elseif (in_array($status, ['failed', 'canceled', 'expired'], true)) {
            q("UPDATE gebruikers SET mandaat_status = 'geen' WHERE id = ? AND mandaat_status = 'in_behandeling'", [$lokaal['gebruiker_id']]);
        }
        return;
    }

    if ($lokaal['factuur_id']) {
        $terugbetaald = ($betaling['amountRefunded']['value'] ?? '0.00') !== '0.00' || !empty($betaling['amountChargedBack']);
        if ($status === 'paid' && !$terugbetaald) {
            q("UPDATE facturen SET status = 'betaald', betaald_op = ? WHERE id = ? AND status != 'geannuleerd'", [nu(), $lokaal['factuur_id']]);
        } elseif (in_array($status, ['failed', 'canceled', 'expired'], true) || $terugbetaald) {
            // Een losse betaling die de ouder afbreekt, verandert een open incasso niet
            if ($lokaal['soort'] === 'incasso' || $terugbetaald) {
                q("UPDATE facturen SET status = 'mislukt' WHERE id = ? AND status IN ('incasso', 'betaald')", [$lokaal['factuur_id']]);
                $factuur = rij('SELECT * FROM facturen WHERE id = ?', [$lokaal['factuur_id']]);
                if ($factuur) {
                    systeembericht((int) $factuur['ouder_id'], 'Het lukte niet om factuur ' . $factuur['nummer'] . ' automatisch af te schrijven. Je kunt de factuur online betalen via "Facturen", of neem contact met ons op.');
                }
            }
        }
    }
}

/** Demo-modus: de machtiging wordt gesimuleerd, er gaat geen geld over. */
function demo_machtiging(array $ouder, string $naam): void
{
    q("UPDATE gebruikers SET mandaat_status = 'demo', mandaat_id = 'demo', mandaat_rekening = 'NL00 **** **** 0000', mandaat_naam = ?, mandaat_datum = ? WHERE id = ?", [
        $naam, vandaag(), $ouder['id'],
    ]);
    log_actie('Machtiging (demo)', '', (int) $ouder['id']);
}
