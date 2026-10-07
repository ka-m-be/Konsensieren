# Konsensieren

Ein kleines Werkzeug für **systemisches Konsensieren** im Web: Vorschläge sammeln,
Zustimmung bewerten, auswerten. Ohne Konto, ohne E-Mail, ohne Datenbankserver.

Gedacht für Gruppen von etwa 5 bis 25 Personen, zum Beispiel Vereine, Teams, Hausgemeinschaften, Familien, die eine Entscheidung treffen wollen, die alle mittragen können.

## Die Idee in Kurzform

Bei einer Mehrheitsentscheidung gewinnt, wofür die meisten sind; eine große Minderheit
kann dabei völlig unter die Räder kommen.

Systemisches Konsensieren dreht die Frage um: Statt ein Kreuz zu setzen, sagt jede Person
zu **jedem** Vorschlag, wie weit sie mitgeht – von 0 („geht gar nicht für mich“) bis 10
(„voll dabei“). Es gewinnt der Vorschlag mit dem breitesten Rückhalt. Zusätzlich steht die
**Passivlösung** („wir ändern nichts“) auf dem Stimmzettel: Nur was mehr Rückhalt hat
als sie, wird wirklich von der Gruppe getragen.

Das Ergebnis ist selten der Lieblingsvorschlag der Mehrheit, dafür meist der, mit dem
am meisten Leute leben können.

> **Wenn du die Methode aus Büchern kennst:** Dort wird *Widerstand* gezählt, und die
> kleinste Zahl gewinnt. Das ist dieselbe Rechnung von der anderen Seite –
> `Widerstand = 10 − Zustimmung`, die Rangfolge ist identisch. Wir fragen lieber danach,
> was trägt, als danach, was stört; das Ergebnis bleibt dasselbe. Der Export führt beide
> Zahlen mit.

## Einfach mal ausprobieren

(Wenn das Tool auf einem Webserver läuft, siehe [INSTALL](INSTALL.md))

Auf der Startseite steht ein Knopf, der eine **Beispiel-Abstimmung** anlegt: „Wohin
fährt die Gruppe?“ – sieben erfundene Leute aus einem Verein, die sich über die
gemeinsame Reise nicht einig sind. Kein Schaubild, sondern eine echte Abstimmung mit
allen Rechten; man kann alles anfassen und nichts kaputtmachen. Wählbar sind drei
Stadien: Vorschläge werden gesammelt, es wird gerade bewertet, oder das Ergebnis
steht. Nach sieben Tagen löscht sie sich von selbst.

Die Zahlen darin sind nicht ausgewürfelt, sondern so gebaut, dass sie den Punkt der
Methode zeigen: Per Handzeichen gewönne der Alpenurlaub mit drei von sieben
Erststimmen – beim Konsensieren gewinnt die Nordsee, ohne eine einzige Höchstnote,
aber ohne dass jemand hinten runterfällt.

## Im Sitzungsraum: die App

Sitzen alle zusammen, braucht es keine Online-Abstimmung. Zum Werkzeug gehört eine App
für den Sitzungsraum: Die Vorschläge stehen an der Tafel, alle zeigen ihre Zustimmung
mit Karten oder Fingern, und die App auf dem Handy der Moderation zählt – nach
denselben Regeln wie online. Sie liegt im Verzeichnis `Konsens-App/`, kennt keine
Namen und hat ihre eigene Anleitung und ihr eigenes Changelog. Ein Umschalter
„Online · Im Raum“ oben auf jeder Seite wechselt zwischen beiden. Über eine
gesicherte Adresse (https) lässt sich die App auf den Home-Bildschirm legen und
startet dann auch ohne Netz. Ein Ergebnis lässt sich als Text teilen, als Datei sichern
oder drucken, mit oder ohne Netz. Sie braucht JavaScript und ist bisher nur auf Deutsch.

## Was es kann

- Drei Phasen: Vorschläge sammeln → bewerten → Ergebnis, mit Terminen, die
  automatisch weiterschalten
