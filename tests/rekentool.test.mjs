// Tests voor de rekentool kinderopvangtoeslag. Draaien met: npm test
import { test } from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import vm from "node:vm";

const hier = (pad) => new URL(pad, import.meta.url);
const context = vm.createContext({});
for (const bestand of ["../js/rekentool/toeslag-2026.js", "../js/rekentool/instellingen.js", "../js/rekentool/berekening.js"]) {
  vm.runInContext(readFileSync(hier(bestand), "utf8"), context, { filename: bestand });
}
const R = context.VCKRekentool;
const JAAR = context.VCKToeslag[2026];
const INSTELLINGEN = context.VCKRekentoolInstellingen;

// Tweede, onafhankelijke bron: de tabel uit de brochure van Dienst Toeslagen
const BROCHURE = readFileSync(hier("./bronnen/brochure-2026-tabel.txt"), "utf8")
  .split("\n")
  .filter((regel) => regel.startsWith("€"))
  .map((regel) => {
    const getallen = regel.match(/[\d.]+(?:,\d+)?/g);
    const euro = (t) => Number(t.replace(/\./g, ""));
    const procent = (t) => Math.round(Number(t.replace(",", ".")) * 100);
    return { van: euro(getallen[0]), tot: euro(getallen[1]), eerste: procent(getallen[2]), volgend: procent(getallen[3]) };
  });

const kind = (soort, uurtarief, uren) => ({ soort, uurtarief, uren });

/* ---------- Officiële gegevens ---------- */

test("de tabel is compleet en sluit aan", () => {
  assert.equal(R.controleerJaar(JAAR), true);
  assert.equal(JAAR.tabel.length, 69);
});

test("de tabel is gelijk aan de brochure van Dienst Toeslagen (tweede bron)", () => {
  assert.equal(BROCHURE.length, JAAR.tabel.length);
  BROCHURE.forEach((b, i) => {
    const [van, tot, eerste, volgend] = JAAR.tabel[i];
    assert.equal(van, b.van, `rij ${i + 1} vanaf`);
    assert.equal(tot === null ? 99999999 : tot, b.tot, `rij ${i + 1} tot en met`);
    assert.equal(eerste, b.eerste, `rij ${i + 1} 1e kind`);
    assert.equal(volgend, b.volgend, `rij ${i + 1} volgend kind`);
  });
});

test("maximale uurprijzen en uren 2026", () => {
  assert.deepEqual({ ...JAAR.maxUurprijs }, { dagopvang: 1123, bso: 998, gastouder: 849 });
  assert.equal(JAAR.maxUrenPerMaand, 230);
});

test("percentages dalen nooit bij een hoger inkomen en het volgende kind krijgt nooit minder dan het 1e", () => {
  JAAR.tabel.forEach(([, , eerste, volgend], i) => {
    assert.ok(volgend >= eerste, `rij ${i + 1}`);
    if (i > 0) {
      assert.ok(eerste <= JAAR.tabel[i - 1][2], `1e kind rij ${i + 1}`);
      assert.ok(volgend <= JAAR.tabel[i - 1][3], `volgend kind rij ${i + 1}`);
    }
  });
});

/* ---------- Officiële rekenvoorbeelden uit de brochure 2026 ---------- */

test("rekenvoorbeeld 1: één kind, dagopvang, inkomen € 60.000", () => {
  const r = R.bereken({ inkomen: 60000, werkt: true, kinderen: [kind("dagopvang", 1050, 12200)] }, JAAR);
  assert.equal(r.kinderen[0].uurprijs, 1050);
  assert.equal(r.kinderen[0].opvangkosten, 128100); // € 1.281
  assert.equal(r.kinderen[0].percentage, 9390); // 93,90%
  assert.equal(r.totaalToeslag, 120286); // € 1.202,86
  assert.equal(r.totaalToeslagUitbetaald, 120200); // € 1.202
});

