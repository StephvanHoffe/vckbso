// De binnenkort-pagina (coming soon, november 2026): binnenkort.html en en/coming-soon.html, de schakelaar in .htaccess
// (het bestand binnenkort.aan), en in Mijn BSO de livegang met een automatische mail (beheer/inc/binnenkort.php).
// Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { execFileSync } from "node:child_process";
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join, posix } from "node:path";
import { BINNENKORT } from "../tools/site/talen.mjs";
import { bepaalBestanden } from "../tools/pakket.mjs";

const lees = (pad) => readFileSync(new URL("../" + pad, import.meta.url), "utf8");
const htaccess = lees(".htaccess");
const paginas = Object.entries(BINNENKORT).flat();

/** De RewriteRules van de binnenkort-pagina, als JavaScript-regex (Apache gebruikt PCRE; deze patronen werken in beide). */
const regels = [...htaccess.matchAll(/RewriteCond %\{DOCUMENT_ROOT\}\/binnenkort\.aan -f\n\s+RewriteCond %\{HTTP_COOKIE\} [^\n]+\n\s+RewriteRule (\S+) (\S+)(?: \[([^\]]+)\])?/g)]
  .map(([, patroon, doel, vlaggen = ""]) => ({ patroon: new RegExp(patroon), doel, vlaggen }));

/** Wat Apache doet met een pad (zonder / vooraan) zolang binnenkort.aan er staat: [doel, vlaggen] van de eerste regel die past. */
const binnenkortRegel = (pad) => {
  const regel = regels.find((r) => r.patroon.test(pad));
  return regel ? [regel.doel, regel.vlaggen] : null;
};

test(".htaccess: met binnenkort.aan ziet een bezoeker alleen de binnenkort-pagina", () => {
  assert.equal(regels.length, 5, "vijf regels voor de binnenkort-pagina");
  assert.deepEqual(binnenkortRegel(""), ["binnenkort.html", "L"]);
  assert.deepEqual(binnenkortRegel("en/"), ["en/coming-soon.html", "L"]);
  for (const pad of ["contact.html", "aanmelden.html", "locaties/sportpark-middenmeer.html", "over-ons.html", "binnenkort.aan"]) {
    assert.deepEqual(binnenkortRegel(pad), ["/", "R=302,L"], pad);
  }
  for (const pad of ["en/contact.html", "en/locations/sportpark-middenmeer.html"]) {
    assert.deepEqual(binnenkortRegel(pad), ["/en/", "R=302,L"], pad);
  }
  assert.deepEqual(binnenkortRegel("sitemap.xml"), ["-", "R=404,L"], "geen sitemap zolang de website niet live is");
  // Mijn BSO, de bestanden van de pagina en de privacyverklaring blijven bereikbaar
  for (const pad of ["beheer", "beheer/", "beheer/inloggen.php", "beheer/op-de-hoogte.php", "css/style.css", "js/main.js", "privacy.html", "en/privacy.html", "robots.txt", "404.html", ...paginas]) {
    assert.equal(binnenkortRegel(pad), null, `${pad} hoort bereikbaar te blijven`);
  }
  // Het team kan met het cookie uit Mijn BSO de echte website zien
  assert.equal((htaccess.match(/RewriteCond %\{HTTP_COOKIE\} !\(\^\|;\\s\*\)sporty_voorproef=1\(;\|\$\)/g) || []).length, 5);
  // Na de livegang stuurt de binnenkort-pagina zelf door
  assert.match(htaccess, /RewriteCond %\{DOCUMENT_ROOT\}\/binnenkort\.aan !-f\n\s+RewriteRule \^binnenkort\\\.html\$ \/ \[R=302,L\]/);
  assert.match(htaccess, /RewriteCond %\{DOCUMENT_ROOT\}\/binnenkort\.aan !-f\n\s+RewriteRule \^en\/coming-soon\\\.html\$ \/en\/ \[R=302,L\]/);
});

