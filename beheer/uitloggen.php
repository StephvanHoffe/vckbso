<?php
require __DIR__ . '/inc/bootstrap.php';

if (is_post()) {
    csrf_controleer();
    if (huidige_gebruiker()) {
        log_actie('Uitgelogd');
    }
    log_uit();
    flash('succes', 'Je bent uitgelogd. Tot ziens!');
}
redirect('inloggen.php');
