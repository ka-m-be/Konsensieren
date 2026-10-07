<?php
/**
 * Testszenarien zum positiven Framing (Spec/52_Testszenarien.md).
 *
 * Anders als tools/test.php sichert dieses Skript nicht nur zu, sondern
 * *berichtet*: Es fuehrt jedes Szenario vor und schreibt Rangfolge, Kennzahlen
 * und Urteil in eine Tabelle.
 *
 * Der Witz dabei: Es laeuft in beiden Welten. Vor der Umstellung speichert die
 * Anwendung Widerstand, danach Zustimmung. Die Szenarien sind durchgaengig in
 * *Zustimmungswerten* notiert, und der Bericht rechnet alles auf Zustimmung um.
 * Deshalb muessen Ausgangsmessung und Nachmessung im vergleichbaren Teil
 * zeichengleich sein - ein blosses diff genuegt als Beweis, dass die Umstellung
 * die Ergebnisse nicht angetastet hat.
 *
 * Aufruf:
 *   php tools/szenarien.php             alle Szenarien nach stdout
 *   php tools/szenarien.php 5           nur Szenario 5
 *   php tools/szenarien.php --ablage    zusaetzlich als Spec/53_Messung_<datum>.md
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit("Nur auf der Kommandozeile.\n");

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/index.php';
define('SK_EINSTIEG', true);
require __DIR__ . '/../lib/bootstrap.php';

/* ==================================================================== Rahmen */

$GLOBALS['sk_zeilen']  = [];   // der Bericht, Zeile fuer Zeile
$GLOBALS['sk_polls']   = [];   // aufzuraeumende Abstimmungen
$GLOBALS['sk_gut']     = 0;
$GLOBALS['sk_schlecht'] = 0;

function sag(string $zeile = ''): void { $GLOBALS['sk_zeilen'][] = $zeile; }

function pruefe(string $was, $ist, $soll): void
{
    if ($ist === $soll) { $GLOBALS['sk_gut']++; sag("- ok · $was"); return; }
    $GLOBALS['sk_schlecht']++;
    sag("- **FEHLER** · $was");
    sag("  - erwartet: `" . var_export($soll, true) . "`");
    sag("  - bekommen: `" . var_export($ist, true) . "`");
}

function wahr(string $was, bool $ist): void { pruefe($was, $ist, true); }

/**
 * Speichert die Anwendung Zustimmung (neue Welt) oder Widerstand (alte)?
 * Erkennbar an der Spalte, die die Datenwanderung mitbringt.
 */
function neueWelt(Poll $p): bool
{
    static $bekannt = null;
    if ($bekannt !== null) return $bekannt;
    $bekannt = false;
    foreach ($p->db->query('PRAGMA table_info(poll)')->fetchAll() as $spalte) {
        if ((string)$spalte['name'] === 'skala_version') $bekannt = true;
    }
    return $bekannt;
}

/** Eine frische Abstimmung mit n Personen, gleich in der Bewertungsphase. */
function bau(int $personen, array $opt = []): array
{
    $opt += ['passiv' => true, 'passiv_text' => 'Alles bleibt bei der bisherigen Regelung.',
             'veto' => false, 'schwelle' => 0, 'quorum' => 50];
    [$poll] = Poll::anlegen('Szenario', '', time() + 86400, time() + 2 * 86400, $opt);
    $GLOBALS['sk_polls'][] = $poll->id();
    $leute = [];
    for ($i = 0; $i < $personen; $i++) {
        [$id] = $poll->teilnehmerAnlegen('P' . ($i + 1), $i === 0);
        $leute[] = ['id' => $id, 'name' => 'P' . ($i + 1)];
    }
    return [$poll, $leute];
}

/** Legt Vorschlaege an und gibt Titel => id zurueck. */
function vorschlaege(Poll $p, array $leute, array $titel): array
{
    $aus = [];
    foreach ($titel as $i => $t) {
        $aus[$t] = Vorschlaege::anlegen($p, $leute[$i % count($leute)], $t, '', [], false);
    }
    return $aus;
}

/** Der Titel der Passivloesung, wie ihn die Sprachdatei vorgibt. */
function pt(): string { return t('passiv.titel'); }

/** id der Passivloesung auf dem Stimmzettel. */
function passivId(Poll $p): ?int
{
    foreach (Vorschlaege::stimmzettel($p) as $v) {
        if ((int)$v['ist_passiv'] === 1) return (int)$v['id'];
    }
    return null;
}

/**
 * Traegt einen *Zustimmungswert* ein - in der alten Welt gedreht.
 * null heisst unbewertet und bleibt in beiden Welten unbewertet.
 */
function setzeZ(Poll $p, int $tid, int $vid, ?int $z, bool $veto = false, string $grund = ''): void
{
    $wert = ($z === null) ? null : (neueWelt($p) ? $z : 10 - $z);
    Bewertungen::speichern($p, $tid, $vid, $wert, $veto, $grund);
}

/**
 * Rechnet eine Ergebniszeile auf Zustimmung um, gleich in welcher Welt sie
 * entstanden ist. Das Ergebnis ist der vergleichbare Teil des Berichts.
 */
function alsZustimmung(Poll $p, array $z): array
{
    $neu = neueWelt($p);
    $mittel = $z['mittel'] === null ? null : ($neu ? (float)$z['mittel'] : 10.0 - (float)$z['mittel']);
    // Mindestzustimmung: in der alten Welt der gespiegelte Hoechstwiderstand.
    $mindest = $z['bewertet'] > 0
        ? ($neu ? (int)($z['minimal'] ?? 0) : 10 - (int)$z['maximal'])
        : null;
    $verteilung = [];
    for ($i = 0; $i <= 10; $i++) {
        $verteilung[$i] = (int)$z['verteilung'][$neu ? $i : 10 - $i];
    }
    return [
        'titel'       => (string)$z['titel'],
        'rang'        => $z['rang'],
        'mittel'      => $mittel,
        'bewertet'    => (int)$z['bewertet'],
        'mindest'     => $mindest,
        'schwach'     => (int)($neu ? ($z['niedrig'] ?? 0) : $z['hoch']),   // Werte bis 2
        'kik'         => $z['kik'],
        'legitimiert' => $z['legitimiert'],
        'belastbar'   => (bool)$z['belastbar'],
        'gesperrt'    => (bool)$z['gesperrt'],
        'passiv'      => (int)$z['ist_passiv'] === 1,
        'verteilung'  => $verteilung,
    ];
}

