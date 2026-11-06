// Koppelingen in Mijn BSO (beheer/koppelingen.php): geheimen alleen gemaskeerd, e-mail via SMTP goed opgebouwd.
// Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync, readdirSync } from "node:fs";
import { execFileSync } from "node:child_process";

const lees = (pad) => readFileSync(new URL("../" + pad, import.meta.url), "utf8");
const heeftPhp = () => {
  try {
    execFileSync("php", ["-v"], { stdio: "ignore" });
    return true;
  } catch {
    return false;
  }
};
const php = (code) => JSON.parse(execFileSync("php", ["-r", `require 'beheer/inc/koppelingen.php'; require 'beheer/inc/smtp.php'; ${code}`], { cwd: new URL("..", import.meta.url), encoding: "utf8" }));

test("een geheim is alleen gemaskeerd te zien", (t) => {
  if (!heeftPhp()) return t.skip("php is niet geïnstalleerd");
  // Nepsleutels, in stukjes opgebouwd zodat geen scanner ze voor echte sleutels aanziet
  const nepSleutel = ["sk", "live", "nepsleutel".repeat(3) + "9a3f"].join("_");
  const nepWebhook = ["whsec", "nepgeheim".repeat(3) + "1234"].join("_");
  const uit = php(`echo json_encode([
    koppeling_masker('stripe.geheime_sleutel', '${nepSleutel}'),
    koppeling_masker('stripe.webhook_geheim', '${nepWebhook}'),
    koppeling_masker('smtp.wachtwoord', 'een-heel-lang-wachtwoord-voor-de-mailbox'),
    koppeling_masker('stripe.geheime_sleutel', 'kort'),
    koppeling_masker('stripe.geheime_sleutel', ''),
  ]);`);
  assert.deepEqual(uit, ["sk_live_…9a3f", "whsec_…1234", "••••••••", "••••••••", ""]);
});

test("een e-mail via SMTP heeft nette koppen en een veilige inhoud", (t) => {
  if (!heeftPhp()) return t.skip("php is niet geïnstalleerd");
  const bericht = php(`echo json_encode(smtp_bericht('info@sporty.nl', 'Sporty "BSO"', 'ouder@example.nl', 'team@sporty.nl', "Welkom bij Sporty, René!\\r\\nBcc: x@y.nl", ".punt aan het begin\\nregel twee met € en é"));`);
  const [kop, ...rest] = bericht.split("\r\n\r\n");
  assert.ok(!/(^|[^\r])\n/.test(bericht), "alle regels eindigen op CRLF");
  assert.match(kop, /^From: "Sporty \\"BSO\\"" <info@sporty\.nl>$/m);
  assert.match(kop, /^To: <ouder@example\.nl>$/m);
  assert.match(kop, /^Reply-To: <team@sporty\.nl>$/m);
  assert.match(kop, /^Subject: Welkom bij Sporty, =\?UTF-8\?Q\?Ren=C3=A9/m, "onderwerp met é is gecodeerd");
  assert.doesNotMatch(kop, /^Bcc:/m, "een regeleinde in het onderwerp kan geen kop toevoegen");
  assert.match(kop, /^Content-Transfer-Encoding: base64$/m);
  const inhoud = Buffer.from(rest.join("").replace(/\r\n/g, ""), "base64").toString();
  assert.equal(inhoud, ".punt aan het begin\r\nregel twee met € en é");
  assert.ok(rest.join("\r\n\r\n").split("\r\n").every((r) => r.length <= 76 && !r.startsWith(".")), "korte regels, geen punt vooraan");
});

test("Stripe en e-mail lezen hun instellingen uit Koppelingen, en alleen met het recht Koppelingen", () => {
  const code = [...readdirSync(new URL("../beheer/", import.meta.url)).filter((f) => f.endsWith(".php")).map((f) => "beheer/" + f),
    ...readdirSync(new URL("../beheer/inc/", import.meta.url)).filter((f) => f.endsWith(".php")).map((f) => "beheer/inc/" + f)];
  for (const pad of code) {
    assert.doesNotMatch(lees(pad), /cfg\('(stripe\.(geheime_sleutel|webhook_geheim|methoden)|mail\.|smtp\.|team_email)/, `${pad} leest een koppeling rechtstreeks uit config.php; gebruik koppeling()`);
  }
  assert.match(lees("beheer/koppelingen.php"), /vereis_recht\('koppelingen'\)/);
  assert.match(lees("beheer/inc/rechten.php"), /'koppelingen' => \['koppelingen\.php'/);
  assert.match(lees("beheer/inc/database.php"), /INSERT OR IGNORE INTO team_rechten \(gebruiker_id, recht\) SELECT id, 'koppelingen'/);
});
