/*
 * Rahmen jeder Seite der App: Texte einsetzen, die Fassung in den Fuss
 * schreiben und den Vorrat fuer den Betrieb ohne Netz anmelden. Die
 * Fassungsnummer steht hier und in sw.js; tools/test.js prueft, dass beide zur
 * obersten im Changelog passen.
 */
(function (wurzel, fabrik) {
  if (typeof module === 'object' && module.exports) module.exports = fabrik();
  else wurzel.Rahmen = fabrik();
}(this, function () {
  'use strict';

  const FASSUNG = '0.2.3';

  if (typeof document !== 'undefined') {
    globalThis.Texte.fuellen(document);
    const fassung = document.getElementById('fassung');
    if (fassung) fassung.textContent = globalThis.Texte.t('fuss.fassung', { v: FASSUNG });
    // Den Vorrat fuer den Betrieb ohne Netz (sw.js) erlauben Browser nur ueber
    // https oder auf dem eigenen Rechner. Ueber http im WLAN fehlt
    // navigator.serviceWorker, und die App braucht das Netz wie bisher.
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('sw.js', { updateViaCache: 'none' }).catch(function () {});
    }
    // Das Menue oben, wie im Werkzeug (Spezifikation 19): ein details-Element mit
    // dem Stapel-Knopf. Es geht zu bei einem Klick daneben und mit Escape. Die
    // Seite, auf der man ist, markiert erst das Skript, denn Kopf und Fuss stehen
    // auf allen Seiten wortgleich im HTML (tools/test.js).
    document.querySelectorAll('.menue').forEach(function (menue) {
      menue.querySelectorAll('.menue-liste a').forEach(function (a) {
        if (a.pathname === location.pathname) a.setAttribute('aria-current', 'page');
      });
      document.addEventListener('click', function (e) {
        if (menue.open && !menue.contains(e.target)) menue.open = false;
      });
      document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || !menue.open) return;
        menue.open = false;
        menue.querySelector('summary').focus();
      });
    });
  }

  return { FASSUNG: FASSUNG };
}));
