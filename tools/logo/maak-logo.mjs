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

export function maakLogo({ kleur = "#F05A1A", achtergrond = null, alleenWoord = false } = {}) {
  const GAT = 4.5; // witte rand tussen overlappende delen
  const KO = 9; // ruimte rond het woordmerk
  const woord = tekstPad(pacifico, "Sporty", alleenWoord ? 100 : 104, 200, alleenWoord ? 100 : 330);
  const glimlachD = alleenWoord ? "" : "M166 368 Q203 385 240 368";
  if (alleenWoord) {
    // Woordmerk met lachje, voor de header
    const vb = { x: woord.bb.x1 - 4, y: woord.bb.y1 - 4, w: woord.bb.x2 - woord.bb.x1 + 8, h: woord.bb.y2 - woord.bb.y1 + 8 };
    return { svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${vb.x.toFixed(1)} ${vb.y.toFixed(1)} ${vb.w.toFixed(1)} ${vb.h.toFixed(1)}"><path fill="${kleur}" d="${woord.d}"/></svg>`, vb };
  }
  const bso = tekstPad(poppins, "BSO", 27, 201, 238);
  const GAT2 = 2 * GAT;
  const ARM = 17;

  // Lagen van achter naar voren. teken = wat je ziet, vorm = silhouet dat lagen erachter uitspaart
  const krulCirkels = [];
  for (let i = 0; i < 12; i++) { const a = (i / 12) * Math.PI * 2 + 0.2; krulCirkels.push(`<circle cx="${(121 + Math.cos(a) * 29).toFixed(1)}" cy="${(126 + Math.sin(a) * 29).toFixed(1)}" r="12.5"/>`); }
  const krul = `<circle cx="121" cy="126" r="31"/>${krulCirkels.join("")}`;
  const staart = "M214 112 C212 92 198 81 179 81 C162 81 149 90 145 105 C156 96 170 94 182 100 C190 104 195 112 193 121 Z";
  const meisje = `<circle cx="201" cy="138" r="33"/>`;
  const petHoofd = `<circle cx="281" cy="132" r="33"/>`;
  const pet = "M247 128 C247 99 266 86 285 86 C307 86 321 101 321 126 Z";
  const klep = "M309 121 C326 115 346 121 350 131 C340 138 322 138 306 134 Z";
  const rompR = "M362 340 L362 214 C356 184 330 168 296 166 L248 166 C238 168 232 176 232 190 L232 340 Z";
  const rompL = "M38 340 L38 214 C44 184 70 168 104 166 L154 166 C164 168 170 176 170 190 L170 340 Z";
  const shirt = "M166 180 C178 168 224 168 236 180 L244 340 L158 340 Z";
  const kraag = "M185 171 C193 185 209 185 217 171";
  const armMeisje = "M230 180 C258 168 294 167 324 184";
  const armKrul = "M144 184 C164 172 188 168 212 176";
  const mouwL = "M74 186 C88 206 92 240 88 330", mouwR = "M326 186 C312 206 308 240 312 330";
  const lijn = (d, b) => `<path d="${d}" fill="none" stroke="${kleur}" stroke-width="${b}" stroke-linecap="round" stroke-linejoin="round"/>`;
  const lijnVorm = (d, b) => `<path d="${d}" fill="none" stroke="#000" stroke-width="${b}" stroke-linecap="round" stroke-linejoin="round"/>`;
  const lagen = [
    { teken: `<path d="${rompR}" fill="${kleur}"/>`, vorm: `<path d="${rompR}"/>`, eigenSnede: lijnVorm(mouwR, GAT) },
    { teken: `<path d="${rompL}" fill="${kleur}"/>`, vorm: `<path d="${rompL}"/>`, eigenSnede: lijnVorm(mouwL, GAT) },
    { teken: lijn(shirt, 6) + lijn(kraag, 6) + lijn(armMeisje, ARM), vorm: `<path d="${shirt}"/>`, vormLijn: lijnVorm(armMeisje, ARM + GAT2) },
    { teken: lijn(armKrul, ARM), vorm: "", vormLijn: lijnVorm(armKrul, ARM + GAT2) },
    { teken: `<g fill="${kleur}">${krul}${meisje}${petHoofd}</g>`, vorm: krul + meisje + petHoofd },
    { teken: `<path d="${staart}" fill="${kleur}"/>`, vorm: `<path d="${staart}"/>` },
    { teken: `<path d="${pet}" fill="none" stroke="${kleur}" stroke-width="5" stroke-linejoin="round"/><path d="${klep}" fill="${kleur}" stroke="${kleur}" stroke-width="5" stroke-linejoin="round"/><circle cx="285" cy="85" r="4.5" fill="${kleur}"/><path d="M285 89 C281 101 279 113 281 127" fill="none" stroke="${kleur}" stroke-width="3.5" stroke-linecap="round"/>`, vorm: `<path d="${pet}"/><path d="${klep}"/>` },
    { teken: `<path d="${bso.d}" fill="${kleur}"/>`, vorm: "" },
  ];

  const woordKO = `<use href="#w" fill="#000" stroke="#000" stroke-width="${2 * KO}" stroke-linejoin="round"/><use href="#gl" fill="none" stroke="#000" stroke-width="${11 + 2 * KO}" stroke-linecap="round"/>`;
  const maskers = lagen.map((laag, i) => {
    const ervoor = lagen.slice(i + 1).map((l) => (l.vorm ? `<g fill="#000" stroke="#000" stroke-width="${GAT2}" stroke-linejoin="round">${l.vorm}</g>` : "") + (l.vormLijn || "")).join("");
    return `<mask id="m${i}" maskUnits="userSpaceOnUse" x="0" y="0" width="400" height="400"><rect width="400" height="400" fill="#fff"/>${ervoor}${laag.eigenSnede || ""}${woordKO}</mask>`;
  });
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 392">
<defs><path id="w" d="${woord.d}"/><path id="gl" d="${glimlachD}"/><clipPath id="binnen"><circle cx="200" cy="186" r="151"/></clipPath>${maskers.join("")}<mask id="mRing" maskUnits="userSpaceOnUse" x="0" y="0" width="400" height="400"><rect width="400" height="400" fill="#fff"/>${woordKO}</mask></defs>
${achtergrond ? `<rect width="400" height="392" fill="${achtergrond}"/>` : ""}
<circle cx="200" cy="186" r="163" fill="none" stroke="${kleur}" stroke-width="11" mask="url(#mRing)"/>
<g clip-path="url(#binnen)">${lagen.map((l, i) => `<g mask="url(#m${i})">${l.teken}</g>`).join("")}</g>
<use href="#w" fill="${kleur}"/>
<use href="#gl" fill="none" stroke="${kleur}" stroke-width="11" stroke-linecap="round"/>
</svg>`;
  return { svg };
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
