<?php
/**
 * Einfache Tests ohne Framework: php tools/test.php
 * Legt eine eigene Datenablage unter data/test an und raeumt sie wieder weg.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit("Nur auf der Kommandozeile.\n");

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/index.php';
define('SK_EINSTIEG', true);
require __DIR__ . '/../lib/bootstrap.php';

// Die Zaehler heissen bewusst sperrig: im Skript-Bereich sind lokale Variablen
// zugleich globale, und ein $gut aus einem Testfall wuerde sie sonst ueberschreiben.
$GLOBALS['sk_gut'] = 0; $GLOBALS['sk_schlecht'] = 0;

function pruefe(string $was, $ist, $soll): void
{
    if ($ist === $soll) {
        $GLOBALS['sk_gut']++;
        return;
    }
    $GLOBALS['sk_schlecht']++;
    echo "  FEHLER  $was\n";
    echo "          erwartet: ", var_export($soll, true), "\n";
    echo "          bekommen: ", var_export($ist, true), "\n";
}

function wahr(string $was, bool $ist): void { pruefe($was, $ist, true); }

function abschnitt(string $name): void { echo "\n$name\n"; }

/** Eine frische Abstimmung mit n Personen. */
function bau(int $personen = 5, array $opt = []): array
{
    $opt += ['passiv' => true, 'passiv_text' => 'Alles bleibt.', 'veto' => false, 'schwelle' => 0];
    [$poll] = Poll::anlegen('Test', '', time() + 86400, time() + 2 * 86400, $opt);
    $leute = [];
    for ($i = 0; $i < $personen; $i++) {
        [$id] = $poll->teilnehmerAnlegen('P' . $i, $i === 0);
        $leute[] = ['id' => $id, 'name' => 'P' . $i];
    }
    return [$poll, $leute];
}

$sk_polls = [];
function merken(Poll $p): Poll { $GLOBALS['sk_polls'][] = $p->id(); return $p; }

/* ---------------------------------------------------------------- Keys */
abschnitt('Schlüssel');
$g = Keys::geheimnis();
pruefe('Geheimnis ist 26 Zeichen lang', strlen($g), 26);
pruefe('Schlüssel zerlegen', Keys::zerlegen('ABCDEFGHJK.' . $g), ['ABCDEFGHJK', $g]);
pruefe('Schlüssel ohne Punkt wird abgelehnt', Keys::zerlegen('ABCDEFGHJK'), null);
pruefe('zu kurze pollid wird abgelehnt', Keys::zerlegen('ABC.' . $g), null);
pruefe('Kleinschreibung wird angenommen', Keys::zerlegen(strtolower('ABCDEFGHJK.' . $g)), ['ABCDEFGHJK', $g]);
wahr('Hash ist stabil', Keys::hash($g) === Keys::hash($g));
wahr('Hashes unterscheiden sich', Keys::hash($g) !== Keys::hash(Keys::geheimnis()));

/* --------------------------------------------------------- Anlegen */
abschnitt('Abstimmung anlegen');
[$p, $leute] = bau(5);
merken($p);
pruefe('startet in der Vorschlagsphase', $p->phase(), Poll::PHASE_VORSCHLAG);
pruefe('fünf Teilnehmende', $p->teilnehmerZahl(), 5);
$zettel = Vorschlaege::stimmzettel($p);
pruefe('Passivlösung liegt bei', count($zettel), 1);
pruefe('Passivlösung ist als solche gekennzeichnet', (int)$zettel[0]['ist_passiv'], 1);
wahr('Admin-Schlüssel wird nicht im Klartext gespeichert',
     strpos((string)$p->v('admin_hash'), '.') === false && strlen((string)$p->v('admin_hash')) === 64);

/* ------------------------------------------------------------ Schwelle */
abschnitt('Unterstützer-Schwelle');
[$p2, $l2] = bau(10, ['schwelle' => 20]);
merken($p2);
pruefe('20 % von 10 sind 2', $p2->schwelle(), 2);
$v1 = Vorschlaege::anlegen($p2, $l2[0], 'A', '', [], true);
$vorher = Vorschlaege::stimmzettel($p2);
pruefe('mit einer Unterstützung noch nicht auf dem Zettel', count($vorher), 1);
Vorschlaege::unterstuetzen($p2, $v1, (int)$l2[1]['id'], true);
pruefe('mit zwei Unterstützungen auf dem Zettel', count(Vorschlaege::stimmzettel($p2)), 2);
Vorschlaege::adminStatus($p2, $v1, 'entfernt');
pruefe('von der Redaktion entfernt', count(Vorschlaege::stimmzettel($p2)), 1);
Vorschlaege::adminStatus($p2, $v1, 'aktiv');
$v2 = Vorschlaege::anlegen($p2, $l2[0], 'B', '', [], true);
Vorschlaege::adminAufnahme($p2, $v2, 'erzwungen');
pruefe('trotz fehlender Unterstützung aufgenommen', count(Vorschlaege::stimmzettel($p2)), 3);
$p2->uebergangBewertung('test');
pruefe('Bezugsgröße wird beim Phasenwechsel festgeschrieben', (int)$p2->v('schwelle_basis'), 10);
$p2->teilnehmerAnlegen('Nachzügler');
pruefe('Nachzügler ändert die Schwelle nicht mehr', $p2->schwelle(), 2);

