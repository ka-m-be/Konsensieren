/*
 * Verbesserungen fuer den Browser. Alles Wesentliche funktioniert auch ohne:
 * die Bewertung sind Radiofelder, jede Aktion ist ein normales Formular.
 */
(function () {
  'use strict';

  /* Menue oben (Spezifikation 19): ohne JavaScript ein details-Element, das mit
     dem Stapel-Knopf auf- und zugeht. Hier nur, was man von einem Menue erwartet:
     Es geht zu bei einem Klick daneben und mit Escape. */
  document.querySelectorAll('.menue').forEach(function (menue) {
    document.addEventListener('click', function (e) {
      if (menue.open && !menue.contains(e.target)) menue.open = false;
    });
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape' || !menue.open) return;
      menue.open = false;
      menue.querySelector('summary').focus();
    });
  });

  /* Links kopieren */
  document.querySelectorAll('.kopieren').forEach(function (knopf) {
    knopf.addEventListener('click', function () {
      var feld = document.getElementById(knopf.dataset.ziel);
      if (!feld) return;
      feld.select();
      var fertig = function () {
        var alt = knopf.textContent;
        knopf.textContent = '✓';
        setTimeout(function () { knopf.textContent = alt; }, 1200);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(feld.value).then(fertig, function () {});
      } else if (document.execCommand) {
        document.execCommand('copy');
        fertig();
      }
    });
  });

  /* Sicherheitsabfragen. Als Datenattribut, weil die Content-Security-Policy
     keine Inline-Handler zulaesst - ohne JavaScript wird eben nicht nachgefragt. */
  document.querySelectorAll('form[data-bestaetigen]').forEach(function (formular) {
    formular.addEventListener('submit', function (e) {
      if (!window.confirm(formular.dataset.bestaetigen)) e.preventDefault();
    });
  });

  /* Linkfelder beim Antippen ganz markieren */
  document.querySelectorAll('[data-alles-markieren]').forEach(function (feld) {
    feld.addEventListener('focus', function () { feld.select(); });
  });

  /* Skala: gewaehlten Wert hervorheben und in Worten anzeigen */
  document.querySelectorAll('.skala').forEach(function (skala) {
    var wort = skala.querySelector('.skala-wort');
    var zeigen = function () {
      skala.querySelectorAll('.wert').forEach(function (w) {
        var eingabe = w.querySelector('input');
        w.classList.toggle('gewaehlt', eingabe.checked);
        if (eingabe.checked && wort) {
          var versteckt = w.querySelector('.versteckt');
          wort.textContent = versteckt ? versteckt.textContent : '';
        }
      });
    };
    skala.addEventListener('change', zeigen);
    zeigen();
  });

  /* Ungespeicherte Bewertungen nicht verlieren */
  var bewertungsform = document.getElementById('bewertungsform');
  if (bewertungsform) {
    var schmutzig = false;
    // Die Felder haengen per form-Attribut am Formular, sitzen im Baum aber
    // ausserhalb - deshalb hoeren wir am Dokument mit.
    document.addEventListener('change', function (e) {
      if (e.target && e.target.form === bewertungsform) schmutzig = true;
    });
    bewertungsform.addEventListener('submit', function () { schmutzig = false; });
    window.addEventListener('beforeunload', function (e) {
      if (schmutzig) { e.preventDefault(); e.returnValue = ''; }
    });
  }

  /* Erklaertexte: ob sie auf- oder zugeklappt sind, bleibt erhalten.
     Der Schluessel haengt am Text der Zusammenfassung, nicht an der Position -
     so passt es auch, wenn eine Seite andere Abschnitte bekommt. */
  document.querySelectorAll('details.hinweis > summary').forEach(function (summary) {
    var details = summary.parentNode;
    var name = 'konsensieren.hinweis.' + summary.textContent.trim().slice(0, 40);
    try {
      var stand = localStorage.getItem(name);
      if (stand === 'auf') details.open = true;
      if (stand === 'zu') details.open = false;
    } catch (e) {}
    details.addEventListener('toggle', function () {
      try { localStorage.setItem(name, details.open ? 'auf' : 'zu'); } catch (e) {}
    });
  });

  /* Persoenlichen Link auf diesem Geraet merken - nur auf ausdruecklichen Wunsch */
  var schluessel = 'konsensieren.gemerkt';
  var lesen = function () {
    try { return JSON.parse(localStorage.getItem(schluessel) || '[]'); }
    catch (e) { return []; }
  };
  var schreiben = function (liste) {
    try { localStorage.setItem(schluessel, JSON.stringify(liste)); } catch (e) {}
  };

  var merken = document.getElementById('merken');
  if (merken) {
    var link = merken.dataset.link;
    var titel = merken.dataset.titel;
    merken.checked = lesen().some(function (e) { return e.link === link; });
    merken.addEventListener('change', function () {
      var liste = lesen().filter(function (e) { return e.link !== link; });
      if (merken.checked) liste.push({ link: link, titel: titel, zeit: Date.now() });
      schreiben(liste);
    });
  }

  /* Auf der Startseite die gemerkten Abstimmungen anbieten */
  var kasten = document.getElementById('gemerkt');
  if (kasten) {
    var liste = lesen();
    if (liste.length) {
      var ul = document.createElement('ul');
      liste.sort(function (a, b) { return b.zeit - a.zeit; }).forEach(function (e) {
        var li = document.createElement('li');
        var a = document.createElement('a');
        a.href = e.link;
        a.textContent = e.titel || e.link;
        li.appendChild(a);
        ul.appendChild(li);
      });
      kasten.appendChild(ul);
      kasten.hidden = false;
    }
  }
})();
