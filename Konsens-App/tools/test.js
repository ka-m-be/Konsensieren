#!/usr/bin/env node
/*
 * Pruefungen der Konsens-App ohne Browser:  node Konsens-App/tools/test.js
 * Node dient nur dem Pruefen; die App selbst braucht es nicht.
 */
'use strict';

const fs = require('fs');
const pfad = require('path');
const Zaehlwerk = require('../zaehlwerk.js');
const Runde = require('../runde.js');
const Pruefen = require('../pruefen.js');

const APP = pfad.join(__dirname, '..');
let gut = 0, schlecht = 0;

function pruefe(was, ist, soll) {
  if (JSON.stringify(ist) === JSON.stringify(soll)) { gut++; return; }
  schlecht++;
  console.log('  FEHLER  ' + was);
  console.log('          erwartet: ' + JSON.stringify(soll));
  console.log('          bekommen: ' + JSON.stringify(ist));
}
function wahr(was, ist) { pruefe(was, ist, true); }
function wirft(was, aufruf) {
  try { aufruf(); } catch (e) { gut++; return; }
  schlecht++;
  console.log('  FEHLER  ' + was + ' (ohne Ausnahme durchgelaufen)');
}
function abschnitt(name) { console.log('\n' + name); }
function zeile(erg, nr) { return erg.zeilen.find(function (z) { return z.nr === nr; }); }

/* Eine Runde in einer frischen Sitzung, Zeitstempel 1000. */
function runde(einstellungen) {
  const sitzung = Runde.neueSitzung(1000);
  const r = Runde.neueRunde(sitzung, Object.assign({ skala: 10, passiv: true, anwesende: 5 }, einstellungen), 1000);
  return [sitzung, r];
}

/* ------------------------------------------------ Gemeinsame Testfaelle */
abschnitt('Gemeinsame Testfälle mit dem Werkzeug');
const faelle = JSON.parse(fs.readFileSync(pfad.join(__dirname, 'faelle.json'), 'utf8')).faelle;
wahr('faelle.json enthält Fälle', faelle.length > 0);
faelle.forEach(function (f) { pruefe(f.name, Pruefen.fall(Zaehlwerk, f), []); });

/* -------------------------------------------------- Verdopplung (V9) */
abschnitt('Karten und Hände: 0 bis 5 wird verdoppelt');
{
  const [, r] = runde({ skala: 5, anwesende: 3 });
  [3, 4, 5].forEach(function (w) { Runde.wertHinzu(r, 1, w); });
  [0, 1, 2].forEach(function (w) { Runde.wertHinzu(r, 2, w); });
  [2, 2, 2].forEach(function (w) { Runde.wertHinzu(r, 0, w); });
  const ein = Runde.eingabe(r);
  pruefe('das Zählwerk sieht 6, 8 und 10', ein.zeilen[1], [6, 8, 10]);
  const erg = Zaehlwerk.rechnen(ein);
  pruefe('Rückhalt auf 0 bis 10', zeile(erg, 1).mittel, 8);
  pruefe('Werte bis 2 sind dort die Karten 0 und 1', zeile(erg, 2).niedrig, 2);
  pruefe('der niedrigste Wert steht auf 0 bis 10', zeile(erg, 1).minimal, 6);
  pruefe('Kraft im Konsens auf 0 bis 10', zeile(erg, 1).kik, 4);
  wirft('eine 6 passt nicht zu 0 bis 5', function () { Runde.wertHinzu(r, 1, 6); });
  wirft('halbe Werte gibt es nicht', function () { Runde.wertHinzu(r, 1, 2.5); });
  wirft('Text ist kein Wert', function () { Runde.wertHinzu(r, 1, '3'); });
  wirft('negative Werte gibt es nicht', function () { Runde.wertHinzu(r, 1, -1); });
}
{
  const [s, r] = runde({ skala: 5, anwesende: 2 });
  Runde.zettelHinzu(s, [1, 5, 0]);
  pruefe('auch ganze Zettel werden verdoppelt', Runde.eingabe(r).zeilen.map(function (z) { return z[0]; }), [2, 10, 0]);
}
{
  const [, r] = runde({ skala: 10, anwesende: 2 });
  Runde.wertHinzu(r, 1, 7);
  pruefe('bei 0 bis 10 bleibt der Wert, wie er ist', Runde.eingabe(r).zeilen[1], [7]);
}

/* ------------------------------------------ Erfassen je Vorschlag und Wert */
abschnitt('Erfassen je Vorschlag und je Wert');
{
  const [, r] = runde({ skala: 5, anwesende: 4 });
  Runde.wertHinzu(r, 1, 5);
  Runde.wertHinzu(r, 1, null);
  Runde.wertHinzu(r, 1, 3);
  pruefe('Enthaltungen zählen als erfasst', Runde.erfasst(r, 1), 3);
  pruefe('Rückgängig nimmt den letzten Eintrag', Runde.wertZurueck(r, 1), 3);
  pruefe('danach stehen noch zwei da', r.karten[1], [5, null]);
  pruefe('Rückgängig in einer leeren Zeile tut nichts', Runde.wertZurueck(r, 4), undefined);
  Runde.anzahlSetzen(r, 1, 2, 3);
  pruefe('je Wert: drei Zweien dazu', r.karten[1], [5, null, 2, 2, 2]);
  Runde.anzahlSetzen(r, 1, 2, 1);
  pruefe('je Wert: auf eine Zwei verringert, der Rest bleibt stehen', r.karten[1], [5, null, 2]);
  pruefe('je Wert: die Zahl der Zweien', Runde.anzahlVon(r, 1, 2), 1);
  Runde.anzahlSetzen(r, 3, 4, 2);
  pruefe('je Wert legt die Zeile an', Runde.zeilenzahl(r), 3);
  Runde.zeileLeeren(r, 3);
  pruefe('eine geleerte letzte Zeile verschwindet', Runde.zeilenzahl(r), 1);
  wirft('je Wert zählt keine Enthaltungen', function () { Runde.anzahlSetzen(r, 1, null, 2); });
  wirft('keine negative Anzahl', function () { Runde.anzahlSetzen(r, 1, 3, -1); });
  pruefe('eine vorgeblätterte, leere Zeile ist noch keine', Runde.eingabe(r).zeilen.length, 2);
}
{
  const [, r] = runde({ passiv: false });
  wirft('ohne Passivlösung gibt es keine Zeile P', function () { Runde.wertHinzu(r, 0, 5); });
  pruefe('ohne Passivlösung beginnt die Runde bei Vorschlag 1', r.zeile, 1);
}

