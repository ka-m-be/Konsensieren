/*
 * Druckvorlage: Karten fuer Vorleseprogramme beschriften und Drucken ausloesen.
 * Die Texte setzt rahmen.js ein. Der Umschalter zwischen Farbe und Schwarz-Weiss
 * braucht kein Skript, er haengt an zwei Radioknoepfen.
 */
(function () {
  'use strict';

  document.querySelectorAll('.kartenbild').forEach(function (bild) {
    bild.setAttribute('aria-label', Texte.t('karten.karte_aria', { w: bild.dataset.wert }));
  });

  const drucken = document.getElementById('drucken');
  if (typeof window.print === 'function') {
    drucken.addEventListener('click', function () { window.print(); });
  } else {
    drucken.hidden = true;
  }
}());
