<?php
declare(strict_types=1);
if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; }

/** Kleinkram, den sonst jede Datei doppelt haette. */
final class Util
{
    public static function esc(?string $s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Mehrzeiliger Nutzertext: escapen, Absaetze erhalten, sonst nichts. */
    public static function absaetze(?string $s): string
    {
        $s = trim((string)$s);
        if ($s === '') return '';
        $bloecke = preg_split('/\n{2,}/', str_replace("\r\n", "\n", $s)) ?: [];
        $out = '';
        foreach ($bloecke as $b) {
            $out .= '<p>' . nl2br(self::esc(trim($b)), false) . '</p>';
        }
        return $out;
    }

    public static function jetzt(): int
    {
        return time();
    }

    /** Zeitpunkt in der konfigurierten Zeitzone. */
    public static function zeit(?int $ts, string $format = 'd.m.Y, H:i'): string
    {
        if ($ts === null) return '–';
        $dt = new DateTimeImmutable('@' . $ts);
        return $dt->setTimezone(new DateTimeZone(Config::get('zeitzone')))->format($format);
    }

    /** Datum aus einem <input type="date"> plus Uhrzeit, in der Anzeigezeitzone. */
    public static function datumEinlesen(string $datum, string $uhrzeit = '23:59'): ?int
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) return null;
        try {
            $dt = new DateTimeImmutable($datum . ' ' . $uhrzeit, new DateTimeZone(Config::get('zeitzone')));
        } catch (Throwable $e) {
            return null;
        }
        return $dt->getTimestamp();
    }

    public static function datumFeld(?int $ts): string
    {
        if ($ts === null) return '';
        return (new DateTimeImmutable('@' . $ts))
            ->setTimezone(new DateTimeZone(Config::get('zeitzone')))->format('Y-m-d');
    }

    /** "noch 3 Tage", "noch 5 Stunden", "abgelaufen" */
    public static function restzeit(?int $ts): string
    {
        if ($ts === null) return '';
        $d = $ts - self::jetzt();
        if ($d <= 0) return I18n::t('zeit.abgelaufen');
        if ($d < 3600) return I18n::t('zeit.noch_minuten', ['n' => (int)ceil($d / 60)]);
        if ($d < 86400) return I18n::t('zeit.noch_stunden', ['n' => (int)ceil($d / 3600)]);
        return I18n::t('zeit.noch_tage', ['n' => (int)ceil($d / 86400)]);
    }

    public static function kuerzen(string $s, int $max): string
    {
        $s = trim($s);
        if (function_exists('mb_substr')) {
            return mb_strlen($s, 'UTF-8') > $max ? mb_substr($s, 0, $max, 'UTF-8') : $s;
        }
        return substr($s, 0, $max);
    }

    public static function post(string $name, string $vorgabe = ''): string
    {
        $v = $_POST[$name] ?? $vorgabe;
        return is_string($v) ? trim($v) : $vorgabe;
    }

    public static function postInt(string $name, int $vorgabe = 0): int
    {
        $v = $_POST[$name] ?? null;
        return is_numeric($v) ? (int)$v : $vorgabe;
    }

    public static function postAn(string $name): bool
    {
        return !empty($_POST[$name]);
    }

    public static function get(string $name, string $vorgabe = ''): string
    {
        $v = $_GET[$name] ?? $vorgabe;
        return is_string($v) ? trim($v) : $vorgabe;
    }

    /**
     * Sprungmarke aus dem abgeschickten Formular. Damit landet man nach dem
     * Speichern wieder an der Stelle, an der man gerade war, statt oben auf der
     * Seite. Ohne JavaScript ist das der einzige verlaessliche Weg.
     */
    public static function anker(): string
    {
        $roh = self::post('anker');
        return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $roh) ? '#' . $roh : '';
    }

    public static function weiterleiten(string $url): void
    {
        header('Location: ' . $url, true, 303);
        exit;
    }

    /**
     * Adresse einer mitgelieferten Datei mit Versionsstempel.
     * Ohne den holt sich der Browser nach einer Aktualisierung womoeglich noch
     * lange das alte Stylesheet - manche Server senden fuer statische Dateien
     * weder Last-Modified noch ETag, dann wird gar nicht erst nachgefragt.
     */
    public static function datei(string $pfad): string
    {
        $voll = rtrim((string)Config::get('wurzel'), '/') . '/' . ltrim($pfad, '/');
        $stand = @filemtime($voll);
        return self::basis() . '/' . ltrim($pfad, '/') . ($stand ? '?v=' . $stand : '');
    }

    /** Basis-URL der Installation, ohne Schraegstrich am Ende. */
    public static function basis(): string
    {
        static $basis = null;
        if ($basis !== null) return $basis;
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $dir  = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
        return $basis = ($https ? 'https' : 'http') . '://' . $host . $dir;
    }

    /**
     * Profi-Modus (Spezifikation 20): Wer das Werkzeug kennt, will auf der
     * Startseite und in der Verwaltung keine Erklaerkaesten, keine Erlaeuterungen
     * unter den Feldern, kein Beispiel und keine Karte zur App mehr sehen. Die
     * Wahl haengt an der Person, nicht an einer Abstimmung, denn sie gilt schon
     * beim Anlegen - deshalb ein Cookie wie bei der Sprache, und wie dort ohne
     * JavaScript ueber ?profi=an und ?profi=aus. Aus heisst: kein Cookie.
     */
    public static function profi(): bool
    {
        return ($_COOKIE['profi'] ?? '') === '1';
    }

    public static function profiWaehlen(?string $wunsch): void
    {
        if ($wunsch !== 'an' && $wunsch !== 'aus') return;
        $an = $wunsch === 'an';
        @setcookie('profi', $an ? '1' : '', [
            'expires' => $an ? time() + 31536000 : time() - 86400, 'path' => '/', 'samesite' => 'Lax',
            'httponly' => true, 'secure' => !empty($_SERVER['HTTPS']),
        ]);
        // Schon fuer diese Seite, nicht erst ab der naechsten.
        if ($an) $_COOKIE['profi'] = '1'; else unset($_COOKIE['profi']);
    }

    /**
     * Adresse der App fuer den Sitzungsraum, die als statisches Verzeichnis neben
     * dem Werkzeug liegt (Spezifikation 19). null, wenn sie fehlt: Wer die App
     * nicht anbieten will, laesst das Verzeichnis weg, und die Verweise gehen mit.
     */
    public static function raum(string $pfad = ''): ?string
    {
        $wurzel = rtrim((string)Config::get('wurzel'), '/');
        if (!is_file($wurzel . '/Konsens-App/index.html')) return null;
        return self::basis() . '/Konsens-App/' . ltrim($pfad, '/');
    }
}
