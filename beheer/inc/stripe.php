<?php
/*
 * Stripe: machtiging voor automatische incasso (SEPA) en betalingen.
 *
 * Werkwijze
 * 1. De ouder geeft een machtiging af op een betaalpagina van Stripe (Checkout in "setup"-modus):
 *    via de eigen bank met iDEAL | Wero (Stripe schrijft € 0,01 af en betaalt die meteen terug),
 *    of door het IBAN in te vullen. Stripe maakt daarmee een SEPA-machtiging aan.
 * 2. Voor elke factuur start de beheerder een incasso op die machtiging (een PaymentIntent).
 *    Een SEPA-incasso duurt een paar werkdagen.
 * 3. Stripe meldt de uitkomst via de webhook (stripe-webhook.php). Na terugkomst van de
 *    betaalpagina halen we de status ook meteen zelf op, dus lokaal testen kan zonder webhook.
 *
 * We vertrouwen nooit de inhoud van een melding: de webhook controleert de handtekening en we
 * halen de actuele status altijd zelf op bij Stripe.
 *
 * Zonder geheime sleutel draait alles in demo-modus: er gaat geen geld over.
 * Documentatie: https://docs.stripe.com/payments/ideal/set-up-payment,
 * https://docs.stripe.com/payments/sepa-debit en https://docs.stripe.com/webhooks
 */

declare(strict_types=1);

// Het adres van de API. Alleen in tests wordt dit vooraf op een nagebootste Stripe gezet.
if (!defined('STRIPE_API')) {
    define('STRIPE_API', 'https://api.stripe.com/v1/');
}
const STRIPE_WEBHOOK_MARGE = 300; // seconden verschil met de tijd in de handtekening

/** Betaalmethoden op de pagina's van Stripe, tenzij config.php iets anders zegt. */
const STRIPE_METHODEN_MACHTIGING = ['ideal', 'sepa_debit'];
const STRIPE_METHODEN_BETALEN = ['ideal'];

/** De gebeurtenissen die de webhook verwerkt. Zet precies deze aan bij de webhook in het dashboard van Stripe. */
const STRIPE_EVENTS = [
    'checkout.session.completed',
    'checkout.session.async_payment_succeeded',
    'checkout.session.async_payment_failed',
    'checkout.session.expired',
    'payment_intent.processing',
    'payment_intent.succeeded',
    'payment_intent.payment_failed',
    'payment_intent.canceled',
    'charge.refunded',
    'charge.dispute.created',
    'mandate.updated',
];

function stripe_actief(): bool
{
    return trim((string) cfg('stripe.geheime_sleutel', '')) !== '';
}

function stripe_testmodus(): bool
{
    return (bool) preg_match('/^(sk|rk)_test_/', (string) cfg('stripe.geheime_sleutel', ''));
}

/** Ingestelde betaalmethoden (een lege lijst betekent: wat in het dashboard van Stripe aan staat). */
function stripe_methoden(string $soort): array
{
    $standaard = $soort === 'machtiging' ? STRIPE_METHODEN_MACHTIGING : STRIPE_METHODEN_BETALEN;
    $methoden = cfg('stripe.methoden_' . $soort, $standaard);
    return array_values(array_unique(array_filter(array_map('strval', is_array($methoden) ? $methoden : $standaard))));
}

/**
 * Zet een (geneste) array om naar de formuliervelden die Stripe verwacht, zoals
 * line_items[0][price_data][currency]=eur en expand[]=latest_charge.
 */
function stripe_velden(array $data, string $voorvoegsel = ''): array
{
    $paren = [];
    $lijst = array_is_list($data);
    foreach ($data as $sleutel => $waarde) {
        if ($voorvoegsel === '') {
            $naam = (string) $sleutel;
        } else {
            $naam = $voorvoegsel . ($lijst && !is_array($waarde) ? '[]' : '[' . $sleutel . ']');
        }
        if (is_array($waarde)) {
            array_push($paren, ...stripe_velden($waarde, $naam));
        } elseif ($waarde !== null) {
            $paren[] = [$naam, is_bool($waarde) ? ($waarde ? 'true' : 'false') : (string) $waarde];
        }
    }
    return $paren;
}

function stripe_query(array $data): string
{
    return implode('&', array_map(fn ($paar) => rawurlencode($paar[0]) . '=' . rawurlencode($paar[1]), stripe_velden($data)));
}