test("rekenvoorbeeld 2: bso en dagopvang, allebei boven de maximale uurprijs, inkomen € 120.000", () => {
  const r = R.bereken({ inkomen: 120000, werkt: true, kinderen: [kind("bso", 1010, 6500), kind("dagopvang", 1245, 8700)] }, JAAR);
  const [oudste, jongste] = r.kinderen;
  assert.equal(jongste.positie, 1); // meeste uren
  assert.equal(jongste.uurprijs, 1123);
  assert.equal(jongste.opvangkosten, 97701); // € 977,01
  assert.equal(jongste.percentage, 6060);
  assert.equal(jongste.toeslag, 59207); // € 592,07
  assert.equal(oudste.positie, 2);
  assert.equal(oudste.uurprijs, 998);
  assert.equal(oudste.opvangkosten, 64870); // € 648,70
  assert.equal(oudste.percentage, 8790);
  assert.equal(oudste.toeslag, 57021); // € 570,21
  assert.equal(r.totaalToeslag, 116228); // € 1.162,28
  assert.equal(r.totaalToeslagUitbetaald, 116200);
});

test("rekenvoorbeeld 3: gastouder, 240 uur (maximaal 230), inkomen € 40.000", () => {
  const r = R.bereken({ inkomen: 40000, werkt: true, kinderen: [kind("gastouder", 825, 24000)] }, JAAR);
  assert.equal(r.kinderen[0].vergoedeUren, 23000);
  assert.equal(r.kinderen[0].opvangkosten, 189750); // € 1.897,50
  assert.equal(r.totaalToeslag, 182160); // € 1.821,60
  assert.equal(r.totaalToeslagUitbetaald, 182100);
  assert.equal(r.kinderen[0].kosten, 198000); // de opvang rekent wel alle 240 uur
});

test("rekenvoorbeeld 4: gastouder boven de maximale uurprijs, inkomen € 50.000", () => {
  const r = R.bereken({ inkomen: 50000, werkt: true, kinderen: [kind("gastouder", 952, 10800)] }, JAAR);
  assert.equal(r.kinderen[0].uurprijs, 849);
  assert.equal(r.kinderen[0].opvangkosten, 91692); // € 916,92
  assert.equal(r.totaalToeslag, 88024); // € 880,24
  assert.equal(r.totaalToeslagUitbetaald, 88000);
});

/* ---------- Regels ---------- */

test("elke grens van de tabel valt in de goede rij", () => {
  JAAR.tabel.forEach(([van, tot], i) => {
    assert.equal(R.vindRij(JAAR, van).index, i, `vanaf rij ${i + 1}`);
    if (tot !== null) {
      assert.equal(R.vindRij(JAAR, tot).index, i, `tot en met rij ${i + 1}`);
      assert.equal(R.vindRij(JAAR, tot + 1).index, i + 1, `net erboven rij ${i + 1}`);
    }
  });
  assert.equal(R.vindRij(JAAR, 99999999).index, 68);
  assert.throws(() => R.vindRij(JAAR, -1));
});

test("goedkoper dan het maximum: de eigen uurprijs telt", () => {
  const r = R.bereken({ inkomen: 30000, werkt: true, kinderen: [kind("bso", 950, 5000)] }, JAAR);
  assert.equal(r.kinderen[0].uurprijs, 950);
  assert.equal(r.kinderen[0].opvangkosten, 47500);
});

test("precies 230 uur en net erboven", () => {
  const precies = R.bereken({ inkomen: 30000, werkt: true, kinderen: [kind("bso", 998, 23000)] }, JAAR);
  const erboven = R.bereken({ inkomen: 30000, werkt: true, kinderen: [kind("bso", 998, 23001)] }, JAAR);
  assert.equal(precies.totaalToeslag, erboven.totaalToeslag);
  assert.equal(erboven.kinderen[0].vergoedeUren, 23000);
  assert.ok(erboven.kinderen[0].kosten > precies.kinderen[0].kosten);
});

test("het 1e kind is het kind met de meeste uren, ongeacht de volgorde van invullen", () => {
  const a = kind("bso", 998, 4000);
  const b = kind("bso", 998, 6000);
  const r1 = R.bereken({ inkomen: 100000, werkt: true, kinderen: [a, b] }, JAAR);
  const r2 = R.bereken({ inkomen: 100000, werkt: true, kinderen: [b, a] }, JAAR);
  assert.equal(r1.kinderen[1].positie, 1);
  assert.equal(r2.kinderen[0].positie, 1);
  assert.equal(r1.totaalToeslag, r2.totaalToeslag);
});

test("bij evenveel uren is het kind met de hoogste opvangkosten het 1e kind", () => {
  const r = R.bereken({ inkomen: 100000, werkt: true, kinderen: [kind("bso", 998, 8000), kind("dagopvang", 1123, 8000)] }, JAAR);
  assert.equal(r.kinderen[1].positie, 1); // dagopvang: hogere kosten
  assert.equal(r.kinderen[0].positie, 2);
});

