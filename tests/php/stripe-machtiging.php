<?php
/*
 * Hulpscript voor tests/stripe-machtiging.test.mjs: start een machtiging tegen de nagebootste Stripe
 * (STRIPE_API_ADRES) en geeft terug wat er gebeurde.
 */
declare(strict_types=1);

define('STRIPE_API', getenv('STRIPE_API_ADRES'));
require __DIR__ . '/../../beheer/inc/bootstrap.php';

koppeling_zet('stripe.geheime_sleutel', 'sk_test_' . str_repeat('a', 24), 1);
koppelingen_opgeslagen(true);
q("INSERT INTO gebruikers (rol, status, naam, email, stripe_klant_id, aangemaakt_op) VALUES ('ouder', 'nieuw', 'Sam Test', 'sam@test.invalid', 'cus_oud', ?)", [nu()]);
$ouder = rij('SELECT * FROM gebruikers WHERE id = ?', [laatste_id()]);

$uit = [];
$uit['url'] = start_machtiging($ouder);
$uit['klant_na'] = waarde('SELECT stripe_klant_id FROM gebruikers WHERE id = ?', [$ouder['id']]);
$uit['status_na'] = waarde('SELECT mandaat_status FROM gebruikers WHERE id = ?', [$ouder['id']]);
$uit['waarschuwing_na_gelukt'] = stripe_waarschuwing();

// iDEAL staat niet aan in het dashboard van Stripe
file_put_contents(getenv('STRIPE_MAP') . '/scenario', 'methode_uit');
sleep(1); // de fout moet later zijn dan de laatste geslaagde machtiging
try {
    start_machtiging(rij('SELECT * FROM gebruikers WHERE id = ?', [$ouder['id']]));
    $uit['fout'] = null;
} catch (RuntimeException $fout) {
    $uit['fout'] = $fout->getMessage();
    stripe_fout_vastleggen('Machtiging starten', $fout->getMessage());
}
$uit['uitleg'] = stripe_fout_uitleg((string) $uit['fout']);
$uit['waarschuwing'] = stripe_waarschuwing();
echo json_encode($uit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