/* ------------------------------------------------------------ Zettel */
abschnitt('Ganze Zettel');
{
  const [s, r] = runde({ anwesende: 3 });
  const nr = Runde.zettelHinzu(s, [4, 7, 10]);
  wahr('die Zettelnummer hat fünf Zeichen aus Ziffern und Großbuchstaben', /^[0-9A-Z]{5}$/.test(nr));
  Runde.zettelHinzu(s, [5, 6], 'KURZ1');
  Runde.wertHinzu(r, 2, 3);
  pruefe('ein kürzerer Zettel zählt in späteren Zeilen als Enthaltung', Runde.erfasst(r, 2), 3);
  pruefe('und liefert dort keinen Wert', Runde.eingabe(r).zeilen[2], [3, 10, null]);
  wirft('eine Zettelnummer gibt es nur einmal', function () { Runde.zettelHinzu(s, [1], 'KURZ1'); });
  wahr('Zettel lassen sich wieder entfernen', Runde.zettelEntfernen(r, 'KURZ1'));
  pruefe('danach ist er weg', r.zettel.length, 1);
  wirft('ein Wert über 10 wird abgewiesen', function () { Runde.zettelHinzu(s, [11]); });
}
{
  const [s, r] = runde({ passiv: false, anwesende: 2 });
  Runde.zettelHinzu(s, [9, 4]);
  pruefe('ohne Passivlösung wird ein Wert in Zeile P übergangen', r.zettel[0].werte, [null, 4]);
}
{
  const [s, r] = runde({ anwesende: 2 });
  Runde.entwurfSetzen(r, 0, 5);
  Runde.entwurfSetzen(r, 1, null);
  Runde.entwurfSetzen(r, 3, 8);
  pruefe('der Entwurf kennt übersprungene Zeilen', Runde.entwurfWerte(r), [5, null, undefined, 8]);
  const nr = Runde.entwurfFertig(s);
  pruefe('fertig: Übersprungenes wird Enthaltung', r.zettel[0].werte, [5, null, null, 8]);
  wahr('fertig: der Zettel hat eine Nummer', r.zettel[0].nr === nr);
  pruefe('fertig: der Entwurf ist leer', Runde.entwurfWerte(r), []);
  pruefe('ein leerer Entwurf wird kein Zettel', Runde.entwurfFertig(s), null);
}
{
  // Die Bestaetigung "Zettel ... zaehlt" gehoert zur Runde. In 0.1.0 stand sie nach
  // "Sitzung beenden" noch auf dem Schirm der neuen Sitzung.
  const [s, r] = runde({ anwesende: 3 });
  pruefe('eine neue Runde bestätigt keinen Zettel', r.letzterZettel, null);
  Runde.entwurfSetzen(r, 0, 4);
  const nr = Runde.entwurfFertig(s);
  pruefe('nach „Zettel fertig“ steht seine Nummer da', r.letzterZettel, nr);
  Runde.entwurfSetzen(r, 0, 2);
  pruefe('der nächste Zettel nimmt die Bestätigung weg', r.letzterZettel, null);
  const nr2 = Runde.entwurfFertig(s);
  Runde.zettelEntfernen(r, nr2);
  pruefe('ein zurückgenommener Zettel ist nicht mehr bestätigt', r.letzterZettel, null);
  Runde.entwurfSetzen(r, 0, 1);
  Runde.entwurfFertig(s);
  const r2 = Runde.neueRunde(s, { skala: 10, passiv: true, anwesende: 3 }, 2000);
  pruefe('die nächste Runde beginnt ohne Bestätigung', r2.letzterZettel, null);
  Runde.entwurfSetzen(r2, 0, 3);
  Runde.entwurfFertig(s);
  Runde.abschliessen(s, Zaehlwerk.rechnen(Runde.eingabe(r2)), 3000);
  pruefe('nach dem Abschluss ist keine Bestätigung mehr da', r2.letzterZettel, null);
  pruefe('eine neue Sitzung hat keine Runde', Runde.neueSitzung(4000).runde, null);
}

