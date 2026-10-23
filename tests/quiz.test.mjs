// Controleert de Sporty-quiz op de homepage (js/quiz.js en de vragen in index.html). Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import vm from "node:vm";

const lees = (pad) => readFileSync(new URL("../" + pad, import.meta.url), "utf8");
const context = vm.createContext({});
vm.runInContext(lees("js/quiz.js"), context, { filename: "js/quiz.js" });
const { TYPES, LEEFTIJDEN, uitslag } = context.SportyQuiz;

const html = lees("index.html");
const quiz = html.match(/<section[^>]*id="quiz"[\s\S]*?<\/section>/)[0];
const vragen = [...quiz.matchAll(/<fieldset[^>]*data-vraag[\s\S]*?<\/fieldset>/g)].map((m) => m[0]);
const waarden = (vraag) => [...vraag.matchAll(/type="radio" name="([^"]+)" value="([^"]+)"/g)].map((m) => ({ naam: m[1], waarde: m[2] }));

test("de uitslag is het type dat het vaakst gekozen is", () => {
  assert.equal(uitslag(["stuiterbal", "teamcaptain", "stuiterbal", "chillkampioen", "spelbedenker", "ok"]), "stuiterbal");
  assert.equal(uitslag(["chillkampioen", "chillkampioen", "teamcaptain", "chillkampioen", "teamcaptain", "jong"]), "chillkampioen");
});

test("bij een gelijke stand telt het type dat als laatste gekozen is", () => {
  assert.equal(uitslag(["teamcaptain", "spelbedenker", "spelbedenker", "teamcaptain", "stuiterbal"]), "teamcaptain");
  assert.equal(uitslag(["stuiterbal", "teamcaptain", "spelbedenker", "chillkampioen", "spelbedenker"]), "spelbedenker");
  assert.equal(uitslag(["stuiterbal", "teamcaptain", "spelbedenker", "chillkampioen", "ok"]), "chillkampioen");
});

test("de vragen in index.html: vier antwoorden per vraag, elk type één keer, de laatste vraag is de leeftijd", () => {
  assert.equal(vragen.length, 6);
  vragen.slice(0, -1).forEach((vraag, i) => {
    const antwoorden = waarden(vraag);
    assert.deepEqual(antwoorden.map((a) => a.waarde).sort(), [...TYPES].sort(), `vraag ${i + 1}`);
    assert.ok(antwoorden.every((a) => a.naam === `v${i + 1}`), `vraag ${i + 1}: name="v${i + 1}"`);
    assert.match(vraag, new RegExp(`Vraag ${i + 1} van 6`));
  });
  const leeftijd = waarden(vragen.at(-1));
  assert.ok(leeftijd.length === 4 && leeftijd.every((a) => a.naam === "leeftijd" && LEEFTIJDEN.includes(a.waarde)));
  assert.equal((quiz.match(/data-reactie="[^"]+"/g) || []).length, 6 * 4 + TYPES.length, "Henny reageert op elk antwoord en bij elke uitslag");
});

test("bij elk type en elke leeftijd staat een uitslag in index.html", () => {
  for (const type of TYPES) assert.match(quiz, new RegExp(`data-type="${type}"[^>]*hidden>[\\s\\S]*?<h3 tabindex="-1">`), type);
  for (const leeftijd of LEEFTIJDEN) assert.match(quiz, new RegExp(`data-leeftijd="${leeftijd}" hidden>[\\s\\S]*?class="btn"`), leeftijd);
  assert.match(html, /<script src="js\/quiz\.js" defer><\/script>/);
});
