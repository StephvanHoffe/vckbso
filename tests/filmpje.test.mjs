/**
 * Het filmpje "Sporty in 30 seconden" op de homepage (NL en EN): speelt pas als je op afspelen drukt, laadt niets
 * vooraf, en heeft ondertiteling in beide talen (WCAG 1.2.2), met de taal van de pagina als standaard.
 */
import { test } from "node:test";
import assert from "node:assert/strict";
import { existsSync, readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = fileURLToPath(new URL("..", import.meta.url));

for (const [pagina, taal] of [["index.html", "nl"], ["en/index.html", "en"]]) {
  test(`${pagina}: het filmpje speelt op verzoek, met ondertiteling in het ${taal === "nl" ? "Nederlands" : "Engels"}`, () => {
    const html = readFileSync(join(ROOT, pagina), "utf8");
    const video = html.match(/<video\b[^>]*>[\s\S]*?<\/video>/);
    assert.ok(video, "er staat een filmpje op de homepage");
    const open = video[0].match(/<video\b[^>]*>/)[0];
    assert.match(open, /\bcontrols\b/);
    assert.match(open, /\bpreload="none"/);
    assert.doesNotMatch(open, /\bautoplay\b/, "speelt niet vanzelf");
    assert.match(open, /\baria-label="[^"]+"/);

    const map = dirname(join(ROOT, pagina));
    const bestanden = [...video[0].matchAll(/\b(?:src|poster)="([^"]+)"/g), ...open.matchAll(/\bposter="([^"]+)"/g)].map((m) => m[1]);
    for (const b of bestanden) assert.ok(existsSync(join(map, b)), `${b} bestaat`);

    const sporen = [...video[0].matchAll(/<track\b[^>]*>/g)].map((m) => m[0]);
    assert.ok(sporen.length >= 2, "ondertiteling in het Nederlands en het Engels");
    for (const spoor of sporen) {
      assert.match(spoor, /kind="captions"/);
      const vtt = readFileSync(join(map, spoor.match(/src="([^"]+)"/)[1]), "utf8");
      assert.match(vtt, /^WEBVTT\n/);
      assert.match(vtt, /\d\d:\d\d:\d\d\.\d{3} --> \d\d:\d\d:\d\d\.\d{3}/);
    }
    const standaard = sporen.filter((s) => /\bdefault\b/.test(s));
    assert.equal(standaard.length, 1);
    assert.match(standaard[0], new RegExp(`srclang="${taal}"`));
  });
}
