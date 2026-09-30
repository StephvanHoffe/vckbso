#!/usr/bin/env node
/**
 * Zet de schema.org-gegevens (JSON-LD) in de <head> van de pagina's.
 *
 * - Homepage: de BSO zelf (ChildCare) en de website (WebSite, voor de sitenaam in Google).
 * - Contact: de BSO zelf en het kruimelpad.
 * - Veelgestelde vragen: het kruimelpad en alle vragen (FAQPage).
 * - Overige pagina's: het kruimelpad.
 *
 * Kruimelpad en vragen komen uit de zichtbare HTML, zodat ze altijd overeenkomen met wat
 * bezoekers zien. Pas je een vraag, een pagina of de gegevens hieronder aan? Draai dan:
 *
 *   npm run seo            gegevens bijwerken
 *   npm run seo -- --check alleen controleren (npm test doet dit ook)
 */
import { readFileSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";

// TODO: domein kiezen bij de nieuwe naam Sporty (nu nog bsovck.nl); pas dit aan, ook in de <head> van elke pagina, sitemap.xml en robots.txt
export const SITE = "https://bsovck.nl";
const ROOT = fileURLToPath(new URL("..", import.meta.url));

// Gegevens van de BSO. Houd deze gelijk aan wat op de website staat.
// TODO: streetAddress en postalCode toevoegen zodra het adres bekend is, en sameAs met de links naar social media en het Google Bedrijfsprofiel
export const BSO = {
  "@type": "ChildCare",
  "@id": `${SITE}/#sporty`,
  name: "Sporty",
  alternateName: "BSO Sporty",
  legalName: "Je Dag in Beeld",
  description: "Sportieve buitenschoolse opvang (BSO) in Amsterdam met oprechte aandacht voor ieder kind en volop sport en beweging.",
  url: `${SITE}/`,
  image: `${SITE}/assets/img/og-image.jpg`,
  logo: `${SITE}/assets/img/icon-512.png`,
  telephone: "+31612345678",
  email: "info@vckbso.nl",
  address: { "@type": "PostalAddress", addressLocality: "Amsterdam", addressCountry: "NL" },
  areaServed: { "@type": "City", name: "Amsterdam" },
  openingHoursSpecification: [
    { "@type": "OpeningHoursSpecification", dayOfWeek: ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"], opens: "09:00", closes: "17:00" },
  ],
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
  "over-ons.html",
  "diensten.html",
  "prijzen.html",
  "team.html",
  "veelgestelde-vragen.html",
  "contact.html",
  "aanmelden.html",
  "privacy.html",
  "voorwaarden.html",
  "en/index.html",
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
  if (pagina === "contact.html") graaf.push(BSO);
  if (pagina === "en/index.html") {
    graaf.push({
      "@type": "WebPage",
      "@id": `${SITE}/en/#webpage`,
      url: `${SITE}/en/`,
      name: tekst(html.match(/<title>([\s\S]*?)<\/title>/)[1]),
      inLanguage: "en",
      isPartOf: { "@id": WEBSITE["@id"] },
      about: { "@id": BSO["@id"] },
    });
  }
  const pad = kruimelpad(html);
  if (pad) graaf.push(pad);
  if (pagina === "veelgestelde-vragen.html") graaf.push(vragen(html));
  return { "@context": "https://schema.org", "@graph": graaf };
}

const SCRIPT = /<script type="application\/ld\+json">[\s\S]*?<\/script>\n?/g;

/** De pagina met bijgewerkte JSON-LD (één blok, vlak voor </head>). */
export function bijgewerkt(pagina, html) {
  const json = JSON.stringify(gegevensVoor(pagina, html)).replace(/</g, "\\u003c");
  const zonder = html.replace(SCRIPT, "");
  return zonder.replace("</head>", `<script type="application/ld+json">${json}</script>\n</head>`);
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
