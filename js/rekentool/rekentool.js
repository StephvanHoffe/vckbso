/*
 * Rekentool kinderopvangtoeslag op de prijzenpagina: het formulier en de uitkomst.
 * De berekening zelf staat in berekening.js (en is getest in tests/rekentool.test.mjs).
 */
(function () {
  "use strict";

  const R = window.VCKRekentool;
  const instellingen = window.VCKRekentoolInstellingen || { opvangvormen: [] };
  const jaren = window.VCKToeslag || {};
  const basis = document.querySelector("[data-rekentool]");
  if (!basis || !R) return;

  // Tweetalig: op de Engelse pagina (<html lang="en">) de Engelse teksten
  const EN = /^en/.test(document.documentElement.getAttribute("lang") || "");
  const t = (nl, en) => (EN ? en : nl);
  const MAX_KINDEREN = 5;
  const PROEFBEREKENING = "https://www.belastingdienst.nl/wps/wcm/connect/nl/toeslagen/content/hulpmiddel-proefberekening-toeslagen";
  const DAGNAMEN = EN
    ? { ma: "Monday", di: "Tuesday", wo: "Wednesday", do: "Thursday", vr: "Friday" }
    : { ma: "Maandag", di: "Dinsdag", wo: "Woensdag", do: "Donderdag", vr: "Vrijdag" };
  const SOORTNAMEN = EN
    ? { bso: "After-school care (BSO)", dagopvang: "Day care (kinderdagverblijf)", gastouder: "Childminder (gastouderopvang)" }
    : { bso: "Buitenschoolse opvang", dagopvang: "Dagopvang (kinderdagverblijf)", gastouder: "Gastouderopvang" };
  const SOORTKORT = EN
    ? { bso: "after-school care", dagopvang: "day care", gastouder: "childminders" }
    : { bso: "bso", dagopvang: "dagopvang", gastouder: "gastouderopvang" };
  const naamVan = (vorm) => (EN && vorm.naamEngels) || vorm.naam;
  const rangtel = (n) => (EN ? n + (n === 1 ? "st" : n === 2 ? "nd" : n === 3 ? "rd" : "th") : n + "e");
  // Bedragen zoals mensen ze typen: in het Engels 55,000 en 9.50; de berekening leest de Nederlandse notatie
  const invoerTekst = (tekst) => (EN ? tekst.replace(/[,\s]/g, "").replace(/\./g, ",") : tekst);

  // Het jaar van de tabel: uit de instellingen, anders het nieuwste dat er is
  const beschikbaar = Object.keys(jaren).map(Number).sort((a, b) => b - a);
  const jaar = jaren[instellingen.jaar] || jaren[beschikbaar[0]];
  if (!jaar) return;

  // Alleen volledig ingevulde opvangvormen van Sporty tonen
  const vormen = (instellingen.opvangvormen || []).filter((vorm) => {
    const fouten = R.controleerOpvangvorm(vorm);
    if (fouten.length && window.console) console.warn("Rekentool: opvangvorm overgeslagen (" + (vorm && vorm.naam) + "): " + fouten.join(", "));
    return fouten.length === 0;
  });

  const form = basis.querySelector("[data-rt-form]");
  const lijst = basis.querySelector("[data-rt-kinderen]");
  const sjabloon = document.getElementById("rt-kind-sjabloon");
  const knopToevoegen = basis.querySelector("[data-rt-toevoegen]");
  const uitkomst = basis.querySelector("[data-rt-uitkomst]");
  const veld = (naam) => uitkomst.querySelector("[data-rt-" + naam + "]");
  let teller = 0;
  let statusTimer;

  // De samenvatting is direct zichtbaar; schermlezers lezen hem voor zodra iemand even stopt met typen
  function zetStatus(tekst) {
    veld("status").textContent = tekst;
    clearTimeout(statusTimer);
    statusTimer = setTimeout(() => { veld("voorlezen").textContent = tekst; }, 700);
  }

  basis.querySelectorAll("[data-rt-jaar]").forEach((el) => { el.textContent = String(jaar.jaar); });
  basis.querySelectorAll("[data-rt-proef]").forEach((el) => { el.href = PROEFBEREKENING; });
  basis.querySelector("[data-rt-zonder-js]").hidden = true;
  basis.querySelector("[data-rt-geen-tarief]").hidden = vormen.length > 0;
  form.hidden = false;
  uitkomst.hidden = false;

  /* ---------- Kinderen ---------- */

  function opvangOpties(select) {
    const optie = (waarde, tekst) => {
      const o = document.createElement("option");
      o.value = waarde;
      o.textContent = tekst;
      return o;
    };
    if (vormen.length) {
      const vck = document.createElement("optgroup");
      vck.label = "Sporty";
      vormen.forEach((vorm, i) => vck.append(optie("vck:" + i, naamVan(vorm))));
      const elders = document.createElement("optgroup");
      elders.label = t("Andere opvang (bijvoorbeeld voor een broertje of zusje)", "Other childcare (for example for a brother or sister)");
      R.SOORTEN.forEach((soort) => elders.append(optie("eigen:" + soort, SOORTNAMEN[soort])));
      select.append(vck, elders);
    } else {
      R.SOORTEN.forEach((soort) => select.append(optie("eigen:" + soort, SOORTNAMEN[soort])));
    }
  }

  function dagKeuzes(kindEl, vorm) {
    const houder = kindEl.querySelector("[data-rt-dagen]");
    houder.textContent = "";
    const vast = vorm.urenPerMaandPerDag !== undefined;
    const vakantie = (vorm.vakantieweken || 0) > 0 ? R.leesUren(vorm.urenVakantiedag) : 0;
    R.dagenVan(vorm).forEach((dag) => {
      const school = (vorm.schoolweken || 0) > 0 ? R.leesUren((vorm.urenSchooldag || {})[dag] || 0) : 0;
      const label = document.createElement("label");
      label.className = "choice";
      const input = document.createElement("input");
      input.type = "checkbox";
      input.value = dag;
      const uren = school > 0 ? R.honderdsten(school) + t(" uur", " hours") : R.honderdsten(vakantie) + t(" uur in de vakantie", " hours in the holidays");
      label.append(input, document.createTextNode(DAGNAMEN[dag]));
      if (!vast) label.append(" ", Object.assign(document.createElement("span"), { className: "choice__extra", textContent: "(" + uren + ")" }));
      houder.append(label);
    });
    const uitleg = kindEl.querySelector("[data-rt-dagen-uitleg]");
    if (vast) {
      uitleg.textContent = t("Per vaste dag per week gemiddeld ", "For each fixed day a week, on average ") + R.honderdsten(R.leesUren(vorm.urenPerMaandPerDag)) + t(" uur per maand, het hele jaar door. Uurtarief ", " hours a month, all year round. Hourly rate ") + R.euro(R.leesCenten(vorm.uurtarief)) + ".";
      return;
    }
    const delen = [];
    if (vorm.schoolweken) delen.push(vorm.schoolweken + t(" schoolweken", " school weeks"));
    if (vorm.vakantieweken) delen.push(vorm.vakantieweken + t(" vakantieweken van ", " holiday weeks of ") + R.honderdsten(vakantie) + t(" uur per dag", " hours a day"));
    uitleg.textContent = t("Per jaar: ", "Per year: ") + delen.join(t(" en ", " and ")) + t(". Uurtarief ", ". Hourly rate ") + R.euro(R.leesCenten(vorm.uurtarief)) + ".";
  }

  function wisselOpvang(kindEl) {
    const waarde = kindEl.querySelector("[data-rt-opvang]").value;
    const vck = waarde.startsWith("vck:");
    kindEl.querySelector("[data-rt-dagen-blok]").hidden = !vck;
    kindEl.querySelector("[data-rt-eigen-blok]").hidden = vck;
    if (vck) dagKeuzes(kindEl, vormen[Number(waarde.slice(4))]);
  }

  function nummerKinderen() {
    const kinderen = lijst.querySelectorAll("[data-rt-kind]");
    kinderen.forEach((kindEl, i) => {
      kindEl.querySelector("[data-rt-kind-titel]").textContent = t("Kind ", "Child ") + (i + 1);
      const verwijder = kindEl.querySelector("[data-rt-verwijder]");
      verwijder.hidden = kinderen.length === 1;
      verwijder.querySelector("[data-rt-verwijder-wie]").textContent = t("kind ", "child ") + (i + 1);
    });
    knopToevoegen.hidden = kinderen.length >= MAX_KINDEREN;
  }

  function voegKindToe(focus) {
    teller += 1;
    const kindEl = sjabloon.content.firstElementChild.cloneNode(true);
    kindEl.querySelectorAll("[id]").forEach((el) => { el.id = el.id.replace("KIND", String(teller)); });
    kindEl.querySelectorAll("[for]").forEach((el) => { el.htmlFor = el.htmlFor.replace("KIND", String(teller)); });
    kindEl.querySelectorAll("[aria-describedby]").forEach((el) => { el.setAttribute("aria-describedby", el.getAttribute("aria-describedby").replace(/KIND/g, String(teller))); });
    const select = kindEl.querySelector("[data-rt-opvang]");
    opvangOpties(select);
    select.addEventListener("change", () => { wisselOpvang(kindEl); reken(); });
    kindEl.querySelector("[data-rt-verwijder]").addEventListener("click", () => {
      const volgende = kindEl.nextElementSibling || kindEl.previousElementSibling;
      kindEl.remove();
      nummerKinderen();
      reken();
      (volgende ? volgende.querySelector("[data-rt-opvang]") : knopToevoegen).focus();
    });
    lijst.append(kindEl);
    wisselOpvang(kindEl);
    nummerKinderen();
    if (focus) select.focus();
  }

  knopToevoegen.addEventListener("click", () => { voegKindToe(true); reken(); });

  /* ---------- Invoer lezen en controleren ---------- */

  function toonFout(input, fout, tekst) {
    const tonen = Boolean(tekst);
    input.setAttribute("aria-invalid", tonen ? "true" : "false");
    fout.hidden = !tonen;
    fout.textContent = tekst || "";
  }

  // Een foutmelding pas tonen als iemand iets heeft ingevuld of het veld heeft verlaten
  function mogelijkeFout(input, fout, geldig, tekst) {
    const aangeraakt = input.dataset.aangeraakt === "1" || input.value.trim() !== "";
    toonFout(input, fout, aangeraakt && !geldig && input.value.trim() !== "" ? tekst : "");
  }

  function leesInvoer() {
    const ontbreekt = [];
    const inkomenVeld = form.querySelector("#rt-inkomen");
    const inkomen = R.leesInkomen(invoerTekst(inkomenVeld.value));
    mogelijkeFout(inkomenVeld, form.querySelector("#rt-inkomen-fout"), inkomen !== null, t("Vul een bedrag in hele euro's in, bijvoorbeeld 55.000.", "Enter an amount in whole euros, for example 55,000."));
    if (inkomen === null) ontbreekt.push(t("jullie inkomen", "your income"));

    const kinderen = [];
    lijst.querySelectorAll("[data-rt-kind]").forEach((kindEl, i) => {
      const naam = t("kind ", "child ") + (i + 1);
      const waarde = kindEl.querySelector("[data-rt-opvang]").value;
      if (waarde.startsWith("vck:")) {
        const vorm = vormen[Number(waarde.slice(4))];
        const dagen = Array.from(kindEl.querySelectorAll("[data-rt-dagen] input:checked"), (el) => el.value);
        const uren = dagen.length ? R.urenPerMaand(vorm, dagen) : null;
        kindEl.querySelector("[data-rt-uren-uitkomst]").textContent = dagen.length ? t("Samen ", "Together ") + R.honderdsten(uren) + t(" uur per maand.", " hours a month.") : "";
        if (!dagen.length) ontbreekt.push(t("de dagen van ", "the days for ") + naam);
        else kinderen.push({ naam, omschrijving: naamVan(vorm), soort: vorm.soort, uurtarief: R.leesCenten(vorm.uurtarief), uren });
      } else {
        const soort = waarde.slice(6);
        const prijsVeld = kindEl.querySelector("[data-rt-uurprijs]");
        const urenVeld = kindEl.querySelector("[data-rt-uren]");
        const prijs = R.leesCenten(invoerTekst(prijsVeld.value));
        const uren = R.leesUren(invoerTekst(urenVeld.value));
        const prijsGoed = prijs !== null && prijs > 0 && prijs <= 10000;
        const urenGoed = uren !== null && uren > 0 && uren <= 74400;
        mogelijkeFout(prijsVeld, kindEl.querySelector("[data-rt-uurprijs-fout]"), prijsGoed, t("Vul de uurprijs in euro in, bijvoorbeeld 9,50.", "Enter the hourly rate in euros, for example 9.50."));
        mogelijkeFout(urenVeld, kindEl.querySelector("[data-rt-uren-fout]"), urenGoed, t("Vul het aantal uren per maand in, bijvoorbeeld 45 (hoogstens 744).", "Enter the number of hours a month, for example 45 (744 at most)."));
        kindEl.querySelector("[data-rt-uren-uitkomst]").textContent = "";
        if (!prijsGoed) ontbreekt.push(t("de uurprijs van ", "the hourly rate for ") + naam);
        if (!urenGoed) ontbreekt.push(t("de uren van ", "the hours for ") + naam);
        if (prijsGoed && urenGoed) kinderen.push({ naam, omschrijving: SOORTNAMEN[soort], soort, uurtarief: prijs, uren });
      }
    });
    const werkt = form.querySelector("input[name='rt-werkt']:checked").value === "ja";
    return { inkomen, werkt, kinderen, ontbreekt };
  }

  /* ---------- Uitkomst ---------- */

  function maak(tag, tekst, klasse) {
    const el = document.createElement(tag);
    if (tekst) el.textContent = tekst;
    if (klasse) el.className = klasse;
    return el;
  }

  function opsomming(delen) {
    return delen.length < 2 ? delen.join("") : delen.slice(0, -1).join(", ") + t(" en ", " and ") + delen[delen.length - 1];
  }

  function toonLeeg(ontbreekt) {
    veld("netto").textContent = t("€ –", "€–");
    veld("kosten").textContent = "–";
    veld("toeslag").textContent = "–";
    veld("perkind").textContent = "";
    veld("stappen").textContent = "";
    veld("uitleg").hidden = true;
    zetStatus(t("Vul " + opsomming(ontbreekt) + " in om te rekenen.", "Fill in " + opsomming(ontbreekt) + " to calculate."));
  }

  function toonUitkomst(invoer, r) {
    veld("netto").textContent = R.euro(r.totaalNetto);
    veld("kosten").textContent = R.euro(r.totaalKosten);
    veld("toeslag").textContent = r.werkt ? "− " + R.euro(r.totaalToeslag) : R.euro(0);
    zetStatus(r.werkt
      ? t("Je betaalt ongeveer " + R.euro(r.totaalNetto) + " per maand. De kinderopvangtoeslag is ongeveer " + R.euro(r.totaalToeslag) + " per maand.",
        "You pay about " + R.euro(r.totaalNetto) + " a month. The childcare benefit is about " + R.euro(r.totaalToeslag) + " a month.")
      : t("Zonder werk, studie of traject krijg je meestal geen kinderopvangtoeslag. Je betaalt dan " + R.euro(r.totaalKosten) + " per maand.",
        "Without work, study or a training programme you usually don't get childcare benefit. You then pay " + R.euro(r.totaalKosten) + " a month."));

    // Per kind
    const perKind = veld("perkind");
    perKind.textContent = "";
    if (r.kinderen.length > 1) {
      r.kinderen.forEach((k, i) => {
        const blok = maak("div", "", "rekentool__kind-uitkomst");
        const kop = maak("p", "", "rekentool__kind-kop");
        kop.append(maak("strong", t("Kind ", "Child ") + (i + 1)), document.createTextNode(" · " + invoer.kinderen[i].omschrijving + (r.werkt ? " · " + rangtel(k.positie) + t(" kind", " child") : "")));
        const dl = maak("dl", "", "rekentool__som rekentool__som--klein");
        [[t("Kosten", "Costs"), R.euro(k.kosten)], [t("Toeslag", "Benefit"), (r.werkt ? "− " : "") + R.euro(k.toeslag)], [t("Je betaalt", "You pay"), R.euro(k.nettokosten)]].forEach(([dt, dd]) => {
          const rij = maak("div");
          rij.append(maak("dt", dt), maak("dd", dd));
          dl.append(rij);
        });
        blok.append(kop, dl);
        perKind.append(blok);
      });
    }

    // Uitleg per stap, zoals in de brochure van Dienst Toeslagen
    const stappen = veld("stappen");
    stappen.textContent = "";
    veld("uitleg").hidden = false;
    const tot = r.rij.tot === null ? t("en hoger", "and up") : t("tot en met ", "to ") + R.euroHeel(r.rij.tot);
    stappen.append(maak("p", EN
      ? "Your income of " + R.euroHeel(r.inkomen) + " falls in the bracket from " + R.euroHeel(r.rij.van) + " " + tot + ". That comes with " + R.procent(r.rij.eerste) + " for the 1st child and " + R.procent(r.rij.volgend) + " for every next child."
      : "Jullie inkomen van " + R.euroHeel(r.inkomen) + " valt in de schijf van " + R.euroHeel(r.rij.van) + " " + tot + ". Daarbij hoort " + R.procent(r.rij.eerste) + " voor het 1e kind en " + R.procent(r.rij.volgend) + " voor elk volgend kind."));
    const volgorde = r.kinderen.slice().sort((a, b) => a.positie - b.positie);
    volgorde.forEach((k) => {
      const i = k.index;
      const blok = maak("div", "", "rekentool__stap");
      blok.append(maak("h4", t("Kind ", "Child ") + (i + 1) + (r.kinderen.length > 1 ? " (" + rangtel(k.positie) + t(" kind)", " child)") : "")));
      const ol = maak("ol");
      const prijsTekst = EN
        ? (k.uurtarief > k.maxUurprijs
          ? "The hourly rate is " + R.euro(k.uurtarief) + ". That's more than the maximum for " + SOORTKORT[k.soort] + ", so the benefit is calculated with " + R.euro(k.uurprijs) + " an hour."
          : "The hourly rate is " + R.euro(k.uurtarief) + ". That's not more than the maximum for " + SOORTKORT[k.soort] + " (" + R.euro(k.maxUurprijs) + "), so the benefit is calculated with " + R.euro(k.uurprijs) + " an hour.")
        : (k.uurtarief > k.maxUurprijs
          ? "De uurprijs is " + R.euro(k.uurtarief) + ". Dat is meer dan het maximum voor " + SOORTKORT[k.soort] + ", dus de toeslag rekent met " + R.euro(k.uurprijs) + " per uur."
          : "De uurprijs is " + R.euro(k.uurtarief) + ". Dat is niet meer dan het maximum voor " + SOORTKORT[k.soort] + " (" + R.euro(k.maxUurprijs) + "), dus de toeslag rekent met " + R.euro(k.uurprijs) + " per uur.");
      const urenTekst = k.uren > k.vergoedeUren
        ? R.honderdsten(k.uren) + t(" uur per maand. Toeslag krijg je voor maximaal ", " hours a month. You get benefit for at most ") + R.honderdsten(k.vergoedeUren) + t(" uur.", " hours.")
        : R.honderdsten(k.uren) + t(" uur per maand.", " hours a month.");
      ol.append(
        maak("li", prijsTekst),
        maak("li", urenTekst),
        maak("li", t("Opvangkosten voor de toeslag: ", "Childcare costs for the benefit: ") + R.honderdsten(k.vergoedeUren) + " × " + R.euro(k.uurprijs) + " = " + R.euro(k.opvangkosten) + "."),
        maak("li", r.werkt ? t("Toeslag: ", "Benefit: ") + R.procent(k.percentage) + t(" van ", " of ") + R.euro(k.opvangkosten) + " = " + R.euro(k.toeslag) + "." : t("Geen toeslag, omdat je hebt aangegeven dat je niet werkt.", "No benefit, because you said you don't work.")),
        maak("li", t("Wat de opvang rekent: ", "What the childcare charges: ") + R.honderdsten(k.uren) + " × " + R.euro(k.uurtarief) + " = " + R.euro(k.kosten) + t(". Je betaalt zelf ", ". You pay ") + R.euro(k.nettokosten) + ".")
      );
      blok.append(ol);
      stappen.append(blok);
    });
    if (r.werkt) {
      stappen.append(maak("p", EN
        ? "Total benefit: " + R.euro(r.totaalToeslag) + " a month. The Dutch Tax Administration (Belastingdienst) pays out whole euros, rounded down: " + R.euroHeel(r.totaalToeslagUitbetaald / 100) + " a month."
        : "Totaal toeslag: " + R.euro(r.totaalToeslag) + " per maand. De Belastingdienst betaalt hele euro's uit, naar beneden afgerond: " + R.euroHeel(r.totaalToeslagUitbetaald / 100) + " per maand."));
    }
  }

  function reken() {
    const invoer = leesInvoer();
    if (invoer.ontbreekt.length || !invoer.kinderen.length) {
      toonLeeg(invoer.ontbreekt.length ? invoer.ontbreekt : [t("de gegevens van je kind", "your child's details")]);
      return;
    }
    try {
      toonUitkomst(invoer, R.bereken({ inkomen: invoer.inkomen, werkt: invoer.werkt, kinderen: invoer.kinderen }, jaar));
    } catch (fout) {
      toonLeeg([t("alle velden", "all fields")]);
      if (window.console) console.error(fout);
    }
  }

  form.addEventListener("input", reken);
  form.addEventListener("change", reken);
  form.addEventListener("focusout", (event) => {
    if (event.target.matches("input[type='text'], input:not([type])")) {
      event.target.dataset.aangeraakt = "1";
      reken();
    }
  });
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    reken();
  });

  voegKindToe(false);
  reken();
})();