/* ----------------------------------------------------- Abschluss (V7) */
abschnitt('Abschluss und Gedächtnis');
{
  const [s, r] = runde({ anwesende: 2 });
  Runde.wertHinzu(r, 1, 8);
  Runde.zettelHinzu(s, [6, 9], 'ALT01');
  const erg = Zaehlwerk.rechnen(Runde.eingabe(r));
  Runde.abschliessen(s, erg, 2000);
  // Seit 0.2.1 bleiben Einzelwerte und Zettel in offenen Runden zum Teilen, bis die
  // naechste Runde beginnt; wer erst abschloss und dann teilen wollte, stand sonst ohne da.
  pruefe('offen: nach dem Abschluss bleiben Einzelwerte und Zettel', [r.karten[1], r.zettel.length], [[8], 1]);
  pruefe('die Kennzahlen bleiben', r.ergebnis.sieger, 1);
  pruefe('die Zettelnummer bleibt im Gedächtnis der Sitzung', s.alteNummern, ['ALT01']);
  wirft('eine abgeschlossene Runde nimmt nichts mehr an', function () { Runde.wertHinzu(r, 1, 3); });
  const r2 = Runde.neueRunde(s, { skala: 10, passiv: true, anwesende: 2 }, 3000);
  wirft('ein alter Zettel kommt nicht in die neue Runde', function () { Runde.zettelHinzu(s, [1, 2], 'ALT01'); });
  Runde.zettelHinzu(s, [3], 'NEU01');
  Runde.neueRunde(s, { skala: 10, passiv: true, anwesende: 2 }, 4000);
  pruefe('auch eine nicht abgeschlossene Runde gibt ihre Nummern weiter', s.alteNummern, ['ALT01', 'NEU01']);
  wahr('die neue Runde ist leer', r2 !== s.runde && s.runde.zettel.length === 0);
  wahr('und die vorige mit ihren Werten ist weg', s.runde !== r);
  wirft('Anwesende zwischen 1 und 99', function () { Runde.anwesendeSetzen(s.runde, 0); });
  Runde.anwesendeSetzen(s.runde, 12);
  pruefe('Anwesende lassen sich während der Runde ändern', s.runde.anwesende, 12);
}
{
  // Verdeckt ist die Urne: Nach der Auszaehlung gibt es keine Einzelwerte mehr (Plan 7).
  const [s, r] = runde({ anwesende: 2, verdeckt: true });
  Runde.wertHinzu(r, 1, 8);
  Runde.zettelHinzu(s, [6, 9], 'URNE1');
  Runde.entwurfSetzen(r, 0, 3);
  Runde.abschliessen(s, Zaehlwerk.rechnen(Runde.eingabe(r)), 2000);
  pruefe('verdeckt: nach dem Abschluss sind Einzelwerte, Zettel und Entwurf weg', [r.karten, r.zettel, r.entwurf], [[], [], {}]);
  pruefe('die Zettelnummer bleibt im Gedächtnis', s.alteNummern, ['URNE1']);
}
{
  const s = Runde.neueSitzung(0);
  wahr('nach knapp zwölf Stunden gilt die Sitzung noch', !Runde.veraltet(s, Runde.HALTBARKEIT_MS));
  wahr('danach ist sie vergessen', Runde.veraltet(s, Runde.HALTBARKEIT_MS + 1));
  wahr('ein kaputter Speicherstand gilt als veraltet', Runde.veraltet({}, 0));
  wirft('eine Skala 0 bis 2 gibt es nicht', function () {
    Runde.neueRunde(Runde.neueSitzung(0), { skala: 2, passiv: true, anwesende: 5 }, 0);
  });
}

/* ------------------------------------------ Beschriftung (Spec/02, 6) */
abschnitt('Beschriftung der Runde');
{
  const [s, r] = runde({ anwesende: 3 });
  pruefe('eine neue Runde ist unbeschriftet', Runde.beschriftung(r), { thema: '', titel: {} });
  Runde.themaSetzen(r, '  Sommerfahrt   2026 ');
  pruefe('das Thema wird geputzt', Runde.beschriftung(r).thema, 'Sommerfahrt 2026');
  Runde.titelSetzen(r, 1, 'Nordsee');
  Runde.titelSetzen(r, 0, 'Dänemark wie immer');
  pruefe('Titel je Zeile, auch für P', [Runde.titel(r, 1), Runde.titel(r, 0), Runde.titel(r, 2)], ['Nordsee', 'Dänemark wie immer', '']);
  Runde.titelSetzen(r, 1, '   ');
  pruefe('ein leerer Titel verschwindet', Object.keys(Runde.beschriftung(r).titel), ['0']);
  Runde.titelSetzen(r, 2, 'x'.repeat(100));
  pruefe('höchstens 80 Zeichen', Runde.titel(r, 2).length, 80);
  wirft('keine Zeile 100', function () { Runde.titelSetzen(r, 100, 'zu weit'); });
  Runde.abschliessen(s, Zaehlwerk.rechnen(Runde.eingabe(r)), 2000);
  Runde.titelSetzen(r, 2, 'Alpen');
  pruefe('beschriften geht auch nach dem Abschluss', Runde.titel(r, 2), 'Alpen');
  pruefe('und die Beschriftung überlebt ihn', Runde.beschriftung(r).thema, 'Sommerfahrt 2026');
  delete r.beschriftung;
  pruefe('eine ältere Runde ohne das Feld bekommt es', [Runde.titel(r, 2), r.beschriftung], ['', { thema: '', titel: {} }]);
}
{
  const [, r] = runde({ passiv: false, anwesende: 2 });
  wirft('ohne Passivlösung kein Titel für P', function () { Runde.titelSetzen(r, 0, 'P'); });
}

