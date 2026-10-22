#!/usr/bin/env node
/**
 * Maakt de iconen (favicon en app-iconen), het logo voor Google (sporty-logo-512.png) en de intro op de homepage.
 *
 * De logo's zelf zijn de officiële logo's van Sporty (aangeleverd op 22 oktober 2026), gevectoriseerd uit
 * design/logo/: assets/img/sporty-woordmerk(-wit, -zwart).svg en assets/img/sporty-logo(-wit, -zwart).svg.
 * TODO: vervang die door de vectorbestanden van de ontwerper zodra die er zijn.
 *
 * De intro: gemaakt van de vormen van het officiële ronde logo. De drie kinderen rennen vanaf de rand van het
 * scherm naar het midden, de ring sluit zich om ze heen, "BSO", het woordmerk en het lachje komen erbij, en
 * aan het eind staat precies het logo.
 *
 * Gebruik: npm run logo
 * Schrijft favicon.svg, apple-touch-icon.png, icon-192.png, icon-512.png, icon-maskable-512.png en
 * sporty-logo-512.png naar assets/img/, favicon.ico naar de hoofdmap, en de intro in index.html en en/index.html.
 * Letters: Pacifico (de S van het icoon), SIL Open Font License (assets/fonts/OFL.txt).
 */
import opentype from "opentype.js";
import sharp from "sharp";
import { readFileSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";

const ROOT = fileURLToPath(new URL("../../", import.meta.url));
const IMG = ROOT + "assets/img/";
const laad = (pad) => { const b = readFileSync(new URL(pad, import.meta.url)); return opentype.parse(b.buffer.slice(b.byteOffset, b.byteOffset + b.byteLength)); };
const pacifico = laad("./fonts/pacifico-latin-400.woff");


// De intro gebruikt de vormen van het officiële ronde logo zelf (één pad met losse delen, zie maakIntro)
const LOGO_SVG = readFileSync(IMG + "sporty-logo.svg", "utf8");

/**
 * Verdeelt het pad van het officiële logo in de delen die in de intro apart bewegen. Het pad bestaat uit losse
 * contouren ("M … Z"); die worden op hun plek in het logo ingedeeld:
 *  - ring: de open cirkel, de enige contour die bovenaan het logo begint;
 *  - lach: het lachje onder het woordmerk;
 *  - woord: "Sporty" (alles vanaf de onderkant van de ring);
 *  - bso: de letters op het shirt van het middelste kind;
 *  - kinderen: de drie kinderen met hun armen om elkaar heen (één vorm, met gaten).
 * Komen er vectorbestanden van de ontwerper (TODO), controleer dan of deze indeling nog klopt.
 */
export function logoDelen(svg = LOGO_SVG) {
  const viewBox = svg.match(/viewBox="([^"]+)"/)[1].split(/\s+/).map(Number);
  const d = svg.match(/ d="([^"]+)"/)[1];
  const delen = { ring: [], kinderen: [], bso: [], woord: [], lach: [] };
  for (const sub of d.split(/(?=M )/).map((x) => x.trim()).filter(Boolean)) {
    const getallen = sub.match(/-?\d+(\.\d+)?/g).map(Number);
    const xs = getallen.filter((_, i) => i % 2 === 0), ys = getallen.filter((_, i) => i % 2 === 1);
    const x0 = Math.min(...xs), y0 = Math.min(...ys), y1 = Math.max(...ys);
    const soort = y0 < viewBox[1] + 100 && x0 < viewBox[0] + 100 ? "ring"
      : y0 >= 1850 && y1 - y0 < 250 ? "lach"
      : y0 >= 1280 ? "woord"
      : y0 >= 1100 ? "bso"
      : "kinderen";
    delen[soort].push(sub);
  }
  return { viewBox, delen: Object.fromEntries(Object.entries(delen).map(([k, v]) => [k, v.join(" ")])) };
}

