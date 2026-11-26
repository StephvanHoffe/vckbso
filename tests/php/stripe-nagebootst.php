<?php
/*
 * Nagebootste Stripe-API voor tests/stripe-machtiging.test.mjs (router voor php -S).
 * Legt elk verzoek vast in $STRIPE_MAP/verzoeken.jsonl en antwoordt zoals Stripe:
 * - zonder Stripe-Version kent "de nieuwste versie" payment_method_types niet (zo ging het mis in november 2026);
 * - een onbekende klant (cus_oud) geeft "No such customer";
 * - met scenario "methode_uit" staat iDEAL niet aan in het dashboard.
 */
$map = getenv('STRIPE_MAP');
$pad = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$body = file_get_contents('php://input');
parse_str($body, $velden);
$versie = $_SERVER['HTTP_STRIPE_VERSION'] ?? '';
file_put_contents($map . '/verzoeken.jsonl', json_encode(['methode' => $_SERVER['REQUEST_METHOD'], 'pad' => $pad, 'versie' => $versie, 'sleutel' => $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '', 'velden' => $velden]) . "\n", FILE_APPEND);
$scenario = is_file($map . '/scenario') ? trim(file_get_contents($map . '/scenario')) : '';
header('Content-Type: application/json');
$fout = function (string $melding, int $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => ['message' => $melding, 'type' => 'invalid_request_error']]);
    exit;
};
if ($pad === '/v1/customers' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    echo json_encode(['id' => 'cus_nieuw']);
    exit;
}
if ($pad === '/v1/checkout/sessions') {
    if ($versie === '' && isset($velden['payment_method_types'])) {
        $fout('Received unknown parameter: payment_method_types');
    }
    if (($velden['customer'] ?? '') === 'cus_oud') {
        $fout("No such customer: 'cus_oud'; a similar object exists in test mode, but a live mode key was used to make this request.");
    }
    if ($scenario === 'methode_uit') {
        $fout('The payment method type "ideal" is invalid. Please ensure the provided type is activated in your dashboard (https://dashboard.stripe.com/account/payments/settings) and your account is enabled for any preview features that you are trying to use.');
    }
    echo json_encode(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']);
    exit;
}
if (preg_match('#^/v1/checkout/sessions/[^/]+/expire$#', $pad)) {
    echo json_encode(['id' => 'cs_test_1', 'status' => 'expired']);
    exit;
}
$fout('Onbekend pad in de nagebootste Stripe: ' . $pad, 404);
