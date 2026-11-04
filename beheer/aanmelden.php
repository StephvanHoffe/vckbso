<?php
/*
 * Aanmelden gaat sinds november 2026 via het formulier op de website (aanmelden.html, "Kom bij de club"):
 * vrijblijvend, in 7 stappen, verstuurd naar aanvraag.php. Het team activeert daarna het account en de
 * ouder krijgt een link om een wachtwoord te kiezen; de machtiging volgt in Mijn BSO (machtiging.php).
 * Oude links naar deze pagina komen zo bij het nieuwe formulier uit.
 */
require __DIR__ . '/inc/bootstrap.php';

if ($ingelogd = huidige_gebruiker()) {
    redirect(is_ouder($ingelogd) ? 'kinderen.php' : 'index.php');
}
redirect('../aanmelden.html');
