// Het GGD-inspectierapport (25 november 2026): elke pagina linkt in de footer naar de pdf, en die pdf bestaat.
// De houder moet het rapport voor ouders openbaar maken; de footer staat op elke pagina, ook op de binnenkort-pagina.
import { test } from "node:test";
import assert from "node:assert/strict";
import { existsSync, readFileSync, readdirSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = fileURLToPath(new URL("..", import.meta.url));
const paginas = (map) => readdirSync(join(ROOT, map)).filter((f) => f.endsWith(".html")).map((f) => join(map, f));
const ALLE = [...paginas("."), ...paginas("en"), ...paginas("locaties"), ...paginas("en/locations")];

test("elke pagina linkt in de footer naar het GGD-inspectierapport, en de pdf bestaat", () => {
  for (const pagina of ALLE) {
    const html = readFileSync(join(ROOT, pagina), "utf8");
    if (!html.includes('class="site-footer')) continue;
    const footer = html.slice(html.indexOf('class="site-footer'));
    const link = footer.match(/<a href="([^"]*assets\/documenten\/ggd-inspectierapport[^"]*\.pdf)"[^>]*>([^<]+)<\/a>/);
    assert.ok(link, `${pagina}: link naar het GGD-inspectierapport in de footer`);
    assert.ok(existsSync(join(ROOT, dirname(pagina), link[1])), `${pagina}: ${link[1]} bestaat`);
    assert.match(link[2], pagina.startsWith("en/") ? /GGD inspection report \(PDF, in Dutch\)/ : /GGD-inspectierapport \(pdf\)/);
  }
});
