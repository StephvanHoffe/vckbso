/*
 * Instellingen van de rekentool op de pagina Abonnementen (Sporty).
 *
 * Sporty werkt met één abonnement (teksten van de klant, november 2026): vaste dagen per week, 52 weken
 * per jaar inclusief vakanties en studiedagen, uurtarief € 10,31, en per vaste dag per week gemiddeld
 * 27,5 uur per maand. Daarom staat hier urenPerMaandPerDag in plaats van weken en uren per dag.
 *
 * Per opvangvorm kan ook (voor een abonnement dat per week rekent):
 *   - schoolweken / vakantieweken, urenSchooldag { ma, di, wo, do, vr } en urenVakantiedag
 *     -> uren per maand = (schoolweken x uren per schooldag + vakantieweken x uren per vakantiedag) / 12 per dag.
 *
 * TODO: Sporty opent in januari 2027. Zet "jaar" op 2027 met een bestand toeslag-2027.js zodra de
 * officiële tabel en maximale uurprijzen van 2027 bekend zijn (in 2026 is de maximale uurprijs voor
 * BSO € 9,98; volgens Sporty is die in 2027 € 10,31). Tot die tijd rekent de tool met 2026.
 * Controleer na elke wijziging met: npm test (de test meldt ontbrekende of ongeldige gegevens).
 */
(function (root) {
  "use strict";
  root.VCKRekentoolInstellingen = {
    // Het jaar van de toeslagtabel (er moet een bestand toeslag-<jaar>.js zijn en op de pagina staan)
    jaar: 2026,

    opvangvormen: [
      {
        naam: "Sporty Abonnement (52 weken)",
        soort: "bso",
        uurtarief: 10.31,
        urenPerMaandPerDag: 27.5,
      },
    ],
  };
})(typeof globalThis !== "undefined" ? globalThis : this);
