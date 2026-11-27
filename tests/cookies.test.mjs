// Cookiemelding en Google Tag Manager (27 november 2026, js/cookies.js).
// Tag Manager mag pas laden na toestemming: geen script van Google in de HTML, wel de melding en de link
// "Cookie-instellingen" op elke pagina, het beveiligingsbeleid laat Google toe, en Mijn BSO blijft erbuiten.
// Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync, readdirSync, statSync } from "node:fs";
import { PAGINAS } from "../tools/kop-en-voet.mjs";
import { ENGELS } from "../tools/site/talen.mjs";

const lees = (pad) => readFileSync(new URL("../" + pad, import.meta.url), "utf8");
const paginas = [...Object.keys(PAGINAS), ...Object.values(ENGELS)];

test("Tag Manager staat alleen in js/cookies.js en laadt pas na toestemming", () => {
  const script = lees("js/cookies.js");
  assert.match(script, /var GTM_ID = "GTM-K9XSMB74";/);
  assert.match(script, /gtag\("consent", "default", \{ analytics_storage: "denied", ad_storage: "denied", ad_user_data: "denied", ad_personalization: "denied" \}\);/, "standaard staat alles uit");
  // Het script van Google wordt alleen in laadTagManager() gemaakt, en dat gebeurt alleen bij de keuze "ja"
  const laad = script.slice(script.indexOf("var laadTagManager"), script.indexOf("// Toestemming ingetrokken"));
  assert.match(laad, /googletagmanager\.com\/gtm\.js\?id=/);
  assert.equal(script.split("googletagmanager.com/gtm.js").length, 2, "Tag Manager wordt op één plek geladen");
  assert.deepEqual([...script.matchAll(/laadTagManager\(\)/g)].length, 2, "alleen na een eerdere of nieuwe keuze \"ja\"");
  assert.match(script, /if \(OP_DOMEIN && keuze === "ja"\) laadTagManager\(\);/);
  assert.match(script, /if \(nieuw === "ja"\) \{\s*laadTagManager\(\);/);
  assert.match(script, /if \(!keuze && !zegtAlNee\) toon\(false\);/, "zonder keuze vragen we het");
});

test("elke pagina heeft het script en de link Cookie-instellingen; geen Google in de HTML", () => {
  for (const pagina of paginas) {
    const html = lees(pagina);
    assert.match(html, /<script src="(?:\.\.\/|\/)*js\/cookies\.js" defer><\/script>\n<\/footer>/, `${pagina}: js/cookies.js in de footer`);
    assert.match(html, /<a href="[^"]*privacy\.html#cookies" data-cookie-instellingen>(Cookie-instellingen|Cookie settings)<\/a>/, `${pagina}: link Cookie-instellingen`);
    assert.doesNotMatch(html, /googletagmanager|google-analytics/, `${pagina}: Google laadt niet zonder toestemming`);
  }
  for (const pagina of ["privacy.html", "en/privacy.html"]) {
    const html = lees(pagina);
    assert.match(html, /<h2 id="cookies">/);
    assert.match(html, /Google Tag Manager/);
    assert.match(html, /sporty_cookiekeuze/, "de privacyverklaring noemt de cookie met de keuze");
    assert.doesNotMatch(html, /geen tracking- of advertentiecookies|doesn’t use tracking or advertising cookies/);
  }
});

test("de pop-up wacht op de cookiemelding", () => {
  assert.match(lees("js/pop-up.js"), /\[data-cookie-melding\]:not\(\[hidden\]\)/);
});

test("het beveiligingsbeleid laat Google Tag Manager en Analytics toe", () => {
  const csp = lees(".htaccess").match(/Content-Security-Policy "([^"]+)"/)[1];
  const deel = (naam) => (csp.match(new RegExp(`(?:^|; )${naam} ([^;]+)`)) || [])[1] ?? "";
  assert.match(deel("script-src"), /https:\/\/\*\.googletagmanager\.com/);
  assert.match(deel("connect-src"), /https:\/\/\*\.google-analytics\.com/);
  assert.match(deel("connect-src"), /https:\/\/\*\.analytics\.google\.com/);
  assert.match(deel("img-src"), /https:\/\/\*\.google-analytics\.com/);
  assert.match(deel("frame-src"), /https:\/\/\*\.googletagmanager\.com/);
  assert.doesNotMatch(deel("script-src"), /'unsafe-inline'|'unsafe-eval'/, "geen ingebouwde scripts of eval, ook niet voor Tag Manager");
});

test("Mijn BSO gebruikt geen Google Tag Manager", () => {
  const map = new URL("../beheer/", import.meta.url);
  const bestanden = (dir) => readdirSync(dir).flatMap((f) => {
    const pad = new URL(f, dir);
    return statSync(pad).isDirectory() ? bestanden(new URL(f + "/", dir)) : [pad];
  });
  for (const pad of bestanden(map).filter((p) => /\.(php|js|html)$/.test(p.pathname))) {
    assert.doesNotMatch(readFileSync(pad, "utf8"), /googletagmanager|cookies\.js/, `${pad.pathname}: geen Tag Manager in Mijn BSO`);
  }
});
