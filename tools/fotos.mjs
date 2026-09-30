// Zet de bronbeelden in assets/fotos/bron/ om naar AVIF en WebP in meerdere breedtes.
//
// Gebruik:  npm install  &&  npm run fotos
//
// Vervang een stockfoto door een eigen foto door een .jpg of .png met dezelfde
// naam in assets/fotos/bron/ te zetten (bijv. hero.jpg). Lever foto's bij
// voorkeur minimaal 1600px breed aan.
// De website zelf heeft geen build-stap nodig: de uitvoer wordt meegecommit.

import { readdir, mkdir } from "node:fs/promises";
import { extname, basename, join } from "node:path";
import sharp from "sharp";

const BRON = "assets/fotos/bron";
const DOEL = "assets/fotos";
const BREEDTES = [480, 800, 1200, 1600];
const TOEGESTAAN = new Set([".jpg", ".jpeg", ".png", ".webp", ".svg"]);

await mkdir(DOEL, { recursive: true });
const bestanden = (await readdir(BRON)).filter((f) => TOEGESTAAN.has(extname(f).toLowerCase()));

for (const bestand of bestanden) {
  const naam = basename(bestand, extname(bestand));
  const pad = join(BRON, bestand);
  const isSvg = extname(bestand).toLowerCase() === ".svg";
  const meta = await sharp(pad).metadata();
  if (!isSvg && meta.width < 1600) {
    console.warn(`Let op: ${bestand} is ${meta.width}px breed; lever liefst 1600px of meer aan.`);
  }
  for (const breedte of BREEDTES) {
    // SVG op hoge dichtheid inlezen zodat grote uitsneden scherp blijven
    const invoer = () => sharp(pad, isSvg ? { density: Math.max(72, (72 * breedte) / meta.width) } : {})
      .rotate()
      .resize({ width: breedte, withoutEnlargement: !isSvg });
    await invoer().avif({ quality: 55, effort: 4 }).toFile(join(DOEL, `${naam}-${breedte}.avif`));
    await invoer().webp({ quality: 78, effort: 6 }).toFile(join(DOEL, `${naam}-${breedte}.webp`));
  }
  console.log(`✓ ${naam}`);
}
