// De website op Vercel (bsosporty.nl) en Mijn BSO bij Vimexx (mijn.bsosporty.nl), sinds november 2026:
// middleware.js (de binnenkort-pagina), vercel.json, .vercelignore, js/main.js en Mijn BSO met website_url.
// Het voorbeeld op *.vercel.app staat in tests/voorbeeld.test.mjs. Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { execFileSync } from "node:child_process";
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import middleware, { config, DOMEIN, STANDAARD, STATUS_URL, voorTest } from "../middleware.js";
import { bepaalBestanden, bepaalMijnBsoBestanden } from "../tools/pakket.mjs";

const lees = (pad) => readFileSync(new URL("../" + pad, import.meta.url), "utf8");
const vercel = JSON.parse(lees("vercel.json"));
const MIJN = "https://mijn.bsosporty.nl";

/** Een nagebootste statuspagina van Mijn BSO; telt hoe vaak hij is opgevraagd. */
function status(live) {
  const nep = async (url) => {
    nep.keer++;
    assert.equal(url, STATUS_URL);
    if (live === "storing") throw new Error("niet bereikbaar");
    return new Response(JSON.stringify({ live }), { headers: { "Content-Type": "application/json" } });
  };
  nep.keer = 0;
  return nep;
}
const vraag = (pad, { host = "bsosporty.nl", cookie = "" } = {}) => middleware(new Request(`https://${host}${pad}`, { headers: cookie ? { cookie } : {} }));
const doorverwijzing = (antwoord) => [antwoord.status, antwoord.headers.get("location")];
const herschreven = (antwoord) => antwoord.headers.get("x-middleware-rewrite");
const doorgelaten = (antwoord) => antwoord.headers.get("x-middleware-next") === "1";

test("middleware: alleen op het domein, en niet voor de bestanden van de pagina, Mijn BSO of het voorbeeld", () => {
  assert.ok(DOMEIN.test("bsosporty.nl") && DOMEIN.test("www.bsosporty.nl"));
  assert.ok(!DOMEIN.test("sporty-abc.vercel.app") && !DOMEIN.test("mijn.bsosporty.nl") && !DOMEIN.test("bsosporty.nl.example"));
  const matcher = new RegExp(`^${config.matcher[0]}$`);
  for (const pad of ["/", "/en/", "/contact.html", "/en/team.html", "/binnenkort.html", "/sitemap.xml", "/locaties/sportpark-middenmeer.html"]) {
    assert.ok(matcher.test(pad), `${pad} hoort langs de middleware te gaan`);
  }
  for (const pad of ["/css/style.css", "/js/main.js", "/assets/img/favicon.svg", "/design/tokens.css", "/beheer/", "/beheer/inloggen.php", "/beheer", "/voorbeeld/", "/favicon.ico", "/robots.txt", "/site.webmanifest", "/googled009bd1a8c28802f.html"]) {
    assert.ok(!matcher.test(pad), `${pad} hoort niet langs de middleware te gaan`);
  }
  assert.equal(STATUS_URL, `${MIJN}/beheer/website-status.php`);
  assert.equal(STANDAARD, "binnenkort", "zolang de website niet live is: bij twijfel de binnenkort-pagina");
});

