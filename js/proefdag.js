/*
 * Proefdag boeken (proefdag.html): haalt de momenten op die het team in de beheeromgeving heeft
 * klaargezet (beheer/proefdag.php?momenten=1) en toont ze als keuzerondjes, per dag.
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

    dagen.forEach(function (datum) {
      var dag = maak("div", "momenten__dag");
      var naam = perDag[datum][0].dag;
      dag.appendChild(maak("p", "momenten__datum", naam.charAt(0).toUpperCase() + naam.slice(1)));
      var tijden = maak("div", "momenten__tijden");
      perDag[datum].forEach(function (m) {
        var label = maak("label", "moment");
        var rondje = document.createElement("input");
        rondje.type = "radio";
        rondje.name = "moment_id";
        rondje.value = String(m.id);
        rondje.required = true;
        rondje.setAttribute("data-fout", "moment-fout");
        rondje.setAttribute("data-msg-required", "Kies een moment voor je proefdag.");
        rondje.setAttribute("aria-describedby", "moment-fout");
        label.appendChild(rondje);
        label.appendChild(maak("span", "visually-hidden", naam + ", "));
        label.appendChild(maak("span", "moment__tijd", m.begin + "–" + m.eind + " uur"));
        label.appendChild(maak("span", "moment__vrij", m.vrij === 1 ? "nog 1 plek" : "nog " + m.vrij + " plekken"));
        tijden.appendChild(label);
      });
      dag.appendChild(tijden);
      lijst.appendChild(dag);
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
