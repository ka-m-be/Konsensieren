/*
 * Bildschirme der Konsens-App. Rechnen tut zaehlwerk.js, die Runde fuehrt
 * runde.js; hier stehen nur Anzeige, Eingabe und Speicher.
 *
 * Gespeichert wird im Browser des Handys der Moderation, nur Zahlen und keine
 * Namen, damit ein Neuladen oder ein leerer Akku die Runde nicht kostet. Was
 * nach dem Abschluss bleibt und wann alles verschwindet, regelt runde.js (V7).
 */
(function () {
  'use strict';

  const SPEICHER = 'konsens-app.sitzung';
  const SCHIRME = ['start', 'neu', 'erfassen', 'ergebnis', 'teilen'];
  const t = Texte.t;

  let sitzung = null;
  let speicherGeht = true;
  let sperre = null;
  let druckStufe = null;

  function hol(id) { return document.getElementById(id); }

  function el(tag, klasse, text) {
    const e = document.createElement(tag);
    if (klasse) e.className = klasse;
    if (text !== undefined && text !== null) e.textContent = String(text);
    return e;
  }

  function knopf(klasse, text) {
    const k = el('button', klasse, text);
    k.type = 'button';
    return k;
  }

  /* Kurzes Brummen je Eingabe, damit die Moderation den Blick auf dem Raum
     lassen kann. iPhones koennen das im Browser nicht; dort bleibt es still. */
  function brummen() {
    if (navigator.vibrate) navigator.vibrate(12);
  }

  /* ----------------------------------------------------------- Speicher */

  function laden() {
    const jetzt = Date.now();
    try {
      const roh = localStorage.getItem(SPEICHER);
      const s = roh ? JSON.parse(roh) : null;
      if (s && s.fassung === 1 && !Runde.veraltet(s, jetzt)) return s;
      localStorage.removeItem(SPEICHER);
    } catch (e) {
      speicherGeht = false;
    }
    return Runde.neueSitzung(jetzt);
  }

  function sichern() {
    sitzung.geaendert = Date.now();
    try {
      localStorage.setItem(SPEICHER, JSON.stringify(sitzung));
      speicherGeht = true;
    } catch (e) {
      speicherGeht = false;
    }
    hol('speicherwarnung').hidden = speicherGeht;
  }

  /* V7 gilt auch fuer den Seitenbaum: Was die Bildschirme von einer Runde halten,
     verschwindet mit ihr, auch wenn es gerade versteckt ist. Sonst stand nach
     "Sitzung beenden" noch die Bestaetigung eines alten Zettels da (0.1.1). */
  function anzeigeLeeren(auchErgebnis) {
    ['kette', 'zeilenwahl', 'wertzaehler', 'entwurf', 'erfasste', 'zettel-zahl', 'zettel-meldung']
      .forEach(function (id) { hol(id).replaceChildren(); });
    hol('zettel-meldung').hidden = true;
    if (!auchErgebnis) return;
    ['tafel', 'ergebnis-meldungen', 'ergebnis-grundlage', 'abgeschlossen-text', 'ergebnis-thema',
      'druck-kopf', 'druck-anhang', 'druck-fuss', 'teilen-titel', 'teilen-meldung']
      .forEach(function (id) { hol(id).replaceChildren(); });
    hol('ergebnis-thema').hidden = true;
    hol('teilen-meldung').hidden = true;
    hol('teilen-thema').value = '';
    hol('teilen-vorschau').value = '';
  }

  function sitzungBeenden() {
    try { localStorage.removeItem(SPEICHER); } catch (e) { /* nichts zu loeschen */ }
    sitzung = Runde.neueSitzung(Date.now());
    anzeigeLeeren(true);
  }

  /* Wer die App nach Stunden wieder oeffnet, soll keine fremde Runde vorfinden. */
  function verfallPruefen() {
    if (sitzung.runde && Runde.veraltet(sitzung, Date.now())) sitzungBeenden();
  }

  /* --------------------------------------------------------- Navigation */

  function aktuellerSchirm() {
    const s = document.querySelector('.schirm:not([hidden])');
    return s ? s.id : '';
  }

  function zeigen() {
    verfallPruefen();
    const r = sitzung.runde;
    let ziel = location.hash.replace('#', '') || 'start';
    if (SCHIRME.indexOf(ziel) < 0) ziel = 'start';
    if (ziel === 'erfassen' && (!r || r.abgeschlossen !== null)) ziel = r ? 'ergebnis' : 'neu';
    if ((ziel === 'ergebnis' || ziel === 'teilen') && !r) ziel = 'start';
    if ('#' + ziel !== location.hash) history.replaceState(null, '', '#' + ziel);
    const vorher = aktuellerSchirm();
    document.querySelectorAll('.schirm').forEach(function (s) { s.hidden = s.id !== ziel; });
    MALEN[ziel]();
    wachHalten(ziel === 'erfassen');
    if (vorher !== ziel) window.scrollTo(0, 0);
    // "Drucken" auf dem Teilen-Schirm druckt die Tafelansicht mit dem Anhang der
    // dort gewaehlten Stufe: erst hinueber, dann der Druckdialog, sobald sie steht.
    if (druckStufe !== null && ziel === 'ergebnis') {
      malenDruckAnhang(druckStufe);
      druckStufe = null;
      setTimeout(function () { window.print(); }, 50);
    }
  }

  /* Waehrend des Erfassens soll der Bildschirm nicht dunkel werden. Wo der
     Browser das nicht kann, geht es auch ohne. */
  function wachHalten(an) {
    if (!('wakeLock' in navigator)) return;
    if (an && sperre === null && document.visibilityState === 'visible') {
      sperre = 'angefragt';
      navigator.wakeLock.request('screen').then(function (s) {
        sperre = s;
        s.addEventListener('release', function () { sperre = null; });
      }).catch(function () { sperre = null; });
    } else if (!an && sperre !== null && sperre !== 'angefragt') {
      sperre.release().catch(function () {});
      sperre = null;
    }
  }

  /* -------------------------------------------------------------- Start */

  function malenStart() {
    const r = sitzung.runde;
    hol('start-fortsetzen').hidden = !(r && r.abgeschlossen === null);
    hol('start-ergebnis').hidden = !(r && r.abgeschlossen !== null);
    hol('start-moderieren').hidden = !!r;
    hol('start-neu').hidden = !r;
    hol('start-sitzung').hidden = !r;
  }

  /* --------------------------------------------------------- Neue Runde */

  function hatEingaben(r) {
    return r.karten.some(function (liste) { return liste.length > 0; })
      || r.zettel.length > 0 || Object.keys(r.entwurf).length > 0;
  }

  /* Die Einstellungen der vorigen Runde sind die Vorgabe fuer die naechste:
     Meist bleibt in einer Sitzung alles gleich. Vor der ersten Runde ist 0 bis 5
     vorgewaehlt, denn Karten und Haende sind im Raum der Normalfall (0.1.5). */
  function malenNeu() {
    const f = hol('neu-form');
    const v = sitzung.runde || { skala: 5, passiv: true, verdeckt: false, anwesende: '' };
    f.elements.anwesende.value = String(v.anwesende);
    f.querySelector('[name=skala][value="' + v.skala + '"]').checked = true;
    f.elements.passiv.checked = v.passiv;
    f.querySelector('[name=anzeige][value="' + (v.verdeckt ? 'verdeckt' : 'offen') + '"]').checked = true;
    hol('neu-fehler').hidden = true;
  }

  hol('neu-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const f = e.target.elements;
    const n = Number(f.anwesende.value);
    if (!Number.isInteger(n) || n < 1 || n > 99) {
      hol('neu-fehler').hidden = false;
      f.anwesende.focus();
      return;
    }
    const r = sitzung.runde;
    if (r && r.abgeschlossen === null && hatEingaben(r) && !window.confirm(t('bestaetigen.neue_runde'))) return;
    Runde.neueRunde(sitzung, {
      skala: Number(f.skala.value),
      passiv: f.passiv.checked,
      verdeckt: f.anzeige.value === 'verdeckt',
      anwesende: n
    }, Date.now());
    anzeigeLeeren(true);
    sichern();
    location.hash = '#erfassen';
  });

  /* Plus und Minus neben einer Zahl: beim Anlegen im Formular, beim Erfassen
     direkt an der Runde, wenn jemand kommt oder geht. */
  document.addEventListener('click', function (e) {
    const k = e.target.closest('[data-schritt]');
    if (!k) return;
    const schritt = Number(k.dataset.schritt);
    const begrenzt = function (n) { return Math.min(99, Math.max(1, n)); };
    if (k.dataset.fuer === 'anwesende') {
      const r = sitzung.runde;
      Runde.anwesendeSetzen(r, begrenzt(r.anwesende + schritt));
      sichern();
      hol('anwesende-zahl').textContent = String(r.anwesende);
      malenZaehlung(r);
    } else {
      const eingabe = hol(k.dataset.fuer);
      eingabe.value = String(begrenzt((Number(eingabe.value) || 0) + schritt));
      hol('neu-fehler').hidden = true;
    }
  });

  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-aktion="sitzung-beenden"]')) return;
    if (!window.confirm(t('bestaetigen.sitzung_beenden'))) return;
    sitzungBeenden();
    if (location.hash === '#start') zeigen(); else location.hash = '#start';
  });

  /* ----------------------------------------------------------- Erfassen */

  function zeilenName(nr) {
    return nr === 0 || nr === 'P' ? t('zeile.name_p') : t('zeile.name', { n: nr });
  }
  function zeilenKurz(nr) { return nr === 0 || nr === 'P' ? 'P' : String(nr); }
  function ersteZeile(r) { return r.passiv ? 0 : 1; }
  function letzteZeile(r) { return Math.max(Runde.zeilenzahl(r), r.zeile, 1); }

  /* Karte k traegt die Farbe von 2k, wie auf dem gedruckten Blatt. */
  function farbe(r, wert) { return r.skala === 5 ? 2 * wert : wert; }

  function tastenfeld(ziel, r) {
    if (ziel.dataset.skala === String(r.skala)) return;
    ziel.dataset.skala = String(r.skala);
    ziel.className = 'tastenfeld s' + r.skala;
    ziel.replaceChildren();
    for (let w = 0; w <= r.skala; w++) {
      const k = knopf('taste z' + farbe(r, w));
      k.dataset.wert = String(w);
      k.setAttribute('aria-label', w + ' – ' + t('skala.wert' + farbe(r, w)));
      k.appendChild(el('span', 'ziffer', w));
      ziel.appendChild(k);
    }
    const leer = knopf('taste enthaltung');
    leer.dataset.wert = '';
    leer.appendChild(el('span', 'ziffer', '– ' + t('taste.enthaltung')));
    ziel.appendChild(leer);
  }

  function tastenwert(taste) { return taste.dataset.wert === '' ? null : Number(taste.dataset.wert); }

  function malenErfassen() {
    const r = sitzung.runde;
    const info = [t(r.skala === 5 ? 'erfassen.skala5' : 'erfassen.skala10'),
      t(r.verdeckt ? 'erfassen.verdeckt' : 'erfassen.offen')];
    if (!r.passiv) info.push(t('erfassen.ohne_passiv'));
    hol('rundeninfo').textContent = info.join(' · ');
    hol('anwesende-zahl').textContent = String(r.anwesende);
    document.querySelectorAll('.art').forEach(function (k) {
      k.setAttribute('aria-pressed', String(k.dataset.art === r.art));
    });
    const jePerson = r.art === 'person';
    hol('je-zeile').hidden = jePerson;
    hol('feld-person').hidden = !jePerson;
    hol('zeile-leeren').hidden = jePerson;
    if (jePerson) { malenPerson(r); return; }
    hol('zeile-name').textContent = zeilenName(r.zeile);
    hol('zeile-vorher').disabled = r.zeile <= ersteZeile(r);
    hol('weiter').textContent = t('erfassen.weiter', { name: zeilenName(r.zeile + 1) });
    hol('feld-vorschlag').hidden = r.art !== 'vorschlag';
    hol('rueckgaengig').hidden = r.art !== 'vorschlag';
    hol('feld-wert').hidden = r.art !== 'wert';
    if (r.art === 'vorschlag') tastenfeld(hol('tasten-vorschlag'), r);
    else malenWertfeld(r);
    malenZaehlung(r);
  }

  /* Was sich bei jeder Eingabe aendert: Zaehler, Zeilenleiste, Kette. Die
     Eingabefelder bleiben stehen, damit der Fokus beim Tippen nicht springt. */
  function malenZaehlung(r) {
    if (r.art === 'person') { malenPerson(r); return; }
    const n = Runde.erfasst(r, r.zeile);
    const zaehler = hol('zaehler');
    zaehler.textContent = t(n > r.anwesende ? 'erfassen.zu_viele' : 'erfassen.zaehler', { n: n, g: r.anwesende });
    zaehler.classList.toggle('zuviel', n > r.anwesende);

    const leiste = hol('zeilenwahl');
    leiste.replaceChildren();
    let aktuell = null;
    for (let nr = ersteZeile(r); nr <= letzteZeile(r); nr++) {
      const m = Runde.erfasst(r, nr);
      const chip = knopf('chip');
      chip.dataset.zeile = String(nr);
      chip.appendChild(el('b', '', zeilenKurz(nr)));
      chip.appendChild(el('small', '', m + '/' + r.anwesende));
      chip.classList.toggle('voll', m === r.anwesende);
      chip.classList.toggle('zuviel', m > r.anwesende);
      chip.setAttribute('aria-label', t('zeile.chip_aria', { name: zeilenName(nr), n: m, g: r.anwesende }));
      if (nr === r.zeile) {
        chip.classList.add('aktuell');
        chip.setAttribute('aria-current', 'true');
        aktuell = chip;
      }
      leiste.appendChild(chip);
    }
    // Nur waagerecht rollen: Wer unten am Tastenfeld tippt, soll nicht nach
    // oben gerissen werden.
    if (aktuell) leiste.scrollLeft = aktuell.offsetLeft - (leiste.clientWidth - aktuell.offsetWidth) / 2;

    if (r.art === 'vorschlag') {
      const liste = r.karten[r.zeile] || [];
      const kette = hol('kette');
      if (liste.length === 0) {
        kette.textContent = t('erfassen.kette_leer');
      } else {
        // Verdeckt stehen nur Punkte da; wer daneben sitzt, liest nichts mit.
        kette.replaceChildren(el('b', '', liste.map(function (w) {
          return r.verdeckt ? '•' : (w === null ? '–' : String(w));
        }).join(' ')));
      }
    }
  }

  function malenWertfeld(r) {
    hol('wert-hinweis').textContent = t('erfassen.wert_hinweis', { a: r.skala, b: r.skala - 1 });
    const feld = hol('wertzaehler');
    feld.replaceChildren();
    for (let w = r.skala; w >= 0; w--) {
      const zeile = el('div', 'wertzeile');
      const muster = el('span', 'muster z' + farbe(r, w));
      muster.setAttribute('aria-hidden', 'true');
      muster.appendChild(el('span', '', w));
      const eingabe = el('input');
      eingabe.type = 'number';
      eingabe.inputMode = 'numeric';
      eingabe.min = '0';
      eingabe.max = '99';
      eingabe.autocomplete = 'off';
      eingabe.id = 'anzahl-' + w;
      eingabe.dataset.wert = String(w);
      eingabe.value = String(Runde.anzahlVon(r, r.zeile, w));
      eingabe.setAttribute('aria-label', t('erfassen.anzahl_aria', { w: w }));
      const frage = el('label', '', t('erfassen.wer_hat', { w: w }));
      frage.htmlFor = eingabe.id;
      const stepper = el('div', 'stepper');
      const minus = knopf('knopf still stufe', '−');
      minus.dataset.anzahl = '-1';
      minus.dataset.wert = String(w);
      minus.setAttribute('aria-label', t('stepper.weniger'));
      const plus = knopf('knopf still stufe', '+');
      plus.dataset.anzahl = '1';
      plus.dataset.wert = String(w);
      plus.setAttribute('aria-label', t('stepper.mehr'));
      stepper.append(minus, eingabe, plus);
      zeile.append(muster, frage, stepper);
      feld.appendChild(zeile);
    }
  }

  function anzahlAus(eingabe) {
    return Math.max(0, Math.min(99, Math.floor(Number(eingabe.value)) || 0));
  }

  hol('wertzaehler').addEventListener('input', function (e) {
    const eingabe = e.target.closest('input[data-wert]');
    if (!eingabe) return;
    const r = sitzung.runde;
    Runde.anzahlSetzen(r, r.zeile, Number(eingabe.dataset.wert), anzahlAus(eingabe));
    sichern();
    malenZaehlung(r);
  });
  hol('wertzaehler').addEventListener('change', function (e) {
    const eingabe = e.target.closest('input[data-wert]');
    if (eingabe) eingabe.value = String(anzahlAus(eingabe));
  });
  hol('wertzaehler').addEventListener('click', function (e) {
    const k = e.target.closest('[data-anzahl]');
    if (!k) return;
    const r = sitzung.runde;
    const w = Number(k.dataset.wert);
    const n = Math.max(0, Math.min(99, Runde.anzahlVon(r, r.zeile, w) + Number(k.dataset.anzahl)));
    Runde.anzahlSetzen(r, r.zeile, w, n);
    hol('anzahl-' + w).value = String(n);
    brummen();
    sichern();
    malenZaehlung(r);
  });

  hol('tasten-vorschlag').addEventListener('click', function (e) {
    const taste = e.target.closest('.taste');
    if (!taste) return;
    const r = sitzung.runde;
    Runde.wertHinzu(r, r.zeile, tastenwert(taste));
    brummen();
    sichern();
    malenZaehlung(r);
  });

  hol('rueckgaengig').addEventListener('click', function () {
    const r = sitzung.runde;
    Runde.wertZurueck(r, r.zeile);
    sichern();
    malenZaehlung(r);
  });

  function zeileWaehlen(nr) {
    const r = sitzung.runde;
    r.zeile = Math.max(ersteZeile(r), Math.min(99, nr));
    sichern();
    malenErfassen();
  }
  hol('weiter').addEventListener('click', function () { zeileWaehlen(sitzung.runde.zeile + 1); });
  hol('zeile-vorher').addEventListener('click', function () { zeileWaehlen(sitzung.runde.zeile - 1); });
  hol('zeilenwahl').addEventListener('click', function (e) {
    const chip = e.target.closest('.chip');
    if (chip) zeileWaehlen(Number(chip.dataset.zeile));
  });

  hol('zeile-leeren').addEventListener('click', function () {
    const r = sitzung.runde;
    if (!window.confirm(t('bestaetigen.zeile_leeren', { name: zeilenName(r.zeile) }))) return;
    Runde.zeileLeeren(r, r.zeile);
    sichern();
    malenErfassen();
  });

  document.querySelector('.arten').addEventListener('click', function (e) {
    const k = e.target.closest('.art');
    if (!k) return;
    sitzung.runde.art = k.dataset.art;
    sichern();
    malenErfassen();
  });

  /* Je Person: ein ganzer Zettel am Handy der Moderation, etwa wenn es als
     Urne herumgeht. Die Markierung springt nach jedem Tipp weiter, und am Ende
     entsteht von selbst eine neue Zeile - wie beim Zettel in der App (V3). */
  function malenPerson(r) {
    if (typeof r.entwurfZeile !== 'number') r.entwurfZeile = ersteZeile(r);
    hol('person-hinweis').textContent = t(r.verdeckt ? 'erfassen.person_verdeckt' : 'erfassen.person_hinweis');
    const werte = Runde.entwurfWerte(r);
    const bis = Math.max(Runde.zeilenzahl(r), werte.length - 1, r.entwurfZeile, 1);
    const liste = hol('entwurf');
    liste.replaceChildren();
    for (let nr = ersteZeile(r); nr <= bis; nr++) {
      const w = werte[nr];
      const text = w === undefined ? '' : (w === null ? '–' : String(w));
      const li = el('li', nr === r.entwurfZeile ? 'aktuell' : '');
      const k = knopf('');
      k.dataset.zeile = String(nr);
      k.append(el('small', '', zeilenKurz(nr)), el('b', '', text));
      k.setAttribute('aria-label', zeilenName(nr) + ': ' + (w === null ? t('taste.enthaltung') : text));
      if (nr === r.entwurfZeile) k.setAttribute('aria-current', 'true');
      li.appendChild(k);
      liste.appendChild(li);
    }
    tastenfeld(hol('tasten-person'), r);
    const meldung = hol('zettel-meldung');
    meldung.hidden = !r.letzterZettel;
    meldung.textContent = r.letzterZettel ? t('erfassen.zettel_gezaehlt', { nr: r.letzterZettel }) : '';

    const n = r.zettel.length;
    hol('zettel-zahl').textContent = n === 0 ? t('erfassen.zettel_keiner')
      : (n === 1 ? t('erfassen.zettel_eins') : t('erfassen.zettel_zahl', { n: n }));
    const erfasste = hol('erfasste');
    erfasste.replaceChildren();
    erfasste.hidden = r.verdeckt;
    hol('zettel-zuruecknehmen').hidden = !r.verdeckt || n === 0;
    if (r.verdeckt) return;
    r.zettel.forEach(function (z) {
      const teile = [];
      z.werte.forEach(function (w, i) {
        if (i > 0 || r.passiv) teile.push(zeilenKurz(i) + ' ' + (w === null ? '–' : w));
      });
      const li = el('li');
      const weg = knopf('knopf still klein', t('erfassen.zettel_loeschen'));
      weg.dataset.nr = z.nr;
      li.append(el('code', '', z.nr), el('span', '', teile.join(' · ')), weg);
      erfasste.appendChild(li);
    });
  }

  hol('entwurf').addEventListener('click', function (e) {
    const k = e.target.closest('[data-zeile]');
    if (!k) return;
    sitzung.runde.entwurfZeile = Number(k.dataset.zeile);
    sichern();
    malenPerson(sitzung.runde);
  });

  hol('tasten-person').addEventListener('click', function (e) {
    const taste = e.target.closest('.taste');
    if (!taste) return;
    const r = sitzung.runde;
    Runde.entwurfSetzen(r, r.entwurfZeile, tastenwert(taste));
    r.entwurfZeile = Math.min(99, r.entwurfZeile + 1);
    brummen();
    sichern();
    malenPerson(r);
  });

  hol('zettel-fertig').addEventListener('click', function () {
    const r = sitzung.runde;
    const nr = Runde.entwurfFertig(sitzung);
    if (nr === null) return;
    r.entwurfZeile = ersteZeile(r);
    brummen();
    sichern();
    malenPerson(r);
  });

  hol('zettel-verwerfen').addEventListener('click', function () {
    const r = sitzung.runde;
    if (Object.keys(r.entwurf).length > 0 && !window.confirm(t('bestaetigen.entwurf_verwerfen'))) return;
    r.entwurf = {};
    r.entwurfZeile = ersteZeile(r);
    sichern();
    malenPerson(r);
  });

  hol('erfasste').addEventListener('click', function (e) {
    const k = e.target.closest('[data-nr]');
    if (!k || !window.confirm(t('bestaetigen.zettel_loeschen', { nr: k.dataset.nr }))) return;
    Runde.zettelEntfernen(sitzung.runde, k.dataset.nr);
    sichern();
    malenPerson(sitzung.runde);
  });

  hol('zettel-zuruecknehmen').addEventListener('click', function () {
    const r = sitzung.runde;
    if (r.zettel.length === 0 || !window.confirm(t('bestaetigen.zettel_zuruecknehmen'))) return;
    Runde.zettelEntfernen(r, r.zettel[r.zettel.length - 1].nr);
    sichern();
    malenPerson(r);
  });

  /* ----------------------------------------------------------- Ergebnis */

  /* Zahlen und Zeiten schreibt teilen.js, damit Schirm und geteilter Text
     dieselbe Schreibweise haben. */

  function verteilung(r, z) {
    const werte = [];
    for (let w = 0; w <= 10; w += r.skala === 5 ? 2 : 1) werte.push(w);
    const hoechster = Math.max(1, Math.max.apply(null, werte.map(function (w) { return z.verteilung[w]; })));
    // Bei 0 bis 5 sechs Balken mit den Kartenwerten: so, wie es gezeigt wurde.
    const name = function (w) { return String(r.skala === 5 ? w / 2 : w); };
    // Ueber jedem Balken die Anzahl, darunter der Wert, darueber die Summe: So
    // laesst sich das Bild von der Tafel abschreiben, ohne zu zaehlen (0.1.3).
    const block = el('div', 'verteilung-block');
    block.appendChild(el('p', 'verteilung-summe',
      t(z.bewertet === 1 ? 'ergebnis.abgegeben_eins' : 'ergebnis.abgegeben', { n: z.bewertet })));
    const bild = el('div', 'verteilung');
    bild.setAttribute('role', 'img');
    bild.setAttribute('aria-label', t('ergebnis.verteilung_alt', {
      w: werte.map(function (w) { return name(w) + ': ' + z.verteilung[w]; }).join(', ')
    }));
    werte.forEach(function (w) {
      const balken = el('span', 'balken z' + w + ' h' + Math.round(z.verteilung[w] / hoechster * 10));
      balken.append(el('b', '', z.verteilung[w]), el('i'), el('small', '', name(w)));
      bild.appendChild(balken);
    });
    block.appendChild(bild);
    return block;
  }

  function malenErgebnis() {
    const r = sitzung.runde;
    const laeuft = r.abgeschlossen === null;
    const erg = laeuft ? Zaehlwerk.rechnen(Runde.eingabe(r)) : r.ergebnis;
    hol('lage-laeuft').hidden = !laeuft;
    hol('lage-fertig').hidden = laeuft;
    if (!laeuft) {
      hol('abgeschlossen-text').textContent = t('ergebnis.abgeschlossen', { zeit: Teilen.uhrzeit(r.abgeschlossen) })
        + ' ' + t(r.verdeckt ? 'ergebnis.abgeschlossen_verdeckt' : 'ergebnis.abgeschlossen_offen');
    }
    // Nur im Druck zu sehen: Kopf und Fuss wie im geteilten Text; der Anhang
    // kommt erst mit "Drucken" vom Teilen-Schirm (malenDruckAnhang).
    const druckKopf = hol('druck-kopf');
    druckKopf.replaceChildren(el('p', '', Teilen.kopf(r)), el('p', '', Teilen.eckdaten(r, Date.now())));
    hol('druck-fuss').textContent = Teilen.fuss(Rahmen.FASSUNG);
    hol('druck-anhang').replaceChildren();
    hol('ergebnis-grundlage').textContent = t('ergebnis.grundlage', { g: erg.wertende, k: erg.noetig })
      + (r.skala === 5 ? ' ' + t('ergebnis.verdoppelt') : '');

    // Dieselben Lagen wie im Werkzeug, dazu der voellige Gleichstand. Die Saetze
    // baut teilen.js, damit der geteilte Text dasselbe sagt wie der Schirm.
    const meldungen = hol('ergebnis-meldungen');
    meldungen.replaceChildren();
    Teilen.meldungen(r, erg).forEach(function (m) {
      meldungen.appendChild(el('p', 'meldung' + (m.wink ? ' wink' : ''), m.text));
    });
    const thema = Runde.beschriftung(r).thema;
    hol('ergebnis-thema').textContent = thema;
    hol('ergebnis-thema').hidden = thema === '';

    const tafel = hol('tafel');
    tafel.replaceChildren();
    erg.zeilen.forEach(function (z) {
      const li = el('li', 'tafelzeile');
      li.classList.toggle('sieger', erg.sieger === z.nr);
      li.classList.toggle('unsicher', !z.belastbar);
      li.classList.toggle('passiv', z.istPassiv);
      // Ohne Kreis und ohne Platzziffer davor: Die Reihenfolge ist die Rangfolge,
      // und die Nummer ist der Name an der Tafel, klein beschriftet (0.1.4).
      const nummer = el('div', 'nummer');
      nummer.append(el('small', '', t('ergebnis.vorschlag')), el('b', '', zeilenKurz(z.nr)));
      nummer.setAttribute('role', 'img');
      nummer.setAttribute('aria-label', zeilenName(z.nr));

      const kopf = el('div', 'kopfzeile');
      const stufe = z.mittel === null ? 5 : Math.max(0, Math.min(10, Math.round(z.mittel)));
      const schnitt = el('span', 'schnitt z' + stufe);
      // Die Zahl steht ohne Wort da; Vorleseprogramme hoeren "Durchschnitt".
      schnitt.append(el('span', 'versteckt', t('ergebnis.durchschnitt') + ' '),
        el('strong', '', z.mittel === null ? '–' : Teilen.komma(z.mittel)));
      kopf.appendChild(schnitt);
      const etikett = function (text, klasse) { kopf.appendChild(el('span', 'etikett' + (klasse ? ' ' + klasse : ''), text)); };
      if (z.istPassiv) etikett(t('zeile.name_p'), 'passiv');
      Teilen.etiketten(erg, z).forEach(function (e) { etikett(e.text, e.klasse); });

      // Die Kennzahlen neben dem Schnitt baut teilen.js, dieselbe Reihe wie im
      // geteilten Text: niedrigste Stimme, Stimmen unter der Grenze, bewertet
      // von, Enthaltungen, Kraft im Konsens.
      const zahlen = el('p', 'kennzahlen');
      Teilen.kennzahlen(r, erg, z).forEach(function (k) { zahlen.appendChild(el('span', k.wink ? 'wink' : '', k.text)); });

      const haupt = el('div', 'haupt');
      // Der Titel aus der Beschriftung, wenn es einen gibt (Spec/02, 5).
      const titel = Runde.titel(r, z.reihe);
      if (titel !== '') haupt.appendChild(el('p', 'titel', titel));
      haupt.append(kopf, zahlen, verteilung(r, z));
      li.append(nummer, haupt);
      tafel.appendChild(li);
    });
  }

  hol('abschliessen').addEventListener('click', function () {
    if (!window.confirm(t(sitzung.runde.verdeckt ? 'bestaetigen.abschliessen_verdeckt' : 'bestaetigen.abschliessen'))) return;
    Runde.abschliessen(sitzung, Zaehlwerk.rechnen(Runde.eingabe(sitzung.runde)), Date.now());
    anzeigeLeeren(false);
    sichern();
    malenErgebnis();
    window.scrollTo(0, 0);
  });

  /* ------------------------------------------------------------- Teilen */

  /* Der Schirm zum Teilen (Spec/02_Ergebnisse_teilen.md, 5): Stufe waehlen,
     beschriften, den Text sehen und auf einem der Wege hinausgeben. Was hinaus
     geht, baut teilen.js; hier stehen nur Schirm und Wege. */

  function stufeGewaehlt() {
    const e = document.querySelector('#teilen-stufen input:checked');
    return e ? e.value : 'a';
  }

  function teilenText() {
    return Teilen.text(sitzung.runde, stufeGewaehlt(), Date.now(), Rahmen.FASSUNG);
  }

  function malenVorschau() {
    hol('teilen-vorschau').value = teilenText();
  }

  /* Einzelwerte und Zettel unter der Tafel, nur im Druck (Spec/02, 13): dieselbe
     Tabelle wie im Text, als HTML mit Linien, je Zeile eine Eingabe oder ein Zettel. */
  function malenDruckAnhang(stufe) {
    const tab = Teilen.tabelle(sitzung.runde, stufe);
    const ziel = hol('druck-anhang');
    ziel.replaceChildren();
    if (tab === null) return;
    ziel.appendChild(el('p', 'dazu', Teilen.tabellenHinweis(tab)));
    const tabelle = el('table', 'einzelwerte');
    const kopf = el('tr');
    kopf.appendChild(el('th', '', t('teilen.spalte_eingabe')));
    tab.spalten.forEach(function (s) { kopf.appendChild(el('th', '', s)); });
    tabelle.appendChild(el('thead')).appendChild(kopf);
    const rumpf = el('tbody');
    tab.zeilen.forEach(function (z) {
      const tr = el('tr', z.zettel ? 'zettel' : '');
      const erste = el('th', '', z.kennung);
      erste.setAttribute('scope', 'row');
      tr.appendChild(erste);
      z.werte.forEach(function (w) { tr.appendChild(el('td', '', w)); });
      rumpf.appendChild(tr);
    });
    tabelle.appendChild(rumpf);
    ziel.appendChild(tabelle);
  }

  function teilenMeldung(text) {
    const m = hol('teilen-meldung');
    m.textContent = text;
    m.hidden = false;
  }

  function malenTeilen() {
    const r = sitzung.runde;
    const s = Teilen.stufen(r);
    ['b', 'c'].forEach(function (k) {
      const eingabe = document.querySelector('#teilen-stufen input[value="' + k + '"]');
      eingabe.disabled = !s[k].moeglich;
      eingabe.closest('.option').classList.toggle('aus', !s[k].moeglich);
      hol('teilen-stufe-' + k + '-dazu').textContent = s[k].moeglich ? t('teilen.stufe_' + k + '_dazu') : t('teilen.grund_' + s[k].grund);
      if (!s[k].moeglich && eingabe.checked) {
        eingabe.checked = false;
        document.querySelector('#teilen-stufen input[value="a"]').checked = true;
      }
    });

    // Beschriftung: ein Feld je Zeile der Auswertung. Die Zeilen kommen aus dem
    // Ergebnis, denn verdeckt sind nach dem Abschluss die Einzelwerte weg.
    hol('teilen-thema').value = Runde.beschriftung(r).thema;
    const erg = r.abgeschlossen === null ? Zaehlwerk.rechnen(Runde.eingabe(r)) : r.ergebnis;
    const felder = hol('teilen-titel');
    felder.replaceChildren();
    erg.zeilen.map(function (z) { return z.reihe; }).sort(function (a, b) { return a - b; }).forEach(function (nr) {
      const feld = el('div', 'feld');
      const beschriftung = el('label', '', zeilenName(nr));
      beschriftung.htmlFor = 'titel-' + nr;
      const eingabe = el('input');
      eingabe.type = 'text';
      eingabe.id = 'titel-' + nr;
      eingabe.maxLength = Runde.BESCHRIFTUNG_MAX;
      eingabe.autocomplete = 'off';
      eingabe.dataset.zeile = String(nr);
      eingabe.value = Runde.titel(r, nr);
      feld.append(beschriftung, eingabe);
      felder.appendChild(feld);
    });

    // Nur die Wege, die dieser Browser kann. Teilen-Blatt und Zwischenablage
    // gibt er nur ueber https oder localhost frei (Plan, 1.1 und 1.3).
    hol('teilen-blatt').hidden = typeof navigator.share !== 'function';
    hol('teilen-kopieren').hidden = !(navigator.clipboard && typeof navigator.clipboard.writeText === 'function');
    hol('teilen-unsicher').hidden = window.isSecureContext === true;
    hol('teilen-meldung').hidden = true;
    malenVorschau();
  }

  hol('teilen-stufen').addEventListener('change', malenVorschau);

  hol('teilen-thema').addEventListener('input', function (e) {
    Runde.themaSetzen(sitzung.runde, e.target.value);
    sichern();
    malenVorschau();
  });

  hol('teilen-titel').addEventListener('input', function (e) {
    const feld = e.target.closest('input[data-zeile]');
    if (!feld) return;
    Runde.titelSetzen(sitzung.runde, Number(feld.dataset.zeile), feld.value);
    sichern();
    malenVorschau();
  });

  hol('teilen-blatt').addEventListener('click', function () {
    navigator.share({ title: t('teilen.kopf'), text: teilenText() }).then(function () {
      teilenMeldung(t('teilen.geteilt'));
    }, function (e) {
      // Das Blatt zugemacht: kein Fehler, sondern eine Entscheidung.
      if (e && e.name === 'AbortError') return;
      teilenMeldung(t('teilen.fehlgeschlagen'));
    });
  });

  hol('teilen-datei').addEventListener('click', function () {
    const name = Teilen.dateiname(Date.now(), 'txt');
    const url = URL.createObjectURL(new Blob([teilenText()], { type: 'text/plain;charset=utf-8' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = name;
    document.body.appendChild(a);
    a.click();
    a.remove();
    // Safari auf dem iPhone oeffnet die Adresse womoeglich erst nach dem Klick;
    // deshalb nicht sofort wieder freigeben.
    setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
    teilenMeldung(t('teilen.gesichert', { name: name }));
  });

  hol('teilen-kopieren').addEventListener('click', function () {
    navigator.clipboard.writeText(teilenText()).then(function () {
      teilenMeldung(t('teilen.kopiert'));
    }, function () {
      // Rueckfall: den Text markieren, den Rest tut die Person.
      hol('teilen-vorschau').focus();
      hol('teilen-vorschau').select();
      teilenMeldung(t('teilen.markiert'));
    });
  });

  hol('teilen-drucken').addEventListener('click', function () {
    druckStufe = stufeGewaehlt();
    location.hash = '#ergebnis';
  });

  /* -------------------------------------------------------------- Beginn */

  const MALEN = { start: malenStart, neu: malenNeu, erfassen: malenErfassen, ergebnis: malenErgebnis, teilen: malenTeilen };

  window.addEventListener('hashchange', zeigen);
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState !== 'visible') return;
    if (sitzung.runde && Runde.veraltet(sitzung, Date.now())) { zeigen(); return; }
    wachHalten(aktuellerSchirm() === 'erfassen');
  });

  sitzung = laden();
  hol('speicherwarnung').hidden = speicherGeht;
  zeigen();
}());
