/*
 * Zaehlwerk der Konsens-App: dieselbe Rechnung wie lib/Auswertung.php im
 * Werkzeug, uebertragen fuer den Sitzungsraum. Zwei Gruppen, die einmal online
 * und einmal im Raum abstimmen, sollen dieselben Zahlen bekommen; deshalb
 * laufen die Faelle aus tools/faelle.json durch beide Fassungen.
 *
 * Was hier fehlt, fehlt mit Absicht (Spec/01_Vorschlaege.md, 5.2): kein Veto,
 * kein "nur vollstaendige Zettel", keine Namen. Das Zaehlwerk sieht nur Werte
 * von 0 bis 10; Karten und Haende mit 0 bis 5 verdoppelt runde.js vorher.
 */
(function (wurzel, fabrik) {
  if (typeof module === 'object' && module.exports) module.exports = fabrik();
  else wurzel.Zaehlwerk = fabrik();
}(this, function () {
  'use strict';

  /* Wie im Werkzeug voreingestellt. Im Raum ist die Schwelle fast immer
     erfuellt; sie bleibt, damit ein halb erfasster Vorschlag oder eine
     versehentlich angelegte Zeile nicht als Ergebnis durchgeht. */
  const QUORUM_PROZENT = 50;

  /* Unter zwei Bewertungen ist ein Durchschnitt nur die Meinung einer Person.
     Bei genau einer anwesenden Person genuegt eine, sonst waere nie etwas
     belastbar. Wortgleich mit Auswertung::mindestBewertungen(). */
  function mindestBewertungen(wertende, prozent) {
    if (wertende <= 0) return 1;
    const noetig = Math.ceil(wertende * prozent / 100);
    return Math.max(1, Math.min(wertende, Math.max(noetig, Math.min(2, wertende))));
  }

  function pruefeWert(w) {
    if (!Number.isInteger(w) || w < 0 || w > 10) {
      throw new RangeError('Wert ausserhalb von 0 bis 10: ' + String(w));
    }
  }

  /* Die Rangfolge vergleicht auf Tausendstel gerundet wie das Werkzeug. Zwei
     verschiedene Brueche mit Nennern unter 1000 liegen weiter auseinander,
     gleiche ergeben dieselbe Gleitkommazahl - gerundet wird also nur, was
     ohnehin gleich ist, und die Reihenfolge stimmt mit PHP ueberein. */
  function tausendstel(z) {
    return z.mittel === null ? -999 : Math.round(z.mittel * 1000);
  }

  /**
   * eingabe = { anwesende: Zahl, passiv: bool, zeilen: [[Wert oder null, ...], ...] }
   * zeilen[0] gehoert der Passivloesung P und wird ohne P uebergangen; ab
   * zeilen[1] folgen die Vorschlaege mit ihrer Nummer an der Tafel. null ist
   * Enthaltung und zaehlt nicht - auch nicht als 0, denn eine 0 waere die
   * schaerfste Ablehnung.
   */
  function rechnen(eingabe) {
    const wertende = eingabe.anwesende;
    if (!Number.isInteger(wertende) || wertende < 0) {
      throw new RangeError('Anwesende muessen eine ganze Zahl ab 0 sein: ' + String(wertende));
    }
    const mitPassiv = eingabe.passiv === true;
    const roh = eingabe.zeilen || [];
    const noetig = mindestBewertungen(wertende, QUORUM_PROZENT);

    const zeilen = [];
    const anzahl = Math.max(roh.length, mitPassiv ? 1 : 0);
    for (let reihe = mitPassiv ? 0 : 1; reihe < anzahl; reihe++) {
      let summe = 0, bewertet = 0, minimal = 10, niedrig = 0;
      const verteilung = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
      (roh[reihe] || []).forEach(function (w) {
        if (w === null) return;
        pruefeWert(w);
        summe += w;
        bewertet++;
        verteilung[w]++;
        if (w < minimal) minimal = w;
        if (w <= 2) niedrig++;
      });
      zeilen.push({
        nr: reihe === 0 ? 'P' : reihe,
        reihe: reihe,
        istPassiv: reihe === 0,
        summe: summe,
        bewertet: bewertet,
        offen: Math.max(0, wertende - bewertet),
        mittel: bewertet > 0 ? summe / bewertet : null,
        // Ohne eine einzige Bewertung gibt es keinen niedrigsten Wert.
        minimal: bewertet > 0 ? minimal : null,
        niedrig: niedrig,
        verteilung: verteilung,
        beteiligung: wertende > 0 ? bewertet / wertende : 0,
        belastbar: bewertet >= noetig && bewertet > 0
      });
    }

    // Die Passivloesung ist die Messlatte - aber nur, wenn sie selbst belastbar ist.
    const passiv = zeilen.find(function (z) { return z.istPassiv; }) || null;
    const messlatte = passiv !== null && passiv.belastbar ? passiv.mittel : null;

    zeilen.forEach(function (z) {
      if (messlatte === null || z.istPassiv || z.mittel === null) {
        z.kik = null;
        // null heisst ausdruecklich "nicht entscheidbar", nicht "nein".
        z.legitimiert = z.istPassiv ? true : null;
        z.gleichauf = false;
      } else {
        const abstand = z.mittel - messlatte;
        z.kik = abstand;
        // Gleichstand mit dem Nichtstun ist weder besser noch schlechter.
        z.gleichauf = Math.abs(abstand) < 1e-9;
        z.legitimiert = abstand > 1e-9;
      }
    });

    // Belastbare zuerst, groesster Rueckhalt vorn. Bei Gleichstand gewinnt,
    // wer die Zoegernden besser mitnimmt: erst der hoehere Mindestwert, dann
    // weniger Werte bis 2, zuletzt die niedrigere Nummer an der Tafel.
    const sortiert = zeilen.slice().sort(function (a, b) {
      if (a.belastbar !== b.belastbar) return a.belastbar ? -1 : 1;
      return (tausendstel(b) - tausendstel(a))
        || ((b.minimal === null ? 0 : b.minimal) - (a.minimal === null ? 0 : a.minimal))
        || (a.niedrig - b.niedrig)
        || (a.reihe - b.reihe);
    });

    let rang = 0, letzter = null;
    sortiert.forEach(function (z) {
      if (!z.belastbar) { z.rang = null; return; }
      const wert = tausendstel(z);
      if (letzter === null || wert !== letzter) { rang++; letzter = wert; }
      z.rang = rang;
    });

    const siegerZeile = sortiert.find(function (z) {
      return z.belastbar && z.legitimiert !== false;
    }) || null;

    // Nicht im Werkzeug: Entscheidet am Ende nur noch die Nummer, soll die
    // Tafelansicht es sagen, damit die Gruppe selbst entscheidet (5.1). Mit
    // 0 bis 5 kommt das oefter vor. Ein Gleichstand mit P gehoert nicht dazu,
    // denn dort gilt die Regel: Gleichauf mit dem Nichtstun bleibt es dabei.
    let gleichstand = null;
    if (siegerZeile !== null) {
      const gleiche = sortiert.filter(function (z) {
        return z.belastbar && z.legitimiert !== false
          && tausendstel(z) === tausendstel(siegerZeile)
          && z.minimal === siegerZeile.minimal
          && z.niedrig === siegerZeile.niedrig;
      });
      if (gleiche.length > 1) gleichstand = gleiche.map(function (z) { return z.nr; });
    }

    return {
      zeilen: sortiert,
      wertende: wertende,
      noetig: noetig,
      passiv: passiv,
      messlatte: messlatte,
      sieger: siegerZeile === null ? null : siegerZeile.nr,
      gleichstand: gleichstand
    };
  }

  return {
    QUORUM_PROZENT: QUORUM_PROZENT,
    mindestBewertungen: mindestBewertungen,
    rechnen: rechnen
  };
}));
