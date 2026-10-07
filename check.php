<?php
declare(strict_types=1);
/**
 * check.php - Umgebungs-Check fuer das SK-Tool (Systemisches Konsensieren)
 *
 * Einzeldatei, keine Abhaengigkeiten. Per sFTP in das Zielverzeichnis hochladen.
 *
 * Der Bericht verraet einiges ueber den Server, deshalb ist das Skript von Haus
 * aus GESPERRT. Zum Freischalten legt man neben check.php eine Datei
 *
 *     check-freischalten.txt
 *
 * an (Inhalt egal). Die Freischaltung gilt 60 Minuten ab dem Zeitpunkt, an dem
 * die Datei zuletzt geaendert wurde, und laeuft danach von selbst ab. So kann
 * check.php dauerhaft liegen bleiben - etwa damit es im Quelltextpaket
 * mitgeliefert wird - ohne von aussen etwas preiszugeben.
 *
 * Das Skript legt hoechstens ein temporaeres Verzeichnis _check_tmp/ an und
 * raeumt es selbst wieder weg.
 */

const FREISCHALTUNG = 'check-freischalten.txt';
const FREISCHALTUNG_GILT = 3600;   // Sekunden

/** @return array{0:bool,1:string} frei? und der Grund, falls nicht */
function freigeschaltet(): array
{
    $datei = __DIR__ . '/' . FREISCHALTUNG;
    if (!is_file($datei)) return [false, 'fehlt'];
    $alter = time() - (int)@filemtime($datei);
    if ($alter > FREISCHALTUNG_GILT) return [false, 'abgelaufen'];
    return [true, ''];
}

[$frei, $grund] = freigeschaltet();
if (!$frei) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex');
    $minuten = (int)(FREISCHALTUNG_GILT / 60);
    $abgelaufen = $grund === 'abgelaufen';
    ?><!doctype html>
