/*
 * Ergebnisse teilen (Spec/02_Ergebnisse_teilen.md): Aus einer Runde wird ein
 * Klartext, der in Messenger, Mail und Notiz lesbar bleibt und als Markdown
 * durchgeht. Hier steht, was in den Text gehoert, und die Schreibweise der
 * Zahlen, ohne DOM, damit tools/test.js es pruefen kann; die Wege hinaus
 * (Teilen-Blatt, Datei, Zwischenablage, Druck) liegen in app.js.
 *
 * Meldungen, Etiketten und Kennzahlen baut auch die Tafelansicht aus diesen
 * Funktionen: Der geteilte Text soll dasselbe sagen wie der Schirm, Wort fuer
 * Wort, und beides an einer Stelle stehen.
 */
(function (wurzel, fabrik) {
  if (typeof module === 'object' && module.exports) {
    module.exports = fabrik(require('./texte.js'), require('./zaehlwerk.js'), require('./runde.js'));
  } else {
    wurzel.Teilen = fabrik(wurzel.Texte, wurzel.Zaehlwerk, wurzel.Runde);
  }
}(this, function (Texte, Zaehlwerk, Runde) {
  'use strict';

  const t = Texte.t;

  /* Wie number_format im Werkzeug: halbe Zehntel vom Nullpunkt weg. Der kleine
     Zuschlag faengt Brueche wie 23/20 ab, die als Gleitkommazahl knapp unter
     der Haelfte liegen und im Werkzeug aufgerundet erscheinen. */
  function komma(x) {
    const betrag = Math.round(Math.abs(x) * 10 + 1e-7) / 10;
    return (x < 0 && betrag > 0 ? '−' : '') + betrag.toFixed(1).replace('.', ',');
  }
  function vorzeichen(x) {
    const text = komma(x);
    return x > 0 && text !== '0,0' ? '+' + text : text;
  }
  function zwei(n) { return String(n).padStart(2, '0'); }
  function uhrzeit(ms) {
    const d = new Date(ms);
    return zwei(d.getHours()) + ':' + zwei(d.getMinutes());
  }
  function datum(ms) {
    const d = new Date(ms);
    return zwei(d.getDate()) + '.' + zwei(d.getMonth() + 1) + '.' + d.getFullYear() + ', ' + uhrzeit(ms);
  }
  /* Ohne Leerzeichen und Umlaute, mit Datum und Uhrzeit: Mehrere Runden eines
     Abends sollen nebeneinander liegen koennen, ohne sich zu ueberschreiben. */
  function dateiname(ms, endung) {
    const d = new Date(ms);
    return 'konsensieren-raum-' + d.getFullYear() + '-' + zwei(d.getMonth() + 1) + '-' + zwei(d.getDate())
      + '-' + zwei(d.getHours()) + zwei(d.getMinutes()) + '.' + endung;
  }

  function zeilenName(nr) { return nr === 0 || nr === 'P' ? t('zeile.name_p') : t('zeile.name', { n: nr }); }
  function zeilenKurz(nr) { return nr === 0 || nr === 'P' ? 'P' : String(nr); }
  /* Stimmen stehen so da, wie sie gezeigt wurden, bei 0 bis 5 also als Karte;
     Durchschnitt und Kraft im Konsens bleiben auf 0 bis 10 (0.1.4). */
  function stimme(runde, w) { return runde.skala === 5 ? w / 2 : w; }
  function grenze(runde) { return runde.skala === 5 ? 2 : 3; }
  function wert(w) { return w === null ? '–' : String(w); }

  /* Die Lagen der Tafelansicht: dieselben wie im Werkzeug, dazu der voellige
     Gleichstand. wink heisst: als Warnung gefaerbt. */
  function meldungen(runde, erg) {
    const aus = [];
    if (!erg.zeilen.some(function (z) { return z.bewertet > 0; })) aus.push({ text: t('ergebnis.leer'), wink: true });
    else if (erg.passiv !== null && !erg.passiv.belastbar) {
      aus.push({ text: t('ergebnis.passiv_unklar', { n: erg.passiv.bewertet, k: erg.noetig }), wink: true });
    } else if (erg.sieger === 'P') aus.push({ text: t('ergebnis.nur_passiv'), wink: false });
    else if (erg.sieger === null) aus.push({ text: t('ergebnis.kein_sieger'), wink: true });
    if (erg.gleichstand !== null) {
      const namen = erg.gleichstand.map(zeilenName);
      const liste = namen.slice(0, -1).join(', ') + t('ergebnis.und') + namen[namen.length - 1];
      aus.push({ text: t('ergebnis.gleichstand', { liste: liste, g: grenze(runde) }), wink: true });
    }
    return aus;
  }

  /* Die Etiketten einer Zeile, ohne "Passivloesung": Die traegt der Schirm
     selbst, im Text steht sie im Namen der Zeile. */
  function etiketten(erg, z) {
    const aus = [];
    if (erg.sieger === z.nr) aus.push({ text: t('ergebnis.sieger'), klasse: 'sieger' });
    if (!z.belastbar) aus.push({ text: t('ergebnis.zu_wenig'), klasse: 'wink' });
    if (z.bewertet > erg.wertende) aus.push({ text: t('ergebnis.zu_viele'), klasse: 'wink' });
    if (z.gleichauf) aus.push({ text: t('ergebnis.gleichauf'), klasse: '' });
    else if (z.legitimiert === false) aus.push({ text: t('ergebnis.nicht_legitimiert'), klasse: '' });
    return aus;
  }

  /* Der Schnitt allein verschweigt, wer kaum mitkommt; deshalb stehen die
     niedrigste Stimme und die Stimmen unter der Grenze gleich daneben, wie im
     Werkzeug. Enthaltungen stehen als Zahl dabei, zaehlen aber nicht mit. */
  function kennzahlen(runde, erg, z) {
    const aus = [];
    if (z.minimal !== null) aus.push({ text: t('ergebnis.niedrigst', { w: stimme(runde, z.minimal) }), wink: false });
    aus.push({ text: t('ergebnis.unter', { g: grenze(runde), n: z.niedrig }), wink: z.niedrig > 0 });
    aus.push({ text: t('ergebnis.bewertet_von', { n: z.bewertet, g: erg.wertende }), wink: !z.belastbar });
    aus.push({ text: t('ergebnis.enthaltungen', { n: z.offen }), wink: false });
    if (z.kik !== null) aus.push({ text: t('ergebnis.kik', { w: vorzeichen(z.kik) }), wink: false });
    return aus;
  }

  /* Welche Stufen gehen (Plan, 2 und 13): B und C nur in offenen Runden, denn
     verdeckt sieht auch die Moderation keine Einzelwerte. Nach dem Abschluss
     bleiben sie in offenen Runden bis zur naechsten Runde erhalten (0.2.1);
     "abgeschlossen" als Grund gibt es nur noch fuer Runden, die vor 0.2.1
     abgeschlossen wurden und ihre Werte verloren haben. */
  function stufen(runde) {
    const werte = runde.karten.some(function (l) { return l.length > 0; }) || runde.zettel.length > 0;
    const zettel = runde.zettel.length > 0;
    const fertig = runde.abgeschlossen !== null;
    const grundB = runde.verdeckt ? 'verdeckt' : (werte ? null : (fertig ? 'abgeschlossen' : 'leer'));
    const grundC = runde.verdeckt ? 'verdeckt' : (zettel ? null : (fertig && !werte ? 'abgeschlossen' : 'keine_zettel'));
    return {
      a: { moeglich: true, grund: null },
      b: { moeglich: grundB === null, grund: grundB },
      c: { moeglich: grundC === null, grund: grundC }
    };
  }

  /* Was eine Stufe nicht hergibt, faellt auf die naechste darunter zurueck. */
  function stufeMoeglich(runde, stufe) {
    const s = stufen(runde);
    if (stufe === 'c' && !s.c.moeglich) stufe = 'b';
    if (stufe === 'b' && !s.b.moeglich) stufe = 'a';
    return stufe === 'c' || stufe === 'b' ? stufe : 'a';
  }

  /**
   * Der Klartext einer Runde. jetzt gilt fuer den Zwischenstand einer laufenden
   * Runde, eine abgeschlossene traegt ihren Abschluss; fassung kommt aus
   * rahmen.js, damit eine Rueckfrage spaeter zuzuordnen ist.
   */
  /* Die erste Zeile des Textes und der Kopf des Drucks. */
  function kopf(runde) {
    return t(runde.abgeschlossen === null ? 'teilen.kopf_zwischenstand' : 'teilen.kopf');
  }

  /* Die Eckdaten der Runde in einer Zeile: Zeitpunkt, Anwesende, Skala, offen
     oder verdeckt, mit oder ohne Passivloesung. */
  function eckdaten(runde, jetzt) {
    const laeuft = runde.abgeschlossen === null;
    return [
      t(laeuft ? 'teilen.zwischenstand_vom' : 'teilen.runde_vom', { datum: datum(laeuft ? jetzt : runde.abgeschlossen) }),
      t('teilen.anwesende', { g: runde.anwesende }),
      t(runde.skala === 5 ? 'teilen.skala5' : 'teilen.skala10'),
      t(runde.verdeckt ? 'erfassen.verdeckt' : 'erfassen.offen'),
      t(runde.passiv ? 'teilen.mit_passiv' : 'erfassen.ohne_passiv')
    ].join(' · ');
  }

  /* Die Einzelwerte als Tabelle (Plan, 13): je Zeile eine Eingabe, je Spalte ein
     Vorschlag. Leute gibt es meist mehr als Vorschlaege, und so steht es wie auf
     einem Zettel. Zeilen aus Karten und Haenden sind Eingaben in der Reihenfolge
     des Erfassens, keine Personen; wo eine Spalte kuerzer ist, bleibt die Zelle
     leer. Zettel sind je eine Person, stehen unter den Eingaben und tragen in
     Stufe C ihre Nummer. null, wenn die Stufe keine Einzelwerte hat. */
  function tabelle(runde, stufe) {
    stufe = stufeMoeglich(runde, stufe);
    if (stufe === 'a') return null;
    const spalten = [];
    for (let i = runde.passiv ? 0 : 1; i <= Runde.zeilenzahl(runde); i++) spalten.push(i);
    let hoechste = 0;
    spalten.forEach(function (i) { hoechste = Math.max(hoechste, (runde.karten[i] || []).length); });
    const zeilen = [];
    for (let k = 0; k < hoechste; k++) {
      zeilen.push({ kennung: String(k + 1), zettel: false, werte: spalten.map(function (i) {
        const liste = runde.karten[i] || [];
        return k < liste.length ? wert(liste[k]) : '';
      }) });
    }
    runde.zettel.forEach(function (zt, n) {
      zeilen.push({ kennung: stufe === 'c' ? zt.nr : String(hoechste + n + 1), zettel: true, werte: spalten.map(function (i) {
        return i < zt.werte.length ? wert(zt.werte[i]) : '–';
      }) });
    });
    return { spalten: spalten.map(zeilenKurz), zeilen: zeilen, mitNummern: stufe === 'c' };
  }

  function tabellenHinweis(tab) {
    return t('teilen.einzelwerte') + (tab.mitNummern ? ' ' + t('teilen.einzelwerte_zettel') : '');
  }

  /* Der Anhang zu Stufe B und C als Text: die Tabelle mit Strichen, Spalten
     aufgefuellt, damit sie in fester Schrift ausgerichtet steht und als
     Markdown durchgeht. Leer bei Stufe A oder wenn die Runde nichts hergibt. */
  function anhang(runde, stufe) {
    const tab = tabelle(runde, stufe);
    if (tab === null) return '';
    const kopf = [t('teilen.spalte_eingabe')].concat(tab.spalten);
    const reihen = tab.zeilen.map(function (z) { return [z.kennung].concat(z.werte); });
    const breite = kopf.map(function (k, s) {
      return Math.max(3, k.length, reihen.reduce(function (m, r) { return Math.max(m, r[s].length); }, 0));
    });
    const zeile = function (zellen) {
      return '| ' + zellen.map(function (z, s) { return z.padEnd(breite[s]); }).join(' | ') + ' |';
    };
    const aus = [tabellenHinweis(tab), zeile(kopf), zeile(breite.map(function (b) { return '-'.repeat(b); }))];
    reihen.forEach(function (r) { aus.push(zeile(r)); });
    return aus.join('\n');
  }

  function fuss(fassung) {
    return t('teilen.fuss', { v: fassung });
  }

  function text(runde, stufe, jetzt, fassung) {
    const laeuft = runde.abgeschlossen === null;
    const erg = laeuft ? Zaehlwerk.rechnen(Runde.eingabe(runde)) : runde.ergebnis;
    const b = Runde.beschriftung(runde);
    const z = [];

    z.push(kopf(runde));
    z.push(eckdaten(runde, jetzt));
    if (b.thema !== '') z.push(t('teilen.thema_zeile', { thema: b.thema }));
    z.push('');
    z.push(t('teilen.rechnung', { k: erg.noetig }));
    meldungen(runde, erg).forEach(function (m) { z.push(m.text); });
    z.push('');

    erg.zeilen.forEach(function (x) {
      const titel = Runde.titel(runde, x.reihe);
      z.push((x.rang === null ? '–' : x.rang + '.') + ' ' + zeilenName(x.nr) + (titel !== '' ? ' – ' + titel : ''));
      const reihe = [t('teilen.schnitt', { w: x.mittel === null ? '–' : komma(x.mittel) })];
      kennzahlen(runde, erg, x).forEach(function (k) { reihe.push(k.text); });
      z.push('   ' + reihe.join(' · '));
      const marken = etiketten(erg, x).map(function (e) { return e.text; });
      if (marken.length > 0) z.push('   ' + marken.join(' · '));
      const verteilung = [];
      for (let w = 0; w <= 10; w += runde.skala === 5 ? 2 : 1) verteilung.push(stimme(runde, w) + ':' + x.verteilung[w]);
      z.push('   ' + t('teilen.verteilung') + ' ' + verteilung.join(' · '));
    });

    const rest = anhang(runde, stufe);
    if (rest !== '') {
      z.push('');
      z.push(rest);
    }

    z.push('');
    z.push(fuss(fassung));
    return z.join('\n') + '\n';
  }

  return {
    komma: komma,
    vorzeichen: vorzeichen,
    uhrzeit: uhrzeit,
    datum: datum,
    dateiname: dateiname,
    meldungen: meldungen,
    etiketten: etiketten,
    kennzahlen: kennzahlen,
    stufen: stufen,
    stufeMoeglich: stufeMoeglich,
    kopf: kopf,
    eckdaten: eckdaten,
    tabelle: tabelle,
    tabellenHinweis: tabellenHinweis,
    anhang: anhang,
    fuss: fuss,
    text: text
  };
}));
