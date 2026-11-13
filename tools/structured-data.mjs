#!/usr/bin/env node
/**
 * Zet de schema.org-gegevens (JSON-LD) in de <head> van de pagina's.
 *
 * - Homepage: de BSO zelf (ChildCare) en de website (WebSite, voor de sitenaam in Google).
 * - Locatie (Sportpark Middenmeer): de BSO zelf en het kruimelpad.
 * - Contact: de BSO zelf, het kruimelpad en alle veelgestelde vragen (FAQPage).
 * - Overige pagina's: het kruimelpad.
 *
 * - Engelse pagina's (en/): een WebPage in het Engels die bij dezelfde BSO hoort, het kruimelpad,
 *   en op Contact de Engelse veelgestelde vragen.
 * - Elke pagina krijgt hreflang-links naar zijn Nederlandse en Engelse versie (tools/site/talen.mjs).
 *
 * Kruimelpad en vragen komen uit de zichtbare HTML, zodat ze altijd overeenkomen met wat
 * bezoekers zien. Pas je een vraag, een pagina of de gegevens hieronder aan? Draai dan:
 *
 *   npm run seo            gegevens bijwerken
 *   npm run seo -- --check alleen controleren (npm test doet dit ook)
 */
import { readFileSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { ENGELS, NEDERLANDS, adres } from "./site/talen.mjs";

export const SITE = "https://bsosporty.nl";
const ROOT = fileURLToPath(new URL("..", import.meta.url));

// Gegevens van de BSO. Houd deze gelijk aan wat op de website staat.
// TODO: sameAs met de links naar social media en het Google Bedrijfsprofiel toevoegen zodra die er zijn.
// TODO: openingstijden (openingHoursSpecification) toevoegen zodra de tijden van het middagprogramma bekend zijn;
// nu staan alleen de telefonische bereikbaarheid en het adres erin (op schooldagen open na school tot 18.30 uur,
// in vakanties en op studiedagen 08.00–18.30 uur).
export const BSO = {
  "@type": "ChildCare",
  "@id": `${SITE}/#sporty`,
  name: "Sporty",
  alternateName: "BSO Sporty",
  legalName: "Sporty",
  description: "Sportieve buitenschoolse opvang (BSO) op Sportpark Middenmeer in Amsterdam-Oost, voor alle kinderen van 4 t/m 12 jaar. Een club vol plezier, vriendschap en groei.",
  url: `${SITE}/`,
  image: `${SITE}/assets/img/og-image.jpg`,
  logo: `${SITE}/assets/img/sporty-logo-512.png`,
  telephone: "+31612345678",
  email: "info@bsosporty.nl",
  address: { "@type": "PostalAddress", streetAddress: "Voorlandpad 17", postalCode: "1098 TZ", addressLocality: "Amsterdam", addressCountry: "NL" },
  areaServed: [
    { "@type": "Place", name: "Watergraafsmeer, Amsterdam" },
    { "@type": "Place", name: "Amsterdam-Oost" },
    { "@type": "City", name: "Amsterdam" },
  ],
  keywords: "BSO, buitenschoolse opvang, sportieve BSO, sport-BSO, Watergraafsmeer, Amsterdam-Oost",
  contactPoint: {
    "@type": "ContactPoint",
    contactType: "customer service",
    telephone: "+31612345678",
    email: "info@bsosporty.nl",
    availableLanguage: ["nl", "en"],
    hoursAvailable: { "@type": "OpeningHoursSpecification", dayOfWeek: ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"], opens: "09:00", closes: "13:30" },
  },
};

const WEBSITE = {
  "@type": "WebSite",
  "@id": `${SITE}/#website`,
  url: `${SITE}/`,
  name: "Sporty",
  alternateName: "BSO Sporty",
  inLanguage: "nl-NL",
  publisher: { "@id": BSO["@id"] },
};

export const PAGINAS = [
  "index.html",
  "alles-over-sporty.html",
  "abonnementen.html",
  "vakantie-en-studiedagen.html",
  "community.html",
  "pauzebegeleiding.html",
  "een-middag-bij-sporty.html",
  "locaties/sportpark-middenmeer.html",
  "team.html",
  "word-sporty-coach.html",
  "contact.html",
  "proefdag.html",
  "aanmelden.html",
  "blijf-op-de-hoogte.html",
  "klachtenregeling.html",
  "privacy.html",
  "voorwaarden.html",
  // De Engelse versies (en/index.html eerst, zoals altijd)
  ...Object.values(ENGELS),
];

const ENTITEITEN = { amp: "&", lt: "<", gt: ">", quot: '"', apos: "'", nbsp: " ", eacute: "é", euml: "ë", iuml: "ï", ndash: "–", mdash: "—", hellip: "…" };

/** Zichtbare tekst uit een stukje HTML: zonder tags en commentaar, entiteiten omgezet, witruimte samengevoegd. */
export function tekst(html) {
  return html
    .replace(/<!--[\s\S]*?-->/g, "")
    .replace(/<\/(p|li|h[1-6])>/g, " ")
    .replace(/<[^>]+>/g, "")
    .replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, (heel, naam) => {
      if (naam[0] === "#") return String.fromCodePoint(naam[1].toLowerCase() === "x" ? parseInt(naam.slice(2), 16) : Number(naam.slice(1)));
      return ENTITEITEN[naam.toLowerCase()] ?? heel;
    })
    .replace(/\s+/g, " ")
    .trim();
}

export function canonical(html) {
  return html.match(/<link rel="canonical" href="([^"]+)">/)?.[1] ?? null;
}

