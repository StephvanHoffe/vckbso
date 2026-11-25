// Verzonden e-mails in Mijn BSO (E-mails → Verzonden, beheer/verzonden-emails.php): elke e-mail wordt vastgelegd
// met de uitkomst (verstuurd, mislukt met de reden, testmodus of overgeslagen), zonder inhoud, en verdwijnt na de
// bewaartermijn. De aanmeldmail heeft een link om meteen een wachtwoord te kiezen (november 2026).
// Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { execFileSync } from "node:child_process";
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";

const lees = (pad) => readFileSync(new URL("../" + pad, import.meta.url), "utf8");
const heeftPhp = () => {
  try {
    execFileSync("php", ["-r", "exit(extension_loaded('pdo_sqlite') && extension_loaded('sodium') && extension_loaded('intl') ? 0 : 1);"], { stdio: "ignore" });
    return true;
  } catch {
    return false;
  }
};

test("e-mails: vastgelegd met de uitkomst, zonder inhoud, met waarschuwing en bewaartermijn", (t) => {
  if (!heeftPhp()) return t.skip("php (met pdo_sqlite, sodium en intl) is niet geïnstalleerd");
  const map = mkdtempSync(join(tmpdir(), "sporty-emails-"));
  t.after(() => rmSync(map, { recursive: true, force: true }));
  const config = join(map, "config.php");
  writeFileSync(config, `<?php return ['data_dir' => ${JSON.stringify(join(map, "data"))}, 'ontwikkelmodus' => true, 'app_url' => 'https://mijn.example/beheer'];\n`);
  const uit = JSON.parse(execFileSync("php", ["tests/php/emails.php"], {
    cwd: new URL("..", import.meta.url),
    env: { ...process.env, VCK_BEHEER_CONFIG: config },
    encoding: "utf8",
    stdio: ["ignore", "pipe", "ignore"],
  }));

  assert.equal(uit.methode, "log", "zonder instellingen staat e-mail in testmodus");
  assert.equal(uit.waarschuwing_testmodus, "testmodus", "in testmodus waarschuwt Mijn BSO dat er niets de deur uit gaat");
  assert.ok(uit.maillog_bestand_heeft_link, "de aanmeldmail heeft de link om een wachtwoord te kiezen");
  assert.match(uit.aangepast_met_link, /Hoi Sam, leuk!\n\nhttps:\/\/mijn\.example\/beheer\/wachtwoord-instellen\.php\?token=abc$/, "een oude aangepaste tekst krijgt de verplichte link er onderaan bij");
  assert.match(uit.controle_zonder_link.tekst ?? "", /\{link\} moet in de tekst blijven staan/, "een nieuwe tekst zonder {link} wordt niet opgeslagen");
  assert.equal(uit.gelukt_smtp, false);
  assert.equal(uit.waarschuwing_mislukt, "mislukt", "na een mislukte e-mail (zonder geslaagde daarna) waarschuwt Mijn BSO");
  assert.deepEqual(uit.log.map((r) => [r.aan, r.sleutel, r.status]), [
    ["ouder@test.invalid", "aanmelding_ontvangen", "testmodus"],
    ["veel@test.invalid", "aanmelding_ontvangen", "overgeslagen"],
    ["ouder@test.invalid", "wachtwoord_vergeten", "mislukt"],
  ]);
  assert.ok(uit.log.every((r) => !r.fout_leeg), "bij testmodus, overgeslagen en mislukt staat de reden erbij");
  assert.equal(uit.inhoud_bewaard, 0, "de inhoud (met de wachtwoordlink) wordt niet bewaard");
  assert.ok(!uit.na_bewaarbeleid.includes("oud@test.invalid"), "na de bewaartermijn verdwijnen verzonden e-mails");
  assert.equal(uit.na_bewaarbeleid.length, 3);
  assert.ok(uit.rechten, "E-mails is een eigen onderdeel met een eigen recht");
});

test("aanmelden: meteen een e-mail met een link om een wachtwoord te kiezen (NL en EN)", () => {
  const aanvraag = lees("beheer/aanvraag.php");
  assert.match(aanvraag, /'link' => maak_wachtwoordlink\(\$ouderId, 24 \* 7\)/, "de aanmeldmail krijgt een wachtwoordlink van een week");
  assert.doesNotMatch(aanvraag, /if \(te_veel_mails_naar\([^)]*\)\) \{\s*aanvraag_antwoord/, "bij te veel e-mails wordt de aanmelding toch bewaard");
  const teksten = lees("beheer/inc/mailteksten.php");
  for (const sleutel of ["aanmelding_ontvangen", "aanmelding_ontvangen_en"]) {
    const begin = teksten.indexOf(`    '${sleutel}' => [\n`);
    const blok = teksten.slice(begin, teksten.indexOf("\n    ],\n", begin));
    assert.match(blok, /'verplicht' => \['link'\]/, `${sleutel}: de link is verplicht`);
    assert.match(blok, /\{link\}/, `${sleutel}: de link staat in de standaardtekst`);
  }
  assert.match(lees("aanmelden.html"), /link om meteen je wachtwoord voor Mijn BSO aan te maken/);
  assert.match(lees("en/join-the-club.html"), /link to create your password for Mijn BSO straight away/);
  assert.match(lees("beheer/wachtwoord-instellen.php"), /t\('Wachtwoord instellen', 'Set your password'\)/, "de wachtwoordpagina is er ook in het Engels");
});

test("E-mails staat in het menu, met teksten en verzonden e-mails", () => {
  assert.match(lees("beheer/inc/rechten.php"), /'emails' => \['mailteksten\.php', 'E-mails'/);
  assert.match(lees("beheer/mailteksten.php"), /vereis_recht\('emails'\)/);
  assert.match(lees("beheer/verzonden-emails.php"), /vereis_recht\('emails'\)/);
  assert.match(lees("beheer/inc/database.php"), /SELECT gebruiker_id, 'emails' FROM team_rechten WHERE recht = 'instellingen'/, "wie Instellingen had, krijgt E-mails erbij");
});