/* ------------------------------------------- Ergebnisse teilen (Spec/02) */
abschnitt('Ergebnisse teilen: der Text');
const Teilen = require('../teilen.js');
{
  const [s, r] = runde({ skala: 5, anwesende: 3 });
  [2, 2, 3].forEach(function (w) { Runde.wertHinzu(r, 0, w); });
  [5, 4, 5].forEach(function (w) { Runde.wertHinzu(r, 1, w); });
  [1, 2, null].forEach(function (w) { Runde.wertHinzu(r, 2, w); });
  Runde.themaSetzen(r, 'Sommerfahrt');
  Runde.titelSetzen(r, 1, 'Nordsee');
  const jetzt = new Date(2026, 8, 13, 14, 32).getTime();
  const st = Teilen.stufen(r);
  pruefe('offen und laufend: A und B, C erst mit Zetteln',
    [st.a.moeglich, st.b.moeglich, st.c.moeglich, st.c.grund], [true, true, false, 'keine_zettel']);
  const a = Teilen.text(r, 'a', jetzt, '0.2.0');
  const zeilen = a.split('\n');
  pruefe('Kopf: Zwischenstand, solange die Runde läuft', zeilen[0], 'Konsensieren im Raum – Zwischenstand');
  pruefe('Eckdaten der Runde', zeilen[1],
    'Zwischenstand vom 13.09.2026, 14:32 · 3 Anwesende · gezeigt 0 bis 5, gerechnet verdoppelt auf 0 bis 10 · offen · mit Passivlösung');
  pruefe('das Thema', zeilen[2], 'Thema: Sommerfahrt');
  wahr('der Sieger mit Rang und Titel', a.includes('\n1. Vorschlag 1 – Nordsee\n'));
  wahr('die Kennzahlen wie an der Tafel', a.includes(
    '   Durchschnitt 9,3 · niedrigste Stimme 4 · Stimmen <2: 0 · bewertet von 3 der 3 · Enthaltungen: 0 · Kraft im Konsens +4,7\n'));
  wahr('das Etikett des Siegers', a.includes('\n   am breitesten getragen\n'));
  wahr('die Verteilung in Karten', a.includes('   Verteilung 0:0 · 1:0 · 2:0 · 3:0 · 4:1 · 5:2\n'));
  wahr('die Passivlösung ohne Titel, mit Rang', a.includes('\n2. Passivlösung\n'));
  wahr('Vorschlag 2 unter der Passivlösung', a.includes('\n3. Vorschlag 2\n') && a.includes('weniger Zustimmung als Passivlösung'));
  wahr('Stufe A ohne Einzelwerte', !a.includes('Einzelwerte'));
  wahr('der Fuß nennt die Fassung', a.endsWith('\nErstellt mit Konsensieren im Raum, Fassung 0.2.0.\n'));
  const b = Teilen.text(r, 'b', jetzt, '0.2.0');
  wahr('Stufe B: die Einzelwerte als Tabelle, je Zeile eine Eingabe, je Spalte ein Vorschlag', b.includes(
    '\n| Eingabe | P   | 1   | 2   |\n| ------- | --- | --- | --- |\n| 1       | 2   | 5   | 1   |\n| 2       | 2   | 4   | 2   |\n| 3       | 3   | 5   | –   |\n'));
  wahr('mit dem Hinweis davor', b.includes('\nEinzelwerte, wie erfasst: je Zeile eine Eingabe, je Spalte ein Vorschlag.'));
  pruefe('Stufe C fällt ohne Zettel auf B zurück', Teilen.text(r, 'c', jetzt, '0.2.0'), b);
  Runde.zettelHinzu(s, [3, 5, 0], 'ZET01');
  const c = Teilen.text(r, 'c', jetzt, '0.2.0');
  wahr('Stufe C: der Zettel als Zeile mit Nummer', c.includes('\n| 3       | 3   | 5   | –   |\n| ZET01   | 3   | 5   | 0   |\n'));
  wahr('und der Hinweis nennt die Zettel', c.includes('keine Eingabe. Zettel stehen mit ihrer Nummer, je Zettel eine Person.\n'));
  wahr('Stufe B: der Zettel ohne Nummer, als vierte Eingabe', Teilen.text(r, 'b', jetzt, '0.2.0').includes('\n| 4       | 3   | 5   | 0   |\n'));
  pruefe('Kopf, Eckdaten und Anhang einzeln, für den Druck',
    [Teilen.kopf(r), Teilen.eckdaten(r, jetzt).slice(0, 35), Teilen.anhang(r, 'a'), Teilen.anhang(r, 'c').split('\n')[1], Teilen.fuss('0.2.0')],
    ['Konsensieren im Raum – Zwischenstand', 'Zwischenstand vom 13.09.2026, 14:32', '', '| Eingabe | P   | 1   | 2   |', 'Erstellt mit Konsensieren im Raum, Fassung 0.2.0.']);
  pruefe('die Tabelle als Gerüst für den Druck', Teilen.tabelle(r, 'c'), { spalten: ['P', '1', '2'], zeilen: [
    { kennung: '1', zettel: false, werte: ['2', '5', '1'] }, { kennung: '2', zettel: false, werte: ['2', '4', '2'] },
    { kennung: '3', zettel: false, werte: ['3', '5', '–'] }, { kennung: 'ZET01', zettel: true, werte: ['3', '5', '0'] }], mitNummern: true });
  pruefe('ohne Einzelwerte kein Gerüst', Teilen.tabelle(r, 'a'), null);
  Runde.abschliessen(s, Zaehlwerk.rechnen(Runde.eingabe(r)), jetzt + 60000);
  const st2 = Teilen.stufen(r);
  pruefe('offen: nach dem Abschluss gehen alle drei Stufen weiter', [st2.b.moeglich, st2.c.moeglich], [true, true]);
  const d = Teilen.text(r, 'c', jetzt + 999999, '0.2.0');
  pruefe('Kopf: Ergebnis nach dem Abschluss', d.split('\n')[0], 'Konsensieren im Raum – Ergebnis');
  wahr('mit dem Zeitpunkt des Abschlusses', d.split('\n')[1].startsWith('Runde vom 13.09.2026, 14:33 · '));
  wahr('mit Einzelwerten und Zettel', d.includes('\n| 1       | 2   | 5   | 1   |\n') && d.includes('\n| ZET01   | 3   | 5   | 0   |\n'));
  wahr('die Beschriftung bleibt im Text', d.includes('Thema: Sommerfahrt') && d.includes('Vorschlag 1 – Nordsee'));
  pruefe('Dateiname mit Datum und Uhrzeit', Teilen.dateiname(jetzt, 'txt'), 'konsensieren-raum-2026-09-13-1432.txt');
  // Eine Runde, die vor 0.2.1 abgeschlossen wurde, hat ihre Werte verloren.
  r.karten = []; r.zettel = [];
  const st3 = Teilen.stufen(r);
  pruefe('eine alte abgeschlossene Runde ohne Werte: nur A, mit Grund', [st3.b.grund, st3.c.grund], ['abgeschlossen', 'abgeschlossen']);
  pruefe('und C fällt auf A zurück', Teilen.stufeMoeglich(r, 'c'), 'a');
}
{
  const [, r] = runde({ skala: 10, anwesende: 2, verdeckt: true });
  Runde.wertHinzu(r, 1, 7);
  const st = Teilen.stufen(r);
  pruefe('verdeckt: nur A, mit Grund', [st.b.moeglich, st.b.grund, st.c.grund], [false, 'verdeckt', 'verdeckt']);
  const txt = Teilen.text(r, 'b', 0, '0.2.0');
  wahr('verdeckt: B fällt auf A zurück', !txt.includes('Einzelwerte'));
  Runde.abschliessen(runde({ anwesende: 2 })[0], Zaehlwerk.rechnen(Runde.eingabe(r)), 5000);
  pruefe('verdeckt: auch nach dem Abschluss nur A', Teilen.stufen(r).b.grund, 'verdeckt');
  wahr('Eckdaten: verdeckt, 0 bis 10', txt.split('\n')[1].endsWith(' · 2 Anwesende · gezeigt 0 bis 10 · verdeckt · mit Passivlösung'));
  wahr('bei 0 bis 10 die Grenze <3 und elf Werte', txt.includes('Stimmen <3: 0')
    && txt.includes('   Verteilung 0:0 · 1:0 · 2:0 · 3:0 · 4:0 · 5:0 · 6:0 · 7:1 · 8:0 · 9:0 · 10:0\n'));
  wahr('ohne Rang steht ein Strich, ohne Wert ein Strich', txt.includes('\n– Passivlösung\n   Durchschnitt – · Stimmen <3: 0 · bewertet von 0 der 2 · Enthaltungen: 2\n'));
  wahr('die Meldung der Tafel steht im Text', txt.includes('\nDie Passivlösung haben nur 0 bewertet, nötig wären 2.'));
  wahr('das Etikett „zu wenig Bewertungen“', txt.includes('\n   zu wenig Bewertungen\n'));
}
{
  const [, r] = runde({ passiv: false, anwesende: 2 });
  Runde.wertHinzu(r, 1, 6);
  const txt = Teilen.text(r, 'b', 0, '0.2.0');
  wahr('ohne Passivlösung: so steht es da, und die Tabelle hat keine Spalte P',
    txt.split('\n')[1].endsWith('ohne Passivlösung') && txt.includes('\n| Eingabe | 1   |\n') && !txt.includes('| P '));
  pruefe('leer: Stufe B geht nicht', Teilen.stufen(runde({ anwesende: 2 })[1]).b.grund, 'leer');
}
{
  // Ungleich lange Spalten: Wo eine Spalte kuerzer ist, bleibt die Zelle leer,
  // und ein kuerzerer Zettel hat sich in den spaeteren Zeilen enthalten.
  const [s, r] = runde({ skala: 10, anwesende: 3 });
  Runde.wertHinzu(r, 0, 1);
  [7, 10].forEach(function (w) { Runde.wertHinzu(r, 1, w); });
  Runde.zettelHinzu(s, [4], 'KURZ2');
  pruefe('leere Zellen und Enthaltung eines kurzen Zettels', Teilen.tabelle(r, 'c').zeilen,
    [{ kennung: '1', zettel: false, werte: ['1', '7'] }, { kennung: '2', zettel: false, werte: ['', '10'] },
     { kennung: 'KURZ2', zettel: true, werte: ['4', '–'] }]);
  wahr('im Text bleiben die Spalten ausgerichtet', Teilen.anhang(r, 'c').includes('\n| 2       |     | 10  |\n| KURZ2   | 4   | –   |'));
}
pruefe('Zahlen: Komma, Vorzeichen, Uhrzeit', [Teilen.komma(7.25), Teilen.komma(-0.04), Teilen.vorzeichen(1.85),
  Teilen.vorzeichen(0), Teilen.vorzeichen(-2), Teilen.uhrzeit(new Date(2026, 0, 2, 9, 5).getTime())],
  ['7,3', '0,0', '+1,9', '0,0', '−2,0', '09:05']);
