# Änderungen der Konsens-App

Die Offline-App für den Sitzungsraum hat eine eigene Fassungsnummer und dieses
eigene Changelog. Das Werkzeug führt seines in `../CHANGELOG.md`.

## 0.2.3 – 2026-09-13 · Menü oben

- **Ein Menü rechts in der Kopfzeile**, hinter dem Stapel-Symbol, wie im Werkzeug:
  Anleitung und Karten drucken. Die Fußzeile nennt nur noch Quelltext, Impressum und
  Fassung. Die Seite, auf der man ist, steht im Menü hervorgehoben. Ein Klick daneben
  oder Escape schließt es. Der große Knopf „Karten drucken“ auf der Startseite bleibt.
- **Prüfungen:** `test.js` prüft Menü und Fußzeile auf allen fünf Seiten und dass der
  CSS-Block des Menüs wortgleich mit dem Werkzeug ist; `durchlauf.js` öffnet und
  schließt das Menü im Browser und prüft die Markierung auf dem Kartenblatt.

## 0.2.2 – 2026-09-13 · Einzelwerte als Tabelle

Die Einzelwerte stehen im geteilten Text und im Druck als eine Tabelle: je Zeile eine
Eingabe, je Spalte ein Vorschlag, so wie ein Zettel aussieht, denn Leute gibt es meist
mehr als Vorschläge. Zeilen aus Karten und Händen sind Eingaben in der Reihenfolge des
Erfassens, keine Personen; Zettel stehen darunter, je Zettel eine Person, und tragen in
der Stufe „Dazu die Zettel“ ihre Nummer. Der zweite Block für die Zettel entfällt. Im
Text ist es eine Markdown-Tabelle mit ausgerichteten Spalten, im Druck eine Tabelle mit
Linien; die Karten der Tafel bekommen im Druck kräftigere Ränder.

## 0.2.1 – 2026-09-13 · Nach dem ersten Test des Teilens

- **Einzelwerte und Zettel bleiben nach dem Abschluss.** In offenen Runden löscht
  „Runde abschließen“ sie nicht mehr; sie bleiben zum Teilen, bis die nächste Runde
  beginnt oder die Sitzung endet. Vorher stand, wer erst abschloss und dann teilen
  wollte, ohne sie da. Abschließen heißt jetzt nur noch: nichts mehr nachtragen. In
  verdeckten Runden werden sie weiter mit dem Abschluss gelöscht. Rückfrage, Meldung
  und Anleitung sagen je nach Runde, was gilt.
- **Der Druck hat Kopf und Anhang.** Über der Tafelansicht stehen im Druck der Kopf und
  die Eckdaten wie im Text, darunter die Einzelwerte und Zettel, wenn sie auf dem
  Teilen-Schirm gewählt sind, am Ende die Fassung.
- **Impressum als Vorlage.** `Impressum.example.md` ist die Vorlage im Paket; das
  ausgefüllte `Impressum.md` bleibt außerhalb von Repository und Quelltextpaket. Fehlt
  es, zeigt die App die Vorlage, auch ohne Netz.
- **Prüfungen:** `test.js` und `durchlauf.js` prüfen das Bleiben der Werte in offenen und
  das Löschen in verdeckten Runden, den Druck mit Anhang und die Vorlage im Vorrat.

## 0.2.0 – 2026-09-13 · Ergebnisse teilen

Der Prototyp zu Meilenstein 2 (Plan in `Spec/02_Ergebnisse_teilen.md`, Schritte 1
und 2), zum Test im lokalen Netz:

- **„Teilen“ in der Tafelansicht**, während die Runde läuft und nach dem Abschluss.
  Der Schirm dahinter baut einen Klartext, der in Messenger, Mail und Notiz lesbar
  bleibt: Eckdaten der Runde, je Zeile Durchschnitt, niedrigste Stimme, Stimmen unter
  der Grenze, Beteiligung, Enthaltungen, Kraft im Konsens, Etiketten und Verteilung,
  dazu die Meldungen der Tafelansicht. Er sagt Wort für Wort dasselbe wie der Schirm;
  Meldungen, Etiketten und Kennzahlen kommen für beide aus `teilen.js`.
