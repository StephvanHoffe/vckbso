// Controleert de voorbeeldweergave (voorbeeld/) en de Vercel-instellingen. Draaien met: npm test
// Faalt er iets? Maak het voorbeeld opnieuw met: npm run voorbeeld
import { test } from "node:test";
import assert from "node:assert/strict";
import { existsSync, readFileSync, readdirSync, statSync } from "node:fs";
import { dirname, join, normalize } from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = fileURLToPath(new URL("..", import.meta.url));
const VOORBEELD = join(ROOT, "voorbeeld");
const alle = (map) => readdirSync(map).flatMap((naam) => {
  const pad = join(map, naam);
  return statSync(pad).isDirectory() ? alle(pad) : [pad];
});
const paginas = alle(VOORBEELD).filter((p) => p.endsWith(".html"));
const vercel = JSON.parse(readFileSync(join(ROOT, "vercel.json"), "utf8"));
const naarBestand = (url) => join(ROOT, url.endsWith("/") ? url + "index.html" : url);

test("het voorbeeld heeft een overzichtspagina en pagina's voor alle drie de rollen", () => {
  assert.ok(existsSync(join(VOORBEELD, "index.html")), "voorbeeld/index.html ontbreekt");
  for (const rol of ["beheerder", "begeleider", "ouder"]) {
    assert.ok(existsSync(join(VOORBEELD, "beheer", `${rol}-index.html`)), `startpagina voor ${rol} ontbreekt`);
  }
  assert.ok(paginas.length > 50, `maar ${paginas.length} pagina's in het voorbeeld`);
});

test("elke pagina van het voorbeeld heeft noindex en verwijst alleen naar bestaande bestanden", () => {
  for (const pagina of paginas) {
    const html = readFileSync(pagina, "utf8");
    assert.match(html, /<meta name="robots" content="noindex">/, `${pagina}: noindex ontbreekt`);
    for (const [, url] of html.matchAll(/(?:href|src)="([^"#?]+)[^"]*"/g)) {
      if (/^(https?:|mailto:|tel:|data:|javascript:)/.test(url)) continue;
      const doel = normalize(join(dirname(pagina), url));
      assert.ok(doel.startsWith(ROOT) && existsSync(doel), `${pagina.slice(ROOT.length)}: ${url} bestaat niet`);
    }
  }
});

test("het voorbeeld is gemaakt met de huidige naam en het huidige e-mailadres", () => {
  for (const pagina of paginas) {
    assert.doesNotMatch(readFileSync(pagina, "utf8"), /BSO VCK|vckbso/, `${pagina}: verouderd, draai npm run voorbeeld`);
  }
});

test("vercel.json stuurt oude adressen door naar de nieuwe pagina's", () => {
  const oud = vercel.redirects.filter((r) => !r.source.startsWith("/beheer"));
  for (const { source, destination, permanent } of oud) {
    assert.match(source, /^\/[a-z-]+\.html$/, `onverwachte redirect ${source}`);
    assert.equal(permanent, true, `${source}: een oud adres stuur je blijvend door`);
    assert.ok(!existsSync(join(ROOT, source)), `${source} bestaat nog, dan is een redirect niet nodig`);
    assert.ok(existsSync(join(ROOT, destination.replace(/#.*$/, ""))), `${source} → ${destination}: bestemming bestaat niet`);
  }
  const htaccess = readFileSync(join(ROOT, ".htaccess"), "utf8");
  for (const { source, destination } of oud) {
    assert.ok(htaccess.includes(`Redirect 301 ${source} ${destination}`), `.htaccess mist de doorverwijzing van ${source}`);
  }
});

test("vercel.json stuurt de beheeromgeving door naar bestaande pagina's van het voorbeeld", () => {
  const beheer = vercel.redirects.filter((r) => r.source.startsWith("/beheer"));
  assert.ok(beheer.length > 0);
  for (const { source, destination, permanent } of beheer) {
    assert.equal(permanent, false, `${source}: gebruik een tijdelijke redirect, de echte beheeromgeving kan later wel draaien`);
    assert.ok(existsSync(naarBestand(destination)), `${source} → ${destination}: bestemming bestaat niet`);
  }
  assert.equal(vercel.redirects.at(-1).source, "/beheer/:pad*", "de laatste redirect moet alle overige beheerpagina's opvangen");
  // Vercel matcht strikt: /beheer/:pad* vangt /beheer/ (met slash) niet op, anders toont Vercel de PHP-code van beheer/index.php
  assert.ok(vercel.redirects.some((r) => r.source === "/beheer/"), "/beheer/ (met slash aan het eind) heeft een eigen redirect nodig");
  const kop = vercel.headers.find((h) => h.source === "/voorbeeld/(.*)");
  assert.ok(kop?.headers.some((h) => h.key === "X-Robots-Tag" && h.value.includes("noindex")), "X-Robots-Tag noindex voor /voorbeeld/ ontbreekt");
});

test("links van de website naar de beheeromgeving hebben een doel in het voorbeeld", () => {
  const specifiek = new Map(vercel.redirects.filter((r) => !r.source.includes(":")).map((r) => [r.source, r.destination]));
  const websitePaginas = readdirSync(ROOT).filter((p) => p.endsWith(".html")).concat(["en/index.html", "locaties/sportpark-middenmeer.html"]);
  for (const p of websitePaginas) {
    for (const [, naam] of readFileSync(join(ROOT, p), "utf8").matchAll(/href="(?:\.\.\/|\/)?beheer\/([a-z-]+\.php)"/g)) {
      assert.ok(specifiek.has(`/beheer/${naam}`), `${p}: link naar beheer/${naam} heeft geen eigen redirect in vercel.json`);
    }
  }
});
