# Installation

Es braucht nur PHP und ein Verzeichnis auf einem Webserver. Kein Shell-Zugang,
kein Datenbankserver, kein Composer, kein Build.

## 1. Umgebung prüfen

`check.php` per sFTP in das gewünschte Verzeichnis laden und im Browser aufrufen,
zum Beispiel `https://example.org/konsens/check.php`. Das Skript prüft PHP-Version,
Erweiterungen, Schreibrechte, SQLite, PATH_INFO und ob `.htaccess` wirkt.

Der Bericht verrät Einzelheiten über den Server, deshalb ist das Skript von Haus
aus **gesperrt** und antwortet nur mit einem Hinweis. Zum Freischalten legt man
neben `check.php` eine Datei

    check-freischalten.txt

an – der Inhalt ist egal, eine leere Datei genügt – und lädt die Seite neu. Die
Freischaltung gilt **60 Minuten** und läuft danach von selbst wieder ab.

Nötig sind:

|               |                                                          |
| ------------- | -------------------------------------------------------- |
| PHP           | 7.4 oder neuer, empfohlen 8.1+                           |
| Erweiterungen | `pdo_sqlite`, `json`, empfohlen `mbstring`               |
| Schreibrechte | im Installationsverzeichnis, für das Verzeichnis `data/` |

Nach dem Blick auf den Bericht am besten gleich die Freischaltdatei löschen.
`check.php` selbst kann liegen bleiben: Ohne Freischaltung gibt es nichts preis,
und es gehört zum Quelltextpaket, das die Installation zum Herunterladen anbietet.
Wer es lieber ganz loswerden möchte, kann es natürlich auch löschen – dann fehlt
es allerdings im Paket.

## 2. Dateien hochladen

Den gesamten Ordner per sFTP hochladen, gerne in ein Unterverzeichnis.

    index.php  lib/  tpl/  assets/  lang/  Konsens-App/  .htaccess  config.example.php  check.php
    Impressum.example.md  Artikel.md

`Konsens-App/` ist die App für den Sitzungsraum: statische Dateien, kein PHP. Das
Werkzeug verlinkt sie in der Kopfzeile und auf Start- und Hilfeseite. Wer sie nicht
anbieten will, lässt das Verzeichnis weg; dann verschwinden die Verweise von selbst.

Auf den Home-Bildschirm legen und ohne Netz starten lässt sich die App nur, wenn die
Installation über `https` erreichbar ist; so wollen es die Browser. Die `.htaccess`
der App liefert dafür `.webmanifest` mit dem passenden Typ aus. Auf nginx prüfen, ob
`.webmanifest` als `application/manifest+json` ankommt.

Das Verzeichnis `data/` legt die Anwendung beim ersten Aufruf selbst an. Falls das
scheitert, von Hand anlegen und beschreibbar machen (0775, notfalls 0777).

## 3. Konfiguration

Läuft auch ohne. Für eigene Werte `config.example.php` nach `config.php` kopieren
und anpassen:

| Einstellung         | Bedeutung                                                                                                                 |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| `zeitzone`          | Zeitzone aller Anzeigen. Vorgabe `Europe/Berlin`. Wird bewusst **nicht** vom Server geerbt – viele Server laufen auf UTC. |
| `datenverzeichnis`  | Wo die Abstimmungen liegen. **Am besten außerhalb des Webroots**, etwa `dirname(__DIR__) . '/konsens-daten'`.             |
| `anlegen_kennwort`  | Leer = jede und jeder darf Abstimmungen anlegen. Ein Wort eintragen, um das zu beschränken.                               |
| `beispiel_erlauben` | Darf von der Start- und der Hilfeseite aus eine Beispiel-Abstimmung zum Ausprobieren angelegt werden? Vorgabe `true`. Ist ein `anlegen_kennwort` gesetzt, gilt es auch fürs Beispiel. |
| `beispiel_tage`     | Wie lange eine Beispiel-Abstimmung lebt, Vorgabe 7 Tage. Danach löscht sie sich von selbst.                               |
| `max_laufzeit_tage` | Höchstlaufzeit einer Abstimmung, Vorgabe 92 Tage.                                                                         |
| `aufbewahrung_tage` | Wie lange das Ergebnis nach dem Ende abrufbar bleibt, danach wird gelöscht.                                               |

Danach die Startseite aufrufen – fertig.

## Adressen mit oder ohne `index.php`

Liegt die `.htaccess` und wertet der Server sie aus, sehen die Adressen so aus:

    https://example.org/konsens/a/ABCDEFGHJK.XXXX…

Sonst schaltet die Anwendung von selbst auf die Form mit `index.php` um:

    https://example.org/konsens/index.php/a/ABCDEFGHJK.XXXX…