/**
 * Roept de Stripe API aan. Gooit een RuntimeException bij een fout.
 * Met een $sleutel (idempotency key) voert Stripe dezelfde opdracht nooit twee keer uit,
 * ook niet bij een dubbele klik of een time-out.
 */
function stripe(string $methode, string $pad, array $data = [], ?string $sleutel = null): array
{
    $url = STRIPE_API . ltrim($pad, '/');
    $velden = stripe_query($data);
    if ($methode === 'GET' && $velden !== '') {
        $url .= '?' . $velden;
    }
    $koppen = ['Authorization: Bearer ' . cfg('stripe.geheime_sleutel'), 'Accept: application/json'];
    if ($versie = (string) cfg('stripe.api_versie', '')) {
        $koppen[] = 'Stripe-Version: ' . $versie;
    }
    if ($sleutel !== null) {
        $koppen[] = 'Idempotency-Key: ' . $sleutel;
    }
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $methode,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($methode !== 'GET') {
        $koppen[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($curl, CURLOPT_POSTFIELDS, $velden);
    }
    curl_setopt($curl, CURLOPT_HTTPHEADER, $koppen);
    $antwoord = curl_exec($curl);
    $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $fout = curl_error($curl);
    curl_close($curl);

    if ($antwoord === false) {
        throw new RuntimeException('Stripe is niet bereikbaar: ' . $fout);
    }
    $json = json_decode((string) $antwoord, true) ?: [];
    if ($code >= 400) {
        throw new RuntimeException('Stripe gaf een fout: ' . ($json['error']['message'] ?? ('HTTP ' . $code)));
    }
    return $json;
}

/** Voorvoegsel voor idempotency keys, zodat twee organisaties met één Stripe-account elkaar niet raken. */
function stripe_sleutel(string ...$delen): string
{
    return 'mijnbso-' . substr(hash('sha256', basis_url()), 0, 10) . '-' . implode('-', $delen);
}

/**
 * Controleert de handtekening van een webhook (kop Stripe-Signature: t=...,v1=...).
 * Stripe ondertekent "t.inhoud" met HMAC-SHA256 en het geheim van de webhook (whsec_...).
 */
function stripe_handtekening_ok(string $inhoud, string $kop, string $geheim, ?int $nu = null): bool
{
    if ($geheim === '' || $kop === '') {
        return false;
    }
    $tijd = null;
    $handtekeningen = [];
    foreach (explode(',', $kop) as $deel) {
        [$sleutel, $waarde] = array_pad(explode('=', trim($deel), 2), 2, '');
        if ($sleutel === 't' && ctype_digit($waarde)) {
            $tijd = (int) $waarde;
        } elseif ($sleutel === 'v1') {
            $handtekeningen[] = $waarde;
        }
    }
    if ($tijd === null || !$handtekeningen || abs(($nu ?? time()) - $tijd) > STRIPE_WEBHOOK_MARGE) {
        return false;
    }
    $verwacht = hash_hmac('sha256', $tijd . '.' . $inhoud, $geheim);
    foreach ($handtekeningen as $handtekening) {
        if (hash_equals($verwacht, $handtekening)) {
            return true;
        }
    }
    return false;
}

function stripe_klant(array $ouder): string
{
    if (!empty($ouder['stripe_klant_id'])) {
        return $ouder['stripe_klant_id'];
    }
    $klant = stripe('POST', 'customers', [
        'name' => $ouder['naam'],
        'email' => $ouder['email'],
        'preferred_locales' => ['nl'],
        'metadata' => ['gebruiker_id' => (int) $ouder['id']],
    ], stripe_sleutel('klant', (string) $ouder['id']));
    q('UPDATE gebruikers SET stripe_klant_id = ? WHERE id = ?', [$klant['id'], $ouder['id']]);
    return $klant['id'];
}

function registreer_betaling(string $externId, string $soort, int $gebruikerId, ?int $factuurId, int $cent, string $status, ?string $paymentIntent = null): void
{
    q('INSERT OR IGNORE INTO betalingen (extern_id, payment_intent, soort, gebruiker_id, factuur_id, bedrag_cent, status, aangemaakt_op, bijgewerkt_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $externId, $paymentIntent, $soort, $gebruikerId, $factuurId, $cent, $status, nu(), nu(),
    ]);
}

