#!/usr/bin/env node
/**
 * Maakt de deelafbeelding (assets/img/og-image.jpg, 1200×630) die WhatsApp, Facebook,
 * LinkedIn en andere apps tonen als iemand een link naar de website deelt.
 * Kleuren komen uit design/tokens.css, de letters, het logo en Henny uit assets/.
 *
 * Gebruik: npm run og-image   (vereist Playwright met Chromium: npx playwright install chromium)
 */
import { readFileSync } from "node:fs";
import { createRequire } from "node:module";
import { fileURLToPath } from "node:url";

const ROOT = fileURLToPath(new URL("..", import.meta.url));
const HENNY = "assets/img/henny/henny-zit-640.webp";
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

// Zoals de hero op de homepage: oranje vlak met het witte ronde logo, "Let's Play" in handschrift,
// een zwarte kop, Henny met een tekstwolkje en een zwarte band met de kernwaarden
const html = `<!doctype html>
<html lang="nl"><head><meta charset="utf-8">
<style>
${font("assets/fonts/poppins-latin-700.woff2", "Poppins", 700)}
${font("assets/fonts/poppins-latin-800.woff2", "Poppins", 800)}
${font("assets/fonts/caveat-brush-hoofdletters.woff2", "Caveat Brush", 400)}
${tokensZonderFonts}
* { box-sizing: border-box; margin: 0; }
html, body { width: 1200px; height: 630px; overflow: hidden; }
body { position: relative; background: var(--color-primary); color: var(--color-text); font-family: var(--font-heading); }
body::before { content: ""; position: absolute; right: -150px; top: -190px; width: 620px; height: 620px; border: 4px solid rgb(255 255 255 / 0.35); border-radius: 50%; }
.tekst { position: absolute; left: 64px; top: 44px; width: 660px; }
.logo { width: 132px; height: auto; display: block; }
.slogan { margin-top: 22px; color: #fff; font-family: "Caveat Brush"; font-size: 64px; line-height: 1; text-transform: uppercase; rotate: -4deg; transform-origin: left center; }
h1 { margin-top: 14px; font-size: 56px; line-height: 1.02; font-weight: 800; letter-spacing: -0.03em; }
h1 span { color: #fff; }
.henny { position: absolute; right: 70px; bottom: 92px; width: 330px; }
.wolk { position: absolute; right: 330px; top: 74px; padding: 14px 20px; border: 3px solid var(--color-dark); border-radius: 20px; background: #fff; box-shadow: 5px 5px 0 var(--color-dark); font-size: 28px; font-weight: 700; }
.band { position: absolute; left: 0; right: 0; bottom: 0; height: 92px; display: flex; align-items: center; justify-content: center; gap: 64px; background: var(--color-dark); color: var(--color-primary); font-family: var(--font-heading); font-size: 30px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; }
.band b { font-weight: 800; display: flex; align-items: center; gap: 14px; }
.band i { display: block; width: 14px; height: 14px; border-radius: 50%; background: #fff; }
</style></head>
<body>
  <div class="tekst">
    <img class="logo" src="${svg64("assets/img/sporty-logo-wit.svg")}" alt="Sporty">
    <p class="slogan">Let's Play</p>
    <h1>Sport, plezier en ontwikkeling <span>na schooltijd.</span></h1>
  </div>
  <img class="henny" src="data:image/webp;base64,${base64(HENNY)}" alt="Henny, de hond van Sporty">
  <p class="wolk">Woef!</p>
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
