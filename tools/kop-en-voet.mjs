#!/usr/bin/env node
/**
 * Zet dezelfde kop (menu), voet en iconen in alle Nederlandse pagina's.
 * Pas het menu of de footer aan in tools/site/ (kop.html, voet.html, sprite.svg) en draai:
 *   npm run kop-en-voet
 * De pagina's blijven gewone HTML-bestanden; dit script schrijft alleen de gedeelde stukken bij.
 */
import { readFileSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";

const ROOT = fileURLToPath(new URL("../", import.meta.url));
const lees = (pad) => readFileSync(ROOT + pad, "utf8");

/** Pagina's met het menu-item dat actief is ("club" = de knop Kom bij de club). Onderdelen van Alles over Sporty zijn "alles". */
export const PAGINAS = {
  "index.html": "home",
  "alles-over-sporty.html": "alles",
  "abonnementen.html": "alles",
  "vakantie-en-studiedagen.html": "alles",
  "community.html": "alles",
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
  "404.html": "",
};

/** Hoofdpagina's per menu-item: daar is het item "page", op de onderliggende pagina's "true". */
const HOOFD = { home: "index.html", alles: "alles-over-sporty.html", middag: "een-middag-bij-sporty.html", locaties: "locaties/sportpark-middenmeer.html", team: "team.html", coach: "word-sporty-coach.html", contact: "contact.html", club: "aanmelden.html" };

const prefix = (pagina) => (pagina === "404.html" ? "/" : "../".repeat(pagina.split("/").length - 1));

function vul(sjabloon, pagina) {
  const actief = PAGINAS[pagina];
  return sjabloon
    .replace(/\{\{(home|alles|middag|locaties|team|coach|contact|club)\}\}/g, (_, item) => (item === actief ? (HOOFD[item] === pagina ? ' aria-current="page"' : ' aria-current="true"') : ""))
    .replaceAll("{{P}}", prefix(pagina));
}

const SPRITE = readFileSync(ROOT + "tools/site/sprite.svg", "utf8");
const KOP = readFileSync(ROOT + "tools/site/kop.html", "utf8");
const VOET = readFileSync(ROOT + "tools/site/voet.html", "utf8");

/** De pagina met de huidige kop, voet en iconen. */
export function metOnderdelen(html, pagina) {
  const vervang = (tekst, begin, eind, nieuw, wat) => {
    const start = tekst.indexOf(begin);
    const stop = start === -1 ? -1 : tekst.indexOf(eind, start);
    if (stop === -1) throw new Error(`${pagina}: ${wat} niet gevonden`);
    return tekst.slice(0, start) + nieuw + tekst.slice(stop + eind.length);
  };
  let uit = vervang(html, '<svg xmlns="http://www.w3.org/2000/svg" hidden>', "</svg>\n", SPRITE, "iconen");
  uit = vervang(uit, '<header class="site-header">', "</header>\n", vul(KOP, pagina), "kop");
  uit = vervang(uit, '<footer class="site-footer on-dark">', "</footer>\n", vul(VOET, pagina), "voet");
  return uit;
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  for (const pagina of Object.keys(PAGINAS)) {
    const html = lees(pagina);
    const nieuw = metOnderdelen(html, pagina);
    if (nieuw !== html) {
      writeFileSync(ROOT + pagina, nieuw);
      console.log("Bijgewerkt:", pagina);
    }
  }
}
