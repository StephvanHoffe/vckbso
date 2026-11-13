// De Engelse website (en/): elke pagina heeft een Engelse tegenhanger die even compleet is. Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { existsSync, readFileSync } from "node:fs";
import { dirname, posix } from "node:path";
import { ENGELS, NEDERLANDS } from "../tools/site/talen.mjs";
import { PAGINAS as MENU } from "../tools/kop-en-voet.mjs";
import { controleer } from "../tools/site/controleer-engels.mjs";

const lees = (pad) => readFileSync(new URL("../" + pad, import.meta.url), "utf8");
const paren = Object.entries(ENGELS);
const scripts = (html) => [...html.matchAll(/<script>[\s\S]*?<\/script>/g)].map((m) => m[0]);
const ids = (html) => [...html.matchAll(/\sid="([^"]+)"/g)].map((m) => m[1]).sort();
const formulieren = (html) => [...html.matchAll(/<form[^>]*action="([^"]*beheer\/[^"]+)"[^>]*>([\s\S]*?)<\/form>/g)];
const velden = (form) => [...form.matchAll(/ name="([^"]+)"/g)].map((m) => m[1]).filter((n) => n !== "taal").sort();

test("elke Nederlandse pagina heeft een Engelse versie, en omgekeerd", () => {
  for (const pagina of Object.keys(MENU).filter((p) => p !== "404.html")) {
    assert.ok(ENGELS[pagina], `${pagina} staat niet in tools/site/talen.mjs`);
  }
  for (const [nl, en] of paren) {
    assert.ok(existsSync(new URL("../" + nl, import.meta.url)), `${nl} bestaat niet`);
    assert.ok(existsSync(new URL("../" + en, import.meta.url)), `${en} bestaat niet`);
    assert.equal(NEDERLANDS[en], nl);
  }
});

for (const [nl, en] of paren) {
  const htmlNl = lees(nl);
  const htmlEn = lees(en);

  test(`${en}: Engels, zonder Nederlandse resten of kapotte links`, () => {
    assert.match(htmlEn, /<html lang="en">/);
    assert.deepEqual(controleer(en), []);
  });

  test(`${en}: de taalknop wijst naar de tegenhanger, op beide pagina's`, () => {
    for (const [pagina, html] of [[nl, htmlNl], [en, htmlEn]]) {
      const knop = html.match(/<ul class="lang"[\s\S]*?<\/ul>/)[0];
      const doelen = [...knop.matchAll(/href="([^"]+)"/g)].map((m) => posix.normalize(posix.join(dirname(pagina), m[1])));
      assert.deepEqual(doelen, [nl, en], pagina);
      assert.match(knop, new RegExp(`hreflang="${pagina === en ? "en" : "nl"}" aria-current="true"`), `${pagina}: de huidige taal is gemarkeerd`);
    }
  });

  test(`${en}: dezelfde opbouw en scripts als ${nl}`, () => {
    // Inline scripts staan vast in de CSP (.htaccess): ze moeten precies gelijk zijn
    assert.deepEqual(scripts(htmlEn), scripts(htmlNl));
    assert.deepEqual(ids(htmlEn), ids(htmlNl), "dezelfde id's (scripts en ankers gebruiken ze)");
    const fnl = formulieren(htmlNl);
    const fen = formulieren(htmlEn);
    assert.equal(fen.length, fnl.length, "evenveel formulieren");
    fen.forEach((form, i) => {
      assert.match(form[0], /^<form[^>]*>\s*<input type="hidden" name="taal" value="en">/, "het formulier stuurt taal=en mee");
      assert.equal(form[1], "../" + fnl[i][1], "zelfde adres in Mijn BSO");
      assert.deepEqual(velden(form[2]), velden(fnl[i][2]), "dezelfde velden");
    });
  });
}

test("elke Engelse e-mail hoort bij een Nederlandse, met dezelfde invulvelden", () => {
  const php = lees("beheer/inc/mailteksten.php");
  const sleutels = [...php.matchAll(/^ {4}'([a-z_]+)' => \[$/gm)].map((m) => m[1]);
  const engels = sleutels.filter((s) => s.endsWith("_en"));
  assert.ok(engels.length >= 7, "Engelse bevestigingen voor de formulieren en de ondertekening");
  const velden = (sleutel) => {
    const blok = php.slice(php.indexOf(`    '${sleutel}' => [`));
    return [...blok.slice(0, blok.indexOf("\n    ],")).match(/'velden' => \[([\s\S]*?)\n?\s*\],\n\s*'verplicht'/)[1].matchAll(/'([a-z_]+)' => \[/g)].map((m) => m[1]);
  };
  for (const sleutel of engels) {
    const basis = sleutel.slice(0, -3);
    assert.ok(sleutels.includes(basis), `${sleutel}: geen Nederlandse ${basis}`);
    assert.deepEqual(velden(sleutel), velden(basis), `${sleutel}: andere invulvelden dan ${basis}`);
  }
});
