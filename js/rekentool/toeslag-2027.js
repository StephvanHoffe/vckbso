/*
 * Kinderopvangtoeslag 2027: officiële bedragen van de overheid.
 *
 * Bronnen (opgehaald op 24 november 2026):
 * - Staatsblad 2026, 308 (Besluit van 3 oktober 2026): maximale uurprijzen 2027
 *   (dagopvang € 11,60, buitenschoolse opvang € 10,31, gastouderopvang € 8,77):
 *   https://zoek.officielebekendmakingen.nl/stb-2026-308.pdf
 * - Staatsblad 2026, 391 (Besluit van 11 november 2026), Bijlage I "Kinderopvangtoeslagtabel 2027
 *   (loonpeil 2027)". Deze tabel vervangt die uit Stb. 2026, 308:
 *   https://zoek.officielebekendmakingen.nl/stb-2026-391.pdf
 * - Maximaal 230 uur per kind per maand (Dienst Toeslagen, ongewijzigd in 2027).
 * De brochure "Berekening kinderopvangtoeslag 2027" was op 24 november 2026 nog niet verschenen;
 * de rekenwijze is dezelfde als in 2026.
 *
 * Niet met de hand aanpassen. Voor een nieuw jaar: maak een nieuw bestand
 * (toeslag-2028.js) met de nieuwe tabel en draai de tests (npm test).
 *
 * Tabel: [toetsingsinkomen vanaf, tot en met (null = en hoger), percentage 1e kind, percentage 2e en volgende kind]
 * Bedragen in hele euro's, percentages in honderdsten van een procent (9600 = 96,00%).
 */
(function (root) {
  "use strict";
  const jaren = root.VCKToeslag || (root.VCKToeslag = {});
  jaren[2027] = Object.freeze({
    jaar: 2027,
    // Maximale uurprijs in centen
    maxUurprijs: Object.freeze({ dagopvang: 1160, bso: 1031, gastouder: 877 }),
    // Maximaal aantal uren per kind per maand
    maxUrenPerMaand: 230,
    tabel: Object.freeze([
      [0, 24987, 9600, 9600],
      [24988, 26650, 9600, 9600],
      [26651, 28312, 9600, 9600],
      [28313, 29978, 9600, 9600],
      [29979, 31640, 9600, 9600],
      [31641, 33306, 9600, 9600],
      [33307, 34968, 9600, 9600],
      [34969, 36628, 9600, 9600],
      [36629, 38417, 9600, 9600],
      [38418, 40203, 9600, 9600],
      [40204, 41994, 9600, 9600],
      [41995, 43781, 9600, 9600],
      [43782, 45574, 9600, 9600],
      [45575, 47364, 9600, 9600],
      [47365, 49196, 9600, 9600],
      [49197, 51029, 9600, 9600],
      [51030, 52865, 9600, 9600],
      [52866, 54698, 9600, 9600],
      [54699, 56537, 9600, 9600],
      [56538, 58369, 9600, 9600],
      [58370, 60203, 9600, 9600],
      [60204, 62038, 9600, 9600],
      [62039, 64043, 9600, 9600],
      [64044, 67975, 9600, 9600],
      [67976, 71903, 9600, 9600],
      [71904, 75835, 9560, 9600],
      [75836, 79769, 9330, 9570],
      [79770, 83698, 9100, 9540],
      [83699, 87632, 8880, 9470],
      [87633, 91562, 8630, 9420],
      [91563, 95493, 8400, 9370],
      [95494, 99425, 8180, 9300],
      [99426, 103355, 7940, 9240],
      [103356, 107292, 7720, 9200],
      [107293, 111222, 7470, 9170],
      [111223, 115152, 7240, 9100],
      [115153, 119084, 7020, 9060],
      [119085, 123091, 6780, 9010],
      [123092, 127120, 6570, 8940],
      [127121, 131145, 6360, 8890],
      [131146, 135171, 6150, 8850],
      [135172, 139195, 5930, 8820],
      [139196, 143223, 5740, 8750],
      [143224, 147250, 5550, 8690],
      [147251, 151278, 5360, 8650],
      [151279, 155300, 5160, 8590],
      [155301, 159325, 4960, 8550],
      [159326, 163355, 4760, 8480],
      [163356, 167379, 4560, 8420],
      [167380, 171405, 4360, 8320],
      [171406, 175430, 4160, 8290],
      [175431, 179458, 3960, 8210],
      [179459, 183489, 3910, 8120],
      [183490, 187511, 3910, 8060],
      [187512, 191537, 3910, 7970],
      [191538, 195560, 3910, 7920],
      [195561, 199589, 3910, 7840],
      [199590, 203618, 3910, 7770],
      [203619, 207645, 3910, 7700],
      [207646, 211670, 3910, 7600],
      [211671, 215691, 3910, 7550],
      [215692, 219722, 3910, 7480],
      [219723, 223746, 3910, 7400],
      [223747, 227773, 3910, 7330],
      [227774, 231800, 3910, 7270],
      [231801, 235824, 3910, 7190],
      [235825, 239851, 3910, 7110],
      [239852, 243876, 3910, 7060],
      [243877, null, 3910, 6970],
    ].map(Object.freeze)),
  });
})(typeof globalThis !== "undefined" ? globalThis : this);