test("de 230-uursgrens telt mee bij het bepalen van het 1e kind", () => {
  // 250 uur bso (vergoed 230) tegen 230 uur dagopvang (hogere kosten): gelijk aantal vergoede uren
  const r = R.bereken({ inkomen: 100000, werkt: true, kinderen: [kind("bso", 998, 25000), kind("dagopvang", 1123, 23000)] }, JAAR);
  assert.equal(r.kinderen[1].positie, 1);
});

test("zonder werk geen toeslag, wel de kosten", () => {
  const r = R.bereken({ inkomen: 30000, werkt: false, kinderen: [kind("bso", 998, 5000)] }, JAAR);
  assert.equal(r.totaalToeslag, 0);
  assert.equal(r.totaalNetto, r.totaalKosten);
  assert.equal(r.totaalKosten, 49900);
});

test("afronden op centen gaat half naar boven", () => {
  assert.equal(R.deelAfgerond(5, 10), 1); // 0,5 -> 1
  assert.equal(R.deelAfgerond(4, 10), 0);
  assert.equal(R.deelAfgerond(15, 10), 2); // 1,5 -> 2
  assert.equal(R.deelAfgerond(25, 10), 3); // 2,5 -> 3 (geen bankiersafronding)
  assert.throws(() => R.deelAfgerond(1.5, 10));
});

test("ongeldige invoer wordt geweigerd", () => {
  assert.throws(() => R.bereken({ inkomen: 30000, werkt: true, kinderen: [] }, JAAR));
  assert.throws(() => R.bereken({ inkomen: 30000, werkt: true, kinderen: [kind("peuter", 998, 100)] }, JAAR));
  assert.throws(() => R.bereken({ inkomen: 30000, werkt: true, kinderen: [kind("bso", 0, 100)] }, JAAR));
  assert.throws(() => R.bereken({ inkomen: 30000, werkt: true, kinderen: [kind("bso", 998, 99999)] }, JAAR));
  assert.throws(() => R.bereken({ inkomen: 1.5, werkt: true, kinderen: [kind("bso", 998, 100)] }, JAAR));
});

/* ---------- Invoer lezen ---------- */

test("bedragen en uren lezen zoals mensen ze typen", () => {
  const gevallen = {
    "45.000": 4500000, "45000": 4500000, "45.000,50": 4500050, "1.234.567": 123456700,
    "10,25": 1025, "10.25": 1025, "€ 10,25": 1025, " 10,5 ": 1050, ",5": 50, "3.5": 350, "12,345": 1235, "12,344": 1234,
    "": null, "abc": null, "-5": null, "1,2,3": null, "10.2.3": null, "12.34.5": null, "1e5": null,
  };
  for (const [tekst, verwacht] of Object.entries(gevallen)) assert.equal(R.leesHonderdsten(tekst), verwacht, JSON.stringify(tekst));
  assert.equal(R.leesHonderdsten(10.25), 1025);
  assert.equal(R.leesHonderdsten(-1), null);
  assert.equal(R.leesInkomen("45.000,99"), 45000);
});

/* ---------- Uren per maand uit de instellingen ---------- */

test("uren per maand: 40 schoolweken", () => {
  const vorm = { naam: "BSO 40", soort: "bso", uurtarief: 10, schoolweken: 40, vakantieweken: 0, urenSchooldag: { ma: 3.5, di: 3.5, wo: 6, do: 3.5, vr: 5.75 }, urenVakantiedag: 0 };
  assert.deepEqual([...R.controleerOpvangvorm(vorm)], []);
  assert.equal(R.urenPerMaand(vorm, ["ma"]), 1167); // 40 x 3,5 / 12 = 11,666... -> 11,67
  assert.equal(R.urenPerMaand(vorm, ["ma", "wo"]), 3167); // 40 x 9,5 / 12 = 31,666... -> 31,67
  assert.equal(R.urenPerMaand(vorm, ["ma", "di", "wo", "do", "vr"]), 7417); // 40 x 22,25 / 12 = 74,166... -> 74,17
});

