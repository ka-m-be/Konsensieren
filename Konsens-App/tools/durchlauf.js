#!/usr/bin/env node
/*
 * Durchlauf durch die Konsens-App in Chrome ohne Fenster:
 *   node Konsens-App/tools/durchlauf.js
 *
 * Klickt, was eine Moderation klickt, und prueft, was danach auf dem Schirm
 * steht. tools/test.js prueft Rechnung und Runde; was nur in der Anzeige steckt,
 * sieht es nicht - so die Bestaetigung, die in 0.1.0 nach "Sitzung beenden"
 * stehen blieb. Dafuer gibt es diesen Lauf.
 *
 * Er bringt einen kleinen Server mit, der nur die Dateien der App ausliefert;
 * Werkzeug und data/ bleiben unberuehrt. Zum Schluss schaltet er ihn ab und
 * prueft, dass die App aus dem Vorrat des Service Workers startet. Chrome steht in der Umgebungsvariablen
 * CHROME, sonst am ueblichen Ort unter macOS; fehlt es, endet der Lauf mit einem
 * Hinweis und Code 2. Node 20 kennt WebSocket nur mit --experimental-websocket,
 * das Skript startet sich dann selbst damit neu.
 */
'use strict';

if (typeof WebSocket === 'undefined') {
  const neu = require('child_process').spawnSync(process.execPath,
    ['--experimental-websocket', '--no-warnings', __filename].concat(process.argv.slice(2)), { stdio: 'inherit' });
  process.exit(neu.status === null ? 1 : neu.status);
}

const http = require('http');
const fs = require('fs');
const os = require('os');
const pfad = require('path');
const { spawn } = require('child_process');

const APP = pfad.resolve(__dirname, '..');
const CHROME = process.env.CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const ARTEN = {
  '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8', '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml', '.png': 'image/png', '.webmanifest': 'application/manifest+json',
  '.md': 'text/markdown; charset=utf-8'
};
const warte = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };
let gut = 0, schlecht = 0;

function abschnitt(name) { console.log('\n' + name); }

function melde(bericht) {
  (bericht.log || []).forEach(function (e) {
    if (e.ok) { gut++; return; }
    schlecht++;
    console.log('  FEHLER  ' + e.was);
    console.log('          erwartet: ' + JSON.stringify(e.soll));
    console.log('          bekommen: ' + JSON.stringify(e.ist));
  });
  (bericht.fehler || []).forEach(function (f) {
    schlecht++;
    console.log('  FEHLER  ' + f);
  });
}

/* Liefert die App unter /Konsens-App/ aus wie der Server. Wie der eingebaute
   Server von PHP leitet er /Konsens-App ohne Schraegstrich nicht um; so wird der
   Hinweis fuer diesen Fall mitgeprueft. */