pruefe('die Meldungen sind die der Tafelansicht', Teilen.meldungen(runde()[1], Zaehlwerk.rechnen({ anwesende: 3, passiv: true, zeilen: [] })),
  [{ text: 'Noch nichts erfasst.', wink: true }]);

/* -------------------------------------------- Gleichstand an der Spitze */
abschnitt('Völliger Gleichstand an der Spitze');
{
  const ein = function (zeilen) { return { anwesende: 2, passiv: true, zeilen: zeilen }; };
  pruefe('entscheidet nur die Nummer, sagt es die Tafel',
    Zaehlwerk.rechnen(ein([[3, 3], [5, 7], [7, 5]])).gleichstand, [1, 2]);
  pruefe('auch zu dritt', Zaehlwerk.rechnen(ein([[3, 3], [6, 6], [6, 6], [6, 6]])).gleichstand, [1, 2, 3]);
  pruefe('entscheidet der Mindestwert, ist das kein Gleichstand',
    Zaehlwerk.rechnen(ein([[3, 3], [6, 6], [4, 8]])).gleichstand, null);
  pruefe('gleichauf mit P bleibt es beim Bisherigen, ohne Stichrunde',
    Zaehlwerk.rechnen(ein([[6, 6], [6, 6]])).gleichstand, null);
  pruefe('ein Gleichstand dahinter zählt nicht',
    Zaehlwerk.rechnen(ein([[3, 3], [9, 9], [6, 6], [6, 6]])).gleichstand, null);
}

