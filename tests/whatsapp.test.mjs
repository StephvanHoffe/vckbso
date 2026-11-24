// De zwevende WhatsApp-knop en het WhatsApp-nummer op de website. Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { PAGINAS } from "../tools/structured-data.mjs";
import { NEDERLANDS } from "../tools/site/talen.mjs";

const NUMMER = "31618266706"; // +31 6 18266706 (24 november 2026)
const lees = (pad) => readFileSync(new URL("../" + pad, import.meta.url), "utf8");

test("elke pagina heeft één zwevende WhatsApp-knop naar het nummer van Sporty", () => {
  for (const pagina of [...PAGINAS, "404.html"]) {
    const html = lees(pagina);
    const knoppen = [...html.matchAll(/<a class="whatsapp-knop" href="([^"]+)"[^>]*>([\s\S]*?)<\/a>/g)];
    assert.equal(knoppen.length, 1, `${pagina}: verwacht precies één WhatsApp-knop`);
    const [, href, inhoud] = knoppen[0];
    const engels = Boolean(NEDERLANDS[pagina]);
    assert.equal(href, `https://wa.me/${NUMMER}?text=${engels ? "Hi" : "Hoi"}%20Sporty!%20`, pagina);
    assert.match(inhoud, engels ? /Message us<\/span><span class="visually-hidden"> on WhatsApp \(opens WhatsApp\)/ : /App ons<\/span><span class="visually-hidden"> via WhatsApp \(opent WhatsApp\)/, `${pagina}: tekst voor schermlezers`);
    assert.match(knoppen[0][0], /target="_blank" rel="noopener"/, `${pagina}: opent WhatsApp in een nieuw tabblad`);
  }
});

test("alle WhatsApp-links gaan naar hetzelfde nummer", () => {
  for (const pagina of [...PAGINAS, "404.html"]) {
    for (const [, nummer] of lees(pagina).matchAll(/wa\.me\/(\d+)/g)) {
      assert.equal(nummer, NUMMER, `${pagina}: WhatsApp-link naar ${nummer}`);
    }
  }
});
