<?php
/* Opmaak van de pagina's: kop met menu, meldingen en voet. */

declare(strict_types=1);

function icoon(string $naam, string $extraKlasse = ''): string
{
    return '<svg class="icon' . ($extraKlasse ? ' ' . e($extraKlasse) : '') . '" aria-hidden="true" focusable="false"><use href="#i-' . e($naam) . '"></use></svg>';
}

/** Menu-items per rol: [bestand, label, icoon]. */
function menu_items(array $gebruiker): array
{
    if ($gebruiker['rol'] === 'ouder') {
        return [
            ['index.php', 'Overzicht', 'home'],
            ['agenda.php', 'Agenda', 'calendar'],
            ['berichten.php', 'Berichten', 'chat'],
            ['fotos.php', "Foto's", 'camera'],
            ['kinderen.php', 'Mijn kinderen', 'smile'],
            ['facturen.php', 'Facturen', 'euro'],
            ['privacy.php', 'Privacy', 'shield'],
        ];
    }
    // Team: alleen de onderdelen die voor deze persoon aan staan
    return array_values(array_map(fn ($o) => [$o[0], $o[1], $o[2]], onderdelen_van($gebruiker)));
}

/**
 * Begin van een pagina.
 * $opties['publiek'] = true voor pagina's zonder menu (inloggen, aanmelden).
 */
function pagina_begin(string $titel, string $actief = '', array $opties = []): void
{
    $gebruiker = empty($opties['publiek']) ? huidige_gebruiker() : null;
    $ongelezen = 0;
    if ($gebruiker) {
        $ongelezen = is_team($gebruiker) ? ongelezen_voor_team() : ongelezen_voor_ouder((int) $gebruiker['id']);
    }
    $omgeving = $gebruiker && is_ouder($gebruiker) ? 'Mijn BSO' : 'Beheer';
    ?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titel) ?> · <?= e($omgeving) ?> · Sporty</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#c8553d">
<link rel="icon" href="../favicon.ico" sizes="32x32">
<link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">
<link rel="preload" href="../assets/fonts/manrope-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="../design/tokens.css">
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="assets/beheer.css">
<?php foreach ($opties['scripts'] ?? [] as $script): ?>
<script src="<?= e($script) ?>" defer></script>
<?php endforeach; ?>
<script src="assets/beheer.js" defer></script>
</head>
<body class="beheer<?= $gebruiker ? '' : ' beheer--publiek' ?>">
<?= icoon_sprite() ?>
<a class="skip-link" href="#inhoud">Naar de inhoud</a>
<header class="app-header">
  <div class="app-header__inner">
    <a class="logo" href="<?= $gebruiker ? 'index.php' : '../index.html' ?>" aria-label="Sporty, <?= $gebruiker ? 'naar het overzicht' : 'naar de website' ?>"><svg class="logo__mark" viewBox="0 0 40 40" aria-hidden="true" focusable="false"><circle cx="18" cy="22" r="16" style="fill: var(--color-primary)"/><path d="M10.5 23.5a7.5 7.5 0 0 0 15 0" fill="none" stroke-width="3.4" stroke-linecap="round" style="stroke: var(--color-surface)"/><circle cx="33" cy="8" r="5.5" style="fill: var(--color-sun)"/></svg><span aria-hidden="true">Sport<span class="logo__accent">y</span></span><span class="app-header__omgeving"><?= e($omgeving) ?></span></a>
<?php if ($gebruiker): ?>
    <ul class="app-account">
      <li><a href="profiel.php"<?= $actief === 'profiel.php' ? ' aria-current="page"' : '' ?>><?= icoon('user') ?><span class="app-account__label"><?= e(explode(' ', $gebruiker['naam'])[0]) ?><span class="visually-hidden">, mijn gegevens</span></span></a></li>
      <li><form method="post" action="uitloggen.php"><?= csrf_veld() ?><button type="submit" class="link-button"><?= icoon('logout') ?><span class="app-account__label">Uitloggen</span></button></form></li>
    </ul>
  </div>
  <nav class="app-nav" aria-label="Menu">
    <ul>
