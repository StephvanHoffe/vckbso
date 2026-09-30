/*
 * Rekentool kinderopvangtoeslag: de berekening.
 *
 * Volgt de 7 stappen uit de brochure "Berekening kinderopvangtoeslag" van Dienst Toeslagen:
 * 1. uurprijs = het uurtarief, maar niet meer dan de maximale uurprijs voor die soort opvang
 * 2. uren = de uren uit het contract, maar niet meer dan 230 per kind per maand
 * 3. het 1e kind is het kind met de meeste uren (stap 2); bij gelijke uren het kind
 *    met de hoogste opvangkosten; opvangkosten = uren x uurprijs
 * 4. gezamenlijk toetsingsinkomen
 * 5. toeslag 1e kind = opvangkosten x percentage 1e kind (uit de tabel)
 * 6. toeslag andere kinderen = opvangkosten x percentage 2e en volgende kind
 * 7. totaal = alle kinderen samen; de Belastingdienst betaalt hele euro's (naar beneden afgerond)
 *
 * Alles wordt met hele getallen berekend, zonder afrondingsfouten van kommagetallen:
 * bedragen in centen, uren in honderdsten van een uur, percentages in honderdsten van
 * een procent. Bedragen worden per stap op hele centen afgerond (half naar boven),
 * net als in de rekenvoorbeelden van de Belastingdienst.
 *
 * Werkt in de browser (window.VCKRekentool) en in Node (voor de tests).
 */
