// De pop-up "Blijf op de hoogte" (feedback 2.0, november 2026) en het formulier op blijf-op-de-hoogte.html.
// Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { PAGINAS, GEEN_POP_UP } from "../tools/kop-en-voet.mjs";
import { ENGELS, NEDERLANDS } from "../tools/site/talen.mjs";

const lees = (pad) => readFileSync(new URL("../" + pad, import.meta.url), "utf8");
const alle = [...Object.keys(PAGINAS), ...Object.values(ENGELS)];
const server = lees("beheer/op-de-hoogte.php");
const namen = (html) => [...new Set([...html.matchAll(/ name="([a-z_]+)"/g)].map((m) => m[1]))].sort();

test("de pop-up staat op elke pagina, behalve op aanmelden, blijf op de hoogte, privacy en 404", () => {
  for (const pagina of alle) {
    const html = lees(pagina);
    const zonder = GEEN_POP_UP.has(NEDERLANDS[pagina] ?? pagina);
    const aantal = (html.match(/<dialog class="pop-up"/g) || []).length;
    assert.equal(aantal, zonder ? 0 : 1, pagina);
    if (zonder) continue;
    // Na de footer (niet erin: dan krijgt hij de stijl van de donkere footer), met het script erbij
    assert.ok(html.indexOf("<dialog class=\"pop-up\"") > html.indexOf("</footer>"), `${pagina}: de pop-up staat na de footer`);
    assert.match(html, /<script src="(\.\.\/|\/)*js\/pop-up\.js" defer><\/script>/, pagina);
    assert.match(html, /<button class="pop-up__sluit" type="button" data-pop-up-sluit>/, `${pagina}: sluitknop`);
  }
});

test("de tekst van de klant staat in de pop-up", () => {
  const nl = lees("index.html");
  assert.match(nl, /Benieuwd naar <span class="hl">Sporty\?<\/span><\/h2>\s*<p class="pop-up__tekst">Laat je gegevens achter, blijf op de hoogte en ontvang een gratis Sporty-pakket!<\/p>/);
  assert.match(nl, /Zodat we je het Sporty-pakket kunnen toesturen\./);
});

test("de pop-up en de pagina sturen de velden die de server leest", () => {
  const gelezen = [...new Set([...server.matchAll(/invoer\('([a-z_]+)'\)/g)].map((m) => m[1]))].filter((n) => !["actie", "token"].includes(n)).sort();
  const popUp = lees("index.html").match(/<form class="form pop-up__form"[\s\S]*?<\/form>/)[0];
  assert.deepEqual(namen(popUp), gelezen);
  // Engels: dezelfde velden plus taal=en (dan antwoordt en mailt Mijn BSO in het Engels)
  const popUpEn = lees("en/index.html").match(/<form class="form pop-up__form"[\s\S]*?<\/form>/)[0];
  assert.deepEqual(namen(popUpEn), [...gelezen, "taal"].sort());
  assert.match(popUpEn, /<input type="hidden" name="taal" value="en">/);
  for (const pagina of ["blijf-op-de-hoogte.html", "en/stay-in-the-loop.html"]) {
    const form = lees(pagina).match(/<form class="form" action="[^"]*op-de-hoogte\.php"[\s\S]*?<\/form>/)[0];
    const extra = pagina.startsWith("en/") ? ["taal"] : [];
    assert.deepEqual(namen(form), [...gelezen.filter((n) => n !== "bron"), ...extra].sort(), pagina);
  }
  assert.match(popUp, /<input type="hidden" name="bron" value="pop-up">/);
  // Het adres is verplicht, de naam van het kind en het telefoonnummer niet
  for (const id of ["pu-naam", "pu-email", "pu-straat", "pu-postcode", "pu-plaats", "pu-toestemming"]) assert.match(popUp, new RegExp(`id="${id}"[^>]*\\srequired`), id);
  for (const id of ["pu-kind-naam", "pu-telefoon"]) assert.doesNotMatch(popUp.match(new RegExp(`<input id="${id}"[^>]*>`))[0], /\srequired/, id);
});

test("geen dubbele id's op een pagina (ook niet door de pop-up)", () => {
  for (const pagina of alle) {
    const html = lees(pagina).replace(/<svg xmlns="http:\/\/www\.w3\.org\/2000\/svg" hidden>[\s\S]*?<\/svg>/, "");
    const ids = [...html.matchAll(/\sid="([^"]+)"/g)].map((m) => m[1]);
    const dubbel = ids.filter((id, i) => ids.indexOf(id) !== i);
    assert.deepEqual([...new Set(dubbel)], [], pagina);
  }
});

test("Mijn BSO bewaart de naam van het kind en het adres versleuteld, en wist het adres na het pakket", () => {
  const inc = lees("beheer/inc/interesse.php");
  assert.match(lees("beheer/inc/database.php"), /ALTER TABLE interesse ADD COLUMN kind_naam TEXT NOT NULL DEFAULT '';\s*ALTER TABLE interesse ADD COLUMN adres TEXT NOT NULL DEFAULT '';/);
  assert.match(inc, /\$adresVersleuteld = \$adres \? versleutel\(json_encode\(\$adres/);
  assert.match(inc, /\$kindVersleuteld = \$kindNaam !== '' \? versleutel\(\$kindNaam\)/);
  assert.match(lees("beheer/inc/bewaarbeleid.php"), /UPDATE interesse SET adres = '' WHERE adres != '' AND pakket_op IS NOT NULL AND pakket_op < \?/);
});
