/*
 * Gemeinsame Testfaelle pruefen: dieselben Faelle aus tools/faelle.json, die
 * tools/test.php im Hauptprojekt durch das Werkzeug schickt. Genutzt von
 * tools/test.js unter Node und von pruefen.html auf dem Handy, ohne Rechner.
 */
(function (wurzel, fabrik) {
  if (typeof module === 'object' && module.exports) module.exports = fabrik();
  else wurzel.Pruefen = fabrik();
}(this, function () {
  'use strict';

  const OBEN = ['wertende', 'noetig', 'messlatte', 'sieger', 'reihenfolge', 'zeilen'];
  const FELDER = ['summe', 'mittel', 'bewertet', 'offen', 'minimal', 'niedrig', 'verteilung',
    'belastbar', 'kik', 'legitimiert', 'gleichauf', 'rang'];

  /* Gleitkommazahlen auf ein Milliardstel, wie das Werkzeug den Abstand zur
     Passivloesung vergleicht; faelle.json schreibt Brueche ausgerechnet aus. */
  function gleich(ist, soll) {
    if (Array.isArray(soll)) {
      return Array.isArray(ist) && ist.length === soll.length
        && soll.every(function (s, i) { return gleich(ist[i], s); });
    }
    if (typeof soll === 'number' && typeof ist === 'number') return Math.abs(ist - soll) < 1e-9;
    return ist === soll;
  }

  /* Jeder Zettel ist eine Person; Zeile r bekommt von jedem Zettel seinen
     Wert an Stelle r, ein kuerzerer Zettel hat sich dort enthalten. */
  function eingabe(fall) {
    const n = fall.zettel.reduce(function (m, z) { return Math.max(m, z.length); }, 0);
    const zeilen = [];
    for (let r = 0; r < n; r++) {
      zeilen.push(fall.zettel.map(function (z) { return r < z.length ? z[r] : null; }));
    }
    return { anwesende: fall.anwesende, passiv: fall.passiv, zeilen: zeilen };
  }

  /** @return {string[]} Abweichungen von der Erwartung, leer wenn alles stimmt */
  function fall(Zaehlwerk, f) {
    const fehler = [];
    const soll = f.erwartet || {};
    const melde = function (was, ist, erwartet) {
      if (!gleich(ist, erwartet)) {
        fehler.push(was + ': erwartet ' + JSON.stringify(erwartet) + ', bekommen ' + JSON.stringify(ist));
      }
    };
    // Ein Tippfehler in faelle.json soll auffallen, statt still nichts zu pruefen.
    Object.keys(soll).forEach(function (k) {
      if (OBEN.indexOf(k) < 0) fehler.push('unbekannte Angabe: ' + k);
    });
    let erg;
    try {
      erg = Zaehlwerk.rechnen(eingabe(f));
    } catch (e) {
      return ['Ausnahme: ' + e.message];
    }
    ['wertende', 'noetig', 'messlatte'].forEach(function (k) {
      if (k in soll) melde(k, erg[k], soll[k]);
    });
    if ('sieger' in soll) melde('sieger', erg.sieger === null ? null : String(erg.sieger), soll.sieger);
    if ('reihenfolge' in soll) {
      melde('reihenfolge', erg.zeilen.map(function (z) { return String(z.nr); }), soll.reihenfolge);
    }
    Object.keys(soll.zeilen || {}).forEach(function (nr) {
      const z = erg.zeilen.find(function (x) { return String(x.nr) === nr; });
      if (!z) { fehler.push('Zeile ' + nr + ' fehlt'); return; }
      Object.keys(soll.zeilen[nr]).forEach(function (k) {
        if (FELDER.indexOf(k) < 0) { fehler.push('Zeile ' + nr + ': unbekannte Angabe ' + k); return; }
        melde('Zeile ' + nr + ', ' + k, z[k], soll.zeilen[nr][k]);
      });
    });
    return fehler;
  }

  function imBrowser() {
    const ziel = document.getElementById('pruefergebnis');
    if (!ziel) return;
    const g = globalThis;
    const t = g.Texte.t;
    fetch('tools/faelle.json', { cache: 'no-store' })
      .then(function (antwort) {
        if (!antwort.ok) throw new Error('HTTP ' + antwort.status);
        return antwort.json();
      })
      .then(function (daten) {
        let stimmen = 0;
        const liste = document.createElement('ol');
        liste.className = 'pruefliste';
        daten.faelle.forEach(function (f) {
          const fehler = fall(g.Zaehlwerk, f);
          if (fehler.length === 0) stimmen++;
          const li = document.createElement('li');
          li.className = fehler.length === 0 ? 'stimmt' : 'falsch';
          li.textContent = (fehler.length === 0 ? '✓ ' : '✗ ') + f.name;
          fehler.forEach(function (text) {
            const p = document.createElement('p');
            p.textContent = text;
            li.appendChild(p);
          });
          liste.appendChild(li);
        });
        const summe = document.createElement('p');
        summe.className = 'meldung' + (stimmen === daten.faelle.length ? '' : ' fehler');
        summe.textContent = t('pruefen.summe', { n: stimmen, g: daten.faelle.length });
        ziel.replaceChildren(summe, liste);
      })
      .catch(function (e) {
        ziel.textContent = t('pruefen.nicht_geladen', { grund: e.message });
      });
  }
  if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', imBrowser);

  return { gleich: gleich, eingabe: eingabe, fall: fall };
}));