function starteServer() {
  const verbindungen = new Set();
  let gestoppt = false;
  const server = http.createServer(function (anfrage, antwort) {
    let weg = decodeURIComponent(anfrage.url.split('?')[0]);
    if (weg === '/Konsens-App') weg = '/Konsens-App/index.html';
    if (weg.endsWith('/')) weg += 'index.html';
    const datei = pfad.join(APP, weg.replace(/^\/Konsens-App\//, ''));
    const erlaubt = weg.startsWith('/Konsens-App/') && datei.startsWith(APP + pfad.sep);
    if (!erlaubt || !fs.existsSync(datei) || !fs.statSync(datei).isFile()) {
      antwort.writeHead(404);
      antwort.end();
      return;
    }
    antwort.writeHead(200, { 'Content-Type': ARTEN[pfad.extname(datei)] || 'application/octet-stream' });
    fs.createReadStream(datei).pipe(antwort);
  });
  // Fuer den Teil ohne Netz: Der Server geht aus, samt offener Verbindungen.
  server.on('connection', function (s) {
    verbindungen.add(s);
    s.on('close', function () { verbindungen.delete(s); });
  });
  server.stoppe = function () {
    if (gestoppt) return Promise.resolve();
    gestoppt = true;
    return new Promise(function (fertig) {
      server.close(function () { fertig(); });
      verbindungen.forEach(function (s) { s.destroy(); });
    });
  };
  return new Promise(function (fertig) {
    server.listen(0, '127.0.0.1', function () { fertig(server); });
  });
}

/* Chrome ohne Fenster, gesteuert ueber das DevTools-Protokoll, mit eigenem,
   frischem Profil: So beginnt jeder Lauf mit leerem Speicher. */
async function starteChrome() {
  if (!fs.existsSync(CHROME)) return null;
  const profil = fs.mkdtempSync(pfad.join(os.tmpdir(), 'konsens-durchlauf-'));
  const port = 9400 + Math.floor(Math.random() * 500);
  const prozess = spawn(CHROME, ['--headless=new', '--disable-gpu', '--no-first-run',
    '--no-default-browser-check', '--remote-debugging-port=' + port, '--remote-allow-origins=*',
    '--user-data-dir=' + profil, 'about:blank'], { stdio: 'ignore' });
  let ziel = null;
  for (let i = 0; i < 100 && !ziel; i++) {
    await warte(200);
    try {
      const liste = await (await fetch('http://127.0.0.1:' + port + '/json/list')).json();
      ziel = liste.find(function (z) { return z.type === 'page'; }) || null;
    } catch (e) { /* Chrome startet noch */ }
  }
  if (!ziel) { prozess.kill(); return null; }
  const ws = new WebSocket(ziel.webSocketDebuggerUrl);
  await new Promise(function (r) { ws.addEventListener('open', r); });
  let nr = 0, geladen = null;
  const offen = new Map();
  const konsole = [];
  ws.addEventListener('message', function (ereignis) {
    const m = JSON.parse(ereignis.data);
    if (m.id && offen.has(m.id)) { offen.get(m.id)(m); offen.delete(m.id); return; }
    if (m.method === 'Page.loadEventFired' && geladen) { geladen(); geladen = null; }
    if (m.method === 'Runtime.exceptionThrown') {
      const d = m.params.exceptionDetails;
      konsole.push(d.exception && d.exception.description ? d.exception.description : d.text);
    }
    // Brummen ohne echten Fingertipp sperrt Chrome; das ist kein Fehler der App.
    // Ebenso wenig die 404 fuer Dateien direkt unter der Wurzel: Die kommen nur
    // von der Adresse ohne Schraegstrich, und das Symbol trudelt dort erst nach
    // dem Zuruecksetzen der Konsole ein.
    if (m.method === 'Log.entryAdded' && m.params.entry.level === 'error'
        && !/navigator\.vibrate|favicon/.test(m.params.entry.text + ' ' + (m.params.entry.url || ''))
        && !/^https?:\/\/[^/]+\/[^/]*$/.test(m.params.entry.url || '')) {
      konsole.push(m.params.entry.text + ' ' + (m.params.entry.url || ''));
    }
  });
  const sende = function (methode, werte) {
    return new Promise(function (r) {
      nr++;
      offen.set(nr, r);
      ws.send(JSON.stringify({ id: nr, method: methode, params: werte || {} }));
    });
  };
  await sende('Page.enable');
  await sende('Runtime.enable');
  await sende('Log.enable');
  await sende('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
  return {
    konsole: konsole,
    oeffne: async function (url) {
      const fertig = new Promise(function (r) { geladen = r; });
      const antwort = await sende('Page.navigate', { url: url });
      if (antwort.result && antwort.result.errorText) throw new Error(url + ': ' + antwort.result.errorText);
      let uhr = null;
      const frist = new Promise(function (_, weg) {
        uhr = setTimeout(function () { weg(new Error('Seite laedt nicht: ' + url)); }, 10000);
      });
      try { await Promise.race([fertig, frist]); } finally { clearTimeout(uhr); }
    },
    /* Was Chrome selbst zum Home-Bildschirm sagt: Fehler im Manifest und
       Gruende, warum sich die App nicht installieren liesse. */
    manifest: async function () {
      const a = await sende('Page.getAppManifest');
      const i = await sende('Page.getInstallabilityErrors');
      return {
        fehler: ((a.result && a.result.errors) || []).map(function (f) { return f.message; }),
        gruende: i.result ? (i.result.installabilityErrors || []).map(function (f) { return f.errorId; })
          : ['nicht abfragbar: ' + JSON.stringify(i.error || null)]
      };
    },
    /* Fuehrt eine Funktion in der Seite aus. Sie laeuft dort, nicht in Node,
       und liefert { log, fehler } zurueck. */
    fuehreAus: async function (fn) {
      const a = await sende('Runtime.evaluate', {
        expression: '(' + fn.toString() + ')()', awaitPromise: true, returnByValue: true
      });
      const r = a.result;
      if (r.exceptionDetails) {
        const d = r.exceptionDetails;
        return { fehler: ['Ausnahme im Durchlauf: ' + (d.exception && d.exception.description ? d.exception.description : d.text)] };
      }
      return r.result.value || {};
    },
    pdf: async function () {
      const a = await sende('Page.printToPDF', { preferCSSPageSize: true, printBackground: false, displayHeaderFooter: false });
      return Buffer.from(a.result.data, 'base64');
    },
    ende: async function () {
      ws.close();
      prozess.kill();
      await warte(500);
      try { fs.rmSync(profil, { recursive: true, force: true }); } catch (e) { /* bleibt im Temp-Verzeichnis */ }
    }
  };
}

/* ------------------------------------------------------------------------
   Die folgenden Funktionen laufen in der Seite. Sie duerfen nichts aus Node
   benutzen und bringen ihre Helfer deshalb selbst mit.
   ------------------------------------------------------------------------ */

/* Eine ganze Runde mit Karten, 0 bis 5, wie in der Anleitung beschrieben. */
async function rundeMitKarten() {
  const log = [];
  const warte = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };
  const q = function (s) { return document.querySelector(s); };
  const qa = function (s) { return Array.from(document.querySelectorAll(s)); };
  const sichtbar = function (id) { return !document.getElementById(id).hidden; };
  const pruefe = function (was, ist, soll) { log.push({ was: was, ok: JSON.stringify(ist) === JSON.stringify(soll), ist: ist, soll: soll }); };
  const klick = function (s) { const e = q(s); if (!e) throw new Error('fehlt: ' + s); e.click(); };
  const taste = function (v) { klick('#tasten-vorschlag .taste[data-wert="' + v + '"]'); };
  window.confirm = function () { return true; };

  pruefe('die Startseite ist da', sichtbar('start'), true);
  pruefe('mit ihrem Titel', q('#start h1').textContent, 'Konsensieren im Raum');
  pruefe('ohne Warnung zum Speicher', q('#speicherwarnung').hidden, true);
  klick('#start-moderieren'); await warte(80);
  pruefe('„Neue Runde“ ist da', sichtbar('neu'), true);
  q('#neu-form').requestSubmit(); await warte(50);
  pruefe('ohne Anwesende kommt ein Hinweis', q('#neu-fehler').hidden, false);
  for (let i = 0; i < 5; i++) klick('#neu [data-schritt="1"]');
  pruefe('Plus zählt die Anwesenden hoch', q('#neu-anwesende').value, '5');
  pruefe('0 bis 5 ist vorgewählt', q('#neu [name=skala]:checked').value, '5');
  pruefe('und steht zuerst', qa('#neu [name=skala]')[0].value, '5');
  klick('#neu [name=skala][value="5"]');
  q('#neu-form').requestSubmit(); await warte(80);
  pruefe('„Erfassen“ ist da', sichtbar('erfassen'), true);
  pruefe('die erste Zeile ist die Passivlösung', q('#zeile-name').textContent, 'Passivlösung');
  pruefe('sieben Tasten bei 0 bis 5', qa('#tasten-vorschlag .taste').length, 7);
  [2, 2, 3, 1].forEach(taste);
  pruefe('der Zähler für P', q('#zaehler').textContent, '4 von 5 erfasst');
  pruefe('die Kette für P', q('#kette').textContent, '2 2 3 1');
  klick('#weiter'); await warte(30);
  pruefe('weiter zu Vorschlag 1', q('#zeile-name').textContent, 'Vorschlag 1');
  [5, 4, 5, 3, 3, 3].forEach(taste);
  pruefe('zu viele Eingaben fallen auf', q('#zaehler').classList.contains('zuviel'), true);
  klick('#rueckgaengig'); klick('#rueckgaengig');
  pruefe('Rückgängig nimmt zwei zurück', q('#zaehler').textContent, '4 von 5 erfasst');
  klick('#weiter'); await warte(30);
  [1, 2, 0, ''].forEach(taste);
  pruefe('eine Enthaltung steht als Strich', q('#kette').textContent, '1 2 0 –');
  klick('#weiter'); await warte(30);

  klick('.art[data-art="wert"]'); await warte(30);
  pruefe('je Wert: sechs Fragen', qa('#wertzaehler .wertzeile').length, 6);
  pruefe('die erste fragt nach der 5', q('#wertzaehler label').textContent, 'Wer hat 5?');
  klick('#wertzaehler [data-anzahl="1"][data-wert="4"]');
  klick('#wertzaehler [data-anzahl="1"][data-wert="4"]');
  const feld = q('#anzahl-5');
  feld.value = '2';
  feld.dispatchEvent(new Event('input', { bubbles: true }));
  pruefe('je Wert zählt mit', q('#zaehler').textContent, '4 von 5 erfasst');
  pruefe('die Leiste kennt vier Zeilen', qa('#zeilenwahl .chip').length, 4);

  klick('.art[data-art="person"]'); await warte(30);
  pruefe('der Zettel zeigt P bis 3', qa('#entwurf li').length, 4);
  [2, 4, 1, 5].forEach(function (v) { klick('#tasten-person .taste[data-wert="' + v + '"]'); });
  klick('#zettel-fertig'); await warte(30);
  pruefe('ein Zettel ist erfasst', q('#zettel-zahl').textContent, '1 Zettel erfasst.');
  pruefe('und steht in der Liste', qa('#erfasste li').length, 1);
  pruefe('der nächste Zettel beginnt leer', qa('#entwurf b').map(function (b) { return b.textContent; }).join(''), '');

  klick('a[href="#ergebnis"]'); await warte(100);
  pruefe('die Tafelansicht ist da', sichtbar('ergebnis'), true);
  pruefe('die Runde läuft noch', sichtbar('lage-laeuft'), true);
  const zeilen = qa('#tafel .tafelzeile');
  pruefe('die Reihenfolge', zeilen.map(function (z) { return z.querySelector('.nummer b').textContent; }), ['3', '1', 'P', '2']);
  pruefe('über der Nummer steht klein „Vorschlag“', zeilen[0].querySelector('.nummer small').textContent, 'Vorschlag');
  pruefe('die Nummer steht ohne Kreis', getComputedStyle(zeilen[0].querySelector('.nummer')).borderTopStyle, 'none');
  pruefe('keine Platzziffer davor', q('#tafel .rang'), null);
  pruefe('der Durchschnitt des Siegers', zeilen[0].querySelector('.schnitt strong').textContent, '9,2');
  pruefe('ohne das Wort „Rückhalt“', zeilen[0].textContent.includes('Rückhalt'), false);
  pruefe('der Sieger ist markiert', zeilen[0].classList.contains('sieger'), true);
  pruefe('die Kraft im Konsens mit Vorzeichen', zeilen[0].textContent.includes('Kraft im Konsens +5,2'), true);
  pruefe('die Passivlösung als Messlatte', zeilen[2].querySelector('.schnitt strong').textContent, '4,0');
  pruefe('Vorschlag 2 liegt unter der Passivlösung', zeilen[3].textContent.includes('weniger Zustimmung als Passivlösung'), true);
  pruefe('Stimmen <2 bei Vorschlag 2, in Karten', zeilen[3].textContent.includes('Stimmen <2: 3'), true);
  pruefe('die niedrigste Stimme in Karten', zeilen[0].textContent.includes('niedrigste Stimme 4'), true);
  pruefe('die Enthaltung bei Vorschlag 2 als Zahl', zeilen[3].textContent.includes('Enthaltungen: 1'), true);
  pruefe('keine Enthaltung beim Sieger', zeilen[0].textContent.includes('Enthaltungen: 0'), true);
  pruefe('sechs Balken bei 0 bis 5', zeilen[0].querySelectorAll('.balken').length, 6);
  const texte = function (s) { return Array.from(zeilen[0].querySelectorAll(s)).map(function (e) { return e.textContent; }); };
  pruefe('über den Balken stehen die Anzahlen', texte('.balken b'), ['0', '0', '0', '0', '2', '3']);
  pruefe('darunter die Kartenwerte', texte('.balken small'), ['0', '1', '2', '3', '4', '5']);
  pruefe('darüber die Zahl der Bewertungen', zeilen[0].querySelector('.verteilung-summe').textContent, '5 Bewertungen abgegeben');
  const hell = getComputedStyle(zeilen[0].querySelector('.balken.z10 i'));
  pruefe('der hellste Balken hat eine Kontur', hell.borderTopStyle === 'solid' && hell.borderTopWidth !== '0px', true);
  pruefe('und steht nicht in einem grauen Kasten',
    getComputedStyle(zeilen[0].querySelector('.balken.z10')).backgroundColor, 'rgba(0, 0, 0, 0)');
  pruefe('der Hinweis zur Verdopplung', q('#ergebnis-grundlage').textContent.includes('Doppelten'), true);
  pruefe('ohne den Satz über die Karten 0 und 1', q('#ergebnis-grundlage').textContent.includes('Karten 0 und 1'), false);

  // Teilen (Spec/02): der Schirm, die Stufen, die Beschriftung, das Sichern.
  klick('#lage-laeuft a[href="#teilen"]'); await warte(100);
  pruefe('„Teilen“ ist da', sichtbar('teilen'), true);
  pruefe('offen und laufend: alle drei Stufen zur Wahl', qa('#teilen-stufen input:disabled').length, 0);
  let vorschau = q('#teilen-vorschau').value;
  pruefe('die Vorschau beginnt mit dem Zwischenstand', vorschau.split('\n')[0], 'Konsensieren im Raum – Zwischenstand');
  pruefe('und nennt den Sieger', vorschau.includes('\n1. Vorschlag 3\n'), true);
  pruefe('Stufe A ohne Einzelwerte', vorschau.includes('Einzelwerte'), false);
  klick('#teilen-stufen input[value="c"]'); await warte(30);
  vorschau = q('#teilen-vorschau').value;
  pruefe('Stufe C zeigt die Tabelle der Einzelwerte mit dem Zettel als Zeile',
    vorschau.includes('\n| Eingabe | P   | 1   | 2   | 3   |\n') && /\n\| [0-9A-Z]{5}   \| 2   \| 4   \| 1   \| 5   \|\n/.test(vorschau), true);
  const thema = q('#teilen-thema');
  thema.value = 'Sommerfahrt';
  thema.dispatchEvent(new Event('input', { bubbles: true }));
  const titel = q('#titel-3');
  titel.value = 'Nordsee';
  titel.dispatchEvent(new Event('input', { bubbles: true }));
  await warte(30);
  vorschau = q('#teilen-vorschau').value;
  pruefe('das Thema steht im Text', vorschau.includes('\nThema: Sommerfahrt\n'), true);
  pruefe('der Titel auch', vorschau.includes('\n1. Vorschlag 3 – Nordsee\n'), true);
  pruefe('das Teilen-Blatt nur, wo der Browser es kann', q('#teilen-blatt').hidden, typeof navigator.share !== 'function');
  pruefe('Kopieren nur mit Zwischenablage', q('#teilen-kopieren').hidden, !(navigator.clipboard && navigator.clipboard.writeText));
  pruefe('über 127.0.0.1 kein Hinweis auf https', q('#teilen-unsicher').hidden, true);
  // Kein echter Download in den Ordner der Person: Der Klick auf den Link wird
  // abgefangen und nur sein Dateiname gelesen.
  let geladen = null;
  const urklick = HTMLAnchorElement.prototype.click;
  HTMLAnchorElement.prototype.click = function () { geladen = this.download; };
  klick('#teilen-datei'); await warte(50);
  HTMLAnchorElement.prototype.click = urklick;
  pruefe('„Als Text sichern“ lädt eine Datei mit Datum und Uhrzeit', /^konsensieren-raum-\d{4}-\d{2}-\d{2}-\d{4}\.txt$/.test(geladen || ''), true);
  pruefe('und meldet es', q('#teilen-meldung').textContent, 'Gesichert als ' + geladen + '.');
  klick('#teilen a[href="#ergebnis"]'); await warte(100);
  pruefe('zurück in der Tafelansicht steht das Thema', q('#ergebnis-thema').textContent, 'Sommerfahrt');
  pruefe('und der Titel am Sieger', q('#tafel .tafelzeile .titel').textContent, 'Nordsee');
  pruefe('Kopf und Fuß des Drucks sind auf dem Schirm unsichtbar',
    [getComputedStyle(q('#druck-kopf')).display, getComputedStyle(q('#druck-fuss')).display], ['none', 'none']);
  pruefe('aber gefüllt', [q('#druck-kopf p').textContent, /Fassung \d/.test(q('#druck-fuss').textContent)], ['Konsensieren im Raum – Zwischenstand', true]);
  // "Drucken" vom Teilen-Schirm: hinueber zur Tafel, Anhang der gewaehlten Stufe, Druckdialog.
  klick('#lage-laeuft a[href="#teilen"]'); await warte(100);
  let gedruckt = false;
  window.print = function () { gedruckt = true; };
  klick('#teilen-drucken'); await warte(200);
  pruefe('„Drucken“ führt zur Tafelansicht', sichtbar('ergebnis'), true);
  pruefe('und ruft den Druckdialog', gedruckt, true);
  pruefe('mit der Tabelle der Einzelwerte als Anhang', [qa('#druck-anhang table tbody tr').length, qa('#druck-anhang th').map(function (e) { return e.textContent; }).slice(0, 5)],
    [5, ['Eingabe', 'P', '1', '2', '3']]);
  pruefe('und dem Zettel als Zeile mit Nummer', /^[0-9A-Z]{5}$/.test(q('#druck-anhang tr.zettel th').textContent), true);
  pruefe('den der Schirm nicht zeigt', getComputedStyle(q('#druck-anhang')).display, 'none');

  klick('#abschliessen'); await warte(50);
  pruefe('die Runde ist abgeschlossen', sichtbar('lage-fertig'), true);
  const s = JSON.parse(localStorage.getItem('konsens-app.sitzung'));
  pruefe('offen: Einzelwerte und Zettel bleiben zum Teilen', [s.runde.karten.some(function (l) { return l.length > 0; }), s.runde.zettel.length], [true, 1]);
  pruefe('die Meldung sagt es', q('#abgeschlossen-text').textContent.includes('bleiben zum Teilen'), true);
  pruefe('die Kennzahlen bleiben', s.runde.ergebnis.sieger, 3);
  pruefe('die Zettelnummer ist gemerkt', s.alteNummern.length, 1);
  pruefe('die Tafel bleibt stehen', qa('#tafel .tafelzeile').length, 4);
  klick('#lage-fertig a[href="#teilen"]'); await warte(100);
  pruefe('nach dem Abschluss bleiben alle drei Stufen wählbar', qa('#teilen-stufen input:disabled').length, 0);
  pruefe('die Wahl bleibt bei den Zetteln', q('#teilen-stufen input:checked').value, 'c');
  pruefe('die Vorschau ist jetzt ein Ergebnis, mit dem Zettel', [q('#teilen-vorschau').value.split('\n')[0], /\n\| [0-9A-Z]{5} +\|/.test(q('#teilen-vorschau').value)],
    ['Konsensieren im Raum – Ergebnis', true]);
  pruefe('die Beschriftung ist geblieben', [q('#teilen-thema').value, q('#titel-3').value], ['Sommerfahrt', 'Nordsee']);
  location.hash = '#erfassen'; await warte(80);
  pruefe('eine abgeschlossene Runde führt zum Ergebnis', sichtbar('ergebnis'), true);
  klick('#lage-fertig [data-aktion="sitzung-beenden"]'); await warte(80);
  pruefe('zurück am Start', sichtbar('start'), true);
  pruefe('die Beschriftung ist mit der Sitzung weg',
    [q('#teilen-thema').value, q('#teilen-vorschau').value, q('#ergebnis-thema').textContent, qa('#teilen-titel input').length], ['', '', '', 0]);
  location.hash = '#teilen'; await warte(80);
  pruefe('ohne Runde führt „Teilen“ zum Start', sichtbar('start'), true);
  pruefe('der Speicher ist leer', localStorage.getItem('konsens-app.sitzung'), null);
  pruefe('der Start bietet wieder „Ich moderiere“', q('#start-moderieren').hidden, false);
  return { log: log };
}

/* Bei 0 bis 10 stehen die Stimmen, wie sie gesagt wurden, und die Grenze der sehr
   niedrigen heisst "<3" (0.1.4). */
async function rundeMitZahlen() {
  const log = [];
  const warte = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };
  const q = function (s) { return document.querySelector(s); };
  const pruefe = function (was, ist, soll) { log.push({ was: was, ok: JSON.stringify(ist) === JSON.stringify(soll), ist: ist, soll: soll }); };
  const klick = function (s) { const e = q(s); if (!e) throw new Error('fehlt: ' + s); e.click(); };
  window.confirm = function () { return true; };

  klick('#start-moderieren'); await warte(80);
  q('#neu-anwesende').value = '4';
  klick('#neu [name=skala][value="10"]');
  q('#neu-form').requestSubmit(); await warte(80);
  pruefe('zwölf Tasten bei 0 bis 10', document.querySelectorAll('#tasten-vorschlag .taste').length, 12);
  [1, 2, 9].forEach(function (v) { klick('#tasten-vorschlag .taste[data-wert="' + v + '"]'); });
  klick('a[href="#ergebnis"]'); await warte(100);
  const zeile = q('#tafel .tafelzeile');
  pruefe('die erste Zeile ist P', zeile.querySelector('.nummer b').textContent, 'P');
  pruefe('bei 0 bis 10 heißt die Grenze <3', zeile.textContent.includes('Stimmen <3: 2'), true);
  pruefe('die niedrigste Stimme auf 0 bis 10', zeile.textContent.includes('niedrigste Stimme 1'), true);
  pruefe('eine Enthaltung', zeile.textContent.includes('Enthaltungen: 1'), true);
  pruefe('kein Satz zur Verdopplung', q('#ergebnis-grundlage').textContent.includes('Doppelten'), false);
  return { log: log };
}

/* Der Fehler aus 0.1.0: Nach "Sitzung beenden" stand beim Erfassen je Person
   noch die Bestaetigung des letzten Zettels der alten Sitzung da. */
async function sitzungswechsel() {
  const log = [];
  const warte = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };
  const q = function (s) { return document.querySelector(s); };
  const qa = function (s) { return Array.from(document.querySelectorAll(s)); };
  const pruefe = function (was, ist, soll) { log.push({ was: was, ok: JSON.stringify(ist) === JSON.stringify(soll), ist: ist, soll: soll }); };
  const klick = function (s) { const e = q(s); if (!e) throw new Error('fehlt: ' + s); e.click(); };
  const taste = function (v) { klick('#tasten-person .taste[data-wert="' + v + '"]'); };
  const gespeichert = function () { return JSON.parse(localStorage.getItem('konsens-app.sitzung')); };
  const runde = async function (verdeckt) {
    klick('#start-moderieren:not([hidden]), #start-neu:not([hidden])'); await warte(80);
    q('#neu-anwesende').value = '3';
    // Ausdruecklich 0 bis 10: Seit 0.1.5 ist 0 bis 5 vorgewaehlt, und die Werte unten brauchen die 7 und die 9.
    klick('#neu [name=skala][value="10"]');
    klick('#neu [name=anzeige][value="' + (verdeckt ? 'verdeckt' : 'offen') + '"]');
    q('#neu-form').requestSubmit(); await warte(80);
    klick('.art[data-art="person"]'); await warte(30);
  };
  window.confirm = function () { return true; };

  await runde(false);
  [4, 7, 9].forEach(taste);
  klick('#zettel-fertig'); await warte(30);
  const alt = gespeichert().runde.zettel[0].nr;
  pruefe('„Zettel fertig“ bestätigt den Zettel', q('#zettel-meldung').hidden, false);
  pruefe('mit seiner Nummer', q('#zettel-meldung').textContent.includes(alt), true);

  location.hash = '#start'; await warte(80);
  klick('#start-sitzung [data-aktion="sitzung-beenden"]'); await warte(80);
  pruefe('„Sitzung beenden“ leert den Speicher', localStorage.getItem('konsens-app.sitzung'), null);
  pruefe('und die Seite', document.body.innerHTML.includes(alt), false);

  await runde(true);
  pruefe('neue Sitzung: keine alte Bestätigung', q('#zettel-meldung').hidden, true);
  pruefe('neue Sitzung: kein Zettel erfasst', q('#zettel-zahl').textContent, 'Noch kein Zettel erfasst.');
  pruefe('neue Sitzung: die alte Nummer steht nirgends', document.body.innerHTML.includes(alt), false);

  [5, 5].forEach(taste);
  klick('#zettel-fertig'); await warte(30);
  const neu = gespeichert().runde.zettel[0].nr;
  pruefe('der neue Zettel wird bestätigt', q('#zettel-meldung').hidden, false);
  taste(3); await warte(30);
  pruefe('der nächste Tipp nimmt die Bestätigung weg', q('#zettel-meldung').hidden, true);
  klick('#zettel-fertig'); await warte(30);
  location.hash = '#start'; await warte(80);
  await runde(true);
  pruefe('neue Runde derselben Sitzung: keine Bestätigung', q('#zettel-meldung').hidden, true);
  pruefe('frühere Nummern sind gemerkt, aber nicht zu sehen',
    [gespeichert().alteNummern.includes(neu), document.body.innerHTML.includes(neu)], [true, false]);

  // Verdeckt: keine Einzelwerte zum Teilen, und der Abschluss loescht sie (Plan 7).
  [4, 4].forEach(taste);
  klick('#zettel-fertig'); await warte(30);
  location.hash = '#teilen'; await warte(100);
  pruefe('verdeckt: Einzelwerte und Zettel sind aus', qa('#teilen-stufen input:disabled').length, 2);
  pruefe('mit dem Grund', q('#teilen-stufe-c-dazu').textContent, 'Nicht in einer verdeckten Runde.');
  location.hash = '#ergebnis'; await warte(100);
  klick('#abschliessen'); await warte(50);
  pruefe('verdeckt: der Abschluss löscht die Zettel', gespeichert().runde.zettel, []);
  pruefe('und sagt es', q('#abgeschlossen-text').textContent.includes('Die Einzelwerte sind gelöscht.'), true);
  return { log: log };
}