function zahl(?float $v, int $stellen = 2): string
{
    return $v === null ? '–' : number_format($v, $stellen, ',', '');
}

/** Die Tabelle, auf die es ankommt. Titel statt ids, damit sie vergleichbar ist. */
function bericht(Poll $p, ?string $ueberschrift = null): array
{
    $erg = Auswertung::rechnen($p);
    $siegerTitel = null;
    $zeilen = [];
    foreach ($erg['zeilen'] as $z) {
        $u = alsZustimmung($p, $z);
        if ($erg['sieger'] !== null && (int)$z['id'] === $erg['sieger']) $siegerTitel = $u['titel'];
        $zeilen[] = $u;
    }
    if ($ueberschrift !== null) sag("**$ueberschrift**");
    sag();
    sag('| Rang | Vorschlag | Ø Rückhalt | bewertet | Mindestwert | Werte bis 2 | KiK | Urteil |');
    sag('|---|---|---|---|---|---|---|---|');
    foreach ($zeilen as $u) {
        $urteil = [];
        if ($siegerTitel === $u['titel'])   $urteil[] = 'am breitesten getragen';
        if ($u['gesperrt'])                 $urteil[] = 'blockiert';
        if (!$u['belastbar'])               $urteil[] = 'zu wenig Bewertungen';
        if ($u['legitimiert'] === false)    $urteil[] = 'nicht besser als Nichtstun';
        if ($u['passiv'])                   $urteil[] = 'Passivlösung';
        $zeile = [
            $u['rang'] === null ? '·' : (string)(int)$u['rang'],
            $u['titel'],
            zahl($u['mittel']),
            (string)$u['bewertet'],
            $u['mindest'] === null ? '–' : (string)$u['mindest'],
            (string)$u['schwach'],
            $u['kik'] === null ? '–' : zahl((float)$u['kik']),
            $urteil ? implode(', ', $urteil) : '',
        ];
        sag('| ' . implode(' | ', $zeile) . ' |');
    }
    sag();
    sag(sprintf('Wertende: %d von %d · nötige Bewertungen: %d · Modus: %s · Sieger: %s',
        $erg['wertende'], $erg['teilnehmer'], $erg['noetig'], $erg['modus'],
        $siegerTitel ?? 'keiner'));
    sag();
    return ['erg' => $erg, 'zeilen' => $zeilen, 'sieger' => $siegerTitel,
            'nach' => array_column($zeilen, null, 'titel'),
            'reihe' => array_column($zeilen, 'titel')];
}

/* ================================================================ Szenarien */

$szenarien = [];

/* ---------------------------------------------------------------- Fall 1 */
$szenarien[1] = ['Klare Lage', function (): void {
    [$p, $l] = bau(5);
    $v = vorschlaege($p, $l, ['A', 'B', 'C']);
    $p->uebergangBewertung('szenario');
    $pv = passivId($p);
    $daten = [   //        A   B   C   Passiv
        [9, 6, 2, 4],
        [8, 7, 3, 3],
        [10, 5, 1, 5],
        [7, 6, 4, 4],
        [9, 8, 0, 2],
    ];
    foreach ($daten as $i => $reihe) {
        $tid = (int)$l[$i]['id'];
        setzeZ($p, $tid, $v['A'], $reihe[0]);
        setzeZ($p, $tid, $v['B'], $reihe[1]);
        setzeZ($p, $tid, $v['C'], $reihe[2]);
        setzeZ($p, $tid, $pv,     $reihe[3]);
    }
    $b = bericht($p);
    pruefe('A gewinnt', $b['sieger'], 'A');
    pruefe('Reihenfolge A, B, Passiv, C', $b['reihe'], ['A', 'B', pt(), 'C']);
    pruefe('Ø Rückhalt von A', zahl($b['nach']['A']['mittel']), '8,60');
    pruefe('Kraft im Konsens von A', zahl((float)$b['nach']['A']['kik']), '5,00');
    wahr('C ist nicht besser als Nichtstun', $b['nach']['C']['legitimiert'] === false);
    pruefe('Mindestwert bei C', $b['nach']['C']['mindest'], 0);
}];

/* ---------------------------------------------------------------- Fall 2 */
$szenarien[2] = ['Gleicher Mittelwert, andere Lage', function (): void {
    [$p, $l] = bau(6);
    $v = vorschlaege($p, $l, ['D einig', 'E gespalten']);
    $p->uebergangBewertung('szenario');
    $pv = passivId($p);
    $werteE = [10, 10, 10, 0, 0, 0];
    foreach ($l as $i => $person) {
        $tid = (int)$person['id'];
        setzeZ($p, $tid, $v['D einig'],     5);
        setzeZ($p, $tid, $v['E gespalten'], $werteE[$i]);
        setzeZ($p, $tid, $pv,               3);
    }
    $b = bericht($p);
    pruefe('gleicher Mittelwert', zahl($b['nach']['D einig']['mittel']),
                                   zahl($b['nach']['E gespalten']['mittel']));
    pruefe('der einige Vorschlag gewinnt den Gleichstand', $b['sieger'], 'D einig');
    pruefe('Mindestwert trennt die beiden', [$b['nach']['D einig']['mindest'],
                                             $b['nach']['E gespalten']['mindest']], [5, 0]);
    pruefe('drei sehr niedrige Werte bei E', $b['nach']['E gespalten']['schwach'], 3);
    pruefe('keine niedrigen Werte bei D', $b['nach']['D einig']['schwach'], 0);
    sag('Verteilung E (Zustimmung 0 → 10): ' . implode(' ', $b['nach']['E gespalten']['verteilung']));
    sag('Verteilung D (Zustimmung 0 → 10): ' . implode(' ', $b['nach']['D einig']['verteilung']));
    sag();
}];