- **Drei Stufen:** nur das Ergebnis; dazu die Einzelwerte je Zeile; dazu die Zettel mit
  Nummer. Die beiden letzten gibt es nur in offenen Runden und nur, solange die Runde
  läuft, denn mit dem Abschluss löscht die App die Einzelwerte. Die Rückfrage vor dem
  Abschluss sagt das jetzt dazu.
- **Vier Wege:** das Teilen-Blatt des Handys, „Als Text sichern“ (eine Datei
  `konsensieren-raum-JJJJ-MM-TT-HHMM.txt`), Kopieren in die Zwischenablage und immer
  das Textfeld zum Markieren. Dazu „Drucken“, das die Tafelansicht druckt; auf dem Handy
  wird daraus ein PDF. Die App zeigt nur die Knöpfe, die der Browser kann, und sagt über
  `http`, dass Teilen-Blatt und Zwischenablage `https` brauchen.
- **Beschriftung, freiwillig:** ein Thema und je Zeile ein Titel, höchstens 80 Zeichen.
  Sie stehen im Text und in der Tafelansicht, bleiben in der Runde bis zum Ende der
  Sitzung und lassen sich auch nach dem Abschluss noch eintippen.
- **Fassung 0.2.0**, weil der Speicher der Runde ein Feld dazubekommt; ältere Runden im
  Speicher laufen weiter.
- Noch nicht gebaut, Schritt 3 des Plans: JSON im Format des Werkzeugs und „alle Runden
  dieser Sitzung“.
- **Prüfungen:** `test.js` prüft Beschriftung, Stufen, Text in allen drei Stufen, vor und
  nach dem Abschluss, verdeckt, Dateiname und Zahlenschreibweise; `durchlauf.js` den
  Schirm mit Vorschau, Beschriftung, den Knöpfen je Browser und dem Sichern.

## 0.1.5 – 2026-09-13 · Impressum, 0 bis 5 zuerst

- **Impressum** in der Fußzeile jeder Seite. Es kommt aus `Impressum.md`, einer
  Grundfassung mit Platzhaltern zum Anpassen, und wird von `markdown.js` gerendert,
  demselben Renderer wie im Werkzeug. Der Service Worker hält es im Vorrat, also steht
  es auch ohne Netz.
- **0 bis 5 steht zuerst** in der Wahl „Womit wird gezeigt?“ und ist vor der ersten
  Runde vorgewählt: Karten und Hände sind im Raum der Normalfall. Die Einstellungen der
  vorigen Runde bleiben wie bisher die Vorgabe für die nächste.
- **„Testfälle prüfen“ ist aus der Fußzeile verschwunden.** Die Prüfseite bleibt unter
  `pruefen.html` erreichbar; sie ist etwas für die Entwicklung, nicht für den
  Sitzungsraum.
- Die Anleitung verlinkt den Artikel über die Methode im Online-Werkzeug.
- **Prüfungen:** `test.js` schickt die gemeinsamen Markdown-Fälle durch `markdown.js` und
  prüft Reihenfolge und Vorgabe der Skala, Fußzeile und Vorrat; `durchlauf.js` öffnet
  das Impressum mit und ohne Server.

## 0.1.4 – 2026-09-12 · Die Tafelansicht, aufgeräumt

Rückmeldungen aus dem Sitzungsraum zur Tafelansicht:

- **Ohne Platzziffer, ohne Kreis.** Die Ziffer für den Rang links entfällt, denn die
  Reihenfolge ist die Rangfolge. Die Nummer des Vorschlags steht ohne Kreis da, darüber
  sehr klein „Vorschlag“.
- **Die große Zahl steht allein.** Das Wort „Rückhalt“ daneben entfällt; Vorleseprogramme
  hören „Durchschnitt“.