/* Die Pruefseite rechnet die gemeinsamen Faelle im Browser. */
async function pruefseite() {
  const warte = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };
  let meldung = null;
  for (let i = 0; i < 50 && !meldung; i++) {
    meldung = document.querySelector('#pruefergebnis .meldung');
    if (!meldung) await warte(100);
  }
  const text = meldung ? meldung.textContent : '';
  const treffer = text.match(/^(\d+) von (\d+) Fällen stimmen\.$/);
  const falsch = document.querySelectorAll('#pruefergebnis .falsch').length;
  return { log: [
    { was: 'die Prüfseite rechnet alle Fälle', ok: !!treffer && treffer[1] === treffer[2], ist: text, soll: 'n von n Fällen stimmen.' },
    { was: 'und keiner ist falsch', ok: falsch === 0, ist: falsch, soll: 0 }
  ] };
}

/* Das Impressum kommt aus Impressum.md oder der Vorlage und wird erst im Browser gerendert. */
async function impressumSeite() {
  const warte = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };
  let h1 = null;
  for (let i = 0; i < 50 && !h1; i++) {
    h1 = document.querySelector('#impressum h1');
    if (!h1) await warte(100);
  }
  const fehler = document.querySelector('#impressum .fehler');
  const abschnitte = document.querySelectorAll('#impressum h2').length;
  return { log: [
    { was: 'das Impressum ist aus Impressum.md gerendert', ok: !!h1 && h1.textContent === 'Impressum', ist: h1 ? h1.textContent : (fehler ? fehler.textContent : null), soll: 'Impressum' },
    { was: 'mit Abschnitten', ok: abschnitte >= 3, ist: abschnitte, soll: '3 oder mehr' },
    { was: 'und der Seitentitel folgt der Überschrift', ok: /^Impressum · /.test(document.title), ist: document.title, soll: 'Impressum · …' }
  ] };
}