<html lang="de"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Umgebungs-Check gesperrt</title>
<style>
 body { margin:0; padding:2.5rem 1rem; background:#f7f9fb; color:#1f2a33;
        font:16px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
 .k { max-width:36rem; margin:0 auto; background:#fff; border:1px solid #dde5ec;
      border-radius:10px; padding:1.5rem 1.75rem; }
 h1 { font-size:1.25rem; margin:0 0 .75rem; }
 code { background:#eef2f6; padding:.1em .35em; border-radius:4px; }
 .en { margin-top:1.5rem; padding-top:1.25rem; border-top:1px solid #dde5ec; color:#5d6b78; }
</style></head><body><div class="k">
<h1>Umgebungs-Check ist gesperrt</h1>
<?php if ($abgelaufen): ?>
<p>Die Freischaltung ist abgelaufen. Sie gilt <?= $minuten ?> Minuten.</p>
<?php endif; ?>
<p>Der Bericht verrät Einzelheiten über den Server. Zum Freischalten eine Datei
   namens <code><?= FREISCHALTUNG ?></code> neben <code>check.php</code> anlegen –
   der Inhalt ist egal, eine leere Datei genügt<?= $abgelaufen ? ' beziehungsweise erneut anlegen' : '' ?>.
   Dann diese Seite neu laden.</p>
<p>Die Freischaltung gilt <?= $minuten ?> Minuten und läuft danach von selbst ab.
   Am besten löscht man die Datei gleich nach dem Blick auf den Bericht.
   <code>check.php</code> selbst kann liegen bleiben.</p>
<p class="en"><strong>Environment check is locked.</strong> The report reveals
   details about the server. To unlock, create a file named
   <code><?= FREISCHALTUNG ?></code> next to <code>check.php</code> (contents do not
   matter) and reload this page. The unlock lasts <?= $minuten ?> minutes and then
   expires by itself.</p>
</div></body></html><?php
    exit;
}

// ---------------------------------------------------------------- Probe-Modus
// Wird vom Haupt-Lauf per HTTP an sich selbst aufgerufen, um PATH_INFO und die
// Wirksamkeit von .htaccess zu testen.
if (isset($_GET['probe'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "PROBE_OK\n";
    echo 'PATH_INFO=' . ($_SERVER['PATH_INFO'] ?? '') . "\n";
    exit;
}

$TMP = __DIR__ . '/_check_tmp';

// ------------------------------------------------------------------ Aufraeumen
if (isset($_GET['cleanup'])) {
    rrmdir($TMP);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . '/' . $f;
        is_dir($p) ? rrmdir($p) : @unlink($p);
    }
    @rmdir($dir);
}

// --------------------------------------------------------------- Ergebnisliste
$R = [];
function res(string $group, string $label, string $status, string $value, string $note = ''): void {
    global $R;
    $R[$group][] = ['label' => $label, 'status' => $status, 'value' => $value, 'note' => $note];
}
// status: ok | warn | fail | info

// ================================================================== 1. PHP
$pv = PHP_VERSION;
if (version_compare($pv, '8.1', '>=')) {
    res('PHP', 'PHP-Version', 'ok', $pv);
} elseif (version_compare($pv, '7.4', '>=')) {
    res('PHP', 'PHP-Version', 'warn', $pv, 'Laeuft, aber 8.1+ waere deutlich besser (Wartung, Performance).');
} else {
    res('PHP', 'PHP-Version', 'fail', $pv, 'Zu alt. Mindestens 7.4, empfohlen 8.1+.');
}
res('PHP', 'SAPI', 'info', PHP_SAPI);
res('PHP', 'Betriebssystem', 'info', PHP_OS_FAMILY . ' (' . php_uname('s') . ')');
res('PHP', '64-Bit-Integer', PHP_INT_SIZE >= 8 ? 'ok' : 'warn', (string)(PHP_INT_SIZE * 8) . ' Bit');

$exts = [
    'pdo_sqlite' => ['fail', 'Kern-Speicherweg. Ohne das nur der JSON-Fallback.'],
    'sqlite3'    => ['info', 'Nur informativ, gearbeitet wird ueber PDO.'],
    'json'       => ['fail', 'Zwingend erforderlich.'],
    'mbstring'   => ['warn', 'Fuer sauberen Umgang mit Umlauten/UTF-8. Fallback moeglich, aber unschoen.'],
    'openssl'    => ['info', 'Nicht zwingend; random_bytes() ist entscheidend.'],
    'intl'       => ['info', 'Optional, fuer Datums-/Sprachformatierung.'],
    'zlib'       => ['info', 'Optional, fuer Kompression von Exporten.'],
    'fileinfo'   => ['info', 'Optional.'],
];
foreach ($exts as $ext => [$sev, $note]) {
    $have = extension_loaded($ext);
    res('PHP-Erweiterungen', $ext, $have ? 'ok' : $sev, $have ? 'vorhanden' : 'FEHLT', $have ? '' : $note);
}

$hasRb = function_exists('random_bytes');
res('PHP-Erweiterungen', 'random_bytes()', $hasRb ? 'ok' : 'fail',
    $hasRb ? 'vorhanden' : 'FEHLT', $hasRb ? '' : 'Ohne kryptografisch sichere Zufallszahlen keine sicheren Keys.');

// -------------------------------------------------------------- 2. ini-Werte
$ini = [
    'memory_limit'        => ['warn', 'Mindestens 32M sind angenehm.'],
    'max_execution_time'  => ['info', ''],
    'post_max_size'       => ['info', ''],
    'upload_max_filesize' => ['info', ''],
    'date.timezone'       => ['warn', 'Sollte gesetzt sein, sonst sind Phasen-Deadlines mehrdeutig.'],
    'display_errors'      => ['info', 'Auf einem Produktivsystem sollte das aus sein.'],
    'open_basedir'        => ['info', 'Falls gesetzt: Datenverzeichnis muss darin liegen.'],
    'session.save_path'   => ['info', ''],
    'allow_url_fopen'     => ['info', 'Nur fuer diesen Check relevant.'],
];
foreach ($ini as $k => [$sev, $note]) {
    $v = ini_get($k);
    $empty = ($v === false || $v === '');
    $status = 'info';
    if ($k === 'date.timezone') $status = $empty ? $sev : 'ok';
    res('Konfiguration', $k, $status, $empty ? '(nicht gesetzt)' : (string)$v, $empty ? $note : '');
}
res('Konfiguration', 'Zeitzone (effektiv)', 'info', date_default_timezone_get() . ' - jetzt: ' . date('Y-m-d H:i:s'));

$disabled = trim((string)ini_get('disable_functions'));
res('Konfiguration', 'disable_functions', $disabled === '' ? 'ok' : 'warn',
    $disabled === '' ? '(keine)' : $disabled,
    $disabled === '' ? '' : 'Pruefen, ob etwas Benoetigtes dabei ist (z.B. flock ist keine Funktion, aber file_put_contents/rename schon).');

// ---------------------------------------------------------------- 3. Server
$sw = $_SERVER['SERVER_SOFTWARE'] ?? '(unbekannt)';
res('Webserver', 'Server-Software', 'info', (string)$sw);
$isApache = stripos((string)$sw, 'apache') !== false;
$isNginx  = stripos((string)$sw, 'nginx') !== false;

if (function_exists('apache_get_modules')) {
    $mods = apache_get_modules();
    $rw = in_array('mod_rewrite', $mods, true);
    res('Webserver', 'mod_rewrite', $rw ? 'ok' : 'warn', $rw ? 'aktiv' : 'nicht gefunden',
        $rw ? '' : 'Ohne Rewrite laufen die Links ueber ?v=KEY statt /v/KEY.');
    $ah = in_array('mod_authz_core', $mods, true) || in_array('mod_authz_host', $mods, true);
    res('Webserver', 'mod_authz_*', $ah ? 'ok' : 'info', $ah ? 'aktiv' : 'nicht gefunden');
} else {
    res('Webserver', 'Apache-Module', 'info', 'nicht abfragbar (kein mod_php)',
        $isNginx ? 'nginx: .htaccess wirkt NICHT. Datenverzeichnis muss anders geschuetzt werden.' : '');
}

$kennzeichen = null;
foreach (['SK_HTACCESS', 'REDIRECT_SK_HTACCESS', 'REDIRECT_REDIRECT_SK_HTACCESS'] as $k) {
    if (!empty($_SERVER[$k])) { $kennzeichen = $k; break; }
}
res('Webserver', '.htaccess der Anwendung', $kennzeichen !== null ? 'ok' : 'warn',
    $kennzeichen !== null ? 'wird ausgewertet' : 'kommt nicht an',
    $kennzeichen !== null
        ? 'Schoene Adressen ohne index.php sind moeglich.'
        : 'Entweder wurde die Datei .htaccess nicht mit hochgeladen - viele sFTP-Programme '
        . 'blenden Dateien mit fuehrendem Punkt aus - oder der Server wertet sie nicht aus. '
        . 'Die Anwendung laeuft trotzdem; ihre Adressen enthalten dann index.php.');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
      || (($_SERVER['SERVER_PORT'] ?? '') === '443');
res('Webserver', 'HTTPS', $https ? 'ok' : 'warn', $https ? 'ja' : 'nein',
    $https ? '' : 'Keys wandern im Klartext ueber die Leitung. Fuer den Produktivbetrieb noetig.');
res('Webserver', 'Dokument-Root', 'info', (string)($_SERVER['DOCUMENT_ROOT'] ?? '(unbekannt)'));
res('Webserver', 'Skript-Verzeichnis', 'info', __DIR__);
$inSubdir = trim(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
res('Webserver', 'Unterverzeichnis', 'info', $inSubdir === '' ? '(Wurzel)' : '/' . $inSubdir);

// ------------------------------------------------------- 4. Dateisystem
$writableDir = is_writable(__DIR__);
res('Dateisystem', 'Skriptverzeichnis beschreibbar', $writableDir ? 'ok' : 'fail',
    $writableDir ? 'ja' : 'nein', $writableDir ? '' : 'Das Datenverzeichnis kann nicht angelegt werden. Per sFTP Schreibrechte setzen.');

$mkTmp = is_dir($TMP) || @mkdir($TMP, 0775, true);
res('Dateisystem', 'Testverzeichnis anlegen', $mkTmp ? 'ok' : 'fail', $mkTmp ? $TMP : 'fehlgeschlagen');

if ($mkTmp) {
    $tf = $TMP . '/probe.txt';
    $wrote = @file_put_contents($tf, "hallo\n") !== false;
    res('Dateisystem', 'Datei schreiben', $wrote ? 'ok' : 'fail', $wrote ? 'ja' : 'nein');

    if ($wrote) {
        $fh = @fopen($tf, 'r+');
        $lock = $fh && @flock($fh, LOCK_EX | LOCK_NB);
        if ($lock) { flock($fh, LOCK_UN); }
        if ($fh) fclose($fh);
        res('Dateisystem', 'flock() exklusiv', $lock ? 'ok' : 'warn', $lock ? 'ja' : 'nein',
            $lock ? '' : 'Ohne funktionierendes Locking waere der JSON-Fallback unsicher (NFS?).');

        $tmp2 = $TMP . '/probe2.txt';
        $atomic = @rename($tf, $tmp2);
        res('Dateisystem', 'atomares rename()', $atomic ? 'ok' : 'warn', $atomic ? 'ja' : 'nein');
        @unlink($atomic ? $tmp2 : $tf);
    }

    $free = @disk_free_space(__DIR__);
    res('Dateisystem', 'Freier Speicher', $free !== false && $free > 20*1024*1024 ? 'ok' : 'warn',
        $free === false ? 'nicht ermittelbar' : round($free / 1048576) . ' MB');
}

// ------------------------------------------------------------- 5. SQLite
if (extension_loaded('pdo_sqlite') && $mkTmp) {
    $db = $TMP . '/probe.sqlite';
    @unlink($db);
    try {
        $pdo = new PDO('sqlite:' . $db, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $ver = $pdo->query('SELECT sqlite_version()')->fetchColumn();
        res('SQLite', 'Datenbank anlegen', 'ok', 'SQLite ' . $ver);

        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, txt TEXT)');
        $pdo->beginTransaction();
        $st = $pdo->prepare('INSERT INTO t (txt) VALUES (?)');
        $st->execute(['Grüße mit Ümläuten']);
        $pdo->commit();
        $back = $pdo->query('SELECT txt FROM t')->fetchColumn();
        $utfOk = $back === 'Grüße mit Ümläuten';
        res('SQLite', 'Transaktion + UTF-8', $utfOk ? 'ok' : 'warn', $utfOk ? 'ja' : 'Rueckgabe abweichend');

        $jm = $pdo->query('PRAGMA journal_mode=WAL')->fetchColumn();
        res('SQLite', 'WAL-Modus', $jm === 'wal' ? 'ok' : 'warn', (string)$jm,
            $jm === 'wal' ? '' : 'Ohne WAL nur "delete"-Journal. Funktioniert, blockiert aber mehr beim Parallelzugriff.');

        $pdo->exec('PRAGMA foreign_keys=ON');
        $fk = (int)$pdo->query('PRAGMA foreign_keys')->fetchColumn();
        res('SQLite', 'Foreign Keys', $fk === 1 ? 'ok' : 'warn', $fk === 1 ? 'aktivierbar' : 'nicht aktivierbar');

        $pdo->exec('CREATE TABLE j (d TEXT); INSERT INTO j VALUES (\'{"a":1}\')');
        try {
            $jv = $pdo->query("SELECT json_extract(d,'$.a') FROM j")->fetchColumn();
            res('SQLite', 'JSON1-Funktionen', 'ok', 'vorhanden (Ergebnis: ' . var_export($jv, true) . ')');
        } catch (Throwable $e) {
            res('SQLite', 'JSON1-Funktionen', 'info', 'nicht vorhanden', 'Nicht zwingend noetig.');
        }
        $pdo = null;
        @unlink($db);
        @unlink($db . '-wal'); @unlink($db . '-shm'); @unlink($db . '-journal');
    } catch (Throwable $e) {
        res('SQLite', 'Datenbank anlegen', 'fail', 'Fehler', $e->getMessage() . ' -- dann JSON-Fallback.');
    }
} else {
    res('SQLite', 'Test', 'warn', 'uebersprungen', 'pdo_sqlite fehlt oder Verzeichnis nicht beschreibbar. Speicherung dann als JSON-Dateien.');
}

// --------------------------------------------- 6. Selbst-Tests per HTTP
$scheme  = $https ? 'https' : 'http';
$host    = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$scriptU = (string)($_SERVER['SCRIPT_NAME'] ?? '/check.php');
$baseUrl = $scheme . '://' . $host . rtrim(dirname($scriptU), '/\\');
$selfUrl = $scheme . '://' . $host . $scriptU;

function httpGet(string $url): array {
    // [erfolgreich?, http-status, body]
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4,
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false) return [false, 0, ''];
        return [true, $code, (string)$body];
    }
    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true],
                                      'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $body = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int)$m[1];
        }
        if ($body === false) return [false, 0, ''];
        return [true, $code, (string)$body];
    }
    return [false, 0, ''];
}