/* ---------------------------------------------------------- Reihenfolge */
abschnitt('Reihenfolge und Modifikationen');
[$p3, $l3] = bau(3);
merken($p3);
$a = Vorschlaege::anlegen($p3, $l3[0], 'Erster', '', [], true);
sleep(0);
$b = Vorschlaege::anlegen($p3, $l3[0], 'Zweiter', '', [], true);
$c = Vorschlaege::anlegen($p3, $l3[1], 'Abwandlung des Ersten', '', [$a], true);
$p3->db->exec("UPDATE vorschlag SET angelegt = angelegt + id * 10");   // klare Zeitabstände
$liste = Vorschlaege::geordnet($p3);
$titelReihe = array_map(static fn($v) => $v['titel'], $liste);
pruefe('Strang mit der jüngsten Änderung steht oben', $titelReihe[0], 'Erster');
pruefe('Abwandlung folgt eingerückt', $titelReihe[1], 'Abwandlung des Ersten');
pruefe('Abwandlung ist eine Ebene tiefer', (int)$liste[1]['tiefe'], 1);
pruefe('danach der zweite Strang', $titelReihe[2], 'Zweiter');
pruefe('Passivlösung steht immer am Ende', (int)$liste[count($liste) - 1]['ist_passiv'], 1);

Vorschlaege::mischen($p3);
$gemischt = Vorschlaege::geordnet($p3);
pruefe('Mischen behält alle Einträge', count($gemischt), count($liste));
$posA = $posC = -1;
foreach ($gemischt as $i => $v) {
    if ($v['titel'] === 'Erster') $posA = $i;
    if ($v['titel'] === 'Abwandlung des Ersten') $posC = $i;
}
pruefe('Abwandlung bleibt auch gemischt direkt beim Ursprung', $posC, $posA + 1);
pruefe('Passivlösung bleibt auch gemischt am Ende',
       (int)$gemischt[count($gemischt) - 1]['ist_passiv'], 1);
Vorschlaege::reihenfolgeChronologisch($p3);
pruefe('zurück zur chronologischen Ordnung', $p3->v('reihenfolge'), 'neu');

/* ---------------------------------------------------------- Auswertung */
abschnitt('Auswertung');
[$p4, $l4] = bau(4, ['schwelle' => 0]);
merken($p4);
$x = Vorschlaege::anlegen($p4, $l4[0], 'X', '', [], true);
$y = Vorschlaege::anlegen($p4, $l4[0], 'Y', '', [], true);
$p4->uebergangBewertung('test');
// Zustimmungswerte. X: 10,9,8,7 -> Schnitt 8,5   Y: 5,5,5,unbewertet -> Schnitt 5,0
$werteX = [10, 9, 8, 7];
$werteY = [5, 5, 5, null];
foreach ($l4 as $i => $l) {
    Bewertungen::speichern($p4, (int)$l['id'], $x, $werteX[$i], false, '');
    Bewertungen::speichern($p4, (int)$l['id'], $y, $werteY[$i], false, '');
}
$passivId = null;
foreach (Vorschlaege::stimmzettel($p4) as $v) if ((int)$v['ist_passiv'] === 1) $passivId = (int)$v['id'];
foreach ($l4 as $l) Bewertungen::speichern($p4, (int)$l['id'], $passivId, 2, false, '');

$erg = Auswertung::rechnen($p4);
$nach = [];
foreach ($erg['zeilen'] as $z) $nach[$z['titel']] = $z;
pruefe('nötige Bewertungen bei vier Wertenden und 50 %', $erg['noetig'], 2);
pruefe('Summe X', (int)$nach['X']['summe'], 34);
pruefe('Durchschnitt X', $nach['X']['mittel'], 8.5);
pruefe('Durchschnitt Y zählt nur die drei Abgegebenen', $nach['Y']['mittel'], 5.0);
pruefe('Y wurde von dreien bewertet', (int)$nach['Y']['bewertet'], 3);
wahr('Y ist trotzdem belastbar', $nach['Y']['belastbar']);
pruefe('X hat Rang 1', (int)$nach['X']['rang'], 1);
pruefe('X gewinnt', $erg['sieger'], $x);
pruefe('Kraft im Konsens von X', $nach['X']['kik'], 8.5 - 2.0);
wahr('X ist legitimiert', $nach['X']['legitimiert'] === true);
wahr('X liegt nicht gleichauf mit dem Nichtstun', $nach['X']['gleichauf'] === false);
pruefe('Verteilung von X bei Wert 8', $nach['X']['verteilung'][8], 1);
pruefe('niedrigster Einzelwert bei Y', (int)$nach['Y']['minimal'], 5);
pruefe('bei X trägt niemand nur zögerlich mit', (int)$nach['X']['niedrig'], 0);

$fort = Auswertung::fortschritt($p4, (int)$l4[3]['id']);
pruefe('Fortschritt zählt nur ausgefüllte Werte', $fort['fertig'], 2);
pruefe('Fortschritt kennt die Zahl der Vorschläge', $fort['gesamt'], 3);
wahr('Passivlösung als bewertet erkannt', $fort['passiv_offen'] === false);