/* Das Menue oben (0.2.3): zu, ein Tipp oeffnet es, Anleitung und Karten stehen
   darin, ein Klick daneben und Escape schliessen es. */
function menueAuf() {
  const log = [];
  const pruefe = function (was, ist, soll) { log.push({ was: was, ok: JSON.stringify(ist) === JSON.stringify(soll), ist: ist, soll: soll }); };
  const menue = document.querySelector('.kopf details.menue');
  if (!menue) return { log: [{ was: 'die Kopfzeile hat ein Menü', ok: false, ist: null, soll: 'details.menue' }] };
  const knopf = menue.querySelector('summary');
  pruefe('das Menü ist zu', menue.open, false);
  pruefe('der Knopf heißt für Vorleseprogramme „Menü“', knopf.textContent.trim(), 'Menü');
  knopf.click();
  pruefe('ein Tipp öffnet es', menue.open, true);
  pruefe('darin Anleitung und Karten drucken',
    Array.from(menue.querySelectorAll('.menue-liste a')).map(function (a) { return a.textContent; }), ['Anleitung', 'Karten drucken']);
  const liste = menue.querySelector('.menue-liste').getBoundingClientRect();
  pruefe('die Liste liegt ganz auf dem Schirm', liste.left >= 0 && liste.right <= window.innerWidth, true);
  pruefe('und über dem Inhalt', getComputedStyle(menue.querySelector('.menue-liste')).position, 'absolute');
  document.querySelector('main').click();
  pruefe('ein Klick daneben schließt es', menue.open, false);
  knopf.click();
  document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
  pruefe('Escape schließt es', menue.open, false);
  pruefe('und der Fokus steht wieder auf dem Knopf', document.activeElement === knopf, true);
  pruefe('auf der Startseite ist kein Eintrag markiert', document.querySelectorAll('.menue-liste [aria-current]').length, 0);
  return { log: log };
}

