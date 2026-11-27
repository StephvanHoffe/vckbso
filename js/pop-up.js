/*
 * Pop-up "Blijf op de hoogte" (feedback 2.0, november 2026).
 * Verschijnt één keer als iemand ongeveer 30 seconden op de site is (opgeteld over de pagina's van dit bezoek).
 * Eerst 10 seconden; op 25 november 2026 op verzoek van de klant 30 seconden ("hij komt wel erg snel").
 * - Weggeklikt? Dan pas na OPNIEUW_NA_DAGEN dagen weer. Aangemeld? Dan nooit meer.
 * - Niet tijdens de intro op de homepage, niet als het menu of de cookiemelding open is, niet terwijl iemand een veld invult en niet tijdens een filmpje met geluid.
 * - Zonder opslag in de browser (bijvoorbeeld een privévenster dat dat blokkeert) verschijnt hij niet,
 *   anders zou hij op elke pagina opnieuw opduiken.
 * - Een <dialog> met showModal(): de focus blijft in de pop-up, Esc sluit hem, en daarna gaat de focus terug.
 * Het formulier zelf werkt via js/main.js (data-form), net als op blijf-op-de-hoogte.html.
 * De pop-up staat in tools/site/voet.html en voet.en.html; op welke pagina's niet: tools/kop-en-voet.mjs.
 */
(function () {
  "use strict";
  var dialoog = document.querySelector("[data-pop-up]");
  if (!dialoog || typeof dialoog.showModal !== "function") return;

  var SLEUTEL = "sporty-pop-up"; // localStorage: { getoond: tijdstip, aangemeld: true }
  var START = "sporty-pop-up-start"; // sessionStorage: begin van dit bezoek
  var WACHT = 30000; // ongeveer 30 seconden op de site
  var OPNIEUW_NA_DAGEN = 30;
  var DAG = 24 * 60 * 60 * 1000;

  var stand;
  try {
    stand = JSON.parse(localStorage.getItem(SLEUTEL) || "null");
    localStorage.setItem(SLEUTEL + "-test", "1");
    localStorage.removeItem(SLEUTEL + "-test");
  } catch (e) {
    return;
  }
  var bewaar = function (nieuw) {
    try { localStorage.setItem(SLEUTEL, JSON.stringify(nieuw)); } catch (e) { /* niets */ }
  };
  if (stand && (stand.aangemeld || Date.now() - (stand.getoond || 0) < OPNIEUW_NA_DAGEN * DAG)) return;

  var start = Date.now();
  try {
    var eerder = parseInt(sessionStorage.getItem(START), 10);
    if (eerder && eerder <= start) start = eerder; else sessionStorage.setItem(START, String(start));
  } catch (e) { /* dan telt alleen deze pagina */ }

  var form = dialoog.querySelector("[data-pop-up-form]");
  var klaar = dialoog.querySelector("[data-pop-up-klaar]");
  var root = document.documentElement;
  var vorigeFocus = null;

  // Is de bezoeker net met iets anders bezig?
  var bezig = function () {
    var actief = document.activeElement;
    var typt = actief && actief !== document.body && actief.matches("input, select, textarea, [contenteditable]");
    // Kijkt iemand een filmpje met geluid (zoals op de homepage)? Dan niet storen; de stille juichfilmpjes tellen niet mee.
    var kijkt = Array.prototype.some.call(document.querySelectorAll("video"), function (video) { return !video.paused && !video.muted; });
    // De cookiemelding (js/cookies.js) staat nog open: eerst die keuze
    var cookies = Boolean(document.querySelector("[data-cookie-melding]:not([hidden])"));
    return root.classList.contains("intro-speelt") || Boolean(document.querySelector("dialog[open], .site-header.is-open")) || typt || kijkt || cookies;
  };

  var toon = function () {
    if (bezig()) {
      setTimeout(toon, 3000);
      return;
    }
    vorigeFocus = document.activeElement;
    dialoog.showModal();
    bewaar({ getoond: Date.now() });
  };
  setTimeout(toon, Math.max(0, WACHT - (Date.now() - start)));

  var sluit = function () { if (dialoog.open) dialoog.close(); };
  dialoog.querySelectorAll("[data-pop-up-sluit]").forEach(function (knop) { knop.addEventListener("click", sluit); });
  // Klik naast de pop-up (op de donkere achtergrond): sluiten
  dialoog.addEventListener("click", function (event) { if (event.target === dialoog) sluit(); });
  dialoog.addEventListener("close", function () {
    if (vorigeFocus && typeof vorigeFocus.focus === "function" && document.contains(vorigeFocus)) vorigeFocus.focus();
  });

  // Aangemeld: bedankt, en de pop-up komt niet meer terug
  if (form && klaar) {
    form.addEventListener("formulier-verstuurd", function () {
      bewaar({ getoond: Date.now(), aangemeld: true });
      setTimeout(function () {
        form.hidden = true;
        klaar.hidden = false;
        klaar.focus();
      }, 0);
    });
  }
})();