// Strenger Modus: P3 hat Y offen gelassen, zaehlt also gar nicht mit
$p4->setzen(['nur_vollstaendig' => 1]);
$erg2 = Auswertung::rechnen($p4);
$nach2 = [];
foreach ($erg2['zeilen'] as $z) $nach2[$z['titel']] = $z;
pruefe('nur vollständige Stimmzettel: drei Wertende', $erg2['wertende'], 3);
pruefe('nur vollständige Stimmzettel: Summe X ohne die vierte Person', (int)$nach2['X']['summe'], 27);
pruefe('nur vollständige Stimmzettel: Durchschnitt X', $nach2['X']['mittel'], 9.0);
$p4->setzen(['nur_vollstaendig' => 0]);

/* --------------------------------- Der Fall, der in der Praxis aufgefallen ist */
abschnitt('Kaum bewertete Passivlösung kippt das Ergebnis nicht');
// Drei Personen bewerten alle Vorschläge, die Passivlösung aber nur eine - mit 7.
// Zählte Fehlendes mit, käme die Passivlösung zu einem Gewicht, das ihr niemand
// gegeben hat.
[$p9, $l9] = bau(3, ['schwelle' => 0]);
merken($p9);
$a9 = Vorschlaege::anlegen($p9, $l9[0], 'Vorschlag A', '', [], true);
$b9 = Vorschlaege::anlegen($p9, $l9[1], 'Vorschlag B', '', [], true);
$p9->uebergangBewertung('test');
$passiv9 = null;
foreach (Vorschlaege::stimmzettel($p9) as $v) if ((int)$v['ist_passiv'] === 1) $passiv9 = (int)$v['id'];
foreach ([[6, 4], [5, 3], [6, 2]] as $i => $paar) {
    Bewertungen::speichern($p9, (int)$l9[$i]['id'], $a9, $paar[0], false, '');
    Bewertungen::speichern($p9, (int)$l9[$i]['id'], $b9, $paar[1], false, '');
}
Bewertungen::speichern($p9, (int)$l9[0]['id'], $passiv9, 7, false, '');

$erg9 = Auswertung::rechnen($p9);
$n9 = [];
foreach ($erg9['zeilen'] as $z) $n9[$z['titel']] = $z;
$passivZeile = $n9[t('passiv.titel')];
pruefe('drei Wertende, also zwei Bewertungen nötig', $erg9['noetig'], 2);
pruefe('die Passivlösung hat nur eine Bewertung', (int)$passivZeile['bewertet'], 1);
wahr('sie ist damit nicht belastbar', !$passivZeile['belastbar']);
wahr('sie bekommt keinen Rang', $passivZeile['rang'] === null);
wahr('sie gewinnt nicht', $erg9['sieger'] !== $passiv9);
pruefe('gewonnen hat der Vorschlag mit dem größten Rückhalt', $erg9['sieger'], $a9);
wahr('ohne belastbare Messlatte keine Kraft im Konsens', $n9['Vorschlag A']['kik'] === null);
wahr('und keine Aussage über die Legitimation', $n9['Vorschlag A']['legitimiert'] === null);

// Sobald eine zweite Person die Passivlösung bewertet, greift die Messlatte wieder.
Bewertungen::speichern($p9, (int)$l9[1]['id'], $passiv9, 9, false, '');
$erg9b = Auswertung::rechnen($p9);
$n9b = [];
foreach ($erg9b['zeilen'] as $z) $n9b[$z['titel']] = $z;
wahr('jetzt ist die Passivlösung belastbar', $n9b[t('passiv.titel')]['belastbar']);
pruefe('und sie gewinnt zu Recht, weil sie den breitesten Rückhalt hat', $erg9b['sieger'], $passiv9);
wahr('A trägt weniger weit als Nichtstun', $n9b['Vorschlag A']['legitimiert'] === false);
wahr('und liegt auch nicht gleichauf', $n9b['Vorschlag A']['gleichauf'] === false);

// Ein hoch eingestelltes Quorum kann alles aus der Rangfolge nehmen.
$p9->setzen(['quorum_prozent' => 100]);
$erg9c = Auswertung::rechnen($p9);
wahr('bei 100 % fällt die nur zweifach bewertete Passivlösung heraus',
     $erg9c['sieger'] === $a9);
$p9->setzen(['quorum_prozent' => 50]);

/* --------------------------------------------------------------- Veto */
abschnitt('Veto');
[$p6, $l6] = bau(3, ['schwelle' => 0, 'veto' => true]);
merken($p6);
$vGut = Vorschlaege::anlegen($p6, $l6[0], 'Gut', '', [], true);
$p6->uebergangBewertung('test');
foreach ($l6 as $l) Bewertungen::speichern($p6, (int)$l['id'], $vGut, 10, false, '');
Bewertungen::speichern($p6, (int)$l6[2]['id'], $vGut, 10, true, 'Das geht aus meiner Sicht gar nicht.');
$erg6 = Auswertung::rechnen($p6);
foreach ($erg6['zeilen'] as $z) if ($z['titel'] === 'Gut') $g6 = $z;
pruefe('Veto ist unabhängig vom Zahlenwert erfasst', count($g6['vetos']), 1);
pruefe('Veto trägt den Namen', $g6['vetos'][0]['name'], 'P2');
wahr('Vorschlag ist blockiert', $g6['gesperrt']);
wahr('und faellt damit aus der Rangfolge', $g6['rang'] === null);
pruefe('die Zahlen bleiben trotz Veto erhalten', (int)$g6['summe'], 30);
wahr('blockierter Vorschlag gewinnt nicht', $erg6['sieger'] !== $vGut);

