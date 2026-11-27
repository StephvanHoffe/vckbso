/*
 * Cookiemelding en Google Tag Manager (27 november 2026).
 * De klant wil Google Tag Manager op de website (container GTM-K9XSMB74, de code van Google: zie laadTagManager).
 * Tag Manager kan cookies van Google plaatsen, bijvoorbeeld voor Google Analytics of advertenties. Daar is in
 * Nederland eerst toestemming voor nodig (Telecommunicatiewet, artikel 11.7a). Daarom:
 * - Tag Manager laadt pas als de bezoeker op "Akkoord" klikt. "Liever niet" is net zo makkelijk (zelfde knop).
 * - Google Consent Mode: standaard staat alles op "denied"; na akkoord gaan analytics_storage, ad_storage en
 *   ad_user_data op "granted". TODO: wil Sporty advertenties laten zien aan wie de site al bezocht (remarketing)?
 *   Zet dan ook ad_personalization op "granted" en zeg dat in de melding en in de privacyverklaring (NL en EN).
 * - De keuze staat 12 maanden in een eigen cookie (sporty_cookiekeuze, alleen "ja" of "nee"); daarna vragen we opnieuw.
 * - "Cookie-instellingen" in de footer (en in de privacyverklaring) opent de melding weer. Wie de toestemming intrekt,
 *   is de cookies van Google kwijt en krijgt de pagina opnieuw, zodat Tag Manager stopt.
 * - Alleen op bsosporty.nl: niet op het testadres bij Vercel of lokaal, zodat de cijfers kloppen. Nooit in Mijn BSO.
 * - Staat "Do Not Track" of "Global Privacy Control" aan in de browser? Dan zei de bezoeker al nee: we vragen niets.
 *   Kiest diegene zelf via Cookie-instellingen toch "Akkoord", dan geldt die keuze.
 * - Het beveiligingsbeleid (CSP in vercel.json en .htaccess) laat de diensten van Google toe die Tag Manager,
 *   Google Analytics en Google Ads gebruiken. Een tag "Aangepaste HTML" in Tag Manager blokkeert het; zie docs/seo.md.
 * De melding staat niet in de HTML maar wordt hier gemaakt: zonder JavaScript laadt Tag Manager ook niet.
 */
