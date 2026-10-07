<?php
declare(strict_types=1);
if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; }

/**
 * Eine SQLite-Datei je Abstimmung. Kein zentraler Index: die pollid im Schluessel
 * zeigt direkt auf die Datei.
 */
final class Storage
{
    public static function verzeichnisSichern(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        // Doppelter Boden, falls das Datenverzeichnis doch im Webroot liegt.
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess',
                "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
              . "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
        }
        if (!is_file($dir . '/index.html')) {
            @file_put_contents($dir . '/index.html', '');
        }
    }

    public static function pfad(string $pollId): string
    {
        return Config::verzeichnis('polls') . '/' . $pollId . '.sqlite';
    }

    public static function existiert(string $pollId): bool
    {
        return is_file(self::pfad($pollId));
    }

    /** @return string[] */
    public static function alleIds(): array
    {
        $dir = Config::verzeichnis('polls');
        if (!is_dir($dir)) return [];
        $ids = [];
        foreach (scandir($dir) ?: [] as $f) {
            if (preg_match('/^([0-9A-Z]{10})\.sqlite$/', $f, $m)) $ids[] = $m[1];
        }
        return $ids;
    }

    public static function oeffnen(string $pollId, bool $neu = false): ?PDO
    {
        $pfad = self::pfad($pollId);
        if (!$neu && !is_file($pfad)) return null;
        if ($neu) self::verzeichnisSichern(dirname($pfad));

        $pdo = new PDO('sqlite:' . $pfad, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA busy_timeout=5000');
        $pdo->exec('PRAGMA foreign_keys=ON');
        if ($neu) {
            @chmod($pfad, 0660);
            self::schema($pdo);
        } else {
            self::nachziehen($pdo);
        }
        return $pdo;
    }

    public static function loeschen(string $pollId): void
    {
        foreach (['', '-wal', '-shm', '-journal'] as $suffix) {
            @unlink(self::pfad($pollId) . $suffix);
        }
    }

    /**
     * Aktualisiert wird durch Ueberschreiben der Dateien - Wartungsskripte gibt es
     * nicht. Deshalb ergaenzt jede Abstimmung beim Oeffnen fehlende Spalten selbst.
     * @var array<string,string> Spalte => Definition
     */
    private const NACHTRAEGE = [
        // skala_version bewusst mit Vorgabe 0: Eine Datei, die diese Spalte noch
        // nicht hatte, stammt aus der Zeit der Widerstandsskala und muss gedreht
        // werden. Neu angelegte Dateien starten mit 1 (siehe schema()).
        'poll' => ['quorum_prozent' => 'INTEGER NOT NULL DEFAULT 50',
                   'skala_version'  => 'INTEGER NOT NULL DEFAULT 0',
                   'ist_beispiel'   => 'INTEGER NOT NULL DEFAULT 0',
                   'beispiel_admin_geheim' => "TEXT NOT NULL DEFAULT ''"],
    ];

    private static function nachziehen(PDO $pdo): void
    {
        // Gemerkt wird an der Verbindung selbst, nicht an spl_object_id(): Diese
        // Nummer wird nach dem Freigeben eines Objekts wiederverwendet, und dann
        // haette eine spaeter geoeffnete Abstimmung das Nachziehen uebersprungen -
        // fuer die Datenwanderung unten waere das verheerend.
        static $geprueft = null;
        if ($geprueft === null) $geprueft = new WeakMap();
        if (isset($geprueft[$pdo])) return;
        $geprueft[$pdo] = true;
        try {
            foreach (self::NACHTRAEGE as $tabelle => $spalten) {
                $da = [];
                foreach ($pdo->query("PRAGMA table_info($tabelle)")->fetchAll() as $spalte) {
                    $da[(string)$spalte['name']] = true;
                }
                foreach ($spalten as $name => $definition) {
                    if (!isset($da[$name])) {
                        $pdo->exec("ALTER TABLE $tabelle ADD COLUMN $name $definition");
                    }
                }
            }
        } catch (Throwable $e) {
            // Eine nicht nachziehbare Datei ist kein Grund, den Aufruf abzubrechen.
        }
        self::skalaWandern($pdo);
    }

    /**
     * Bis Fassung 0.1.3 stand in bewertung.wert der *Widerstand*, seit 0.2.0 die
     * *Zustimmung*. Beim ersten Oeffnen einer alten Datei wird jeder Wert gedreht.
     *
     * Anders als das Nachziehen von Spalten darf das hier nicht stillschweigend
     * scheitern: Ein halb oder gar nicht gedrehter Bestand saehe plausibel aus und
     * waere falsch - eine 2 hiesse dann "grosse Zustimmung" statt "geht kaum".
     * Deshalb eine Transaktion und im Fehlerfall lieber ein Abbruch als Zahlen,
     * die das Gegenteil dessen bedeuten, was danebensteht.
     */
    private static function skalaWandern(PDO $pdo): void
    {
        try {
            $version = $pdo->query('SELECT MIN(skala_version) FROM poll')->fetchColumn();
        } catch (Throwable $e) {
            return;   // Spalte liess sich nicht anlegen - dann auch nicht wandern.
        }
        if ($version === false || (int)$version >= 1) return;

        $pdo->beginTransaction();
        try {
            $pdo->exec('UPDATE bewertung SET wert = 10 - wert WHERE wert IS NOT NULL');
            $pdo->exec('UPDATE poll SET skala_version = 1');
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function schema(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE poll (
    id                TEXT PRIMARY KEY,
    titel             TEXT NOT NULL,
    beschreibung      TEXT NOT NULL DEFAULT '',
    phase             TEXT NOT NULL DEFAULT 'vorschlag',
    ende_vorschlag    INTEGER NOT NULL,
    ende_bewertung    INTEGER NOT NULL,
    loeschdatum       INTEGER NOT NULL,
    angelegt          INTEGER NOT NULL,
    admin_hash        TEXT NOT NULL,
    einladung_geheim  TEXT NOT NULL,
    ergebnis_hash     TEXT,
    ergebnis_geheim   TEXT,
    -- Der persoenliche Schluessel der anlegenden Person, im Klartext, damit die
    -- Adminseite dauerhaft auf die eigene Teilnehmeransicht verweisen kann. Wer die
    -- Datei lesen kann, sieht ohnehin alle Bewertungen; ein zusaetzliches Risiko
    -- entsteht dadurch nicht.
    admin_nutzer_geheim TEXT NOT NULL DEFAULT '',
    opt_passiv        INTEGER NOT NULL DEFAULT 1,
    passiv_text       TEXT NOT NULL DEFAULT '',
    opt_veto          INTEGER NOT NULL DEFAULT 0,
    schwelle_prozent  INTEGER NOT NULL DEFAULT 20,
    schwelle_basis    INTEGER,
    quorum_prozent    INTEGER NOT NULL DEFAULT 50,
    skala_version     INTEGER NOT NULL DEFAULT 1,
    ist_beispiel      INTEGER NOT NULL DEFAULT 0,
    -- Nur bei Beispiel-Abstimmungen gefuellt: siehe Beispiel::anlegen().
    beispiel_admin_geheim TEXT NOT NULL DEFAULT '',
    sicht_bewertungen INTEGER NOT NULL DEFAULT 0,
    sicht_ergebnis    INTEGER NOT NULL DEFAULT 0,
    zeige_namen       INTEGER NOT NULL DEFAULT 0,
    nur_vollstaendig  INTEGER NOT NULL DEFAULT 0,
    reihenfolge       TEXT NOT NULL DEFAULT 'neu',
    ergebnis_vorab    INTEGER NOT NULL DEFAULT 0,
    sprache           TEXT NOT NULL DEFAULT 'de'
);

CREATE TABLE teilnehmer (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    name      TEXT NOT NULL,
    key_hash  TEXT NOT NULL UNIQUE,
    ist_admin INTEGER NOT NULL DEFAULT 0,
    angelegt  INTEGER NOT NULL,
    gesehen   INTEGER NOT NULL
);

CREATE TABLE vorschlag (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    autor_id       INTEGER REFERENCES teilnehmer(id) ON DELETE SET NULL,
    autor_name     TEXT NOT NULL DEFAULT '',
    titel          TEXT NOT NULL,
    text           TEXT NOT NULL DEFAULT '',
    status         TEXT NOT NULL DEFAULT 'aktiv',
    ziel_id        INTEGER REFERENCES vorschlag(id) ON DELETE SET NULL,
    aufnahme       TEXT NOT NULL DEFAULT 'auto',
    redaktionsnotiz TEXT NOT NULL DEFAULT '',
    ist_passiv     INTEGER NOT NULL DEFAULT 0,
    angelegt       INTEGER NOT NULL,
    geaendert      INTEGER,
    mischidx       INTEGER
);

CREATE TABLE bezug (
    kind_id   INTEGER NOT NULL REFERENCES vorschlag(id) ON DELETE CASCADE,
    eltern_id INTEGER NOT NULL REFERENCES vorschlag(id) ON DELETE CASCADE,
    primaer   INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (kind_id, eltern_id)
);

CREATE TABLE unterstuetzung (
    vorschlag_id  INTEGER NOT NULL REFERENCES vorschlag(id) ON DELETE CASCADE,
    teilnehmer_id INTEGER NOT NULL REFERENCES teilnehmer(id) ON DELETE CASCADE,
    angelegt      INTEGER NOT NULL,
    PRIMARY KEY (vorschlag_id, teilnehmer_id)
);

CREATE TABLE kommentar (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    vorschlag_id  INTEGER NOT NULL REFERENCES vorschlag(id) ON DELETE CASCADE,
    teilnehmer_id INTEGER REFERENCES teilnehmer(id) ON DELETE SET NULL,
    autor_name    TEXT NOT NULL DEFAULT '',
    text          TEXT NOT NULL,
    angelegt      INTEGER NOT NULL
);

CREATE TABLE bewertung (
    vorschlag_id  INTEGER NOT NULL REFERENCES vorschlag(id) ON DELETE CASCADE,
    teilnehmer_id INTEGER NOT NULL REFERENCES teilnehmer(id) ON DELETE CASCADE,
    wert          INTEGER,
    veto          INTEGER NOT NULL DEFAULT 0,
    veto_grund    TEXT NOT NULL DEFAULT '',
    geaendert     INTEGER NOT NULL,
    PRIMARY KEY (vorschlag_id, teilnehmer_id)
);

CREATE TABLE protokoll (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    zeit     INTEGER NOT NULL,
    art      TEXT NOT NULL,
    details  TEXT NOT NULL DEFAULT ''
);

CREATE INDEX idx_bewertung_v ON bewertung(vorschlag_id);
CREATE INDEX idx_kommentar_v ON kommentar(vorschlag_id);
SQL);
    }
}