$selfTestPossible = true;
if (PHP_SAPI === 'cli-server' && (int)(getenv('PHP_CLI_SERVER_WORKERS') ?: 1) < 2) {
    $selfTestPossible = false;
    res('Selbsttest', 'HTTP-Selbstaufruf', 'info', 'nicht moeglich',
        'Der eingebaute PHP-Server ist einthreadig und wuerde sich selbst blockieren. Starte ihn mit PHP_CLI_SERVER_WORKERS=4 php -S ... oder pruefe die beiden Links unten von Hand.');
}

// 6a) PATH_INFO
$pathInfoUrl = $selfUrl . '/pfadtest?probe=1';
if ($selfTestPossible) {
    [$ok, $code, $body] = httpGet($pathInfoUrl);
    if (!$ok) {
        res('Selbsttest', 'PATH_INFO (/skript.php/pfad)', 'info', 'kein Selbstaufruf moeglich', 'Bitte den Link unten von Hand pruefen.');
    } elseif ($code === 200 && strpos($body, 'PATH_INFO=/pfadtest') !== false) {
        res('Selbsttest', 'PATH_INFO (/skript.php/pfad)', 'ok', 'funktioniert',
            'Schoene Links der Form /index.php/v/KEY sind auch ohne mod_rewrite moeglich.');
    } else {
        res('Selbsttest', 'PATH_INFO (/skript.php/pfad)', 'warn', 'HTTP ' . $code,
            'Dann Keys als Query-Parameter (?v=KEY) oder mod_rewrite verwenden.');
    }
}