<?php foreach (menu_items($gebruiker) as [$bestand, $label, $ico]): ?>
      <li><a href="<?= e($bestand) ?>"<?= $actief === $bestand ? ' aria-current="page"' : '' ?>><?= icoon($ico) ?><?= e($label) ?><?php if ($bestand === 'berichten.php' && $ongelezen > 0): ?> <span class="teller"><?= $ongelezen ?><span class="visually-hidden"> ongelezen</span></span><?php endif; ?><?php if ($bestand === 'verzoeken.php' && ($open = open_verzoeken()) > 0): ?> <span class="teller"><?= $open ?><span class="visually-hidden"> open</span></span><?php endif; ?></a></li>
<?php endforeach; ?>
    </ul>
  </nav>
<?php else: ?>
    <a class="link-arrow app-header__terug" href="../index.html">Naar de website<?= icoon('arrow') ?></a>
  </div>
<?php endif; ?>
</header>
<main id="inhoud" class="app-main">
<?php foreach (haal_flashes() as $melding): ?>
  <div class="melding melding--<?= e($melding['soort']) ?>" role="<?= $melding['soort'] === 'fout' ? 'alert' : 'status' ?>"><?= icoon($melding['soort'] === 'fout' ? 'alert' : ($melding['soort'] === 'succes' ? 'check' : 'info')) ?><p><?= e($melding['tekst']) ?></p></div>
<?php endforeach; ?>
<?php if (!mollie_actief() && $gebruiker && (team_mag('facturen', $gebruiker) || team_mag('instellingen', $gebruiker)) && empty($opties['geen_demo_melding'])): ?>
  <p class="demo-balk"><?= icoon('info') ?>Demo-modus: Mollie is nog niet gekoppeld, er wordt niets echt afgeschreven.</p>
<?php endif; ?>
<?php
}

function pagina_einde(): void
{
    ?>
</main>
<footer class="app-footer">
  <p>Sporty · <?= e(instelling('telefoon', '06 12 34 56 78')) ?> · <a href="mailto:<?= e(instelling('email', 'info@sporty.nl')) ?>"><?= e(instelling('email', 'info@sporty.nl')) ?></a> · <a href="../privacy.html">Privacyverklaring</a></p>
</footer>
</body>
</html>
<?php
}

function open_verzoeken(): int
{
    return (int) waarde("SELECT COUNT(*) FROM avg_verzoeken WHERE status IN ('ontvangen', 'in_behandeling')");
}

/** Kop van een pagina met titel en eventueel knoppen rechts. */
function pagina_kop(string $titel, string $intro = '', string $acties = ''): void
{
    echo '<div class="pagina-kop"><div><h1>' . e($titel) . '</h1>';
    if ($intro !== '') {
        echo '<p class="lead">' . $intro . '</p>';
    }
    echo '</div>';
    if ($acties !== '') {
        echo '<div class="btn-group">' . $acties . '</div>';
    }
    echo '</div>';
}

/** Foutmelding onder een formulierveld. */
function veldfout(array $fouten, string $veld): string
{
    if (empty($fouten[$veld])) {
        return '';
    }
    return '<p class="field__error" id="' . e($veld) . '-fout">' . icoon('alert') . '<span>' . e($fouten[$veld]) . '</span></p>';
}

function aria_fout(array $fouten, string $veld): string
{
    return empty($fouten[$veld]) ? '' : ' aria-invalid="true" aria-describedby="' . e($veld) . '-fout"';
}

function foutensamenvatting(array $fouten): string
{
    if (!$fouten) {
        return '';
    }
    $html = '<div class="melding melding--fout" role="alert" tabindex="-1" data-focus>' . icoon('alert') . '<div><p><strong>Er ging iets mis. Controleer de velden hieronder:</strong></p><ul>';
    foreach ($fouten as $fout) {
        $html .= '<li>' . e($fout) . '</li>';
    }
    return $html . '</ul></div></div>';
}

