<?php
/*
 * Mollie roept deze URL aan als de status van een betaling verandert.
 * Mollie stuurt alleen het ID mee; de echte status halen we zelf op bij Mollie,
 * dus een nagemaakte aanroep kan niets veranderen.
 */
define('GEEN_SESSIE', true);
require __DIR__ . '/inc/bootstrap.php';

$id = (string) ($_POST['id'] ?? '');
if (!is_post() || !preg_match('/^tr_[A-Za-z0-9]+$/', $id) || !mollie_actief()) {
    http_response_code(400);
    exit;
}

try {
    verwerk_betaling($id);
    http_response_code(200);
} catch (Throwable $fout) {
    error_log('Mollie-webhook ' . $id . ': ' . $fout->getMessage());
    http_response_code(500); // Mollie probeert het later opnieuw
}
