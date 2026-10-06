#!/usr/bin/env node
/**
 * Bouwt de voorbeeldweergave: een doorklikbare momentopname van de beheeromgeving (Mijn BSO)
 * per rol, met verzonnen gegevens, plus een overzichtspagina. Bedoeld voor statische hosting
 * zoals Vercel, waar de echte beheeromgeving (PHP) niet draait.
 *
 * Gebruik (vanuit de hoofdmap van het project, PHP 8.2+ en Node 20+ nodig):
 *   npm run voorbeeld                     schrijft de map voorbeeld/ (overzicht: voorbeeld/index.html)
 *   npm run voorbeeld -- --bundel <map>   maakt daarnaast een zelfstandige bundel met een kopie van de website
 *
 * Het script maakt een tijdelijke demo-omgeving (tools/beheer-demodata.php en verrijk.php),
 * start daarvoor een eigen PHP-server en ruimt alles daarna weer op. De echte gegevens worden
 * nooit gebruikt. Draai het opnieuw na wijzigingen in de beheeromgeving of de website.
 */
import { execFileSync, spawn } from "node:child_process";
import { copyFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, rmSync, statSync, writeFileSync } from "node:fs";
import { createServer } from "node:net";
import { tmpdir } from "node:os";
import { dirname, join, normalize, relative } from "node:path";
import { fileURLToPath } from "node:url";

const REPO = fileURLToPath(new URL("../../", import.meta.url));
const HIER = fileURLToPath(new URL("./", import.meta.url));
const DOEL = join(REPO, "voorbeeld/");
const bundelIndex = process.argv.indexOf("--bundel");
const BUNDEL = bundelIndex > -1 ? join(process.argv[bundelIndex + 1], "/") : null;
const VANDAAG = new Date().toISOString().slice(0, 10);

const schrijf = (pad, inhoud) => { mkdirSync(dirname(pad), { recursive: true }); writeFileSync(pad, inhoud); };
const kopieer = (van, naar) => { mkdirSync(dirname(naar), { recursive: true }); copyFileSync(van, naar); };

// ---------- Tijdelijke demo-omgeving met eigen PHP-server ----------
const vrijePoort = () => new Promise((klaar) => {
  const server = createServer().listen(0, "127.0.0.1", () => { const { port } = server.address(); server.close(() => klaar(port)); });
});
const POORT = await vrijePoort();
const BASIS = `http://127.0.0.1:${POORT}/beheer/`;
const TMP = mkdtempSync(join(tmpdir(), "sporty-voorbeeld-"));
const DATA = join(TMP, "data");
const CONFIG = join(TMP, "config.php");
writeFileSync(CONFIG, `<?php\nreturn ['data_dir' => ${JSON.stringify(DATA)}, 'ontwikkelmodus' => true, 'mail' => ['methode' => 'log'], 'app_url' => 'http://127.0.0.1:${POORT}/beheer'];\n`);
const env = { ...process.env, VCK_BEHEER_CONFIG: CONFIG };
const php = (...args) => execFileSync("php", args, { cwd: REPO, env, stdio: ["ignore", "pipe", "inherit"] }).toString().trim();

let server;
const opruimen = () => { server?.kill(); rmSync(TMP, { recursive: true, force: true }); };
process.on("exit", opruimen);
process.on("SIGINT", () => process.exit(130));
process.on("SIGTERM", () => process.exit(143));

console.log(php("tools/beheer-demodata.php"));
console.log(php("tools/voorbeeld/verrijk.php"));
server = spawn("php", ["-d", "display_errors=0", "-S", `127.0.0.1:${POORT}`, "-t", REPO], { cwd: REPO, env, stdio: "ignore" });
for (let poging = 0; ; poging++) {
  try { await fetch(BASIS + "inloggen.php"); break; } catch { if (poging > 50) throw new Error("De PHP-server start niet"); await new Promise((r) => setTimeout(r, 100)); }
}

