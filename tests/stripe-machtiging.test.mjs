// Een machtiging starten bij Stripe (beheer/inc/stripe.php), tegen een nagebootste Stripe (tests/php/stripe-nagebootst.php).
// November 2026 lukte een machtiging niet ("Het lukte even niet om naar je bank te gaan"). Nu stuurt Mijn BSO altijd
// een vaste API-versie mee, vervangt het een klant die Stripe niet (meer) kent, en ziet het team de fout met uitleg.
// Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { execFileSync, spawn } from "node:child_process";
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { createServer } from "node:net";
import { tmpdir } from "node:os";
import { join } from "node:path";

const heeftPhp = () => {
  try {
    execFileSync("php", ["-r", "exit(extension_loaded('pdo_sqlite') && extension_loaded('sodium') && extension_loaded('intl') && extension_loaded('curl') ? 0 : 1);"], { stdio: "ignore" });
    return true;
  } catch {
    return false;
  }
};
const vrijePoort = () => new Promise((klaar) => {
  const server = createServer().listen(0, "127.0.0.1", () => { const { port } = server.address(); server.close(() => klaar(port)); });
});

test("machtiging: vaste API-versie, nieuwe klant als de oude onbekend is, en een fout met uitleg voor het team", async (t) => {
  if (!heeftPhp()) return t.skip("php (met pdo_sqlite, sodium, intl en curl) is niet geïnstalleerd");
  const map = mkdtempSync(join(tmpdir(), "sporty-stripe-"));
  const poort = await vrijePoort();
  const repo = new URL("..", import.meta.url).pathname;
  const nagebootst = spawn("php", ["-S", `127.0.0.1:${poort}`, "tests/php/stripe-nagebootst.php"], { cwd: repo, env: { ...process.env, STRIPE_MAP: map }, stdio: "ignore" });
  t.after(() => { nagebootst.kill(); rmSync(map, { recursive: true, force: true }); });
  // Wachten tot de nagebootste Stripe luistert
  for (let i = 0; i < 50; i++) {
    try { await fetch(`http://127.0.0.1:${poort}/v1/klaar`); break; } catch { await new Promise((r) => setTimeout(r, 100)); }
  }
  const config = join(map, "config.php");
  writeFileSync(config, `<?php return ['data_dir' => ${JSON.stringify(join(map, "data"))}, 'ontwikkelmodus' => true, 'app_url' => 'https://mijn.example/beheer'];\n`);
  const uit = JSON.parse(execFileSync("php", ["tests/php/stripe-machtiging.php"], {
    cwd: repo,
    env: { ...process.env, VCK_BEHEER_CONFIG: config, STRIPE_API_ADRES: `http://127.0.0.1:${poort}/v1/`, STRIPE_MAP: map },
    encoding: "utf8",
    stdio: ["ignore", "pipe", "ignore"],
  }));
  const verzoeken = readFileSync(join(map, "verzoeken.jsonl"), "utf8").trim().split("\n").map((r) => JSON.parse(r)).filter((v) => v.pad !== "/v1/klaar");

  assert.equal(uit.url, "https://checkout.stripe.com/c/pay/cs_test_1", "de ouder gaat naar de betaalpagina van Stripe");
  assert.ok(verzoeken.every((v) => v.versie === "2024-06-20"), "elk verzoek heeft de vaste API-versie");
  const [eerste, klant, tweede] = verzoeken;
  assert.deepEqual([eerste.pad, klant.pad, tweede.pad], ["/v1/checkout/sessions", "/v1/customers", "/v1/checkout/sessions"], "onbekende klant: nieuwe klant en één keer opnieuw");
  assert.match(klant.sleutel, /-klant-\d+-opnieuw-\d+$/, "de nieuwe klant krijgt een eigen sleutel (anders geeft Stripe de oude terug)");
  assert.equal(tweede.velden.customer, "cus_nieuw");
  assert.equal(tweede.velden.mode, "setup");
  assert.deepEqual(tweede.velden.payment_method_types, ["ideal", "sepa_debit"]);
  assert.equal(uit.klant_na, "cus_nieuw");
  assert.equal(uit.status_na, "in_behandeling");
  assert.equal(uit.waarschuwing_na_gelukt, null, "geen waarschuwing als het lukte");

  assert.match(uit.fout, /payment method type "ideal" is invalid/);
  assert.match(uit.uitleg, /Instellingen → Betaalmethoden iDEAL \| Wero en SEPA-incasso aan/, "het team krijgt uitleg in gewone taal");
  assert.match(uit.waarschuwing ?? "", /Machtiging starten/, "de waarschuwing op Vandaag noemt waar het misging");
});

test("machtiging: de ouder ziet een vriendelijke melding, het team de echte fout", () => {
  const machtiging = readFileSync(new URL("../beheer/machtiging.php", import.meta.url), "utf8");
  assert.match(machtiging, /stripe_fout_vastleggen\('Machtiging starten'/);
  const koppelingen = readFileSync(new URL("../beheer/koppelingen.php", import.meta.url), "utf8");
  assert.match(koppelingen, /machtiging_sessie_data\(null, \['soort' => 'test'\]\)/, "Verbinding testen probeert ook een machtiging");
  assert.match(koppelingen, /stripe_laatste_fout/, "Koppelingen toont de laatste fout");
});
