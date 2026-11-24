#!/usr/bin/env node
/**
 * Zet de schema.org-gegevens (JSON-LD) in de <head> van de pagina's.
 *
 * - Homepage: de BSO zelf (ChildCare) en de website (WebSite, voor de sitenaam in Google).
 * - Locatie (Sportpark Middenmeer): de BSO zelf en het kruimelpad.
 * - Contact: de BSO zelf, het kruimelpad en alle veelgestelde vragen (FAQPage).
 * - Overige pagina's: het kruimelpad.
 *
 * - Elke pagina: een WebPage in de taal van de pagina die bij dezelfde BSO en website hoort.
 * - Engelse pagina's (en/): dezelfde opbouw, met de BSO in het Engels op de homepage, Locaties en Contact,
 *   en op Contact de Engelse veelgestelde vragen.
 * - Elke pagina krijgt hreflang-links naar zijn Nederlandse en Engelse versie (tools/site/talen.mjs).
 *
 * - sitemap.xml: alle pagina's met hun taalversies. De datum (lastmod) verandert alleen als de inhoud van een
 *   pagina echt verandert (titel, description of <main>); de vingerafdrukken staan in tools/site/lastmod.json.
 *   Een nieuwe kop, voet of iconenset telt dus niet als wijziging.
 *
 * Kruimelpad en vragen komen uit de zichtbare HTML, zodat ze altijd overeenkomen met wat
 * bezoekers zien. Pas je een vraag, een pagina of de gegevens hieronder aan? Draai dan:
 *
 *   npm run seo            gegevens en sitemap bijwerken
 *   npm run seo -- --check alleen controleren (npm test doet dit ook)
 */