$p6->setzen(['opt_veto' => 0]);
$erg6b = Auswertung::rechnen($p6);
foreach ($erg6b['zeilen'] as $z) if ($z['titel'] === 'Gut') $g6b = $z;
wahr('ohne zugelassene Vetos blockiert nichts', !$g6b['gesperrt']);

/* --------------------------------------------------- Neuer Zugangslink */
abschnitt('Verlorener Zugang');
[$pz, $lz] = bau(2);
merken($pz);
$alterHash = $pz->db->query('SELECT key_hash FROM teilnehmer WHERE id = ' . (int)$lz[1]['id'])->fetchColumn();
$neu = $pz->teilnehmerNeuerSchluessel((int)$lz[1]['id']);
wahr('es kommt ein neuer Schlüssel zurück', is_array($neu) && strlen($neu[1]) === 26);
pruefe('mit dem Namen der Person', $neu[0], 'P1');
$neuerHash = $pz->db->query('SELECT key_hash FROM teilnehmer WHERE id = ' . (int)$lz[1]['id'])->fetchColumn();
wahr('der alte Schlüssel gilt nicht mehr', $alterHash !== $neuerHash);
pruefe('der neue führt zur selben Person',
       (int)($pz->teilnehmerPerHash(Keys::hash($neu[1]))['id'] ?? 0), (int)$lz[1]['id']);
wahr('der alte Hash öffnet nichts mehr', $pz->teilnehmerPerHash((string)$alterHash) === null);
wahr('für eine unbekannte Person gibt es nichts', $pz->teilnehmerNeuerSchluessel(9999) === null);
$eintraege = array_map(static fn($e) => $e['art'], $pz->protokoll());
wahr('der Vorgang steht im Protokoll', in_array('teilnehmer.neuer_schluessel', $eintraege, true));

/* ------------------------- Erfundene Vorschlagsnummern (Pentest-Fund) */
abschnitt('Erfundene ids fuehren nicht zum Absturz');
[$pf, $lf] = bau(3);
merken($pf);
$echt = Vorschlaege::anlegen($pf, $lf[0], 'Echt', '', [], true);
wahr('bekannte id existiert', Vorschlaege::existiert($pf, $echt));
wahr('erfundene id existiert nicht', !Vorschlaege::existiert($pf, 99999));
// Vor dem Fix warf jede dieser Zeilen eine Fremdschluessel-Ausnahme.
Vorschlaege::unterstuetzen($pf, 99999, (int)$lf[0]['id'], true);
pruefe('Unterstuetzen mit erfundener id schreibt nichts',
       (int)$pf->db->query('SELECT COUNT(*) FROM unterstuetzung WHERE vorschlag_id = 99999')->fetchColumn(), 0);
Vorschlaege::kommentieren($pf, 99999, $lf[0], 'Hallo', true);
pruefe('Kommentar mit erfundener id schreibt nichts',
       (int)$pf->db->query('SELECT COUNT(*) FROM kommentar WHERE vorschlag_id = 99999')->fetchColumn(), 0);
$pf->uebergangBewertung('test');
Bewertungen::speichern($pf, (int)$lf[0]['id'], 99999, 5, false, '');
pruefe('Bewertung mit erfundener id schreibt nichts',
       (int)$pf->db->query('SELECT COUNT(*) FROM bewertung WHERE vorschlag_id = 99999')->fetchColumn(), 0);
Bewertungen::speichern($pf, (int)$lf[0]['id'], $echt, 4, false, '');
pruefe('gueltige Bewertung geht weiterhin durch',
       (int)$pf->db->query('SELECT wert FROM bewertung WHERE vorschlag_id = ' . $echt)->fetchColumn(), 4);

/* ---------------------------------------------- Beispiel zum Ausprobieren */
abschnitt('Beispiel zum Ausprobieren');
foreach (Beispiel::STADIEN as $stadium) {
    [$bp, $bAdmin, $bNutzer] = Beispiel::anlegen($stadium);
    $GLOBALS['sk_polls'][] = $bp->id();
    wahr("$stadium: als Beispiel gekennzeichnet", $bp->an('ist_beispiel'));
    pruefe("$stadium: acht Teilnehmende (sieben Erfundene und Du)", $bp->teilnehmerZahl(), 8);
    wahr("$stadium: Admin- und Nutzerzugang unterscheiden sich", $bAdmin !== $bNutzer);
    wahr("$stadium: der Nutzerzugang fuehrt zu einer Person",
         $bp->teilnehmerPerHash(Keys::hash($bNutzer)) !== null);
    // Ohne abgelegten Admin-Schluessel waere die Verwaltung nach dem Umleiten in
    // die Teilnehmeransicht unerreichbar - aus dem Hash kommt man nicht zurueck.
    pruefe("$stadium: der Admin-Schluessel ist fuers Beispiel abgelegt",
           (string)$bp->v('beispiel_admin_geheim'), $bAdmin);
    wahr("$stadium: und er oeffnet die Verwaltung wirklich",
         $bp->istAdminSchluessel((string)$bp->v('beispiel_admin_geheim')));
    pruefe("$stadium: fuenf Eintraege auf dem Stimmzettel",
           count(Vorschlaege::stimmzettel($bp)), 5);
    wahr("$stadium: loescht sich frueher als eine echte Abstimmung",
         (int)$bp->v('loeschdatum') < time() + 30 * 86400);
}

