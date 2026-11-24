/*
 * Instellingen van de rekentool op de pagina Abonnementen (Sporty).
 *
 * De rekentool rekent met het Sporty Abonnement (feedback 2.0, november 2026): vaste dagen per week, 52 weken
 * per jaar inclusief vakanties en studiedagen, uurtarief € 9,75, en per vaste dag per week gemiddeld
 * 27 uur per maand (1 dag = € 263,25, 2 dagen = € 526,50, 3 dagen = € 789,75 per maand vóór toeslag).
 * Daarom staat hier urenPerMaandPerDag in plaats van weken en uren per dag. Het incidentele abonnement
 * (€ 12 per uur, 4,5 uur per schooldag, 10,5 uur per vakantie- of studiedag) zit bewust niet in de rekentool.
 *
 * Per opvangvorm kan ook (voor een abonnement dat per week rekent):
 *   - schoolweken / vakantieweken, urenSchooldag { ma, di, wo, do, vr } en urenVakantiedag
 *     -> uren per maand = (schoolweken x uren per schooldag + vakantieweken x uren per vakantiedag) / 12 per dag.
 *
 * Sporty opent op 4 januari 2027, dus de tool rekent met de officiële tabel van 2027 (toeslag-2027.js). De
 * maximale uurprijs voor de bso is in 2027 € 10,31: het uurtarief van Sporty ligt daaronder, dus ouders
 * krijgen toeslag over het hele bedrag. Voor een nieuw jaar: maak toeslag-<jaar>.js, zet "jaar" hieronder
 * goed en vervang het script op abonnement-en-kosten.html en en/membership-and-costs.html.
 * Controleer na elke wijziging met: npm test (de test meldt ontbrekende of ongeldige gegevens).
 */
(function (root) {
  "use strict";
  root.VCKRekentoolInstellingen = {
    // Het jaar van de toeslagtabel (er moet een bestand toeslag-<jaar>.js zijn en op de pagina staan)
    jaar: 2027,

    opvangvormen: [
      {
        naam: "Sporty Abonnement (52 weken)",
        naamEngels: "Sporty membership (52 weeks)", // op de Engelse pagina
        soort: "bso",
        uurtarief: 9.75,
        urenPerMaandPerDag: 27,
      },
    ],
  };
})(typeof globalThis !== "undefined" ? globalThis : this);
