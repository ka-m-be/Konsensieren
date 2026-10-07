/*
 * Markdown fuer das Impressum der App (Impressum.md): derselbe Renderer wie
 * lib/Markdown.php im Werkzeug, Zeile fuer Zeile uebertragen. Beide bekommen
 * dieselben Faelle aus tools/markdown-faelle.json vorgelegt (tools/test.js und
 * tools/test.php), damit dieselbe Datei an beiden Orten gleich aussieht.
 *
 * Nur der noetige Teil der Sprache: Ueberschriften, Absaetze, Listen, Tabellen,
 * Zitate, Code, fett, kursiv, Links. Alles wird zuerst escaped, rohes HTML
 * kommt nie durch, und Links fuehren nur zu http, https, mailto oder einer
 * relativen Adresse. Was fehlt, fehlt mit Absicht: verschachtelte Listen,
 * Unterstriche als Auszeichnung, harte Zeilenumbrueche, Bilder, Fussnoten.
 */
(function (wurzel, fabrik) {
  if (typeof module === 'object' && module.exports) module.exports = fabrik();
  else wurzel.Markdown = fabrik();
}(this, function () {
  'use strict';

  /* Wie htmlspecialchars mit ENT_QUOTES in PHP, Zeichen fuer Zeichen. */
  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  /* trim() nur um ASCII-Leerraum, wie trim() in PHP. */
  function putz(s) {
    return s.replace(/^[ \t\r\n]+/, '').replace(/[ \t\r\n]+$/, '');
  }

  function html(md) {
    return bloecke(String(md).replace(/\r\n/g, '\n').split('\n')).join('\n');
  }

  /* Die erste Ueberschrift als reiner Text, fuer den Seitentitel; leer ohne. */
  function titel(md) {
    const t = String(md).match(/^#[ \t]+(.+?)[ \t]*$/m);
    return t ? putz(t[1].replace(/[*`]/g, '')) : '';
  }

  function bloecke(zeilen) {
    const aus = [];
    const n = zeilen.length;
    let i = 0;
    while (i < n) {
      const z = zeilen[i];
      if (putz(z) === '') { i++; continue; }

      if (/^```/.test(z)) {
        const code = [];
        i++;
        while (i < n && !/^```/.test(zeilen[i])) code.push(zeilen[i++]);
        i++;
        aus.push('<pre><code>' + esc(code.join('\n')) + '</code></pre>');
        continue;
      }
      let t = z.match(/^(#{1,6})[ \t]+(.*?)[ \t]*#*[ \t]*$/);
      if (t) {
        const stufe = t[1].length;
        aus.push('<h' + stufe + '>' + inline(t[2]) + '</h' + stufe + '>');
        i++;
        continue;
      }
      if (/^ {0,3}([-*_])( *\1){2,} *$/.test(z)) {
        aus.push('<hr>');
        i++;
        continue;
      }
      if (/^ {0,3}>/.test(z)) {
        const innen = [];
        while (i < n && (t = zeilen[i].match(/^ {0,3}> ?(.*)$/))) { innen.push(t[1]); i++; }
        aus.push('<blockquote>\n' + bloecke(innen).join('\n') + '\n</blockquote>');
        continue;
      }
      if (tabellenAnfang(zeilen, i)) {
        const richtung = ausrichtung(zeilen[i + 1]);
        let h = '<div class="tabellenrahmen"><table>\n<thead>\n'
          + tabellenzeile(z, 'th', richtung) + '\n</thead>\n<tbody>';
        i += 2;
        while (i < n && zeilen[i].indexOf('|') >= 0 && putz(zeilen[i]) !== '') {
          h += '\n' + tabellenzeile(zeilen[i], 'td', richtung);
          i++;
        }
        aus.push(h + '\n</tbody>\n</table></div>');
        continue;
      }
      t = z.match(/^ {0,3}([-*+]|\d+\.) +/);
      if (t) {
        const geordnet = t[1].length > 1;
        const muster = geordnet ? /^ {0,3}\d+\. +(.*)$/ : /^ {0,3}[-*+] +(.*)$/;
        const punkte = [];
        while (i < n && (t = zeilen[i].match(muster))) {
          let text = t[1];
          i++;
          // Folgezeilen gehoeren zum Punkt, bis ein neuer Punkt, eine Leerzeile
          // oder ein anderer Block beginnt.
          while (i < n && putz(zeilen[i]) !== '' && !muster.test(zeilen[i]) && !blockAnfang(zeilen, i)) {
            text += '\n' + putz(zeilen[i]);
            i++;
          }
          punkte.push('<li>' + inline(text) + '</li>');
        }
        const tag = geordnet ? 'ol' : 'ul';
        aus.push('<' + tag + '>\n' + punkte.join('\n') + '\n</' + tag + '>');
        continue;
      }
      let text = putz(z);
      i++;
      while (i < n && putz(zeilen[i]) !== '' && !blockAnfang(zeilen, i)) {
        text += '\n' + putz(zeilen[i]);
        i++;
      }
      aus.push('<p>' + inline(text) + '</p>');
    }
    return aus;
  }

  /* Beginnt in Zeile i etwas anderes als Fliesstext? Dann endet der Absatz davor. */
  function blockAnfang(zeilen, i) {
    const z = zeilen[i];
    return /^(```|#{1,6}[ \t]| {0,3}>| {0,3}([-*+]|\d+\.) +| {0,3}([-*_])( *\3){2,} *$)/.test(z)
      || tabellenAnfang(zeilen, i);
  }

  function tabellenAnfang(zeilen, i) {
    return zeilen[i].indexOf('|') >= 0 && i + 1 < zeilen.length && istTrennzeile(zeilen[i + 1]);
  }

  function istTrennzeile(z) {
    if (z.indexOf('|') < 0 || z.indexOf('-') < 0) return false;
    return zellen(z).every(function (c) { return /^:?-+:?$/.test(c); });
  }

  function zellen(z) {
    z = putz(z);
    if (z !== '' && z[0] === '|') z = z.slice(1);
    if (z !== '' && z[z.length - 1] === '|') z = z.slice(0, -1);
    return z.split('|').map(putz);
  }

  /* Je Spalte '', 'mitte' oder 'rechts'. */
  function ausrichtung(trennzeile) {
    return zellen(trennzeile).map(function (c) {
      const links = c[0] === ':';
      const rechts = c[c.length - 1] === ':';
      return links && rechts ? 'mitte' : (rechts ? 'rechts' : '');
    });
  }

  function tabellenzeile(z, tag, richtung) {
    return '<tr>' + zellen(z).map(function (c, k) {
      const klasse = richtung[k] || '';
      return '<' + tag + (klasse !== '' ? ' class="' + klasse + '"' : '') + '>' + inline(c) + '</' + tag + '>';
    }).join('') + '</tr>';
  }

  /* Auszeichnung innerhalb eines Blocks. Code zuerst, damit darin nichts als
     Auszeichnung gilt: Jede Code-Spanne wird durch einen Platzhalter ersetzt, der
     Rest als Ganzes ausgezeichnet, dann kommt der Code zurueck. So darf Fett oder
     Kursiv ueber eine Code-Spanne hinwegreichen. */
  function inline(s) {
    const teile = s.split('`');
    if (teile.length % 2 === 0) {
      // Ein einzelner Backtick ohne Partner bleibt ein Zeichen.
      const letzte = teile.pop();
      teile[teile.length - 1] += '`' + letzte;
    }
    const codes = [];
    let text = '';
    teile.forEach(function (t, k) {
      if (k % 2 === 1) {
        codes.push('<code>' + esc(t) + '</code>');
        text += '\x00' + (codes.length - 1) + '\x00';
      } else {
        text += t;
      }
    });
    return textAus(text).replace(/\x00(\d+)\x00/g, function (ganz, nr) { return codes[Number(nr)]; });
  }

  function textAus(t) {
    let s = esc(t);
    s = s.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (ganz, wort, ziel) {
      return linkErlaubt(ziel) ? '<a href="' + ziel + '">' + wort + '</a>' : ganz;
    });
    s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    // Auch ueber den Zeilenumbruch, denn Absaetze sind umbrochen.
    s = s.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    return s;
  }

  /* http, https, mailto oder relativ; jede andere Angabe mit Doppelpunkt bleibt Text. */
  function linkErlaubt(ziel) {
    return /^(https?:\/\/|mailto:)/i.test(ziel) || ziel.indexOf(':') < 0;
  }

  return { html: html, titel: titel };
}));
