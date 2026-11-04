// Het boekingsformulier op proefdag.html en de server (beheer/proefdag.php) moeten bij elkaar passen. Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

const lees = (pad) => readFileSync(new URL("../" + pad, import.meta.url), "utf8");
const html = lees("proefdag.html");
const server = lees("beheer/proefdag.php");
const inc = lees("beheer/inc/proefdagen.php");
const script = lees("js/proefdag.js");
const form = html.match(/<form class="form boeken__form"[\s\S]*?<\/form>/)[0];

test("het formulier stuurt precies de velden die de server leest", () => {
  const velden = new Set([...form.matchAll(/ name="([a-z_]+)"/g)].map((m) => m[1]));
  const gelezen = [...server.matchAll(/invoer\('([a-z_]+)'\)/g)].map((m) => m[1]);
  const lijst = server.match(/foreach \(\[([^\]]+)\] as \$veld\)/)[1].match(/'([a-z_]+)'/g).map((v) => v.slice(1, -1));
  for (const veld of [...gelezen, ...lijst]) {
    if (veld === "moment_id") continue; // de keuzerondjes maakt js/proefdag.js
    assert.ok(velden.has(veld), `proefdag.html mist het veld ${veld}`);
  }
  assert.match(script, /rondje\.name = "moment_id"/);
  assert.match(form, /action="beheer\/proefdag\.php"/);
  assert.match(html, /data-proefdag="beheer\/proefdag\.php"/);
});

test("de leeftijden in het formulier zijn de leeftijden die de server accepteert", () => {
  const server = inc.match(/const PROEFDAG_LEEFTIJDEN = \[([^\]]+)\]/)[1].match(/'(\d+)'/g).map((v) => v.slice(1, -1));
  const keuzes = [...form.match(/<select id="kind-leeftijd"[\s\S]*?<\/select>/)[0].matchAll(/<option value="(\d+)">/g)].map((m) => m[1]);
  assert.deepEqual(keuzes, server);
  assert.deepEqual(keuzes, ["4", "5", "6", "7", "8", "9", "10", "11", "12"], "Sporty is er voor kinderen van 4 t/m 12 jaar");
});

test("bij elk verplicht veld staat een foutmelding, en zonder beheeromgeving blijft er een terugval", () => {
  for (const [, id] of form.matchAll(/<(?:input|select|textarea) id="([a-z-]+)"[^>]*\srequired/g)) {
    assert.ok(form.includes(`id="${id}-fout"`), `melding bij ${id} ontbreekt`);
  }
  assert.ok(form.includes('id="moment-fout"'), "melding bij de momenten ontbreekt");
  assert.match(html, /<div class="boeken" data-proefdag="[^"]+" hidden>/, "het formulier staat verborgen tot er momenten zijn");
  assert.match(html, /<div class="boeken-terugval" data-boeken-terugval>[\s\S]*?href="contact\.html\?onderwerp=proefdag#formulier"/);
});
