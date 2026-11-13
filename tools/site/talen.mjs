/**
 * Nederlandse en Engelse versie van elke pagina (november 2026: de hele website in het Engels).
 * Gebruikt door kop-en-voet (taalknop en menu), structured-data (hreflang en sitemap) en de tests.
 * Een nieuwe pagina? Zet hem hier, maak de Engelse versie en draai npm run kop-en-voet en npm run seo.
 */
export const ENGELS = {
  "index.html": "en/index.html",
  "alles-over-sporty.html": "en/about-sporty.html",
  "abonnement-en-kosten.html": "en/membership-and-costs.html",
  "vakantie-en-studiedagen.html": "en/holidays-and-study-days.html",
  "welkom-bij-de-club.html": "en/welcome-to-the-club.html",
  "pauzebegeleiding.html": "en/lunch-break-supervision.html",
  "een-middag-bij-sporty.html": "en/an-afternoon-at-sporty.html",
  "locaties/sportpark-middenmeer.html": "en/locations/sportpark-middenmeer.html",
  "team.html": "en/team.html",
  "word-sporty-coach.html": "en/become-a-sporty-coach.html",
  "contact.html": "en/contact.html",
  "proefdag.html": "en/trial-day.html",
  "aanmelden.html": "en/join-the-club.html",
  "blijf-op-de-hoogte.html": "en/stay-in-the-loop.html",
  "klachtenregeling.html": "en/complaints-procedure.html",
  "privacy.html": "en/privacy.html",
  "voorwaarden.html": "en/terms-and-conditions.html",
};

/** Omgekeerd: van de Engelse naar de Nederlandse pagina. */
export const NEDERLANDS = Object.fromEntries(Object.entries(ENGELS).map(([nl, en]) => [en, nl]));

/** Het adres zoals in canonical, hreflang en sitemap: index.html wordt de map zelf. */
export const adres = (site, pagina) => `${site}/${pagina.replace(/(^|\/)index\.html$/, "$1")}`;
