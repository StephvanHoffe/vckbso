/**
 * Vercel Routing Middleware voor bsosporty.nl (november 2026): de binnenkort-pagina (coming soon).
 *
 * De website staat op Vercel, Mijn BSO bij Vimexx op mijn.bsosporty.nl. Zolang de website niet live is, zien
 * bezoekers van bsosporty.nl alleen de binnenkort-pagina (binnenkort.html; Engels en/coming-soon.html), waar ze hun
 * gegevens achterlaten. Andere pagina's sturen daar tijdelijk (302) naartoe, en er is nog geen sitemap.
 *
 * Of de website live is, zegt Mijn BSO (website-status.php). De knop "Website live zetten" (Mijn BSO → Op de hoogte)
 * zet hem live en mailt iedereen die zich op de binnenkort-pagina aanmeldde. We vragen de status hooguit eens per
 * 30 seconden op. Is Mijn BSO even niet bereikbaar, dan geldt de laatst bekende status, en anders STANDAARD.
 * TODO na de livegang: zet STANDAARD op "live" (of haal dit bestand weg), dan hangt de website niet meer van
 * Mijn BSO af.
 *
 * - Alleen voor het domein; het voorbeeld op *.vercel.app blijft zoals het is.
 * - Het team ziet de echte website vooraf met het cookie sporty_voorproef (Mijn BSO → Op de hoogte → "Bekijk de
 *   echte website alvast", beheer/voorproef.php).
 * - Mijn BSO (/beheer/…) en /voorbeeld/ regelt vercel.json; de bestanden die de pagina nodig heeft (css, js, assets)
 *   slaan we over.
 * Dezelfde regels staan in .htaccess, voor als alles op één server staat. Test: tests/middleware.test.mjs.
 */
import { next, rewrite } from "@vercel/functions";

export const DOMEIN = /^(www\.)?bsosporty\.nl$/;
export const STATUS_URL = "https://mijn.bsosporty.nl/beheer/website-status.php";
export const STANDAARD = "binnenkort";
const BEWAAR_MS = 30_000;
const OPNIEUW_NA_FOUT_MS = 10_000;
const TIMEOUT_MS = 2_000;

export const config = {
  matcher: ["/((?!css/|js/|assets/|design/|beheer|voorbeeld|favicon\\.ico|site\\.webmanifest|robots\\.txt).*)"],
};

let bekend = { live: null, tot: 0 };
let haal = (...args) => fetch(...args);

/** Is de website live? Volgens Mijn BSO, met een korte buffer. `nu` is er voor de tests. */
export async function isLive(nu = Date.now()) {
  if (bekend.live !== null && nu < bekend.tot) return bekend.live;
  try {
    const antwoord = await haal(STATUS_URL, { headers: { Accept: "application/json" }, signal: AbortSignal.timeout(TIMEOUT_MS) });
    if (!antwoord.ok) throw new Error(`status ${antwoord.status}`);
    const data = await antwoord.json();
    bekend = { live: data.live === true, tot: nu + BEWAAR_MS };
  } catch {
    bekend = { live: bekend.live ?? STANDAARD === "live", tot: nu + OPNIEUW_NA_FOUT_MS };
  }
  return bekend.live;
}

/** Voor de tests: begin opnieuw, met een eigen manier om de status op te halen. */
export function voorTest(ophalen) {
  bekend = { live: null, tot: 0 };
  haal = ophalen;
}

/** Pagina's die ook tijdens de binnenkort-pagina bereikbaar zijn (de pagina zelf, de privacyverklaring, de foutpagina). */
const OOK_BINNENKORT = new Set(["/binnenkort.html", "/en/coming-soon.html", "/privacy.html", "/en/privacy.html", "/404.html"]);

export default async function middleware(request) {
  const url = new URL(request.url);
  if (!DOMEIN.test(url.hostname)) return next();
  const pad = url.pathname;
  const voorproef = /(?:^|;\s*)sporty_voorproef=1(?:;|$)/.test(request.headers.get("cookie") || "");

  if (voorproef || (await isLive())) {
    // Na de livegang stuurt de binnenkort-pagina zelf door naar de homepage
    if (pad === "/binnenkort.html") return Response.redirect(new URL("/", url), 302);
    if (pad === "/en/coming-soon.html") return Response.redirect(new URL("/en/", url), 302);
    return next();
  }

  if (pad === "/") return rewrite(new URL("/binnenkort.html", url));
  if (pad === "/en/") return rewrite(new URL("/en/coming-soon.html", url));
  if (OOK_BINNENKORT.has(pad)) return next();
  if (pad === "/sitemap.xml") {
    return new Response("Nog geen sitemap: de website is nog niet live.\n", { status: 404, headers: { "Content-Type": "text/plain; charset=utf-8" } });
  }
  return Response.redirect(new URL(pad === "/en" || pad.startsWith("/en/") ? "/en/" : "/", url), 302);
}
