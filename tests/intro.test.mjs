// Controleert de intro op de homepage (de kinderen uit het logo rennen in beeld). Draaien met: npm test
// Faalt de eerste test? Draai npm run logo, dan komt de intro weer overeen met het logo.
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync, readdirSync } from "node:fs";
import { join } from "node:path";
import { fileURLToPath } from "node:url";
import { INTRO_PAGINAS, metIntro } from "../tools/logo/maak-logo.mjs";

const ROOT = fileURLToPath(new URL("..", import.meta.url));
const lees = (pagina) => readFileSync(join(ROOT, pagina), "utf8");
const css = lees("css/style.css");

test("de intro op de homepages is gemaakt met het huidige logo", () => {
  for (const pagina of INTRO_PAGINAS) {
    const html = lees(pagina);
    assert.equal(metIntro(html), html, `${pagina}: verouderd, draai npm run logo`);
  }
});

test("de intro is verborgen zonder JavaScript en voor schermlezers", () => {
  for (const pagina of INTRO_PAGINAS) {
    const html = lees(pagina);
    assert.match(html, /<div class="intro" hidden aria-hidden="true">/, pagina);
    assert.match(html, /prefers-reduced-motion: no-preference/, `${pagina}: de intro moet "minder beweging" respecteren`);
    assert.match(html, /sessionStorage/, `${pagina}: de intro hoort maar één keer per bezoek te spelen`);
  }
});

test("alleen de homepages hebben de intro", () => {
  const paginas = readdirSync(ROOT).filter((p) => p.endsWith(".html")).concat(["en/index.html"]);
  for (const pagina of paginas) {
    if (INTRO_PAGINAS.includes(pagina)) continue;
    assert.doesNotMatch(lees(pagina), /class="intro"|met-intro/, `${pagina}: de intro hoort alleen op de homepage`);
  }
});

test("de CSS heeft alle animaties van de intro", () => {
  const gebruikt = new Set([...css.matchAll(/\b(intro-[a-z-]+)\b/g)].map(([, naam]) => naam).filter((naam) => !/^intro-(speelt|overslaan|bg|binnen|w)$/.test(naam)));
  for (const naam of gebruikt) assert.match(css, new RegExp(`@keyframes ${naam} \\{`), `@keyframes ${naam} ontbreekt`);
  for (const naam of ["intro-weg", "intro-weg-snel"]) assert.ok(gebruikt.has(naam), `${naam} wordt niet gebruikt`);
  // Het script haalt de intro weg als deze animaties klaar zijn
  for (const pagina of INTRO_PAGINAS) assert.match(lees(pagina), /animationName === "intro-weg"/, pagina);
});