test("uren per maand: 52 weken met vakantieopvang", () => {
  const vorm = { naam: "BSO 52", soort: "bso", uurtarief: 10, schoolweken: 40, vakantieweken: 12, urenSchooldag: { ma: 3.5, di: 3.5, wo: 6, do: 3.5, vr: 3.5 }, urenVakantiedag: 10 };
  assert.deepEqual([...R.controleerOpvangvorm(vorm)], []);
  assert.equal(R.urenPerMaand(vorm, ["ma"]), 2167); // (40 x 3,5 + 12 x 10) / 12 = 21,666... -> 21,67
  assert.deepEqual([...R.dagenVan(vorm)], ["ma", "di", "wo", "do", "vr"]);
});

test("een onvolledige opvangvorm wordt herkend", () => {
  assert.ok(R.controleerOpvangvorm({ naam: "x", soort: "bso", uurtarief: 0, schoolweken: 40, urenSchooldag: { ma: 3 } }).length > 0);
  assert.ok(R.controleerOpvangvorm({ naam: "x", soort: "bso", uurtarief: 10, schoolweken: 40, urenSchooldag: {} }).length > 0);
  assert.ok(R.controleerOpvangvorm({ naam: "x", soort: "bso", uurtarief: 10, schoolweken: 40, vakantieweken: 12, urenSchooldag: { ma: 3 } }).length > 0);
});

test("de opvangvormen in instellingen.js zijn volledig ingevuld", () => {
  assert.equal(INSTELLINGEN.jaar in context.VCKToeslag, true, "er is geen toeslagtabel voor het ingestelde jaar");
  for (const vorm of INSTELLINGEN.opvangvormen) assert.deepEqual([...R.controleerOpvangvorm(vorm)], [], vorm.naam);
});

/* ---------- Vergelijking met een tweede, onafhankelijke berekening ---------- */

// Zo eenvoudig mogelijk, met BigInt en de tabel uit de brochure (niet uit het databestand)
function referentie(inkomen, werkt, kinderen) {
  const MAX = { dagopvang: 1123n, bso: 998n, gastouder: 849n };
  const afgerond = (teller, noemer) => (2n * teller + noemer) / (2n * noemer);
  const rij = BROCHURE.find((b) => inkomen >= b.van && inkomen <= b.tot);
  const regels = kinderen.map((k) => {
    const prijs = BigInt(k.uurtarief) < MAX[k.soort] ? BigInt(k.uurtarief) : MAX[k.soort];
    const uren = BigInt(Math.min(k.uren, 23000));
    return { uren, kosten: afgerond(uren * prijs, 100n) };
  });
  let eerste = 0;
  regels.forEach((r, i) => {
    const e = regels[eerste];
    if (r.uren > e.uren || (r.uren === e.uren && r.kosten > e.kosten)) eerste = i;
  });
  return regels.reduce((som, r, i) => {
    if (!werkt) return som;
    const pct = BigInt(i === eerste ? rij.eerste : rij.volgend);
    return som + afgerond(r.kosten * pct, 10000n);
  }, 0n);
}

test("20.000 willekeurige situaties: gelijk aan de onafhankelijke berekening", () => {
  let zaad = 20260930;
  const willekeurig = (n) => {
    zaad = (zaad * 1103515245 + 12345) % 2147483648;
    return zaad % n;
  };
  const grenzen = BROCHURE.flatMap((b) => [b.van, b.tot, b.tot + 1]).filter((g) => g <= 300000);
  const soorten = ["bso", "dagopvang", "gastouder"];
  for (let i = 0; i < 20000; i++) {
    const inkomen = willekeurig(3) === 0 ? grenzen[willekeurig(grenzen.length)] : willekeurig(300001);
    const werkt = willekeurig(10) !== 0;
    const kinderen = Array.from({ length: 1 + willekeurig(4) }, () => ({
      soort: soorten[willekeurig(3)],
      uurtarief: willekeurig(4) === 0 ? [849, 998, 1123][willekeurig(3)] : 100 + willekeurig(1900),
      uren: willekeurig(4) === 0 ? [4000, 8000, 23000][willekeurig(3)] : willekeurig(30001),
    }));
    const r = R.bereken({ inkomen, werkt, kinderen }, JAAR);
    assert.equal(BigInt(r.totaalToeslag), referentie(inkomen, werkt, kinderen), JSON.stringify({ inkomen, werkt, kinderen }));
    assert.equal(r.totaalNetto, r.totaalKosten - r.totaalToeslag);
    assert.ok(r.totaalToeslag <= r.totaalKosten);
  }
});