// 6b) .htaccess-Schutz
$htKept = false;
if ($mkTmp) {
    $secret = bin2hex(random_bytes(8));
    @file_put_contents($TMP . '/geheim.txt', "GEHEIM-$secret\n");
    @file_put_contents($TMP . '/.htaccess',
        "# Test: Verzeichnis fuer Web-Zugriff sperren\n" .
        "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n" .
        "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
    $secretUrl = $baseUrl . '/_check_tmp/geheim.txt';
    if ($selfTestPossible) {
        [$ok, $code, $body] = httpGet($secretUrl);
        if (!$ok) {
            res('Selbsttest', '.htaccess sperrt Datenverzeichnis', 'info', 'kein Selbstaufruf moeglich', 'Bitte den Link unten von Hand pruefen.');
            $htKept = true;
        } elseif (strpos($body, 'GEHEIM-' . $secret) !== false) {
            $htaccessIrrelevant = (PHP_SAPI === 'cli-server') || $isNginx;
            res('Selbsttest', '.htaccess sperrt Datenverzeichnis',
                $htaccessIrrelevant ? 'warn' : 'fail',
                'NEIN, Datei war lesbar (HTTP ' . $code . ')',
                $htaccessIrrelevant
                    ? 'Erwartbar: dieser Server wertet .htaccess grundsaetzlich nicht aus. Auf dem echten Zielserver noch einmal pruefen.'
                    : 'Wichtig! Die Abstimmungsdaten muessen anders geschuetzt werden: Datenverzeichnis ausserhalb des Webroots, oder Dateinamen mit Zufalls-Suffix, oder Server-Konfiguration anpassen.');
        } else {
            res('Selbsttest', '.htaccess sperrt Datenverzeichnis', 'ok', 'ja, gesperrt (HTTP ' . $code . ')');
        }
    } else {
        $htKept = true;
    }
}

// ------------------------------------------------------------ Gesamturteil
$counts = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];
foreach ($R as $grp) foreach ($grp as $r) $counts[$r['status']]++;

