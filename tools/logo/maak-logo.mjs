#!/usr/bin/env node
/**
 * Maakt het Sporty-logo en de iconen, nagetekend naar het moodboard (design/moodboard-sporty.jpg):
 * een rond embleem met drie kinderen (krullen, paardenstaart, pet) die de armen om elkaar slaan,
 * "BSO" op het middelste shirt, het woordmerk "Sporty" in script en een lachje eronder.
 * Het logo is één kleur, zodat het oranje op wit en wit op oranje werkt.
 *
 * Gebruik: npm run logo
 * Schrijft naar assets/img/: sporty-logo(.svg, -wit.svg, -512.png), sporty-woordmerk(.svg, -wit.svg),
 * favicon.svg, apple-touch-icon.png, icon-192.png, icon-512.png, icon-maskable-512.png en favicon.ico.
 * Zet ook de intro (de kinderen rennen het logo in) in index.html en en/index.html.
 *
 * TODO: vervang de SVG's door de originele logobestanden van de ontwerper zodra die er zijn.
 * Letters: Pacifico (woordmerk) en Poppins ExtraBold ("BSO"), beide SIL Open Font License (assets/fonts/OFL.txt).
 */
import opentype from "opentype.js";
import sharp from "sharp";
import { readFileSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";

const ROOT = fileURLToPath(new URL("../../", import.meta.url));
const IMG = ROOT + "assets/img/";
const laad = (pad) => { const b = readFileSync(new URL(pad, import.meta.url)); return opentype.parse(b.buffer.slice(b.byteOffset, b.byteOffset + b.byteLength)); };
const pacifico = laad("./fonts/pacifico-latin-400.woff");
const poppins = laad("./fonts/poppins-latin-800.woff");

const tekstPad = (font, tekst, grootte, cx, basislijn) => {
  const breedte = font.getAdvanceWidth(tekst, grootte);
  const p = font.getPath(tekst, 0, 0, grootte);
  const bb = p.getBoundingBox();
  const x = cx - (bb.x1 + bb.x2) / 2;
  return { d: font.getPath(tekst, x, basislijn, grootte).toPathData(1), breedte, bb: { x1: bb.x1 + x, x2: bb.x2 + x, y1: bb.y1 + basislijn, y2: bb.y2 + basislijn } };
};

// Vormen van het embleem (viewBox 0 0 400 392). Lagen van achter naar voren:
// romp pet-kind, romp krullenkind, shirt + arm meisje, arm krullenkind, hoofden, paardenstaart, pet, "BSO".
const GAT = 4.5; // witte rand tussen overlappende delen
const KO = 9; // ruimte rond het woordmerk
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

export function maakLogo({ kleur = "#F05A1A", achtergrond = null, alleenWoord = false } = {}) {
  const woord = tekstPad(pacifico, "Sporty", alleenWoord ? 100 : 104, 200, alleenWoord ? 100 : 330);
  if (alleenWoord) {
    // Woordmerk met lachje, voor de header
    const vb = { x: woord.bb.x1 - 4, y: woord.bb.y1 - 4, w: woord.bb.x2 - woord.bb.x1 + 8, h: woord.bb.y2 - woord.bb.y1 + 8 };
    return { svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${vb.x.toFixed(1)} ${vb.y.toFixed(1)} ${vb.w.toFixed(1)} ${vb.h.toFixed(1)}"><path fill="${kleur}" d="${woord.d}"/></svg>`, vb };
  }
  const bso = tekstPad(poppins, "BSO", 27, 201, 238);
  const GAT2 = 2 * GAT;

  // teken = wat je ziet, vorm = silhouet dat lagen erachter uitspaart
  const lijn = (d, b) => `<path d="${d}" fill="none" stroke="${kleur}" stroke-width="${b}" stroke-linecap="round" stroke-linejoin="round"/>`;
  const lijnVorm = (d, b) => `<path d="${d}" fill="none" stroke="#000" stroke-width="${b}" stroke-linecap="round" stroke-linejoin="round"/>`;
  const lagen = [
    { teken: `<path d="${V.rompR}" fill="${kleur}"/>`, vorm: `<path d="${V.rompR}"/>`, eigenSnede: lijnVorm(V.mouwR, GAT) },
    { teken: `<path d="${V.rompL}" fill="${kleur}"/>`, vorm: `<path d="${V.rompL}"/>`, eigenSnede: lijnVorm(V.mouwL, GAT) },
    { teken: lijn(V.shirt, 6) + lijn(V.kraag, 6) + lijn(V.armMeisje, ARM), vorm: `<path d="${V.shirt}"/>`, vormLijn: lijnVorm(V.armMeisje, ARM + GAT2) },
    { teken: lijn(V.armKrul, ARM), vorm: "", vormLijn: lijnVorm(V.armKrul, ARM + GAT2) },
    { teken: `<g fill="${kleur}">${V.krul}${V.meisje}${V.petHoofd}</g>`, vorm: V.krul + V.meisje + V.petHoofd },
    { teken: `<path d="${V.staart}" fill="${kleur}"/>`, vorm: `<path d="${V.staart}"/>` },
    { teken: `<path d="${V.pet}" fill="none" stroke="${kleur}" stroke-width="5" stroke-linejoin="round"/><path d="${V.klep}" fill="${kleur}" stroke="${kleur}" stroke-width="5" stroke-linejoin="round"/><circle cx="${V.knoop.cx}" cy="${V.knoop.cy}" r="${V.knoop.r}" fill="${kleur}"/><path d="${V.naad}" fill="none" stroke="${kleur}" stroke-width="3.5" stroke-linecap="round"/>`, vorm: `<path d="${V.pet}"/><path d="${V.klep}"/>` },
    { teken: `<path d="${bso.d}" fill="${kleur}"/>`, vorm: "" },
  ];

  const woordKO = `<use href="#w" fill="#000" stroke="#000" stroke-width="${2 * KO}" stroke-linejoin="round"/><use href="#gl" fill="none" stroke="#000" stroke-width="${11 + 2 * KO}" stroke-linecap="round"/>`;
  const maskers = lagen.map((laag, i) => {
    const ervoor = lagen.slice(i + 1).map((l) => (l.vorm ? `<g fill="#000" stroke="#000" stroke-width="${GAT2}" stroke-linejoin="round">${l.vorm}</g>` : "") + (l.vormLijn || "")).join("");
    return `<mask id="m${i}" maskUnits="userSpaceOnUse" x="0" y="0" width="400" height="400"><rect width="400" height="400" fill="#fff"/>${ervoor}${laag.eigenSnede || ""}${woordKO}</mask>`;
  });
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 392">
<defs><path id="w" d="${woord.d}"/><path id="gl" d="${V.glimlach}"/><clipPath id="binnen"><circle cx="200" cy="186" r="151"/></clipPath>${maskers.join("")}<mask id="mRing" maskUnits="userSpaceOnUse" x="0" y="0" width="400" height="400"><rect width="400" height="400" fill="#fff"/>${woordKO}</mask></defs>
${achtergrond ? `<rect width="400" height="392" fill="${achtergrond}"/>` : ""}
<circle cx="200" cy="186" r="163" fill="none" stroke="${kleur}" stroke-width="11" mask="url(#mRing)"/>
<g clip-path="url(#binnen)">${lagen.map((l, i) => `<g mask="url(#m${i})">${l.teken}</g>`).join("")}</g>
<use href="#w" fill="${kleur}"/>
<use href="#gl" fill="none" stroke="${kleur}" stroke-width="11" stroke-linecap="round"/>
</svg>`;
  return { svg };
}

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
 * bewegen (de kinderen rennen in beeld, de armen gaan om elkaar heen, daarna "BSO", het woordmerk en het lachje).
 * In plaats van maskers krijgt elke laag een "halo" in de achtergrondkleur (dezelfde witte rand als in het logo),
 * zodat de lagen ook tijdens het bewegen goed over elkaar vallen. Elke laag is een eigen HTML-element, zodat de
 * browser de beweging met de grafische kaart doet en de pagina niet trager wordt.
 * Aan het eind staat alles op zijn plek en is het beeld gelijk aan sporty-logo.svg.
 * Kleuren en beweging staan in css/style.css (sectie Intro); dit is alleen de vorm.
 */
export function maakIntro() {
  const woord = tekstPad(pacifico, "Sporty", 104, 200, 330);
  const bso = tekstPad(poppins, "BSO", 27, 201, 238);
  const GAT2 = 2 * GAT;
  const pad = (klasse, d, extra = "") => `<path${klasse ? ` class="${klasse}"` : ""} d="${d}"${extra}/>`;
  const breed = (b) => ` stroke-width="${b}"`;
  const metKlasse = (cirkels, klasse, extra = "") => cirkels.replaceAll("<circle", `<circle class="${klasse}"${extra}`);
  const pct = (waarde, totaal) => `${+((waarde / totaal) * 100).toFixed(3)}%`;
  const VOET = { krul: [104, 340], pet: [296, 340], meisje: [201, 340] };

  // Van achter naar voren, net als de lagen van het logo
  const lagen = [
    { naam: "ring", oorsprong: [200, 186], vak: { x: 31, y: 17, b: 338, h: 338 }, inhoud: pad("intro__ring", "M200 23a163 163 0 1 1 0 326a163 163 0 1 1 0-326", breed(11)), buiten: true },
    { naam: "pet-romp", kind: "pet", delen: [{ d: V.rompR, marge: 3 }], inhoud: pad("intro__vlak", V.rompR) + pad("intro__halo-lijn", V.mouwR, breed(GAT)) },
    { naam: "krul-romp", kind: "krul", delen: [{ d: V.rompL, marge: 3 }], inhoud: pad("intro__vlak", V.rompL) + pad("intro__halo-lijn", V.mouwL, breed(GAT)) },
    { naam: "meisje-shirt", kind: "meisje", delen: [{ d: V.shirt, marge: GAT }], inhoud: pad("intro__halo", V.shirt, breed(GAT2)) + pad("intro__lijn", V.shirt, breed(6)) + pad("intro__lijn", V.kraag, breed(6)) },
    // De arm van het meisje hoort bij haar shirt: de halo laat het shirt heel
    { naam: "arm-meisje", oorsprong: [230, 180], delen: [{ d: V.armMeisje, marge: (ARM + GAT2) / 2 }], inhoud: (vak) => `<mask id="intro-shirt" maskUnits="userSpaceOnUse" x="${vak.x}" y="${vak.y}" width="${vak.b}" height="${vak.h}"><rect x="${vak.x}" y="${vak.y}" width="${vak.b}" height="${vak.h}" fill="#fff"/><path d="${V.shirt}" stroke="#000" stroke-width="6"/></mask>` + pad("intro__halo-lijn", V.armMeisje, breed(ARM + GAT2) + ' mask="url(#intro-shirt)"') + pad("intro__lijn", V.armMeisje, breed(ARM)) },
    { naam: "arm-krul", oorsprong: [144, 184], delen: [{ d: V.armKrul, marge: (ARM + GAT2) / 2 }], inhoud: pad("intro__halo-lijn", V.armKrul, breed(ARM + GAT2)) + pad("intro__lijn", V.armKrul, breed(ARM)) },
    { naam: "krul-hoofd", kind: "krul", delen: [{ cirkels: V.krul, marge: GAT }], inhoud: `<g class="intro__halo"${breed(GAT2)}>${V.krul}</g><g class="intro__vlak">${V.krul}</g>` },
    { naam: "meisje-hoofd", kind: "meisje", delen: [{ cirkels: V.meisje, marge: GAT }, { d: V.staart, marge: GAT }], inhoud: metKlasse(V.meisje, "intro__halo", breed(GAT2)) + metKlasse(V.meisje, "intro__vlak") + pad("intro__halo", V.staart, breed(GAT2)) + pad("intro__vlak", V.staart) },
    { naam: "pet-hoofd", kind: "pet", delen: [{ cirkels: V.petHoofd, marge: GAT }, { d: V.pet, marge: GAT }, { d: V.klep, marge: GAT }], inhoud: metKlasse(V.petHoofd, "intro__halo", breed(GAT2)) + metKlasse(V.petHoofd, "intro__vlak") + `<g class="intro__halo"${breed(GAT2)}>${pad("", V.pet)}${pad("", V.klep)}</g>` + pad("intro__lijn", V.pet, breed(5)) + pad("intro__vlak intro__lijn", V.klep, breed(5)) + `<circle class="intro__vlak" cx="${V.knoop.cx}" cy="${V.knoop.cy}" r="${V.knoop.r}"/>` + pad("intro__lijn", V.naad, breed(3.5)) },
    { naam: "bso", oorsprong: [201, 228], delen: [{ d: bso.d, marge: 1 }], inhoud: pad("intro__vlak", bso.d) },
    { naam: "woord", oorsprong: [200, 308], buiten: true, delen: [{ d: woord.d, marge: KO }, { d: V.glimlach, marge: (11 + 2 * KO) / 2 }], inhoud: `<defs><path id="intro-w" d="${woord.d}"/></defs><use class="intro__halo" href="#intro-w"${breed(2 * KO)}/>` + pad("intro__halo-lijn", V.glimlach, breed(11 + 2 * KO)) + `<use class="intro__vlak" href="#intro-w"/>` },
    { naam: "lach", oorsprong: [203, 372], buiten: true, delen: [{ d: V.glimlach, marge: 5.5 }], inhoud: pad("intro__lijn", V.glimlach, breed(11)) },
  ];

  const html = (laag) => {
    const vak = laag.vak || omhulling(laag.delen);
    const [ox, oy] = laag.kind ? VOET[laag.kind] : laag.oorsprong;
    const klassen = `intro__laag intro__laag--${laag.naam}${laag.kind ? ` intro__kind intro__kind--${laag.kind}` : ""}`;
    const stijl = `left:${pct(vak.x, 400)};top:${pct(vak.y, 392)};width:${pct(vak.b, 400)};height:${pct(vak.h, 392)};--o:${pct(ox - vak.x, vak.b)} ${pct(oy - vak.y, vak.h)}`;
    const inhoud = typeof laag.inhoud === "function" ? laag.inhoud(vak) : laag.inhoud;
    return `<div class="${klassen}" style="${stijl}"><svg viewBox="${vak.x} ${vak.y} ${vak.b} ${vak.h}" focusable="false">${inhoud}</svg></div>`;
  };
  const binnen = lagen.filter((l) => !l.buiten).map(html).join("\n");
  const [ring, ...erna] = lagen.filter((l) => l.buiten).map(html);
  return `<div class="intro__logo">\n${ring}\n<div class="intro__binnen">\n${binnen}\n</div>\n${erna.join("\n")}\n</div>`;
}

export const INTRO_PAGINAS = ["index.html", "en/index.html"];
const INTRO = /(<!-- intro: gemaakt met npm run logo -->\n)[\s\S]*?(<!-- \/intro -->)/;

/** De pagina met de huidige intro tussen de markeringen. */
export function metIntro(html) {
  if (!INTRO.test(html)) throw new Error("Markeringen voor de intro ontbreken");
  return html.replace(INTRO, (_, begin, eind) => `${begin}<div class="intro" hidden aria-hidden="true">\n${maakIntro()}\n</div>\n${eind}`);
}

// Favicon en app-iconen: oranje vlak met een witte script-S
export function maakIcoon({ kleur = "#F05A1A", letter = "#ffffff", vorm = "rond", marge = 0 } = {}) {
  const grootte = 44 * (1 - marge);
  const p = pacifico.getPath("S", 0, 0, grootte); const bb = p.getBoundingBox();
  const x = 32 - (bb.x1 + bb.x2) / 2, y = 32 - (bb.y1 + bb.y2) / 2 + 1;
  const d = pacifico.getPath("S", x, y, grootte).toPathData(2);
  const vlak = vorm === "rond" ? `<circle cx="32" cy="32" r="32" fill="${kleur}"/>` : `<rect width="64" height="64" fill="${kleur}"/>`;
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">${vlak}<path fill="${letter}" d="${d}"/></svg>`;
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const kopregel = "<!-- Sporty-logo, nagetekend naar het moodboard (design/moodboard-sporty.jpg). TODO: vervangen door het originele logobestand van de ontwerper. -->\n";
  const svgs = {
    "sporty-logo.svg": kopregel + maakLogo().svg,
    "sporty-logo-wit.svg": kopregel + maakLogo({ kleur: "#ffffff" }).svg,
    "sporty-woordmerk.svg": kopregel + maakLogo({ alleenWoord: true }).svg,
    "sporty-woordmerk-wit.svg": kopregel + maakLogo({ alleenWoord: true, kleur: "#ffffff" }).svg,
    "favicon.svg": maakIcoon(),
  };
  for (const [naam, inhoud] of Object.entries(svgs)) writeFileSync(IMG + naam, inhoud);

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
  writeFileSync(IMG + "sporty-logo-512.png", await png(maakLogo().svg, 512, "#ffffff")); // voor schema.org (Google)
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
