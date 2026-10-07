<?php
declare(strict_types=1);
if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; }

final class Config
{
    /** @var array<string,mixed> */
    private static array $werte = [];
    private static ?string $salt = null;

    public static function laden(string $wurzel): void
    {
        $vorgaben = require $wurzel . '/config.example.php';
        $eigen = is_file($wurzel . '/config.php') ? require $wurzel . '/config.php' : [];
        self::$werte = array_merge($vorgaben, is_array($eigen) ? $eigen : []);
        self::$werte['wurzel'] = $wurzel;
        date_default_timezone_set((string)self::$werte['zeitzone']);
    }

    /** @return mixed */
    public static function get(string $name)
    {
        return self::$werte[$name] ?? null;
    }

    public static function verzeichnis(string $unter = ''): string
    {
        $d = rtrim((string)self::get('datenverzeichnis'), '/');
        return $unter === '' ? $d : $d . '/' . $unter;
    }

    /**
     * Serverschluessel fuer CSRF-Token und Zaehler. Wird bei Bedarf erzeugt und
     * im Datenverzeichnis abgelegt, damit die Konfiguration nicht beschreibbar sein muss.
     */
    public static function salt(): string
    {
        if (self::$salt !== null) return self::$salt;
        $datei = self::verzeichnis('salt.bin');
        if (is_file($datei)) {
            $s = (string)file_get_contents($datei);
            if (strlen($s) >= 32) return self::$salt = $s;
        }
        $s = random_bytes(32);
        Storage::verzeichnisSichern(self::verzeichnis());
        file_put_contents($datei, $s, LOCK_EX);
        @chmod($datei, 0600);
        return self::$salt = $s;
    }
}