test("middleware: zolang de website niet live is, de binnenkort-pagina", async () => {
  voorTest(status(false));
  assert.equal(herschreven(await vraag("/")), "https://bsosporty.nl/binnenkort.html");
  assert.equal(herschreven(await vraag("/en/")), "https://bsosporty.nl/en/coming-soon.html");
  assert.equal(herschreven(await vraag("/", { host: "www.bsosporty.nl" })), "https://www.bsosporty.nl/binnenkort.html");
  assert.deepEqual(doorverwijzing(await vraag("/contact.html")), [302, "https://bsosporty.nl/"]);
  assert.deepEqual(doorverwijzing(await vraag("/locaties/sportpark-middenmeer.html")), [302, "https://bsosporty.nl/"]);
  assert.deepEqual(doorverwijzing(await vraag("/en/team.html")), [302, "https://bsosporty.nl/en/"]);
  assert.deepEqual(doorverwijzing(await vraag("/en")), [302, "https://bsosporty.nl/en/"]);
  for (const pad of ["/binnenkort.html", "/en/coming-soon.html", "/privacy.html", "/en/privacy.html", "/404.html"]) {
    assert.ok(doorgelaten(await vraag(pad)), `${pad} hoort bereikbaar te blijven`);
  }
  assert.equal(herschreven(await vraag("/sitemap.xml")), "https://bsosporty.nl/sitemap-binnenkort.xml", "zolang de website niet live is: een sitemap met alleen de binnenkort-pagina");
  assert.ok(doorgelaten(await vraag("/sitemap-binnenkort.xml")));
  // Het team kijkt alvast met het cookie uit Mijn BSO
  assert.ok(doorgelaten(await vraag("/contact.html", { cookie: "iets=1; sporty_voorproef=1" })));
  assert.deepEqual(doorverwijzing(await vraag("/contact.html", { cookie: "sporty_voorproef=10" })), [302, "https://bsosporty.nl/"]);
});

test("middleware: na de livegang de gewone website, en de binnenkort-pagina stuurt door", async () => {
  voorTest(status(true));
  assert.ok(doorgelaten(await vraag("/")));
  assert.ok(doorgelaten(await vraag("/contact.html")));
  assert.ok(doorgelaten(await vraag("/sitemap.xml")));
  assert.deepEqual(doorverwijzing(await vraag("/binnenkort.html")), [302, "https://bsosporty.nl/"]);
  assert.deepEqual(doorverwijzing(await vraag("/en/coming-soon.html")), [302, "https://bsosporty.nl/en/"]);
});

test("middleware: de status wordt kort onthouden, en bij een storing geldt de laatst bekende", async () => {
  const nep = status(true);
  voorTest(nep);
  await vraag("/");
  await vraag("/team.html");
  assert.equal(nep.keer, 1, "binnen 30 seconden niet opnieuw vragen");
  // Mijn BSO onbereikbaar zonder eerdere status: de binnenkort-pagina (STANDAARD)
  voorTest(status("storing"));
  assert.equal(herschreven(await vraag("/")), "https://bsosporty.nl/binnenkort.html");
  // Het voorbeeld (*.vercel.app) vraagt nooit naar de status
  const voorbeeld = status(false);
  voorTest(voorbeeld);
  assert.ok(doorgelaten(await vraag("/contact.html", { host: "sporty-abc.vercel.app" })));
  assert.equal(voorbeeld.keer, 0);
});

test("vercel.json: op het domein gaat /beheer naar mijn.bsosporty.nl en is het voorbeeld er niet", () => {
  const domein = vercel.redirects.filter((r) => r.has);
  // Bovenaan: Vercel neemt de eerste regel die past
  assert.deepEqual(vercel.redirects.slice(0, domein.length), domein);
  for (const r of domein) {
    assert.deepEqual(r.has, [{ type: "host", value: "(www\\.)?bsosporty\\.nl" }]);
    assert.equal(r.permanent, false, `${r.source}: tijdelijk (307), dan blijft een formulier zonder JavaScript een POST`);
  }
  const naar = Object.fromEntries(domein.map((r) => [r.source, r.destination]));
  assert.equal(naar["/beheer/:pad*"], `${MIJN}/beheer/:pad*`);
  assert.equal(naar["/beheer/"], `${MIJN}/beheer/`);
  assert.equal(naar["/beheer"], `${MIJN}/beheer/`);
  assert.equal(naar["/voorbeeld/:pad*"], "/");
  assert.equal(naar["/voorbeeld/"], "/", "Vercel vangt /voorbeeld/ (met slash) niet op met /voorbeeld/:pad*");
  assert.equal(naar["/voorbeeld"], "/");
  // Alleen buiten het domein: niet in Google
  const noindex = vercel.headers.filter((h) => h.headers.some((k) => k.key === "X-Robots-Tag"));
  assert.equal(noindex.length, 1);
  assert.deepEqual(noindex[0].missing, [{ type: "host", value: "(www\\.)?bsosporty\\.nl" }]);
});