// Die Vorschlagsphase darf noch keine Bewertung enthalten - sonst waere die
// Phase eine Behauptung und keine Lage.
[$bv] = Beispiel::anlegen('vorschlag');
$GLOBALS['sk_polls'][] = $bv->id();
pruefe('Vorschlagsphase: richtige Phase', $bv->phase(), Poll::PHASE_VORSCHLAG);
pruefe('Vorschlagsphase: noch keine Bewertung',
       (int)$bv->db->query('SELECT COUNT(*) FROM bewertung')->fetchColumn(), 0);
wahr('Vorschlagsphase: es gibt Kommentare',
     (int)$bv->db->query('SELECT COUNT(*) FROM kommentar')->fetchColumn() > 0);
wahr('Vorschlagsphase: es gibt eine Abwandlung',
     (int)$bv->db->query('SELECT COUNT(*) FROM bezug')->fetchColumn() > 0);

// Mittendrin: zwei haben gar nicht bewertet, eine Luecke ist dabei.
[$bb] = Beispiel::anlegen('bewertung');
$GLOBALS['sk_polls'][] = $bb->id();
pruefe('Bewertungsphase: richtige Phase', $bb->phase(), Poll::PHASE_BEWERTUNG);
$ergB = Auswertung::rechnen($bb);
$nachB = [];
foreach ($ergB['zeilen'] as $z) $nachB[$z['titel']] = $z;
pruefe('mittendrin haben fuenf die Alpen bewertet',
       (int)$nachB[t('beispiel.v_alpen')]['bewertet'], 5);
pruefe('bei Krakau fehlt zusaetzlich eine Bewertung',
       (int)$nachB[t('beispiel.v_krakau')]['bewertet'], 4);
pruefe('acht Wertende, also vier noetige Bewertungen', $ergB['noetig'], 4);
wahr('Krakau liegt damit haarscharf auf der Schwelle',
     $nachB[t('beispiel.v_krakau')]['belastbar']);

// Das Ergebnis ist der Grund, warum es das Beispiel gibt: Der Favorit der
// Mehrheit verliert gegen den, den alle mittragen.
[$be] = Beispiel::anlegen('ergebnis');
$GLOBALS['sk_polls'][] = $be->id();
pruefe('Ergebnisphase: richtige Phase', $be->phase(), Poll::PHASE_ERGEBNIS);
wahr('die Auswertung ist freigegeben', $be->an('sicht_ergebnis'));
$ergE = Auswertung::rechnen($be);
$nachE = [];
foreach ($ergE['zeilen'] as $z) $nachE[$z['titel']] = $z;
$alpen   = $nachE[t('beispiel.v_alpen')];
$nordsee = $nachE[t('beispiel.v_nordsee')];
$krakau  = $nachE[t('beispiel.v_krakau')];
$passivE = $nachE[t('passiv.titel')];

pruefe('die Nordsee gewinnt', $ergE['sieger'], (int)$nordsee['id']);
pruefe('und zwar mit Rang 1', (int)$nordsee['rang'], 1);
pruefe('Rueckhalt der Nordsee', round((float)$nordsee['mittel'], 2), 7.29);
pruefe('Rueckhalt der Alpen', round((float)$alpen['mittel'], 2), 5.86);
wahr('die Alpen liegen dahinter', (float)$alpen['mittel'] < (float)$nordsee['mittel']);
pruefe('die Nordsee bekommt keine einzige 10', (int)$nordsee['verteilung'][10], 0);
pruefe('die Alpen bekommen zwei Zehner und eine Neun',
       (int)$alpen['verteilung'][10] + (int)$alpen['verteilung'][9], 3);
pruefe('bei den Alpen faellt jemand auf null', (int)$alpen['minimal'], 0);
pruefe('bei der Nordsee faellt niemand unter sechs', (int)$nordsee['minimal'], 6);
pruefe('zwei tragen die Alpen kaum mit', (int)$alpen['niedrig'], 2);
pruefe('bei der Nordsee niemand', (int)$nordsee['niedrig'], 0);
wahr('Krakau bringt weniger Rueckhalt als das Nichtstun',
     $krakau['legitimiert'] === false);
wahr('die Passivloesung ist belastbar bewertet', $passivE['belastbar']);
wahr('alle acht sind erfasst, auch die noch nicht bewertet haben',
     $ergE['wertende'] === 8);

// Der Handzeichen-Vergleich aus dem Erklaerkasten muss stimmen: Genau drei der
// sieben Erfundenen haben die Alpen als hoechsten Wert.
$ersteStimmen = 0;
foreach ([[10, 7, 8, 4, 5], [10, 8, 3, 5, 4], [9, 7, 7, 3, 6], [5, 8, 2, 6, 3],
          [6, 6, 7, 5, 7], [1, 7, 4, 9, 8], [0, 8, 6, 8, 5]] as $zeile) {
    if ($zeile[0] === max($zeile)) $ersteStimmen++;
}
pruefe('per Handzeichen haetten die Alpen drei von sieben Stimmen', $ersteStimmen, 3);

// Eine gewoehnliche Abstimmung darf diesen Schluessel niemals im Klartext haben.
[$normal] = Poll::anlegen('Ganz normal', '', time() + 86400, time() + 2 * 86400,
                          ['passiv' => true, 'passiv_text' => 'x', 'veto' => false, 'schwelle' => 0]);
