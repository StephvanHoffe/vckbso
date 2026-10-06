/* Voorbeeldweergave: formulieren slaan niets op, pagina's buiten het voorbeeld melden dat. */
(() => {
  const balk = document.querySelector(".vb-balk");
  const melding = document.querySelector(".vb-melding");
  const inBeheer = balk && balk.dataset.rol !== "website";
  let timer;
  const toon = (tekst) => {
    if (!melding) return;
    melding.textContent = tekst;
    melding.hidden = false;
    clearTimeout(timer);
    timer = setTimeout(() => { melding.hidden = true; }, 3500);
  };

  document.addEventListener("submit", (event) => {
    if (!inBeheer) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    if (event.target.hasAttribute("data-vb-uitloggen")) {
      window.location.href = "inloggen.html";
      return;
    }
    toon("Dit is een voorbeeld: er wordt niets opgeslagen of verstuurd.");
  }, true);

  document.addEventListener("click", (event) => {
    const link = event.target.closest("a[data-vb-niet]");
    if (link) {
      event.preventDefault();
      event.stopImmediatePropagation();
      toon("Deze pagina zit niet in het voorbeeld.");
    }
  }, true);
})();
