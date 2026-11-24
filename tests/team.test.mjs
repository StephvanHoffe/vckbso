/**
 * Het teamblok op de homepage (feedback 2.0, punt 17): dezelfde mensen als op de teampagina, met hun foto,
 * in het Nederlands en het Engels. Elke kaart linkt naar het teamlid op team.html, en die plek bestaat.
 */
import { test } from "node:test";
import assert from "node:assert/strict";
import { existsSync, readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = fileURLToPath(new URL("..", import.meta.url));
const lees = (pad) => readFileSync(join(ROOT, pad), "utf8");

for (const [home, team] of [["index.html", "team.html"], ["en/index.html", "en/team.html"]]) {
  test(`${home}: teamblok met een kaart per teamlid, gelinkt naar ${team}`, () => {
    const html = lees(home);
    const blok = html.match(/<section[^>]*\bid="team"[^>]*>([\s\S]*?)<\/section>/);
    assert.ok(blok, "er is een teamblok (section#team)");
    const links = [...blok[1].matchAll(/<a class="team-lid__link" href="team\.html#([a-z]+)">/g)].map((m) => m[1]);
    assert.deepEqual(links, ["sureyo", "tim", "anass", "henny"]);

    const teamHtml = lees(team);
    for (const id of links) assert.match(teamHtml, new RegExp(`<li class="team-lid[^"]*"[^>]*\\bid="${id}"`), `${team} heeft #${id}`);
    // Dezelfde namen als op de teampagina
    const namen = (s) => [...s.matchAll(/<h3>([^<]+)<\/h3>/g)].map((m) => m[1]);
    const teamLijst = teamHtml.match(/<ul class="team-lijst[^"]*">([\s\S]*?)<\/ul>/)[1];
    assert.deepEqual(namen(blok[1]), namen(teamLijst));

    // Elke afbeelding bestaat, heeft een alt-tekst en afmetingen, en laadt pas als hij in beeld komt
    const map = dirname(join(ROOT, home));
    const imgs = [...blok[1].matchAll(/<img\b[^>]*>/g)].map((m) => m[0]);
    assert.equal(imgs.length, 4);
    for (const img of imgs) {
      assert.match(img, /\balt="[^"]+"/);
      assert.match(img, /\bwidth="\d+" height="\d+"/);
      assert.match(img, /\bloading="lazy"/);
      const bronnen = [img.match(/\bsrc="([^"]+)"/)[1], ...img.match(/\bsrcset="([^"]+)"/)[1].split(",").map((d) => d.trim().split(/\s+/)[0])];
      for (const bron of bronnen) assert.ok(existsSync(join(map, bron)), `${bron} bestaat`);
    }
    assert.match(blok[1], /<a class="btn" href="team\.html">/);
  });
}