/** Start de pagina van Stripe waarop de ouder de machtiging afgeeft. Geeft de URL van die pagina terug. */
function start_machtiging(array $ouder): string
{
    $omschrijving = 'Machtiging automatische incasso ' . instelling('bedrijfsnaam', 'Sporty');
    $metadata = ['soort' => 'machtiging', 'gebruiker_id' => (int) $ouder['id']];
    $data = [
        'mode' => 'setup',
        'customer' => stripe_klant($ouder),
        'locale' => 'nl',
        'success_url' => app_url('machtiging.php?terug=1&sessie={CHECKOUT_SESSION_ID}'),
        'cancel_url' => app_url('machtiging.php?afgebroken=1'),
        'metadata' => $metadata,
        'setup_intent_data' => ['description' => $omschrijving, 'metadata' => $metadata],
    ];
    if ($methoden = stripe_methoden('machtiging')) {
        $data['payment_method_types'] = $methoden;
    } else {
        $data['currency'] = 'eur';
    }
    $sessie = stripe('POST', 'checkout/sessions', $data);
    registreer_betaling($sessie['id'], 'machtiging', (int) $ouder['id'], null, 0, 'open');
    q("UPDATE gebruikers SET mandaat_status = 'in_behandeling' WHERE id = ? AND mandaat_status IN ('geen', 'ingetrokken')", [$ouder['id']]);
    log_actie('Machtiging gestart', 'Stripe ' . $sessie['id'], (int) $ouder['id']);
    return (string) $sessie['url'];
}

/** Incasso voor een factuur op de machtiging van de ouder. */
function start_incasso(array $factuur): void
{
    if (!in_array($factuur['status'], ['open', 'mislukt'], true)) {
        throw new RuntimeException('Factuur ' . $factuur['nummer'] . ' staat op "' . status_label($factuur['status']) . '"; daar start je geen incasso voor.');
    }
    $ouder = rij('SELECT * FROM gebruikers WHERE id = ?', [$factuur['ouder_id']]);
    if (!$ouder || !in_array($ouder['mandaat_status'], ['geldig', 'demo'], true)) {
        throw new RuntimeException('Geen geldige machtiging voor ' . ($ouder['naam'] ?? 'deze ouder') . '.');
    }
    if ($ouder['mandaat_status'] === 'demo' || !stripe_actief()) {
        // Demo: er gaat geen geld over, de factuur wordt meteen als betaald gemarkeerd
        q("UPDATE facturen SET status = 'betaald', betaald_op = ?, notitie = 'Demo-modus: niet echt afgeschreven' WHERE id = ?", [nu(), $factuur['id']]);
        log_actie('Incasso (demo)', 'Factuur ' . $factuur['nummer']);
        return;
    }
    if (empty($ouder['stripe_klant_id']) || !str_starts_with((string) $ouder['mandaat_id'], 'pm_')) {
        throw new RuntimeException('De machtiging van ' . $ouder['naam'] . ' is niet bij Stripe bekend. Vraag de ouder om de machtiging opnieuw af te geven.');
    }
    // Eén poging per keer: een dubbele klik of time-out geeft dezelfde incasso, geen tweede
    $poging = 1 + (int) waarde("SELECT COUNT(*) FROM betalingen WHERE factuur_id = ? AND soort = 'incasso'", [$factuur['id']]);
    $betaling = stripe('POST', 'payment_intents', [
        'amount' => (int) $factuur['bedrag_cent'],
        'currency' => 'eur',
        'customer' => $ouder['stripe_klant_id'],
        'payment_method' => $ouder['mandaat_id'],
        'payment_method_types' => ['sepa_debit'],
        'confirm' => true,
        'off_session' => true,
        'description' => 'Factuur ' . $factuur['nummer'] . ' ' . instelling('bedrijfsnaam', 'Sporty'),
        'metadata' => ['soort' => 'incasso', 'factuur_id' => (int) $factuur['id'], 'factuurnummer' => $factuur['nummer']],
    ], stripe_sleutel('incasso', (string) $factuur['id'], (string) $poging));
    registreer_betaling($betaling['id'], 'incasso', (int) $ouder['id'], (int) $factuur['id'], (int) $factuur['bedrag_cent'], (string) $betaling['status'], $betaling['id']);
    q("UPDATE facturen SET status = 'incasso' WHERE id = ? AND status IN ('open', 'mislukt')", [$factuur['id']]);
    log_actie('Incasso gestart', 'Factuur ' . $factuur['nummer'] . ', Stripe ' . $betaling['id']);
    if (in_array($betaling['status'], ['succeeded', 'requires_payment_method', 'canceled'], true)) {
        verwerk_payment_intent($betaling['id']);
    }
}