- Vorschläge lassen sich **abwandeln**; Abwandlungen erscheinen eingerückt beim Original
- Kommentare an jedem Vorschlag, ein- und ausklappbar
- **Unterstützungs-Schwelle**, damit die Liste vor dem Bewerten nicht ausufert, dazu
  Redaktionswerkzeuge für die verwaltende Person (zusammenführen, vom Zettel nehmen,
  Reihenfolge würfeln)
- Zustimmungsskala 0–10 mit ausdrücklichem Zustand **„noch nicht bewertet“** – was
  offen bleibt, zählt gar nicht, und ganz sicher nicht als 0
- Optionale **Vetos** – eine eigene, begründungspflichtige Handlung, unabhängig vom Wert
- Auswertung mit Rückhalt, Beteiligung, Verteilung, niedrigstem Einzelwert und
  Kraft im Konsens; Export als CSV und JSON. Der niedrigste Wert steht bewusst gleich
  neben dem Durchschnitt: Positiv fragen heißt nicht, Bedenken zu verstecken
- Sichtbarkeit von Zwischenstand, Einzelbewertungen und Namen jederzeit umschaltbar
- Optionaler **Nur-Lesen-Link** auf das Ergebnis für Leute außerhalb der Gruppe
- Deutsch und Englisch
- **Profi-Modus** für Leute, die das Werkzeug kennen: blendet auf der Startseite und in
  der Verwaltung die Erklärungen aus, umschaltbar im Menü oben rechts
- Funktioniert vollständig **ohne JavaScript**; JavaScript verbessert nur die Bedienung

## Datensparsamkeit

Gespeichert werden ausschließlich selbstgewählte Namen, Vorschläge, Kommentare und
Bewertungen. Keine Konten, keine E-Mail-Adressen, keine Benachrichtigungen, keine
Tracker, keine nachgeladenen Ressourcen von fremden Servern. Jede Abstimmung wird nach
Ablauf automatisch gelöscht; das Datum steht auf jeder Seite. Ein Cookie wird nur
gesetzt, wenn jemand die Sprache umschaltet oder den Profi-Modus einschaltet.

Der Zugang läuft über nicht erratbare Links: ein Admin-Link, ein Einladungslink für die
Gruppe und ein persönlicher Link je Person. Wer den Einladungslink hat, kann sich
mehrfach eintragen – für Gruppen, die einander kennen, ist das vertretbar, die
verwaltende Person sieht die Teilnahmeliste und kann Einträge entfernen.

## Technik

PHP ab 7.4 (empfohlen 8.1+), eine SQLite-Datei je Abstimmung, kein Framework, keine
Abhängigkeiten, kein Übersetzungsschritt. Das Repository ist zugleich das Upload-Paket.
Installation siehe [INSTALL.md](INSTALL.md).

Das Impressum kommt aus `Impressum.md` im Hauptverzeichnis, die App hat ihr eigenes in
`Konsens-App/Impressum.md`. Beide entstehen aus einer Vorlage (`Impressum.example.md`,
mit Datenschutzhinweis und Platzhaltern in eckigen Klammern) wie `config.php` aus
`config.example.php`; die ausgefüllten Dateien bleiben außerhalb von Repository und
Quelltextpaket. Sie werden beim Aufruf gerendert; das Werkzeug versteht dafür ein kleines
eigenes Markdown.

Mitgeliefert ist `check.php`, das die Umgebung eines Servers prüft. Weil sein
Bericht Einzelheiten über den Server verrät, ist es gesperrt, solange nicht
daneben eine Datei `check-freischalten.txt` liegt; die Freischaltung läuft nach
60 Minuten von selbst ab. So kann es dauerhaft installiert bleiben.

## Lizenz

MIT, siehe [LICENSE](LICENSE). Benutzen, ändern und weitergeben ist erlaubt, auch
gewerblich; es muss nur der Lizenztext erhalten bleiben.

## Zum Weiterlesen

[Artikel.md](Artikel.md) erklärt die Methode für Leute ohne Vorwissen: was sie will, wie
eine Runde abläuft, wann sie sich lohnt und was bei Störungen passiert. Das Werkzeug
zeigt ihn unter `/artikel`.

Georg Paulus, Siegfried Schrotta, Erich Visotschnig: *Systemisches Konsensieren*.
Siegfried Schrotta, Erich Visotschnig: *Das SK-Prinzip*.