function menueKarten() {
  const aktuell = Array.from(document.querySelectorAll('.menue-liste a[aria-current="page"]')).map(function (a) { return a.textContent; });
  return { log: [{ was: 'auf dem Kartenblatt ist „Karten drucken“ im Menü markiert', ok: JSON.stringify(aktuell) === JSON.stringify(['Karten drucken']), ist: aktuell, soll: ['Karten drucken'] }] };
}

function ohneSchraegstrich() {
  const hinweis = document.querySelector('.ohne-stil');
  const link = hinweis ? hinweis.querySelector('a') : null;
  const anzeige = hinweis ? getComputedStyle(hinweis).display : null;
  return { log: [
    { was: 'ohne Schrägstrich steht der Hinweis da', ok: anzeige !== null && anzeige !== 'none', ist: anzeige, soll: 'sichtbar' },
    { was: 'und führt zur Adresse mit Schrägstrich', ok: !!link && /\/Konsens-App\/$/.test(link.href), ist: link ? link.href : null, soll: '…/Konsens-App/' }
  ] };
}

function mitSchraegstrich() {
  const anzeige = getComputedStyle(document.querySelector('.ohne-stil')).display;
  const start = !document.getElementById('start').hidden;
  return { log: [
    { was: 'mit Schrägstrich ist der Hinweis versteckt', ok: anzeige === 'none', ist: anzeige, soll: 'none' },
    { was: 'und die App startet', ok: start, ist: start, soll: true }
  ] };
}