/* ---------------------------------------------------------------- Fall 3 */
$szenarien[3] = ['Offengelassenes zählt nicht', function (): void {
    [$p, $l] = bau(5);
    $v = vorschlaege($p, $l, ['A', 'B', 'C']);
    $p->uebergangBewertung('szenario');
    $pv = passivId($p);
    $daten = [
        [9, 6, 2, 4],
        [8, 7, 3, 3],
        [10, 5, 1, 5],
        [7, null, null, null],
        [9, null, null, null],
    ];
    foreach ($daten as $i => $reihe) {
        $tid = (int)$l[$i]['id'];
        setzeZ($p, $tid, $v['A'], $reihe[0]);
        setzeZ($p, $tid, $v['B'], $reihe[1]);
        setzeZ($p, $tid, $v['C'], $reihe[2]);
        setzeZ($p, $tid, $pv,     $reihe[3]);
    }
    $b = bericht($p, 'Beteiligungsschwelle 50 % (nötig: 3)');
    pruefe('A von fünf bewertet', $b['nach']['A']['bewertet'], 5);
    pruefe('B von dreien bewertet', $b['nach']['B']['bewertet'], 3);
    wahr('B bleibt belastbar', $b['nach']['B']['belastbar']);

    // Die Umkehrprobe: fehlende Werte sind keine Zeilen, nicht Zeilen mit 0.
    $offen = (int)$p->db->query('SELECT COUNT(*) FROM bewertung')->fetchColumn();
    pruefe('nur abgegebene Bewertungen stehen in der Datenbank', $offen, 3 * 4 + 2);
    // Entscheidend ist nicht, welche Zahl dort steht, sondern dass gar keine Zeile
    // existiert. Nur so kann Offengelassenes nicht als Wert missverstanden werden.
    $st = $p->db->prepare('SELECT COUNT(*) FROM bewertung WHERE teilnehmer_id = ?');
    $st->execute([(int)$l[3]['id']]);
    pruefe('wer nur einen Vorschlag bewertet hat, hat auch nur eine Zeile', (int)$st->fetchColumn(), 1);

    $p->setzen(['quorum_prozent' => 80]);
    $b2 = bericht($p, 'dieselbe Lage bei Beteiligungsschwelle 80 % (nötig: 4)');
    wahr('B fällt aus der Rangfolge', !$b2['nach']['B']['belastbar']);
    wahr('C fällt aus der Rangfolge', !$b2['nach']['C']['belastbar']);
    pruefe('A gewinnt weiterhin', $b2['sieger'], 'A');
    $p->setzen(['quorum_prozent' => 50]);
}];

/* ---------------------------------------------------------------- Fall 4 */
$szenarien[4] = ['Die kaum bewertete Passivlösung', function (): void {
    [$p, $l] = bau(3);
    $v = vorschlaege($p, $l, ['Vorschlag A', 'Vorschlag B']);
    $p->uebergangBewertung('szenario');
    $pv = passivId($p);
    foreach ([[6, 4], [5, 3], [6, 2]] as $i => $paar) {
        $tid = (int)$l[$i]['id'];
        setzeZ($p, $tid, $v['Vorschlag A'], $paar[0]);
        setzeZ($p, $tid, $v['Vorschlag B'], $paar[1]);
    }
    setzeZ($p, (int)$l[0]['id'], $pv, 7);   // nur eine Person bewertet die Passivloesung

    $b = bericht($p, 'nur eine Bewertung für die Passivlösung');
    $passiv = $b['nach'][pt()];
    wahr('die Passivlösung ist nicht belastbar', !$passiv['belastbar']);
    wahr('sie bekommt keinen Rang', $passiv['rang'] === null);
    wahr('ohne Messlatte keine Kraft im Konsens', $b['nach']['Vorschlag A']['kik'] === null);
    wahr('und kein Urteil über das Nichtstun', $b['nach']['Vorschlag A']['legitimiert'] === null);
    pruefe('gewonnen hat der Vorschlag mit dem größten Rückhalt', $b['sieger'], 'Vorschlag A');

    setzeZ($p, (int)$l[1]['id'], $pv, 9);
    $b2 = bericht($p, 'sobald eine zweite Person die Passivlösung bewertet');
    $passiv2 = $b2['nach'][pt()];
    wahr('jetzt ist sie belastbar', $passiv2['belastbar']);
    pruefe('und sie gewinnt zu Recht', $b2['sieger'], pt());
    wahr('A ist nicht besser als Nichtstun', $b2['nach']['Vorschlag A']['legitimiert'] === false);
}];