/** Losse betaling van een factuur (bijvoorbeeld na een mislukte incasso). Geeft de URL van de betaalpagina terug. */
function start_eenmalige_betaling(array $factuur): string
{
    $ouder = rij('SELECT * FROM gebruikers WHERE id = ?', [$factuur['ouder_id']]);
    $omschrijving = 'Factuur ' . $factuur['nummer'] . ' ' . instelling('bedrijfsnaam', 'Sporty');
    $metadata = ['soort' => 'eenmalig', 'factuur_id' => (int) $factuur['id'], 'factuurnummer' => $factuur['nummer']];
    $data = [
        'mode' => 'payment',
        'locale' => 'nl',
        'line_items' => [[
            'quantity' => 1,
            'price_data' => ['currency' => 'eur', 'unit_amount' => (int) $factuur['bedrag_cent'], 'product_data' => ['name' => $omschrijving]],
        ]],
        'success_url' => app_url('factuur.php?id=' . (int) $factuur['id'] . '&betaald=1&sessie={CHECKOUT_SESSION_ID}'),
        'cancel_url' => app_url('factuur.php?id=' . (int) $factuur['id']),
        'client_reference_id' => (string) $factuur['id'],
        'metadata' => $metadata,
        'payment_intent_data' => ['description' => $omschrijving, 'metadata' => $metadata],
    ];
    if ($ouder) {
        $data['customer'] = stripe_klant($ouder);
    }
    if ($methoden = stripe_methoden('betalen')) {
        $data['payment_method_types'] = $methoden;
    }
    $sessie = stripe('POST', 'checkout/sessions', $data);
    registreer_betaling($sessie['id'], 'eenmalig', (int) $factuur['ouder_id'], (int) $factuur['id'], (int) $factuur['bedrag_cent'], 'open');
    return (string) $sessie['url'];
}

/**
 * Haalt een Checkout-sessie op en verwerkt de uitkomst: een machtiging, of een losse betaling.
 * Wordt aangeroepen door de webhook en na terugkomst van de betaalpagina.
 */
function verwerk_checkout_sessie(string $sessieId): void
{
    $lokaal = rij('SELECT * FROM betalingen WHERE extern_id = ?', [$sessieId]);
    if (!$lokaal) {
        return; // Niet van ons: negeren
    }
    $sessie = stripe('GET', 'checkout/sessions/' . rawurlencode($sessieId), ['expand' => ['setup_intent.latest_attempt', 'setup_intent.payment_method']]);

    if ($lokaal['soort'] === 'machtiging') {
        $setup = is_array($sessie['setup_intent'] ?? null) ? $sessie['setup_intent'] : null;
        $status = $setup ? (string) $setup['status'] : (string) $sessie['status'];
        q('UPDATE betalingen SET status = ?, bijgewerkt_op = ? WHERE id = ?', [$status, nu(), $lokaal['id']]);
        if ($setup && $setup['status'] === 'succeeded') {
            verwerk_machtiging($setup, (int) $lokaal['gebruiker_id']);
        } elseif ($sessie['status'] === 'expired' || $status === 'canceled') {
            q("UPDATE gebruikers SET mandaat_status = 'geen' WHERE id = ? AND mandaat_status = 'in_behandeling'", [$lokaal['gebruiker_id']]);
        }
        return;
    }

    $betaling = $sessie['payment_intent'] ?? null;
    $betalingId = is_array($betaling) ? (string) $betaling['id'] : (string) $betaling;
    if ($betalingId !== '') {
        q('UPDATE betalingen SET payment_intent = ?, bijgewerkt_op = ? WHERE id = ?', [$betalingId, nu(), $lokaal['id']]);
        verwerk_payment_intent($betalingId);
    } elseif ($sessie['status'] === 'expired') {
        q("UPDATE betalingen SET status = 'expired', bijgewerkt_op = ? WHERE id = ?", [nu(), $lokaal['id']]);
    }
}