import { createHash } from "node:crypto";
import { readFileSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { ENGELS, NEDERLANDS, adres } from "./site/talen.mjs";

export const SITE = "https://bsosporty.nl";
const ROOT = fileURLToPath(new URL("..", import.meta.url));

// Gegevens van de BSO. Houd deze gelijk aan wat op de website staat.
// TODO: het Google Bedrijfsprofiel aan sameAs toevoegen zodra het er is.
// Openingstijden op schooldagen: vanaf het ophalen van school (14.00 uur, zie Een middag bij Sporty) tot 18.30 uur;
// tot 12 juli 2027 op dinsdag en donderdag, daarna maandag t/m vrijdag (beheer/inc/openingsdagen.php).
// Vakantie- en studiedagen (08.00–18.30 uur) vallen op wisselende datums en staan er daarom niet in.
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
  telephone: "+31618266706",
  email: "info@bsosporty.nl",
  address: { "@type": "PostalAddress", streetAddress: "Voorlandpad 17", postalCode: "1098 TZ", addressLocality: "Amsterdam", addressCountry: "NL" },
  // Dezelfde plek als de kaart op de locatiepagina (tools/kaart.mjs)
  geo: { "@type": "GeoCoordinates", latitude: 52.3481547, longitude: 4.9535974 },
  hasMap: "https://www.google.com/maps/search/?api=1&query=Sporty%2C+Voorlandpad+17%2C+1098+TZ+Amsterdam",
  priceRange: "€ 9,75 – € 12 per uur",
  // Feedback 2.0 (november 2026): LRK, KvK, social media en de brancheorganisatie
  identifier: [
    { "@type": "PropertyValue", propertyID: "LRK", name: "Landelijk Register Kinderopvang", value: "236315389" },
    { "@type": "PropertyValue", propertyID: "KvK", name: "Kamer van Koophandel", value: "42064267" },
  ],
  sameAs: ["https://www.instagram.com/bso.sporty/", "https://www.tiktok.com/@bso.sporty", "https://www.facebook.com/profile.php?id=61596047927666"],
  memberOf: { "@type": "Organization", name: "Brancheorganisatie Kinderopvang" },
  openingHoursSpecification: [
    { "@type": "OpeningHoursSpecification", dayOfWeek: ["Tuesday", "Thursday"], opens: "14:00", closes: "18:30", validFrom: "2027-01-04", validThrough: "2027-07-11" },
    { "@type": "OpeningHoursSpecification", dayOfWeek: ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"], opens: "14:00", closes: "18:30", validFrom: "2027-07-12" },
  ],
  areaServed: [
    { "@type": "Place", name: "Watergraafsmeer, Amsterdam" },
    { "@type": "Place", name: "Amsterdam-Oost" },
    { "@type": "City", name: "Amsterdam" },
  ],
  keywords: "BSO, buitenschoolse opvang, sportieve BSO, sport-BSO, Watergraafsmeer, Amsterdam-Oost",
  contactPoint: {
    "@type": "ContactPoint",
    contactType: "customer service",
    telephone: "+31618266706",
    email: "info@bsosporty.nl",
    availableLanguage: ["nl", "en"],
    hoursAvailable: { "@type": "OpeningHoursSpecification", dayOfWeek: ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"], opens: "09:00", closes: "13:30" },
  },
};

// De BSO voor de Engelse pagina's: dezelfde gegevens, met een Engelse beschrijving en deelafbeelding
const BSO_EN = {
  ...BSO,
  description: "Active after-school care (BSO) at Sportpark Middenmeer in Amsterdam East, for all children aged 4 to 12. A club full of fun, friendship and growth.",
  image: `${SITE}/assets/img/og-image-en.jpg`,
  priceRange: "€9.75–€12 per hour",
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
  "abonnement-en-kosten.html",
  "vakantie-en-studiedagen.html",
  "welkom-bij-de-club.html",
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
  const nl = NEDERLANDS[pagina] ?? pagina;
  const engels = Boolean(NEDERLANDS[pagina]);
  // De BSO zelf op de homepage, de locatiepagina en Contact (in de taal van de pagina)
  if (["index.html", "contact.html", "locaties/sportpark-middenmeer.html"].includes(nl)) graaf.push(engels ? BSO_EN : BSO);
  if (pagina === "index.html") graaf.push(WEBSITE);
  // Elke pagina: een WebPage in de taal van de pagina, bij dezelfde BSO en website, met de titel
  const url = adres(SITE, pagina);
  graaf.push({
    "@type": "WebPage",
    "@id": `${url}#webpage`,
    url,
    name: tekst(html.match(/<title>([\s\S]*?)<\/title>/)[1]),
    inLanguage: engels ? "en" : "nl",
    isPartOf: { "@id": WEBSITE["@id"] },
    about: { "@id": BSO["@id"] },
  });
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

/* ---------- Sitemap ---------- */

// Prioriteit per pagina (Google kijkt er niet naar, andere zoekmachines soms wel); de Engelse iets lager
const PRIORITEIT = {
  "index.html": 1.0, "alles-over-sporty.html": 0.9, "abonnement-en-kosten.html": 0.9, "een-middag-bij-sporty.html": 0.9, "proefdag.html": 0.9,
  "locaties/sportpark-middenmeer.html": 0.8, "contact.html": 0.8, "aanmelden.html": 0.8, "team.html": 0.7,
  "vakantie-en-studiedagen.html": 0.6, "welkom-bij-de-club.html": 0.6, "word-sporty-coach.html": 0.6,
  "pauzebegeleiding.html": 0.5, "blijf-op-de-hoogte.html": 0.5, "klachtenregeling.html": 0.3, "privacy.html": 0.3, "voorwaarden.html": 0.3,
};
const LASTMOD = ROOT + "tools/site/lastmod.json";

/** Vingerafdruk van wat een zoekmachine van de pagina indexeert: titel, description en <main>. */
export function inhoudHash(html) {
  const deel = (re) => html.match(re)?.[0] ?? "";
  const inhoud = [/<title>[\s\S]*?<\/title>/, /<meta name="description"[^>]*>/, /<main[\s\S]*<\/main>/].map(deel).join("\n");
  return createHash("sha256").update(inhoud).digest("hex").slice(0, 16);
}

export const leesLastmod = () => JSON.parse(readFileSync(LASTMOD, "utf8"));

/** De datums bijgewerkt: een pagina waarvan de inhoud is veranderd (of die nieuw is) krijgt de datum van vandaag. */
export function lastmodBijgewerkt(oud, htmlPerPagina, vandaag) {
  return Object.fromEntries(PAGINAS.map((p) => {
    const hash = inhoudHash(htmlPerPagina[p]);
    return [p, oud[p]?.hash === hash ? oud[p] : { hash, datum: vandaag }];
  }));
}

export function sitemapXml(lastmod) {
  const urls = PAGINAS.map((pagina) => {
    const nl = NEDERLANDS[pagina] ?? pagina;
    const prioriteit = Math.max(0.1, (PRIORITEIT[nl] ?? 0.5) - (NEDERLANDS[pagina] ? 0.1 : 0)).toFixed(1);
    const talen = [["nl", nl], ["en", ENGELS[nl]], ["x-default", nl]].map(([taal, p]) => `    <xhtml:link rel="alternate" hreflang="${taal}" href="${adres(SITE, p)}"/>`).join("\n");
    return `  <url>\n    <loc>${adres(SITE, pagina)}</loc>\n${talen}\n    <lastmod>${lastmod[pagina].datum}</lastmod>\n    <priority>${prioriteit}</priority>\n  </url>`;
  });
  return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">\n${urls.join("\n")}\n</urlset>\n`;
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const alleenControleren = process.argv.includes("--check");
  let verouderd = 0;
  const htmlPerPagina = {};
  for (const pagina of PAGINAS) {
    const pad = ROOT + pagina;
    const html = readFileSync(pad, "utf8");
    const nieuw = bijgewerkt(pagina, html);
    htmlPerPagina[pagina] = nieuw;
    if (nieuw === html) continue;
    verouderd++;
    if (alleenControleren) console.error(`Verouderd: ${pagina}`);
    else {
      writeFileSync(pad, nieuw);
      console.log(`Bijgewerkt: ${pagina}`);
    }
  }
  // Sitemap met de datum van de laatste echte wijziging per pagina
  const vandaag = new Date().toLocaleDateString("sv-SE", { timeZone: "Europe/Amsterdam" });
  const oudeDatums = leesLastmod();
  const datums = lastmodBijgewerkt(oudeDatums, htmlPerPagina, vandaag);
  const xml = sitemapXml(datums);
  const datumsTekst = JSON.stringify(datums, null, 2) + "\n";
  for (const [pad, inhoud] of [[LASTMOD, datumsTekst], [ROOT + "sitemap.xml", xml]]) {
    if (readFileSync(pad, "utf8") === inhoud) continue;
    verouderd++;
    if (alleenControleren) console.error(`Verouderd: ${pad.slice(ROOT.length)}`);
    else {
      writeFileSync(pad, inhoud);
      console.log(`Bijgewerkt: ${pad.slice(ROOT.length)}`);
    }
  }
  if (alleenControleren && verouderd) {
    console.error("Draai npm run seo om de schema.org-gegevens en de sitemap bij te werken.");
    process.exit(1);
  }
  if (!verouderd) console.log("Alle schema.org-gegevens zijn up-to-date.");
}
