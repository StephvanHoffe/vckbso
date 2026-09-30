/* BSO VCK · kleine verbeteringen bovenop de HTML. De site werkt ook zonder JavaScript. */
(function () {
  "use strict";

  var root = document.documentElement;
  root.classList.add("js");
  window.vckReady = true;

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---------- Mobiel menu ---------- */
  var header = document.querySelector(".site-header");
  var toggle = document.querySelector(".nav-toggle");
  var nav = document.getElementById("site-nav");

  if (header && toggle && nav) {
    var toggleLabel = toggle.querySelector(".visually-hidden");

    var setOpen = function (open) {
      header.classList.toggle("is-open", open);
      toggle.setAttribute("aria-expanded", String(open));
      if (toggleLabel) toggleLabel.textContent = open ? "Menu sluiten" : "Menu openen";
    };

    toggle.addEventListener("click", function () {
      setOpen(toggle.getAttribute("aria-expanded") !== "true");
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && header.classList.contains("is-open")) {
        setOpen(false);
        toggle.focus();
      }
    });

    document.addEventListener("click", function (event) {
      if (header.classList.contains("is-open") && !header.contains(event.target)) setOpen(false);
    });

    nav.addEventListener("click", function (event) {
      if (event.target.closest("a")) setOpen(false);
    });

    var wide = window.matchMedia("(min-width: 75em)");
    var onWide = function () {
      if (wide.matches) setOpen(false);
    };
    if (wide.addEventListener) wide.addEventListener("change", onWide);
  }

  /* ---------- Schaduw onder de header na scrollen ---------- */
  if (header) {
    var ticking = false;
    var updateHeader = function () {
      header.classList.toggle("is-scrolled", window.scrollY > 8);
      ticking = false;
    };
    updateHeader();
    window.addEventListener("scroll", function () {
      if (!ticking) {
        window.requestAnimationFrame(updateHeader);
        ticking = true;
      }
    }, { passive: true });
  }

  /* ---------- Elementen in beeld laten komen ---------- */
  var reveals = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window && !reduceMotion) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        }
      });
    }, { rootMargin: "0px 0px -8% 0px", threshold: 0.12 });
    reveals.forEach(function (el) { observer.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add("is-visible"); });
  }

  /* ---------- Vandaag markeren in de openingstijden ---------- */
  var today = String(new Date().getDay());
  document.querySelectorAll('[data-day="' + today + '"]').forEach(function (row) {
    row.classList.add("is-today");
    var cell = row.querySelector("th");
    if (cell && !cell.querySelector(".today-label")) {
      var label = document.createElement("span");
      label.className = "today-label";
      label.textContent = row.closest("[lang='en']") ? "today" : "vandaag";
      cell.appendChild(label);
    }
  });

  /* ---------- Google Maps pas laden na een klik (privacy) ---------- */
  document.querySelectorAll("[data-map]").forEach(function (map) {
    var button = map.querySelector("[data-map-load]");
    if (!button) return;
    button.addEventListener("click", function () {
      var iframe = document.createElement("iframe");
      iframe.src = map.getAttribute("data-map");
      iframe.title = map.getAttribute("data-map-title") || "Kaart";
      iframe.loading = "lazy";
      iframe.referrerPolicy = "no-referrer-when-downgrade";
      iframe.allowFullscreen = true;
      map.appendChild(iframe);
      map.classList.add("is-loaded");
      iframe.setAttribute("tabindex", "-1");
      iframe.focus();
    });
  });

  /* ---------- Contactformulier ---------- */
  var form = document.querySelector("[data-contact-form]");
  if (form) {
    form.setAttribute("novalidate", "");
    var status = form.querySelector("[data-form-status]");
    var submit = form.querySelector('[type="submit"]');
    var submitText = submit ? submit.innerHTML : "";
    var fields = Array.prototype.filter.call(form.elements, function (el) {
      return el.name && el.type !== "hidden" && el.type !== "submit" && !el.closest(".hp");
    });

    var messages = {
      naam: { valueMissing: "Vul je naam in." },
      email: {
        valueMissing: "Vul je e-mailadres in.",
        typeMismatch: "Dit lijkt geen geldig e-mailadres. Controleer het even, bijvoorbeeld naam@voorbeeld.nl."
      },
      telefoon: { patternMismatch: "Vul een geldig telefoonnummer in, bijvoorbeeld 06 12 34 56 78." },
      bericht: {
        valueMissing: "Schrijf je bericht.",
        tooShort: "Je bericht is wat kort. Vertel ons iets meer, dan kunnen we je goed helpen."
      }
    };

    var showStatus = function (type, text) {
      status.className = "form-status form-status--" + type;
      status.textContent = text;
    };

    var validate = function (field) {
      var v = field.validity;
      var own = messages[field.name] || {};
      var text = "";
      if (v.valueMissing) text = own.valueMissing || "Dit veld is verplicht.";
      else if (v.typeMismatch) text = own.typeMismatch || "Controleer dit veld nog even.";
      else if (v.patternMismatch) text = own.patternMismatch || "Controleer dit veld nog even.";
      else if (v.tooShort) text = own.tooShort || "Dit is wat kort.";
      var error = document.getElementById(field.id + "-fout");
      field.setAttribute("aria-invalid", text ? "true" : "false");
      if (error) {
        error.querySelector("[data-error-text]").textContent = text;
        error.hidden = !text;
      }
      return !text;
    };

    fields.forEach(function (field) {
      field.addEventListener("blur", function () {
        if (field.value.trim() !== "" || field.getAttribute("aria-invalid") === "true") validate(field);
      });
      field.addEventListener("input", function () {
        if (field.getAttribute("aria-invalid") === "true") validate(field);
      });
    });

    form.addEventListener("submit", function (event) {
      event.preventDefault();

      var invalid = fields.filter(function (field) { return !validate(field); });
      if (invalid.length) {
        showStatus("error", invalid.length === 1
          ? "Er is nog 1 veld niet goed ingevuld. Kijk even bij de melding in rood."
          : "Er zijn nog " + invalid.length + " velden niet goed ingevuld. Kijk even bij de meldingen in rood.");
        invalid[0].focus();
        return;
      }

      var honeypot = form.querySelector(".hp input");
      if (honeypot && honeypot.value) return;

      var data = new FormData(form);
      var endpoint = form.getAttribute("action") || "";
      var mailto = form.getAttribute("data-mailto") || "info@vckbso.nl";

      // Zolang er nog geen formulierendienst is ingesteld, openen we het e-mailprogramma.
      if (!endpoint || endpoint.indexOf("TODO") !== -1) {
        var onderwerp = data.get("onderwerp") || "Bericht via de website";
        var body = "Naam: " + data.get("naam") + "\nE-mail: " + data.get("email") +
          (data.get("telefoon") ? "\nTelefoon: " + data.get("telefoon") : "") +
          "\n\n" + data.get("bericht");
        window.location.href = "mailto:" + mailto + "?subject=" + encodeURIComponent(onderwerp) + "&body=" + encodeURIComponent(body);
        showStatus("success", "Je e-mailprogramma wordt geopend met je bericht erin. Verstuur het daar, dan komt het bij ons binnen!");
        return;
      }

      if (submit) {
        submit.disabled = true;
        submit.textContent = "Bezig met versturen…";
      }
      form.setAttribute("aria-busy", "true");

      fetch(endpoint, { method: "POST", body: data, headers: { Accept: "application/json" } })
        .then(function (response) {
          if (!response.ok) throw new Error("Versturen mislukt");
          form.reset();
          fields.forEach(function (field) { field.removeAttribute("aria-invalid"); });
          showStatus("success", "Joepie, je bericht is verstuurd! We reageren zo snel mogelijk.");
          status.setAttribute("tabindex", "-1");
          status.focus();
        })
        .catch(function () {
          showStatus("error", "Oeps, het versturen is niet gelukt. Probeer het nog eens of mail ons direct via " + mailto + ".");
        })
        .then(function () {
          if (submit) {
            submit.disabled = false;
            submit.innerHTML = submitText;
          }
          form.removeAttribute("aria-busy");
        });
    });
  }
})();