/** Het zichtbare kruimelpad als BreadcrumbList, of null als de pagina er geen heeft. */
export function kruimelpad(html) {
  const nav = html.match(/<nav class="breadcrumb"[^>]*>([\s\S]*?)<\/nav>/);
  if (!nav) return null;
  const hier = canonical(html);
  const items = [...nav[1].matchAll(/<li([^>]*)>([\s\S]*?)<\/li>/g)].map(([, attributen, inhoud], i) => {
    const href = inhoud.match(/<a href="([^"]+)"/)?.[1];
    let url = href ? new URL(href, hier).href : hier;
    url = url.replace(/\/index\.html$/, "/");
    return { "@type": "ListItem", position: i + 1, name: tekst(inhoud), item: url };
  });
  return { "@type": "BreadcrumbList", itemListElement: items };
}

/** Alle uitklapbare vragen (details/summary) als FAQPage. */
export function vragen(html) {
  const lijst = [...html.matchAll(/<details>\s*<summary>([\s\S]*?)<\/summary>[\s\S]*?<div class="faq__answer">([\s\S]*?)<\/div>\s*<\/details>/g)];
  if (!lijst.length) return null;
  return {
    "@type": "FAQPage",
    "@id": `${canonical(html)}#vragen`,
    mainEntity: lijst.map(([, vraag, antwoord]) => ({
      "@type": "Question",
      name: tekst(vraag),
      acceptedAnswer: { "@type": "Answer", text: tekst(antwoord) },
    })),
  };
}

/** De JSON-LD die bij een pagina hoort. */
export function gegevensVoor(pagina, html) {
  const graaf = [];
  if (pagina === "index.html") graaf.push(BSO, WEBSITE);
  if (pagina === "contact.html" || pagina === "locaties/sportpark-middenmeer.html") graaf.push(BSO);
  if (NEDERLANDS[pagina]) {
    // Engelse pagina: verwijst naar dezelfde BSO en website, met de Engelse titel
    const url = adres(SITE, pagina);
    graaf.push({
      "@type": "WebPage",
      "@id": `${url}#webpage`,
      url,
      name: tekst(html.match(/<title>([\s\S]*?)<\/title>/)[1]),
      inLanguage: "en",
      isPartOf: { "@id": WEBSITE["@id"] },
      about: { "@id": BSO["@id"] },
    });
  }
  const pad = kruimelpad(html);
  if (pad) graaf.push(pad);
  if (pagina === "contact.html" || pagina === "en/contact.html") graaf.push(vragen(html));
  return { "@context": "https://schema.org", "@graph": graaf };
}

/** De hreflang-links van een pagina en zijn tegenhanger: Nederlands, Engels, en Nederlands als standaard. */
export function hreflangVoor(pagina) {
  const nl = NEDERLANDS[pagina] ?? pagina;
  const en = ENGELS[nl];
  if (!en) return "";
  return [["nl", nl], ["en", en], ["x-default", nl]].map(([taal, p]) => `<link rel="alternate" hreflang="${taal}" href="${adres(SITE, p)}">`).join("\n") + "\n";
}

const SCRIPT = /<script type="application\/ld\+json">[\s\S]*?<\/script>\n?/g;

/** De pagina met bijgewerkte hreflang-links (direct na de canonical) en JSON-LD (één blok, vlak voor </head>). */
export function bijgewerkt(pagina, html) {
  let uit = html.replace(/<link rel="alternate" hreflang="[^"]+" href="[^"]+">\n/g, "");
  uit = uit.replace(/(<link rel="canonical" href="[^"]+">\n)/, (canon) => canon + hreflangVoor(pagina));
  const json = JSON.stringify(gegevensVoor(pagina, uit)).replace(/</g, "\\u003c");
  return uit.replace(SCRIPT, "").replace("</head>", `<script type="application/ld+json">${json}</script>\n</head>`);
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const alleenControleren = process.argv.includes("--check");
  let verouderd = 0;
  for (const pagina of PAGINAS) {
    const pad = ROOT + pagina;
    const html = readFileSync(pad, "utf8");
    const nieuw = bijgewerkt(pagina, html);
    if (nieuw === html) continue;
    verouderd++;
    if (alleenControleren) console.error(`Verouderd: ${pagina}`);
    else {
      writeFileSync(pad, nieuw);
      console.log(`Bijgewerkt: ${pagina}`);
    }
  }
  if (alleenControleren && verouderd) {
    console.error("Draai npm run seo om de schema.org-gegevens bij te werken.");
    process.exit(1);
  }
  if (!verouderd) console.log("Alle schema.org-gegevens zijn up-to-date.");
}
