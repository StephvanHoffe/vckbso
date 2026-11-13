#!/usr/bin/env node
/**
 * Maakt de deelafbeeldingen (1200×630) die WhatsApp, Facebook, LinkedIn en andere apps tonen als
 * iemand een link naar de website deelt: assets/img/og-image.jpg (Nederlands) en og-image-en.jpg (Engels).
 * Kleuren komen uit design/tokens.css, de letters, het logo en Henny uit assets/.
 *
 * Gebruik: npm run og-image   (vereist Playwright met Chromium: npx playwright install chromium)
 */
import { readFileSync } from "node:fs";
import { createRequire } from "node:module";
import { fileURLToPath } from "node:url";

const ROOT = fileURLToPath(new URL("..", import.meta.url));
const HENNY = "assets/img/henny/henny-zit-640.webp";
// Per taal: bestand, kop (het laatste deel wit), label met de plek, Henny's wolkje en de kernwaarden
const VERSIES = [
  { uit: "assets/img/og-image.jpg", lang: "nl", kop: ["Sporty is meer dan een sport-BSO,", "we zijn een club!"], label: "Sportieve BSO · Watergraafsmeer, Amsterdam-Oost", wolk: "Woef!", waarden: ["Plezier", "Vriendschap", "Groei"] },
  { uit: "assets/img/og-image-en.jpg", lang: "en", kop: ["More than after-school care,", "we’re a club!"], label: "After-school care · Watergraafsmeer, Amsterdam East", wolk: "Woof!", waarden: ["Fun", "Friendship", "Growth"] },
];
const escape = (s) => s.replace(/&/g, "&amp;").replace(/</g, "&lt;");

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
const html = (v) => `<!doctype html>
<html lang="${v.lang}"><head><meta charset="utf-8">
<style>
${font("assets/fonts/poppins-latin-700.woff2", "Poppins", 700)}
${font("assets/fonts/poppins-latin-800.woff2", "Poppins", 800)}
${font("assets/fonts/caveat-brush-hoofdletters.woff2", "Caveat Brush", 400)}
${tokensZonderFonts}
* { box-sizing: border-box; margin: 0; }
html, body { width: 1200px; height: 630px; overflow: hidden; }
body { position: relative; background: var(--color-primary); color: var(--color-text); font-family: var(--font-heading); }
body::before { content: ""; position: absolute; right: -150px; top: -190px; width: 620px; height: 620px; border: 4px solid rgb(255 255 255 / 0.35); border-radius: 50%; }
.tekst { position: absolute; left: 64px; top: 40px; width: 680px; }
.logo { width: 112px; height: auto; display: block; }
.slogan { margin-top: 16px; color: #fff; font-family: "Caveat Brush"; font-size: 64px; line-height: 1; text-transform: uppercase; rotate: -4deg; transform-origin: left center; }
h1 { margin-top: 12px; font-size: 52px; line-height: 1.02; font-weight: 800; letter-spacing: -0.03em; }
h1 span { color: #fff; }
.label span { white-space: nowrap; }
.label { margin-top: 18px; font-size: 22px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; }
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
    <h1>${escape(v.kop[0])} <span>${escape(v.kop[1])}</span></h1>
    <p class="label">${v.label.split(", ").map((deel) => `<span>${escape(deel)}</span>`).join(", ")}</p>
  </div>
  <img class="henny" src="data:image/webp;base64,${base64(HENNY)}" alt="">
  <p class="wolk">${v.wolk}</p>
  <div class="band">${v.waarden.map((w) => `<b><i></i>${w}</b>`).join("")}</div>
</body></html>`;

const { chromium } = await laadPlaywright();
const browser = await chromium.launch(process.env.PLAYWRIGHT_BROWSERS_PATH ? {} : undefined);
const page = await browser.newPage({ viewport: { width: 1200, height: 630 } });
for (const v of VERSIES) {
  await page.setContent(html(v));
  await page.evaluate(() => document.fonts.ready);
  await page.screenshot({ path: ROOT + v.uit, type: "jpeg", quality: 86 });
  console.log(`Gemaakt: ${v.uit}`);
}
await browser.close();