/* ---------------------------------------------------------------- Fall 5 */
$szenarien[5] = ['Bullet Voting', function (): void {
    // Neun ehrliche, differenzierte Stimmzettel.
    $ehrlich = [
        [8, 7, 5, 4], [7, 8, 4, 5], [9, 6, 6, 4], [6, 7, 5, 5], [8, 8, 3, 4],
        [7, 6, 6, 5], [9, 7, 4, 3], [6, 8, 5, 4], [8, 7, 5, 5],
    ];
    $bauen = function (bool $mitSaboteur) use ($ehrlich): array {
        [$p, $l] = bau($mitSaboteur ? 10 : 9);
        $v = vorschlaege($p, $l, ['A', 'B', 'C']);
        $p->uebergangBewertung('szenario');
        $pv = passivId($p);
        foreach ($ehrlich as $i => $reihe) {
            $tid = (int)$l[$i]['id'];
            setzeZ($p, $tid, $v['A'], $reihe[0]);
            setzeZ($p, $tid, $v['B'], $reihe[1]);
            setzeZ($p, $tid, $v['C'], $reihe[2]);
            setzeZ($p, $tid, $pv,     $reihe[3]);
        }
        if ($mitSaboteur) {
            $tid = (int)$l[9]['id'];
            setzeZ($p, $tid, $v['C'], 10);   // der eigene Liebling
            setzeZ($p, $tid, $v['A'], 0);
            setzeZ($p, $tid, $v['B'], 0);
            setzeZ($p, $tid, $pv,     0);
        }
        return [$p, $v];
    };

    [$p1] = $bauen(false);
    $ohne = bericht($p1, 'neun ehrliche Stimmzettel');
    [$p2] = $bauen(true);
    $mit = bericht($p2, 'dieselben neun, dazu ein Stimmzettel nach dem Muster „mein Liebling 10, alles andere 0“');

    pruefe('ohne Saboteur gewinnt A', $ohne['sieger'], 'A');
    pruefe('mit Saboteur gewinnt weiterhin A', $mit['sieger'], 'A');
    pruefe('die Rangfolge bleibt unverändert', $mit['reihe'], $ohne['reihe']);
    foreach (['A', 'B', 'C'] as $t) {
        sag(sprintf('- %s: Ø %s → %s (%s), Mindestwert %s → %s, Werte bis 2: %d → %d',
            $t,
            zahl($ohne['nach'][$t]['mittel']), zahl($mit['nach'][$t]['mittel']),
            zahl((float)$mit['nach'][$t]['mittel'] - (float)$ohne['nach'][$t]['mittel']),
            (string)$ohne['nach'][$t]['mindest'], (string)$mit['nach'][$t]['mindest'],
            $ohne['nach'][$t]['schwach'], $mit['nach'][$t]['schwach']));
    }
    sag();
    wahr('der Stimmzettel schlägt sich in „Werte bis 2“ nieder',
         $mit['nach']['A']['schwach'] === 1 && $mit['nach']['B']['schwach'] === 1);
    pruefe('und drückt den Mindestwert von A auf null', $mit['nach']['A']['mindest'], 0);
}];

/* ---------------------------------------------------------------- Fall 6 */
$szenarien[6] = ['Konsequente Nullen', function (): void {
    $ehrlich = [[8, 5, 3, 4], [7, 6, 2, 5], [9, 5, 4, 4], [8, 6, 3, 3]];
    $bauen = function (?int $starr) use ($ehrlich): Poll {
        [$p, $l] = bau($starr === null ? 4 : 5);
        $v = vorschlaege($p, $l, ['A', 'B', 'C']);
        $p->uebergangBewertung('szenario');
        $pv = passivId($p);
        foreach ($ehrlich as $i => $reihe) {
            $tid = (int)$l[$i]['id'];
            setzeZ($p, $tid, $v['A'], $reihe[0]);
            setzeZ($p, $tid, $v['B'], $reihe[1]);
            setzeZ($p, $tid, $v['C'], $reihe[2]);
            setzeZ($p, $tid, $pv,     $reihe[3]);
        }
        if ($starr !== null) {
            $tid = (int)$l[4]['id'];
            foreach ([$v['A'], $v['B'], $v['C'], $pv] as $vid) setzeZ($p, $tid, $vid, $starr);
        }
        return $p;
    };

    $ohne  = bericht($bauen(null), 'vier ehrliche Stimmzettel');
    $null  = bericht($bauen(0),    'dazu eine Person, die überall 0 setzt – auch bei der Passivlösung');
    pruefe('die Rangfolge bleibt unverändert', $null['reihe'], $ohne['reihe']);
    pruefe('der Sieger bleibt derselbe', $null['sieger'], $ohne['sieger']);
    foreach (['A', 'B', 'C', pt()] as $t) {
        sag(sprintf('- %s: Ø %s → %s', $t, zahl($ohne['nach'][$t]['mittel']), zahl($null['nach'][$t]['mittel'])));
    }
    sag();
    wahr('alle Mittelwerte sinken', (float)$null['nach']['A']['mittel'] < (float)$ohne['nach']['A']['mittel']);
    wahr('auch die Passivlösung sinkt mit – das Niveau verschiebt sich, nicht die Auswahl',
         (float)$null['nach'][pt()]['mittel']
         < (float)$ohne['nach'][pt()]['mittel']);
    // Ein Stimmzettel mit lauter gleichen Werten multipliziert jeden Mittelwert mit
    // (n-1)/n - und damit auch ihre Differenz. Die Auswahl bleibt, der Abstand zum
    // Nichtstun schrumpft. Das gilt fuer lauter Nullen wie fuer lauter Zehner.
    pruefe('die Kraft im Konsens schrumpft um genau den Faktor 4/5',
           zahl((float)$null['nach']['A']['kik']),
           zahl((float)$ohne['nach']['A']['kik'] * 4 / 5));
}];

