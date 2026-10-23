/*
 * Sporty-quiz op de homepage: past jouw kind bij Sporty?
 *
 * De vragen, antwoorden en uitslagen staan in index.html en en/index.html (sectie #quiz), dus ook alle
 * teksten per taal; het script zelf bevat geen tekst. Elk antwoord hoort
 * bij een type (value); het type dat het vaakst gekozen is, is de uitslag. De laatste vraag
 * (leeftijd) bepaalt welke conclusie eronder staat. Er wordt niets opgeslagen of verstuurd.
 *
 * Werkt in de browser en in Node (voor de tests, via globalThis.SportyQuiz).
 */
(function (root) {
  "use strict";

  var TYPES = ["stuiterbal", "teamcaptain", "spelbedenker", "chillkampioen"];
  var LEEFTIJDEN = ["jong", "ok", "oud"];

  /** Het type dat het vaakst gekozen is; bij een gelijke stand het type dat als laatste gekozen is. */
  function uitslag(antwoorden) {
    var telling = {};
    var beste = null;
    antwoorden.forEach(function (type) {
      if (TYPES.indexOf(type) > -1) telling[type] = (telling[type] || 0) + 1;
    });
    antwoorden.forEach(function (type) {
      if (telling[type] && (beste === null || telling[type] >= telling[beste])) beste = type;
    });
    return beste || TYPES[0];
  }

  root.SportyQuiz = { TYPES: TYPES, LEEFTIJDEN: LEEFTIJDEN, uitslag: uitslag };

  var doc = root.document;
  var quiz = doc && doc.querySelector("[data-quiz]");
  if (!quiz) return;

  var kaart = quiz.parentNode;
  var vragen = [].slice.call(quiz.querySelectorAll("[data-vraag]"));
  var verderKnop = quiz.querySelector("[data-quiz-verder]");
  var verder = verderKnop.querySelector("span");
  var verderTekst = verder.textContent;
  var laatsteTekst = verderKnop.getAttribute("data-laatste") || verderTekst;
  var terug = quiz.querySelector("[data-quiz-terug]");
  var fout = quiz.querySelector("[data-quiz-fout]");
  var reactie = kaart.querySelector("[data-quiz-reactie]");
  var henny = kaart.querySelector(".quiz-kaart__henny");
  var uitslagVak = kaart.querySelector("[data-quiz-uitslag]");
  var opnieuw = kaart.querySelector("[data-quiz-opnieuw]");
  var startReactie = reactie.textContent;
  var huidig = 0;
  var reduceMotion = root.matchMedia && root.matchMedia("(prefers-reduced-motion: reduce)").matches;

  var gekozen = function (vraag) {
    return vraag.querySelector("input:checked");
  };

  // Focus naar de nieuwe vraag of de uitslag; staat de kaart (met Henny) niet goed in beeld, dan scrollt hij mee
  var focusOp = function (el) {
    el.focus({ preventScroll: true });
    var boven = kaart.getBoundingClientRect().top;
    if (boven < 160 || boven > root.innerHeight * 0.6) {
      kaart.scrollIntoView({ block: "start", behavior: reduceMotion ? "auto" : "smooth" });
    }
  };

  var toon = function (index, metFocus) {
    huidig = index;
    vragen.forEach(function (vraag, i) {
      vraag.hidden = i !== index;
    });
    terug.hidden = index === 0;
    verder.textContent = index === vragen.length - 1 ? laatsteTekst : verderTekst;
    fout.hidden = true;
    kaart.style.setProperty("--voortgang", String(index / vragen.length));
    if (metFocus) focusOp(vragen[index]);
  };

  var toonUitslag = function () {
    var type = uitslag(vragen.map(function (vraag) { return gekozen(vraag).value; }));
    var leeftijd = gekozen(vragen[vragen.length - 1]).value;
    var blok = null;
    [].forEach.call(uitslagVak.querySelectorAll("[data-type]"), function (el) {
      el.hidden = el.getAttribute("data-type") !== type;
      if (!el.hidden) blok = el;
    });
    [].forEach.call(uitslagVak.querySelectorAll("[data-leeftijd]"), function (el) {
      el.hidden = el.getAttribute("data-leeftijd") !== leeftijd;
    });
    kaart.style.setProperty("--voortgang", "1");
    kaart.classList.add("is-klaar");
    quiz.hidden = true;
    uitslagVak.hidden = false;
    reactie.textContent = blok.getAttribute("data-reactie");
    focusOp(blok.querySelector("h3"));
  };

  // Henny reageert op elk antwoord
  quiz.addEventListener("change", function (event) {
    var tekst = event.target.getAttribute("data-reactie");
    if (tekst) reactie.textContent = tekst;
    fout.hidden = true;
    henny.classList.remove("is-blij");
    void henny.offsetWidth; // zo begint het sprongetje elke keer opnieuw
    henny.classList.add("is-blij");
  });

  quiz.addEventListener("submit", function (event) {
    event.preventDefault();
    if (!gekozen(vragen[huidig])) {
      fout.hidden = false;
      return;
    }
    if (huidig < vragen.length - 1) toon(huidig + 1, true);
    else toonUitslag();
  });

  terug.addEventListener("click", function () {
    toon(huidig - 1, true);
  });

  opnieuw.addEventListener("click", function () {
    quiz.reset();
    uitslagVak.hidden = true;
    quiz.hidden = false;
    kaart.classList.remove("is-klaar");
    reactie.textContent = startReactie;
    toon(0, true);
  });

  toon(0, false);
})(typeof globalThis !== "undefined" ? globalThis : this);