$verdict = $counts['fail'] > 0
    ? ['fail', 'Es gibt blockierende Punkte -- siehe die rot markierten Zeilen.']
    : ($counts['warn'] > 0
        ? ['warn', 'Grundsaetzlich geeignet. Die gelben Punkte solltest du dir ansehen.']
        : ['ok', 'Alles in Ordnung. Die Umgebung ist geeignet.']);

// Aufraeumen, wenn nichts mehr gebraucht wird
if ($mkTmp && !$htKept) { rrmdir($TMP); }

$icon = ['ok' => '&#10003;', 'warn' => '!', 'fail' => '&#10007;', 'info' => 'i'];
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Umgebungs-Check &ndash; SK-Tool</title>
<style>
  :root {
    --bg: #f7f9fb; --card: #fff; --ink: #1f2a33; --muted: #5d6b78;
    --line: #dde5ec; --ok: #1c7a4f; --okbg: #e8f6ee;
    --warn: #8a5a00; --warnbg: #fdf3e0; --fail: #a3252b; --failbg: #fbeaea;
    --info: #35566e; --infobg: #eef3f7;
  }
  * { box-sizing: border-box; }
  body { margin: 0; padding: 2rem 1rem 4rem; background: var(--bg); color: var(--ink);
         font: 16px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  .wrap { max-width: 62rem; margin: 0 auto; }
  h1 { font-size: 1.6rem; margin: 0 0 .25rem; }
  .sub { color: var(--muted); margin: 0 0 1.5rem; }
  .verdict { padding: 1rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem;
             border: 1px solid var(--line); font-weight: 600; }
  .verdict.ok { background: var(--okbg); border-color: #b6e0c8; color: var(--ok); }
  .verdict.warn { background: var(--warnbg); border-color: #ecd6a8; color: var(--warn); }
  .verdict.fail { background: var(--failbg); border-color: #edbcbc; color: var(--fail); }
  .tally { font-weight: 400; color: var(--muted); display: block; margin-top: .35rem; font-size: .9rem; }
  section { background: var(--card); border: 1px solid var(--line); border-radius: 10px;
            margin-bottom: 1.1rem; overflow: hidden; }
  h2 { font-size: 1rem; margin: 0; padding: .7rem 1rem; background: #f2f6f9;
       border-bottom: 1px solid var(--line); letter-spacing: .02em; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: .55rem 1rem; border-top: 1px solid #eef2f6; vertical-align: top; }
  tr:first-child td { border-top: 0; }
  td.s { width: 1.9rem; text-align: center; font-weight: 700; padding-right: 0; }
  td.l { width: 34%; color: var(--muted); }
  td.v { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .88rem;
         word-break: break-word; }
  .note { display: block; font-family: system-ui, sans-serif; font-size: .85rem;
          color: var(--muted); margin-top: .3rem; }
  .ok  td.s { color: var(--ok); }   .warn td.s { color: var(--warn); }
  .fail td.s { color: var(--fail); } .info td.s { color: var(--info); }
  .fail td.v { color: var(--fail); font-weight: 600; }
  .warn td.v { color: var(--warn); }
  .manual { background: var(--infobg); border: 1px solid #cfdde8; border-radius: 10px;
            padding: 1rem 1.25rem; margin-bottom: 1.1rem; font-size: .95rem; }
  .manual ul { margin: .5rem 0 0; padding-left: 1.2rem; }
  .manual a { color: #14608f; }
  footer { margin-top: 2rem; color: var(--muted); font-size: .9rem; }
  code { background: #eef2f6; padding: .1em .35em; border-radius: 4px; font-size: .9em; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Umgebungs-Check</h1>
  <p class="sub">Tool f&uuml;r systemisches Konsensieren &middot; <?= h(date('d.m.Y H:i')) ?></p>

  <div class="verdict <?= $verdict[0] ?>">
    <?= h($verdict[1]) ?>
    <span class="tally"><?= $counts['ok'] ?> ok &middot; <?= $counts['warn'] ?> Hinweise &middot;
      <?= $counts['fail'] ?> Probleme &middot; <?= $counts['info'] ?> Infos</span>
  </div>

<?php if ($htKept): ?>
  <div class="manual">
    <strong>Zwei Dinge bitte von Hand pr&uuml;fen</strong> (automatischer Selbstaufruf war nicht m&ouml;glich):
    <ul>
      <li><a href="<?= h($selfUrl) ?>/pfadtest?probe=1" target="_blank">PATH_INFO-Test &ouml;ffnen</a> &ndash;
          gut ist, wenn dort <code>PATH_INFO=/pfadtest</code> steht.</li>
      <li><a href="<?= h($baseUrl) ?>/_check_tmp/geheim.txt" target="_blank">Gesch&uuml;tzte Testdatei &ouml;ffnen</a> &ndash;
          gut ist <em>Forbidden / 403</em>. Wenn du dort Text mit &bdquo;GEHEIM-&ldquo; siehst,
          wirkt <code>.htaccess</code> nicht und die Daten brauchen einen anderen Schutz.</li>
    </ul>
    <p style="margin:.7rem 0 0"><a href="?cleanup=1">Testverzeichnis jetzt l&ouml;schen</a></p>
  </div>
<?php endif; ?>

<?php foreach ($R as $group => $rows): ?>
  <section>
    <h2><?= h($group) ?></h2>
    <table>
      <?php foreach ($rows as $r): ?>
      <tr class="<?= $r['status'] ?>">
        <td class="s"><?= $icon[$r['status']] ?></td>
        <td class="l"><?= h($r['label']) ?></td>
        <td class="v"><?= h($r['value']) ?>
          <?php if ($r['note'] !== ''): ?><span class="note"><?= h($r['note']) ?></span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </section>
<?php endforeach; ?>

  <footer>
    <p><strong>Bitte diese Datei nach dem Test wieder l&ouml;schen.</strong>
       Sie verr&auml;t Details &uuml;ber den Server.</p>
    <p>Kopiere das Ergebnis (oder einen Screenshot) zur&uuml;ck in die Diskussion &ndash;
       daraus ergibt sich, ob SQLite oder der JSON-Fallback verwendet wird.</p>
  </footer>
</div>
</body>
</html>