/* ---------------------------------------------------------------- Fall 7 */
$szenarien[7] = ['Konsequente Zehner', function (): void {
    $ehrlich = [[8, 5, 3, 4], [7, 6, 2, 5], [9, 5, 4, 4], [8, 6, 3, 3]];
    [$p, $l] = bau(5);
    $v = vorschlaege($p, $l, ['A', 'B', 'C']);
    $p->uebergangBewertung('szenario');
    $pv = passivId($p);
    foreach ($ehrlich as $i => $reihe) {
        $tid = (int)$l[$i]['id'];
        setzeZ($p, $tid, $v['A'], $reihe[0]);
        setzeZ($p, $tid, $v['B'], $reihe[1]);
        setzeZ($p, $tid, $v['C'], $reihe[2]);
        setzeZ($p, $tid, $pv,     $reihe[3]);
    }
    $tid = (int)$l[4]['id'];
    foreach ([$v['A'], $v['B'], $v['C'], $pv] as $vid) setzeZ($p, $tid, $vid, 10);

    $b = bericht($p, 'vier ehrliche Stimmzettel, dazu eine Person, die überall 10 setzt');
    pruefe('A gewinnt weiterhin', $b['sieger'], 'A');
    pruefe('die Kraft im Konsens schrumpft um denselben Faktor 4/5 wie bei lauter Nullen',
           zahl((float)$b['nach']['A']['kik']), zahl(4.0 * 4 / 5));
    wahr('kein Vorschlag bekommt dadurch einen niedrigen Wert',
         $b['nach']['A']['schwach'] === 0);
    sag('Befund: Die Zeile ist für die Auswahl harmlos – sie hebt alle Mittelwerte,');
    sag('einschließlich der Passivlösung, und lässt die Rangfolge unberührt. Sie ist es');
    sag('aber nicht für den Abstand zum Nichtstun: Ein Stimmzettel mit lauter gleichen');
    sag('Werten staucht jede Kraft im Konsens um den Faktor (n−1)/n, gleich welcher Wert');
    sag('darauf steht. Lauter Nullen und lauter Zehner wirken darin genau gleich – das');
    sag('ist keine Eigenart des neuen Framings, sondern gilt schon heute. Auffällig ist');
    sag('sie nur in der Bewertungsmatrix, und dort unter positivem Framing weniger als');
    sag('vorher: eine Zeile aus lauter Zehnern liest sich als Zustimmung, während dieselbe');
    sag('Haltung früher als Zeile aus lauter Nullen erschien. Das ist ein Befund für die');
    sag('Oberfläche, nicht für die Rechnung.');
    sag();
}];

/* ---------------------------------------------------------------- Fall 8 */
$szenarien[8] = ['Zustimmungsverzerrung', function (): void {
    $roh = [[9, 6, 2, 4], [8, 7, 3, 3], [10, 5, 1, 5], [7, 6, 4, 4], [9, 8, 0, 2]];
    $bauen = function (int $hub, bool $auchPassiv) use ($roh): Poll {
        [$p, $l] = bau(5);
        $v = vorschlaege($p, $l, ['A', 'B', 'C']);
        $p->uebergangBewertung('szenario');
        $pv = passivId($p);
        foreach ($roh as $i => $reihe) {
            $tid = (int)$l[$i]['id'];
            setzeZ($p, $tid, $v['A'], min(10, $reihe[0] + $hub));
            setzeZ($p, $tid, $v['B'], min(10, $reihe[1] + $hub));
            setzeZ($p, $tid, $v['C'], min(10, $reihe[2] + $hub));
            setzeZ($p, $tid, $pv,     min(10, $reihe[3] + ($auchPassiv ? $hub : 0)));
        }
        return $p;
    };

    $ohne = bericht($bauen(0, true),  'Ausgangslage (wie Fall 1)');
    $mit  = bericht($bauen(2, true),  'alle Werte um 2 gehoben, Deckelung bei 10, Passivlösung gehoben');
    $halb = bericht($bauen(2, false), 'alle Werte um 2 gehoben, Passivlösung *nicht* gehoben');

    pruefe('gehobene Werte ändern die Rangfolge nicht', $mit['reihe'], $ohne['reihe']);
    $legitOhne = 0; $legitHalb = 0;
    foreach ($ohne['zeilen'] as $z) if ($z['legitimiert'] === true && !$z['passiv']) $legitOhne++;
    foreach ($halb['zeilen'] as $z) if ($z['legitimiert'] === true && !$z['passiv']) $legitHalb++;
    sag(sprintf('- als besser als Nichtstun gelten: %d ohne Hub, %d wenn nur die Vorschläge gehoben werden',
        $legitOhne, $legitHalb));
    sag(sprintf('- Deckeneffekt: Ø von A steigt von %s auf %s, der Abstand zu B von %s auf %s',
        zahl($ohne['nach']['A']['mittel']), zahl($mit['nach']['A']['mittel']),
        zahl((float)$ohne['nach']['A']['mittel'] - (float)$ohne['nach']['B']['mittel']),
        zahl((float)$mit['nach']['A']['mittel'] - (float)$mit['nach']['B']['mittel'])));
    sag();
    sag('Befund: Solange die Passivlösung mitgehoben wird, ist die Verzerrung folgenlos.');
    sag('Wird sie es nicht, gelten mehr Vorschläge als legitimiert – das ist der einzige');
    sag('Weg, auf dem das neue Framing die Methode doch verändern könnte. Am Rechner ist');
    sag('das nicht entscheidbar; es bleibt Beobachtungspunkt für den ersten Praxistest.');
    sag();
}];

/* ---------------------------------------------------------------- Fall 9 */
$szenarien[9] = ['Vollständiger Gleichstand', function (): void {
    foreach ([0, 5, 10] as $wert) {
        [$p, $l] = bau(4);
        $v = vorschlaege($p, $l, ['A', 'B']);
        $p->uebergangBewertung('szenario');
        $pv = passivId($p);
        foreach ($l as $person) {
            $tid = (int)$person['id'];
            foreach ([$v['A'], $v['B'], $pv] as $vid) setzeZ($p, $tid, $vid, $wert);
        }
        $b = bericht($p, "alle bewerten alles mit $wert");
        pruefe("bei $wert gewinnt die Passivlösung", $b['sieger'], pt());
        pruefe("bei $wert ist die Kraft im Konsens null", zahl((float)$b['nach']['A']['kik']), '0,00');
        wahr("bei $wert gilt A als nicht besser als Nichtstun", $b['nach']['A']['legitimiert'] === false);
        wahr("bei $wert sind alle Vorschläge belastbar", $b['nach']['A']['belastbar']);
    }
    sag('Befund: Die Rechnung ist richtig – kein Vorschlag hat mehr Rückhalt als das');
    sag('Nichtstun, also bleibt es beim Nichtstun. Das gilt auch dann, wenn alle alles');
    sag('mit 10 bewerten: Begeisterung für jeden Vorschlag ist noch kein Grund, etwas zu');
    sag('ändern. Die Beschriftung trifft es aber nicht. A und B stehen mit');
    sag('„bringt weniger als Nichtstun“ da, obwohl sie exakt gleichauf liegen – das ist');
    sag('sachlich falsch und unter positivem Framing doppelt ärgerlich. Nötig ist ein');
    sag('eigener Zustand „gleichauf mit dem Nichtstun“ neben „darunter“.');
    sag();
}];

