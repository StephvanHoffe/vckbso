/* Beheeromgeving BSO VCK: kleine verbeteringen. Alles werkt ook zonder JavaScript. */
(function () {
  "use strict";

  document.documentElement.classList.add("js");

  /* Bevestigen voor acties die je niet makkelijk terugdraait */
  document.addEventListener("submit", function (event) {
    var vraag = event.target.getAttribute("data-bevestig");
    if (vraag && !window.confirm(vraag)) event.preventDefault();
  });

  /* Foutmeldingen bovenaan het formulier in beeld en voorgelezen */
  var fout = document.querySelector("[data-focus]");
  if (fout) fout.focus();

  /* Gesprek: nieuwste bericht in beeld */
  document.querySelectorAll("[data-scroll-einde]").forEach(function (lijst) {
    lijst.scrollTop = lijst.scrollHeight;
  });

  /* Aanmelden: kinderen toevoegen en verwijderen */
  document.querySelectorAll("[data-kinderen]").forEach(function (wrap) {
    var lijst = wrap.querySelector("[data-kinderen-lijst]");
    var knop = wrap.querySelector("[data-kind-toevoegen]");
    var sjabloon = wrap.querySelector("template");
    var max = parseInt(wrap.getAttribute("data-max") || "6", 10);
    if (!lijst || !knop || !sjabloon) return;

    var bijwerken = function () {
      var items = lijst.querySelectorAll("[data-kind]");
      items.forEach(function (item, i) {
        item.querySelector("[data-kind-nummer]").textContent = String(i + 1);
        var verwijder = item.querySelector("[data-kind-verwijderen]");
        if (verwijder) verwijder.hidden = items.length === 1;
      });
      knop.hidden = items.length >= max;
    };

    knop.hidden = false;
    knop.addEventListener("click", function () {
      var index = Date.now();
      var html = sjabloon.innerHTML.replace(/__INDEX__/g, String(index));
      lijst.insertAdjacentHTML("beforeend", html);
      bijwerken();
      var nieuw = lijst.lastElementChild.querySelector("input");
      if (nieuw) nieuw.focus();
    });

    lijst.addEventListener("click", function (event) {
      var verwijder = event.target.closest("[data-kind-verwijderen]");
      if (!verwijder) return;
      verwijder.closest("[data-kind]").remove();
      bijwerken();
      knop.focus();
    });

    bijwerken();
  });

  /* Foto's uploaden: aantal gekozen bestanden tonen */
  document.querySelectorAll("input[type=file][data-teller]").forEach(function (input) {
    var uitvoer = document.getElementById(input.getAttribute("data-teller"));
    input.addEventListener("change", function () {
      if (!uitvoer) return;
      var n = input.files ? input.files.length : 0;
      uitvoer.textContent = n === 0 ? "" : n === 1 ? "1 foto gekozen" : n + " foto's gekozen";
    });
  });

  /* Factuur printen of als pdf opslaan */
  document.querySelectorAll("[data-print]").forEach(function (knop) {
    knop.hidden = false;
    knop.addEventListener("click", function () { window.print(); });
  });

  /* Alle kinderen van een groep in één keer aanvinken */
  document.querySelectorAll("[data-alles-aan]").forEach(function (knop) {
    knop.hidden = false;
    knop.addEventListener("click", function () {
      var groep = document.getElementById(knop.getAttribute("data-alles-aan"));
      if (!groep) return;
      var vakjes = groep.querySelectorAll("input[type=checkbox]:not(:disabled)");
      var allemaal = Array.prototype.every.call(vakjes, function (v) { return v.checked; });
      vakjes.forEach(function (v) { v.checked = !allemaal; });
    });
  });
})();