/** Een gelukte machtiging vastleggen: de herbruikbare SEPA-betaalmethode, de laatste cijfers van het IBAN en de naam. */
function verwerk_machtiging(array $setup, int $gebruikerId): void
{
    $methode = is_array($setup['payment_method'] ?? null) ? $setup['payment_method'] : null;
    $type = (string) ($methode['type'] ?? '');
    $details = $setup['latest_attempt']['payment_method_details'][$type] ?? [];

    if (!empty($details['generated_sepa_debit'])) {
        // iDEAL | Wero (of Bancontact): Stripe maakt er een SEPA-machtiging van
        $sepaId = is_array($details['generated_sepa_debit']) ? (string) $details['generated_sepa_debit']['id'] : (string) $details['generated_sepa_debit'];
        $sepa = stripe('GET', 'payment_methods/' . rawurlencode($sepaId));
        $naam = (string) ($details['verified_name'] ?? '');
    } elseif ($type === 'sepa_debit') {
        $sepa = $methode;
        $naam = '';
    } else {
        q("UPDATE gebruikers SET mandaat_status = 'geen' WHERE id = ? AND mandaat_status = 'in_behandeling'", [$gebruikerId]);
        log_actie('Machtiging niet bruikbaar', 'Betaalmethode ' . ($type ?: 'onbekend') . ' is geen SEPA-incasso', $gebruikerId);
        return;
    }
    $naam = $naam !== '' ? $naam : (string) ($sepa['billing_details']['name'] ?? '');
    $huidig = rij('SELECT mandaat_id, mandaat_status FROM gebruikers WHERE id = ?', [$gebruikerId]);
    if ($huidig && $huidig['mandaat_id'] === $sepa['id'] && $huidig['mandaat_status'] === 'geldig') {
        return; // Al verwerkt (de webhook en de terugkeerpagina komen allebei langs)
    }
    q("UPDATE gebruikers SET mandaat_id = ?, mandaat_status = 'geldig', mandaat_rekening = ?, mandaat_naam = ?, mandaat_datum = ? WHERE id = ?", [
        $sepa['id'],
        iban_einde((string) ($sepa['sepa_debit']['country'] ?? ''), (string) ($sepa['sepa_debit']['last4'] ?? '')),
        $naam,
        vandaag(),
        $gebruikerId,
    ]);
    log_actie('Machtiging geldig', 'Stripe ' . $sepa['id'], $gebruikerId);
}

/** "NL** **** **** 4300": alleen het land en de laatste vier cijfers; meer slaan we niet op. */
function iban_einde(string $land, string $laatste4): string
{
    return ($land !== '' ? strtoupper($land) : '??') . '** **** **** ' . ($laatste4 !== '' ? $laatste4 : '****');
}

/** Haalt een betaling (PaymentIntent) op en zet de factuur op betaald of mislukt. */
function verwerk_payment_intent(string $betalingId): void
{
    $lokaal = rij('SELECT * FROM betalingen WHERE extern_id = ? OR payment_intent = ? ORDER BY id DESC LIMIT 1', [$betalingId, $betalingId]);
    if (!$lokaal || !$lokaal['factuur_id']) {
        return;
    }
    $betaling = stripe('GET', 'payment_intents/' . rawurlencode($betalingId), ['expand' => ['latest_charge']]);
    $status = (string) $betaling['status'];
    $charge = is_array($betaling['latest_charge'] ?? null) ? $betaling['latest_charge'] : [];
    $teruggeboekt = !empty($charge['disputed']);   // de ouder liet de afschrijving terugboeken (storno)
    $terugbetaald = !empty($charge['refunded']);   // helemaal terugbetaald, door jullie zelf
    q('UPDATE betalingen SET status = ?, bijgewerkt_op = ? WHERE id = ?', [
        $teruggeboekt ? 'teruggeboekt' : ($terugbetaald ? 'terugbetaald' : $status), nu(), $lokaal['id'],
    ]);

    if ($teruggeboekt || ($lokaal['soort'] === 'incasso' && in_array($status, ['requires_payment_method', 'canceled'], true))) {
        // Mislukt of teruggeboekt. Een losse betaling die de ouder afbreekt, verandert een open incasso niet.
        $gewijzigd = q("UPDATE facturen SET status = 'mislukt' WHERE id = ? AND status IN ('incasso', 'betaald')", [$lokaal['factuur_id']])->rowCount();
        $factuur = rij('SELECT * FROM facturen WHERE id = ?', [$lokaal['factuur_id']]);
        if ($gewijzigd && $factuur) {
            log_actie($teruggeboekt ? 'Incasso teruggeboekt' : 'Incasso mislukt', 'Factuur ' . $factuur['nummer'] . ', Stripe ' . $betalingId);
            systeembericht((int) $factuur['ouder_id'], 'Het lukte niet om factuur ' . $factuur['nummer'] . ' automatisch af te schrijven. Je kunt de factuur online betalen via "Facturen", of neem contact met ons op.');
        }
    } elseif ($status === 'succeeded' && !$terugbetaald) {
        q("UPDATE facturen SET status = 'betaald', betaald_op = COALESCE(betaald_op, ?) WHERE id = ? AND status NOT IN ('betaald', 'geannuleerd')", [nu(), $lokaal['factuur_id']]);
    } elseif ($terugbetaald) {
        log_actie('Betaling terugbetaald', 'Factuur ' . $lokaal['factuur_id'] . ', Stripe ' . $betalingId . '. Pas de factuur zelf aan als dat nodig is.');
    }
}

