// SEO-basis van de website controleren. Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { existsSync, readFileSync } from "node:fs";
import { PAGINAS, SITE, bijgewerkt, canonical, tekst } from "../tools/structured-data.mjs";

const hier = (pad) => new URL("../" + pad, import.meta.url);
const lees = (pad) => readFileSync(hier(pad), "utf8");
const html = Object.fromEntries(PAGINAS.map((p) => [p, lees(p)]));
const meta = (bron, naam) => bron.match(new RegExp(`<meta (?:name|property)="${naam}" content="([^"]*)">`))?.[1];
const titel = (bron) => tekst(bron.match(/<title>([\s\S]*?)<\/title>/)[1]);
const sitemap = lees("sitemap.xml");
const inSitemap = [...sitemap.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1]);

test("elke pagina heeft een unieke titel van hooguit 60 tekens met de naam Sporty", () => {
  const titels = PAGINAS.map((p) => titel(html[p]));
  for (const [i, t] of titels.entries()) {
    assert.ok(t.length >= 20 && t.length <= 60, `${PAGINAS[i]}: titel is ${t.length} tekens: ${t}`);
    assert.match(t, /Sporty/, `${PAGINAS[i]}: de naam ontbreekt in de titel`);
  }
  assert.equal(new Set(titels).size, titels.length, "twee pagina's hebben dezelfde titel");
});

test("elke pagina heeft een unieke meta description van 70 tot 160 tekens", () => {
  const teksten = PAGINAS.map((p) => meta(html[p], "description"));
  for (const [i, d] of teksten.entries()) {
    assert.ok(d, `${PAGINAS[i]}: geen meta description`);
    assert.ok(d.length >= 70 && d.length <= 160, `${PAGINAS[i]}: description is ${d.length} tekens`);
  }
  assert.equal(new Set(teksten).size, teksten.length, "twee pagina's hebben dezelfde description");
});

test("canonical, Open Graph en sitemap wijzen naar dezelfde adressen", () => {
  for (const p of PAGINAS) {
    const url = canonical(html[p]);
    assert.ok(url?.startsWith(SITE + "/"), `${p}: canonical ontbreekt of hoort niet bij ${SITE}`);
    assert.equal(meta(html[p], "og:url"), url, `${p}: og:url wijkt af van de canonical`);
    assert.equal(tekst(meta(html[p], "og:title")), titel(html[p]), `${p}: og:title wijkt af van de titel`);
    assert.equal(meta(html[p], "og:description"), meta(html[p], "description"), `${p}: og:description wijkt af`);
    assert.ok(meta(html[p], "og:image:alt"), `${p}: og:image:alt ontbreekt`);
    assert.ok(inSitemap.includes(url), `${p}: ${url} staat niet in sitemap.xml`);
  }
});

test("alles in de sitemap bestaat en is indexeerbaar", () => {
  for (const url of inSitemap) {
    let pad = url.slice(SITE.length + 1);
    if (pad === "" || pad.endsWith("/")) pad += "index.html";
    assert.ok(existsSync(hier(pad)), `sitemap noemt ${url}, maar ${pad} bestaat niet`);
    assert.doesNotMatch(lees(pad), /<meta name="robots" content="[^"]*noindex/, `${pad} staat in de sitemap maar heeft noindex`);
  }
  assert.match(lees("robots.txt"), new RegExp(`Sitemap: ${SITE}/sitemap.xml`));
});

test("de foutpagina wordt niet geïndexeerd", () => {
  const fout = lees("404.html");
  assert.match(fout, /<meta name="robots" content="noindex">/);
  assert.equal(canonical(fout), null, "een foutpagina hoort geen canonical te hebben");
});

test("taalversies van de homepage verwijzen naar elkaar", () => {
  for (const p of ["index.html", "en/index.html"]) {
    for (const [taal, url] of [["nl", `${SITE}/`], ["en", `${SITE}/en/`], ["x-default", `${SITE}/`]]) {
      assert.ok(html[p].includes(`<link rel="alternate" hreflang="${taal}" href="${url}">`), `${p}: hreflang ${taal} ontbreekt`);
    }
  }
});

test("elke pagina heeft één h1, een taal en alt-teksten bij alle afbeeldingen", () => {
  for (const p of PAGINAS) {
    assert.equal((html[p].match(/<h1[\s>]/g) || []).length, 1, `${p}: niet precies één h1`);
    assert.match(html[p], /<html lang="(nl|en)">/);
    for (const img of html[p].match(/<img [^>]*>/g) || []) {
      assert.match(img, / alt="/, `${p}: afbeelding zonder alt: ${img.slice(0, 80)}`);
      assert.match(img, / width="\d+" height="\d+"/, `${p}: afbeelding zonder afmetingen: ${img.slice(0, 80)}`);
    }
  }
});

test("schema.org-gegevens zijn geldig en komen overeen met de zichtbare pagina (anders: npm run seo)", () => {
  for (const p of PAGINAS) {
    const blokken = [...html[p].matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)];
    assert.equal(blokken.length, 1, `${p}: verwacht precies één JSON-LD-blok`);
    const data = JSON.parse(blokken[0][1]);
    assert.equal(data["@context"], "https://schema.org");
    assert.equal(bijgewerkt(p, html[p]), html[p], `${p}: schema.org-gegevens zijn verouderd; draai npm run seo`);
  }
  const vragen = JSON.parse(html["veelgestelde-vragen.html"].match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/)[1])["@graph"].find((n) => n["@type"] === "FAQPage");
  assert.equal(vragen.mainEntity.length, (html["veelgestelde-vragen.html"].match(/<summary>/g) || []).length);
  const bso = JSON.parse(html["index.html"].match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/)[1])["@graph"][0];
  assert.equal(bso.name, "Sporty");
  assert.ok(html["index.html"].includes(`href="tel:${bso.telephone}"`), "telefoonnummer in schema.org wijkt af van de website");
  assert.ok(html["index.html"].includes(`href="mailto:${bso.email}"`), "e-mailadres in schema.org wijkt af van de website");
});

test("de oude naam BSO VCK komt nergens meer voor op de website", () => {
  for (const p of [...PAGINAS, "404.html", "site.webmanifest", "js/rekentool/rekentool.js", "js/rekentool/instellingen.js"]) {
    assert.doesNotMatch(lees(p), /BSO VCK|BSO\+VCK/, `${p} noemt nog BSO VCK`);
  }
});