test("alles waar de binnenkort-pagina naar linkt, is bereikbaar zolang hij aanstaat", () => {
  const inPakket = new Set(bepaalBestanden());
  for (const pagina of paginas) {
    assert.ok(inPakket.has(pagina), `${pagina} zit niet in het uploadpakket`);
    const html = lees(pagina);
    const refs = [
      ...[...html.matchAll(/\b(?:href|src)="([^"#]+)/g)].map((m) => m[1]),
      ...[...html.matchAll(/\bsrcset="([^"]+)"/g)].flatMap((m) => m[1].split(",").map((d) => d.trim().split(/\s+/)[0])),
    ].filter((r) => !/^(https?:|mailto:|tel:|data:)/.test(r));
    for (const ref of refs) {
      const pad = posix.normalize(posix.join(posix.dirname(pagina), ref.split("?")[0]));
      assert.equal(binnenkortRegel(pad), null, `${pagina} linkt naar ${ref}, maar dat stuurt de binnenkort-regel door`);
    }
  }
});

test("de binnenkort-pagina meldt aan bij Mijn BSO met bron binnenkort, en staat niet in de sitemap", () => {
  for (const pagina of paginas) {
    const html = lees(pagina);
    const form = html.match(/<form class="form binnenkort__form"[\s\S]*?<\/form>/)[0];
    assert.match(form, /action="(\.\.\/)?beheer\/op-de-hoogte\.php"/);
    assert.match(form, /<input type="hidden" name="bron" value="binnenkort">/);
    for (const veld of ["naam", "email", "straat", "postcode", "plaats", "toestemming"]) {
      assert.match(form, new RegExp(`name="${veld}"[^>]*required`), `${pagina}: ${veld} is verplicht`);
    }
    assert.match(html, /opent op 4 januari 2027|opens on 4 January 2027/);
    assert.doesNotMatch(html, /class="pop-up"/, "geen pop-up op de binnenkort-pagina");
  }
  assert.doesNotMatch(lees("sitemap.xml"), /binnenkort|coming-soon/);
  assert.match(lees("beheer/op-de-hoogte.php"), /\['pop-up', 'binnenkort'\]/, "Mijn BSO accepteert de bron binnenkort");
});

test("het uploadpakket zet de binnenkort-pagina alleen aan met --binnenkort", () => {
  const pakket = lees("tools/pakket.mjs");
  assert.match(pakket, /process\.argv\.includes\("--binnenkort"\)/);
  assert.ok(!bepaalBestanden().includes("binnenkort.aan"), "zonder --binnenkort staat binnenkort.aan niet in het pakket");
});

test("Mijn BSO: live zetten haalt binnenkort.aan weg en mailt wie zich op de binnenkort-pagina aanmeldde", (t) => {
  try {
    execFileSync("php", ["-r", "exit(extension_loaded('pdo_sqlite') && extension_loaded('sodium') && extension_loaded('intl') ? 0 : 1);"], { stdio: "ignore" });
  } catch {
    return t.skip("php (met pdo_sqlite, sodium en intl) is niet geïnstalleerd");
  }
  const map = mkdtempSync(join(tmpdir(), "sporty-binnenkort-"));
  t.after(() => rmSync(map, { recursive: true, force: true }));
  mkdirSync(join(map, "public_html"));
  const config = join(map, "config.php");
  writeFileSync(config, `<?php return ['data_dir' => ${JSON.stringify(join(map, "data"))}, 'website_map' => ${JSON.stringify(join(map, "public_html"))}, 'ontwikkelmodus' => true, 'mail' => ['methode' => 'log'], 'app_url' => 'https://test.invalid/beheer'];\n`);
  const uit = JSON.parse(execFileSync("php", ["tests/php/binnenkort.php"], {
    cwd: new URL("..", import.meta.url),
    env: { ...process.env, VCK_BEHEER_CONFIG: config },
    encoding: "utf8",
  }));

  assert.equal(uit.aan_bij_start, true);
  assert.deepEqual(uit.wachtend_voor_live, ["sam@test.invalid", "alex@test.invalid"], "alleen bevestigd en via de binnenkort-pagina");
  assert.deepEqual(uit.niet_live_geen_mail, [0, 0, 0], "geen mail zolang de website niet live is");
  assert.deepEqual(uit.live, [2, 0, 0]);
  assert.equal(uit.aan_na_live, false, "binnenkort.aan is weg");
  assert.equal(uit.live_op_gezet, true);
  assert.deepEqual(uit.mail_aan, ["sam@test.invalid", "alex@test.invalid"]);
  assert.ok(uit.nl_onderwerp && uit.nl_link, "Nederlandse mail met de link naar de website en een afmeldlink");
  assert.ok(uit.en_onderwerp && uit.en_link, "Engelse mail voor wie zich op de Engelse pagina aanmeldde");
  assert.deepEqual(uit.nogmaals, [0, 0, 0], "niemand krijgt de mail twee keer");
  assert.deepEqual(uit.later, [1, 0, 0], "wie later bevestigt, krijgt de mail alsnog");
  assert.equal(uit.later_aan, true);
  assert.equal(uit.weer_aan, true, "terug naar de binnenkort-pagina kan ook");
  assert.deepEqual(uit.pip_wacht_nu, ["pip@test.invalid"], "wie al op de lijst stond en zich via de binnenkort-pagina aanmeldt, krijgt de mail ook");
});
