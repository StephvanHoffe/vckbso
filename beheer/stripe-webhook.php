<?php
/*
 * Stripe roept deze URL aan als er iets verandert aan een machtiging of betaling.
 * Stel hem in het dashboard van Stripe in als webhook (Ontwikkelaars → Webhooks), met de
 * gebeurtenissen uit STRIPE_EVENTS in inc/stripe.php, en zet het ondertekeningsgeheim (whsec_...)
 * in Mijn BSO onder Koppelingen. Zonder geldige handtekening doen we niets; met een geldige
 * handtekening halen we de actuele status alsnog zelf op bij Stripe.
 * Wanneer de laatste melding binnenkwam (of werd geweigerd), staat op de pagina Koppelingen.
 */
define('GEEN_SESSIE', true);
require __DIR__ . '/inc/bootstrap.php';

$inhoud = (string) file_get_contents('php://input');
if (!is_post() || !stripe_actief() || strlen($inhoud) > 1_000_000) {
    http_response_code(400);
    exit;
}
if (!stripe_handtekening_ok($inhoud, (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), (string) koppeling('stripe.webhook_geheim'))) {
    error_log('Stripe-webhook: ongeldige handtekening (controleer het webhookgeheim onder Koppelingen)');
    instelling_zet('stripe_webhook_geweigerd', nu());
    http_response_code(400);
    exit;
}
instelling_zet('stripe_webhook_ontvangen', nu());

$event = json_decode($inhoud, true);
if (!is_array($event)) {
    http_response_code(400);
    exit;
}
try {
    verwerk_stripe_event($event);
    http_response_code(200);
} catch (Throwable $fout) {
    error_log('Stripe-webhook ' . ($event['id'] ?? '') . ': ' . $fout->getMessage());
    http_response_code(500); // Stripe probeert het later opnieuw
}
