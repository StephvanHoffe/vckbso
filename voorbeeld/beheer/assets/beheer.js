/* Beheeromgeving Sporty: kleine verbeteringen. Alles werkt ook zonder JavaScript. */
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

  /* QR-code voor de authenticator-app (tweestapsverificatie) */
  document.querySelectorAll("[data-qr]").forEach(function (plek) {
    if (typeof window.qrcode !== "function") return;
    var qr = window.qrcode(0, "M");
    qr.addData(plek.getAttribute("data-qr"));
    qr.make();
    plek.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 3, scalable: true });
  });

  /* Factuur printen of als pdf opslaan */
  document.querySelectorAll("[data-print]").forEach(function (knop) {
    knop.hidden = false;
    knop.addEventListener("click", function () { window.print(); });
  });

  /* Rechten van een collega: snel alles aan, het pakket voor begeleiders of alles uit */
  document.querySelectorAll("[data-rechten-form]").forEach(function (form) {
    form.querySelectorAll("[data-rechten-snel]").forEach(function (el) { el.hidden = false; });
    form.addEventListener("click", function (event) {
      var knop = event.target.closest("[data-rechten-preset]");
      if (!knop) return;
      var keuze = knop.getAttribute("data-rechten-preset");
      form.querySelectorAll("input[name='rechten[]']").forEach(function (vakje) {
        vakje.checked = keuze === "alles" || (keuze === "begeleider" && vakje.getAttribute("data-begeleider") === "1");
      });
    });
  });

  /* Grafiek Financiën: per maand een tooltip met beide bedragen (muis en toetsenbord) */
  document.querySelectorAll("[data-grafiek]").forEach(function (grafiek) {
    var tip = document.createElement("div");
    tip.className = "grafiek-tip";
    tip.hidden = true;
    tip.setAttribute("aria-hidden", "true");
    grafiek.appendChild(tip);
    function rij(tekst, bedrag, kleur) {
      var r = document.createElement("div");
      r.className = "grafiek-tip__rij";
      var sleutel = document.createElement("span");
      sleutel.className = "grafiek-tip__sleutel";
      sleutel.style.background = kleur;
      var waarde = document.createElement("strong");
      waarde.textContent = bedrag;
      r.append(sleutel, waarde, document.createTextNode(" " + tekst));
      return r;
    }
    function toon(maand) {
      var stijl = getComputedStyle(grafiek);
      tip.textContent = "";
      var titel = document.createElement("div");
      titel.className = "grafiek-tip__titel";
      titel.textContent = maand.getAttribute("data-tip-titel");
      tip.append(titel, rij("inkomsten", maand.getAttribute("data-tip-in"), stijl.getPropertyValue("--reeks-in")), rij("uitgaven", maand.getAttribute("data-tip-uit"), stijl.getPropertyValue("--reeks-uit")));
      tip.hidden = false;
      var vak = grafiek.getBoundingClientRect();
      var m = maand.getBoundingClientRect();
      var links = m.left - vak.left + grafiek.scrollLeft + m.width / 2 - tip.offsetWidth / 2;
      tip.style.left = Math.max(0, Math.min(links, grafiek.scrollWidth - tip.offsetWidth)) + "px";
      tip.style.top = "0px";
    }
    grafiek.addEventListener("pointerover", function (event) {
      var maand = event.target.closest(".grafiek__maand");
      if (maand) toon(maand);
    });
    grafiek.addEventListener("pointerleave", function () { tip.hidden = true; });
    grafiek.addEventListener("focusin", function (event) {
      var maand = event.target.closest(".grafiek__maand");
      if (maand) toon(maand);
    });
    grafiek.addEventListener("focusout", function () { tip.hidden = true; });
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

  /* Aanmelden: Henny rent mee naar het doel terwijl je de verplichte velden invult */
  document.querySelectorAll("[data-henny-baan]").forEach(function (baan) {
    var form = baan.closest("form");
    var wolk = baan.querySelector("[data-henny-wolk]");
    if (!form || !wolk) return;
    var kop = document.querySelector(".app-header");
    var secties = Array.prototype.filter.call(form.children, function (el) { return el.tagName === "FIELDSET"; });
    // Wat Henny zegt zodra deze stap aan de beurt is (op volgorde van de secties in het formulier)
    var zinnen = [
      "Woef! Zullen we beginnen?",
      "Top! En nu: wie is je kleine topper?",
      "Lekker bezig! Wanneer komt je kind sporten?",
      "Bijna! Nog een wachtwoord. Ik kijk niet, beloofd.",
      "Nog twee vinkjes en we zijn bij het doel!"
    ];
    var klaarZin = "Je hebt je kleine topper bijna aangemeld, door naar de volgende stap!";
    var verplicht = function (el) {
      return Array.prototype.filter.call(el.querySelectorAll("input[required], select[required], textarea[required]"), function (veld) {
        return !veld.disabled && !veld.closest("template");
      });
    };

    var begonnen = false;
    var bijwerken = function (event) {
      if (event) begonnen = true;
      var velden = verplicht(form);
      var goed = velden.filter(function (veld) { return veld.value.trim() !== "" && veld.checkValidity(); }).length;
      var voortgang = velden.length ? goed / velden.length : 0;
      var beurt = secties.findIndex(function (sectie) {
        return verplicht(sectie).some(function (veld) { return veld.value.trim() === "" || !veld.checkValidity(); });
      });
      var zin = beurt === -1 ? klaarZin : beurt === 0 && begonnen ? "Goed zo! Eerst nog even jouw gegevens." : zinnen[Math.min(beurt, zinnen.length - 1)];
      baan.style.setProperty("--voortgang", voortgang.toFixed(3));
      baan.classList.toggle("is-klaar", beurt === -1);
      if (wolk.textContent !== zin) wolk.textContent = zin;
    };

    var kopHoogte = function () {
      if (kop) document.documentElement.style.setProperty("--kop-hoogte", kop.offsetHeight + "px");
    };

    baan.hidden = false;
    kopHoogte();
    bijwerken();
    form.addEventListener("input", bijwerken);
    form.addEventListener("change", bijwerken);
    form.addEventListener("click", function (event) { window.setTimeout(function () { bijwerken(event); }, 0); });
    window.addEventListener("resize", kopHoogte);
  });
})();
