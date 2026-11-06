// Alle e-mails van Mijn BSO zijn aan te passen onder Instellingen → E-mails (beheer/mailteksten.php).
// Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync, readdirSync } from "node:fs";
import { execFileSync } from "node:child_process";

const lees = (pad) => readFileSync(new URL("../" + pad, import.meta.url), "utf8");
const phpBestanden = [
  ...readdirSync(new URL("../beheer/", import.meta.url)).filter((f) => f.endsWith(".php")).map((f) => "beheer/" + f),
  ...readdirSync(new URL("../beheer/inc/", import.meta.url)).filter((f) => f.endsWith(".php")).map((f) => "beheer/inc/" + f),
];
const teksten = lees("beheer/inc/mailteksten.php");
const sleutels = new Set([...teksten.matchAll(/^ {4}'([a-z_]+)' => \[$/gm)].map((m) => m[1]));

test("alle e-mails gaan via een aanpasbare tekst", () => {
  for (const pad of phpBestanden) {
    if (pad === "beheer/inc/mail.php" || pad === "beheer/inc/mailteksten.php") continue;
    const code = lees(pad);
    // De testmail op de pagina E-mails verstuurt de tekst uit het formulier zelf; die op Koppelingen is een technische test
    const direct = [...code.matchAll(/\b(stuur_mail|mail_team)\(/g)].length - (pad === "beheer/mailteksten.php" || pad === "beheer/koppelingen.php" ? 1 : 0);
    assert.equal(direct, 0, `${pad} verstuurt een e-mail zonder aanpasbare tekst; gebruik stuur_mailtekst() of mail_team_tekst()`);
    for (const [, sleutel] of code.matchAll(/(?:stuur_mailtekst\([^,]+|mail_team_tekst\()\s*,?\s*'([a-z_]+)'/g)) {
      assert.ok(sleutels.has(sleutel), `${pad}: de e-mail '${sleutel}' staat niet in beheer/inc/mailteksten.php`);
    }
  }
  assert.ok(sleutels.size >= 20, "de lijst met e-mails is leeg of onvolledig");
});

test("de standaardteksten zijn geldig: geen onbekende invulvelden, de verplichte staan erin", (t) => {
  try {
    execFileSync("php", ["-v"], { stdio: "ignore" });
  } catch {
    t.skip("php is niet geïnstalleerd");
    return;
  }
  const uit = execFileSync("php", ["-r", `
    function instelling() { return ''; } function rijen() { return []; }
    require 'beheer/inc/mailteksten.php';
    $fout = [];
    foreach (MAILTEKSTEN as $s => $d) {
      if (!isset(MAILTEKST_GROEPEN[$d['groep']])) $fout[] = "$s: onbekende groep";
      if ($f = mailtekst_controleer($s, (string) $d['onderwerp'], $d['tekst'])) $fout[] = "$s: " . implode(' ', $f);
      foreach (array_keys($d['velden']) as $veld) if (!str_contains($d['tekst'] . $d['onderwerp'], '{' . $veld . '}')) $fout[] = "$s: {" . $veld . "} wordt niet gebruikt";
      [$o, $t] = mailtekst($s, mailtekst_voorbeeldwaarden($s));
      if (preg_match('/\\{[a-z_]+\\}/', $o . $t)) $fout[] = "$s: niet alles ingevuld";
    }
    echo json_encode($fout);
  `], { cwd: new URL("..", import.meta.url), encoding: "utf8" });
  assert.deepEqual(JSON.parse(uit), []);
});
