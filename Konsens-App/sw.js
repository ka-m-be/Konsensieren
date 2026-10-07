/*
 * Service Worker der Konsens-App: Beim ersten Besuch legt er alle Dateien in
 * einen Vorrat und liefert sie danach von dort. So startet die App vom
 * Home-Bildschirm auch ohne Netz. Die Fassung im Namen des Vorrats ist dieselbe
 * wie in rahmen.js (tools/test.js prueft das): Aendert sie sich, bemerkt der
 * Browser eine neue sw.js, holt alles neu und wirft den alten Vorrat weg.
 * Zuerst aus dem Vorrat, dann erst aus dem Netz: Im Sitzungsraum soll die App
 * sofort da sein, auch wenn das WLAN haengt.
 */
(function (wurzel, fabrik) {
  if (typeof module === 'object' && module.exports) module.exports = fabrik();
  else fabrik();
}(this, function () {
  'use strict';

  const FASSUNG = '0.2.3';
  const VORRAT = 'konsens-app-' + FASSUNG;
  const DATEIEN = [
    './', 'index.html', 'anleitung.html', 'karten.html', 'pruefen.html', 'impressum.html',
    'app.css', 'karten.css',
    'texte.js', 'rahmen.js', 'zaehlwerk.js', 'runde.js', 'teilen.js', 'app.js', 'karten.js', 'pruefen.js',
    'markdown.js', 'impressum.js', 'Impressum.example.md',
    'tools/faelle.json', 'manifest.webmanifest',
    'symbol.svg', 'symbol-180.png', 'symbol-192.png', 'symbol-512.png'
  ];
  // Gibt es nicht auf jeder Installation; fehlt eine, darf der Vorrat nicht
  // scheitern. Das ausgefuellte Impressum: ohne es zeigt die App die Vorlage.
  const WAHLWEISE = ['Impressum.md'];

  if (typeof self !== 'undefined' && typeof self.addEventListener === 'function' && typeof caches !== 'undefined') {
    self.addEventListener('install', function (ereignis) {
      ereignis.waitUntil(caches.open(VORRAT)
        .then(function (vorrat) {
          return vorrat.addAll(DATEIEN).then(function () {
            return Promise.all(WAHLWEISE.map(function (d) { return vorrat.add(d).catch(function () {}); }));
          });
        })
        .then(function () { return self.skipWaiting(); }));
    });
    self.addEventListener('activate', function (ereignis) {
      ereignis.waitUntil(caches.keys()
        .then(function (namen) {
          return Promise.all(namen.filter(function (n) { return n.indexOf('konsens-app-') === 0 && n !== VORRAT; })
            .map(function (n) { return caches.delete(n); }));
        })
        .then(function () { return self.clients.claim(); }));
    });
    self.addEventListener('fetch', function (ereignis) {
      const anfrage = ereignis.request;
      if (anfrage.method !== 'GET' || new URL(anfrage.url).origin !== self.location.origin) return;
      ereignis.respondWith(caches.open(VORRAT)
        .then(function (vorrat) { return vorrat.match(anfrage, { ignoreSearch: true }); })
        .then(function (treffer) { return treffer || fetch(anfrage); }));
    });
  }

  return { FASSUNG: FASSUNG, VORRAT: VORRAT, DATEIEN: DATEIEN, WAHLWEISE: WAHLWEISE };
}));
