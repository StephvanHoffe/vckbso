// Het beveiligingsbeleid (Content-Security-Policy) van de website in .htaccess moet passen bij de pagina's:
// elk ingebouwd script moet erin staan met zijn vingerafdruk (sha256), anders blokkeert de browser het.
// Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync, readdirSync } from "node:fs";
import { createHash } from "node:crypto";

const lees = (pad) => readFileSync(new URL("../" + pad, import.meta.url), "utf8");
const paginas = [
  ...readdirSync(new URL("..", import.meta.url)).filter((f) => f.endsWith(".html")),
  "en/index.html",
  ...readdirSync(new URL("../locaties/", import.meta.url)).filter((f) => f.endsWith(".html")).map((f) => "locaties/" + f),
];
const csp = lees(".htaccess").match(/Content-Security-Policy "([^"]+)"/)[1];

test("elk ingebouwd script staat met zijn vingerafdruk in het beveiligingsbeleid", () => {
  for (const pagina of paginas) {
    for (const [, attrs, inhoud] of lees(pagina).matchAll(/<script(?![^>]*\bsrc=)([^>]*)>([\s\S]*?)<\/script>/g)) {
      if (attrs.includes("ld+json")) continue;
      const hash = "'sha256-" + createHash("sha256").update(inhoud, "utf8").digest("base64") + "'";
      assert.ok(csp.includes(hash), `${pagina}: het script dat begint met "${inhoud.trim().slice(0, 40)}" staat niet in de CSP in .htaccess. Voeg toe: ${hash}`);
    }
  }
});

test("geen scripts, lettertypen of afbeeldingen van andere sites", () => {
  for (const pagina of paginas) {
    const html = lees(pagina);
    assert.doesNotMatch(html, /<script[^>]+src="https?:\/\//, `${pagina} laadt een script van een andere site`);
    assert.doesNotMatch(html, /<img[^>]+src="https?:\/\//, `${pagina} laadt een afbeelding van een andere site`);
    assert.doesNotMatch(html, / on[a-z]+="/, `${pagina} heeft een ingebouwde gebeurtenis (onclick=...), die de CSP blokkeert`);
  }
  assert.match(csp, /object-src 'none'/);
  assert.doesNotMatch(csp, /script-src[^;]*'unsafe-inline'/);
});