// Knippaden tussen de kinderen (in de maten van het logo), langs de witte ruimte tussen haren en shirts.
// Links het krullenkind, in het midden het meisje met het BSO-shirt, rechts het kind met de pet.
const NAAD_LINKS = [[1205, 0], [1205, 740], [1130, 805], [1125, 960], [1185, 1120], [1185, 2300]];
const NAAD_RECHTS = [[1758, 0], [1758, 940], [1790, 1120], [1840, 1400], [1840, 2300]];

/**
 * De intro op de homepage, gemaakt van het officiële ronde logo (assets/img/sporty-logo.svg): de drie kinderen
 * rennen vanaf de rand van het scherm naar het midden en slaan de armen om elkaar, de ring sluit zich om ze heen,
 * "BSO" ploft op het shirt, het woordmerk zwaait omhoog en het lachje verschijnt. Aan het eind liggen alle delen
 * precies op hun plek en is het beeld gelijk aan het logo.
 * Elke laag is een eigen HTML-element ter grootte van het hele logo, zodat de browser de beweging met de grafische
 * kaart doet. De drie kinderen zijn dezelfde vorm met elk een eigen knippad; de knippaden overlappen een paar
 * eenheden, zodat er geen naadje te zien is. De beweging staat in css/style.css (sectie Intro).
 */
export function maakIntro() {
  const { viewBox: [vx, vy, vb, vh], delen } = logoDelen();
  const vak = `${vx} ${vy} ${vb} ${vh}`;
  const pct = (waarde, totaal) => `${+((waarde / totaal) * 100).toFixed(2)}%`;
  const punten = (lijn, dx) => lijn.map(([x, y]) => `${x + dx},${y}`);
  const L = vx - 50, R = vx + vb + 50, B = 2300;
  const knip = {
    links: [`${L},0`, ...punten(NAAD_LINKS, 3), `${L},${B}`],
    midden: [...punten(NAAD_LINKS, -3), ...punten(NAAD_RECHTS, 3).reverse()],
    rechts: [...punten(NAAD_RECHTS, -3), `${R},${B}`, `${R},0`],
  };
  // Draaipunt van elk kind: onder het shirt (daar "landen" ze na het rennen)
  const voet = { links: [880, 1420], midden: [1500, 1480], rechts: [2110, 1420] };
  const svg = (inhoud) => `<svg viewBox="${vak}" focusable="false">${inhoud}</svg>`;
  const laag = (naam, inhoud, [ox, oy] = [vx + vb / 2, vy + vh / 2], extra = "") =>
    `<div class="intro__laag intro__laag--${naam}${extra}" style="--o:${pct(ox - vx, vb)} ${pct(oy - vy, vh)}">${svg(inhoud)}</div>`;
  const vorm = (d) => `<path class="intro__vorm" fill-rule="evenodd" d="${d}"/>`;
  const kind = (wie, i) => laag(wie, `<clipPath id="intro-knip-${wie}"><polygon points="${knip[wie].join(" ")}"/></clipPath>`
    + (i === 0 ? `<defs><path id="intro-kinderen" fill-rule="evenodd" d="${delen.kinderen}"/></defs>` : "")
    + `<use class="intro__vorm" href="#intro-kinderen" clip-path="url(#intro-knip-${wie})"/>`,
  voet[wie], ` intro__kind intro__kind--${wie}`);
  const lagen = [
    laag("ring", vorm(delen.ring), [1498.5, 1038.5]),
    ...["links", "rechts", "midden"].map(kind),
    laag("bso", vorm(delen.bso), [1500, 1215]),
    laag("woord", vorm(delen.woord), [1500, 2000]),
    laag("lach", vorm(delen.lach), [1213, 1990]),
  ];
  return `<div class="intro__logo">\n${lagen.join("\n")}\n</div>`;
}

export const INTRO_PAGINAS = ["index.html", "en/index.html"];
const INTRO = /(<!-- intro: gemaakt met npm run logo -->\n)[\s\S]*?(<!-- \/intro -->)/;