- **Passivlösung statt Nichtstun.** Die Etiketten heißen „weniger Zustimmung als
  Passivlösung“ und „gleichauf mit der Passivlösung“; Meldungen und Erklärungen sprechen
  ebenso.
- **Stimmen, wie sie gezeigt wurden.** Aus „niedrigster Wert“ wird „niedrigste Stimme“,
  aus „Werte bis 2“ wird „Stimmen <2“. Bei 0 bis 5 stehen beide in Karten: „Stimmen <2“
  sind die Karten 0 und 1, und der Satz, der das oben erklärte, entfällt. Bei 0 bis 10
  heißt die Grenze „<3“. Durchschnitt und Kraft im Konsens bleiben auf 0 bis 10.
- **Enthaltungen als Zahl** in jeder Zeile, neben „bewertet von“.

## 0.1.3 – 2026-09-12 · Auf den Home-Bildschirm, und Balken zum Ablesen

- **Auf den Home-Bildschirm und ohne Netz.** Die App bringt jetzt ein Manifest, ein
  Symbol und einen Service Worker mit. Einmal geöffnet, lässt sie sich auf Android über
  „App installieren“ und auf dem iPhone über „Teilen“ und „Zum Home-Bildschirm“ ablegen
  und startet danach ohne Netz. Vorher war das nur ein Lesezeichen, das jedes Mal den
  Server brauchte. Das geht nur über eine gesicherte Adresse (https); so wollen es die
  Browser. Aus Meilenstein 3 vorgezogen, ohne den QR-Leser.
- **Balken zum Ablesen.** In der Tafelansicht steht über jedem Balken, wie viele diesen
  Wert gezeigt haben, darunter der Wert und darüber die Zahl der abgegebenen
  Bewertungen. Die Balken haben eine Kontur und stehen auf einer Grundlinie; vorher war
  der Balken für die höchste Zustimmung vor dem Grund kaum zu sehen. Das Werkzeug
  zeichnet das Bild genauso.

## 0.1.2 – 2026-09-12 · Anleitung, und die App gehört zum Werkzeug

- **Anleitung.** Eine eigene Seite erklärt, was die App ist, wann sie passt und wann
  das Online-Werkzeug, wie man eine Runde vorbereitet und führt, wie man das Ergebnis
  liest und was die App sich merkt. Sie ist von jeder Seite der App aus erreichbar, und
  das Werkzeug verlinkt sie auf seiner Start- und Hilfeseite.
- **Eine Suite mit dem Werkzeug.** Oben auf jeder Seite steht derselbe Umschalter
  „Online · Im Raum“ wie im Werkzeug und führt hinüber; die Kopfzeile kommt aus
  demselben Stück Stylesheet. Die Fußzeile nennt auf allen Seiten Anleitung, Karten,
  Testfälle, Quelltext und Fassung.
- Die Startseite der App verweist auf die Anleitung und, für Gruppen, die nicht
  beisammen sind, aufs Online-Werkzeug.
- **Für die Entwicklung:** `tools/durchlauf.js` klickt die App in Chrome ohne Fenster
  durch: eine Runde mit allen drei Erfassungsarten, den Sitzungswechsel aus 0.1.1, die
  Prüfseite, die Adresse ohne Schrägstrich und den Druck des Kartenblatts. So bleiben
  auch Fehler festgehalten, die nur auf dem Bildschirm stecken.

## 0.1.1 – 2026-09-12 · zwei Fehler aus dem ersten Versuch

- **Adresse ohne Schrägstrich.** Wer die App als `…/Konsens-App` statt `…/Konsens-App/`
  aufrief, bekam eine leere Seite: Der Browser suchte Stil und Skripte dann eine Ebene zu
  hoch. Apache leitet solche Adressen selbst um, der eingebaute Server von PHP nicht.
  Jetzt steht in diesem Fall ein Hinweis mit einem Link zur richtigen Adresse da. Selbst
  umleiten kann die Seite nicht, denn dafür bräuchte sie ein Skript im HTML, und das
  verbietet ihre Content-Security-Policy.