$GLOBALS['sk_polls'][] = $normal->id();
wahr('eine echte Abstimmung ist kein Beispiel', !$normal->an('ist_beispiel'));
pruefe('und legt keinen Admin-Schluessel im Klartext ab',
       (string)$normal->v('beispiel_admin_geheim'), '');

/* ------------------------------------------- Wanderung der Bestandsdaten */
abschnitt('Wanderung von der Widerstands- auf die Zustimmungsskala');
[$pw, $lw] = bau(3);
$idw = $pw->id();
$vw = Vorschlaege::anlegen($pw, $lw[0], 'Gewandert', '', [], true);
$pw->uebergangBewertung('test');
// So, wie eine Datei aus 0.1.3 aussieht: Widerstandswerte und keine Skalenfassung.
foreach ([2, 7, null] as $i => $wert) {
    Bewertungen::speichern($pw, (int)$lw[$i]['id'], $vw, $wert, false, '');
}
$vorherW = $pw->db->query('SELECT teilnehmer_id, wert FROM bewertung ORDER BY teilnehmer_id')->fetchAll();
$pw->db->exec('UPDATE poll SET skala_version = 0');
unset($pw);

$pw2 = Poll::laden($idw);
pruefe('die Skalenfassung steht danach auf 1',
       (int)$pw2->db->query('SELECT skala_version FROM poll')->fetchColumn(), 1);
$nachherW = $pw2->db->query('SELECT teilnehmer_id, wert FROM bewertung ORDER BY teilnehmer_id')->fetchAll();
pruefe('aus Widerstand 2 wird Zustimmung 8', (int)$nachherW[0]['wert'], 8);
pruefe('aus Widerstand 7 wird Zustimmung 3', (int)$nachherW[1]['wert'], 3);
pruefe('es sind genauso viele Zeilen wie vorher', count($nachherW), count($vorherW));
wahr('Unbewertetes wurde gar nicht erst geschrieben', count($nachherW) === 2);
unset($pw2);
$pw3 = Poll::laden($idw);
pruefe('ein zweites Öffnen dreht nicht noch einmal',
       $pw3->db->query('SELECT teilnehmer_id, wert FROM bewertung ORDER BY teilnehmer_id')->fetchAll(),
       $nachherW);
$GLOBALS['sk_polls'][] = $idw;
unset($pw3);

/* -------------------------------------------- Texte zur Zustimmungsskala */
abschnitt('Texte reden nicht mehr von der Widerstandsskala');
// Bei der Umstellung auf Zustimmung (0.2.0) blieben in beiden Sprachen drei
// Saetze stehen, die unter der neuen Skala das Gegenteil sagen: der kleinste
// Wert gewinne, eine 10 blockiere nichts, hohe Werte seien ein Warnsignal.
// Behoben in 0.3.1. Ein Editor, der einen alten Puffer zurueckschreibt,
// braechte sie unbemerkt wieder. Nur hilfe.skala3 darf so reden: Dort wird
// die Zaehlung der Fachliteratur ausdruecklich als Gegenstueck erklaert. Zwei
// weitere fielen erst in 0.3.2 auf, beide auf der Startseite: die 0 als "sehr
// gerne" und gleich darunter "die wenigsten Bedenken".
$altWendungen = [
    'de' => ['kleinste Wert gewinnt', 'eine 10 ist einfach das Ende', 'hohe Werte als Warnsignal',
             'Die 0 heißt „sehr gerne', 'wenigsten Bedenken hat'],
    'en' => ['smallest value wins', 'a 10 is simply the end', 'high values as a warning',
             '0 means “very happily', 'fewest reservations'],
];
foreach ($altWendungen as $altSprache => $altListe) {
    $altTexte = require __DIR__ . '/../lang/' . $altSprache . '.php';
    $altFunde = [];
    foreach ($altTexte as $altSchluessel => $altText) {
        if (!is_string($altText) || $altSchluessel === 'hilfe.skala3') continue;
        foreach ($altListe as $altW) {
            if (stripos($altText, $altW) !== false) $altFunde[] = "$altSchluessel: $altW";
        }
    }
    pruefe("$altSprache: keine Wendung der alten Skala", $altFunde, []);
}

