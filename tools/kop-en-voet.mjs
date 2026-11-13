#!/usr/bin/env node
/**
 * Zet dezelfde kop (menu), voet en iconen in alle pagina's, Nederlands en Engels.
 * Pas het menu of de footer aan in tools/site/ (kop.html en voet.html, en de Engelse kop.en.html en
 * voet.en.html; sprite.svg voor de iconen) en draai:
 *   npm run kop-en-voet
 * De pagina's blijven gewone HTML-bestanden; dit script schrijft alleen de gedeelde stukken bij.
 * Welke Engelse pagina bij welke Nederlandse hoort, staat in tools/site/talen.mjs (voor de taalknop).
 */
import { existsSync, readFileSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { ENGELS, NEDERLANDS } from "./site/talen.mjs";

const ROOT = fileURLToPath(new URL("../", import.meta.url));
const lees = (pad) => readFileSync(ROOT + pad, "utf8");

/** Pagina's met het menu-item dat actief is ("club" = de knop Kom bij de club). Onderdelen van Alles over Sporty zijn "alles". */
export const PAGINAS = {
  "index.html": "home",
  "alles-over-sporty.html": "alles",
  "abonnement-en-kosten.html": "alles",
  "vakantie-en-studiedagen.html": "alles",
  "welkom-bij-de-club.html": "alles",
  "pauzebegeleiding.html": "alles",
  "een-middag-bij-sporty.html": "middag",
  "locaties/sportpark-middenmeer.html": "locaties",
  "team.html": "team",
  "word-sporty-coach.html": "coach",
  "contact.html": "contact",
  "proefdag.html": "",
  "aanmelden.html": "club",
  "klachtenregeling.html": "",
  "privacy.html": "",
  "voorwaarden.html": "",
  "blijf-op-de-hoogte.html": "",
  "404.html": "",
};

/** Hoofdpagina's per menu-item: daar is het item "page", op de onderliggende pagina's "true". */
const HOOFD = { home: "index.html", alles: "alles-over-sporty.html", middag: "een-middag-bij-sporty.html", locaties: "locaties/sportpark-middenmeer.html", team: "team.html", coach: "word-sporty-coach.html", contact: "contact.html", club: "aanmelden.html" };

/** Naar de hoofdmap van de site ({{P}}) en, voor Engelse pagina's, naar de map en/ ({{E}}). */
const prefix = (pagina) => (pagina === "404.html" ? "/" : "../".repeat(pagina.split("/").length - 1));
const prefixEngels = (pagina) => "../".repeat(pagina.split("/").length - 2);

function vul(sjabloon, pagina) {
  // Het actieve menu-item volgt de Nederlandse tegenhanger
  const nl = NEDERLANDS[pagina] ?? pagina;
  const actief = PAGINAS[nl];
  return sjabloon
    .replace(/\{\{(home|alles|middag|locaties|team|coach|contact|club)\}\}/g, (_, item) => (item === actief ? (HOOFD[item] === nl ? ' aria-current="page"' : ' aria-current="true"') : ""))
    .replace(/\{\{s:([^}]+)\}\}/g, (_, doel) => (doel === nl ? ' aria-current="page"' : ""))
    .replaceAll("{{NL}}", prefix(pagina) + nl)
    .replaceAll("{{EN}}", prefix(pagina) + (ENGELS[nl] ?? "en/index.html"))
    .replaceAll("{{E}}", NEDERLANDS[pagina] ? prefixEngels(pagina) : prefix(pagina) + "en/")
    .replaceAll("{{P}}", prefix(pagina))
    // Links naar de homepage gaan naar de map (./, ../, en/), net als de canonical-URL; /index.html stuurt door naar /
    .replace(/href="((?:\.\.\/)*|\/)?(en\/)?index\.html(#[^"]*)?"/g, (_, terug = "", en = "", hash = "") => `href="${terug + en || "./"}${hash}"`);
}

const SPRITE = readFileSync(ROOT + "tools/site/sprite.svg", "utf8");
const KOP = readFileSync(ROOT + "tools/site/kop.html", "utf8");
const VOET = readFileSync(ROOT + "tools/site/voet.html", "utf8");
const KOP_EN = readFileSync(ROOT + "tools/site/kop.en.html", "utf8");
const VOET_EN = readFileSync(ROOT + "tools/site/voet.en.html", "utf8");

/** De pagina met de huidige kop, voet en iconen. */
export function metOnderdelen(html, pagina) {
  const vervang = (tekst, begin, eind, nieuw, wat) => {
    const start = tekst.indexOf(begin);
    const stop = start === -1 ? -1 : tekst.indexOf(eind, start);
    if (stop === -1) throw new Error(`${pagina}: ${wat} niet gevonden`);
    return tekst.slice(0, start) + nieuw + tekst.slice(stop + eind.length);
  };
  let uit = vervang(html, '<svg xmlns="http://www.w3.org/2000/svg" hidden>', "</svg>\n", SPRITE, "iconen");
  const engels = Boolean(NEDERLANDS[pagina]);
  uit = vervang(uit, '<header class="site-header">', "</header>\n", vul(engels ? KOP_EN : KOP, pagina), "kop");
  uit = vervang(uit, '<footer class="site-footer on-dark">', "</footer>\n", vul(engels ? VOET_EN : VOET, pagina), "voet");
  return uit;
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  // Engelse pagina's die (nog) niet bestaan, slaan we over; npm test meldt dat
  for (const pagina of [...Object.keys(PAGINAS), ...Object.values(ENGELS)].filter((p) => existsSync(ROOT + p))) {
    const html = lees(pagina);
    const nieuw = metOnderdelen(html, pagina);
    if (nieuw !== html) {
      writeFileSync(ROOT + pagina, nieuw);
      console.log("Bijgewerkt:", pagina);
    }
  }
}
