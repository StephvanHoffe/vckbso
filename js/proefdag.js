/*
 * Proefdag boeken (proefdag.html): haalt de momenten op die het team in de beheeromgeving heeft
 * klaargezet (beheer/proefdag.php?momenten=1) en toont ze in een agenda: je klikt een dag aan en kiest
 * daarna een tijd.
 * Het versturen, de controle en de foutmeldingen regelt js/main.js (form[data-form]).
 *
 * Lukt het ophalen niet (geen beheeromgeving, zoals op een statische voorbeeldsite) of staat er
 * niets klaar, dan blijft het formulier verborgen en ziet de bezoeker de terugval: een berichtje
 * sturen of bellen.
 */
(function () {
  "use strict";

  var wrap = document.querySelector("[data-proefdag]");
  if (!wrap || !window.fetch) return;

  var bron = wrap.getAttribute("data-proefdag");
  var form = wrap.querySelector("form");
  var lijst = wrap.querySelector("[data-proefdag-momenten]");
  var klaar = wrap.querySelector("[data-proefdag-klaar]");
  var terugval = document.querySelector("[data-boeken-terugval]");
  var terugvalTekst = terugval && terugval.querySelector("[data-terugval-tekst]");
  var wolk = document.querySelector("[data-henny-wolk]");
  var vandaag = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

  var maak = function (tag, klasse, tekst) {
    var el = document.createElement(tag);
    if (klasse) el.className = klasse;
    if (tekst) el.textContent = tekst;
    return el;
  };

  var MAANDEN = ["januari", "februari", "maart", "april", "mei", "juni", "juli", "augustus", "september", "oktober", "november", "december"];
  var WEEKDAGEN = [["ma", "maandag"], ["di", "dinsdag"], ["wo", "woensdag"], ["do", "donderdag"], ["vr", "vrijdag"]];
  var iso = function (d) { return d.toISOString().slice(0, 10); };
  var groot = function (tekst) { return tekst.charAt(0).toUpperCase() + tekst.slice(1); };

  /*
   * Agenda: een maand met maandag t/m vrijdag. Alleen dagen waarop het team een moment heeft klaargezet
   * (nu dinsdag en donderdag) zijn aan te klikken; daaronder verschijnen de tijden van die dag als keuzerondjes.
   * Alle keuzerondjes staan in het formulier (per dag een blok, verborgen tot je de dag kiest), zodat de
   * controle van js/main.js gewoon werkt.
   */
  var toon = function (momenten) {
    var perDag = {};
    var dagen = [];
    momenten.forEach(function (m) {
      if (!perDag[m.datum]) {
        perDag[m.datum] = [];
        dagen.push(m.datum);
      }
      perDag[m.datum].push(m);
    });
    dagen.sort();

    var kalender = wrap.querySelector("[data-proefdag-kalender]");
    var keuzeTekst = wrap.querySelector("[data-proefdag-keuze]");
    var blokken = {};
    var gekozen = null;

    dagen.forEach(function (datum) {
      var naam = perDag[datum][0].dag;
      var dag = maak("div", "momenten__dag");
      dag.hidden = true;
      dag.appendChild(maak("p", "momenten__datum", groot(naam)));
      var tijden = maak("div", "momenten__tijden");
      perDag[datum].forEach(function (m) {
        var label = maak("label", "moment");
        var rondje = document.createElement("input");
        rondje.type = "radio";
        rondje.name = "moment_id";
        rondje.value = String(m.id);
        rondje.required = true;
        rondje.setAttribute("data-fout", "moment-fout");
        rondje.setAttribute("data-msg-required", "Kies een dag in de agenda en daarna een tijd.");
        rondje.setAttribute("aria-describedby", "moment-fout");
        label.appendChild(rondje);
        label.appendChild(maak("span", "visually-hidden", naam + ", "));
        label.appendChild(maak("span", "moment__tijd", m.begin + "–" + m.eind + " uur"));
        label.appendChild(maak("span", "moment__vrij", m.vrij === 1 ? "nog 1 plek" : "nog " + m.vrij + " plekken"));
        tijden.appendChild(label);
      });
      dag.appendChild(tijden);
      lijst.appendChild(dag);
      blokken[datum] = dag;
    });

    var maanden = [];
    var eerste = new Date(dagen[0].slice(0, 7) + "-01T00:00:00Z");
    var laatste = dagen[dagen.length - 1].slice(0, 7);
    for (var d = new Date(eerste); iso(d).slice(0, 7) <= laatste; d.setUTCMonth(d.getUTCMonth() + 1)) maanden.push(iso(d).slice(0, 7));
    var maand = 0;

    var kies = function (datum) {
      gekozen = datum;
      Object.keys(blokken).forEach(function (k) {
        blokken[k].hidden = k !== datum;
        if (k !== datum) blokken[k].querySelectorAll("input").forEach(function (i) { i.checked = false; });
      });
      kalender.querySelectorAll("[data-datum]").forEach(function (knop) {
        knop.setAttribute("aria-pressed", String(knop.getAttribute("data-datum") === datum));
      });
      var rondjes = blokken[datum].querySelectorAll("input");
      if (rondjes.length === 1) {
        rondjes[0].checked = true;
        rondjes[0].dispatchEvent(new Event("change", { bubbles: true }));
      }
      // De gekozen dag staat al boven de tijden: de aanwijzing is alleen nog voor schermlezers
      keuzeTekst.classList.add("visually-hidden");
      keuzeTekst.textContent = groot(perDag[datum][0].dag) + " gekozen. " + (rondjes.length === 1 ? "Er is één tijd, die staat al aan." : "Kies hieronder een tijd.");
    };

    var teken = function () {
      kalender.textContent = "";
      var jaarMaand = maanden[maand].split("-").map(Number);
      var kop = maak("div", "kalender__kop");
      var terug = maak("button", "kalender__pijl");
      terug.type = "button";
      terug.innerHTML = '<svg class="icon" aria-hidden="true" focusable="false"><use href="#i-arrow"></use></svg><span class="visually-hidden">Vorige maand</span>';
      terug.disabled = maand === 0;
      var verder = maak("button", "kalender__pijl kalender__pijl--verder");
      verder.type = "button";
      verder.innerHTML = '<svg class="icon" aria-hidden="true" focusable="false"><use href="#i-arrow"></use></svg><span class="visually-hidden">Volgende maand</span>';
      verder.disabled = maand === maanden.length - 1;
      var titel = maak("p", "kalender__maand", MAANDEN[jaarMaand[1] - 1] + " " + jaarMaand[0]);
      titel.id = "kalender-maand";
      titel.setAttribute("aria-live", "polite");
      kop.appendChild(terug);
      kop.appendChild(titel);
      kop.appendChild(verder);
      terug.addEventListener("click", function () { maand--; teken(); (maand === 0 ? verder : terug).focus(); });
      verder.addEventListener("click", function () { maand++; teken(); (maand === maanden.length - 1 ? kalender.querySelector(".kalender__pijl") : kalender.querySelector(".kalender__pijl--verder")).focus(); });
      kalender.appendChild(kop);

      var tabel = maak("table", "kalender__tabel");
      tabel.setAttribute("aria-labelledby", "kalender-maand");
      var thead = document.createElement("thead");
      var rij = document.createElement("tr");
      WEEKDAGEN.forEach(function (w) {
        var th = maak("th", "", w[0]);
        th.scope = "col";
        th.setAttribute("abbr", w[1]);
        rij.appendChild(th);
      });
      thead.appendChild(rij);
      tabel.appendChild(thead);
      var tbody = document.createElement("tbody");
      var begin = new Date(Date.UTC(jaarMaand[0], jaarMaand[1] - 1, 1));
      begin.setUTCDate(begin.getUTCDate() - ((begin.getUTCDay() + 6) % 7));
      for (var week = 0; week < 6; week++) {
        var r = document.createElement("tr");
        var iets = false;
        for (var wd = 0; wd < 5; wd++) {
          var dag = new Date(begin);
          dag.setUTCDate(begin.getUTCDate() + week * 7 + wd);
          var td = document.createElement("td");
          if (dag.getUTCMonth() === jaarMaand[1] - 1) {
            iets = true;
            var datum = iso(dag);
            if (perDag[datum]) {
              var knop = maak("button", "kalender__dag", String(dag.getUTCDate()));
              knop.type = "button";
              knop.setAttribute("data-datum", datum);
              knop.setAttribute("aria-pressed", String(datum === gekozen));
              var aantal = perDag[datum].length;
              knop.setAttribute("aria-label", groot(perDag[datum][0].dag) + ", " + aantal + (aantal === 1 ? " moment" : " momenten") + " vrij");
              knop.addEventListener("click", function (event) { kies(event.currentTarget.getAttribute("data-datum")); });
              td.appendChild(knop);
            } else {
              var leeg = maak("span", "kalender__dag kalender__dag--dicht", String(dag.getUTCDate()));
              td.appendChild(leeg);
            }
          }
          r.appendChild(td);
        }
        if (iets) tbody.appendChild(r);
      }
      tabel.appendChild(tbody);
      kalender.appendChild(tabel);
    };

    teken();

    // Versturen zonder moment: de melding staat onder de agenda; zet de focus op de agenda in plaats van op een verborgen rondje
    form.addEventListener("submit", function () {
      if (form.querySelector('input[name="moment_id"]:checked')) return;
      setTimeout(function () {
        var doel = kalender.querySelector('[aria-pressed="true"]') || kalender.querySelector("button.kalender__dag");
        if (doel) doel.focus();
      }, 0);
    });
  };

  fetch(bron + (bron.indexOf("?") > -1 ? "&" : "?") + "momenten=1", { headers: { Accept: "application/json" } })
    .then(function (antwoord) {
      if ((antwoord.headers.get("Content-Type") || "").indexOf("json") === -1) throw new Error("geen beheeromgeving");
      return antwoord.json();
    })
    .then(function (data) {
      var momenten = ((data && data.momenten) || []).filter(function (m) { return m.datum > vandaag && m.vrij > 0; });
      if (!momenten.length) {
        if (terugvalTekst) terugvalTekst.textContent = terugvalTekst.getAttribute("data-geen-momenten");
        return;
      }
      toon(momenten);
      wrap.hidden = false;
      if (terugval) terugval.hidden = true;
    })
    .catch(function () {
      // De terugval blijft gewoon staan
    });

  // Geboekt: het formulier maakt plaats voor de bevestiging, en Henny is blij
  form.addEventListener("formulier-verstuurd", function (event) {
    var wanneer = (event.detail && event.detail.moment) || "";
    klaar.querySelector("[data-proefdag-wanneer]").textContent = wanneer;
    form.hidden = true;
    klaar.hidden = false;
    if (wolk) wolk.textContent = "Joepie! Ik zie je snel!";
    setTimeout(function () {
      klaar.focus();
    }, 0);
  });
})();