/** De pagina met de huidige intro tussen de markeringen. */
export function metIntro(html) {
  if (!INTRO.test(html)) throw new Error("Markeringen voor de intro ontbreken");
  return html.replace(INTRO, (_, begin, eind) => `${begin}<div class="intro" hidden aria-hidden="true">\n${maakIntro()}\n</div>\n${eind}`);
}

// Favicon en app-iconen: oranje vlak met een witte script-S
export function maakIcoon({ kleur = "#FC6200", letter = "#ffffff", vorm = "rond", marge = 0 } = {}) {
  const grootte = 44 * (1 - marge);
  const p = pacifico.getPath("S", 0, 0, grootte); const bb = p.getBoundingBox();
  const x = 32 - (bb.x1 + bb.x2) / 2, y = 32 - (bb.y1 + bb.y2) / 2 + 1;
  const d = pacifico.getPath("S", x, y, grootte).toPathData(2);
  const vlak = vorm === "rond" ? `<circle cx="32" cy="32" r="32" fill="${kleur}"/>` : `<rect width="64" height="64" fill="${kleur}"/>`;
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">${vlak}<path fill="${letter}" d="${d}"/></svg>`;
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  // De logo's zelf (sporty-logo*.svg en sporty-woordmerk*.svg) zijn de officiële logo's van de klant,
  // gevectoriseerd uit design/logo/. Dit script maakt alleen de iconen, het logo voor Google en de intro.
  writeFileSync(IMG + "favicon.svg", maakIcoon());

  // De intro op de homepages (tussen de markeringen, zie ook css/style.css sectie Intro)
  for (const pagina of INTRO_PAGINAS) {
    const html = readFileSync(ROOT + pagina, "utf8");
    const nieuw = metIntro(html);
    if (nieuw === html) continue;
    writeFileSync(ROOT + pagina, nieuw);
    console.log(`Intro bijgewerkt in ${pagina}`);
  }

  const png = (svg, breedte, achtergrond) => {
    let beeld = sharp(Buffer.from(svg), { density: 600 }).resize(breedte);
    if (achtergrond) beeld = beeld.flatten({ background: achtergrond });
    return beeld.png({ compressionLevel: 9 }).toBuffer();
  };
  // voor schema.org (Google): het officiële ronde logo op wit
  writeFileSync(IMG + "sporty-logo-512.png", await sharp(IMG + "sporty-logo.svg", { density: 40 }).resize(512, 512, { fit: "contain", background: "#ffffff" }).flatten({ background: "#ffffff" }).png({ compressionLevel: 9 }).toBuffer());
  writeFileSync(IMG + "apple-touch-icon.png", await png(maakIcoon({ vorm: "vierkant" }), 180));
  writeFileSync(IMG + "icon-192.png", await png(maakIcoon({ vorm: "vierkant" }), 192));
  writeFileSync(IMG + "icon-512.png", await png(maakIcoon({ vorm: "vierkant" }), 512));
  writeFileSync(IMG + "icon-maskable-512.png", await png(maakIcoon({ vorm: "vierkant", marge: 0.3 }), 512));

  // favicon.ico met 16, 32 en 48 pixels (PNG in een ICO-bestand)
  const maten = [16, 32, 48];
  const beelden = await Promise.all(maten.map((m) => png(maakIcoon(), m)));
  const kop = Buffer.alloc(6);
  kop.writeUInt16LE(1, 2);
  kop.writeUInt16LE(maten.length, 4);
  let offset = 6 + 16 * maten.length;
  const regels = maten.map((m, i) => {
    const r = Buffer.alloc(16);
    r.writeUInt8(m, 0); r.writeUInt8(m, 1); r.writeUInt16LE(1, 4); r.writeUInt16LE(32, 6);
    r.writeUInt32LE(beelden[i].length, 8); r.writeUInt32LE(offset, 12);
    offset += beelden[i].length;
    return r;
  });
  writeFileSync(ROOT + "favicon.ico", Buffer.concat([kop, ...regels, ...beelden]));
  console.log("Logo en iconen gemaakt in assets/img/ en favicon.ico");
}