/* Nach dem ersten Besuch liegt alles im Vorrat des Service Workers. */
async function vorrat() {
  const log = [];
  const pruefe = function (was, ist, soll) { log.push({ was: was, ok: JSON.stringify(ist) === JSON.stringify(soll), ist: ist, soll: soll }); };
  if (!('serviceWorker' in navigator)) return { log: [{ was: 'der Browser kennt Service Worker', ok: false, ist: false, soll: true }] };
  const anmeldung = await Promise.race([navigator.serviceWorker.ready,
    new Promise(function (r) { setTimeout(function () { r(null); }, 8000); })]);
  pruefe('der Service Worker ist aktiv', !!(anmeldung && anmeldung.active), true);
  const namen = (await caches.keys()).filter(function (n) { return n.indexOf('konsens-app-') === 0; });
  pruefe('es gibt genau einen Vorrat der App', namen.length, 1);
  const v = await caches.open(namen[0] || 'keiner');
  const drin = (await v.keys()).map(function (a) { return new URL(a.url).pathname.replace(/^\/Konsens-App\//, '') || './'; });
  const soll = ['./', 'index.html', 'app.js', 'anleitung.html', 'karten.html', 'impressum.html', 'Impressum.example.md', 'manifest.webmanifest', 'symbol-512.png', 'tools/faelle.json'];
  pruefe('im Vorrat liegen Seiten, Skripte, Manifest und Symbole', soll.filter(function (d) { return drin.indexOf(d) < 0; }), []);
  return { log: log };
}

function ohneNetzStart() {
  const start = document.getElementById('start');
  const titel = start ? start.querySelector('h1').textContent : null;
  return { log: [
    { was: 'ohne Server startet die App aus dem Vorrat', ok: !!start && !start.hidden, ist: start ? !start.hidden : null, soll: true },
    { was: 'mit ihren Texten', ok: titel === 'Konsensieren im Raum', ist: titel, soll: 'Konsensieren im Raum' }
  ] };
}

function ohneNetzAnleitung() {
  const h1 = document.querySelector('h1');
  const titel = h1 ? h1.textContent : null;
  return { log: [{ was: 'auch die Anleitung ist ohne Server da', ok: titel === 'So funktioniert Konsensieren im Raum', ist: titel, soll: 'So funktioniert Konsensieren im Raum' }] };
}

/* ------------------------------------------------------------------------ */

/* Chrome schreibt jede Seite als eigenes Objekt ins PDF; A4 misst 595 x 842 Punkt. */
function blatt(was, pdf) {
  const text = pdf.toString('latin1');
  const seiten = (text.match(/\/Type\s*\/Page(?![a-z])/g) || []).length;
  const kasten = text.match(/\/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)\s*\]/);
  const groesse = kasten ? [Math.round(Number(kasten[1])), Math.round(Number(kasten[2]))] : null;
  return { log: [
    { was: 'das Kartenblatt ' + was + ' passt auf eine Seite', ok: seiten === 1, ist: seiten, soll: 1 },
    { was: 'das Kartenblatt ' + was + ' ist A4', ok: JSON.stringify(groesse) === '[595,842]', ist: groesse, soll: [595, 842] }
  ] };
}

(async function () {
  const server = await starteServer();
  const basis = 'http://127.0.0.1:' + server.address().port + '/Konsens-App/';
  const chrome = await starteChrome();
  if (!chrome) {
    console.log('Chrome nicht gefunden: ' + CHROME);
    console.log('Den Pfad in der Umgebungsvariablen CHROME angeben; der Durchlauf faellt aus.');
    await server.stoppe();
    process.exit(2);
  }
  const frisch = async function (url) {
    await chrome.oeffne(url);
    await chrome.fuehreAus(function () { localStorage.clear(); return {}; });
    await chrome.oeffne(url);
  };
  try {
    abschnitt('Eine Runde mit Karten, 0 bis 5');
    await frisch(basis);
    melde(await chrome.fuehreAus(rundeMitKarten));

    abschnitt('Eine Runde mit Zahlen, 0 bis 10');
    await frisch(basis);
    melde(await chrome.fuehreAus(rundeMitZahlen));

    abschnitt('Sitzung beenden lässt nichts stehen');
    await frisch(basis);
    melde(await chrome.fuehreAus(sitzungswechsel));

    abschnitt('Prüfseite');
    await chrome.oeffne(basis + 'pruefen.html');
    melde(await chrome.fuehreAus(pruefseite));

    abschnitt('Impressum');
    await chrome.oeffne(basis + 'impressum.html');
    melde(await chrome.fuehreAus(impressumSeite));

    abschnitt('Menü oben');
    await chrome.oeffne(basis);
    melde(await chrome.fuehreAus(menueAuf));
    await chrome.oeffne(basis + 'karten.html');
    melde(await chrome.fuehreAus(menueKarten));

    abschnitt('Adresse ohne Schrägstrich');
    const vorher = chrome.konsole.length;
    await chrome.oeffne(basis.replace(/\/$/, ''));
    melde(await chrome.fuehreAus(ohneSchraegstrich));
    // Stil und Skripte fehlen hier mit Absicht; ihre 404 sind kein Fehler der App.
    chrome.konsole.length = vorher;
    await chrome.oeffne(basis);
    melde(await chrome.fuehreAus(mitSchraegstrich));

    abschnitt('Kartenblatt');
    await chrome.oeffne(basis + 'karten.html');
    melde(blatt('in Farbe', await chrome.pdf()));
    await chrome.fuehreAus(function () { document.querySelector('label[for=druck-sw]').click(); return {}; });
    melde(blatt('in Schwarz-Weiß', await chrome.pdf()));

    abschnitt('Konsole');
    melde({ log: [{ was: 'keine Fehler in der Konsole', ok: chrome.konsole.length === 0, ist: chrome.konsole, soll: [] }] });

    // Zuletzt, weil danach der Server aus ist.
    abschnitt('Home-Bildschirm und ohne Netz');
    await chrome.oeffne(basis);
    melde(await chrome.fuehreAus(vorrat));
    const m = await chrome.manifest();
    melde({ log: [
      { was: 'Chrome liest das Manifest ohne Fehler', ok: m.fehler.length === 0, ist: m.fehler, soll: [] },
      { was: 'Chrome hält die App für installierbar', ok: m.gruende.length === 0, ist: m.gruende, soll: [] }
    ] });
    await server.stoppe();
    await chrome.oeffne(basis);
    melde(await chrome.fuehreAus(ohneNetzStart));
    await chrome.oeffne(basis + 'anleitung.html');
    melde(await chrome.fuehreAus(ohneNetzAnleitung));
    await chrome.oeffne(basis + 'impressum.html');
    const ohneNetz = await chrome.fuehreAus(impressumSeite);
    melde({ log: (ohneNetz.log || []).map(function (e) { e.was = 'ohne Server: ' + e.was; return e; }), fehler: ohneNetz.fehler });
  } catch (e) {
    melde({ fehler: ['Abbruch: ' + e.message] });
  } finally {
    await chrome.ende();
    await server.stoppe();
  }
  console.log('\n' + '-'.repeat(50));
  console.log(gut + ' bestanden, ' + schlecht + ' fehlgeschlagen');
  process.exit(schlecht > 0 ? 1 : 0);
}());
