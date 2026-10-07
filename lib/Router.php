<?php
declare(strict_types=1);
if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; }

final class Router
{
    /** @return string[] Pfadsegmente */
    public static function segmente(): array
    {
        $pfad = (string)($_SERVER['PATH_INFO'] ?? '');
        if ($pfad === '' && isset($_GET['p'])) $pfad = '/' . (string)$_GET['p'];
        $pfad = trim($pfad, '/');
        if ($pfad === '') return [];
        return array_values(array_filter(explode('/', $pfad), static fn($s) => $s !== ''));
    }

    /** Baut eine Adresse innerhalb der Installation. */
    public static function url(string $pfad = '', array $parameter = []): string
    {
        $pfad = ltrim($pfad, '/');
        $basis = Util::basis();
        // Mit mod_rewrite geht es ohne index.php; ohne Rewrite braucht es den Umweg.
        $url = self::rewriteMoeglich()
            ? $basis . '/' . $pfad
            : $basis . '/index.php' . ($pfad === '' ? '' : '/' . $pfad);
        if ($parameter) $url .= '?' . http_build_query($parameter);
        return $url;
    }

    /**
     * Adressen ohne index.php sind huebscher, aber sie funktionieren nur, wenn
     * die mitgelieferte .htaccess auch wirklich ausgewertet wird. Ein Link mit
     * Schluessel wird weitergegeben und kopiert - ein kaputter waere teuer.
     * Deshalb wird nicht geraten, sondern nachgesehen: Die .htaccess setzt ein
     * Kennzeichen, und nur wenn das ankommt, gibt es schoene Adressen.
     */
    private static function rewriteMoeglich(): bool
    {
        static $ja = null;
        if ($ja !== null) return $ja;

        $wunsch = Config::get('schoene_links');
        if ($wunsch === true || $wunsch === false) return $ja = $wunsch;

        // Wurde dieser Aufruf selbst umgeschrieben? Dann steht es fest.
        if (self::umgebung('SK_REWRITE') !== null) return $ja = true;

        // Kommt das Kennzeichen aus der .htaccess nicht an, wird sie nicht
        // ausgewertet - oder sie wurde gar nicht erst hochgeladen. Dateien, die
        // mit einem Punkt beginnen, blenden viele sFTP-Programme aus.
        if (self::umgebung('SK_HTACCESS') === null) return $ja = false;

        if (function_exists('apache_get_modules')) {
            return $ja = in_array('mod_rewrite', apache_get_modules(), true);
        }
        return $ja = true;
    }

    /** Serverangabe, die je nach Umschreibung mit REDIRECT_ davor ankommt. */
    private static function umgebung(string $name): ?string
    {
        foreach ([$name, 'REDIRECT_' . $name, 'REDIRECT_REDIRECT_' . $name] as $schluessel) {
            if (isset($_SERVER[$schluessel]) && $_SERVER[$schluessel] !== '') {
                return (string)$_SERVER[$schluessel];
            }
        }
        $wert = getenv($name);
        return ($wert === false || $wert === '') ? null : $wert;
    }
}
