<?php
/**
 * Erzeugt eine gefuellte Beispiel-Abstimmung zum Ausprobieren.
 * Aufruf:  php tools/testdaten.php [Personen] [Vorschlaege] [Phase]
 * Phase:   vorschlag | bewertung | ergebnis   (Vorgabe: bewertung)
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit("Nur auf der Kommandozeile.\n");

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8788';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/index.php';
define('SK_EINSTIEG', true);
require __DIR__ . '/../lib/bootstrap.php';

$personen  = (int)($argv[1] ?? 8);
$anzahlVor = (int)($argv[2] ?? 5);
$zielPhase = (string)($argv[3] ?? 'bewertung');

$namen = ['Anke','Bernd','Cem','Dilek','Erik','Fatma','Georg','Hanna','Ismail','Jana',
          'Klaus','Lena','Murat','Nora','Ole','Pia','Quentin','Rosa','Sven','Tuula',
          'Ulf','Vera','Wanda','Xaver','Yusuf'];
$titel = [
    'Zwei Wochen Wanderurlaub in den Alpen',
    'Eine Woche Nordsee mit Ferienhaus',
    'Stadtreise nach Krakau',
    'Zu Hause bleiben und Tagesausflüge machen',
    'Segeltörn auf der Ostsee',
    'Radtour entlang der Elbe',
    'Gemeinsames Zeltlager am See',
    'Sprachreise nach Lissabon',
];
$texte = [
    'Hütten sind früh ausgebucht, wir müssten uns schnell entscheiden. Dafür ist es günstig.',
    'Viel Platz, gut mit Kindern, aber das Wetter ist ein Wagnis.',
    'Günstige Zugverbindung, viel zu sehen, und niemand muss fahren.',
    'Kostet fast nichts und lässt sich kurzfristig ändern.',
    'Braucht mindestens eine Person mit Schein. Wäre etwas Besonderes.',
    'Gemütlich, flach, und man kann jederzeit abbrechen.',
    'Sehr günstig, aber bei Dauerregen ungemütlich.',
    'Teurer, dafür lernen alle etwas.',
];

[$poll, $adminGeheim, $einladung] = Poll::anlegen(
    'Wohin fährt die Gruppe im Sommer?',
    'Wir haben eine Woche Zeit und ein knappes Budget. Bitte alle Vorschläge bewerten.',
    time() + 3 * 86400, time() + 10 * 86400,
    ['passiv' => true, 'passiv_text' => 'Wir fahren dieses Jahr gar nicht gemeinsam weg.',
     'veto' => false, 'schwelle' => 20]
);

$leute = [];
$geheimnisse = [];
for ($i = 0; $i < $personen; $i++) {
    $name = $namen[$i % count($namen)] . ($i >= count($namen) ? ' ' . $i : '');
    [$id, $geheim] = $poll->teilnehmerAnlegen($name, $i === 0);
    $leute[] = ['id' => $id, 'name' => $name];
    $geheimnisse[$name] = $geheim;
    if ($i === 0) $poll->setzen(['admin_nutzer_geheim' => $geheim]);
}

$vorschlagsIds = [];
for ($i = 0; $i < $anzahlVor; $i++) {
    $autor = $leute[$i % count($leute)];
    $eltern = [];
    // Jeder dritte Vorschlag ist eine Abwandlung des vorherigen.
    if ($i > 0 && $i % 3 === 0) $eltern[] = $vorschlagsIds[$i - 1];
    $vorschlagsIds[] = Vorschlaege::anlegen(
        $poll, $autor,
        $titel[$i % count($titel)] . ($i >= count($titel) ? ' (' . $i . ')' : ''),
        $texte[$i % count($texte)], $eltern, $i % 4 !== 0
    );
}

// Unterstuetzungen und Kommentare
foreach ($vorschlagsIds as $vid) {
    foreach ($leute as $l) {
        if (random_int(0, 100) < 45) Vorschlaege::unterstuetzen($poll, $vid, (int)$l['id'], true);
    }
    if (random_int(0, 100) < 60) {
        $l = $leute[random_int(0, count($leute) - 1)];
        Vorschlaege::kommentieren($poll, $vid, $l,
            'Ich frage mich, ob wir das in einer Woche überhaupt schaffen.', true);
    }
}

if ($zielPhase !== 'vorschlag') {
    $poll->uebergangBewertung('testdaten');
    foreach (Vorschlaege::stimmzettel($poll) as $v) {
        foreach ($leute as $l) {
            if (random_int(0, 100) < 12) continue;   // ein paar lassen etwas offen
            Bewertungen::speichern($poll, (int)$l['id'], (int)$v['id'], random_int(0, 10), false, '');
        }
    }
    $poll->setzen(['sicht_ergebnis' => 1, 'sicht_bewertungen' => 1]);
}
if ($zielPhase === 'ergebnis') {
    $poll->uebergangErgebnis('testdaten');
}

$basis = 'http://' . $_SERVER['HTTP_HOST'] . '/index.php';
echo "Beispiel-Abstimmung angelegt: ", $poll->id(), "\n";
echo "Personen: $personen, Vorschläge: $anzahlVor, Phase: ", $poll->phase(), "\n\n";
echo "Verwaltung : $basis/a/", $poll->id(), '.', $adminGeheim, "\n";
echo "Einladung  : $basis/i/", $poll->id(), '.', $einladung, "\n";
echo "Teilnehmen :\n";
foreach ($geheimnisse as $name => $g) {
    printf("  %-12s %s/u/%s.%s\n", $name, $basis, $poll->id(), $g);
}