// ---------- Rollen en sessies ----------
const ROLLEN = {
  beheerder: { id: 1, seeds: ["index.php", "agenda.php", `agenda.php?dag=${VANDAAG}`, "kinderen.php", "ouders.php", "berichten.php", "fotos.php", "groepen.php", "facturen.php", "financien.php", "medewerkers.php", "verzoeken.php", "instellingen.php", "beveiliging.php", "logboek.php", "profiel.php", "tweestaps.php"] },
  begeleider: { id: 2, seeds: ["index.php", "agenda.php", `agenda.php?dag=${VANDAAG}`, "kinderen.php", "ouders.php", "berichten.php", "fotos.php", "profiel.php"] },
  ouder: { id: 3, seeds: ["index.php", "agenda.php", "berichten.php", "fotos.php", "kinderen.php", "facturen.php", "privacy.php", "profiel.php", "tweestaps.php"] },
};
const PUBLIEK = ["inloggen.php", "aanmelden.php", "wachtwoord-vergeten.php"];
mkdirSync(join(DATA, "sessies"), { recursive: true });
for (const [rol, r] of Object.entries(ROLLEN)) {
  const sid = ("voorbeeld" + rol).padEnd(32, "0");
  r.cookie = `vck_standaard=${sid}`;
  writeFileSync(join(DATA, "sessies", `sess_${sid}`), `gebruiker_id|i:${r.id};organisatie|s:9:"standaard";laatst_actief|i:${Math.floor(Date.now() / 1000)};`);
}

// Niet in de momentopname: acties, downloads, exports
const OVERSLAAN = /^(uitloggen|export|agenda-actie|mollie-webhook|machtiging|installeren|inloggen-code|wachtwoord-instellen|foto)\.php|csv=|export=|download=|bijlage=|bewerk=/;
// Detailpagina's die ook twee klikken diep worden meegenomen
const DETAIL = /^((kind|ouder|factuur|verzoeken)\.php\?id=\d+|berichten\.php\?ouder=\d+|logboek\.php\?onderwerp=[a-z]+%3A\d+)$/;
// Deze week en deze maand zijn gelijk aan de standaardweergave van de agenda
const maandag = new Date(VANDAAG); maandag.setDate(maandag.getDate() - ((maandag.getDay() + 6) % 7));
const ALIASSEN = { [`agenda.php?week=${maandag.toISOString().slice(0, 10)}`]: "agenda.php", [`agenda.php?maand=${VANDAAG.slice(0, 7)}`]: "agenda.php" };

const bestandsnaam = (rol, url) => {
  const [pad, query = ""] = url.split("?");
  const extra = query.replace(/[^a-z0-9]+/gi, "-").replace(/^-|-$/g, "").toLowerCase();
  return `${rol ? rol + "-" : ""}${pad.replace(/\.php$/, "")}${extra ? "-" + extra : ""}.html`;
};
const normaliseer = (href) => {
  const [zonderHash, hash = ""] = href.split("#");
  return { url: zonderHash.replace(/&amp;/g, "&"), hash: hash ? "#" + hash : "" };
};
async function haal(url, cookie) {
  const antwoord = await fetch(BASIS + url, { headers: cookie ? { cookie } : {}, redirect: "manual" });
  return antwoord.status === 200 ? await antwoord.text() : null;
}