/** Een machtiging die de ouder bij de bank (of jullie bij Stripe) heeft ingetrokken. */
function verwerk_mandaat(string $mandaatId): void
{
    $mandaat = stripe('GET', 'mandates/' . rawurlencode($mandaatId));
    $methode = is_array($mandaat['payment_method'] ?? null) ? $mandaat['payment_method']['id'] : (string) ($mandaat['payment_method'] ?? '');
    if (($mandaat['status'] ?? '') !== 'inactive' || $methode === '') {
        return;
    }
    $ouder = rij("SELECT * FROM gebruikers WHERE mandaat_id = ? AND mandaat_status = 'geldig'", [$methode]);
    if (!$ouder) {
        return;
    }
    q("UPDATE gebruikers SET mandaat_status = 'ingetrokken' WHERE id = ?", [$ouder['id']]);
    log_actie('Machtiging ingetrokken', 'Bij de bank of bij Stripe (' . $mandaatId . ')', (int) $ouder['id']);
    melding_voor_team((int) $ouder['id'], 'De machtiging voor automatische incasso van ' . $ouder['naam'] . ' is ingetrokken (bij de bank of bij Stripe). Er worden geen incasso\'s meer gestart; de ouder kan een nieuwe machtiging afgeven.');
}

/** Machtiging intrekken vanuit Mijn BSO: ook bij Stripe, zodat er niets meer kan worden afgeschreven. */
function trek_machtiging_in(array $ouder): void
{
    if (stripe_actief() && str_starts_with((string) $ouder['mandaat_id'], 'pm_')) {
        stripe('POST', 'payment_methods/' . rawurlencode((string) $ouder['mandaat_id']) . '/detach');
    }
    q("UPDATE gebruikers SET mandaat_status = 'ingetrokken' WHERE id = ?", [$ouder['id']]);
}

/** Een melding van Stripe (al gecontroleerd op de handtekening). */
function verwerk_stripe_event(array $event): void
{
    $type = (string) ($event['type'] ?? '');
    $object = $event['data']['object'] ?? [];
    if (!in_array($type, STRIPE_EVENTS, true) || !is_array($object)) {
        return;
    }
    $id = (string) ($object['id'] ?? '');
    if (str_starts_with($type, 'checkout.session.') && preg_match('/^cs_[A-Za-z0-9_]+$/', $id)) {
        verwerk_checkout_sessie($id);
    } elseif (str_starts_with($type, 'payment_intent.') && preg_match('/^pi_[A-Za-z0-9_]+$/', $id)) {
        verwerk_payment_intent($id);
    } elseif (str_starts_with($type, 'charge.')) {
        $betaling = $object['payment_intent'] ?? '';
        $betaling = is_array($betaling) ? (string) ($betaling['id'] ?? '') : (string) $betaling;
        if (preg_match('/^pi_[A-Za-z0-9_]+$/', $betaling)) {
            verwerk_payment_intent($betaling);
        }
    } elseif ($type === 'mandate.updated' && preg_match('/^mandate_[A-Za-z0-9_]+$/', $id)) {
        verwerk_mandaat($id);
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