(function () {
  "use strict";

  var GTM_ID = "GTM-K9XSMB74";
  var KEUZE = "sporty_cookiekeuze";
  var BEWAAR_DAGEN = 365;
  var OP_DOMEIN = /^(www\.)?bsosporty\.nl$/.test(location.hostname);

  var root = document.documentElement;
  var EN = (root.getAttribute("lang") || "").slice(0, 2) === "en";
  var t = function (nl, en) { return EN ? en : nl; };

  // Google Consent Mode: alles uit tot de bezoeker ja zegt
  window.dataLayer = window.dataLayer || [];
  var gtag = window.gtag || function () { window.dataLayer.push(arguments); };
  window.gtag = gtag;
  gtag("consent", "default", { analytics_storage: "denied", ad_storage: "denied", ad_user_data: "denied", ad_personalization: "denied" });

  var leesKeuze = function () {
    var gevonden = document.cookie.match(new RegExp("(?:^|; )" + KEUZE + "=(ja|nee)(?:;|$)"));
    return gevonden ? gevonden[1] : "";
  };
  var bewaarKeuze = function (keuze) {
    document.cookie = KEUZE + "=" + keuze + "; max-age=" + BEWAAR_DAGEN * 24 * 60 * 60 + "; path=/; SameSite=Lax" + (location.protocol === "https:" ? "; Secure" : "") + (OP_DOMEIN ? "; domain=bsosporty.nl" : "");
  };

  var geladen = false;
  var laadTagManager = function () {
    if (geladen) return;
    geladen = true;
    gtag("consent", "update", { analytics_storage: "granted", ad_storage: "granted", ad_user_data: "granted" });
    // De code die Google voor deze container geeft (Tag Manager → Tag Manager installeren)
    gtag("js", new Date());
    var script = document.createElement("script");
    script.async = true;
    script.src = "https://www.googletagmanager.com/gtm.js?id=" + GTM_ID;
    document.head.appendChild(script);
  };

  // Toestemming ingetrokken: de cookies van Google weg (op dit adres en op het hele domein)
  var wisGoogleCookies = function () {
    var domeinen = ["", "; domain=" + location.hostname, "; domain=bsosporty.nl", "; domain=.bsosporty.nl"];
    document.cookie.split("; ").forEach(function (paar) {
      var naam = paar.split("=")[0];
      if (!/^(_ga|_gid|_gat|_gcl_|_gac_|_gtm|FPID|FPLC)/.test(naam)) return;
      domeinen.forEach(function (domein) { document.cookie = naam + "=; max-age=0; path=/" + domein; });
    });
  };

  var zegtAlNee = navigator.doNotTrack === "1" || window.doNotTrack === "1" || navigator.globalPrivacyControl === true;
  var keuze = leesKeuze();
  if (OP_DOMEIN && keuze === "ja") laadTagManager();

  // ---------- De melding ----------
  var privacy = document.querySelector('.site-footer a[href$="privacy.html"]');
  var melding = null;
  var terugNaar = null;

  var maakMelding = function () {
    melding = document.createElement("section");
    melding.className = "cookie-melding";
    melding.setAttribute("data-cookie-melding", "");
    melding.setAttribute("aria-labelledby", "cookie-melding-titel");
    melding.hidden = true;
    melding.innerHTML =
      '<h2 class="cookie-melding__titel" id="cookie-melding-titel" tabindex="-1">' + t("Koekje <span class=\"hl\">erbij?</span>", "Fancy a <span class=\"hl\">cookie?</span>") + "</h2>" +
      "<p>" + t(
        "Niet om op te eten. Mogen we cookies van Google gebruiken? Daarmee zien we hoe de website wordt gebruikt en hoe goed onze advertenties werken. Zonder werkt de site net zo goed.",
        "Not the edible kind. May we use cookies from Google? They show us how people use the website and how well our ads work. The site works just as well without them."
      ) + (privacy ? ' <a href="' + privacy.getAttribute("href") + '#cookies">' + t("Meer over cookies", "More about cookies") + "</a>" : "") + "</p>" +
      '<p class="cookie-melding__nu" data-cookie-nu hidden></p>' +
      '<div class="cookie-melding__knoppen">' +
      '<button class="btn btn--small btn--dark" type="button" data-cookie-keuze="ja">' + t("Akkoord", "Accept") + "</button>" +
      '<button class="btn btn--small btn--dark" type="button" data-cookie-keuze="nee">' + t("Liever niet", "No thanks") + "</button>" +
      "</div>";
    // Vooraan in de pagina (na de link "Naar de inhoud"), zodat je hem met het toetsenbord meteen bereikt
    var skip = document.querySelector(".skip-link");
    if (skip) skip.insertAdjacentElement("afterend", melding); else document.body.insertBefore(melding, document.body.firstChild);

    melding.querySelectorAll("[data-cookie-keuze]").forEach(function (knop) {
      knop.addEventListener("click", function () { kies(knop.getAttribute("data-cookie-keuze")); });
    });
    melding.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && leesKeuze()) sluit();
    });
  };

  var toon = function (focus) {
    if (!melding) maakMelding();
    var nu = melding.querySelector("[data-cookie-nu]");
    var huidig = leesKeuze();
    nu.hidden = !huidig;
    nu.textContent = huidig === "ja"
      ? t("Je hebt eerder ja gezegd. Liever niet meer? Dan halen we de cookies van Google weg.", "You said yes before. Changed your mind? Then we’ll remove the cookies from Google.")
      : t("Je hebt eerder nee gezegd. Dat is helemaal prima.", "You said no before. That’s absolutely fine.");
    melding.hidden = false;
    if (focus) melding.querySelector(".cookie-melding__titel").focus();
  };

  var sluit = function () {
    if (!melding) return;
    var hadFocus = melding.contains(document.activeElement);
    melding.hidden = true;
    if (hadFocus && terugNaar && document.contains(terugNaar)) terugNaar.focus();
    terugNaar = null;
  };

  var kies = function (nieuw) {
    var oud = leesKeuze();
    bewaarKeuze(nieuw);
    sluit();
    if (nieuw === "ja") {
      laadTagManager();
    } else if (oud === "ja" || geladen) {
      gtag("consent", "update", { analytics_storage: "denied", ad_storage: "denied", ad_user_data: "denied", ad_personalization: "denied" });
      wisGoogleCookies();
      // Tag Manager draait al op deze pagina: opnieuw laden, dan stopt hij echt
      location.reload();
    }
  };

  if (!OP_DOMEIN) return;

  // "Cookie-instellingen" in de footer en in de privacyverklaring: de melding weer openen
  document.querySelectorAll("[data-cookie-instellingen]").forEach(function (link) {
    link.addEventListener("click", function (event) {
      event.preventDefault();
      terugNaar = link;
      toon(true);
    });
  });

  // Nog geen keuze (of langer dan 12 maanden geleden): vragen. Zegt de browser al nee, dan vragen we niets.
  if (!keuze && !zegtAlNee) toon(false);
})();
