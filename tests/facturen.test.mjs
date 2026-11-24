// Mijn BSO, facturen (beheer/inc/facturen.php): het Sporty Abonnement is een vast bedrag per maand, Incidenteel
// rekent per dag (4,5 uur op een schooldag, 10,5 uur op een vakantie- of studiedag) en kosteloos afmelden kan tot
// 48 uur van tevoren (antwoorden van Sporty, november 2026).
// Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { execFileSync } from "node:child_process";
import { mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";

const heeftPhp = () => {
  try {
    execFileSync("php", ["-r", "exit(extension_loaded('pdo_sqlite') && extension_loaded('sodium') && extension_loaded('intl') ? 0 : 1);"], { stdio: "ignore" });
    return true;
  } catch {
    return false;
  }
};

test("facturen: abonnement vast per maand, incidenteel per dag, te laat afmelden telt mee", (t) => {
  if (!heeftPhp()) return t.skip("php (met pdo_sqlite, sodium en intl) is niet geïnstalleerd");
  const map = mkdtempSync(join(tmpdir(), "sporty-facturen-"));
  t.after(() => rmSync(map, { recursive: true, force: true }));
  const config = join(map, "config.php");
  writeFileSync(config, `<?php return ['data_dir' => ${JSON.stringify(join(map, "data"))}, 'ontwikkelmodus' => true, 'mail' => ['methode' => 'log']];\n`);
  const uit = JSON.parse(execFileSync("php", ["tests/php/facturen.php"], {
    cwd: new URL("..", import.meta.url),
    env: { ...process.env, VCK_BEHEER_CONFIG: config },
    encoding: "utf8",
  }));

  assert.equal(uit.aantal, 3, "drie ouders krijgen een factuur (niet de nieuwe aanmelding en niet zonder abonnement)");
  assert.equal(uit.nogmaals, 0, "een tweede keer maakt geen dubbele facturen");
  assert.deepEqual(uit.zonder_abonnement, ["Bram"]);

  const abonnement = uit.facturen["Ouder Abonnement"];
  assert.equal(abonnement.bedrag_cent, 52650, "2 vaste dagen x 27 uur x € 9,75 = € 526,50");
  assert.equal(abonnement.regels.length, 1);
  assert.match(abonnement.regels[0].omschrijving, /^Sporty Abonnement Noor Test, maart 2027: 2 vaste dagen per week × 27 uur$/);
  assert.equal(abonnement.regels[0].aantal, 54);
  assert.equal(abonnement.regels[0].prijs_cent, 975);

  const incidenteel = uit.facturen["Ouder Incidenteel"];
  assert.deepEqual(incidenteel.regels.map((r) => [r.aantal, r.prijs_cent, r.bedrag_cent]), [
    [13.5, 1200, 16200], // 3 schooldagen (bevestigd, ziek en te laat afgemeld) x 4,5 uur x € 12
    [10.5, 1200, 12600], // 1 vakantiedag x 10,5 uur x € 12
  ]);
  assert.match(incidenteel.regels[0].omschrijving, /3 schooldagen × 4,5 uur \(waarvan 1 minder dan 48 uur van tevoren afgemeld\)/);
  assert.match(incidenteel.regels[1].omschrijving, /1 vakantie- of studiedag × 10,5 uur$/);
  assert.equal(incidenteel.bedrag_cent, 28800);
  assert.deepEqual(uit.gefactureerd, [1, 1, 1, 1, 0], "de op tijd afgemelde dag blijft ongefactureerd");

  const halverwege = uit.facturen["Ouder Halverwege"];
  assert.equal(halverwege.bedrag_cent, Math.round(27 * 975 * 16 / 31), "naar rato: 16 van de 31 dagen");
  assert.match(halverwege.regels[0].omschrijving, /\(naar rato: 16 van de 31 dagen\)$/);

  assert.equal(uit.totaal, 52650 + 28800 + Math.round(27 * 975 * 16 / 31));
  assert.deepEqual(uit.te_laat, { "precies 48 uur": false, "net te laat": true, "hele dag om 08.00": true });
});