/* --------------------------------------------------------------- Fall 10 */
$szenarien[10] = ['Wanderung der Bestandsdaten', function (): void {
    [$probe] = bau(1);
    if (!neueWelt($probe)) {
        sag('Übersprungen: Die Wanderung gibt es erst nach der Umstellung.');
        sag();
        return;
    }

    // Eine Abstimmung im alten Format herstellen: Widerstandswerte in der Datenbank
    // und skala_version zurueck auf 0, so wie sie eine Datei aus 0.1.3 mitbringt.
    [$p, $l] = bau(5);
    $id = $p->id();
    $v = vorschlaege($p, $l, ['A', 'B', 'C']);
    $p->uebergangBewertung('szenario');
    $pv = passivId($p);
    $daten = [
        [9, 6, 2, 4],
        [8, 7, 3, 3],
        [10, 5, 1, 5],
        [7, null, 4, 4],       // eine Luecke, damit NULL mitgeprueft wird
        [9, 8, 0, 2],
    ];
    foreach ($daten as $i => $reihe) {
        $tid = (int)$l[$i]['id'];
        foreach ([$v['A'], $v['B'], $v['C'], $pv] as $sp => $vid) {
            $z = $reihe[$sp];
            // Ausdruecklich als Widerstand schreiben, an setzeZ() vorbei.
            Bewertungen::speichern($p, $tid, $vid, $z === null ? null : 10 - $z, false, '');
        }
    }
    $vorher = [
        'zeilen' => (int)$p->db->query('SELECT COUNT(*) FROM bewertung')->fetchColumn(),
        'werte'  => $p->db->query('SELECT vorschlag_id, teilnehmer_id, wert FROM bewertung
                                   ORDER BY vorschlag_id, teilnehmer_id')->fetchAll(),
    ];
    $p->db->exec('UPDATE poll SET skala_version = 0');
    unset($p);

    // Erstes Oeffnen: die Wanderung greift.
    $p1 = Poll::laden($id);
    pruefe('die Abstimmung laesst sich oeffnen', $p1 instanceof Poll, true);
    pruefe('die Fassung der Skala steht danach auf 1',
           (int)$p1->db->query('SELECT skala_version FROM poll')->fetchColumn(), 1);
    pruefe('es sind gleich viele Zeilen wie vorher',
           (int)$p1->db->query('SELECT COUNT(*) FROM bewertung')->fetchColumn(), $vorher['zeilen']);
    $nachher = $p1->db->query('SELECT vorschlag_id, teilnehmer_id, wert FROM bewertung
                               ORDER BY vorschlag_id, teilnehmer_id')->fetchAll();
    $gedreht = true;
    foreach ($vorher['werte'] as $k => $zeile) {
        $alt = $zeile['wert'];
        $neu = $nachher[$k]['wert'];
        if ($alt === null ? $neu !== null : (int)$neu !== 10 - (int)$alt) $gedreht = false;
    }
    wahr('jeder Wert ist genau einmal gedreht, NULL blieb NULL', $gedreht);
    pruefe('unbewertet ist unbewertet geblieben',
           (int)$p1->db->query('SELECT COUNT(*) FROM bewertung WHERE wert IS NULL')->fetchColumn(),
           (int)$p1->db->query('SELECT COUNT(*) FROM bewertung WHERE wert IS NULL')->fetchColumn());

    $b1 = bericht($p1, 'nach der Wanderung');
    pruefe('die Rangfolge stimmt mit der Ausgangsmessung überein',
           $b1['reihe'], ['A', 'B', pt(), 'C']);
    pruefe('Ø Rückhalt von A', zahl($b1['nach']['A']['mittel']), '8,60');

    // Zweites Oeffnen: nichts darf sich mehr aendern.
    unset($p1);
    $p2 = Poll::laden($id);
    $zweimal = $p2->db->query('SELECT vorschlag_id, teilnehmer_id, wert FROM bewertung
                               ORDER BY vorschlag_id, teilnehmer_id')->fetchAll();
    pruefe('ein zweites Oeffnen dreht nicht noch einmal', $zweimal, $nachher);
    $b2 = bericht($p2, 'nach dem zweiten Öffnen');
    pruefe('und das Ergebnis bleibt gleich', $b2['reihe'], $b1['reihe']);

    // Was nicht mitwandern darf.
    pruefe('Vetos bleiben unberührt',
           (int)$p2->db->query('SELECT COUNT(*) FROM bewertung WHERE veto = 1')->fetchColumn(), 0);
    pruefe('das Protokoll ist unverändert',
           count($p2->protokoll()) > 0, true);
    unset($p2);
}];

/* --------------------------------------------------------------- Fall 11 */
$szenarien[11] = ['Kleine Gruppen und harte Schwellen', function (): void {
    // eine wertende Person
    [$p, $l] = bau(1);
    $v = vorschlaege($p, $l, ['A']);
    $p->uebergangBewertung('szenario');
    setzeZ($p, (int)$l[0]['id'], $v['A'], 8);
    setzeZ($p, (int)$l[0]['id'], (int)passivId($p), 3);
    $b = bericht($p, 'eine einzige wertende Person');
    // Die Spezifikation verlangt mindestens zwei Bewertungen. Bei nur einer wertenden
    // Person waere damit nie etwas belastbar; deshalb deckelt mindestBewertungen() den
    // Wert auf die Zahl der Wertenden. Der Grenzfall ist bewusst so gelassen.
    pruefe('nötig ist hier nur eine Bewertung', $b['erg']['noetig'], 1);
    wahr('A gilt damit als belastbar', $b['nach']['A']['belastbar']);
    pruefe('und gewinnt', $b['sieger'], 'A');

    // Schwelle 100 %
    [$p2, $l2] = bau(4, ['quorum' => 100]);
    $v2 = vorschlaege($p2, $l2, ['A', 'B']);
    $p2->uebergangBewertung('szenario');
    $pv2 = passivId($p2);
    foreach ($l2 as $i => $person) {
        $tid = (int)$person['id'];
        setzeZ($p2, $tid, $v2['A'], 8);
        setzeZ($p2, $tid, $pv2, 3);
        if ($i < 3) setzeZ($p2, $tid, $v2['B'], 9);   // einer fehlt
    }
    $b2 = bericht($p2, 'Beteiligungsschwelle 100 %, bei B fehlt eine Bewertung');
    wahr('B fällt aus der Rangfolge', !$b2['nach']['B']['belastbar']);
    pruefe('A gewinnt', $b2['sieger'], 'A');

    // nur vollstaendige Stimmzettel, aber niemand ist vollstaendig
    $p2->setzen(['quorum_prozent' => 50, 'nur_vollstaendig' => 1]);
    $p2->db->prepare('DELETE FROM bewertung WHERE vorschlag_id = ?')->execute([$v2['B']]);
    $b3 = bericht($p2, 'nur vollständige Stimmzettel, aber niemand hat alles bewertet');
    pruefe('null Wertende', $b3['erg']['wertende'], 0);
    wahr('kein Sieger, kein Absturz', $b3['sieger'] === null);
    $p2->setzen(['nur_vollstaendig' => 0]);

    // ohne Passivloesung
    [$p3, $l3] = bau(4, ['passiv' => false]);
    $v3 = vorschlaege($p3, $l3, ['A', 'B']);
    $p3->uebergangBewertung('szenario');
    foreach ($l3 as $person) {
        setzeZ($p3, (int)$person['id'], $v3['A'], 8);
        setzeZ($p3, (int)$person['id'], $v3['B'], 4);
    }
    $b4 = bericht($p3, 'Abstimmung ohne Passivlösung');
    pruefe('A gewinnt', $b4['sieger'], 'A');
    wahr('ohne Messlatte keine Kraft im Konsens', $b4['nach']['A']['kik'] === null);
    wahr('und kein Urteil über das Nichtstun', $b4['nach']['A']['legitimiert'] === null);

    // Last
    [$p4, $l4] = bau(25);
    $titel = [];
    for ($i = 1; $i <= 60; $i++) $titel[] = 'V' . $i;
    $p4->db->beginTransaction();
    $v4 = vorschlaege($p4, $l4, $titel);
    $p4->db->commit();
    $p4->uebergangBewertung('szenario');
    $pv4 = passivId($p4);
    $start = microtime(true);
    $p4->db->beginTransaction();
    foreach ($l4 as $i => $person) {
        $tid = (int)$person['id'];
        foreach ($v4 as $nr => $vid) setzeZ($p4, $tid, $vid, ($i * 7 + strlen($nr)) % 11);
        setzeZ($p4, $tid, $pv4, 5);
    }
    $p4->db->commit();
    $erg4 = Auswertung::rechnen($p4);
    $dauer = microtime(true) - $start;
    pruefe('60 Vorschläge und 25 Personen ergeben 61 Zeilen', count($erg4['zeilen']), 61);
    wahr('Schreiben und Rechnen bleiben unter fünf Sekunden', $dauer < 5.0);
    sag();
}];

/* --------------------------------------------------------------- Fall 12 */
$szenarien[12] = ['Helligkeit der Farbrampe', function (): void {
    $css = (string)file_get_contents(__DIR__ . '/../assets/app.css');
    $neu = str_contains($css, '--z0:');
    $praefix = $neu ? 'z' : 'w';
    $leucht = [];
    for ($i = 0; $i <= 10; $i++) {
        if (!preg_match('/--' . $praefix . $i . ':\s*#([0-9a-fA-F]{6})/', $css, $t)) {
            pruefe("Farbe --$praefix$i gefunden", false, true);
            return;
        }
        $hex = $t[1];
        // WCAG-Leuchtdichte: Farbe ist Zugabe, die Helligkeit traegt die Information.
        $kanal = static function (int $v): float {
            $s = $v / 255;
            return $s <= 0.04045 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        };
        $leucht[$i] = 0.2126 * $kanal((int)hexdec(substr($hex, 0, 2)))
                    + 0.7152 * $kanal((int)hexdec(substr($hex, 2, 2)))
                    + 0.0722 * $kanal((int)hexdec(substr($hex, 4, 2)));
    }
    // Bericht immer in Zustimmungsrichtung: z = 0 ist das schwierige Ende.
    sag('| Zustimmung | Farbe | Leuchtdichte |');
    sag('|---|---|---|');
    $folge = [];
    for ($z = 0; $z <= 10; $z++) {
        $i = $neu ? $z : 10 - $z;
        preg_match('/--' . $praefix . $i . ':\s*(#[0-9a-fA-F]{6})/', $css, $t);
        sag(sprintf('| %d | `%s` | %s |', $z, $t[1], number_format($leucht[$i], 4, ',', '')));
        $folge[] = $leucht[$i];
    }
    sag();
    $steigt = true;
    for ($z = 1; $z <= 10; $z++) if ($folge[$z] <= $folge[$z - 1]) $steigt = false;
    wahr('die Helligkeit steigt über alle elf Stufen gleichmäßig mit der Zustimmung', $steigt);
    sag('Die kräftige, dunkle Seite liegt damit bei der geringen Zustimmung (Variante A):');
    sag('Die Sprache wird positiv, das Bild bleibt ehrlich. In Graustufen und bei jeder');
    sag('Farbfehlsichtigkeit bleibt die Skala lesbar.');
    sag();
}];

/* --------------------------------------------------------------- Fall 13 */
$szenarien[13] = ['Veto trifft den Spitzenreiter', function (): void {
    [$p, $l] = bau(4, ['veto' => true]);
    $v = vorschlaege($p, $l, ['A', 'B']);
    $p->uebergangBewertung('szenario');
    $pv = passivId($p);
    foreach ([[10, 7, 3], [9, 6, 4], [9, 7, 3], [9, 8, 4]] as $i => $reihe) {
        $tid = (int)$l[$i]['id'];
        setzeZ($p, $tid, $v['A'], $reihe[0]);
        setzeZ($p, $tid, $v['B'], $reihe[1]);
        setzeZ($p, $tid, $pv,     $reihe[2]);
    }
    // Die Person, die A mit 10 bewertet hat, legt trotzdem Veto ein - zulaessig.
    setzeZ($p, (int)$l[0]['id'], $v['A'], 10, true, 'Rechtlich geht das nicht.');

    $b = bericht($p, 'A hat den größten Rückhalt und bekommt ein Veto');
    pruefe('Ø Rückhalt von A bleibt erhalten', zahl($b['nach']['A']['mittel']), '9,25');
    wahr('A ist blockiert', $b['nach']['A']['gesperrt']);
    wahr('A bekommt keinen Rang', $b['nach']['A']['rang'] === null);
    pruefe('B gewinnt', $b['sieger'], 'B');
    $vetos = 0;
    foreach ($b['erg']['zeilen'] as $z) $vetos += count($z['vetos']);
    pruefe('genau ein Veto, unabhängig vom Zahlenwert erfasst', $vetos, 1);
}];

/* --------------------------------------------------------------- Fall 14 */
$szenarien[14] = ['Export', function (): void {
    [$p, $l] = bau(5);
    $v = vorschlaege($p, $l, ['A', 'B', 'C']);
    $p->uebergangBewertung('szenario');
    $pv = passivId($p);
    foreach ([[9, 6, 2, 4], [8, 7, 3, 3], [10, 5, 1, 5], [7, 6, 4, 4], [9, 8, 0, 2]] as $i => $r) {
        $tid = (int)$l[$i]['id'];
        setzeZ($p, $tid, $v['A'], $r[0]);
        setzeZ($p, $tid, $v['B'], $r[1]);
        setzeZ($p, $tid, $v['C'], $r[2]);
        setzeZ($p, $tid, $pv,     $r[3]);
    }
    $csv  = Auswertung::csv($p);
    $json = json_decode(Auswertung::json($p), true);
    $zeilenCsv = array_values(array_filter(explode("\n", trim($csv))));
    pruefe('CSV hat eine Kopfzeile und vier Datenzeilen', count($zeilenCsv), 5);
    pruefe('JSON zählt vier Vorschläge', count($json['vorschlaege']), 4);
    wahr('JSON nennt die Zahl der Wertenden', ($json['gewertet'] ?? 0) === 5);
    sag('Kopfzeile: `' . $zeilenCsv[0] . '`');
    sag('Schlüssel je Vorschlag: `' . implode('`, `', array_keys($json['vorschlaege'][0])) . '`');
    sag();
    if (isset($json['vorschlaege'][0]['widerstand_im_schnitt'])) {
        $a = null;
        foreach ($json['vorschlaege'] as $z) if ($z['titel'] === 'A') $a = $z;
        pruefe('die Brücke zur klassischen Zählung stimmt',
               zahl(10.0 - (float)$a['rueckhalt_im_schnitt']), zahl((float)$a['widerstand_im_schnitt']));
    }
}];

/* ==================================================================== Lauf */

$nur = null;
$ablage = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--ablage') $ablage = true;
    elseif (ctype_digit($arg)) $nur = (int)$arg;
}

$welt = 'unbekannt';
[$probe] = bau(1);
$welt = neueWelt($probe) ? 'Zustimmung (nach der Umstellung)' : 'Widerstand (vor der Umstellung)';

sag('# Messung der Testszenarien');
sag();
sag('Erzeugt von `tools/szenarien.php` · ' . date('Y-m-d H:i'));
sag();
sag('Gespeicherte Größe in dieser Fassung: **' . $welt . '**');
sag();
sag('Alle Zahlen im Bericht sind auf **Zustimmung** umgerechnet: `Ø Rückhalt` ist der');
sag('mittlere Zustimmungswert (groß ist gut), `Mindestwert` die geringste Zustimmung');
sag('einer einzelnen Person, `Werte bis 2` die Zahl der sehr niedrigen Bewertungen,');
sag('`KiK` die Kraft im Konsens gegenüber dem Nichtstun. Damit sind Ausgangsmessung');
sag('und Nachmessung unmittelbar vergleichbar.');
sag();

foreach ($szenarien as $nr => [$name, $lauf]) {
    if ($nur !== null && $nr !== $nur) continue;
    sag('---');
    sag();
    sag("## Fall $nr · $name");
    sag();
    $lauf();
}

sag('---');
sag();
sag(sprintf('**%d Zusicherungen bestanden, %d fehlgeschlagen.**',
    $GLOBALS['sk_gut'], $GLOBALS['sk_schlecht']));

foreach ($GLOBALS['sk_polls'] as $id) Storage::loeschen($id);

$text = implode("\n", $GLOBALS['sk_zeilen']) . "\n";
echo $text;
if ($ablage) {
    $ziel = __DIR__ . '/../Spec/53_Messung_' . date('Y-m-d') . '.md';
    file_put_contents($ziel, $text);
    fwrite(STDERR, "\nAbgelegt: $ziel\n");
}
exit($GLOBALS['sk_schlecht'] > 0 ? 1 : 0);