test("vercel.json: dezelfde beveiligingskoppen als .htaccess, en de formulieren mogen naar Mijn BSO", () => {
  const koppen = Object.fromEntries(vercel.headers.find((h) => h.source === "/(.*)" && !h.missing).headers.map((h) => [h.key, h.value]));
  const csp = koppen["Content-Security-Policy"];
  const htaccess = lees(".htaccess");
  const hashes = (tekst) => [...tekst.matchAll(/'sha256-[^']+'/g)].map((m) => m[0]).sort();
  assert.deepEqual(hashes(csp), hashes(htaccess.match(/Content-Security-Policy "([^"]+)"/)[1]), "de vingerafdrukken van de scripts in de pagina's");
  assert.match(csp, /connect-src 'self' https:\/\/formspree\.io https:\/\/mijn\.bsosporty\.nl[ ;]/);
  // Google Tag Manager (na toestemming, js/cookies.js): hetzelfde in vercel.json en .htaccess
  const google = (tekst) => [...tekst.matchAll(/https:\/\/[^\s;"]*(?:google|doubleclick|gstatic)[^\s;"]*/g)].map((m) => m[0]).sort();
  assert.deepEqual(google(csp), google(htaccess.match(/Content-Security-Policy "([^"]+)"/)[1]), "dezelfde diensten van Google");
  assert.match(csp, /form-action 'self' https:\/\/formspree\.io https:\/\/mijn\.bsosporty\.nl mailto:;/);
  assert.equal(koppen["X-Content-Type-Options"], "nosniff");
  assert.equal(koppen["X-Frame-Options"], "SAMEORIGIN");
  assert.ok(koppen["Referrer-Policy"] && koppen["Permissions-Policy"]);
});

test(".vercelignore: interne bestanden niet online, alles wat de website gebruikt wel", () => {
  const regels = lees(".vercelignore").split("\n").map((r) => r.trim()).filter((r) => r && !r.startsWith("#"));
  const negeer = (pad) => {
    let uit = false;
    for (const regel of regels) {
      const terug = regel.startsWith("!");
      const patroon = regel.replace(/^!/, "").replace(/^\//, "");
      const past = patroon.endsWith("/*") ? pad.startsWith(patroon.slice(0, -1)) : pad === patroon || pad.startsWith(patroon + "/");
      if (past) uit = !terug;
    }
    return uit;
  };
  for (const pad of ["CLAUDE.md", "README.md", "briefing.json", "docs/live-zetten-vimexx.md", "beheer/inc/bootstrap.php", "beheer/config.voorbeeld.php", "tools/pakket.mjs", "tests/vercel.test.mjs", "design/logo/x.png", "assets/fotos/BRONNEN.md", "assets/fotos/bron/x.jpg"]) {
    assert.ok(negeer(pad), `${pad} hoort niet op Vercel`);
  }
  const website = bepaalBestanden().filter((p) => !p.startsWith("beheer/") && p !== ".htaccess");
  for (const pad of website) {
    assert.ok(!negeer(pad), `${pad} hoort bij de website maar staat in .vercelignore`);
  }
  for (const pad of ["middleware.js", "package.json", "package-lock.json", "vercel.json", "design/tokens.css", "voorbeeld/index.html"]) {
    assert.ok(!negeer(pad), `${pad} is nodig op Vercel`);
  }
});

test("js/main.js stuurt de formulieren op het domein naar mijn.bsosporty.nl", () => {
  const js = lees("js/main.js");
  assert.ok(js.includes("if (/^(www\\.)?bsosporty\\.nl$/.test(location.hostname)) {"), "dezelfde hostnamen als de middleware");
  assert.ok(js.includes('"https://mijn.bsosporty.nl" + url.pathname'));
  for (const doel of ['form[action]', '[data-proefdag]', 'a[href]']) assert.ok(js.includes(doel), doel);
});

test("pakket voor mijn.bsosporty.nl: Mijn BSO en de opmaak, niet de website", () => {
  const bestanden = bepaalMijnBsoBestanden();
  for (const pad of ["beheer/index.php", "beheer/website-status.php", "beheer/.htaccess", "css/style.css", "design/tokens.css", "assets/fonts/dm-sans-latin-400.woff2", "assets/img/sporty-woordmerk.svg", "favicon.ico"]) {
    assert.ok(bestanden.includes(pad), `${pad} ontbreekt`);
  }
  assert.ok(!bestanden.some((p) => p.endsWith(".html") && !p.startsWith("beheer/")), "geen pagina's van de website");
  assert.ok(!bestanden.some((p) => /^beheer\/(config\.php|data\/.+\.sqlite)/.test(p)), "geen configuratie of gegevens");
  const ht = lees("tools/mijnbso/.htaccess");
  assert.match(ht, /RewriteRule \^\$ https:\/\/%\{HTTP_HOST\}\/beheer\/ \[R=302,L\]/);
  // Achter de https-server van Vimexx: geen doorverwijzingslus als X-Forwarded-Proto https zegt
  assert.match(ht, /RewriteCond %\{HTTP:X-Forwarded-Proto\} !\^https \[NC\]\n(?:.*\n)?\s*RewriteRule \^ https:/);
  assert.match(lees("tools/mijnbso/robots.txt"), /Disallow: \//);
});

test("Mijn BSO met website_url: links naar de website, formulieren van de website, schakelaar zonder bestand", (t) => {
  try {
    execFileSync("php", ["-r", "exit(extension_loaded('pdo_sqlite') && extension_loaded('sodium') && extension_loaded('intl') ? 0 : 1);"], { stdio: "ignore" });
  } catch {
    return t.skip("php (met pdo_sqlite, sodium en intl) is niet geïnstalleerd");
  }
  const map = mkdtempSync(join(tmpdir(), "sporty-elders-"));
  t.after(() => rmSync(map, { recursive: true, force: true }));
  mkdirSync(join(map, "webmap"));
  const config = join(map, "config.php");
  writeFileSync(config, `<?php return ['data_dir' => ${JSON.stringify(join(map, "data"))}, 'website_map' => ${JSON.stringify(join(map, "webmap"))}, 'ontwikkelmodus' => true, 'mail' => ['methode' => 'log'], 'app_url' => '${MIJN}/beheer', 'website_url' => 'https://bsosporty.nl/'];\n`);
  const uit = JSON.parse(execFileSync("php", ["tests/php/website-elders.php"], { cwd: new URL("..", import.meta.url), env: { ...process.env, VCK_BEHEER_CONFIG: config }, encoding: "utf8" }));
  assert.equal(uit.elders, true);
  assert.equal(uit.website_url, "https://bsosporty.nl", "zonder / aan het eind");
  assert.deepEqual(uit.hosts.sort(), ["bsosporty.nl", "www.bsosporty.nl"]);
  assert.equal(uit.pagina_nl, "https://bsosporty.nl/blijf-op-de-hoogte.html");
  assert.equal(uit.pagina_en, "https://bsosporty.nl/en/stay-in-the-loop.html");
  assert.equal(uit.home, "https://bsosporty.nl/");
  assert.equal(uit.link, "https://bsosporty.nl/privacy.html");
  assert.equal(uit.adres_en, "https://bsosporty.nl/en/");
  assert.deepEqual(uit.herkomst, { website: true, www: true, mijn: true, null: true, leeg: true, vreemd: false, "lijkt erop": false });
  assert.equal(uit.binnenkort_bij_start, true);
  assert.deepEqual(uit.live, [0, 0, 0]);
  assert.equal(uit.binnenkort_na_live, false);
  assert.equal(uit.binnenkort_weer_aan, true);
  assert.equal(uit.geen_bestand, true, "met de website elders zet Mijn BSO geen binnenkort.aan neer");
});
