// Het aanmeldformulier "Kom bij de club" (aanmelden.html) en de server (beheer/aanvraag.php) moeten bij elkaar passen.
// Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

const lees = (pad) => readFileSync(new URL("../" + pad, import.meta.url), "utf8");
const html = lees("aanmelden.html");
const inc = lees("beheer/inc/aanvraag.php");
const script = lees("js/aanmelden.js");
const form = html.match(/<form class="form aanmelden__form"[\s\S]*?<\/form>/)[0];
const namen = new Set([...form.matchAll(/ name="([^"]+)"/g)].map((m) => m[1].replace(/kinderen\[0\]/, "kinderen[i]")));
const sleutels = (constante) => [...inc.match(new RegExp(`const ${constante} = \\[([\\s\\S]*?)\\];`))[1].matchAll(/'([a-z0-9-]+)' =>/g)].map((m) => m[1]);

test("het formulier heeft 7 stappen, in volgorde, en Henny zegt bij elke stap iets", () => {
  const stappen = [...form.matchAll(/data-stap="(\d)"/g)].map((m) => +m[1]);
  assert.deepEqual(stappen, [1, 2, 3, 4, 5, 6, 7]);
  const wolkjes = script.match(/var WOLKJES = \[([\s\S]*?)\];/)[1].match(/"[^"]+"/g);
  assert.equal(wolkjes.length, 7);
  assert.match(form, /Verstuur vrijblijvend/);
  assert.match(html, /Aanmelden is helemaal vrijblijvend/);
});

test("het formulier stuurt de velden die de server leest", () => {
  const perKind = [...inc.matchAll(/\$k\['([a-z_]+)'\]/g)].map((m) => `kinderen[i][${m[1]}]`);
  const ouder = [...inc.matchAll(/\$post\['([a-z_]+)'\]/g)].map((m) => m[1]).filter((n) => n !== "kinderen");
  for (const naam of [...new Set([...perKind, ...ouder])]) {
    const verwacht = naam === "kinderen[i][dagen]" ? "kinderen[i][dagen][]" : naam;
    assert.ok(namen.has(verwacht), `aanmelden.html mist het veld ${verwacht}`);
  }
});

test("de keuzes in het formulier zijn de keuzes die de server accepteert", () => {
  const waarden = (naam) => [...form.matchAll(new RegExp(`name="${naam.replace(/[[\]]/g, "\\$&")}" value="([^"]+)"`, "g"))].map((m) => m[1]);
  assert.deepEqual(waarden("kinderen[0][geslacht]"), sleutels("AANVRAAG_GESLACHT_KIND"));
  assert.deepEqual(waarden("geslacht"), sleutels("AANVRAAG_GESLACHT_OUDER"));
  assert.deepEqual(waarden("kinderen[0][abonnement]"), sleutels("AANVRAAG_ABONNEMENTEN"));
  assert.deepEqual(waarden("kinderen[0][dagen][]"), ["1", "2", "3", "4", "5"]);
  const gevonden = [...form.match(/<select id="gevonden"[\s\S]*?<\/select>/)[0].matchAll(/<option value="([a-z]+)">/g)].map((m) => m[1]);
  assert.deepEqual(gevonden, sleutels("AANVRAAG_GEVONDEN"));
});

test("bij elk verplicht veld staat een foutmelding, en het formulier werkt ook zonder JavaScript", () => {
  for (const [, id] of form.matchAll(/<(?:input|select|textarea) id="([a-z0-9-]+)"[^>]*\srequired/g)) {
    assert.ok(form.includes(`id="${id}-fout"`), `melding bij ${id} ontbreekt`);
  }
  for (const id of ["k0-geslacht", "k0-dagen", "partner"]) assert.ok(form.includes(`id="${id}-fout"`), `melding bij ${id} ontbreekt`);
  assert.match(form, /action="beheer\/aanvraag\.php" method="post"/);
  assert.doesNotMatch(form, /<section class="aanmelden__stap"[^>]* hidden/, "zonder JavaScript staan alle stappen in beeld");
});
