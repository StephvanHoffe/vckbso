#!/usr/bin/env node
/**
 * Controleert een Engelse pagina: staat er nog Nederlandse tekst in, en bestaan alle lokale links en bestanden?
 *   node tools/site/controleer-engels.mjs en/contact.html [en/...]
 * Kop, voet, iconen, scripts en commentaar slaat hij over (die komen uit de generator of zijn voor de ontwikkelaar).
 * Een regel met Nederlandse woorden is een signaal, geen bewijs: namen als Sportpark Middenmeer en Mijn BSO mogen.
 */
import { existsSync, readFileSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = fileURLToPath(new URL("../../", import.meta.url));

// Woorden die in Engelse tekst vrijwel nooit voorkomen
const NL = new Set(("de het een en van voor met niet zijn ook bij naar dat wat wij onze ons jouw jij je kind kinderen ouders " +
  "school na tot uur dag dagen week bij meer over alle wordt worden kan kun kunt moet mag graag hier daar " +
  "deze die dit zo nog al als maar want omdat wanneer welke waar hoe heel lekker samen leuk sport sporten spelen " +
  "aanmelden inloggen versturen verstuur bekijk boek lees kies vul volgende vorige terug stap gegevens bericht").split(" ").filter((w) => !["school", "sport", "week", "over", "die", "al"].includes(w)));
const TOEGESTAAN = /Sportpark Middenmeer|Mijn BSO|Geschillencommissie Kinderopvang|kinderopvangtoeslag|Watergraafsmeer|Voorlandpad|Amsterdam-Oost|Let's Play|Nederlands/g;

export function controleer(pagina) {
  const pad = join(ROOT, pagina);
  const html = readFileSync(pad, "utf8");
  const problemen = [];
  if (!/<html lang="en">/.test(html)) problemen.push("geen <html lang=\"en\">");
  const kern = html
    .replace(/<svg xmlns="http:\/\/www\.w3\.org\/2000\/svg" hidden>[\s\S]*?<\/svg>/, "")
    .replace(/<header class="site-header">[\s\S]*?<\/header>/, "")
    .replace(/<footer class="site-footer on-dark">[\s\S]*?<\/footer>/, "")
    .replace(/<script[\s\S]*?<\/script>/g, "")
    .replace(/<style[\s\S]*?<\/style>/g, "")
    .replace(/<!--[\s\S]*?-->/g, "");
  // Zichtbare tekst en tekst in attributen
  const stukken = [];
  for (const m of kern.matchAll(/>([^<]+)</g)) stukken.push(m[1]);
  // Attributen met tekst; data-fout en data-naar bevatten id's en stapnummers, geen tekst
  for (const m of kern.matchAll(/\s(?:alt|title|aria-label|placeholder|content|data-(?!fout=|naar=)[a-z-]+)="([^"]*)"/g)) stukken.push(m[1]);
  for (const s of stukken) {
    const schoon = s.replace(/&[a-z#0-9]+;/gi, " ").replace(TOEGESTAAN, "").trim();
    if (!schoon) continue;
    // Een naam (Tim van Eldonk): alle woorden met een hoofdletter, behalve tussenvoegsels
    if (/^[A-Z][\p{L}'-]*(\s+(van|de|der|den|ter|ten|het|[A-Z][\p{L}'-]*))+$/u.test(schoon)) continue;
    const woorden = schoon.toLowerCase().match(/[a-zà-ÿ']+/g) || [];
    const nl = woorden.filter((w) => NL.has(w));
    if (nl.length >= 2 || (woorden.length <= 3 && nl.length === 1 && woorden.length > 0 && !/^(de|en|je)$/.test(nl[0]))) {
      problemen.push(`Nederlands? "${schoon.slice(0, 100)}"`);
    }
  }
  // Lokale verwijzingen
  for (const m of html.matchAll(/\s(?:href|src|action|poster)="([^"#?]+)[^"]*"/g)) {
    const doel = m[1];
    if (/^(https?:|mailto:|tel:|data:|\/\/)/.test(doel) || doel.startsWith("/")) continue;
    const bestand = resolve(dirname(pad), doel);
    if (!existsSync(bestand) && !existsSync(join(bestand, "index.html"))) problemen.push(`bestaat niet: ${doel}`);
  }
  for (const m of html.matchAll(/\ssrcset="([^"]+)"/g)) {
    for (const deel of m[1].split(",")) {
      const doel = deel.trim().split(/\s+/)[0];
      if (doel && !existsSync(resolve(dirname(pad), doel))) problemen.push(`bestaat niet (srcset): ${doel}`);
    }
  }
  return problemen;
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  let totaal = 0;
  for (const pagina of process.argv.slice(2)) {
    const p = controleer(pagina);
    totaal += p.length;
    console.log(`${pagina}: ${p.length ? p.length + " punt(en)" : "in orde"}`);
    for (const regel of p) console.log("  - " + regel);
  }
  process.exit(totaal ? 1 : 0);
}
