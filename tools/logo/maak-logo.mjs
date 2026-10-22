#!/usr/bin/env node
/**
 * Maakt de iconen (favicon en app-iconen), het logo voor Google (sporty-logo-512.png) en de intro op de homepage.
 *
 * De logo's zelf zijn de officiële logo's van Sporty (aangeleverd op 22 oktober 2026), gevectoriseerd uit
 * design/logo/: assets/img/sporty-woordmerk(-wit, -zwart).svg en assets/img/sporty-logo(-wit, -zwart).svg.
 * TODO: vervang die door de vectorbestanden van de ontwerper zodra die er zijn.
 *
 * De intro: de drie kinderen uit het logo rennen vanaf de rand van het scherm naar het midden, slaan de armen
 * om elkaar, de ring sluit zich en de animatie vloeit over in het officiële ronde logo. De kinderen zijn
 * nagetekend (vormen hieronder in V), zodat ze los kunnen bewegen.
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


// Vormen van het embleem (viewBox 0 0 400 392). Lagen van achter naar voren:
// romp pet-kind, romp krullenkind, shirt + arm meisje, arm krullenkind, hoofden, paardenstaart, pet, "BSO".
const GAT = 4.5; // witte rand tussen overlappende delen
const ARM = 17;
const krulCirkels = Array.from({ length: 12 }, (_, i) => {
  const a = (i / 12) * Math.PI * 2 + 0.2;
  return `<circle cx="${(121 + Math.cos(a) * 29).toFixed(1)}" cy="${(126 + Math.sin(a) * 29).toFixed(1)}" r="12.5"/>`;
});
const V = {
  krul: `<circle cx="121" cy="126" r="31"/>${krulCirkels.join("")}`,
  staart: "M214 112 C212 92 198 81 179 81 C162 81 149 90 145 105 C156 96 170 94 182 100 C190 104 195 112 193 121 Z",
  meisje: `<circle cx="201" cy="138" r="33"/>`,
  petHoofd: `<circle cx="281" cy="132" r="33"/>`,
  pet: "M247 128 C247 99 266 86 285 86 C307 86 321 101 321 126 Z",
  klep: "M309 121 C326 115 346 121 350 131 C340 138 322 138 306 134 Z",
  knoop: { cx: 285, cy: 85, r: 4.5 },
  naad: "M285 89 C281 101 279 113 281 127",
  rompR: "M362 340 L362 214 C356 184 330 168 296 166 L248 166 C238 168 232 176 232 190 L232 340 Z",
  rompL: "M38 340 L38 214 C44 184 70 168 104 166 L154 166 C164 168 170 176 170 190 L170 340 Z",
  shirt: "M166 180 C178 168 224 168 236 180 L244 340 L158 340 Z",
  kraag: "M185 171 C193 185 209 185 217 171",
  armMeisje: "M230 180 C258 168 294 167 324 184",
  armKrul: "M144 184 C164 172 188 168 212 176",
  mouwL: "M74 186 C88 206 92 240 88 330",
  mouwR: "M326 186 C312 206 308 240 312 330",
  glimlach: "M166 368 Q203 385 240 368",
};

/** Omhullende rechthoek van paden (alleen absolute M/L/C/Q/Z, zoals hierboven) en cirkels, met marge. */
function omhulling(delen) {
  let x1 = Infinity, y1 = Infinity, x2 = -Infinity, y2 = -Infinity;
  const neem = (x, y, m) => { x1 = Math.min(x1, x - m); y1 = Math.min(y1, y - m); x2 = Math.max(x2, x + m); y2 = Math.max(y2, y + m); };
  for (const { d, cirkels, marge = 0 } of delen) {
    if (d) {
      const getallen = d.match(/-?\d*\.?\d+/g).map(Number);
      for (let i = 0; i < getallen.length; i += 2) neem(getallen[i], getallen[i + 1], marge);
    }
    for (const [, cx, cy, r] of (cirkels || "").matchAll(/cx="([\d.]+)" cy="([\d.]+)" r="([\d.]+)"/g)) neem(+cx, +cy, +r + marge);
  }
  return { x: Math.floor(x1), y: Math.floor(y1), b: Math.ceil(x2) - Math.floor(x1), h: Math.ceil(y2) - Math.floor(y1) };
}

