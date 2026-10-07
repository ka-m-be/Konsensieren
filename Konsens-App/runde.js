/*
 * Die Runde im Speicher der Moderation: nur Zahlen, keine Namen, keine Oberflaeche.
 *
 * Werte stehen hier in den Einheiten der Runde, also 0 bis 5 oder 0 bis 10;
 * null ist Enthaltung. Erst eingabe() verdoppelt fuer das Zaehlwerk, damit es
 * immer 0 bis 10 sieht und von Karten und Fingern nichts wissen muss (V9).
 *
 * Zwei Quellen fuellen eine Runde: Einzelwerte je Zeile (Karten, Haende, das
 * Tastenfeld der Moderation) und ganze Zettel (eine Person, alle Zeilen). Beide
 * landen in derselben Zaehlung. Zeile 0 ist die Passivloesung P, ab 1 folgen
 * die Nummern an der Tafel.
 */
(function (wurzel, fabrik) {
  if (typeof module === 'object' && module.exports) module.exports = fabrik();
  else wurzel.Runde = fabrik();
}(this, function () {
  'use strict';

  const ZEICHEN = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

  /* V7 verlangt, dass nichts ueber die Sitzung hinaus liegen bleibt, spaetestens
     bis zum naechsten Tag. Ein Kalendertag waere falsch fuer Sitzungen ueber
     Mitternacht; zwoelf Stunden ohne Eingabe gibt es in keiner laufenden Runde. */
  const HALTBARKEIT_MS = 12 * 60 * 60 * 1000;

  function zufallsnummer() {
    const zahlen = new Uint32Array(5);
    const krypto = typeof globalThis !== 'undefined' ? globalThis.crypto : undefined;
    if (krypto && krypto.getRandomValues) krypto.getRandomValues(zahlen);
    else for (let i = 0; i < 5; i++) zahlen[i] = Math.floor(Math.random() * 4294967296);
    let nr = '';
    for (let i = 0; i < 5; i++) nr += ZEICHEN[zahlen[i] % ZEICHEN.length];
    return nr;
  }

  function neueSitzung(jetzt) {
    return { fassung: 1, geaendert: jetzt, runde: null, alteNummern: [] };
  }

  function veraltet(sitzung, jetzt) {
    return !sitzung || typeof sitzung.geaendert !== 'number'
      || jetzt - sitzung.geaendert > HALTBARKEIT_MS;
  }

  function pruefeAnwesende(n) {
    if (!Number.isInteger(n) || n < 1 || n > 99) {
      throw new RangeError('Anwesende zwischen 1 und 99: ' + String(n));
    }
  }

  /* Wer eine Runde verlaesst, nimmt ihre Zettelnummern ins Gedaechtnis der
     Sitzung mit: Ein alter Zettel soll nicht in die naechste Abstimmung rutschen
     (V3). Die Zettel selbst verschwinden. */
  function nummernMerken(sitzung) {
    const r = sitzung.runde;
    if (!r) return;
    r.zettel.forEach(function (z) {
      if (sitzung.alteNummern.indexOf(z.nr) < 0) sitzung.alteNummern.push(z.nr);
    });
  }

  function neueRunde(sitzung, e, jetzt) {
    if (e.skala !== 10 && e.skala !== 5) throw new RangeError('Skala ist 10 oder 5: ' + String(e.skala));
    pruefeAnwesende(e.anwesende);
    nummernMerken(sitzung);
    const passiv = e.passiv !== false;
    sitzung.runde = {
      skala: e.skala,
      passiv: passiv,
      verdeckt: e.verdeckt === true,
      anwesende: e.anwesende,
      beginn: jetzt,
      karten: [],
      zettel: [],
      entwurf: {},
      letzterZettel: null,
      zeile: passiv ? 0 : 1,
      art: 'vorschlag',
      beschriftung: { thema: '', titel: {} },
      ergebnis: null,
      abgeschlossen: null
    };
    sitzung.geaendert = jetzt;
    return sitzung.runde;
  }

  function erlaubt(runde, wert) {
    return wert === null || (Number.isInteger(wert) && wert >= 0 && wert <= runde.skala);
  }

  function pruefeOffen(runde) {
    if (runde.abgeschlossen !== null) throw new Error('Die Runde ist abgeschlossen.');
  }

  function pruefeZeile(runde, zeile) {
    const erste = runde.passiv ? 0 : 1;
    if (!Number.isInteger(zeile) || zeile < erste || zeile > 99) {
      throw new RangeError('Keine Zeile dieser Runde: ' + String(zeile));
    }
  }

  function karten(runde, zeile) {
    while (runde.karten.length <= zeile) runde.karten.push([]);
    return runde.karten[zeile];
  }

  function wertHinzu(runde, zeile, wert) {
    pruefeOffen(runde);
    pruefeZeile(runde, zeile);
    if (!erlaubt(runde, wert)) throw new RangeError('Wert passt nicht zur Skala: ' + String(wert));
    karten(runde, zeile).push(wert);
  }

  function wertZurueck(runde, zeile) {
    pruefeOffen(runde);
    const liste = runde.karten[zeile] || [];
    return liste.length > 0 ? liste.pop() : undefined;
  }

  function anzahlVon(runde, zeile, wert) {
    return (runde.karten[zeile] || []).filter(function (w) { return w === wert; }).length;
  }

  /* Zaehlen je Wert: "Wer hat 4?" setzt die Zahl der Vieren in dieser Zeile.
     Die uebrigen Eintraege bleiben in ihrer Reihenfolge stehen. */
  function anzahlSetzen(runde, zeile, wert, anzahl) {
    pruefeOffen(runde);
    pruefeZeile(runde, zeile);
    if (wert === null || !erlaubt(runde, wert)) throw new RangeError('Wert passt nicht zur Skala: ' + String(wert));
    if (!Number.isInteger(anzahl) || anzahl < 0 || anzahl > 999) throw new RangeError('Anzahl: ' + String(anzahl));
    const rest = karten(runde, zeile).filter(function (w) { return w !== wert; });
    for (let i = 0; i < anzahl; i++) rest.push(wert);
    runde.karten[zeile] = rest;
  }

  function zeileLeeren(runde, zeile) {
    pruefeOffen(runde);
    if (runde.karten[zeile]) runde.karten[zeile] = [];
  }

  /* Ein Zettel ist eine Person mit allen Zeilen; wo er kuerzer ist als andere,
     hat sie sich enthalten. Die Nummer entsteht mit dem Zettel und haelt ihn
     auseinander; doppelt vergeben wird sie auch ueber fruehere Runden nicht. */
  function zettelHinzu(sitzung, werte, nummer) {
    const runde = sitzung.runde;
    pruefeOffen(runde);
    const liste = werte.slice();
    while (liste.length > 0 && liste[liste.length - 1] === undefined) liste.pop();
    const sauber = liste.map(function (w, i) {
      if (w === undefined) return null;
      if (!erlaubt(runde, w)) throw new RangeError('Wert passt nicht zur Skala: ' + String(w));
      return i === 0 && !runde.passiv ? null : w;
    });
    const vergeben = function (nr) {
      return sitzung.alteNummern.indexOf(nr) >= 0
        || runde.zettel.some(function (z) { return z.nr === nr; });
    };
    let nr = nummer === undefined ? zufallsnummer() : nummer;
    while (nummer === undefined && vergeben(nr)) nr = zufallsnummer();
    if (vergeben(nr)) throw new Error('Zettelnummer schon vergeben: ' + nr);
    runde.zettel.push({ nr: nr, werte: sauber });
    return nr;
  }

  function zettelEntfernen(runde, nr) {
    pruefeOffen(runde);
    const vorher = runde.zettel.length;
    runde.zettel = runde.zettel.filter(function (z) { return z.nr !== nr; });
    if (runde.letzterZettel === nr) runde.letzterZettel = null;
    return runde.zettel.length < vorher;
  }

  /* Der Entwurf ist der Zettel, der gerade am Handy der Moderation getippt
     wird, etwa wenn es als Urne herumgeht. Er zaehlt erst mit "fertig". */
  function entwurfSetzen(runde, zeile, wert) {
    pruefeOffen(runde);
    pruefeZeile(runde, zeile);
    if (!erlaubt(runde, wert)) throw new RangeError('Wert passt nicht zur Skala: ' + String(wert));
    runde.entwurf[String(zeile)] = wert;
    // Wer den naechsten Zettel beginnt, braucht die Bestaetigung des vorigen nicht mehr.
    runde.letzterZettel = null;
  }

  function entwurfWerte(runde) {
    const liste = [];
    Object.keys(runde.entwurf).forEach(function (k) { liste[Number(k)] = runde.entwurf[k]; });
    for (let i = 0; i < liste.length; i++) if (!(i in liste)) liste[i] = undefined;
    return liste;
  }

  function entwurfFertig(sitzung) {
    const runde = sitzung.runde;
    const werte = entwurfWerte(runde);
    if (werte.length === 0) return null;
    const nr = zettelHinzu(sitzung, werte);
    runde.entwurf = {};
    // "Zettel ... zaehlt" gehoert zur Runde, nicht zur Anzeige: So verschwindet die
    // Bestaetigung mit der Runde und mit der Sitzung, statt auf dem Schirm zu bleiben.
    runde.letzterZettel = nr;
    return nr;
  }

  /* Beschriftung fuer die Dokumentation (Spec/02_Ergebnisse_teilen.md, 6): ein
     Thema und je Zeile ein Titel, freier Text der Moderation, hoechstens 80
     Zeichen, nur das, was an der Tafel steht - keine Namen von Personen. Aeltere
     Runden im Speicher haben das Feld nicht; beschriftung() legt es dann an.
     Beschriften geht auch nach dem Abschluss noch, denn die Dokumentation
     entsteht oft erst dann. Weg ist es mit der Runde (V7). */
  const BESCHRIFTUNG_MAX = 80;

  function beschriftung(runde) {
    if (!runde.beschriftung || typeof runde.beschriftung !== 'object') runde.beschriftung = { thema: '', titel: {} };
    if (typeof runde.beschriftung.thema !== 'string') runde.beschriftung.thema = '';
    if (!runde.beschriftung.titel || typeof runde.beschriftung.titel !== 'object') runde.beschriftung.titel = {};
    return runde.beschriftung;
  }

  function beschriftungPutzen(text) {
    return String(text === undefined || text === null ? '' : text).replace(/\s+/g, ' ').trim().slice(0, BESCHRIFTUNG_MAX);
  }

  function themaSetzen(runde, text) {
    beschriftung(runde).thema = beschriftungPutzen(text);
  }

  function titelSetzen(runde, zeile, text) {
    pruefeZeile(runde, zeile);
    const b = beschriftung(runde);
    const sauber = beschriftungPutzen(text);
    if (sauber === '') delete b.titel[String(zeile)];
    else b.titel[String(zeile)] = sauber;
  }

  function titel(runde, zeile) {
    return beschriftung(runde).titel[String(zeile)] || '';
  }

  function anwesendeSetzen(runde, n) {
    pruefeOffen(runde);
    pruefeAnwesende(n);
    runde.anwesende = n;
  }

  /* Wie viele Personen sind in dieser Zeile schon erfasst? Jeder Zettel zaehlt
     in jeder Zeile mit, als Wert oder als Enthaltung. */
  function erfasst(runde, zeile) {
    return (runde.karten[zeile] || []).length + runde.zettel.length;
  }

  /* So viele Vorschlagszeilen hat die Runde: bis zur hoechsten Nummer, zu der
     irgendetwas erfasst ist. Eine vorgeblaetterte, leere Zeile ist noch keine. */
  function zeilenzahl(runde) {
    let n = 0;
    runde.karten.forEach(function (liste, i) { if (liste.length > 0 && i > n) n = i; });
    runde.zettel.forEach(function (z) { if (z.werte.length - 1 > n) n = z.werte.length - 1; });
    return n;
  }

  function eingabe(runde) {
    const faktor = runde.skala === 5 ? 2 : 1;
    const zeilen = [];
    for (let i = 0; i <= zeilenzahl(runde); i++) {
      const werte = (runde.karten[i] || []).slice();
      runde.zettel.forEach(function (z) { werte.push(i < z.werte.length ? z.werte[i] : null); });
      zeilen.push(i === 0 && !runde.passiv ? [] : werte.map(function (w) {
        return w === null ? null : w * faktor;
      }));
    }
    return { anwesende: runde.anwesende, passiv: runde.passiv, zeilen: zeilen };
  }

  /* Nach dem Abschluss bleiben die Kennzahlen und die Verteilungen bis zur
     naechsten Runde - und in offenen Runden auch die Einzelwerte und Zettel, zum
     Teilen: Wer erst abschliesst und dann teilen will, haette sonst nichts mehr in
     der Hand (0.2.1, nach dem ersten Test). Verdeckt werden sie mit dem Abschluss
     geloescht, wie eine Urne nach der Auszaehlung (V7, Plan 7). Entwurf und
     Bestaetigung gehen immer; nachtragen laesst sich nichts mehr. */
  function abschliessen(sitzung, ergebnis, jetzt) {
    const runde = sitzung.runde;
    pruefeOffen(runde);
    nummernMerken(sitzung);
    runde.ergebnis = ergebnis;
    runde.abgeschlossen = jetzt;
    if (runde.verdeckt) {
      runde.karten = [];
      runde.zettel = [];
    }
    runde.entwurf = {};
    runde.letzterZettel = null;
    sitzung.geaendert = jetzt;
  }

  return {
    HALTBARKEIT_MS: HALTBARKEIT_MS,
    zufallsnummer: zufallsnummer,
    neueSitzung: neueSitzung,
    veraltet: veraltet,
    neueRunde: neueRunde,
    erlaubt: erlaubt,
    wertHinzu: wertHinzu,
    wertZurueck: wertZurueck,
    anzahlVon: anzahlVon,
    anzahlSetzen: anzahlSetzen,
    zeileLeeren: zeileLeeren,
    zettelHinzu: zettelHinzu,
    zettelEntfernen: zettelEntfernen,
    entwurfSetzen: entwurfSetzen,
    entwurfWerte: entwurfWerte,
    entwurfFertig: entwurfFertig,
    BESCHRIFTUNG_MAX: BESCHRIFTUNG_MAX,
    beschriftung: beschriftung,
    themaSetzen: themaSetzen,
    titelSetzen: titelSetzen,
    titel: titel,
    anwesendeSetzen: anwesendeSetzen,
    erfasst: erfasst,
    zeilenzahl: zeilenzahl,
    eingabe: eingabe,
    abschliessen: abschliessen
  };
}));