/* ------------------------------ Gemeinsame Testfaelle mit der Konsens-App */
abschnitt('Gemeinsame Testfälle mit der Konsens-App');
// Dieselben Faelle schickt Konsens-App/tools/test.js durch das Zaehlwerk der
// App. Treffen beide die Erwartung, rechnen sie auch gleich - und eine Gruppe
// im Raum bekommt dieselben Zahlen wie online. Die Reihenfolge des Anlegens
// wird ausdruecklich gesetzt: Bei voelligem Gleichstand entscheidet online der
// fruehere Vorschlag, in der App die niedrigere Nummer an der Tafel mit P vorn.
$gfDaten = json_decode((string)file_get_contents(__DIR__ . '/../Konsens-App/tools/faelle.json'), true);
wahr('faelle.json ist lesbar', is_array($gfDaten) && !empty($gfDaten['faelle']));
$gfGleich = null;
$gfGleich = static function ($ist, $soll) use (&$gfGleich): bool {
    if (is_array($soll)) {
        if (!is_array($ist) || count($ist) !== count($soll)) return false;
        $ist = array_values($ist);
        foreach (array_values($soll) as $i => $s) if (!$gfGleich($ist[$i], $s)) return false;
        return true;
    }
    if ((is_int($soll) || is_float($soll)) && (is_int($ist) || is_float($ist))) return abs($ist - $soll) < 1e-9;
    return $ist === $soll;
};
foreach ($gfDaten['faelle'] ?? [] as $gfFall) {
    [$gfPoll, $gfLeute] = bau((int)$gfFall['anwesende'], ['passiv' => (bool)$gfFall['passiv'], 'schwelle' => 0]);
    merken($gfPoll);
    $gfIds = [];   // Zeile an der Tafel => vorschlag_id, 0 ist die Passivloesung
    $gfZeilen = max(array_map('count', $gfFall['zettel']));
    for ($r = 1; $r < $gfZeilen; $r++) {
        $gfIds[$r] = Vorschlaege::anlegen($gfPoll, $gfLeute[0], 'Vorschlag ' . $r, '', [], true);
    }
    foreach (Vorschlaege::stimmzettel($gfPoll) as $v) if ((int)$v['ist_passiv'] === 1) $gfIds[0] = (int)$v['id'];
    $gfAnlage = $gfPoll->db->prepare('UPDATE vorschlag SET angelegt = ? WHERE id = ?');
    foreach ($gfIds as $r => $vid) $gfAnlage->execute([1000 + $r, $vid]);
    $gfPoll->uebergangBewertung('test');
    $gfAbweichung = [];
    foreach ($gfFall['zettel'] as $i => $gfZettel) {
        foreach ($gfZettel as $r => $wert) {
            if ($wert === null) continue;
            if (!isset($gfIds[$r])) { $gfAbweichung[] = "Wert fuer Zeile $r, die es nicht gibt"; continue; }
            Bewertungen::speichern($gfPoll, (int)$gfLeute[$i]['id'], $gfIds[$r], (int)$wert, false, '');
        }
    }
    $gfErg = Auswertung::rechnen($gfPoll);
    $gfNr = array_flip($gfIds);
    $gfName = static fn(int $vid): string => $gfNr[$vid] === 0 ? 'P' : (string)$gfNr[$vid];
    $gfIst = [
        'wertende'    => $gfErg['wertende'],
        'noetig'      => $gfErg['noetig'],
        'messlatte'   => $gfErg['messlatte'],
        'sieger'      => $gfErg['sieger'] === null ? null : $gfName($gfErg['sieger']),
        'reihenfolge' => array_map(static fn($z) => $gfName((int)$z['id']), $gfErg['zeilen']),
    ];
    $gfSoll = $gfFall['erwartet'];
    foreach ($gfIst as $k => $wert) {
        if (array_key_exists($k, $gfSoll) && !$gfGleich($wert, $gfSoll[$k])) {
            $gfAbweichung[] = "$k: " . json_encode($wert) . ' statt ' . json_encode($gfSoll[$k]);
        }
    }
    foreach ($gfSoll['zeilen'] ?? [] as $nr => $felder) {
        $gfZeile = null;
        foreach ($gfErg['zeilen'] as $z) if ($gfName((int)$z['id']) === (string)$nr) $gfZeile = $z;
        if ($gfZeile === null) { $gfAbweichung[] = "Zeile $nr fehlt"; continue; }
        foreach ($felder as $k => $wert) {
            if (!array_key_exists($k, $gfZeile) || !$gfGleich($gfZeile[$k], $wert)) {
                $gfAbweichung[] = "Zeile $nr, $k: " . json_encode($gfZeile[$k] ?? '(fehlt)') . ' statt ' . json_encode($wert);
            }
        }
    }
    pruefe($gfFall['name'], $gfAbweichung, []);
}

/* ------------------------------------- Beide Sprachen, dieselben Schluessel */
abschnitt('Beide Sprachen kennen dieselben Texte');
// Fehlt ein Schluessel, zeigt die Oberflaeche ihn roh an. Mit der App im
// Verbund (0.3.2) kamen Texte dazu, die leicht nur in einer Sprache landen.
$spDe = require __DIR__ . '/../lang/de.php';
$spEn = require __DIR__ . '/../lang/en.php';
pruefe('kein Text nur auf Deutsch', array_values(array_diff(array_keys($spDe), array_keys($spEn))), []);
pruefe('kein Text nur auf Englisch', array_values(array_diff(array_keys($spEn), array_keys($spDe))), []);

