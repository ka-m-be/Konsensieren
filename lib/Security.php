<?php
declare(strict_types=1);
if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; }

final class Security
{
    /** Bindet die Token an die gerade geoeffnete Abstimmung. */
    private static string $kontext = '';

    public static function kontext(string $wert): void
    {
        self::$kontext = $wert;
    }

    public static function kopfzeilen(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
             . "style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
        header('Referrer-Policy: no-referrer');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Cross-Origin-Opener-Policy: same-origin');
    }

    public static function token(string $bezug = ''): string
    {
        // Der Kontext sorgt dafuer, dass ein anderswo abgeschriebenes Token hier
        // nicht gilt. Ohne Sitzungen ist das die einfachste tragfaehige Bindung.
        return hash_hmac('sha256', 'csrf|' . $bezug . '|' . self::$kontext, Config::salt());
    }

    public static function tokenPruefen(string $bezug = ''): bool
    {
        $ist = $_POST['token'] ?? '';
        return is_string($ist) && hash_equals(self::token($bezug), $ist);
    }

    public static function tokenFeld(string $bezug = ''): string
    {
        return '<input type="hidden" name="token" value="' . Util::esc(self::token($bezug)) . '">';
    }

    /**
     * Bremse gegen massenhaftes Anlegen und gegen das Durchprobieren von Schluesseln.
     * Gezaehlt werden ausdruecklich nur Fehlversuche - eine ganze Gruppe sitzt oft
     * hinter einer einzigen Adresse, und normales Benutzen darf nie gedrosselt werden.
     * Der Zaehler steht in einer Datei, deren Name nur ein Hash aus Adresse, Salt und
     * Stunde ist; nach 24 Stunden wird er weggeraeumt.
     */
    public static function drosselung(string $zweck, int $grenze): bool
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '?');
        $stunde = (int)floor(Util::jetzt() / 3600);
        $name = substr(hash_hmac('sha256', $zweck . '|' . $ip . '|' . $stunde, Config::salt()), 0, 32);
        $dir = Config::verzeichnis('zaehler');
        Storage::verzeichnisSichern($dir);
        $datei = $dir . '/' . $name;
        $stand = is_file($datei) ? (int)file_get_contents($datei) : 0;
        $stand++;
        @file_put_contents($datei, (string)$stand, LOCK_EX);
        return $stand <= $grenze;
    }

    /**
     * Ein Fehlversuch: kurz verzoegern, mitzaehlen und nach zu vielen Versuchen
     * ganz zumachen. Bei 130 Bit Schluessellaenge ist Raten ohnehin aussichtslos;
     * das hier haelt vor allem die Protokolle sauber.
     */
    public static function fehlversuch(): bool
    {
        usleep(300000);
        return self::drosselung('fehlversuch', 30);
    }
}
