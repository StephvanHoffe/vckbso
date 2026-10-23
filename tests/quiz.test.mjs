// Controleert de Sporty-quiz op de homepages (js/quiz.js en de vragen in index.html en en/index.html). Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import vm from "node:vm";

const lees = (pad) => readFileSync(new URL("../" + pad, import.meta.url), "utf8");
const context = vm.createContext({});
vm.runInContext(lees("js/quiz.js"), context, { filename: "js/quiz.js" });
const { TYPES, LEEFTIJDEN, uitslag } = context.SportyQuiz;

const PAGINAS = { "index.html": { vraag: "Vraag", van: "van", script: "js/quiz.js" }, "en/index.html": { vraag: "Question", van: "of", script: "../js/quiz.js" } };
const quizVan = (pagina) => lees(pagina).match(/<section[^>]*id="quiz"[\s\S]*?<\/section>/)[0];
const vragenVan = (quiz) => [...quiz.matchAll(/<fieldset[^>]*data-vraag[\s\S]*?<\/fieldset>/g)].map((m) => m[0]);
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

for (const [pagina, taal] of Object.entries(PAGINAS)) {
  const html = lees(pagina);
  const quiz = quizVan(pagina);
  const vragen = vragenVan(quiz);

  test(`${pagina}: vier antwoorden per vraag, elk type één keer, de laatste vraag is de leeftijd`, () => {
    assert.equal(vragen.length, 6);
    vragen.slice(0, -1).forEach((vraag, i) => {
      const antwoorden = waarden(vraag);
      assert.deepEqual(antwoorden.map((a) => a.waarde).sort(), [...TYPES].sort(), `vraag ${i + 1}`);
      assert.ok(antwoorden.every((a) => a.naam === `v${i + 1}`), `vraag ${i + 1}: name="v${i + 1}"`);
      assert.match(vraag, new RegExp(`${taal.vraag} ${i + 1} ${taal.van} 6`));
    });
    const leeftijd = waarden(vragen.at(-1));
    assert.ok(leeftijd.length === 4 && leeftijd.every((a) => a.naam === "leeftijd" && LEEFTIJDEN.includes(a.waarde)));
    assert.equal((quiz.match(/data-reactie="[^"]+"/g) || []).length, 6 * 4 + TYPES.length, "Henny reageert op elk antwoord en bij elke uitslag");
    assert.match(quiz, /data-quiz-verder data-laatste="[^"]+"/, "tekst van de knop bij de laatste vraag");
  });

  test(`${pagina}: bij elk type en elke leeftijd staat een uitslag, met een knop`, () => {
    for (const type of TYPES) assert.match(quiz, new RegExp(`data-type="${type}"[^>]*hidden>[\\s\\S]*?<h3 tabindex="-1">`), type);
    for (const leeftijd of LEEFTIJDEN) assert.match(quiz, new RegExp(`data-leeftijd="${leeftijd}" hidden>[\\s\\S]*?class="btn"`), leeftijd);
    assert.ok(html.includes(`<script src="${taal.script}" defer></script>`));
    assert.match(html, /<symbol id="i-idee"/);
    assert.match(html, /class="hero__quiz"><a [^>]*href="#quiz"/);
  });
}

test("de Engelse en de Nederlandse quiz hebben dezelfde opbouw", () => {
  const [nl, en] = Object.keys(PAGINAS).map((pagina) => vragenVan(quizVan(pagina)).map((vraag) => waarden(vraag).map((a) => a.naam + "=" + a.waarde).join()));
  assert.deepEqual(en, nl);
});
