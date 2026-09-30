#!/usr/bin/env node
/**
 * Maakt de deelafbeelding (assets/img/og-image.jpg, 1200×630) die WhatsApp, Facebook,
 * LinkedIn en andere apps tonen als iemand een link naar de website deelt.
 * Kleuren komen uit design/tokens.css, de letter en de foto uit assets/.
 *
 * Gebruik: npm run og-image   (vereist Playwright met Chromium: npx playwright install chromium)
 */
import { readFileSync } from "node:fs";
import { createRequire } from "node:module";
import { fileURLToPath } from "node:url";

const ROOT = fileURLToPath(new URL("..", import.meta.url));
const FOTO = "assets/fotos/hero-1200.webp";
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

const html = `<!doctype html>
<html lang="nl"><head><meta charset="utf-8">
<style>
@font-face { font-family: "Manrope"; font-weight: 200 800; src: url(data:font/woff2;base64,${base64("assets/fonts/manrope-latin.woff2")}) format("woff2"); }
${tokens}
* { box-sizing: border-box; margin: 0; }
html, body { width: 1200px; height: 630px; overflow: hidden; }
body { display: grid; grid-template-columns: 620px 1fr; background: var(--color-surface-alt); color: var(--color-text); font-family: var(--font-heading); }
.tekst { display: flex; flex-direction: column; justify-content: center; padding: 0 40px 0 72px; }
.logo { display: flex; align-items: center; gap: 14px; font-size: 38px; font-weight: 800; letter-spacing: -0.03em; }
.logo svg { width: 54px; height: 54px; }
.logo span { color: var(--color-primary-strong); }
h1 { margin-top: 44px; font-size: 64px; line-height: 1.12; font-weight: 800; letter-spacing: -0.03em; }
.hl { background: linear-gradient(to top, var(--hl) 0 0.3em, transparent 0.3em); padding-inline: 0.04em; }
p { margin-top: 30px; white-space: nowrap; font-size: 27px; font-weight: 700; color: var(--color-primary-strong); }
.foto { background: url(data:image/webp;base64,${base64(FOTO)}) center / cover; border-radius: var(--radius-l) 0 0 var(--radius-l); }
</style></head>
<body>
  <div class="tekst">
    <div class="logo"><svg viewBox="0 0 40 40"><circle cx="18" cy="22" r="16" fill="var(--color-primary)"/><path d="M10.5 23.5a7.5 7.5 0 0 0 15 0" fill="none" stroke-width="3.4" stroke-linecap="round" stroke="var(--color-surface)"/><circle cx="33" cy="8" r="5.5" fill="var(--color-sun)"/></svg><div>Sport<span>y</span></div></div>
    <h1>De <span class="hl" style="--hl: var(--color-secondary)">leukste</span>,<br><span class="hl" style="--hl: var(--color-sun)">gezelligste</span> én<br><span class="hl" style="--hl: var(--color-mint)">sportiefste</span> BSO</h1>
    <p>Buitenschoolse opvang in Amsterdam</p>
  </div>
  <div class="foto" role="img" aria-label="Kinderen voetballen samen op een grasveld"></div>
</body></html>`;

const { chromium } = await laadPlaywright();
const browser = await chromium.launch(process.env.PLAYWRIGHT_BROWSERS_PATH ? {} : undefined);
const page = await browser.newPage({ viewport: { width: 1200, height: 630 } });
await page.setContent(html);
await page.evaluate(() => document.fonts.ready);
await page.screenshot({ path: ROOT + UIT, type: "jpeg", quality: 86 });
await browser.close();
console.log(`Gemaakt: ${UIT}`);
