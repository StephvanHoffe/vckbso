/*
 * Kom bij de club (aanmelden.html): het aanmeldformulier in 7 stappen, één onderwerp per scherm.
 *
 * - Henny rent over de voortgangsbalk mee en zegt af en toe iets.
 * - Elke stap wordt gecontroleerd voordat je verder kunt; meldingen staan direct bij het veld.
 * - Meer kinderen: stap 1 krijgt een blok per kind, en stap 2 t/m 4 een deel per kind.
 * - Wat je invult, onthoudt de browser op dit apparaat (localStorage), zodat je later verder kunt.
 *   Na het versturen wordt het gewist.
 * - Per stap telt beheer/aanvraag.php anoniem mee (zonder cookies of persoonsgegevens), zodat Sporty
 *   ziet bij welke stap ouders afhaken.
 * Zonder JavaScript is het één lang formulier dat gewoon verstuurd kan worden.
 */
(function () {
  "use strict";

  var form = document.querySelector("[data-aanmelden]");
  if (!form) return;

  // Tweetalig: op de Engelse pagina (<html lang="en">) de Engelse teksten
  var EN = (document.documentElement.getAttribute("lang") || "").slice(0, 2) === "en";
  var t = function (nl, en) { return EN ? en : nl; };
  var MAAND_EN = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
  var en = function (lijst) { return lijst.length > 1 ? lijst.slice(0, -1).join(", ") + t(" en ", " and ") + lijst[lijst.length - 1] : lijst[0]; };

  var MAX_KINDEREN = 6;
  var SLEUTEL = "sporty-aanmelden";
  var ENDPOINT = form.getAttribute("action");
  var WOLKJES = EN ? [
    "No worries, you're not committing to anything yet!",
    "Which school does your little star go to?",
    "Fixed days or now and then? Both are a win!",
    "Which days shall we play sports together?",
    "Your little star is almost signed up, on to the next step!",
    "Nearly there, the goal is in sight!",
    "One more click and I'll run for the goal!"
  ] : [
    "Geen zorgen, je zit nog nergens aan vast!",
    "Op welke school zit je kleine topper?",
    "Vaste dagen of af en toe? Allebei goed!",
    "Welke dagen gaan we samen sporten?",
    "Je hebt je kleine topper bijna aangemeld, door naar de volgende stap!",
    "Nog even en we zijn bij het doel!",
    "Nog één klik en ik ren naar het doel!"
  ];
  var DAGNAMEN = EN ? ["", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday"] : ["", "maandag", "dinsdag", "woensdag", "donderdag", "vrijdag"];

  var stappen = [].slice.call(form.querySelectorAll("[data-stap]"));
  var verder = form.querySelector("[data-verder]");
  var terug = form.querySelector("[data-terug]");
  var verstuur = form.querySelector(".aanmelden__verstuur");
  var status = form.querySelector("[data-form-status]");
  var voortgang = document.querySelector("[data-voortgang]");
  var wolk = document.querySelector("[data-henny-wolk]");
  var klaar = document.querySelector("[data-aanmelden-klaar]");
  var bewaardMelding = document.querySelector("[data-bewaard]");
  var kinderenLijst = form.querySelector("[data-kinderen]");
  var kindErbij = form.querySelector("[data-kind-erbij]");
  var kindMax = form.querySelector("[data-kind-max]");
  var perKind = [].slice.call(form.querySelectorAll("[data-per-kind]"));
  var partnerBlok = form.querySelector("[data-partner]");
  var overzicht = form.querySelector("[data-overzicht]");
  var alles = form.querySelector("[data-akkoord-alles]");
  var vandaag = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  var huidig = 0;
  var bewaarTimer = null;
  var geteld = {};
  var volleDagen = [];

  // Gewenste startdatum: standaard vandaag, maar niet vóór de opening van Sporty (data-start-vanaf)
  var startVeld = form.querySelector("[data-start-vanaf]");
  var standaardStart = startVeld && startVeld.getAttribute("data-start-vanaf") > vandaag ? startVeld.getAttribute("data-start-vanaf") : vandaag;

  // Dagen waarop Sporty nog niet open is (tot de datum in data-alle-dagen-vanaf alleen de dagen in data-dagen-open)
  var dichteDagen = (function () {
    var groep = form.querySelector("[data-dagen-open]");
    if (!groep) return [];
    var vanaf = groep.getAttribute("data-alle-dagen-vanaf");
    if (vanaf && vandaag >= vanaf) return [];
    var open = groep.getAttribute("data-dagen-open").split(",").map(Number);
    return [1, 2, 3, 4, 5].filter(function (dag) { return open.indexOf(dag) === -1; });
  })();

  // Sjablonen van een kind (stap 1) en van de delen per kind (stap 2 t/m 4), nog leeg
  var kindSjabloon = kinderenLijst.querySelector("[data-kind]").cloneNode(true);
  var deelSjablonen = perKind.map(function (wrap) { return wrap.querySelector("[data-kind]").cloneNode(true); });

  /* ---------- Hulpjes ---------- */

  var opslag = {
    lees: function () { try { return JSON.parse(localStorage.getItem(SLEUTEL) || "null"); } catch (e) { return null; } },
    schrijf: function (data) { try { localStorage.setItem(SLEUTEL, JSON.stringify(data)); } catch (e) { /* privévenster of geen opslag */ } },
    wis: function () { try { localStorage.removeItem(SLEUTEL); } catch (e) { /* niets */ } }
  };

  var tel = function (stap) {
    if (geteld[stap]) return;
    geteld[stap] = true;
    try { if (navigator.sendBeacon) navigator.sendBeacon(ENDPOINT + "?stap=" + stap); } catch (e) { /* niet belangrijk */ }
  };

  // Datum zoals we hem tonen: 04-01-2027, of in het Engels 4 January 2027
  var datumNl = function (iso) {
    if (!iso) return "";
    var d = iso.split("-");
    return EN ? Number(d[2]) + " " + MAAND_EN[Number(d[1]) - 1] + " " + d[0] : d.reverse().join("-");
  };

  var toonStatus = function (soort, tekst) {
    status.className = "form-status form-status--" + soort;
    status.textContent = tekst;
  };

  var hernummer = function (el, i) {
    el.setAttribute("data-kind", String(i));
    [el].concat([].slice.call(el.querySelectorAll("[id], [for], [name], [aria-describedby], [data-fout]"))).forEach(function (node) {
      ["id", "for", "name", "aria-describedby", "data-fout"].forEach(function (attr) {
        var waarde = node.getAttribute(attr);
        if (waarde) node.setAttribute(attr, waarde.replace(/kinderen\[\d+\]/g, "kinderen[" + i + "]").replace(/\bk\d+-/g, "k" + i + "-"));
      });
    });
    return el;
  };

  /* ---------- Kinderen: blokken toevoegen en weghalen ---------- */

  var kindBlokken = function () { return [].slice.call(kinderenLijst.querySelectorAll("[data-kind]")); };

  var dagenTekst = function (aantal) {
    return aantal ? aantal + (aantal === 1 ? t(" dag", " day") : t(" dagen", " days")) + t(" per week", " a week") : "";
  };

  // Incidenteel (feedback 2.0): dan zijn vaste dagen niet verplicht
  var isIncidenteel = function (i) {
    var gekozen = form.querySelector('input[name="kinderen[' + i + '][abonnement]"]:checked');
    return Boolean(gekozen && gekozen.value === "incidenteel");
  };
  var werkIncidenteelBij = function () {
    form.querySelectorAll("[data-minstens-een]").forEach(function (groep) {
      var deel = groep.closest("[data-kind]");
      var los = isIncidenteel(deel ? deel.getAttribute("data-kind") : 0);
      var ster = groep.querySelector("[data-dagen-ster]");
      var uitleg = groep.querySelector("[data-dagen-incidenteel]");
      if (ster) ster.hidden = los;
      if (uitleg) uitleg.hidden = !los;
      if (los) zetFout(groep.querySelector('input[type="checkbox"]'), "");
    });
  };

  var kindNaam = function (i) {
    var veld = document.getElementById("k" + i + "-voornaam");
    var naam = veld ? veld.value.trim() : "";
    return naam || (kindBlokken().length > 1 ? t("kind ", "child ") + (i + 1) : t("je kind", "your child"));
  };

  var werkNamenBij = function () {
    var blokken = kindBlokken();
    var namen = blokken.map(function (b, i) { return kindNaam(i); });
    blokken.forEach(function (blok, i) {
      blok.querySelector("[data-kind-titel]").textContent = blokken.length > 1 ? t("Kind ", "Child ") + (i + 1) + (namen[i].indexOf(t("kind ", "child ")) === 0 ? "" : ": " + namen[i]) : t("Jouw kind", "Your child");
      blok.querySelector("[data-kind-weg]").hidden = blokken.length === 1;
    });
    perKind.forEach(function (wrap) {
      [].forEach.call(wrap.querySelectorAll("[data-kind]"), function (deel, i) {
        deel.querySelector("[data-kindnaam]").textContent = namen[i];
      });
    });
    form.querySelectorAll("[data-kind-weg] [data-kindnaam]").forEach(function (el, i) { el.textContent = namen[i]; });
    var samen = en(namen);
    form.querySelectorAll("[data-kindnamen]").forEach(function (el) { el.textContent = samen; });
    form.querySelectorAll("[data-zit]").forEach(function (el) { el.textContent = namen.length > 1 ? t("zitten", "do") : t("zit", "does"); });
    // De uitleg over de open dagen staat maar één keer, bij het eerste kind
    form.querySelectorAll("[data-dagen-beperkt]").forEach(function (el, i) { el.hidden = !dichteDagen.length || i > 0; });
    kindErbij.hidden = blokken.length >= MAX_KINDEREN;
    kindMax.hidden = blokken.length < MAX_KINDEREN;
  };

  var pasVolleDagenToe = function (root) {
    root.querySelectorAll("[data-dag]").forEach(function (vak) {
      var dag = parseInt(vak.getAttribute("data-dag"), 10);
      var vol = volleDagen.indexOf(dag) > -1;
      var dicht = dichteDagen.indexOf(dag) > -1;
      var keuze = vak.closest("label");
      vak.disabled = vol || dicht;
      if (dicht) vak.checked = false;
      if (keuze) keuze.classList.toggle("choice--dicht", dicht);
      if (vol) {
        vak.checked = false;
        var label = vak.closest("label");
        if (label && !label.querySelector(".vol")) label.insertAdjacentHTML("beforeend", ' <span class="vol">' + t("(vol)", "(full)") + '</span>');
        if (label) label.classList.add("choice--vol");
      }
    });
  };

  var vulStandaardStart = function () {
    form.querySelectorAll("[data-start-vanaf]").forEach(function (el) { if (!el.value) el.value = standaardStart; });
  };

  var zetAantal = function (aantal) {
    aantal = Math.max(1, Math.min(MAX_KINDEREN, aantal));
    while (kindBlokken().length < aantal) {
      var i = kindBlokken().length;
      kinderenLijst.appendChild(hernummer(kindSjabloon.cloneNode(true), i));
      perKind.forEach(function (wrap, n) {
        var deel = hernummer(deelSjablonen[n].cloneNode(true), i);
        pasVolleDagenToe(deel);
        wrap.appendChild(deel);
      });
    }
    while (kindBlokken().length > aantal) {
      kindBlokken().pop().remove();
      perKind.forEach(function (wrap) {
        var delen = wrap.querySelectorAll("[data-kind]");
        delen[delen.length - 1].remove();
      });
    }
    form.querySelectorAll("[data-max-today]").forEach(function (el) { el.max = vandaag; });
    form.querySelectorAll("[data-min-today]").forEach(function (el) { el.min = vandaag; });
    vulStandaardStart();
    werkNamenBij();
    werkIncidenteelBij();
  };

  // Een kind weghalen: de gegevens van de kinderen erna schuiven een plek op
  var haalKindWeg = function (index) {
    var data = verzamel();
    var aantal = kindBlokken().length;
    Object.keys(data.velden).forEach(function (naam) {
      var m = naam.match(/^kinderen\[(\d+)\](.*)$/);
      if (!m) return;
      var i = parseInt(m[1], 10);
      if (i === index) delete data.velden[naam];
      if (i > index) {
        data.velden["kinderen[" + (i - 1) + "]" + m[2]] = data.velden[naam];
        delete data.velden[naam];
      }
    });
    zetAantal(aantal - 1);
    form.querySelectorAll('[name^="kinderen["]').forEach(function (veld) { if (veld.type === "checkbox" || veld.type === "radio") veld.checked = false; else veld.value = veld.defaultValue; });
    vul(data.velden);
    vulStandaardStart();
    werkNamenBij();
    werkIncidenteelBij();
    bewaar();
  };

  /* ---------- Controleren ---------- */

  var isVeld = function (el) {
    return el.name && !el.disabled && ["hidden", "submit", "button"].indexOf(el.type) === -1 && !el.closest(".hp") && !el.closest("[hidden]");
  };

  var partnerJa = function () {
    var gekozen = form.querySelector('input[name="partner"]:checked');
    return gekozen && gekozen.value === "ja";
  };

  var zetFout = function (veld, tekst) {
    var fout = document.getElementById(veld.getAttribute("data-fout") || veld.id + "-fout");
    veld.setAttribute("aria-invalid", tekst ? "true" : "false");
    if (fout) {
      fout.querySelector("[data-error-text]").textContent = tekst;
      fout.hidden = !tekst;
    }
  };

  var valideer = function (veld) {
    var msg = function (sleutel, standaard) { return veld.getAttribute("data-msg-" + sleutel) || standaard; };
    var tekst = "";
    var groep = veld.closest("[data-minstens-een]");
    if (groep) {
      var vakjes = groep.querySelectorAll('input[type="checkbox"]');
      var deel = groep.closest("[data-kind]");
      var verplicht = !isIncidenteel(deel ? deel.getAttribute("data-kind") : 0);
      if (verplicht && ![].some.call(vakjes, function (v) { return v.checked; })) tekst = vakjes[0].getAttribute("data-msg-required") || t("Kies minstens één.", "Choose at least one.");
      zetFout(vakjes[0], tekst);
      return !tekst;
    }
    if (veld.hasAttribute("data-verplicht-bij-partner") && partnerJa() && !veld.value.trim()) {
      tekst = msg("required", t("Dit veld is verplicht.", "Please fill in this field."));
    } else {
      var v = veld.validity;
      if (v.valueMissing) tekst = msg("required", t("Dit veld is verplicht.", "Please fill in this field."));
      else if (v.badInput || v.typeMismatch) tekst = msg("type", t("Controleer dit veld nog even.", "Please check this field."));
      else if (v.patternMismatch) tekst = msg("pattern", t("Controleer dit veld nog even.", "Please check this field."));
      else if (v.rangeOverflow || v.rangeUnderflow) tekst = msg("range", t("Controleer dit veld nog even.", "Please check this field."));
    }
    zetFout(veld, tekst);
    return !tekst;
  };

  // De velden van een stap; van keuzerondjes en vinkjesgroepen telt alleen het eerste
  var veldenVan = function (stap) {
    var gezien = {};
    return [].filter.call(stap.querySelectorAll("input, select, textarea"), function (el) {
      if (!isVeld(el)) return false;
      if (el.type === "radio" || (el.type === "checkbox" && el.closest("[data-minstens-een]"))) {
        if (gezien[el.name]) return false;
        gezien[el.name] = true;
      }
      return true;
    });
  };

  var valideerStap = function (index) {
    var fout = veldenVan(stappen[index]).filter(function (veld) { return !valideer(veld); });
    if (fout.length) {
      toonStatus("error", fout.length === 1 ? t("Er is nog 1 veld niet goed ingevuld. Kijk even bij de melding in rood.", "1 field isn't filled in correctly yet. Have a look at the message in red.") : t("Er zijn nog " + fout.length + " velden niet goed ingevuld. Kijk even bij de meldingen in rood.", fout.length + " fields aren't filled in correctly yet. Have a look at the messages in red."));
      fout[0].focus();
      return false;
    }
    status.className = "form-status";
    status.textContent = "";
    return true;
  };

  /* ---------- Naar een stap ---------- */

  var gaNaar = function (index, metFocus) {
    huidig = Math.max(0, Math.min(stappen.length - 1, index));
    stappen.forEach(function (stap, i) { stap.hidden = i !== huidig; });
    var laatste = huidig === stappen.length - 1;
    verder.hidden = laatste;
    verstuur.hidden = !laatste;
    terug.hidden = huidig === 0;
    var stap = stappen[huidig];
    voortgang.querySelector("[data-voortgang-tekst]").textContent = t("Stap ", "Step ") + (huidig + 1) + t(" van ", " of ") + stappen.length;
    voortgang.querySelector("[data-voortgang-naam]").textContent = stap.getAttribute("data-stapnaam");
    voortgang.style.setProperty("--voortgang", String(huidig / (stappen.length - 1)));
    wolk.textContent = WOLKJES[huidig] || "";
    if (laatste) bouwOverzicht();
    tel(huidig + 1);
    clearTimeout(bewaarTimer);
    opslag.schrijf(verzamel());
    if (metFocus) {
      stap.focus({ preventScroll: true });
      var boven = voortgang.getBoundingClientRect().top;
      if (boven < 0 || boven > window.innerHeight * 0.4) voortgang.scrollIntoView({ block: "start", behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth" });
    }
  };

  /* ---------- Onthouden ---------- */

  var verzamel = function () {
    var velden = {};
    [].forEach.call(form.elements, function (el) {
      if (!el.name || el.closest(".hp") || el.hasAttribute("data-akkoord") || ["submit", "button"].indexOf(el.type) > -1) return;
      if (el.type === "checkbox") {
        if (el.name.slice(-2) === "[]") {
          velden[el.name] = velden[el.name] || [];
          if (el.checked) velden[el.name].push(el.value);
        }
        return;
      }
      if (el.type === "radio") {
        if (el.checked) velden[el.name] = el.value;
        return;
      }
      velden[el.name] = el.value;
    });
    return { aantal: kindBlokken().length, stap: huidig, velden: velden };
  };

  var vul = function (velden) {
    Object.keys(velden).forEach(function (naam) {
      var waarde = velden[naam];
      form.querySelectorAll('[name="' + naam.replace(/"/g, "") + '"]').forEach(function (el) {
        if (el.type === "checkbox") el.checked = Array.isArray(waarde) && waarde.indexOf(el.value) > -1 && !el.disabled;
        else if (el.type === "radio") el.checked = el.value === waarde;
        else el.value = waarde;
      });
    });
  };

  var bewaar = function () {
    clearTimeout(bewaarTimer);
    bewaarTimer = setTimeout(function () { if (!form.hidden) opslag.schrijf(verzamel()); }, 250);
  };

  /* ---------- Overzicht (stap 7) ---------- */

  var bouwOverzicht = function () {
    var data = verzamel().velden;
    var w = function (naam) { return (data[naam] || "").trim ? (data[naam] || "").trim() : data[naam]; };
    var labelVan = function (naamVeld, waarde) {
      var el = form.querySelector('[name="' + naamVeld + '"][value="' + waarde + '"]');
      if (!el) return "";
      var naam = el.closest("label").querySelector(".abo-keuze__naam");
      return (naam || el.closest("label")).textContent.replace(/\((vol|full)\)/, "").trim();
    };
    var blokken = [];
    kindBlokken().forEach(function (b, i) {
      var k = "kinderen[" + i + "]";
      var dagen = (data[k + "[dagen][]"] || []).map(function (d) { return DAGNAMEN[d]; });
      blokken.push({ titel: kindBlokken().length > 1 ? t("Kind ", "Child ") + (i + 1) : t("Je kind", "Your child"), stap: 0, regels: [
        [t("Naam", "Name"), [w(k + "[voornaam]"), w(k + "[tussenvoegsel]"), w(k + "[achternaam]")].filter(Boolean).join(" ")],
        [t("Voorletters", "Initials"), w(k + "[voorletters]")],
        [t("Geslacht", "Gender"), labelVan(k + "[geslacht]", data[k + "[geslacht]"])],
        [t("Geboortedatum", "Date of birth"), datumNl(w(k + "[geboortedatum]"))],
        [t("Gewenste start", "Preferred start date"), datumNl(w(k + "[startdatum]"))],
        [t("Groep of klas", "School year (groep) or class"), w(k + "[groep_klas]")],
        [t("Goed om te weten", "Good to know"), w(k + "[allergieen]")]
      ] });
      blokken.push({ titel: t("School van ", "School of ") + kindNaam(i), stap: 1, regels: [
        [t("School", "School"), w(k + "[school]")],
        [t("Sportvereniging", "Sports club"), w(k + "[sportvereniging]")]
      ] });
      blokken.push({ titel: t("Abonnement en dagen van ", "Membership and days for ") + kindNaam(i), stap: 3, regels: [
        [t("Abonnement", "Membership"), labelVan(k + "[abonnement]", data[k + "[abonnement]"])],
        [t("Dagen", "Days"), dagen.length ? dagenTekst(dagen.length) + ": " + dagen.join(", ") : (isIncidenteel(i) ? t("Geen vaste dagen (incidenteel)", "No fixed days (flexible)") : "")],
        [t("Toelichting", "Notes"), w(k + "[toelichting]")]
      ] });
    });
    blokken.push({ titel: t("Jouw gegevens", "Your details"), stap: 4, regels: [
      [t("Naam", "Name"), [w("voornaam"), w("tussenvoegsel"), w("achternaam")].filter(Boolean).join(" ")],
      [t("Voorletters", "Initials"), w("voorletters")],
      [t("Geslacht", "Gender"), data.geslacht ? labelVan("geslacht", data.geslacht) : ""],
      [t("Adres", "Address"), [w("straat") + " " + w("huisnummer"), (w("postcode") + " " + w("plaats")).trim()].filter(function (r) { return r.trim(); }).join(", ")],
      [t("E-mail", "Email"), w("email")],
      [t("Telefoon", "Phone"), w("telefoon")],
      [t("Gevonden via", "Found us via"), w("gevonden") ? form.querySelector('#gevonden option[value="' + w("gevonden") + '"]').textContent : ""],
      [t("Opmerkingen", "Comments"), w("opmerkingen")]
    ] });
    blokken.push({ titel: "Partner", stap: 5, regels: partnerJa() ? [
      [t("Naam", "Name"), [w("partner_voornaam"), w("partner_tussenvoegsel"), w("partner_achternaam")].filter(Boolean).join(" ")],
      [t("E-mail", "Email"), w("partner_email")],
      [t("Telefoon", "Phone"), w("partner_telefoon")]
    ] : [["Partner", t("Niet toegevoegd", "Not added")]] });

    overzicht.textContent = "";
    blokken.forEach(function (blok) {
      var sectie = document.createElement("div");
      sectie.className = "overzicht__blok";
      var kop = document.createElement("div");
      kop.className = "overzicht__kop";
      var h = document.createElement("h3");
      h.textContent = blok.titel;
      var knop = document.createElement("button");
      knop.type = "button";
      knop.className = "link-button overzicht__aanpassen";
      knop.setAttribute("data-naar", String(blok.stap));
      knop.innerHTML = '<svg class="icon" aria-hidden="true" focusable="false"><use href="#i-potlood"></use></svg>' + t("Aanpassen", "Edit") + '<span class="visually-hidden"></span>';
      knop.querySelector(".visually-hidden").textContent = ": " + blok.titel.charAt(0).toLowerCase() + blok.titel.slice(1);
      kop.appendChild(h);
      kop.appendChild(knop);
      sectie.appendChild(kop);
      var dl = document.createElement("dl");
      blok.regels.forEach(function (regel) {
        if (!regel[1]) return;
        var dt = document.createElement("dt");
        dt.textContent = regel[0];
        var dd = document.createElement("dd");
        dd.textContent = regel[1];
        dl.appendChild(dt);
        dl.appendChild(dd);
      });
      sectie.appendChild(dl);
      overzicht.appendChild(sectie);
    });
  };

  /* ---------- Gebeurtenissen ---------- */

  verder.addEventListener("click", function () {
    if (valideerStap(huidig)) gaNaar(huidig + 1, true);
  });
  terug.addEventListener("click", function () {
    gaNaar(huidig - 1, true);
  });
  overzicht.addEventListener("click", function (event) {
    var knop = event.target.closest("[data-naar]");
    if (knop) gaNaar(parseInt(knop.getAttribute("data-naar"), 10), true);
  });

  kindErbij.addEventListener("click", function () {
    zetAantal(kindBlokken().length + 1);
    var nieuw = kindBlokken()[kindBlokken().length - 1];
    nieuw.querySelector("input").focus();
    bewaar();
  });
  kinderenLijst.addEventListener("click", function (event) {
    var knop = event.target.closest("[data-kind-weg]");
    if (!knop) return;
    var index = kindBlokken().indexOf(knop.closest("[data-kind]"));
    haalKindWeg(index);
    kindErbij.hidden ? kinderenLijst.querySelector("input").focus() : kindErbij.focus();
  });

  form.addEventListener("input", function (event) {
    if (event.target.hasAttribute("data-kind-voornaam")) werkNamenBij();
    if (event.target.getAttribute("aria-invalid") === "true") valideer(event.target);
    bewaar();
  });
  form.addEventListener("change", function (event) {
    var el = event.target;
    if (el.name === "partner") partnerBlok.hidden = !partnerJa();
    if (/\[abonnement\]$/.test(el.name)) werkIncidenteelBij();
    if (el.closest("[data-minstens-een]")) {
      var groep = el.closest("[data-minstens-een]");
      var aantal = groep.querySelectorAll("input:checked").length;
      groep.querySelector("[data-dagen-aantal]").textContent = dagenTekst(aantal);
      if (groep.querySelector('[aria-invalid="true"]')) valideer(el);
    } else if (el.type === "radio" || el.type === "checkbox") {
      var eerste = el.type === "radio" ? form.querySelector('input[name="' + el.name + '"]') : el;
      if (eerste.getAttribute("aria-invalid") === "true") valideer(eerste);
    }
    if (alles && el.hasAttribute("data-akkoord")) alles.checked = [].every.call(form.querySelectorAll("[data-akkoord]"), function (v) { return v.checked; });
    if (alles && el === alles) form.querySelectorAll("[data-akkoord]").forEach(function (v) { v.checked = alles.checked; zetFout(v, ""); });
    bewaar();
  });
  form.addEventListener("focusout", function (event) {
    var el = event.target;
    if (!isVeld(el) || el.type === "radio" || el.type === "checkbox") return;
    if (el.value.trim() !== "" || el.getAttribute("aria-invalid") === "true") valideer(el);
  });

  bewaardMelding.querySelector("[data-opnieuw]").addEventListener("click", function () {
    opslag.wis();
    form.reset();
    zetAantal(1);
    form.querySelectorAll('[aria-invalid="true"]').forEach(function (el) { zetFout(el, ""); });
    partnerBlok.hidden = true;
    bewaardMelding.hidden = true;
    gaNaar(0, true);
  });

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    // Enter in een veld: naar de volgende stap
    if (huidig < stappen.length - 1) {
      verder.click();
      return;
    }
    // Alle stappen nog een keer controleren
    for (var i = 0; i < stappen.length; i++) {
      var ok = veldenVan(stappen[i]).every(function (veld) { return valideer(veld); });
      if (!ok) {
        if (i !== huidig) gaNaar(i, true);
        valideerStap(i);
        return;
      }
    }
    if (form.querySelector(".hp input").value) return;

    var tekst = verstuur.innerHTML;
    verstuur.disabled = true;
    verstuur.textContent = t("Bezig met versturen…", "Sending…");
    form.setAttribute("aria-busy", "true");
    fetch(ENDPOINT, { method: "POST", body: new FormData(form), headers: { Accept: "application/json" } })
      .then(function (antwoord) {
        if ((antwoord.headers.get("Content-Type") || "").indexOf("application/json") === -1) throw { geenServer: true };
        return antwoord.json().then(function (body) { return { ok: antwoord.ok && body.ok !== false, body: body }; });
      })
      .then(function (resultaat) {
        if (resultaat.ok) {
          clearTimeout(bewaarTimer);
          opslag.wis();
          form.hidden = true;
          bewaardMelding.hidden = true;
          voortgang.style.setProperty("--voortgang", "1");
          voortgang.classList.add("is-klaar");
          voortgang.querySelector("[data-voortgang-tekst]").textContent = t("Verstuurd", "Sent");
          voortgang.querySelector("[data-voortgang-naam]").textContent = t("Welkom bij de club!", "Welcome to the club!");
          wolk.textContent = t("Woef! Tot snel bij de club!", "Woof! See you soon at the club!");
          klaar.hidden = false;
          klaar.focus({ preventScroll: true });
          klaar.scrollIntoView({ block: "start", behavior: "auto" });
          return;
        }
        var fouten = resultaat.body.fouten || {};
        var eerste = null;
        Object.keys(fouten).forEach(function (naam) {
          var veld = form.querySelector('[name="' + naam.replace(/"/g, "") + '"]');
          if (!veld) return;
          zetFout(veld, fouten[naam]);
          eerste = eerste || veld;
        });
        if (eerste) {
          var stap = stappen.indexOf(eerste.closest("[data-stap]"));
          if (stap !== huidig) gaNaar(stap, true);
          eerste.focus();
        }
        toonStatus("error", resultaat.body.melding || t("Oeps, het versturen is niet gelukt. Probeer het nog eens of bel ons even.", "Oops, that didn't work. Please try again or give us a call."));
      })
      .catch(function (fout) {
        toonStatus("error", fout && fout.geenServer ? form.getAttribute("data-geen-server") : t("Oeps, het versturen is niet gelukt. Probeer het nog eens of bel ons even.", "Oops, that didn't work. Please try again or give us a call."));
      })
      .then(function () {
        verstuur.disabled = false;
        verstuur.innerHTML = tekst;
        form.removeAttribute("aria-busy");
      });
  });

  // Bij het verlaten van de pagina meteen opslaan
  window.addEventListener("pagehide", function () {
    if (!form.hidden) opslag.schrijf(verzamel());
  });

  /* ---------- Start ---------- */

  form.classList.add("is-in-stappen");
  voortgang.hidden = false;
  if (alles) alles.closest("[data-alles]").hidden = false;
  partnerBlok.hidden = true;
  pasVolleDagenToe(form);
  zetAantal(1);

  var bewaard = opslag.lees();
  var startStap = 0;
  if (bewaard && bewaard.velden && Object.keys(bewaard.velden).some(function (k) { var v = bewaard.velden[k]; return Array.isArray(v) ? v.length : v && v !== standaardStart && v !== "sporty-52"; })) {
    zetAantal(bewaard.aantal || 1);
    vul(bewaard.velden);
    vulStandaardStart();
    partnerBlok.hidden = !partnerJa();
    werkNamenBij();
    werkIncidenteelBij();
    form.querySelectorAll("[data-minstens-een]").forEach(function (groep) {
      var aantal = groep.querySelectorAll("input:checked").length;
      groep.querySelector("[data-dagen-aantal]").textContent = dagenTekst(aantal);
    });
    bewaardMelding.hidden = false;
    startStap = Math.min(bewaard.stap || 0, stappen.length - 1);
  }
  gaNaar(startStap, false);

  // Dagen die vol zitten (stelt het team in bij Groepen)
  fetch(ENDPOINT + "?info=1", { headers: { Accept: "application/json" } })
    .then(function (antwoord) {
      if ((antwoord.headers.get("Content-Type") || "").indexOf("json") === -1) throw new Error("geen beheeromgeving");
      return antwoord.json();
    })
    .then(function (info) {
      volleDagen = (info.volle_dagen || []).map(Number);
      if (!volleDagen.length) return;
      pasVolleDagenToe(form);
      var uitleg = form.querySelector("[data-volle-dagen]");
      var namen = volleDagen.map(function (d) { return DAGNAMEN[d]; });
      uitleg.textContent = EN
        ? "We're currently full on " + en(namen) + ". Would you still like that day? Mention it in the notes and we'll look at the waiting list."
        : "Op " + en(namen) + " zitten we op dit moment vol. Wil je toch graag die dag? Zet het bij de toelichting, dan kijken we naar de wachtlijst.";
      uitleg.hidden = false;
    })
    .catch(function () { /* zonder beheeromgeving: alle dagen kiesbaar */ });
})();