/* ------------------------------------------------ Unsinn wird abgewiesen */
abschnitt('Das Zählwerk weist Unsinn ab');
wirft('Wert 11', function () { Zaehlwerk.rechnen({ anwesende: 2, passiv: false, zeilen: [[], [11]] }); });
wirft('Wert als Text', function () { Zaehlwerk.rechnen({ anwesende: 2, passiv: false, zeilen: [[], ['5']] }); });
wirft('negative Anwesende', function () { Zaehlwerk.rechnen({ anwesende: -1, passiv: true, zeilen: [] }); });
wirft('halbe Anwesende', function () { Zaehlwerk.rechnen({ anwesende: 2.5, passiv: true, zeilen: [] }); });
pruefe('eine leere Runde hat nur die Passivlösung',
  Zaehlwerk.rechnen({ anwesende: 3, passiv: true, zeilen: [] }).zeilen.map(function (z) { return z.nr; }), ['P']);

/* ---------------------------------------------------- Texte und Farben */
abschnitt('Texte und Farben');
{
  // Jeder Schluessel, den eine Seite oder ein Skript benutzt, muss in texte.js
  // stehen; sonst zeigte die App den Schluessel statt des Textes.
  const Texte = require('../texte.js');
  const benutzt = new Set();
  ['index.html', 'karten.html', 'pruefen.html', 'anleitung.html', 'impressum.html'].forEach(function (datei) {
    const html = fs.readFileSync(pfad.join(APP, datei), 'utf8');
    (html.match(/data-t(?:-aria)?="[^"]+"/g) || []).forEach(function (m) { benutzt.add(m.split('"')[1]); });
  });
  ['app.js', 'karten.js', 'pruefen.js', 'rahmen.js', 'impressum.js', 'teilen.js'].forEach(function (datei) {
    const js = fs.readFileSync(pfad.join(APP, datei), 'utf8');
    (js.match(/'[a-z]+\.[a-z_0-9]+'/g) || []).forEach(function (m) { benutzt.add(m.slice(1, -1)); });
  });
  benutzt.delete('skala.wert');   // wird mit der Zahl zusammengesetzt, siehe unten
  // Ebenso zusammengesetzt, auf dem Teilen-Schirm: der Grund je Stufe und die Erlaeuterung je Stufe.
  benutzt.delete('teilen.grund_');
  benutzt.delete('teilen.stufe_');
  ['teilen.grund_verdeckt', 'teilen.grund_abgeschlossen', 'teilen.grund_leer', 'teilen.grund_keine_zettel',
    'teilen.stufe_b_dazu', 'teilen.stufe_c_dazu'].forEach(function (k) { benutzt.add(k); });
  // Dateinamen wie 'sw.js' sehen aus wie Schluessel, sind aber keine.
  Array.from(benutzt).forEach(function (k) { if (/\.(js|html|css|json|png|svg|webmanifest)$/.test(k)) benutzt.delete(k); });
  const fehlend = Array.from(benutzt).filter(function (k) { return !(k in Texte.de); }).sort();
  pruefe('jeder benutzte Textschlüssel steht in texte.js', fehlend, []);
  wahr('es wurden überhaupt Schlüssel gefunden', benutzt.size > 60);
  const stufen = [];
  for (let w = 0; w <= 10; w++) if (!(('skala.wert' + w) in Texte.de)) stufen.push(w);
  pruefe('die Wortleiter hat alle elf Stufen', stufen, []);
  pruefe('Platzhalter werden ersetzt', Texte.t('erfassen.zaehler', { n: 8, g: 10 }), '8 von 10 erfasst');
}
{
  // Die Karten und Tasten sollen die Farben des Werkzeugs tragen (V8).
  const rampe = function (css) {
    const aus = {};
    (css.match(/--z\d+:\s*#[0-9a-fA-F]{6}/g) || []).forEach(function (m) {
      const teile = m.split(/:\s*/);
      aus[teile[0]] = teile[1].toLowerCase();
    });
    return aus;
  };
  const werkzeug = rampe(fs.readFileSync(pfad.join(APP, '..', 'assets', 'app.css'), 'utf8'));
  const app = rampe(fs.readFileSync(pfad.join(APP, 'app.css'), 'utf8'));
  pruefe('die Farbrampe ist dieselbe wie im Werkzeug', app, werkzeug);
  pruefe('mit elf Stufen', Object.keys(app).length, 11);
  const karten = fs.readFileSync(pfad.join(APP, 'karten.css'), 'utf8');
  pruefe('Karte k trägt die Farbe von 2k', [0, 1, 2, 3, 4, 5].map(function (k) {
    const treffer = karten.match(new RegExp('\\.rahmen\\.k' + k + ' \\{ fill: var\\(--z(\\d+)\\)'));
    return treffer ? Number(treffer[1]) : null;
  }), [0, 2, 4, 6, 8, 10]);
  const blatt = fs.readFileSync(pfad.join(APP, 'karten.html'), 'utf8');
  pruefe('sechs Karten auf dem Blatt', (blatt.match(/class="kartenbild"/g) || []).length, 6);
  pruefe('schraffiert sind genau die Karten 0 und 1', (blatt.match(/url\(#schraffur(\d)\)/g) || []).join(), 'url(#schraffur0),url(#schraffur1)');
}

/* -------------------------------------------------------- Aus einem Guss */
abschnitt('Werkzeug und App aus einem Guss');
{
  // Die Kopfzeile kommt in beiden Teilen aus demselben CSS-Block (Spezifikation
  // des Werkzeugs, Abschnitt 19). Wer ihn an einer Stelle aendert, aendert beide.
  const block = function (css) {
    const a = css.indexOf('/* --- Suite:');
    const b = css.indexOf('/* --- Ende Suite --- */');
    return a >= 0 && b > a ? css.slice(a, b) : null;
  };
  const werkzeugCss = fs.readFileSync(pfad.join(APP, '..', 'assets', 'app.css'), 'utf8');
  const appCss = fs.readFileSync(pfad.join(APP, 'app.css'), 'utf8');
  wahr('das Werkzeug hat den Block der Suite', block(werkzeugCss) !== null);
  pruefe('die App hat ihn wortgleich', block(appCss), block(werkzeugCss));
  wahr('zum Block der Suite gehört das Menü', (block(werkzeugCss) || '').indexOf('.menue-liste') >= 0);
  // Ebenso das Balkenbild der Auswertung (App 0.1.3, Werkzeug 0.3.3).
  const balken = function (css) {
    const a = css.indexOf('/* --- Verteilung:');
    const b = css.indexOf('/* --- Ende Verteilung --- */');
    return a >= 0 && b > a ? css.slice(a, b) : null;
  };
  wahr('das Werkzeug hat den Block des Balkenbilds', balken(werkzeugCss) !== null);
  pruefe('die App hat das Balkenbild wortgleich', balken(appCss), balken(werkzeugCss));

  // Jede Seite der App traegt dieselbe Kopf- und Fusszeile.
  const seiten = ['index.html', 'karten.html', 'pruefen.html', 'anleitung.html', 'impressum.html'];
  const stueck = function (html, tag, klasse) {
    const m = html.match(new RegExp('<' + tag + ' class="' + klasse + '">[\\s\\S]*?</' + tag + '>'));
    return m ? m[0] : null;
  };
  const kopf = {}, fuss = {};
  seiten.forEach(function (s) {
    const html = fs.readFileSync(pfad.join(APP, s), 'utf8');
    kopf[s] = stueck(html, 'header', 'kopf');
    fuss[s] = stueck(html, 'footer', 'fuss');
  });
  wahr('die Startseite hat Kopf- und Fusszeile', kopf['index.html'] !== null && fuss['index.html'] !== null);
  seiten.slice(1).forEach(function (s) {
    pruefe(s + ': dieselbe Kopfzeile wie die Startseite', kopf[s], kopf['index.html']);
    pruefe(s + ': dieselbe Fusszeile wie die Startseite', fuss[s], fuss['index.html']);
  });
  wahr('der Umschalter fuehrt ins Werkzeug', /class="suite"[\s\S]*href="\.\.\/"/.test(kopf['index.html'] || ''));
  // Seit 0.1.5 steht das Impressum im Fuss und die Pruefseite nicht mehr; sie
  // bleibt unter pruefen.html erreichbar.
  wahr('die Fusszeile verlinkt das Impressum', /href="impressum\.html"/.test(fuss['index.html'] || ''));
  wahr('und nicht mehr die Pruefseite', !/pruefen\.html/.test(fuss['index.html'] || ''));
  // Seit 0.2.3 stehen Anleitung und Karten im Menue oben, wie Hilfe und Sprache im
  // Werkzeug, und nicht mehr in Kopf- und Fusszeile.
  const menue = (kopf['index.html'] || '').match(/<details class="menue">[\s\S]*?<\/details>/);
  wahr('die Kopfzeile hat das Menü mit dem Stapel-Knopf', !!menue && menue[0].indexOf('class="stapel"') >= 0);
  pruefe('im Menü: Anleitung und Karten drucken', menue ? menue[0].match(/href="[^"]+"/g) : null, ['href="anleitung.html"', 'href="karten.html"']);
  wahr('neben dem Menü keine Links mehr rechts in der Kopfzeile', !/kopf-nav/.test(kopf['index.html'] || ''));
  wahr('und die Fusszeile nennt Anleitung und Karten nicht mehr', !/anleitung\.html|karten\.html/.test(fuss['index.html'] || ''));
  wahr('und zeigt „Im Raum“ als aktiv', /class="aktiv" href="\.\/#start"/.test(kopf['index.html'] || ''));

  // Die Fassung im Fuss ist die oberste im Changelog der App.
  const Rahmen = require('../rahmen.js');
  const oben = (fs.readFileSync(pfad.join(APP, 'CHANGELOG.md'), 'utf8').match(/^## (\d+\.\d+\.\d+)/m) || [])[1];
  pruefe('die Fassung im Fuss steht oben im Changelog', Rahmen.FASSUNG, oben);
}

/* ------------------------------------------- Home-Bildschirm und ohne Netz */
abschnitt('Home-Bildschirm und ohne Netz');
{
  // Ohne Manifest, Symbole und Service Worker legt ein Handy nur ein Lesezeichen
  // an, das jedes Mal den Server braucht (Rueckmeldung zu 0.1.2).
  const manifest = JSON.parse(fs.readFileSync(pfad.join(APP, 'manifest.webmanifest'), 'utf8'));
  pruefe('das Manifest nennt Name, Start und Anzeige',
    [manifest.name, manifest.short_name, manifest.start_url, manifest.scope, manifest.display],
    ['Konsensieren im Raum', 'Konsensieren', './', './', 'standalone']);
  // Breite und Hoehe stehen im IHDR-Block jeder PNG-Datei ab Byte 16.
  const masse = function (datei) {
    const b = fs.readFileSync(pfad.join(APP, datei));
    return b.readUInt32BE(16) + 'x' + b.readUInt32BE(20);
  };
  manifest.icons.filter(function (s) { return s.type === 'image/png'; }).forEach(function (s) {
    pruefe('das Symbol ' + s.src + ' hat die angegebene Größe', masse(s.src), s.sizes);
  });
  wahr('ein Symbol taugt zum Zuschneiden (maskable)', manifest.icons.some(function (s) { return s.purpose === 'maskable'; }));
  pruefe('das Symbol fürs iPhone misst 180 Punkte', masse('symbol-180.png'), '180x180');

  const Sw = require('../sw.js');
  const Rahmen = require('../rahmen.js');
  pruefe('der Vorrat trägt die Fassung aus rahmen.js', Sw.FASSUNG, Rahmen.FASSUNG);
  pruefe('jede Datei im Vorrat gibt es',
    Sw.DATEIEN.filter(function (d) { return d !== './' && !fs.existsSync(pfad.join(APP, d)); }), []);
  // Wer eine Seite, ein Skript, ein Stylesheet oder ein Bild dazulegt, muss es in
  // den Vorrat aufnehmen, sonst fehlt es ohne Netz.
  pruefe('keine Datei der App fehlt im Vorrat', fs.readdirSync(APP).filter(function (d) {
    return /\.(html|css|js|png|svg|webmanifest)$/.test(d) && d !== 'sw.js' && Sw.DATEIEN.indexOf(d) < 0;
  }), []);
  wahr('die Testfälle liegen auch im Vorrat', Sw.DATEIEN.indexOf('tools/faelle.json') >= 0);
  wahr('die Impressum-Vorlage liegt auch im Vorrat', Sw.DATEIEN.indexOf('Impressum.example.md') >= 0);
  // Das ausgefuellte Impressum gibt es nicht auf jeder Installation; es darf den
  // Vorrat nicht scheitern lassen und kommt deshalb nur mit, wenn es da ist.
  pruefe('das ausgefüllte Impressum kommt wahlweise dazu', Sw.WAHLWEISE, ['Impressum.md']);
  wahr('und nicht in die Pflichtliste', Sw.DATEIEN.indexOf('Impressum.md') < 0);
  ['index.html', 'karten.html', 'pruefen.html', 'anleitung.html', 'impressum.html'].forEach(function (s) {
    const html = fs.readFileSync(pfad.join(APP, s), 'utf8');
    wahr(s + ' verweist auf Manifest und iPhone-Symbol',
      html.indexOf('<link rel="manifest" href="manifest.webmanifest">') >= 0
      && html.indexOf('<link rel="apple-touch-icon" href="symbol-180.png">') >= 0);
  });
  wahr('rahmen.js meldet den Service Worker an',
    fs.readFileSync(pfad.join(APP, 'rahmen.js'), 'utf8').indexOf("navigator.serviceWorker.register('sw.js'") >= 0);
}

/* ------------------------------------------------- Markdown wie im Werkzeug */
abschnitt('Markdown wie im Werkzeug');
{
  // lib/Markdown.php und markdown.js muessen aus derselben Datei dasselbe HTML
  // machen; tools/test.php schickt dieselben Faelle durch die PHP-Seite.
  const Markdown = require('../markdown.js');
  const md = JSON.parse(fs.readFileSync(pfad.join(__dirname, 'markdown-faelle.json'), 'utf8')).faelle;
  wahr('markdown-faelle.json enthält Fälle', md.length > 0);
  md.forEach(function (f) { pruefe(f.name, Markdown.html(f.md), f.html); });
  pruefe('der Titel ist die erste Überschrift ohne Auszeichnung', Markdown.titel('Vorspann\n# Der **Titel**\n## Nicht'), 'Der Titel');
  pruefe('ohne Überschrift kein Titel', Markdown.titel('nur Text'), '');
  const impressum = fs.readFileSync(pfad.join(APP, 'Impressum.example.md'), 'utf8');
  pruefe('die Impressum-Vorlage der App beginnt mit ihrer Überschrift', Markdown.titel(impressum), 'Impressum');
  wahr('und hat Abschnitte', (Markdown.html(impressum).match(/<h2>/g) || []).length >= 3);
}

/* ------------------------------------------------------ Skala: 0 bis 5 zuerst */
abschnitt('0 bis 5 steht zuerst und ist vorgewählt');
{
  // Karten und Haende sind im Raum der Normalfall (0.1.5).
  const index = fs.readFileSync(pfad.join(APP, 'index.html'), 'utf8');
  wahr('0 bis 5 steht vor 0 bis 10', index.indexOf('name="skala" value="5"') < index.indexOf('name="skala" value="10"'));
  wahr('vor der ersten Runde ist 0 bis 5 vorgewählt', /\{ skala: 5, passiv: true/.test(fs.readFileSync(pfad.join(APP, 'app.js'), 'utf8')));
}

/* ----------------------------------------------- Adresse ohne Schraegstrich */
abschnitt('Adresse ohne Schrägstrich');
{
  // Unter .../Konsens-App ohne / laden Stil und Skripte nicht; dann bleibt nur
  // statisches HTML, und das muss den Weg zur richtigen Adresse zeigen.
  const html = fs.readFileSync(pfad.join(APP, 'index.html'), 'utf8');
  const css = fs.readFileSync(pfad.join(APP, 'app.css'), 'utf8');
  wahr('index.html hat den Hinweis mit Link auf die Adresse mit Schrägstrich',
    /<p class="ohne-stil">[^<]*<a href="Konsens-App\/">/.test(html));
  wahr('app.css versteckt ihn, sobald es geladen ist', /\.ohne-stil \{ display: none; \}/.test(css));
  wahr('der Hinweis braucht kein Skript', html.indexOf('ohne-stil') < html.indexOf('<script'));
}

console.log('\n' + '-'.repeat(50));
console.log(gut + ' bestanden, ' + schlecht + ' fehlgeschlagen');
process.exit(schlecht > 0 ? 1 : 0);