/* ---------------------------------------- Markdown fuer Impressum und Artikel */
abschnitt('Markdown für Impressum und Artikel');
// Dieselben Faelle schickt Konsens-App/tools/test.js durch markdown.js. Treffen
// beide die Erwartung, sieht dieselbe Datei an beiden Orten gleich aus.
$mdDaten = json_decode((string)file_get_contents(__DIR__ . '/../Konsens-App/tools/markdown-faelle.json'), true);
wahr('markdown-faelle.json ist lesbar', is_array($mdDaten) && !empty($mdDaten['faelle']));
foreach ($mdDaten['faelle'] ?? [] as $mdFall) pruefe($mdFall['name'], Markdown::html($mdFall['md']), $mdFall['html']);
pruefe('der Titel ist die erste Überschrift ohne Auszeichnung', Markdown::titel("Vorspann\n# Der **Titel**\n## Nicht"), 'Der Titel');
pruefe('ohne Überschrift kein Titel', Markdown::titel('nur Text'), '');
foreach (['Impressum.example.md' => 'Impressum', 'Artikel.md' => 'Systemisches Konsensieren'] as $mdDatei => $mdAnfang) {
    $mdText = (string)@file_get_contents(__DIR__ . '/../' . $mdDatei);
    wahr("$mdDatei liegt im Hauptverzeichnis", $mdText !== '');
    wahr("$mdDatei beginnt mit seiner Überschrift", strpos(Markdown::titel($mdText), $mdAnfang) === 0);
    $mdHtml = Markdown::html($mdText);
    wahr("$mdDatei rendert ohne rohes HTML und ohne Skript-Adressen",
         strpos($mdHtml, '<script') === false && strpos($mdHtml, 'javascript:') === false);
}
$mdArtikel = Markdown::html((string)file_get_contents(__DIR__ . '/../Artikel.md'));
wahr('der Artikel zeigt seine drei Tabellen', substr_count($mdArtikel, '<table>') === 3);
wahr('und seine Quellen als Links', substr_count($mdArtikel, '<a href="https://') >= 10);
wahr('die App hat ihre eigene Vorlage', Markdown::titel((string)@file_get_contents(__DIR__ . '/../Konsens-App/Impressum.example.md')) === 'Impressum');
// Das ausgefuellte Impressum ist wie config.php Sache der Installation: Es kommt
// nicht ins Quelltextpaket, die Vorlage schon (Spezifikation 20).
$zipNamen = array_map(static fn(array $d): string => $d[1], Zip::dateien());
wahr('das Quelltextpaket enthält die Impressum-Vorlagen',
     in_array('konsensieren/Impressum.example.md', $zipNamen, true)
     && in_array('konsensieren/Konsens-App/Impressum.example.md', $zipNamen, true));
wahr('aber kein ausgefülltes Impressum',
     !in_array('konsensieren/Impressum.md', $zipNamen, true)
     && !in_array('konsensieren/Konsens-App/Impressum.md', $zipNamen, true));

/* ----------------------------------------------------------- Profi-Modus */
abschnitt('Profi-Modus');
// Ein Cookie wie bei der Sprache; aus heisst kein Cookie (Spezifikation 20).
wahr('ohne Cookie aus', !Util::profi());
Util::profiWaehlen('an');
wahr('?profi=an schaltet ein, schon fuer diese Seite', Util::profi());
Util::profiWaehlen('unsinn');
wahr('Unsinn ändert nichts', Util::profi());
ob_start(); erkl('feld.dein_name_dazu'); $profiAn = ob_get_clean();
pruefe('im Profi-Modus schreibt erkl() nichts', $profiAn, '');
Util::profiWaehlen('aus');
wahr('?profi=aus schaltet aus', !Util::profi());
ob_start(); erkl('feld.dein_name_dazu'); $profiAus = ob_get_clean();
pruefe('sonst die Erläuterung als span', $profiAus, '<span class="dazu">' . Util::esc(t('feld.dein_name_dazu')) . '</span>');
ob_start(); erkl('start.keine_mail', [], 'p'); $profiP = ob_get_clean();
wahr('auf Wunsch als Absatz', strpos($profiP, '<p class="dazu">') === 0);

/* ------------------------------------------ Die App fuer den Sitzungsraum */
abschnitt('Die App für den Sitzungsraum');
pruefe('der Verweis zeigt auf das Verzeichnis der App', Util::raum(), Util::basis() . '/Konsens-App/');
pruefe('und auf ihre Anleitung', Util::raum('anleitung.html'), Util::basis() . '/Konsens-App/anleitung.html');
wahr('die Anleitung liegt wirklich dort', is_file(__DIR__ . '/../Konsens-App/anleitung.html'));

/* -------------------------------------------------------------- Phasen */
abschnitt('Phasen');
[$p7, $l7] = bau(2);
merken($p7);
$p7->setzen(['ende_vorschlag' => time() - 10]);
$p7->phaseNachziehen();
pruefe('abgelaufener Termin schaltet weiter', $p7->phase(), Poll::PHASE_BEWERTUNG);
wahr('zurück geht es, solange niemand bewertet hat', $p7->zurueckZurVorschlagsphase());
$p7->uebergangBewertung('test');
$zettel7 = Vorschlaege::stimmzettel($p7);
Bewertungen::speichern($p7, (int)$l7[0]['id'], (int)$zettel7[0]['id'], 3, false, '');
wahr('mit vorhandenen Bewertungen nicht mehr zurück', !$p7->zurueckZurVorschlagsphase());
$p7->setzen(['ende_bewertung' => time() - 10]);
$p7->phaseNachziehen();
pruefe('Bewertungsende schaltet zum Ergebnis', $p7->phase(), Poll::PHASE_ERGEBNIS);

/* -------------------------------------------------------- Aufräumen */
abschnitt('Aufräumen ohne Cron');
[$p8] = bau(1);
$id8 = $p8->id();
$p8->setzen(['loeschdatum' => time() - 10]);
wahr('abgelaufen erkannt', $p8->abgelaufen());
unset($p8);
Housekeeping::jetzt();
wahr('abgelaufene Abstimmung ist gelöscht', !Storage::existiert($id8));

/* --------------------------------------------------------- Aufräumen */
foreach ($GLOBALS['sk_polls'] as $id) Storage::loeschen($id);

echo "\n", str_repeat('-', 50), "\n";
echo $GLOBALS['sk_gut'], " bestanden, ", $GLOBALS['sk_schlecht'], " fehlgeschlagen\n";
exit($GLOBALS['sk_schlecht'] > 0 ? 1 : 0);
