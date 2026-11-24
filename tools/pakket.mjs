// Maakt het uploadpakket voor de hosting (bijvoorbeeld een Managed VPS bij Vimexx): alleen de bestanden
// die online moeten, zonder voorbeeld, tools, tests, documentatie, ontwerpbestanden of gegevens.
//
//   npm run pakket            -> pakket/sporty-website.zip (uitpakken in public_html)
//
// Het script controleert eerst of elke link in de pagina's (href, src, srcset, url()) naar een bestand
// in het pakket wijst. Ontbreekt er iets, dan stopt het met een foutmelding. Zie docs/live-zetten-vimexx.md.
import { readFileSync, readdirSync, statSync, existsSync, mkdirSync, rmSync, writeFileSync } from "node:fs";
import { join, dirname, relative, posix } from "node:path";
import { execFileSync } from "node:child_process";
import { fileURLToPath } from "node:url";

const ROOT = join(dirname(fileURLToPath(import.meta.url)), "..");

// Wat er online moet. Mappen worden helemaal meegenomen, behalve wat onder UITSLUITEN valt.
const BESTANDEN = [".htaccess", "favicon.ico", "site.webmanifest", "robots.txt", "sitemap.xml", "design/tokens.css"];
const MAPPEN = ["css", "js", "assets", "en", "locaties", "beheer"];
const UITSLUITEN = [
  /\.md$/, // interne notities, zoals assets/fotos/BRONNEN.md
  /^assets\/fotos\/bron\//, // de bronbestanden van foto's en filmpjes; de site gebruikt alleen de omgezette versies
  /(^|\/)\.DS_Store$/,
  /^beheer\/config\.php$/, // de echte configuratie maak je op de server zelf
  /^beheer\/data\/(?!\.htaccess$|index\.html$)/, // nooit gegevens meesturen
];

function alleBestanden(map) {
  const uit = [];
  for (const naam of readdirSync(join(ROOT, map))) {
    const pad = posix.join(map, naam);
    if (statSync(join(ROOT, pad)).isDirectory()) uit.push(...alleBestanden(pad));
    else uit.push(pad);
  }
  return uit;
}

/** De lijst met bestanden (relatief, met /) die in het pakket komen. */
export function bepaalBestanden() {
  const paginas = readdirSync(ROOT).filter((f) => f.endsWith(".html"));
  const lijst = [...paginas, ...BESTANDEN, ...MAPPEN.flatMap(alleBestanden)];
  return [...new Set(lijst)].filter((p) => existsSync(join(ROOT, p)) && !UITSLUITEN.some((r) => r.test(p))).sort();
}

/** Alle lokale verwijzingen in html- en css-bestanden die niet naar een bestand in het pakket wijzen. */
export function ontbrekendeLinks(bestanden) {
  const inPakket = new Set(bestanden);
  const fouten = [];
  for (const bestand of bestanden.filter((b) => /\.(html|css)$/.test(b) && !b.startsWith("beheer/"))) {
    const tekst = readFileSync(join(ROOT, bestand), "utf8");
    const refs = [
      ...[...tekst.matchAll(/\b(?:href|src)="([^"]+)"/g)].map((m) => m[1]),
      ...[...tekst.matchAll(/\bsrcset="([^"]+)"/g)].flatMap((m) => m[1].split(",").map((d) => d.trim().split(/\s+/)[0])),
      ...[...tekst.matchAll(/url\(\s*["']?([^"')]+)["']?\s*\)/g)].map((m) => m[1]),
    ];
    for (let ref of refs) {
      if (/^(https?:|mailto:|tel:|data:|#|javascript:|\/\/)/.test(ref) || ref.includes("{{")) continue;
      ref = ref.split("#")[0].split("?")[0];
      if (ref === "") continue;
      // Links naar Mijn BSO (beheer/*.php) gaan naar pagina's die de server zelf maakt
      const doel = ref.startsWith("/") ? ref.slice(1) : posix.normalize(posix.join(posix.dirname(bestand), ref));
      const kandidaten = [doel, posix.join(doel, "index.html"), posix.join(doel, "index.php")];
      if (!kandidaten.some((k) => inPakket.has(k.replace(/\/$/, "")) || inPakket.has(k))) {
        fouten.push(`${bestand}: ${ref}`);
      }
    }
  }
  return [...new Set(fouten)];
}

function maakPakket() {
  const bestanden = bepaalBestanden();
  const fouten = ontbrekendeLinks(bestanden);
  if (fouten.length) {
    console.error(`Deze links wijzen naar een bestand dat niet in het pakket zit:\n  ${fouten.join("\n  ")}`);
    process.exit(1);
  }
  const uitMap = join(ROOT, "pakket");
  mkdirSync(uitMap, { recursive: true });
  const zip = join(uitMap, "sporty-website.zip");
  rmSync(zip, { force: true });
  const lijst = join(uitMap, "bestanden.txt");
  writeFileSync(lijst, bestanden.join("\n") + "\n");
  execFileSync("zip", ["-q", "-X", zip, "-@"], { cwd: ROOT, input: bestanden.join("\n") });
  const mb = (statSync(zip).size / 1024 / 1024).toFixed(1);
  console.log(`Klaar: ${relative(ROOT, zip)} (${bestanden.length} bestanden, ${mb} MB).`);
  console.log("Pak het uit in public_html en volg docs/live-zetten-vimexx.md.");
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  maakPakket();
}
