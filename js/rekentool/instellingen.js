/*
 * Instellingen van de rekentool op de prijzenpagina (BSO VCK).
 *
 * TODO (BSO VCK): vul hieronder de opvangvormen in. Per opvangvorm:
 *   - naam:            zoals ouders hem kennen, bijvoorbeeld "BSO 40 weken (alleen schoolweken)"
 *   - soort:           "bso" (buitenschoolse opvang); voor de maximale uurprijs van de toeslag
 *   - uurtarief:       het uurtarief in euro, met een punt: 10.25 is € 10,25
 *   - schoolweken:     aantal weken per jaar met opvang na school (vaak 40)
 *   - vakantieweken:   aantal weken per jaar met opvang in de schoolvakanties (0 als er geen vakantieopvang bij zit)
 *   - urenSchooldag:   uren opvang per middag, per dag (woensdag is vaak langer), bijvoorbeeld 3.5
 *   - urenVakantiedag: uren opvang per vakantiedag, bijvoorbeeld 10
 *
 * De rekentool rekent de uren per maand uit zoals in een contract:
 * (schoolweken x uren per schooldag + vakantieweken x uren per vakantiedag) / 12, per gekozen dag,
 * afgerond op twee decimalen. Rekent BSO VCK de uren per maand anders uit (bijvoorbeeld afgerond
 * op hele uren)? Pas dat dan aan in berekening.js (functie urenPerMaand) en in de tests.
 *
 * Zolang de lijst leeg is, vult de bezoeker zelf een uurprijs en het aantal uren per maand in.
 * Controleer na elke wijziging met: npm test (de test meldt ontbrekende of ongeldige gegevens).
 */
(function (root) {
  "use strict";
  root.VCKRekentoolInstellingen = {
    // Het jaar van de toeslagtabel (er moet een bestand toeslag-<jaar>.js zijn en op de pagina staan)
    jaar: 2026,

    opvangvormen: [
      // Voorbeeld: haal de // weg en vul de echte gegevens in.
      // {
      //   naam: "BSO 40 weken (alleen schoolweken)",
      //   soort: "bso",
      //   uurtarief: 0,
      //   schoolweken: 40,
      //   vakantieweken: 0,
      //   urenSchooldag: { ma: 0, di: 0, wo: 0, do: 0, vr: 0 },
      //   urenVakantiedag: 0,
      // },
      // {
      //   naam: "BSO 52 weken (met vakantieopvang)",
      //   soort: "bso",
      //   uurtarief: 0,
      //   schoolweken: 40,
      //   vakantieweken: 12,
      //   urenSchooldag: { ma: 0, di: 0, wo: 0, do: 0, vr: 0 },
      //   urenVakantiedag: 0,
      // },
    ],
  };
})(typeof globalThis !== "undefined" ? globalThis : this);
