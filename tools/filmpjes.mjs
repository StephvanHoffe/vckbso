#!/usr/bin/env node
/**
 * Zet korte filmpjes (zoals de juichmomenten van het team) om voor de website.
 *
 * Per filmpje in FILMPJES hieronder maakt het script, in assets/fotos/:
 *   <naam>.webm         VP9, het kleinst (Chrome, Firefox, Edge, Safari 17+)
 *   <naam>.mp4          H.264, voor alle andere browsers
 *   <naam>-poster.webp  het stilstaande beeld: wat bezoekers zien tot het filmpje speelt, als het
 *                       gepauzeerd is en bij "minder beweging"
 * Alles 4:5 (zoals de beelden op de teampagina), 600×750 pixels, zonder geluid en zonder metadata
 * (dus ook zonder de locatie die een telefoon in een filmpje zet).
 *
 * De bronnen staan in assets/fotos/bron/filmpjes/ (die map gaat niet mee naar de server).
 * Een nieuw filmpje: zet het daar neer (MOV of MP4, liefst staand), voeg een regel toe aan FILMPJES
 * en draai:  npm run filmpjes            (alles)
 *            npm run filmpjes -- juich-tim  (alleen dit filmpje)
 * Vereist ffmpeg (met libx264 en libvpx-vp9) en sharp (npm install).
 */
import { execFileSync } from "node:child_process";
import { existsSync, mkdtempSync, rmSync, statSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { fileURLToPath } from "node:url";
import sharp from "sharp";

const ROOT = fileURLToPath(new URL("..", import.meta.url));
const BRON = join(ROOT, "assets/fotos/bron/filmpjes");
const DOEL = join(ROOT, "assets/fotos");
const BREEDTE = 600;
const HOOGTE = 750; // 4:5
const POSTER = { breedte: 600, hoogte: 750 }; // de beelden op de teampagina zijn hooguit 19rem (304px) breed: genoeg voor een scherp scherm

/**
 * bron:   het bestand in assets/fotos/bron/filmpjes/
 * boven:  waar de uitsnede van 4:5 komt als het filmpje hoger is (0 = bovenaan, 0,5 = midden, 1 = onderaan)
 * poster: { foto: "bestand.jpg" } een eigen foto, of { seconde: 1.5 } een beeld uit het filmpje;
 *         bij een foto eventueel uitsnede: { left, top, width, height } in pixels van de foto
 */
export const FILMPJES = [
  { naam: "juich-sureyo", bron: "juich-sureyo.mp4", poster: { foto: "team-sureyo.jpg", uitsnede: { left: 96, top: 314, width: 608, height: 760 } } },
  // TODO: de foto's van Tim en Anas als poster gebruiken zodra ze als bestand binnen zijn (nu een beeld uit het filmpje)
  { naam: "juich-tim", bron: "juich-tim.mp4", poster: { seconde: 0.9 } },
  { naam: "juich-anas", bron: "juich-anas.mp4", poster: { seconde: 5.1 } },
];

const ffmpeg = (...args) => execFileSync("ffmpeg", ["-v", "error", "-y", ...args], { stdio: "inherit" });
const kb = (pad) => `${Math.round(statSync(pad).size / 1024)} kB`;

function uitsnede(boven = 0.5) {
  // Hoger dan 4:5: een stuk van 4:5 uitsnijden (de volle breedte), anders alleen schalen
  const h = "min(ih\\,iw*5/4)";
  return `crop=iw:${h}:0:(ih-${h})*${boven},scale=${BREEDTE}:${HOOGTE}:flags=lanczos,setsar=1`;
}

async function maak({ naam, bron, boven, poster }) {
  const invoer = join(BRON, bron);
  if (!existsSync(invoer)) throw new Error(`${bron} staat niet in assets/fotos/bron/filmpjes/`);
  const filter = uitsnede(boven);
  const zonder = ["-an", "-sn", "-map_metadata", "-1", "-map_chapters", "-1"];
  const webm = join(DOEL, `${naam}.webm`);
  const mp4 = join(DOEL, `${naam}.mp4`);
  ffmpeg("-i", invoer, ...zonder, "-vf", filter, "-c:v", "libvpx-vp9", "-crf", "40", "-b:v", "0", "-row-mt", "1", "-deadline", "good", "-cpu-used", "2", webm);
  ffmpeg("-i", invoer, ...zonder, "-vf", filter, "-c:v", "libx264", "-crf", "27", "-preset", "slow", "-profile:v", "high", "-pix_fmt", "yuv420p", "-movflags", "+faststart", mp4);

  // Het stilstaande beeld: een eigen foto, of een beeld uit het filmpje
  const posterPad = join(DOEL, `${naam}-poster.webp`);
  let beeld;
  const tijdelijk = mkdtempSync(join(tmpdir(), "filmpje-"));
  try {
    if (poster?.foto) beeld = join(BRON, poster.foto);
    else {
      beeld = join(tijdelijk, "beeld.png");
      ffmpeg("-ss", String(poster?.seconde ?? 0), "-i", invoer, "-frames:v", "1", "-vf", uitsnede(boven).replace(`scale=${BREEDTE}:${HOOGTE}`, `scale=${POSTER.breedte}:${POSTER.hoogte}`), beeld);
    }
    const foto = sharp(beeld).rotate();
    if (poster?.uitsnede) foto.extract(poster.uitsnede);
    await foto.resize(POSTER.breedte, POSTER.hoogte, { fit: "cover" }).webp({ quality: 72, effort: 6 }).toFile(posterPad);
  } finally {
    rmSync(tijdelijk, { recursive: true, force: true });
  }
  console.log(`✓ ${naam}: webm ${kb(webm)}, mp4 ${kb(mp4)}, poster ${kb(posterPad)}`);
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const alleen = new Set(process.argv.slice(2));
  for (const filmpje of FILMPJES.filter((f) => !alleen.size || alleen.has(f.naam))) await maak(filmpje);
}