function icoon_sprite(): string
{
    return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" hidden><symbol id="i-home" viewBox="0 0 24 24"><path d="M4 11 12 4l8 7v8a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1z"/></symbol><symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="15" rx="3"/><path d="M3.5 10h17M8 3v4M16 3v4"/></symbol><symbol id="i-chat" viewBox="0 0 24 24"><path d="M5 4h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-8l-5 4v-4H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><path d="M8 9h8M8 12.5h5"/></symbol><symbol id="i-camera" viewBox="0 0 24 24"><path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.5"/></symbol><symbol id="i-smile" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8.5 14a4.5 4.5 0 0 0 7 0M9 9.5h.01M15 9.5h.01"/></symbol><symbol id="i-euro" viewBox="0 0 24 24"><path d="M17.5 6.5a7 7 0 1 0 0 11"/><path d="M4 10h9M4 14h9"/></symbol><symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.6a3.5 3.5 0 0 1 0 6.8M21.5 20a6.5 6.5 0 0 0-4-6"/></symbol><symbol id="i-grid" viewBox="0 0 24 24"><rect x="4" y="4" width="7" height="7" rx="2"/><rect x="13" y="4" width="7" height="7" rx="2"/><rect x="4" y="13" width="7" height="7" rx="2"/><rect x="13" y="13" width="7" height="7" rx="2"/></symbol><symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3 5 6v5.5c0 4.4 3 7.8 7 9.5 4-1.7 7-5.1 7-9.5V6z"/><path d="m9 12 2 2 4-4"/></symbol><symbol id="i-gear" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3M5.3 5.3l2.1 2.1M16.6 16.6l2.1 2.1M5.3 18.7l2.1-2.1M16.6 7.4l2.1-2.1"/></symbol><symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></symbol><symbol id="i-logout" viewBox="0 0 24 24"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H4"/></symbol><symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol><symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></symbol><symbol id="i-back" viewBox="0 0 24 24"><path d="M19 12H5M11 6l-6 6 6 6"/></symbol><symbol id="i-alert" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/></symbol><symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></symbol><symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5"/></symbol><symbol id="i-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></symbol><symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol><symbol id="i-minus" viewBox="0 0 24 24"><path d="M5 12h14"/></symbol><symbol id="i-send" viewBox="0 0 24 24"><path d="m4 12 16-8-5 17-3-7z"/><path d="m12 14 8-10"/></symbol><symbol id="i-heart" viewBox="0 0 24 24"><path d="M12 20s-7.5-4.6-7.5-10.1A4.4 4.4 0 0 1 12 7.2a4.4 4.4 0 0 1 7.5 2.7C19.5 15.4 12 20 12 20z"/></symbol><symbol id="i-thermo" viewBox="0 0 24 24"><path d="M10 13.5V5a2 2 0 0 1 4 0v8.5a4 4 0 1 1-4 0z"/><path d="M12 9v6"/></symbol><symbol id="i-book" viewBox="0 0 24 24"><path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5M8 7h7"/></symbol><symbol id="i-phone" viewBox="0 0 24 24"><path d="M5 3.5h3.2l1.6 4-2 1.3a11 11 0 0 0 5.4 5.4l1.3-2 4 1.6V17a3 3 0 0 1-3 3A15.5 15.5 0 0 1 2 6.5a3 3 0 0 1 3-3z"/></symbol><symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></symbol><symbol id="i-print" viewBox="0 0 24 24"><path d="M7 9V4h10v5M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/><rect x="7" y="14" width="10" height="7" rx="1"/></symbol><symbol id="i-download" viewBox="0 0 24 24"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></symbol><symbol id="i-trash" viewBox="0 0 24 24"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></symbol><symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol><symbol id="i-chart" viewBox="0 0 24 24"><path d="M4 20h16"/><rect x="5.5" y="11" width="3.5" height="6.5" rx="1"/><rect x="10.25" y="5.5" width="3.5" height="12" rx="1"/><rect x="15" y="13.5" width="3.5" height="4" rx="1"/></symbol><symbol id="i-key" viewBox="0 0 24 24"><circle cx="8" cy="15" r="4.5"/><path d="m11.2 11.8 8.3-8.3M16.5 6.5l3 3M14 9l2 2"/></symbol><symbol id="i-clip" viewBox="0 0 24 24"><path d="m20 11.5-8.2 8.2a5 5 0 0 1-7-7L13.2 4.3a3.4 3.4 0 0 1 4.8 4.8l-8.4 8.4a1.8 1.8 0 0 1-2.5-2.5l7.7-7.7"/></symbol><symbol id="i-bank" viewBox="0 0 24 24"><path d="M3 10 12 4l9 6M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 20h18"/></symbol></svg>
SVG;
}
