#!/bin/sh
# Durchlauf durch die Weboberflaeche gegen einen laufenden Server.
#   php -S 127.0.0.1:8788 -t .   und dann   sh tools/smoketest.sh
# Legt eine Beispiel-Abstimmung an, klickt sie durch und raeumt sie weg.
set -u
BASIS="${1:-http://127.0.0.1:8788/index.php}"
TMP=$(mktemp -d)
GUT=0; SCHLECHT=0

pruefe() { # pruefe "Was" "ist" "soll"
  if [ "$2" = "$3" ]; then GUT=$((GUT+1)); else
    SCHLECHT=$((SCHLECHT+1)); printf '  FEHLER  %s\n          erwartet: %s\n          bekommen: %s\n' "$1" "$3" "$2"
  fi
}
hole()  { curl -s -o "$TMP/s.html" -w "%{http_code}" "$1"; }
token() { curl -s "$1" | grep -o 'name="token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
enthaelt() { grep -qF "$2" "$TMP/s.html" && echo ja || echo nein; }

# Der Durchlauf provoziert bewusst Fehlversuche; damit die Bremse nicht nach
# mehreren Laeufen anspringt, wird ihr Zaehler vorher zurueckgesetzt.
rm -rf "$(dirname "$0")/../data/zaehler"

echo "Weboberflaeche gegen $BASIS"

php "$(dirname "$0")/testdaten.php" 6 5 bewertung > "$TMP/links.txt" 2>&1 || { cat "$TMP/links.txt"; exit 1; }
ADMIN=$(grep '^Verwaltung' "$TMP/links.txt" | sed 's/.*: //')
EINL=$(grep '^Einladung' "$TMP/links.txt" | sed 's/.*: //')
USER=$(grep -m1 '  Anke' "$TMP/links.txt" | awk '{print $2}')
POLLID=$(echo "$ADMIN" | sed 's|.*/a/||;s|\..*||')

echo "\nSeiten erreichbar"
for pfad in "" /redaktion /teilnehmer /einstellungen /protokoll /ergebnis; do
  pruefe "admin$pfad" "$(hole "$ADMIN$pfad")" 200
done
for pfad in /vorschlaege /bewerten /ergebnis /ich; do
  pruefe "user$pfad" "$(hole "$USER$pfad")" 200
done
pruefe "einladung" "$(hole "$EINL")" 200
pruefe "hilfe" "$(hole "$BASIS/hilfe")" 200
pruefe "quelltext" "$(hole "$BASIS/quelltext")" 200
pruefe "impressum" "$(hole "$BASIS/impressum")" 200
pruefe "artikel" "$(hole "$BASIS/artikel")" 200
pruefe "startseite" "$(hole "$BASIS")" 200

echo "\nFalsche Schluessel werden abgewiesen"
pruefe "erfundener Schluessel" "$(hole "$BASIS/u/$POLLID.AAAAAAAAAAAAAAAAAAAAAAAAAA")" 403
pruefe "unbekannte Abstimmung" "$(hole "$BASIS/a/ZZZZZZZZZZ.AAAAAAAAAAAAAAAAAAAAAAAAAA")" 404
pruefe "Unfug im Pfad" "$(hole "$BASIS/u/kaputt")" 404
pruefe "unbekannte Seite" "$(hole "$BASIS/gibtsnicht")" 404
pruefe "Adminweg mit Userschluessel" "$(hole "$(echo "$USER" | sed 's|/u/|/a/|')")" 403

echo "\nFormular ohne gueltiges Token"
code=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$USER/bewerten" -d "token=falsch" -d "aktion=bewerten")
pruefe "abgewiesen" "$code" 403

echo "\nBewerten"
T=$(token "$USER/bewerten")
IDS=$(curl -s "$USER/bewerten" | grep -oE 'name="wert\[[0-9]+\]"' | grep -oE '[0-9]+' | sort -un)
ARGS=""
for i in $IDS; do ARGS="$ARGS -d wert%5B$i%5D=4"; done
# shellcheck disable=SC2086
curl -s -o /dev/null -X POST "$USER/bewerten" -d "token=$T" -d "aktion=bewerten" $ARGS
hole "$USER/bewerten" > /dev/null
gesetzt=$(grep -c 'value="4" checked' "$TMP/s.html")
pruefe "alle Werte gespeichert" "$gesetzt" "$(echo "$IDS" | wc -l | tr -d ' ')"

echo "\nBeitritt"
T=$(token "$EINL")
curl -s -o "$TMP/b.txt" -D "$TMP/b.head" -X POST "$EINL" --data-urlencode "token=$T" --data-urlencode "name=Zora"
pruefe "neuer Zugang" "$(grep -ci '^location:.*\/u\/' "$TMP/b.head")" 1
curl -s -o "$TMP/s.html" -X POST "$EINL" --data-urlencode "token=$T" --data-urlencode "name=Zora"
pruefe "Name nur einmal" "$(enthaelt x 'benutzt schon jemand')" ja

echo "\nVorschlagsphase"
php "$(dirname "$0")/testdaten.php" 4 2 vorschlag > "$TMP/l2.txt" 2>&1
VADMIN=$(grep '^Verwaltung' "$TMP/l2.txt" | sed 's/.*: //')
VUSER=$(grep -m1 '  Bernd' "$TMP/l2.txt" | awk '{print $2}')
T=$(token "$VUSER/vorschlaege")
curl -s -o /dev/null -X POST "$VUSER/vorschlaege" -d "token=$T" -d "aktion=vorschlag_neu" \
     --data-urlencode "titel=Ein frischer Vorschlag" --data-urlencode "text=Mit Erläuterung." -d "mit_namen=1"
hole "$VUSER/vorschlaege" > /dev/null
pruefe "Vorschlag erscheint" "$(enthaelt x 'Ein frischer Vorschlag')" ja
pruefe "mit Namen" "$(enthaelt x 'von Bernd')" ja
NEUID=$(grep -B4 'Ein frischer Vorschlag' "$TMP/s.html" | grep -oE 'tiefe0' >/dev/null && \
        curl -s "$VUSER/vorschlaege" | grep -oE 'name="primaer"' >/dev/null && echo ok || echo ok)

# Abwandlung eines bestehenden Vorschlags
ERSTE=$(curl -s "$VUSER/vorschlaege" | grep -oE '<option value="[0-9]+"' | sed -n 2p | grep -oE '[0-9]+')
T=$(token "$VUSER/vorschlaege")
curl -s -o /dev/null -X POST "$VUSER/vorschlaege" -d "token=$T" -d "aktion=vorschlag_neu" \
     --data-urlencode "titel=Abwandlung davon" -d "primaer=$ERSTE" -d "mit_namen=1"
hole "$VUSER/vorschlaege" > /dev/null
pruefe "Abwandlung wird eingerückt" "$(grep -c 'vorschlag tiefe1' "$TMP/s.html")" 1

# Unterstuetzen und kommentieren
T=$(token "$VUSER/vorschlaege")
curl -s -o /dev/null -X POST "$VUSER/vorschlaege" -d "token=$T" -d "aktion=unterstuetzen" -d "id=$ERSTE" -d "ja=1"
curl -s -o /dev/null -X POST "$VUSER/vorschlaege" -d "token=$T" -d "aktion=kommentar" -d "id=$ERSTE" \
     --data-urlencode "text=Das halte ich für zu teuer." -d "mit_namen=1"
hole "$VUSER/vorschlaege" > /dev/null
pruefe "Kommentar erscheint" "$(enthaelt x 'Das halte ich für zu teuer.')" ja

# Redaktion
T=$(token "$VADMIN/redaktion")
curl -s -o /dev/null -X POST "$VADMIN" -d "token=$T" -d "aktion=redaktion_entfernen" -d "id=$ERSTE"
hole "$VADMIN/redaktion" > /dev/null
pruefe "vom Stimmzettel genommen" "$(enthaelt x 'nicht auf dem Stimmzettel')" ja
curl -s -o /dev/null -X POST "$VADMIN" -d "token=$T" -d "aktion=redaktion_zurueckholen" -d "id=$ERSTE"
curl -s -o /dev/null -X POST "$VADMIN" -d "token=$T" -d "aktion=redaktion_notiz" -d "id=$ERSTE" \
     --data-urlencode "notiz=Deckt sich mit Vorschlag 2"
hole "$VUSER/vorschlaege" > /dev/null
pruefe "Redaktionsnotiz sichtbar" "$(enthaelt x 'Deckt sich mit Vorschlag 2')" ja
curl -s -o /dev/null -X POST "$VADMIN" -d "token=$T" -d "aktion=mischen"
hole "$VADMIN/redaktion" > /dev/null
pruefe "Reihenfolge gewürfelt" "$(enthaelt x 'gewürfelte Reihenfolge')" ja
curl -s -o /dev/null -X POST "$VADMIN" -d "token=$T" -d "aktion=reihenfolge_neu"
hole "$VADMIN/redaktion" > /dev/null
pruefe "wieder chronologisch" "$(enthaelt x 'neuesten Vorschläge oben')" ja

# Aufraeumen dieser zweiten Abstimmung
T=$(token "$VADMIN/einstellungen")
curl -s -o /dev/null -X POST "$VADMIN" -d "token=$T" -d "aktion=loeschen" -d "sicher=LOESCHEN"

echo "\nNeuer Zugangslink"
hole "$ADMIN/teilnehmer" > /dev/null
pruefe "Knopf in der Liste" "$(enthaelt x 'teilnehmer_neuer_link')" ja
pruefe "kein Schluessel im Klartext in der Liste" "$(grep -c '/u/' "$TMP/s.html")" 0
# Bewusst die letzte Person: der Durchlauf arbeitet weiter mit Ankes altem Link,
# und der wuerde durch einen neuen Schluessel ungueltig.
BID=$(grep -oE 'name="id" value="[0-9]+"' "$TMP/s.html" | tail -1 | grep -oE '[0-9]+')
T=$(token "$ADMIN/teilnehmer")
curl -s -o "$TMP/s.html" -X POST "$ADMIN" -d "token=$T" -d "aktion=teilnehmer_neuer_link" -d "id=$BID"
pruefe "neuer Link wird einmal angezeigt" "$(grep -c 'id="neuerlink"' "$TMP/s.html")" 1
NEU=$(grep -oE 'value="http[^"]*/u/[^"]*"' "$TMP/s.html" | head -1 | sed 's/value="//;s/"//')
pruefe "und er funktioniert" "$(hole "$NEU")" 200

echo "\nOeffentlicher Ergebnis-Link"
T=$(token "$ADMIN")
curl -s -o /dev/null -X POST "$ADMIN" -d "token=$T" -d "aktion=ergebnislink_neu"
ERG=$(curl -s "$ADMIN" | grep -oE 'http[^"]*/e/[A-Z0-9.]+' | head -1)
pruefe "vor der Ergebnisphase gesperrt" "$(hole "$ERG")" 403
curl -s -o /dev/null -X POST "$ADMIN" -d "token=$T" -d "aktion=schalter" -d "feld=ergebnis_vorab"
pruefe "nach Freigabe erreichbar" "$(hole "$ERG")" 200
pruefe "zeigt keine Namensspalte" "$(enthaelt x 'class="matrix"')" nein
pruefe "zeigt kein Kommentarfeld" "$(enthaelt x 'kommentarform')" nein
pruefe "zeigt die Auswertung" "$(enthaelt x 'ergebnisliste')" ja

echo "\nHTML und CSP"
hole "$BASIS" > /dev/null
pruefe "Stylesheet mit Versionsstempel" "$(grep -cE 'app\.css\?v=[0-9]+' "$TMP/s.html")" 1
pruefe "Skript mit Versionsstempel" "$(grep -cE 'app\.js\?v=[0-9]+' "$TMP/s.html")" 1
hole "$USER/bewerten" > /dev/null 2>&1 || true
curl -s -o "$TMP/s.html" "$USER/vorschlaege"
pruefe "keine Inline-Handler (CSP)" "$(grep -cE 'on(submit|click|focus|change)=' "$TMP/s.html")" 0
pruefe "keine Inline-Styles (CSP)" "$(grep -c 'style="' "$TMP/s.html")" 0
verschachtelt=$(php -r '
  $h = file_get_contents($argv[1]); $t = 0; $max = 0;
  foreach (preg_split("/(<form|<\/form)/i", $h, -1, PREG_SPLIT_DELIM_CAPTURE) as $stueck) {
      if (stripos($stueck, "<form") === 0 && stripos($stueck, "</") !== 0) { $t++; $max = max($max, $t); }
      elseif (stripos($stueck, "</form") === 0) { $t--; }
  }
  echo $max;' "$TMP/s.html")
pruefe "keine verschachtelten Formulare" "$verschachtelt" 1

echo "\nImpressum und Artikel aus Markdown"
hole "$BASIS/impressum" > /dev/null
pruefe "Impressum ist gerendert" "$(enthaelt x '<h1>Impressum</h1>')" ja
pruefe "Impressum im Seitentitel" "$(enthaelt x '<title>Impressum · ')" ja
hole "$BASIS/artikel" > /dev/null
pruefe "Artikel ist gerendert" "$(enthaelt x 'Vereinsheim')" ja
pruefe "Artikel mit Tabelle" "$(enthaelt x '<table>')" ja
pruefe "Artikel mit seinen Quellen" "$(enthaelt x 'href="https://sk-prinzip.eu/methode/"')" ja
pruefe "nur das eigene Skript auf der Seite" "$(grep -c '<script' "$TMP/s.html")" 1
hole "$BASIS" > /dev/null
pruefe "Fuss verlinkt das Impressum" "$(enthaelt x '/impressum"')" ja
pruefe "Startseite verlinkt den Artikel" "$(enthaelt x '/artikel"')" ja
hole "$BASIS/hilfe" > /dev/null
pruefe "Hilfeseite verlinkt den Artikel" "$(enthaelt x '/artikel"')" ja

echo "\nProfi-Modus"
hole "$BASIS" > /dev/null
pruefe "Prozent steht neben dem Feld" "$(grep -c 'class="einheit"' "$TMP/s.html")" 2
pruefe "ohne Profi-Modus: Beispiel und Raumkarte da" "$(enthaelt x 'beispielkarte')$(enthaelt x 'raumkarte')" jaja
pruefe "ohne Profi-Modus: Erklaerkasten da" "$(enthaelt x 'Was ist das?')" ja
pruefe "Schalter zum Einschalten im Menue" "$(enthaelt x '?profi=an')" ja
curl -s -o "$TMP/s.html" -D "$TMP/p.head" "$BASIS?profi=an"
pruefe "?profi=an setzt ein Cookie" "$(grep -ci '^set-cookie: profi=1' "$TMP/p.head")" 1
pruefe "und wirkt schon auf dieser Seite" "$(enthaelt x 'class="profi"')" ja
curl -s -o "$TMP/s.html" -b "profi=1" "$BASIS"
pruefe "Profi-Modus: kein Beispiel, keine Raumkarte" "$(enthaelt x 'beispielkarte')$(enthaelt x 'raumkarte')" neinnein
pruefe "Profi-Modus: kein Erklaerkasten" "$(enthaelt x 'Was ist das?')" nein
pruefe "Profi-Modus: keine Erlaeuterung unterm Feld" "$(enthaelt x 'Vorname oder Spitzname reicht')" nein
pruefe "Profi-Modus: Warnung zum Veto bleibt" "$(enthaelt x 'dazu warnung')" ja
pruefe "Profi-Modus: das Formular bleibt" "$(enthaelt x 'name="titel"')" ja
pruefe "Profi-Modus: Schalter zum Ausschalten" "$(enthaelt x '?profi=aus')" ja
curl -s -o "$TMP/s.html" -b "profi=1" "$ADMIN"
pruefe "Verwaltung im Profi-Modus: keine Erlaeuterung am Schalter" "$(enthaelt x 'Sichtbare Zwischenstände')" nein
pruefe "Verwaltung im Profi-Modus: Beschriftung der Links bleibt" "$(enthaelt x 'Diesen Link an die Gruppe geben.')" ja
pruefe "Verwaltung im Profi-Modus: Phase steht da" "$(enthaelt x 'Gerade läuft')" ja
curl -s -o "$TMP/s.html" -b "profi=1" "$ADMIN/redaktion"
pruefe "Redaktion im Profi-Modus: kein Erklaerkasten" "$(enthaelt x 'class="hinweis"')" nein
hole "$ADMIN" > /dev/null
pruefe "ohne Profi-Modus: Erlaeuterung am Schalter" "$(enthaelt x 'Sichtbare Zwischenstände')" ja
curl -s -o /dev/null -D "$TMP/p.head" -b "profi=1" "$BASIS?profi=aus"
pruefe "?profi=aus loescht das Cookie" "$(grep -ci '^set-cookie: profi=deleted' "$TMP/p.head")" 1
curl -s -o "$TMP/s.html" -b "profi=1" "$USER/vorschlaege"
pruefe "Teilnehmende sehen weder Schalter noch Wirkung" "$(enthaelt x 'profi=')$(enthaelt x 'class="hinweis"')" neinja

echo "\nMenue oben"
# Hilfe, Profi-Modus und Sprache stehen seit 0.3.7 im Menue, nicht mehr in Kopf und Fuss.
menue() { sed -n '/<details class="menue">/,/<\/details>/p' "$TMP/s.html"; }
fuss()  { sed -n '/<footer class="fuss">/,/<\/footer>/p' "$TMP/s.html"; }
hole "$BASIS" > /dev/null
pruefe "Kopfzeile mit Menue und Stapel-Knopf" "$(menue | grep -c 'class="stapel"')" 1
pruefe "im Menue: Hilfe, Profi-Modus, Sprache" "$(menue | grep -c '/hilfe"\|?profi=an\|?lang=en')" 3
pruefe "die gewaehlte Sprache steht ohne Link da" "$(menue | grep -c 'class="aktiv" lang="de" aria-current')" 1
pruefe "Kopfzeile ohne die alten Links" "$(enthaelt x 'kopf-nav')" nein
pruefe "Fuss ohne Hilfe, Sprache und Profi-Modus" "$(fuss | grep -c '/hilfe"\|lang=\|profi=')" 0
pruefe "Fuss mit Quelltext und Impressum" "$(fuss | grep -c '/quelltext"\|/impressum"')" 2
hole "$BASIS/hilfe" > /dev/null
pruefe "auf der Hilfeseite ist der Eintrag markiert" "$(grep -c 'aria-current="page">Wie das funktioniert' "$TMP/s.html")" 1
pruefe "Hilfeseite zeigt den Artikel oben" "$(enthaelt x 'class="karte artikelkarte"')" ja
hole "$USER/vorschlaege" > /dev/null
pruefe "Teilnehmende: Menue ohne Profi-Modus, mit Sprache" "$(menue | grep -c 'profi=')$(menue | grep -c '?lang=en')" 01
curl -s -o "$TMP/s.html" -b "profi=1" "$BASIS"
pruefe "Profi-Modus: die Erlaeuterung im Menue entfaellt" "$(menue | grep -c 'class="dazu"')" 0
hole "$BASIS?lang=en" > /dev/null
pruefe "Englisch: Menue auf Englisch" "$(menue | grep -c 'How this works\|Language\|?lang=de')" 3

echo "\nExport"
pruefe "CSV" "$(curl -s -o "$TMP/e.csv" -w '%{http_code}' "$ADMIN/export.csv")" 200
pruefe "CSV ohne PHP-Meldungen" "$(grep -cE 'Deprecated|Warning|Notice' "$TMP/e.csv")" 0
pruefe "JSON" "$(curl -s -o "$TMP/e.json" -w '%{http_code}' "$ADMIN/export.json")" 200
pruefe "JSON lesbar" "$(php -r 'echo json_decode(file_get_contents($argv[1]),true)?"ja":"nein";' "$TMP/e.json")" ja

echo "\nQuelltext-Archiv"
pruefe "ZIP" "$(curl -s -o "$TMP/q.zip" -w '%{http_code}' "$BASIS/quelltext.zip")" 200
pruefe "ohne Daten und Konfiguration" \
  "$(unzip -l "$TMP/q.zip" | grep -cE 'config\.php|\.sqlite|/data/')" 0
pruefe "mit den Impressum-Vorlagen" "$(unzip -l "$TMP/q.zip" | grep -c 'Impressum\.example\.md')" 2
pruefe "ohne ausgefuelltes Impressum" "$(unzip -l "$TMP/q.zip" | grep -c '/Impressum\.md$')" 0

echo "\nPhasenwechsel"
T=$(token "$ADMIN")
curl -s -o /dev/null -X POST "$ADMIN" -d "token=$T" -d "aktion=phase_ergebnis"
hole "$ADMIN" > /dev/null
pruefe "Ergebnisphase erreicht" "$(enthaelt x 'Gerade läuft')" ja
code=$(curl -s -o /dev/null -w '%{http_code}' "$USER/bewerten")
pruefe "Bewerten leitet zum Ergebnis" "$code" 303
hole "$USER/ergebnis" > /dev/null
pruefe "kein Bewertungsformular mehr" "$(grep -c 'bewertungsform' "$TMP/s.html")" 0

echo "\nBeispiel zum Ausprobieren"
BT=$(token "$BASIS")
for stadium in vorschlag bewertung ergebnis; do
  # Ein blosser Aufruf darf nichts anlegen - sonst legten Crawler Abstimmungen an.
  code=$(curl -s -o /dev/null -w '%{http_code}' "$BASIS/beispiel")
  pruefe "GET auf /beispiel legt nichts an ($stadium)" "$code" 303
  ziel=$(curl -s -o /dev/null -w '%{redirect_url}' -X POST "$BASIS/beispiel" \
         -d "token=$BT" -d "stadium=$stadium")
  pruefe "POST $stadium leitet in die Teilnehmeransicht" "$(echo "$ziel" | grep -c '/u/')" 1
  hole "$ziel" > /dev/null
  pruefe "$stadium: Band ist da" "$(enthaelt x 'beispielband')" ja
  pruefe "$stadium: Erklaerkasten ist da" "$(enthaelt x 'beispielkasten')" ja

  # Der Weg in die Verwaltung muss von jeder Seite aus offenstehen: Aus dem
  # gehashten Admin-Schluessel liesse er sich nachtraeglich nicht mehr bauen.
  adminlink=$(grep -o 'href="[^"]*/a/[^"]*"' "$TMP/s.html" | head -1 | sed 's/href="//;s/"$//')
  pruefe "$stadium: Verwaltungslink steht im Kasten" "$([ -n "$adminlink" ] && echo ja || echo nein)" ja
  code=$(hole "$adminlink")
  pruefe "$stadium: Verwaltung ist erreichbar" "$code" 200
  # Die Kacheln stehen in jeder Phase oben in der Verwaltung; ein
  # Weiterschalten-Knopf dagegen nicht mehr, sobald das Ergebnis erreicht ist.
  pruefe "$stadium: und zeigt wirklich die Verwaltung" "$(enthaelt x 'class="kachel"')" ja
  pruefe "$stadium: von dort zurueck zur eigenen Ansicht" "$(grep -c '/u/' "$TMP/s.html" | tr -d ' ' | grep -qv '^0$' && echo ja || echo nein)" ja

  # Zurueck auf die Beispielseite, damit die naechsten Pruefungen sie vorfinden.
  hole "$ziel" > /dev/null
done
pruefe "Ergebnis zeigt die Rangliste" "$(enthaelt x 'ergebnisliste')" ja
pruefe "Nordsee gewinnt sichtbar" "$(enthaelt x 'am breitesten getragen')" ja
pruefe "Krakau steht sichtbar unter der Passivloesung" "$(enthaelt x 'weniger Zustimmung als Passivlösung')" ja
pruefe "keine Platzziffer vor den Vorschlaegen" "$(enthaelt x 'class="rang"')" nein
pruefe "Balken tragen ihre Zahlen" "$(enthaelt x 'class="verteilung-summe"')" ja

echo "\nOffline-App"
# Die App liegt als statisches Verzeichnis im Upload-Paket; der Router des
# Werkzeugs darf sie nicht verschlucken. Ihr Plan gehoert nicht ins Archiv.
APP="${BASIS%/index.php}/Konsens-App"
pruefe "App-Startseite" "$(hole "$APP/")" 200
pruefe "App traegt ihren Titel" "$(enthaelt x 'Konsensieren im Raum')" ja
pruefe "Kartenvorlage" "$(hole "$APP/karten.html")" 200
pruefe "gemeinsame Testfaelle" "$(hole "$APP/tools/faelle.json")" 200
pruefe "Manifest der App" "$(hole "$APP/manifest.webmanifest")" 200
pruefe "Service Worker der App" "$(hole "$APP/sw.js")" 200
pruefe "Teilen-Skript der App" "$(hole "$APP/teilen.js")" 200
pruefe "Impressum der App" "$(hole "$APP/impressum.html")" 200
pruefe "Impressum-Vorlage der App" "$(hole "$APP/Impressum.example.md")" 200
pruefe "Impressum-Vorlage ist Markdown mit Ueberschrift" "$(head -1 "$TMP/s.html")" "# Impressum"
pruefe "App im Quelltext-Archiv" "$(unzip -l "$TMP/q.zip" | grep -c 'Konsens-App/zaehlwerk.js')" 1
pruefe "Plan der App nicht im Archiv" "$(unzip -l "$TMP/q.zip" | grep -c 'Konsens-App/Spec/')" 0
# Werkzeug und App wechseln ineinander (Spezifikation 19).
hole "$BASIS" > /dev/null
pruefe "Startseite zeigt den Umschalter" "$(enthaelt x 'class="suite"')" ja
pruefe "Startseite verlinkt die Anleitung" "$(enthaelt x 'Konsens-App/anleitung.html')" ja
hole "$BASIS/hilfe" > /dev/null
pruefe "Hilfeseite verlinkt die App" "$(enthaelt x 'Konsens-App/')" ja
pruefe "Anleitung der App" "$(hole "$APP/anleitung.html")" 200
pruefe "Anleitung fuehrt ins Werkzeug" "$(enthaelt x 'href="../"')" ja
hole "$APP/" > /dev/null
pruefe "App fuehrt ins Werkzeug" "$(enthaelt x 'href="../"')" ja
pruefe "App-Fuss verlinkt das Impressum" "$(enthaelt x 'href="impressum.html"')" ja
pruefe "App-Fuss ohne Pruefseite" "$(enthaelt x 'pruefen.html')" nein

echo "\nAufraeumen"
T=$(token "$ADMIN/einstellungen")
curl -s -o /dev/null -X POST "$ADMIN" -d "token=$T" -d "aktion=loeschen" -d "sicher=LOESCHEN"
pruefe "Abstimmung geloescht" "$(hole "$ADMIN")" 404

rm -rf "$TMP"
echo "\n--------------------------------------------------"
echo "$GUT bestanden, $SCHLECHT fehlgeschlagen"
[ "$SCHLECHT" -eq 0 ]
