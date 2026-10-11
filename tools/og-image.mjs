#!/usr/bin/env node
/**
 * Maakt de deelafbeelding (assets/img/og-image.jpg, 1200×630) die WhatsApp, Facebook,
 * LinkedIn en andere apps tonen als iemand een link naar de website deelt.
 * Kleuren komen uit design/tokens.css, de letters, het logo en de foto uit assets/.
 *
 * Gebruik: npm run og-image   (vereist Playwright met Chromium: npx playwright install chromium)
 */
import { readFileSync } from "node:fs";
import { createRequire } from "node:module";
import { fileURLToPath } from "node:url";

const ROOT = fileURLToPath(new URL("..", import.meta.url));
const FOTO = "assets/fotos/kring-1600.webp";
const UIT = "assets/img/og-image.jpg";

async function laadPlaywright() {
  try {
    return await import("playwright");
  } catch {
    // Ook een globaal geïnstalleerde Playwright is goed
    const require = createRequire(import.meta.url);
    for (const map of [process.env.PLAYWRIGHT_PAD, ...(process.env.NODE_PATH || "").split(":")].filter(Boolean)) {
      try {
        return require(require.resolve("playwright", { paths: [map] }));
      } catch {}
    }
  }
  console.error("Playwright is niet gevonden. Installeer het met: npm install --no-save playwright && npx playwright install chromium");
  process.exit(1);
}

const base64 = (pad) => readFileSync(ROOT + pad).toString("base64");
const tokens = readFileSync(ROOT + "design/tokens.css", "utf8");

const svg64 = (pad) => `data:image/svg+xml;base64,${base64(pad)}`;
const font = (pad, familie, gewicht) => `@font-face { font-family: "${familie}"; font-weight: ${gewicht}; src: url(data:font/woff2;base64,${base64(pad)}) format("woff2"); }`;
const tokensZonderFonts = tokens.replace(/@font-face\s*{[^}]*}/g, "");

// Zoals de hero op de homepage: foto van kinderen met de armen om elkaar, donkere overloop, wit embleem,
// kop met een oranje slot en een oranje band met de kernwaarden
const html = `<!doctype html>
<html lang="nl"><head><meta charset="utf-8">
<style>
${font("assets/fonts/poppins-latin-700.woff2", "Poppins", 700)}
${font("assets/fonts/poppins-latin-800.woff2", "Poppins", 800)}
${tokensZonderFonts}
* { box-sizing: border-box; margin: 0; }
html, body { width: 1200px; height: 630px; overflow: hidden; }
body { position: relative; background: var(--color-dark); color: #fff; font-family: var(--font-heading); }
.foto { position: absolute; inset: 0 0 92px; overflow: hidden; isolation: isolate; }
.foto::before { content: ""; position: absolute; inset: 0; background: url(data:image/webp;base64,${base64(FOTO)}) 100% 30% / auto 118%; }
.foto::after { content: ""; position: absolute; inset: 0; z-index: 1; background: linear-gradient(90deg, rgb(34 34 34 / 0.95) 0, rgb(34 34 34 / 0.88) 44%, rgb(34 34 34 / 0) 74%); }
.tekst { position: absolute; left: 64px; top: 48px; width: 640px; }
.logo { width: 150px; height: auto; display: block; }
h1 { margin-top: 26px; font-size: 58px; line-height: 1.02; font-weight: 800; letter-spacing: -0.03em; }
h1 span { display: block; color: var(--color-primary); }
.band { position: absolute; left: 0; right: 0; bottom: 0; height: 92px; display: flex; align-items: center; justify-content: center; gap: 64px; background: var(--color-primary); font-family: var(--font-heading); font-size: 30px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; }
.band b { font-weight: 800; display: flex; align-items: center; gap: 14px; }
.band i { display: block; width: 14px; height: 14px; border-radius: 50%; background: #fff; }
</style></head>
<body>
  <div class="foto" role="img" aria-label="Een groep kinderen staat met de armen om elkaar heen op een zonnig grasveld"></div>
  <div class="tekst">
    <img class="logo" src="${svg64("assets/img/sporty-logo-wit.svg")}" alt="Sporty">
    <h1>Sport, plezier en ontwikkeling <span>na schooltijd.</span></h1>
  </div>
  <div class="band"><b><i></i>Sport</b><b><i></i>Plezier</b><b><i></i>Ontwikkeling</b></div>
</body></html>`;

const { chromium } = await laadPlaywright();
const browser = await chromium.launch(process.env.PLAYWRIGHT_BROWSERS_PATH ? {} : undefined);
const page = await browser.newPage({ viewport: { width: 1200, height: 630 } });
await page.setContent(html);
await page.evaluate(() => document.fonts.ready);
await page.screenshot({ path: ROOT + UIT, type: "jpeg", quality: 86 });
await browser.close();
console.log(`Gemaakt: ${UIT}`);
