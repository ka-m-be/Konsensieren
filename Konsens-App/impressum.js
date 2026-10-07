/*
 * Das Impressum der App: Impressum.md laden und mit markdown.js rendern. Eine
 * Markdown-Datei statt einer HTML-Seite, damit die betreibende Person sie
 * anpassen kann, ohne HTML zu schreiben - wie Impressum.md im Werkzeug. Die
 * einzige Stelle in der App, die innerHTML setzt; was hineinkommt, hat
 * markdown.js vollstaendig escaped.
 */
(function () {
  'use strict';

  const ziel = document.getElementById('impressum');
  const t = globalThis.Texte.t;

  // Erst das ausgefuellte Impressum, sonst die Vorlage mit Platzhaltern - wie im
  // Werkzeug. Die Vorlage liegt immer im Vorrat, das Impressum nur, wenn es beim
  // Anlegen des Vorrats da war (sw.js). Der eingebaute Server von PHP liefert
  // fuer eine fehlende Datei die Startseite des Werkzeugs mit 200; die ist kein
  // Impressum und zaehlt wie ein Fehlen.
  function lade(datei) {
    return fetch(datei).then(function (antwort) {
      if (!antwort.ok) return null;
      return antwort.text().then(function (text) { return /^\s*<(!doctype|html)/i.test(text) ? null : text; });
    });
  }

  lade('Impressum.md')
    .then(function (md) { return md !== null ? md : lade('Impressum.example.md'); })
    .then(function (md) {
      if (md === null) throw new Error('404');
      ziel.innerHTML = globalThis.Markdown.html(md);
      const titel = globalThis.Markdown.titel(md);
      if (titel !== '') document.title = titel + ' · ' + t('suite.name');
    })
    .catch(function (e) {
      const p = document.createElement('p');
      p.className = 'meldung fehler';
      p.textContent = t('impressum.fehler', { grund: e.message });
      ziel.replaceChildren(p);
    });
}());
