// Het uploadpakket voor de hosting (npm run pakket, tools/pakket.mjs): compleet, en zonder gegevens of geheimen.
// Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { bepaalBestanden, ontbrekendeLinks } from "../tools/pakket.mjs";

const bestanden = bepaalBestanden();

test("het pakket bevat de site, Mijn BSO en de serverinstellingen", () => {
  for (const nodig of ["index.html", "404.html", ".htaccess", "robots.txt", "sitemap.xml", "design/tokens.css", "beheer/index.php", "beheer/.htaccess", "beheer/.user.ini", "beheer/inc/.htaccess", "beheer/cli/.htaccess", "beheer/data/.htaccess", "beheer/config.voorbeeld.php", "en/index.html"]) {
    assert.ok(bestanden.includes(nodig), `${nodig} ontbreekt in het pakket`);
  }
});

test("het pakket bevat geen gegevens, configuratie, voorbeeld of ontwikkelbestanden", () => {
  const verboden = bestanden.filter((b) => b === "beheer/config.php" || /^beheer\/data\/(?!\.htaccess$|index\.html$)/.test(b)
    || /^(voorbeeld|tools|tests|docs|node_modules|pakket)\//.test(b) || /\.md$/.test(b) || ["vercel.json", "package.json", "package-lock.json"].includes(b) || b.startsWith(".git"));
  assert.deepEqual(verboden, []);
});

test("elke link in de pagina's wijst naar een bestand in het pakket", () => {
  assert.deepEqual(ontbrekendeLinks(bestanden), []);
});
