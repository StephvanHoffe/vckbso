<?php
/* Download van een export bij een privacyverzoek. Alleen voor de aanvrager (na afronden) en wie privacyverzoeken afhandelt. */
require __DIR__ . '/inc/bootstrap.php';

$gebruiker = vereis_login();
if (is_team($gebruiker)) {
    vereis_recht('verzoeken');
}
$verzoek = rij('SELECT * FROM avg_verzoeken WHERE id = ?', [get_int('verzoek')]);
if (!$verzoek || !$verzoek['export_bestand'] || $verzoek['export_verloopt_op'] < nu()) {
    niet_gevonden('Deze export bestaat niet (meer). Exports zijn ' . EXPORT_GELDIG_DAGEN . ' dagen beschikbaar.');
}
if (is_ouder($gebruiker)) {
    $magDownloaden = (int) $verzoek['gebruiker_id'] === (int) $gebruiker['id'] && $verzoek['status'] === 'afgerond'
        && (!$verzoek['kind_id'] || mag_namens_kind((int) $verzoek['kind_id'], (int) $gebruiker['id']));
    if (!$magDownloaden) {
        niet_gevonden();
    }
}
$inhoud = export_lees($verzoek['export_bestand']);
if ($inhoud === null) {
    niet_gevonden();
}
log_export('Export van privacyverzoek gedownload', $verzoek['kind_id'] ? 'kind:' . $verzoek['kind_id'] : 'gebruiker:' . $verzoek['gebruiker_id'], 'verzoek ' . $verzoek['id']);
stuur_download($inhoud, 'gegevens-' . preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($verzoek['over_naam'])) . '-' . date('Y-m-d') . '.json');
