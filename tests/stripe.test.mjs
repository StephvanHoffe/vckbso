// Betalen via Stripe (beheer/inc/stripe.php): de handtekening van de webhook, de velden naar de API en de documentatie.
// Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync, readdirSync } from "node:fs";
import { execFileSync } from "node:child_process";
import { createHmac } from "node:crypto";

const lees = (pad) => readFileSync(new URL("../" + pad, import.meta.url), "utf8");
const heeftPhp = () => {
  try {
    execFileSync("php", ["-v"], { stdio: "ignore" });
    return true;
  } catch {
    return false;
  }
};
const php = (code) => JSON.parse(execFileSync("php", ["-r", `require 'beheer/inc/stripe.php'; ${code}`], { cwd: new URL("..", import.meta.url), encoding: "utf8" }));

test("de webhook accepteert alleen een geldige, verse handtekening van Stripe", (t) => {
  if (!heeftPhp()) return t.skip("php is niet geïnstalleerd");
  const geheim = "whsec_test";
  const inhoud = JSON.stringify({ id: "evt_1", type: "payment_intent.succeeded", data: { object: { id: "pi_1" } } });
  const nu = 1_800_000_000;
  const teken = (tijd, sleutel = geheim) => createHmac("sha256", sleutel).update(`${tijd}.${inhoud}`).digest("hex");
  const gevallen = {
    geldig: `t=${nu},v1=${teken(nu)}`,
    "geldig, met een tweede (oud) geheim ernaast": `t=${nu},v1=${teken(nu, "whsec_oud")},v1=${teken(nu)}`,
    "ander geheim": `t=${nu},v1=${teken(nu, "whsec_fout")}`,
    "te oud": `t=${nu - 301},v1=${teken(nu - 301)}`,
    "zonder tijd": `v1=${teken(nu)}`,
    "alleen v0": `t=${nu},v0=${teken(nu)}`,
    leeg: "",
  };
  const b64 = (x) => Buffer.from(x).toString("base64");
  const uit = php(`$inhoud = base64_decode('${b64(inhoud)}');
    echo json_encode(array_map(fn ($kop) => stripe_handtekening_ok($inhoud, $kop, '${geheim}', ${nu}), json_decode(base64_decode('${b64(JSON.stringify(gevallen))}'), true)));`);
  assert.deepEqual(uit, {
    geldig: true,
    "geldig, met een tweede (oud) geheim ernaast": true,
    "ander geheim": false,
    "te oud": false,
    "zonder tijd": false,
    "alleen v0": false,
    leeg: false,
  });
  assert.equal(php(`echo json_encode(stripe_handtekening_ok('x', 't=1,v1=abc', '', 1));`), false, "zonder ingesteld geheim nooit geldig");
});

test("geneste velden gaan in de vorm die Stripe verwacht", (t) => {
  if (!heeftPhp()) return t.skip("php is niet geïnstalleerd");
  const uit = php(`echo json_encode(urldecode(stripe_query([
    'mode' => 'payment', 'confirm' => true, 'off_session' => false, 'leeg' => null,
    'payment_method_types' => ['ideal', 'sepa_debit'],
    'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => 6789]]],
    'metadata' => ['factuur_id' => 12],
    'success_url' => 'https://x.nl/f.php?id=1&sessie={CHECKOUT_SESSION_ID}',
  ])));`);
  assert.equal(uit, [
    "mode=payment", "confirm=true", "off_session=false",
    "payment_method_types[]=ideal", "payment_method_types[]=sepa_debit",
    "line_items[0][quantity]=1", "line_items[0][price_data][currency]=eur", "line_items[0][price_data][unit_amount]=6789",
    "metadata[factuur_id]=12",
    "success_url=https://x.nl/f.php?id=1&sessie={CHECKOUT_SESSION_ID}",
  ].join("&"));
});

test("Mollie is helemaal vervangen door Stripe", () => {
  const bestanden = [
    ...readdirSync(new URL("../beheer/", import.meta.url)).filter((f) => f.endsWith(".php")).map((f) => "beheer/" + f),
    ...readdirSync(new URL("../beheer/inc/", import.meta.url)).filter((f) => f.endsWith(".php") && f !== "database.php").map((f) => "beheer/inc/" + f),
    "privacy.html", "README.md",
  ];
  for (const pad of bestanden) {
    assert.doesNotMatch(lees(pad), /mollie/i, `${pad} noemt Mollie nog`);
  }
  const csp = lees("beheer/inc/bootstrap.php").match(/form-action [^"]+/)[0];
  assert.match(csp, /https:\/\/checkout\.stripe\.com/, "de betaalpagina van Stripe moet in form-action staan");
});

test("de handleiding noemt precies de gebeurtenissen die de webhook verwerkt", () => {
  const events = [...lees("beheer/inc/stripe.php").match(/const STRIPE_EVENTS = \[([\s\S]*?)\];/)[1].matchAll(/'([a-z_.]+)'/g)].map((m) => m[1]);
  assert.ok(events.length >= 8);
  const regel = lees("docs/beheeromgeving.md").split("\n").find((r) => r.includes("met deze gebeurtenissen:"));
  const genoemd = [...regel.split("met deze gebeurtenissen:")[1].split(". Zet ")[0].matchAll(/`([a-z_.]+)`/g)].map((m) => m[1]).filter((e) => !e.endsWith(".php"));
  assert.deepEqual([...new Set(genoemd)].sort(), [...events].sort());
});
