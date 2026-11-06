#!/usr/bin/env node
/**
 * Maakt het kaartbeeld van de omgeving van Sporty (Locaties en Contact) uit kaarttegels van OpenStreetMap.
 *
 * Waarom een eigen plaatje: een ingesloten Google-kaart plaatst cookies en stuurt gegevens van bezoekers naar
 * Google, en mag daarom pas na toestemming laden. Dit plaatje staat op onze eigen server; wie erop klikt,
 * gaat naar Google Maps. Zo is er geen cookiemelding nodig.
 *
 * Gebruik (alleen nodig als het adres verandert; pas dan LAT en LON aan):
 *   NODE_USE_ENV_PROXY=1 node tools/kaart.mjs      (NODE_USE_ENV_PROXY alleen achter een proxy)
 *
 * De kaart is © OpenStreetMap-bijdragers (ODbL): de bronvermelding staat onder de kaart op de pagina's.
 * Gebruiksregels voor de tegels: https://operations.osmfoundation.org/policies/tiles/ (we halen ze eenmalig op).
 */
import sharp from "sharp";
import { fileURLToPath } from "node:url";

const ROOT = fileURLToPath(new URL("../", import.meta.url));
// Voorlandpad 17, 1098 TZ Amsterdam (opgezocht via OpenStreetMap Nominatim)
const LAT = 52.3481547;
const LON = 4.9535974;
const ZOOM = 16;
const BREED = 960;
const HOOG = 720;
const TEGEL = 256;
const UA = "Sporty-website/1.0 (eenmalig kaartbeeld maken; tools/kaart.mjs)";

const n = 2 ** ZOOM;
const px = ((LON + 180) / 360) * n * TEGEL;
const rad = (LAT * Math.PI) / 180;
const py = ((1 - Math.log(Math.tan(rad) + 1 / Math.cos(rad)) / Math.PI) / 2) * n * TEGEL;

const x0 = Math.floor((px - BREED / 2) / TEGEL);
const x1 = Math.floor((px + BREED / 2) / TEGEL);
const y0 = Math.floor((py - HOOG / 2) / TEGEL);
const y1 = Math.floor((py + HOOG / 2) / TEGEL);

const tegels = [];
for (let y = y0; y <= y1; y++) {
  for (let x = x0; x <= x1; x++) {
    const url = `https://tile.openstreetmap.org/${ZOOM}/${x}/${y}.png`;
    const antwoord = await fetch(url, { headers: { "User-Agent": UA } });
    if (!antwoord.ok) throw new Error(`${url}: ${antwoord.status}`);
    tegels.push({ input: Buffer.from(await antwoord.arrayBuffer()), left: (x - x0) * TEGEL, top: (y - y0) * TEGEL });
    await new Promise((klaar) => setTimeout(klaar, 250));
  }
}

const geheel = await sharp({
  create: { width: (x1 - x0 + 1) * TEGEL, height: (y1 - y0 + 1) * TEGEL, channels: 3, background: "#f2efe9" },
})
  .composite(tegels)
  .png()
  .toBuffer();

// Het adres precies in het midden; iets minder fel, zodat het oranje van de speld opvalt
const uitsnede = () =>
  sharp(geheel)
    .extract({ left: Math.round(px - BREED / 2 - x0 * TEGEL), top: Math.round(py - HOOG / 2 - y0 * TEGEL), width: BREED, height: HOOG })
    .modulate({ saturation: 0.7 });

await uitsnede().avif({ quality: 60, effort: 6 }).toFile(ROOT + "assets/img/kaart-sporty.avif");
await uitsnede().webp({ quality: 82, effort: 6 }).toFile(ROOT + "assets/img/kaart-sporty.webp");
console.log(`Kaartbeeld gemaakt uit ${tegels.length} tegels: assets/img/kaart-sporty.avif en .webp`);