// ---------- Crawlen ----------
const paginas = {}; // rol -> Map(url -> html)
for (const [rol, r] of Object.entries(ROLLEN)) {
  const gezien = new Map();
  const rij = r.seeds.map((u) => [u, 0]);
  while (rij.length && gezien.size < 140) {
    const [url, diepte] = rij.shift();
    if (gezien.has(url) || OVERSLAAN.test(url)) continue;
    const html = await haal(url, r.cookie);
    if (!html) { console.log(`  (${rol}) overgeslagen: ${url}`); continue; }
    gezien.set(url, html);
    if (diepte >= 2) continue;
    for (const [, href] of html.matchAll(/href="([a-z-]+\.php(?:\?[^"#]*)?)(?:#[^"]*)?"/g)) {
      const { url: volgende } = normaliseer(href);
      if (diepte === 1 && !DETAIL.test(volgende)) continue;
      if (!gezien.has(volgende) && !OVERSLAAN.test(volgende) && !PUBLIEK.includes(volgende)) rij.push([volgende, diepte + 1]);
    }
  }
  paginas[rol] = gezien;
  console.log(`${rol}: ${gezien.size} pagina's`);
}
paginas[""] = new Map();
for (const url of PUBLIEK) paginas[""].set(url, await haal(url, null));

// Foto's die in de pagina's voorkomen
const fotos = new Map();
for (const map of Object.values(paginas)) for (const html of map.values()) for (const [, id, maat] of html.matchAll(/foto\.php\?id=(\d+)(?:&amp;maat=(klein))?/g)) {
  const sleutel = `foto-${id}-${maat || "groot"}.jpg`;
  if (fotos.has(sleutel)) continue;
  const antwoord = await fetch(`${BASIS}foto.php?id=${id}${maat ? "&maat=klein" : ""}`, { headers: { cookie: ROLLEN.beheerder.cookie } });
  if (antwoord.ok) fotos.set(sleutel, Buffer.from(await antwoord.arrayBuffer()));
}
console.log(`foto's: ${fotos.size}`);

// ---------- Voorbeeldbalk ----------
// vb: pad naar de map van het voorbeeld, site: pad naar de hoofdmap van de website, overzicht: bestandsnaam van de overzichtspagina
const balk = (rol, { vb, site, overzicht }) => {
  const knop = (r, label) => `<a href="${vb}beheer/${r}-index.html"${r === rol ? ' aria-current="page"' : ""}>${label}</a>`;
  return `<div class="vb-balk" data-rol="${rol}" role="region" aria-label="Voorbeeldweergave">
  <div class="vb-balk__binnen">
    <p class="vb-balk__tekst"><strong>Voorbeeld</strong> <span>Verzonnen gegevens. Formulieren en knoppen slaan niets op.</span></p>
    <nav class="vb-balk__rollen" aria-label="Bekijk als"><span>Bekijk als:</span>${knop("beheerder", "Beheerder")}${knop("begeleider", "Begeleider")}${knop("ouder", "Ouder")}<a href="${site}index.html"${rol === "website" ? ' aria-current="page"' : ""}>Website</a><a href="${vb}${overzicht}">Overzicht</a></nav>
  </div>
</div>
<div class="vb-melding" role="status" aria-live="polite" hidden></div>`;
};
// De balk komt na de link "Naar de inhoud", zodat die de eerste link op de pagina blijft
const injecteer = (html, rol, paden) => {
  html = html.replace(/<\/head>/, `<link rel="stylesheet" href="${paden.vb}beheer/voorbeeld.css">\n<script src="${paden.vb}beheer/voorbeeld.js" defer></script>\n<meta name="robots" content="noindex">\n</head>`);
  const skip = html.match(/<a class="skip-link"[^>]*>[^<]*<\/a>/);
  return skip ? html.replace(skip[0], `${skip[0]}\n${balk(rol, paden)}`) : html.replace(/(<body[^>]*>)/, `$1\n${balk(rol, paden)}`);
};

// ---------- Beheerpagina's herschrijven ----------
// site: pad van de beheerpagina naar de hoofdmap van de website
function herschrijfBeheer(rol, html, { site, overzicht }) {
  const map = paginas[rol];
  const doel = (href) => {
    let { url, hash } = normaliseer(href);
    url = ALIASSEN[url] ?? url;
    if (PUBLIEK.includes(url)) return bestandsnaam("", url) + hash;
    if (map.has(url)) return bestandsnaam(rol, url) + hash;
    const zonderQuery = url.split("?")[0];
    if (map.has(zonderQuery) && !url.includes("?")) return bestandsnaam(rol, zonderQuery) + hash;
    return null;
  };
  html = html.replace(/(<input type="hidden" name="csrf" value=")[^"]*"/g, '$1voorbeeld"');
  html = html.replace(/(src|href)="foto\.php\?id=(\d+)(?:&amp;maat=(klein))?"/g, (_, attr, id, maat) => `${attr}="fotos/foto-${id}-${maat || "groot"}.jpg"`);
  html = html.replace(/href="([a-z-]+\.php[^"]*)"/g, (heel, href) => {
    const nieuw = doel(href);
    return nieuw ? `href="${nieuw}"` : `href="#" data-vb-niet="${href}"`;
  });
  html = html.replace(/action="uitloggen\.php"/g, 'action="inloggen.html" data-vb-uitloggen');
  html = html.replace(/action="[a-z-]+\.php[^"]*"/g, 'action="#"');
  if (site !== "../") html = html.replace(/((?:href|src)=")\.\.\//g, `$1${site}`);
  return injecteer(html, rol || "publiek", { vb: "../", site, overzicht });
}

// Schrijft de momentopname naar <vbMap>beheer/
function schrijfMomentopname(vbMap, paden) {
  for (const [rol, map] of Object.entries(paginas)) {
    for (const [url, html] of map) schrijf(vbMap + "beheer/" + bestandsnaam(rol, url), herschrijfBeheer(rol, html, paden));
  }
  for (const [naam, data] of fotos) schrijf(vbMap + "beheer/fotos/" + naam, data);
  for (const bestand of ["assets/beheer.css", "assets/beheer.js", "assets/vendor/qrcode.js"]) kopieer(REPO + "beheer/" + bestand, vbMap + "beheer/" + bestand);
  kopieer(HIER + "voorbeeld.css", vbMap + "beheer/voorbeeld.css");
  kopieer(HIER + "voorbeeld.js", vbMap + "beheer/voorbeeld.js");
}

const start = readFileSync(HIER + "start.html", "utf8");
const overzichtspagina = (site, vb) => {
  const [kop, lijf] = start.replaceAll("{{SITE}}", site).replaceAll("{{VB}}", vb).split("<!--BODY-->");
  return `<!doctype html>\n<html lang="nl">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">\n<meta name="robots" content="noindex">\n${kop}</head>\n<body>\n${lijf}</body>\n</html>\n`;
};

// ---------- 1. voorbeeld/ in de repo (de website zelf staat ernaast) ----------
rmSync(DOEL, { recursive: true, force: true });
schrijfMomentopname(DOEL, { site: "../../", overzicht: "index.html" });
schrijf(DOEL + "index.html", overzichtspagina("../", ""));
controleerLinks(DOEL, REPO);
console.log(`voorbeeld/: ${tel(DOEL)} bestanden`);

// ---------- 2. Zelfstandige bundel met een kopie van de website (optioneel) ----------
if (BUNDEL) {
  const W = BUNDEL + "website/";
  rmSync(BUNDEL, { recursive: true, force: true });
  schrijfMomentopname(W, { site: "../", overzicht: "voorbeeld.html" });
  const siteBestanden = execFileSync("git", ["-C", REPO, "ls-files"]).toString().trim().split("\n")
    .filter((p) => !/^(beheer|tools|tests|docs|content|voorbeeld|node_modules)\/|^assets\/fotos\/bron\/|^package|README|CLAUDE|briefing|vercel\.json|\.gitignore|robots\.txt|sitemap\.xml|^404\.html|BRONNEN\.md|OFL\.txt/.test(p));
  for (const p of siteBestanden) {
    if (!p.endsWith(".html")) { kopieer(REPO + p, W + p); continue; }
    const prefix = p.includes("/") ? "../" : "";
    const html = readFileSync(REPO + p, "utf8").replace(/href="((?:\.\.\/)?)beheer\/([a-z-]+)\.php"/g, (_, terug, naam) => `href="${terug}beheer/${naam === "privacy" ? "ouder-privacy" : naam}.html"`);
    schrijf(W + p, injecteer(html, "website", { vb: prefix, site: prefix, overzicht: "voorbeeld.html" }));
  }
  schrijf(W + "voorbeeld.html", overzichtspagina("", ""));
  schrijf(BUNDEL + "start.html", overzichtspagina("website/", "website/"));
  controleerLinks(BUNDEL, BUNDEL);
  console.log(`bundel: ${tel(BUNDEL)} bestanden in ${BUNDEL}`);
}

// Klaar: de PHP-server stoppen en de tijdelijke demo-omgeving opruimen (via de exit-handler)
process.exit(0);

// ---------- Controle: elke lokale link en afbeelding bestaat ----------
function controleerLinks(map, wortel) {
  const kapot = [];
  for (const bestand of alleBestanden(map).filter((p) => p.endsWith(".html"))) {
    const html = readFileSync(bestand, "utf8");
    for (const [, attr, waarde] of html.matchAll(/(href|src|srcset)="([^"]*)"/g)) {
      const urls = attr === "srcset" ? waarde.split(",").map((d) => d.trim().split(" ")[0]) : [waarde];
      for (const url of urls) {
        if (!url || /^(https?:|mailto:|tel:|#|data:|javascript:)/.test(url)) continue;
        const pad = normalize(join(dirname(bestand), url.split("#")[0].split("?")[0]));
        if (!pad.startsWith(normalize(wortel)) || !existsSync(pad)) kapot.push(`${relative(wortel, bestand)} → ${url}`);
      }
    }
  }
  if (kapot.length) {
    console.error(`Kapotte links (${kapot.length}):\n  ` + kapot.slice(0, 30).join("\n  "));
    process.exit(1);
  }
}
function alleBestanden(map) {
  return readdirSync(map).flatMap((naam) => {
    const pad = join(map, naam);
    return statSync(pad).isDirectory() ? alleBestanden(pad) : [pad];
  });
}
function tel(map) {
  return alleBestanden(map).length;
}
