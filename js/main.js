/* Sporty · kleine verbeteringen bovenop de HTML. De site werkt ook zonder JavaScript. */
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

    var wide = window.matchMedia("(min-width: 80em)");
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

  /* ---------- Formulieren (contact en aanmelden) ---------- */
  var todayIso = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

  document.querySelectorAll("form[data-form]").forEach(function (form) {
    form.setAttribute("novalidate", "");
    var status = form.querySelector("[data-form-status]");
    var submit = form.querySelector('[type="submit"]');
    var submitText = submit ? submit.innerHTML : "";
    var mailto = form.getAttribute("data-mailto") || "info@vckbso.nl";

    // Een geboortedatum kan niet in de toekomst liggen
    form.querySelectorAll("[data-max-today]").forEach(function (el) { el.max = todayIso; });

    var isField = function (el) {
      return el && el.name && el.type !== "hidden" && el.type !== "submit" && el.type !== "button" && !el.closest(".hp");
    };
    var getFields = function () {
      return Array.prototype.filter.call(form.elements, isField);
    };

    var showStatus = function (type, text) {
      status.className = "form-status form-status--" + type;
      status.textContent = text;
    };

    // Foutmeldingen staan als data-msg-* op het veld zelf
    var validate = function (field) {
      var v = field.validity;
      var msg = function (key, fallback) { return field.getAttribute("data-msg-" + key) || fallback; };
      var text = "";
      if (v.valueMissing) text = msg("required", "Dit veld is verplicht.");
      else if (v.badInput) text = msg("type", "Controleer dit veld nog even.");
      else if (v.typeMismatch) text = msg("type", "Controleer dit veld nog even.");
      else if (v.patternMismatch) text = msg("pattern", "Controleer dit veld nog even.");
      else if (v.tooShort) text = msg("short", "Dit is wat kort.");
      else if (v.rangeOverflow || v.rangeUnderflow) text = msg("range", "Controleer dit veld nog even.");
      var error = document.getElementById(field.id + "-fout");
      field.setAttribute("aria-invalid", text ? "true" : "false");
      if (error) {
        error.querySelector("[data-error-text]").textContent = text;
        error.hidden = !text;
      }
      return !text;
    };

    form.addEventListener("focusout", function (event) {
      var field = event.target;
      if (!isField(field)) return;
      var filled = field.type !== "checkbox" && field.value.trim() !== "";
      if (filled || field.getAttribute("aria-invalid") === "true") validate(field);
    });
    ["input", "change"].forEach(function (type) {
      form.addEventListener(type, function (event) {
        if (isField(event.target) && event.target.getAttribute("aria-invalid") === "true") validate(event.target);
      });
    });

    /* Meerdere kinderen aanmelden: een blok herhalen */
    form.querySelectorAll("[data-repeat]").forEach(function (wrap) {
      var list = wrap.querySelector("[data-repeat-list]");
      var add = wrap.querySelector("[data-repeat-add]");
      var note = wrap.querySelector("[data-repeat-note]");
      var max = parseInt(wrap.getAttribute("data-repeat-max") || "4", 10);
      var template = list.querySelector("[data-repeat-item]").cloneNode(true);

      var renumber = function () {
        var items = list.querySelectorAll("[data-repeat-item]");
        items.forEach(function (item, i) {
          var n = String(i + 1);
          item.querySelector("[data-repeat-number]").textContent = n;
          item.querySelectorAll("[id], [for], [name], [aria-describedby]").forEach(function (el) {
            ["id", "for", "name", "aria-describedby"].forEach(function (attr) {
              var value = el.getAttribute(attr);
              if (value) el.setAttribute(attr, value.replace(/kind\d+/g, "kind" + n));
            });
          });
          var remove = item.querySelector("[data-repeat-remove]");
          if (remove) remove.hidden = items.length === 1;
        });
        var full = items.length >= max;
        add.hidden = full;
        if (note) note.hidden = !full;
      };

      add.addEventListener("click", function () {
        var item = template.cloneNode(true);
        list.appendChild(item);
        renumber();
        var first = item.querySelector("input, select, textarea");
        if (first) first.focus();
      });

      list.addEventListener("click", function (event) {
        var remove = event.target.closest("[data-repeat-remove]");
        if (!remove) return;
        remove.closest("[data-repeat-item]").remove();
        renumber();
        add.focus();
      });

      form.addEventListener("reset", function () {
        var items = list.querySelectorAll("[data-repeat-item]");
        for (var i = 1; i < items.length; i++) items[i].remove();
        renumber();
      });

      renumber();
    });

    /* Leesbare samenvatting voor de e-mail */
    var labelOf = function (field) {
      if (field.hasAttribute("data-summary-label")) return field.getAttribute("data-summary-label");
      var group = field.closest("[data-choice-group]");
      var label = group ? group.querySelector("legend") : form.querySelector('label[for="' + field.id + '"]');
      var text = (label ? label.textContent : field.name).replace(/\s*\*|\s*\([^)]*\)/g, "").trim();
      var item = field.closest("[data-repeat-item]");
      if (item) text = item.querySelector("legend").textContent.trim() + ", " + text.charAt(0).toLowerCase() + text.slice(1);
      return text;
    };

    var summary = function () {
      var order = [];
      var values = {};
      getFields().forEach(function (field) {
        if ((field.type === "checkbox" || field.type === "radio") && !field.checked) return;
        var value = field.value.trim();
        if (!value) return;
        if (field.type === "date") value = value.split("-").reverse().join("-");
        if (field.type === "checkbox" && !field.closest("[data-choice-group]")) value = "ja";
        var label = labelOf(field);
        if (!(label in values)) {
          order.push(label);
          values[label] = value;
        } else {
          values[label] += ", " + value;
        }
      });
      return order.map(function (label) {
        var sep = /[?:]$/.test(label) ? " " : ": ";
        return values[label].indexOf("\n") !== -1 ? "\n" + label + sep.trim() + "\n" + values[label] : label + sep + values[label];
      }).join("\n");
    };

    form.addEventListener("submit", function (event) {
      event.preventDefault();

      var invalid = getFields().filter(function (field) { return !validate(field); });
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

      // Zolang er nog geen formulierendienst is ingesteld, openen we het e-mailprogramma.
      if (!endpoint || endpoint.indexOf("TODO") !== -1) {
        var subject = data.get("onderwerp") || form.getAttribute("data-subject") || "Bericht via de website";
        var mailtoUrl = "mailto:" + mailto + "?subject=" + encodeURIComponent(subject) + "&body=" + encodeURIComponent(summary());
        var mailtoText = form.getAttribute("data-mailto-success") || "Je e-mailprogramma wordt geopend met je bericht erin. Verstuur het daar, dan komt het bij ons binnen!";
        window.location.href = mailtoUrl;
        showStatus("success", mailtoText);
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
          getFields().forEach(function (field) { field.removeAttribute("aria-invalid"); });
          showStatus("success", form.getAttribute("data-success") || "Joepie, je bericht is verstuurd! We reageren zo snel mogelijk.");
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
  });
})();