/**
 * Het logo voor de intro op de homepage: dezelfde vormen, verdeeld over lagen die elk apart kunnen
 * bewegen. De kinderen (met benen) rennen vanaf de rand van het scherm naar het midden, slaan de armen
 * om elkaar, de ring sluit zich om ze heen en daarna komen "BSO", het woordmerk en het lachje.
 * In plaats van maskers krijgt elke laag een "halo" in de achtergrondkleur (dezelfde witte rand als in het logo),
 * zodat de lagen ook tijdens het bewegen goed over elkaar vallen. Elke laag is een eigen HTML-element, zodat de
 * browser de beweging met de grafische kaart doet en de pagina niet trager wordt.
 * Aan het eind staat alles op zijn plek en is het beeld gelijk aan sporty-logo.svg.
 * Kleuren en beweging staan in css/style.css (sectie Intro); dit is alleen de vorm.
 */
export function maakIntro(pre = "") {
  const GAT2 = 2 * GAT;
  const pad = (klasse, d, extra = "") => `<path${klasse ? ` class="${klasse}"` : ""} d="${d}"${extra}/>`;
  const breed = (b) => ` stroke-width="${b}"`;
  const metKlasse = (cirkels, klasse, extra = "") => cirkels.replaceAll("<circle", `<circle class="${klasse}"${extra}`);
  const pct = (waarde, totaal) => `${+((waarde / totaal) * 100).toFixed(3)}%`;
  const VOET = { krul: [104, 340], pet: [296, 340], meisje: [201, 340] }; // heup: draaipunt bij het rennen

  // Tijdens het rennen hebben de kinderen een broek en benen; die vallen straks buiten de ring
  const BROEK = {
    krul: "M46 345 L162 345 L162 364 C162 371 157 375 150 375 L58 375 C51 375 46 371 46 364 Z",
    pet: "M240 345 L352 345 L352 364 C352 371 347 375 340 375 L252 375 C245 375 240 371 240 364 Z",
    meisje: "M163 348 L239 348 L240 366 C240 372 236 375 230 375 L172 375 C166 375 162 372 162 366 Z",
  };
  const BENEN = { krul: [[78, 130], 26], pet: [[270, 322], 26], meisje: [[185, 217], 22] };
  const broek = (wie) => pad("intro__halo", BROEK[wie], breed(GAT2)) + pad("intro__vlak", BROEK[wie]);
  const benen = Object.entries(BENEN).flatMap(([wie, [xen, dikte]]) => xen.map((x, i) => {
    const d = `M${x} 368 L${x} 452`;
    return { naam: `been-${wie}-${"ab"[i]}`, kind: wie, been: "ab"[i], delen: [{ d, marge: dikte / 2 }], inhoud: pad("intro__lijn", d, breed(dikte)) };
  }));

  // Van achter naar voren, net als de lagen van het logo. De iris (crème met een rond gat) sluit zich op het
  // eind om de kinderen heen en snijdt af wat buiten de ring valt, net als het knippad van het logo.
  const lagen = [
    ...benen,
    { naam: "pet-romp", kind: "pet", delen: [{ d: V.rompR, marge: 3 }, { d: BROEK.pet, marge: GAT }], inhoud: pad("intro__vlak", V.rompR) + pad("intro__halo-lijn", V.mouwR, breed(GAT)) + broek("pet") },
    { naam: "krul-romp", kind: "krul", delen: [{ d: V.rompL, marge: 3 }, { d: BROEK.krul, marge: GAT }], inhoud: pad("intro__vlak", V.rompL) + pad("intro__halo-lijn", V.mouwL, breed(GAT)) + broek("krul") },
    { naam: "meisje-shirt", kind: "meisje", delen: [{ d: V.shirt, marge: GAT }, { d: BROEK.meisje, marge: GAT }], inhoud: pad("intro__halo", V.shirt, breed(GAT2)) + pad("intro__lijn", V.shirt, breed(6)) + pad("intro__lijn", V.kraag, breed(6)) + broek("meisje") },
    // De arm van het meisje hoort bij haar shirt: de halo laat het shirt heel
    { naam: "arm-meisje", oorsprong: [230, 180], delen: [{ d: V.armMeisje, marge: (ARM + GAT2) / 2 }], inhoud: (vak) => `<mask id="intro-shirt" maskUnits="userSpaceOnUse" x="${vak.x}" y="${vak.y}" width="${vak.b}" height="${vak.h}"><rect x="${vak.x}" y="${vak.y}" width="${vak.b}" height="${vak.h}" fill="#fff"/><path d="${V.shirt}" stroke="#000" stroke-width="6"/></mask>` + pad("intro__halo-lijn", V.armMeisje, breed(ARM + GAT2) + ' mask="url(#intro-shirt)"') + pad("intro__lijn", V.armMeisje, breed(ARM)) },
    { naam: "arm-krul", oorsprong: [144, 184], delen: [{ d: V.armKrul, marge: (ARM + GAT2) / 2 }], inhoud: pad("intro__halo-lijn", V.armKrul, breed(ARM + GAT2)) + pad("intro__lijn", V.armKrul, breed(ARM)) },
    { naam: "krul-hoofd", kind: "krul", delen: [{ cirkels: V.krul, marge: GAT }], inhoud: `<g class="intro__halo"${breed(GAT2)}>${V.krul}</g><g class="intro__vlak">${V.krul}</g>` },
    { naam: "meisje-hoofd", kind: "meisje", delen: [{ cirkels: V.meisje, marge: GAT }, { d: V.staart, marge: GAT }], inhoud: metKlasse(V.meisje, "intro__halo", breed(GAT2)) + metKlasse(V.meisje, "intro__vlak") + pad("intro__halo", V.staart, breed(GAT2)) + pad("intro__vlak", V.staart) },
    { naam: "pet-hoofd", kind: "pet", delen: [{ cirkels: V.petHoofd, marge: GAT }, { d: V.pet, marge: GAT }, { d: V.klep, marge: GAT }], inhoud: metKlasse(V.petHoofd, "intro__halo", breed(GAT2)) + metKlasse(V.petHoofd, "intro__vlak") + `<g class="intro__halo"${breed(GAT2)}>${pad("", V.pet)}${pad("", V.klep)}</g>` + pad("intro__lijn", V.pet, breed(5)) + pad("intro__vlak intro__lijn", V.klep, breed(5)) + `<circle class="intro__vlak" cx="${V.knoop.cx}" cy="${V.knoop.cy}" r="${V.knoop.r}"/>` + pad("intro__lijn", V.naad, breed(3.5)) },
    { naam: "iris", oorsprong: [200, 186], vak: { x: -130, y: -144, b: 660, h: 660 }, inhoud: pad("intro__iris", "M-130 186a330 330 0 1 0 660 0a330 330 0 1 0-660 0ZM49 186a151 151 0 1 0 302 0a151 151 0 1 0-302 0Z", ' fill-rule="evenodd"') },
    { naam: "ring", oorsprong: [200, 186], vak: { x: 31, y: 17, b: 338, h: 338 }, inhoud: pad("intro__ring", "M200 23a163 163 0 1 1 0 326a163 163 0 1 1 0-326", breed(7)) },
  ];

  const html = (laag) => {
    const vak = laag.vak || omhulling(laag.delen);
    const [ox, oy] = laag.kind ? VOET[laag.kind] : laag.oorsprong;
    const klassen = `intro__laag intro__laag--${laag.naam}${laag.kind ? ` intro__kind intro__kind--${laag.kind}` : ""}${laag.been ? ` intro__been intro__been--${laag.been}` : ""}`;
    const stijl = `left:${pct(vak.x, 400)};top:${pct(vak.y, 392)};width:${pct(vak.b, 400)};height:${pct(vak.h, 392)};--o:${pct(ox - vak.x, vak.b)} ${pct(oy - vak.y, vak.h)}`;
    const inhoud = typeof laag.inhoud === "function" ? laag.inhoud(vak) : laag.inhoud;
    return `<div class="${klassen}" style="${stijl}"><svg viewBox="${vak.x} ${vak.y} ${vak.b} ${vak.h}" focusable="false">${inhoud}</svg></div>`;
  };
  // Het officiële ronde logo (assets/img/sporty-logo.svg), zo geplaatst dat de ring precies over de ring
  // van de animatie valt: middelpunt (200, 186) en straal 163, tegen middelpunt (1498,5; 1038,5) en straal 960
  // in de viewBox van het logo (507 46 1985 2104). Aan het eind vloeit de animatie hierin over.
  const k = 163 / 960;
  const officieel = `<img class="intro__officieel" src="${pre}assets/img/sporty-logo.svg" alt="" width="337" height="357" style="left:${pct(200 - (1498.5 - 507) * k, 400)};top:${pct(186 - (1038.5 - 46) * k, 392)};width:${pct(1985 * k, 400)}">`;
  return `<div class="intro__logo">\n<div class="intro__lagen">\n${lagen.map(html).join("\n")}\n</div>\n${officieel}\n</div>`;
}

export const INTRO_PAGINAS = ["index.html", "en/index.html"];
const INTRO = /(<!-- intro: gemaakt met npm run logo -->\n)[\s\S]*?(<!-- \/intro -->)/;

/** De pagina met de huidige intro tussen de markeringen. */
export function metIntro(html) {
  if (!INTRO.test(html)) throw new Error("Markeringen voor de intro ontbreken");
  const pre = /<link rel="canonical" href="[^"]*\/en\/">/.test(html) ? "../" : "";
  return html.replace(INTRO, (_, begin, eind) => `${begin}<div class="intro" hidden aria-hidden="true">\n${maakIntro(pre)}\n</div>\n${eind}`);
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