(function (root) {
  "use strict";

  const SOORTEN = ["bso", "dagopvang", "gastouder"];
  const DAGEN = ["ma", "di", "wo", "do", "vr"];
  const MAX_UREN_INVOER = 74400; // 744 uur: een volledige maand van 31 dagen
  const MAX_TARIEF_INVOER = 10000; // € 100 per uur
  const MAX_INKOMEN = 99999999;

  /** a / b afgerond op een heel getal, half naar boven (a >= 0, b > 0, beide hele getallen). */
  function deelAfgerond(a, b) {
    if (!Number.isSafeInteger(a) || !Number.isSafeInteger(b) || a < 0 || b <= 0) {
      throw new RangeError("deelAfgerond: ongeldige invoer");
    }
    return Math.floor((2 * a + b) / (2 * b));
  }

  /**
   * Leest een getal zoals mensen het in Nederland typen: "45.000", "45000", "10,25",
   * "€ 1.234,50" of "10.25". Geeft het getal in honderdsten terug (1025 voor 10,25),
   * of null als het geen geldig getal is. Meer dan twee decimalen wordt afgerond.
   */
  function leesHonderdsten(tekst) {
    if (typeof tekst === "number") {
      // Getallen uit het instellingenbestand, bijvoorbeeld 10.25 of 3.5
      const h = Math.round(tekst * 100);
      return Number.isFinite(tekst) && tekst >= 0 && Number.isSafeInteger(h) ? h : null;
    }
    if (typeof tekst !== "string") return null;
    let s = tekst.replace(/[€\s ]/g, "").replace(/^\+/, "");
    if (s === "" || /[^0-9.,]/.test(s)) return null;
    let geheel;
    let decimalen = "";
    if (s.includes(",")) {
      // Komma is het decimaalteken, punten zijn duizendtallen
      const delen = s.split(",");
      if (delen.length !== 2) return null;
      if (delen[0] === "") delen[0] = "0"; // ",5" is 0,5
      if (!/^\d{1,3}(\.\d{3})*$|^\d+$/.test(delen[0]) || !/^\d*$/.test(delen[1])) return null;
      geheel = delen[0].replace(/\./g, "");
      decimalen = delen[1];
    } else if (s.includes(".")) {
      const delen = s.split(".");
      if (delen.slice(1).every((d) => d.length === 3) && /^\d{1,3}$/.test(delen[0])) {
        geheel = delen.join(""); // 45.000 of 1.234.567: duizendtallen
      } else if (delen.length === 2 && /^\d+$/.test(delen[0]) && /^\d{1,2}$/.test(delen[1])) {
        geheel = delen[0]; // 10.25: decimaalpunt
        decimalen = delen[1];
      } else {
        return null;
      }
    } else {
      geheel = s;
    }
    if (!/^\d+$/.test(geheel) || geheel.length > 12) return null;
    const derde = decimalen.length > 2 ? Number(decimalen[2]) : 0;
    const honderdsten = Number(geheel) * 100 + Number((decimalen + "00").slice(0, 2)) + (derde >= 5 ? 1 : 0);
    return Number.isSafeInteger(honderdsten) ? honderdsten : null;
  }

  /** Bedrag in euro's (tekst of getal) naar centen, of null. */
  function leesCenten(tekst) {
    return leesHonderdsten(tekst);
  }

  /** Inkomen in hele euro's (centen worden weggelaten), of null. */
  function leesInkomen(tekst) {
    const h = leesHonderdsten(tekst);
    if (h === null) return null;
    const euro = Math.floor(h / 100);
    return euro <= MAX_INKOMEN ? euro : null;
  }

  /** Uren (tekst of getal) naar honderdsten van een uur, of null. */
  function leesUren(tekst) {
    return leesHonderdsten(tekst);
  }

  /** Controleert de officiële gegevens van een jaar. Gooit een fout als er iets niet klopt. */
  function controleerJaar(jaar) {
    if (!jaar || !Array.isArray(jaar.tabel) || jaar.tabel.length === 0) throw new Error("Geen toeslagtabel");
    for (const soort of SOORTEN) {
      if (!Number.isSafeInteger(jaar.maxUurprijs[soort]) || jaar.maxUurprijs[soort] <= 0) throw new Error("Maximale uurprijs ontbreekt: " + soort);
    }
    if (!Number.isSafeInteger(jaar.maxUrenPerMaand) || jaar.maxUrenPerMaand <= 0) throw new Error("Maximaal aantal uren ontbreekt");
    jaar.tabel.forEach((rij, i) => {
      const [van, tot, eerste, volgend] = rij;
      const laatste = i === jaar.tabel.length - 1;
      if (i === 0 && van !== 0) throw new Error("Tabel begint niet bij 0");
      if (i > 0 && van !== jaar.tabel[i - 1][1] + 1) throw new Error("Tabel sluit niet aan bij rij " + (i + 1));
      if (laatste ? tot !== null : !(Number.isSafeInteger(tot) && tot >= van)) throw new Error("Ongeldige bovengrens in rij " + (i + 1));
      for (const p of [eerste, volgend]) {
        if (!Number.isSafeInteger(p) || p < 0 || p > 10000) throw new Error("Ongeldig percentage in rij " + (i + 1));
      }
    });
    return true;
  }

  /** De rij uit de toeslagtabel voor een toetsingsinkomen (hele euro's). */
  function vindRij(jaar, inkomen) {
    if (!Number.isSafeInteger(inkomen) || inkomen < 0) throw new RangeError("Ongeldig inkomen");
    const index = jaar.tabel.findIndex(([van, tot]) => inkomen >= van && (tot === null || inkomen <= tot));
    const [van, tot, eerste, volgend] = jaar.tabel[index];
    return { index, van, tot, eerste, volgend };
  }

  /** Is een opvangvorm uit de instellingen volledig ingevuld? Geeft een lijst met problemen terug. */
  function controleerOpvangvorm(vorm) {
    const fouten = [];
    if (!vorm || typeof vorm !== "object") return ["Geen opvangvorm"];
    if (!vorm.naam) fouten.push("naam ontbreekt");
    if (!SOORTEN.includes(vorm.soort)) fouten.push("soort moet bso, dagopvang of gastouder zijn");
    const tarief = leesCenten(vorm.uurtarief);
    if (!(tarief > 0 && tarief <= MAX_TARIEF_INVOER)) fouten.push("uurtarief ontbreekt of is ongeldig");
    const weken = (vorm.schoolweken || 0) + (vorm.vakantieweken || 0);
    for (const w of ["schoolweken", "vakantieweken"]) {
      const waarde = vorm[w] || 0;
      if (!Number.isInteger(waarde) || waarde < 0) fouten.push(w + " moet een heel getal zijn");
    }
    if (!(weken >= 1 && weken <= 53)) fouten.push("aantal weken moet tussen 1 en 53 liggen");
    const school = vorm.urenSchooldag || {};
    let dagenMetOpvang = 0;
    for (const dag of DAGEN) {
      const u = leesUren(school[dag] ?? 0);
      if (u === null || u > 2400) fouten.push("uren op " + dag + " zijn ongeldig");
      else if (u > 0) dagenMetOpvang++;
    }
    const vakantie = leesUren(vorm.urenVakantiedag ?? 0);
    if (vakantie === null || vakantie > 2400) fouten.push("uren per vakantiedag zijn ongeldig");
    if ((vorm.vakantieweken || 0) > 0 && !(vakantie > 0)) fouten.push("uren per vakantiedag ontbreken");
    if ((vorm.schoolweken || 0) > 0 && dagenMetOpvang === 0) fouten.push("uren per schooldag ontbreken");
    return fouten;
  }

  /** Dagen waarop een opvangvorm open is (schooldag of vakantiedag met uren). */
  function dagenVan(vorm) {
    const vakantie = (vorm.vakantieweken || 0) > 0 ? leesUren(vorm.urenVakantiedag ?? 0) : 0;
    return DAGEN.filter((dag) => {
      const school = (vorm.schoolweken || 0) > 0 ? leesUren((vorm.urenSchooldag || {})[dag] ?? 0) : 0;
      return school > 0 || vakantie > 0;
    });
  }

  /**
   * Uren per maand voor een opvangvorm en gekozen dagen, in honderdsten van een uur:
   * (per gekozen dag: schoolweken x uren per schooldag + vakantieweken x uren per vakantiedag) / 12,
   * afgerond op twee decimalen.
   */
  function urenPerMaand(vorm, dagen) {
    let perJaar = 0; // honderdsten van een uur
    for (const dag of dagen) {
      if (!DAGEN.includes(dag)) throw new RangeError("Onbekende dag: " + dag);
      perJaar += (vorm.schoolweken || 0) * leesUren((vorm.urenSchooldag || {})[dag] ?? 0);
      perJaar += (vorm.vakantieweken || 0) * leesUren(vorm.urenVakantiedag ?? 0);
    }
    return deelAfgerond(perJaar, 12);
  }

  /**
   * De berekening.
   * invoer = {
   *   inkomen: gezamenlijk toetsingsinkomen in hele euro's,
   *   werkt: true als de ouder(s) werken (of studeren of een traject volgen),
   *   kinderen: [{ soort: "bso" | "dagopvang" | "gastouder", uurtarief: centen, uren: honderdsten van een uur per maand }]
   * }
   */
  function bereken(invoer, jaar) {
    controleerJaar(jaar);
    const { inkomen, werkt, kinderen } = invoer;
    if (!Array.isArray(kinderen) || kinderen.length === 0) throw new RangeError("Geen kinderen");
    const rij = vindRij(jaar, inkomen);
    const maxUren = jaar.maxUrenPerMaand * 100;

    const regels = kinderen.map((kind, i) => {
      if (!SOORTEN.includes(kind.soort)) throw new RangeError("Onbekende soort opvang");
      if (!Number.isSafeInteger(kind.uurtarief) || kind.uurtarief <= 0 || kind.uurtarief > MAX_TARIEF_INVOER) throw new RangeError("Ongeldig uurtarief");
      if (!Number.isSafeInteger(kind.uren) || kind.uren < 0 || kind.uren > MAX_UREN_INVOER) throw new RangeError("Ongeldig aantal uren");
      const maxUurprijs = jaar.maxUurprijs[kind.soort];
      const uurprijs = Math.min(kind.uurtarief, maxUurprijs); // stap 1
      const vergoedeUren = Math.min(kind.uren, maxUren); // stap 2
      const opvangkosten = deelAfgerond(vergoedeUren * uurprijs, 100); // stap 3
      const kosten = deelAfgerond(kind.uren * kind.uurtarief, 100); // wat de opvang rekent
      return { index: i, soort: kind.soort, uurtarief: kind.uurtarief, uren: kind.uren, maxUurprijs, uurprijs, vergoedeUren, opvangkosten, kosten };
    });

    // Stap 3: volgorde. Meeste vergoede uren eerst, bij gelijke uren de hoogste opvangkosten.
    const volgorde = regels.slice().sort((a, b) => b.vergoedeUren - a.vergoedeUren || b.opvangkosten - a.opvangkosten || a.index - b.index);
    volgorde.forEach((regel, positie) => {
      regel.positie = positie + 1; // 1 = 1e kind
      regel.percentage = positie === 0 ? rij.eerste : rij.volgend; // stap 5 en 6
      regel.toeslag = werkt ? deelAfgerond(regel.opvangkosten * regel.percentage, 10000) : 0;
      regel.nettokosten = regel.kosten - regel.toeslag;
    });

    const totaalKosten = regels.reduce((som, r) => som + r.kosten, 0);
    const totaalToeslag = regels.reduce((som, r) => som + r.toeslag, 0); // stap 7
    return {
      jaar: jaar.jaar,
      inkomen,
      werkt: Boolean(werkt),
      rij,
      kinderen: regels,
      totaalKosten,
      totaalToeslag,
      totaalToeslagUitbetaald: Math.floor(totaalToeslag / 100) * 100,
      totaalNetto: totaalKosten - totaalToeslag,
    };
  }

  /* ---------- Weergave ---------- */

  const euroOpmaak = typeof Intl !== "undefined" ? new Intl.NumberFormat("nl-NL", { style: "currency", currency: "EUR" }) : null;

  function euro(centen) {
    const teken = centen < 0 ? "-" : "";
    const abs = Math.abs(centen);
    if (euroOpmaak) return euroOpmaak.format(centen / 100);
    return teken + "€ " + Math.floor(abs / 100).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") + "," + String(abs % 100).padStart(2, "0");
  }

  function euroHeel(euros) {
    return "€ " + String(euros).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  }

  /** Honderdsten als tekst met komma, zonder overbodige nullen: 4333 -> "43,33", 12200 -> "122". */
  function honderdsten(waarde) {
    const geheel = Math.floor(waarde / 100).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    const rest = waarde % 100;
    return rest === 0 ? geheel : geheel + "," + String(rest).padStart(2, "0").replace(/0$/, "");
  }

  /** Percentage in honderdsten van een procent: 9390 -> "93,90%". */
  function procent(waarde) {
    return Math.floor(waarde / 100) + "," + String(waarde % 100).padStart(2, "0") + "%";
  }

  root.VCKRekentool = Object.freeze({
    SOORTEN,
    DAGEN,
    deelAfgerond,
    leesHonderdsten,
    leesCenten,
    leesInkomen,
    leesUren,
    controleerJaar,
    vindRij,
    controleerOpvangvorm,
    dagenVan,
    urenPerMaand,
    bereken,
    euro,
    euroHeel,
    honderdsten,
    procent,
  });
})(typeof globalThis !== "undefined" ? globalThis : this);