- **Alte Bestätigung in der neuen Sitzung.** Nach „Sitzung beenden“ stand beim Erfassen
  je Person noch „Zettel … zählt“ vom letzten Zettel der alten Sitzung da, auch verdeckt,
  und ebenso nach einer neuen Runde. Gelöscht war der Zettel längst; stehen geblieben war
  nur die Meldung auf dem Bildschirm. Jetzt gehört die Bestätigung zur Runde und
  verschwindet mit ihr. Außerdem leeren „Sitzung beenden“, eine neue Runde und der
  Abschluss alles, was die Bildschirme versteckt noch von der alten Runde hielten, und
  die Eingabefelder bitten den Browser, sich keine Werte zu merken.

## 0.1.0 – 2026-09-12 · Zählen mit Karten und Handzeichen

Die erste Fassung kann alles, was eine Runde braucht, in der nur die Moderation ein
Handy hat. Im Plan (`Spec/01_Vorschlaege.md`) ist das Stufe 0 und Meilenstein 1.

- **Neue Runde:** Wie viele sind da, wird mit 0 bis 10 oder mit 0 bis 5 gezeigt,
  steht die Passivlösung P mit an der Tafel, offen oder verdeckt. Bei nur einem
  Vorschlag erinnert die App daran, erst nach Einwänden zu fragen.
- **Erfassen auf drei Arten.** *Je Vorschlag:* Karten oder Finger hoch, und du tippst
  die gezeigten Werte ein, ein Tipp je Person, mit Zähler und Rückgängig. Die Tasten
  sehen aus wie die Karten. *Je Wert:* „Wer hat 5? Wer hat 4?“, du trägst ein, wie
  viele Hände oben sind; das geht auch in größeren Runden. *Je Person:* ein ganzer
  Zettel am Stück, etwa wenn das Handy als Urne herumgeht. Die Zeilen entstehen beim
  Erfassen, eine Zahl der Vorschläge gibt niemand ein.
- **Tafelansicht:** Rang, Nummer, Rückhalt im Schnitt, niedrigster Wert, Werte bis 2,
  bewertet von, Kraft im Konsens und die Verteilung als Balken, groß genug zum
  Abschreiben. Gerechnet wird wie online, auch mit 0 bis 5: Die App verdoppelt die
  Kartenwerte, bevor sie rechnet. Liegen die ersten beiden völlig gleichauf und
  entscheidet nur noch die Nummer, sagt die Tafelansicht das dazu.
- **Wenig Gedächtnis:** Die App kennt keine Namen. Mit „Runde abschließen“ sind die
  Einzelwerte gelöscht, Kennzahlen und Verteilungen bleiben bis zur nächsten Runde.
  „Sitzung beenden“ löscht alles, und nach zwölf Stunden ohne Eingabe geschieht das
  von selbst.
- **Karten drucken:** sechs Karten von 0 bis 5 auf einem A4-Blatt zum Ausschneiden,
  in Farbe oder Schwarz-Weiß. Die Ziffer steht schwarz auf Weiß, die Farbe aus der
  Skala des Werkzeugs sitzt im Rahmen, die Karten 0 und 1 sind zusätzlich schraffiert.
- **Testfälle prüfen:** Die Seite rechnet dieselben Fälle durch wie das Werkzeug
  (`tools/faelle.json`); `tools/test.php` im Werkzeug und `tools/test.js` hier
  schicken sie durch beide Fassungen.

Noch nicht dabei: QR-Stimmzettel von Handy zu Handy (Meilenstein 2) und die
Installation für den Betrieb ganz ohne Netz (Meilenstein 3). Bis dahin muss die App
einmal aus dem Netz geladen sein. Geht das Netz danach weg, läuft sie weiter, nur neu
laden lässt sie sich dann nicht; die erfasste Runde liegt im Speicher des Handys und
ist nach dem Neuladen wieder da.
