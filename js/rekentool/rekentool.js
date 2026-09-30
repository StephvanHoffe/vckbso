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

  const MAX_KINDEREN = 5;
  const PROEFBEREKENING = "https://www.belastingdienst.nl/wps/wcm/connect/nl/toeslagen/content/hulpmiddel-proefberekening-toeslagen";
  const DAGNAMEN = { ma: "Maandag", di: "Dinsdag", wo: "Woensdag", do: "Donderdag", vr: "Vrijdag" };
  const SOORTNAMEN = { bso: "Buitenschoolse opvang", dagopvang: "Dagopvang (kinderdagverblijf)", gastouder: "Gastouderopvang" };
  const SOORTKORT = { bso: "bso", dagopvang: "dagopvang", gastouder: "gastouderopvang" };

  // Het jaar van de tabel: uit de instellingen, anders het nieuwste dat er is
  const beschikbaar = Object.keys(jaren).map(Number).sort((a, b) => b - a);
  const jaar = jaren[instellingen.jaar] || jaren[beschikbaar[0]];
  if (!jaar) return;

  // Alleen volledig ingevulde opvangvormen van BSO VCK tonen
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
      vck.label = "BSO VCK";
      vormen.forEach((vorm, i) => vck.append(optie("vck:" + i, vorm.naam)));
      const elders = document.createElement("optgroup");
      elders.label = "Andere opvang (bijvoorbeeld voor een broertje of zusje)";
      R.SOORTEN.forEach((soort) => elders.append(optie("eigen:" + soort, SOORTNAMEN[soort])));
      select.append(vck, elders);
    } else {
      R.SOORTEN.forEach((soort) => select.append(optie("eigen:" + soort, SOORTNAMEN[soort])));
    }
  }

  function dagKeuzes(kindEl, vorm) {
    const houder = kindEl.querySelector("[data-rt-dagen]");
    houder.textContent = "";
    const vakantie = (vorm.vakantieweken || 0) > 0 ? R.leesUren(vorm.urenVakantiedag) : 0;
    R.dagenVan(vorm).forEach((dag) => {
      const school = (vorm.schoolweken || 0) > 0 ? R.leesUren((vorm.urenSchooldag || {})[dag] || 0) : 0;
      const label = document.createElement("label");
      label.className = "choice";
      const input = document.createElement("input");
      input.type = "checkbox";
      input.value = dag;
      const uren = school > 0 ? R.honderdsten(school) + " uur" : R.honderdsten(vakantie) + " uur in de vakantie";
      label.append(input, document.createTextNode(DAGNAMEN[dag] + " "), Object.assign(document.createElement("span"), { className: "choice__extra", textContent: "(" + uren + ")" }));
      houder.append(label);
    });
    const uitleg = kindEl.querySelector("[data-rt-dagen-uitleg]");
    const delen = [];
    if (vorm.schoolweken) delen.push(vorm.schoolweken + " schoolweken");
    if (vorm.vakantieweken) delen.push(vorm.vakantieweken + " vakantieweken van " + R.honderdsten(vakantie) + " uur per dag");
    uitleg.textContent = "Per jaar: " + delen.join(" en ") + ". Uurtarief " + R.euro(R.leesCenten(vorm.uurtarief)) + ".";
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
      kindEl.querySelector("[data-rt-kind-titel]").textContent = "Kind " + (i + 1);
      const verwijder = kindEl.querySelector("[data-rt-verwijder]");
      verwijder.hidden = kinderen.length === 1;
      verwijder.querySelector("[data-rt-verwijder-wie]").textContent = "kind " + (i + 1);
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
    const inkomen = R.leesInkomen(inkomenVeld.value);
    mogelijkeFout(inkomenVeld, form.querySelector("#rt-inkomen-fout"), inkomen !== null, "Vul een bedrag in hele euro's in, bijvoorbeeld 55.000.");
    if (inkomen === null) ontbreekt.push("jullie inkomen");

    const kinderen = [];
    lijst.querySelectorAll("[data-rt-kind]").forEach((kindEl, i) => {
      const naam = "kind " + (i + 1);
      const waarde = kindEl.querySelector("[data-rt-opvang]").value;
      if (waarde.startsWith("vck:")) {
        const vorm = vormen[Number(waarde.slice(4))];
        const dagen = Array.from(kindEl.querySelectorAll("[data-rt-dagen] input:checked"), (el) => el.value);
        const uren = dagen.length ? R.urenPerMaand(vorm, dagen) : null;
        kindEl.querySelector("[data-rt-uren-uitkomst]").textContent = dagen.length ? "Samen " + R.honderdsten(uren) + " uur per maand." : "";
        if (!dagen.length) ontbreekt.push("de dagen van " + naam);
        else kinderen.push({ naam, omschrijving: vorm.naam, soort: vorm.soort, uurtarief: R.leesCenten(vorm.uurtarief), uren });
      } else {
        const soort = waarde.slice(6);
        const prijsVeld = kindEl.querySelector("[data-rt-uurprijs]");
        const urenVeld = kindEl.querySelector("[data-rt-uren]");
        const prijs = R.leesCenten(prijsVeld.value);
        const uren = R.leesUren(urenVeld.value);
        const prijsGoed = prijs !== null && prijs > 0 && prijs <= 10000;
        const urenGoed = uren !== null && uren > 0 && uren <= 74400;
        mogelijkeFout(prijsVeld, kindEl.querySelector("[data-rt-uurprijs-fout]"), prijsGoed, "Vul de uurprijs in euro in, bijvoorbeeld 9,50.");
        mogelijkeFout(urenVeld, kindEl.querySelector("[data-rt-uren-fout]"), urenGoed, "Vul het aantal uren per maand in, bijvoorbeeld 45 (hoogstens 744).");
        kindEl.querySelector("[data-rt-uren-uitkomst]").textContent = "";
        if (!prijsGoed) ontbreekt.push("de uurprijs van " + naam);
        if (!urenGoed) ontbreekt.push("de uren van " + naam);
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
    return delen.length < 2 ? delen.join("") : delen.slice(0, -1).join(", ") + " en " + delen[delen.length - 1];
  }

  function toonLeeg(ontbreekt) {
    veld("netto").textContent = "€ –";
    veld("kosten").textContent = "–";
    veld("toeslag").textContent = "–";
    veld("perkind").textContent = "";
    veld("stappen").textContent = "";
    veld("uitleg").hidden = true;
    zetStatus("Vul " + opsomming(ontbreekt) + " in om te rekenen.");
  }

  function toonUitkomst(invoer, r) {
    veld("netto").textContent = R.euro(r.totaalNetto);
    veld("kosten").textContent = R.euro(r.totaalKosten);
    veld("toeslag").textContent = r.werkt ? "− " + R.euro(r.totaalToeslag) : R.euro(0);
    zetStatus(r.werkt
      ? "Je betaalt ongeveer " + R.euro(r.totaalNetto) + " per maand. De kinderopvangtoeslag is ongeveer " + R.euro(r.totaalToeslag) + " per maand."
      : "Zonder werk, studie of traject krijg je meestal geen kinderopvangtoeslag. Je betaalt dan " + R.euro(r.totaalKosten) + " per maand.");

    // Per kind
    const perKind = veld("perkind");
    perKind.textContent = "";
    if (r.kinderen.length > 1) {
      r.kinderen.forEach((k, i) => {
        const blok = maak("div", "", "rekentool__kind-uitkomst");
        const kop = maak("p", "", "rekentool__kind-kop");
        kop.append(maak("strong", "Kind " + (i + 1)), document.createTextNode(" · " + invoer.kinderen[i].omschrijving + (r.werkt ? " · " + k.positie + "e kind" : "")));
        const dl = maak("dl", "", "rekentool__som rekentool__som--klein");
        [["Kosten", R.euro(k.kosten)], ["Toeslag", (r.werkt ? "− " : "") + R.euro(k.toeslag)], ["Je betaalt", R.euro(k.nettokosten)]].forEach(([dt, dd]) => {
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
    const tot = r.rij.tot === null ? "en hoger" : "tot en met " + R.euroHeel(r.rij.tot);
    stappen.append(maak("p", "Jullie inkomen van " + R.euroHeel(r.inkomen) + " valt in de schijf van " + R.euroHeel(r.rij.van) + " " + tot + ". Daarbij hoort " + R.procent(r.rij.eerste) + " voor het 1e kind en " + R.procent(r.rij.volgend) + " voor elk volgend kind."));
    const volgorde = r.kinderen.slice().sort((a, b) => a.positie - b.positie);
    volgorde.forEach((k) => {
      const i = k.index;
      const blok = maak("div", "", "rekentool__stap");
      blok.append(maak("h4", "Kind " + (i + 1) + (r.kinderen.length > 1 ? " (" + k.positie + "e kind)" : "")));
      const ol = maak("ol");
      const prijsTekst = k.uurtarief > k.maxUurprijs
        ? "De uurprijs is " + R.euro(k.uurtarief) + ". Dat is meer dan het maximum voor " + SOORTKORT[k.soort] + ", dus de toeslag rekent met " + R.euro(k.uurprijs) + " per uur."
        : "De uurprijs is " + R.euro(k.uurtarief) + ". Dat is niet meer dan het maximum voor " + SOORTKORT[k.soort] + " (" + R.euro(k.maxUurprijs) + "), dus de toeslag rekent met " + R.euro(k.uurprijs) + " per uur.";
      const urenTekst = k.uren > k.vergoedeUren
        ? R.honderdsten(k.uren) + " uur per maand. Toeslag krijg je voor maximaal " + R.honderdsten(k.vergoedeUren) + " uur."
        : R.honderdsten(k.uren) + " uur per maand.";
      ol.append(
        maak("li", prijsTekst),
        maak("li", urenTekst),
        maak("li", "Opvangkosten voor de toeslag: " + R.honderdsten(k.vergoedeUren) + " × " + R.euro(k.uurprijs) + " = " + R.euro(k.opvangkosten) + "."),
        maak("li", r.werkt ? "Toeslag: " + R.procent(k.percentage) + " van " + R.euro(k.opvangkosten) + " = " + R.euro(k.toeslag) + "." : "Geen toeslag, omdat je hebt aangegeven dat je niet werkt."),
        maak("li", "Wat de opvang rekent: " + R.honderdsten(k.uren) + " × " + R.euro(k.uurtarief) + " = " + R.euro(k.kosten) + ". Je betaalt zelf " + R.euro(k.nettokosten) + ".")
      );
      blok.append(ol);
      stappen.append(blok);
    });
    if (r.werkt) {
      stappen.append(maak("p", "Totaal toeslag: " + R.euro(r.totaalToeslag) + " per maand. De Belastingdienst betaalt hele euro's uit, naar beneden afgerond: " + R.euroHeel(r.totaalToeslagUitbetaald / 100) + " per maand."));
    }
  }

  function reken() {
    const invoer = leesInvoer();
    if (invoer.ontbreekt.length || !invoer.kinderen.length) {
      toonLeeg(invoer.ontbreekt.length ? invoer.ontbreekt : ["de gegevens van je kind"]);
      return;
    }
    try {
      toonUitkomst(invoer, R.bereken({ inkomen: invoer.inkomen, werkt: invoer.werkt, kinderen: invoer.kinderen }, jaar));
    } catch (fout) {
      toonLeeg(["alle velden"]);
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
