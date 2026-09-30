/*
 * Kinderopvangtoeslag 2026: officiële bedragen van de overheid.
 *
 * Bronnen (opgehaald op 30 september 2026):
 * - Rijksoverheid, "Bedragen kinderopvangtoeslag 2026":
 *   https://www.rijksoverheid.nl/onderwerpen/kinderopvangtoeslag/bedragen-kinderopvangtoeslag-2026
 * - Dienst Toeslagen, brochure "Berekening kinderopvangtoeslag 2026" (TG 080 - 1Z61FD):
 *   https://download.belastingdienst.nl/toeslagen/docs/berekening_kinderopvangtoeslag_tg0801z61fd.pdf
 *
 * Niet met de hand aanpassen. Voor een nieuw jaar: maak een nieuw bestand
 * (toeslag-2027.js) met de nieuwe tabel en draai de tests (npm test).
 *
 * Tabel: [toetsingsinkomen vanaf, tot en met (null = en hoger), percentage 1e kind, percentage 2e en volgende kind]
 * Bedragen in hele euro's, percentages in honderdsten van een procent (9600 = 96,00%).
 */
(function (root) {
  "use strict";
  const jaren = root.VCKToeslag || (root.VCKToeslag = {});
  jaren[2026] = Object.freeze({
    jaar: 2026,
    // Maximale uurprijs in centen
    maxUurprijs: Object.freeze({ dagopvang: 1123, bso: 998, gastouder: 849 }),
    // Maximaal aantal uren per kind per maand
    maxUrenPerMaand: 230,
    tabel: Object.freeze([
      [0, 24149, 9600, 9600],
      [24150, 25756, 9600, 9600],
      [25757, 27363, 9600, 9600],
      [27364, 28973, 9600, 9600],
      [28974, 30579, 9600, 9600],
      [30580, 32189, 9600, 9600],
      [32190, 33795, 9600, 9600],
      [33796, 35400, 9600, 9600],
      [35401, 37129, 9600, 9600],
      [37130, 38855, 9600, 9600],
      [38856, 40586, 9600, 9600],
      [40587, 42313, 9600, 9600],
      [42314, 44046, 9600, 9600],
      [44047, 45776, 9600, 9600],
      [45777, 47546, 9600, 9600],
      [47547, 49318, 9600, 9600],
      [49319, 51092, 9600, 9600],
      [51093, 52864, 9600, 9600],
      [52865, 54641, 9600, 9600],
      [54642, 56412, 9600, 9600],
      [56413, 58184, 9550, 9560],
      [58185, 59957, 9480, 9560],
      [59958, 61895, 9390, 9560],
      [61896, 65695, 9240, 9560],
      [65696, 69492, 9160, 9520],
      [69493, 73292, 9050, 9460],
      [73293, 77094, 8820, 9420],
      [77095, 80891, 8590, 9390],
      [80892, 84693, 8370, 9320],
      [84694, 88491, 8120, 9270],
      [88492, 92291, 7890, 9220],
      [92292, 96091, 7670, 9150],
      [96092, 99889, 7430, 9090],
      [99890, 103694, 7210, 9050],
      [103695, 107492, 6960, 9020],
      [107493, 111290, 6730, 8950],
      [111291, 115090, 6510, 8910],
      [115091, 118963, 6270, 8860],
      [118964, 122857, 6060, 8790],
      [122858, 126747, 5850, 8740],
      [126748, 130638, 5640, 8700],
      [130639, 134527, 5420, 8670],
      [134528, 138420, 5230, 8600],
      [138421, 142312, 5040, 8540],
      [142313, 146205, 4850, 8500],
      [146206, 150092, 4650, 8440],
      [150093, 153982, 4450, 8400],
      [153983, 157877, 4250, 8330],
      [157878, 161766, 4050, 8270],
      [161767, 165657, 3850, 8170],
      [165658, 169547, 3650, 8140],
      [169548, 173440, 3650, 8060],
      [173441, 177335, 3650, 7970],
      [177336, 181223, 3650, 7910],
      [181224, 185114, 3650, 7820],
      [185115, 189002, 3650, 7770],
      [189003, 192896, 3650, 7690],
      [192897, 196789, 3650, 7620],
      [196790, 200681, 3650, 7550],
      [200682, 204571, 3650, 7450],
      [204572, 208458, 3650, 7400],
      [208459, 212353, 3650, 7330],
      [212354, 216242, 3650, 7250],
      [216243, 220134, 3650, 7180],
      [220135, 224026, 3650, 7120],
      [224027, 227915, 3650, 7040],
      [227916, 231807, 3650, 6960],
      [231808, 235697, 3650, 6910],
      [235698, null, 3650, 6820],
    ].map(Object.freeze)),
  });
})(typeof globalThis !== "undefined" ? globalThis : this);