Beide funktionieren gleich gut; die zweite ist nur länger. Die Anwendung rät dabei
nicht, sondern prüft: Die `.htaccess` setzt ein Kennzeichen, und nur wenn das beim
PHP ankommt, entstehen kurze Adressen. Kommt eine Seite wie `/konsens/hilfe` mit
**404** zurück, fehlt die `.htaccess` oder der Server wertet sie nicht aus –
`check.php` sagt es in der Zeile *„.htaccess der Anwendung“*.

Erzwingen lässt sich beides in `config.php` über `schoene_links` (`true`, `false`
oder `'auto'`).

Ein Hinweis für den Fall, dass schon Links verteilt wurden: Eine kurze Adresse
lässt sich jederzeit in die lange übersetzen, indem man hinter dem
Installationsverzeichnis `index.php/` einfügt – der Schlüssel bleibt derselbe.

## Sicherheit der Daten

`data/` wird durch eine mitgelieferte `.htaccess` gesperrt, und die Dateinamen
enthalten Zufall. **Auf nginx wirkt `.htaccess` nicht.** Dort das Datenverzeichnis
entweder außerhalb des Webroots legen (siehe `datenverzeichnis`) oder in der
Server-Konfiguration sperren:

    location ~ ^/konsens/data/ { deny all; }

`check.php` sagt, was der Server tatsächlich tut.

## Kein Cron nötig – aber ein Hinweis

Phasenwechsel und das Löschen abgelaufener Abstimmungen passieren beim Aufruf der
Seiten, höchstens alle sechs Stunden einmal. Wenn eine Installation über Wochen
niemand aufruft, wird auch nichts gelöscht. Zwei Möglichkeiten:

- In der Verwaltung einer Abstimmung gibt es den Knopf **„Jetzt aufräumen“**.
- Falls vorhanden, einen Uptime-Monitor oder Cron einmal täglich die Startseite
  abrufen lassen. Mehr braucht es nicht.

## Sichern

Alles steckt in `data/`. Ein Kopieren des Verzeichnisses per sFTP genügt als Sicherung;
eine laufende Abstimmung sollte man dabei besser nicht mitten im Zugriff erwischen.
Einzelne Abstimmungen lassen sich in der Verwaltung als CSV oder JSON exportieren.

## Aktualisieren

Neue Dateien über die alten legen. `config.php` und `data/` bleiben unberührt.
Installierte Apps auf den Handys holen sich den neuen Stand von selbst, sobald sie mit
Netz gestartet werden; zu sehen ist er ab dem nächsten Öffnen.

## Impressum

Wer dieses Werkzeug öffentlich anbietet, ist für Impressum und Datenschutzhinweis
zuständig. Dafür liegen zwei Vorlagen bei, nach dem Muster von `config.example.php`:

    Impressum.example.md              →  Impressum.md               (Werkzeug, Seite /impressum)
    Konsens-App/Impressum.example.md  →  Konsens-App/Impressum.md   (App, Fußzeile)

Die Vorlage kopieren, in der Kopie die Platzhalter in eckigen Klammern ersetzen, Absätze
streichen, die nicht zutreffen, den kursiven Vorspann löschen, hochladen. Die Zahlen zur
Löschfrist müssen zu `config.php` passen. Solange es kein `Impressum.md` gibt, zeigen
Werkzeug und App die Vorlage mit ihren Platzhaltern. Das ausgefüllte `Impressum.md`
bleibt außerhalb des Repositorys und des Quelltextpakets; das Paket enthält nur die
Vorlagen. Geschrieben wird in Markdown: Überschriften mit `#`, Listen mit `-`, fett mit
`**`, Links als `[Text](Adresse)`; rohes HTML kommt nicht durch.

## Betrieb

Die Anwendung selbst speichert nur, was in der README steht – die Zugriffs-
protokolle des Webservers liegen außerhalb ihrer Reichweite und sollten kurz
aufbewahrt werden.

## Ausprobieren und Entwickeln

    php -S 127.0.0.1:8788 -t .
    php tools/testdaten.php 8 6 bewertung   # Beispiel-Abstimmung mit Links
    php tools/test.php                      # Tests der Rechen- und Phasenlogik
    sh  tools/smoketest.sh                  # Durchlauf durch die Weboberfläche
    node Konsens-App/tools/test.js          # Tests der App
    node Konsens-App/tools/durchlauf.js     # die App im Browser, braucht Chrome

`tools/test.php`, der Smoketest und `tools/testdaten.php` legen Abstimmungen in `data/`
an. Auf einer echten Installation also nicht laufen lassen; zum Entwickeln am besten
gegen eine Kopie ohne `data/`.
