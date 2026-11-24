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
  // Henny's wolkjes in het Engels en in het Nederlands
  const [, engels, nederlands] = script.match(/var WOLKJES = EN \? \[([\s\S]*?)\] : \[([\s\S]*?)\];/);
  assert.equal(engels.match(/"[^"]+"/g).length, 7);
  assert.equal(nederlands.match(/"[^"]+"/g).length, 7);
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

test("de open dagen in het formulier zijn dezelfde als in de beheeromgeving", () => {
  const php = lees("beheer/inc/openingsdagen.php");
  const constante = (naam) => php.match(new RegExp(`const ${naam} = ([^;]+);`))[1];
  const groep = form.match(/<fieldset class="field keuzegroep"[^>]*data-dagen-open="([\d,]+)" data-alle-dagen-vanaf="([\d-]+)"/);
  assert.ok(groep, "data-dagen-open en data-alle-dagen-vanaf bij de dagen");
  assert.equal(`[${groep[1].split(",").join(", ")}]`, constante("SPORTY_EERSTE_DAGEN"));
  assert.equal(`'${groep[2]}'`, constante("SPORTY_ALLE_DAGEN_VANAF"));
  assert.match(form, new RegExp(`data-start-vanaf=${constante("SPORTY_OPEN_VANAF").replaceAll("'", '"')}`));
  // Zonder JavaScript staan de dichte dagen al uit, met de uitleg erbij
  const open = groep[1].split(",");
  for (const dag of ["1", "2", "3", "4", "5"]) {
    const vak = form.match(new RegExp(`<input type="checkbox" name="kinderen\\[0\\]\\[dagen\\]\\[\\]" value="${dag}"[^>]*>`))[0];
    assert.equal(/\sdisabled/.test(vak), !open.includes(dag), `dag ${dag}`);
  }
  assert.match(form, /data-dagen-beperkt>Van 4 januari tot de zomervakantie zijn we open op dinsdag en donderdag/);
});

test("voorletters, startdatum en groep van het kind zijn verplicht; de oude velden sportclub en plaats zijn weg", () => {
  for (const id of ["k0-voorletters", "k0-startdatum", "k0-groep"]) assert.match(form, new RegExp(`<input id="${id}"[^>]*\\srequired`), id);
  assert.doesNotMatch(form, /\[sportclub\]|school_plaats/);
  assert.doesNotMatch(inc, /'sportclub'|school_plaats/);
  assert.match(form, /Op welke school <span data-zit>zit<\/span> <span data-kindnamen>je kind<\/span>\? Noteer hier de school\./);
});

test("het Engelse formulier (en/join-the-club.html) stuurt dezelfde velden en keuzes, met taal=en", () => {
  const en = lees("en/join-the-club.html").match(/<form class="form aanmelden__form"[\s\S]*?<\/form>/)[0];
  const velden = (f) => [...f.matchAll(/ name="([^"]+)"(?: value="([^"]*)")?/g)].map((m) => m[1] + "=" + (m[2] ?? "")).filter((v) => v !== "taal=en").sort();
  assert.deepEqual(velden(en), velden(form));
  assert.match(en, /<form[^>]*>\s*<input type="hidden" name="taal" value="en">/);
  assert.match(en, /action="\.\.\/beheer\/aanvraag\.php"/);
});

test("feedback 2.0: Incidenteel als tweede abonnement, dan zijn vaste dagen niet verplicht", () => {
  assert.match(form, /name="kinderen\[0\]\[abonnement\]" value="incidenteel"/);
  assert.match(form, /Uurtarief € 9,75;/);
  assert.match(form, /Uurtarief € 12;/);
  assert.doesNotMatch(form, /10,31/);
  // De server: geen dag nodig bij Incidenteel, wel bij het Sporty Abonnement
  assert.match(inc, /if \(!\$kind\['dagen'\] && \$kind\['abonnement'\] !== 'incidenteel'\)/);
  // Het script: de controle van de dagen kijkt naar het abonnement van dat kind
  assert.match(script, /var verplicht = !isIncidenteel\(/);
  assert.match(form, /data-dagen-incidenteel hidden>Je koos Incidenteel/);
});

test("feedback 2.0: de sportvereniging van het kind is een vraag die niet verplicht is (stap 2)", () => {
  const stap2 = form.match(/data-stap="2"[\s\S]*?<\/section>/)[0];
  const veld = stap2.match(/<input id="k0-sportvereniging" name="kinderen\[0\]\[sportvereniging\]"[^>]*>/);
  assert.ok(veld, "het veld sportvereniging staat in stap 2");
  assert.doesNotMatch(veld[0], /\srequired/);
  assert.match(stap2, /Zo weten we of we je kind na Sporty naar de sportclub moeten brengen\./);
  assert.match(inc, /'sportvereniging' => \$tekst\(\$k\['sportvereniging'\] \?\? '', 120\)/);
  assert.match(script, /k \+ "\[sportvereniging\]"/);
});
