<?php
/**
 * Konfiguration. Diese Datei nach config.php kopieren und anpassen.
 * Ohne config.php gelten genau diese Werte.
 */
return [
    // Zeitzone fuer alle Anzeigen. Die Serverzeitzone wird bewusst nicht geerbt.
    'zeitzone' => 'Europe/Berlin',

    // Wo die Abstimmungen liegen. Wenn moeglich ausserhalb des Webroots legen.
    'datenverzeichnis' => __DIR__ . '/data',

    // Darf von der Start- und der Hilfeseite aus eine Beispiel-Abstimmung zum
    // Ausprobieren angelegt werden? Sie ist eine echte Abstimmung mit erfundenen
    // Daten und loescht sich nach 'beispiel_tage' Tagen von selbst. Ist unten ein
    // anlegen_kennwort gesetzt, gilt es auch fuers Beispiel - sonst waere es eine
    // Umgehung der Sperre.
    'beispiel_erlauben' => true,
    'beispiel_tage'     => 7,

    // Leer = jede und jeder darf Abstimmungen anlegen.
    // Ein Wort eintragen, um das Anlegen auf Eingeweihte zu beschraenken.
    'anlegen_kennwort' => '',

    // Grenzen
    'max_laufzeit_tage'  => 92,   // Vorgabe der Aufgabe: hoechstens drei Monate
    'aufbewahrung_tage'  => 92,   // danach wird die Abstimmung geloescht
    'max_vorschlaege'    => 200,
    'max_teilnehmer'     => 200,
    'max_textlaenge'     => 4000,

    // Adressen ohne index.php? 'auto' entscheidet selbst und waehlt im Zweifel
    // den sicheren Weg mit index.php. true erzwingt schoene Adressen (nur, wenn
    // check.php mod_rewrite bestaetigt hat), false schaltet sie ab.
    'schoene_links' => 'auto',

    // Voreingestellte Sprache, wenn der Browser nichts Brauchbares meldet.
    'sprache' => 'de',
];
