<?php
declare(strict_types=1);
if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; }

/**
 * Es gibt keinen Cron. Also raeumt jeder Seitenaufruf ein wenig mit auf,
 * hoechstens aber alle paar Stunden einmal.
 */
final class Housekeeping
{
    private const ABSTAND = 21600; // 6 Stunden

    public static function vielleicht(): void
    {
        $stempel = Config::verzeichnis('aufraeumen.stamp');
        $letzte = is_file($stempel) ? (int)@filemtime($stempel) : 0;
        if (Util::jetzt() - $letzte < self::ABSTAND) return;
        Storage::verzeichnisSichern(Config::verzeichnis());
        @touch($stempel);
        self::jetzt();
    }

    /** @return array{geloescht:int,geprueft:int} */
    public static function jetzt(): array
    {
        $geloescht = 0;
        $ids = Storage::alleIds();
        foreach ($ids as $id) {
            try {
                $p = Poll::laden($id);
                if ($p === null) continue;
                if ($p->abgelaufen()) {
                    unset($p);
                    Storage::loeschen($id);
                    $geloescht++;
                }
            } catch (Throwable $e) {
                // Eine kaputte Datei darf den Rest nicht aufhalten.
            }
        }
        self::zaehlerAufraeumen();
        return ['geloescht' => $geloescht, 'geprueft' => count($ids)];
    }

    private static function zaehlerAufraeumen(): void
    {
        $dir = Config::verzeichnis('zaehler');
        if (!is_dir($dir)) return;
        $grenze = Util::jetzt() - 86400;
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..' || $f === '.htaccess' || $f === 'index.html') continue;
            $pfad = $dir . '/' . $f;
            if (@filemtime($pfad) < $grenze) @unlink($pfad);
        }
    }
}
