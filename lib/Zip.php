<?php
declare(strict_types=1);
if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; }

/**
 * Packt den eigenen Quelltext zum Herunterladen. Nutzt ZipArchive, wenn vorhanden,
 * sonst einen kleinen eingebauten Schreiber ohne Kompression.
 */
final class Zip
{
    /** @return array<int,array{0:string,1:string}> [absoluter Pfad, Name im Archiv] */
    public static function dateien(): array
    {
        $wurzel = rtrim((string)Config::get('wurzel'), '/');
        $aus = [];
        $ausgeschlossen = ['data', '.git', '_check_tmp', 'Spec', 'node_modules'];
        $iter = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($wurzel, FilesystemIterator::SKIP_DOTS),
                static function ($datei, $schluessel, $iterator) use ($ausgeschlossen) {
                    $name = $datei->getFilename();
                    if ($datei->isDir()) return !in_array($name, $ausgeschlossen, true);
                    if ($name === 'config.php') return false;      // enthaelt oertliche Angaben
                    if ($name === 'Impressum.md') return false;    // das ausgefuellte Impressum; die Vorlage Impressum.example.md geht mit
                    if ($name === 'check-freischalten.txt') return false;
                    if (substr($name, -6) === '.sqlite') return false;
                    if ($name === '.DS_Store') return false;
                    return true;
                }
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iter as $datei) {
            $pfad = $datei->getPathname();
            $rel = ltrim(str_replace('\\', '/', substr($pfad, strlen($wurzel))), '/');
            $aus[] = [$pfad, 'konsensieren/' . $rel];
        }
        sort($aus);
        return $aus;
    }

    public static function ausgeben(string $dateiname): void
    {
        $dateien = self::dateien();
        $inhalt = class_exists('ZipArchive') ? self::mitZipArchive($dateien) : self::eigenerZip($dateien);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $dateiname . '"');
        header('Content-Length: ' . strlen($inhalt));
        echo $inhalt;
    }

    private static function mitZipArchive(array $dateien): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sk');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($dateien as [$pfad, $name]) {
            $zip->addFile($pfad, $name);
        }
        $zip->close();
        $inhalt = (string)file_get_contents($tmp);
        @unlink($tmp);
        return $inhalt;
    }

    /** Minimaler ZIP-Schreiber, Verfahren "store". */
    private static function eigenerZip(array $dateien): string
    {
        $eintraege = '';
        $verzeichnis = '';
        $versatz = 0;
        foreach ($dateien as [$pfad, $name]) {
            $daten = (string)file_get_contents($pfad);
            $crc = crc32($daten);
            $laenge = strlen($daten);
            $zeit = self::dosZeit((int)filemtime($pfad));
            $kopf = "\x50\x4b\x03\x04" . pack('vvvVVVVvv', 20, 0, 0, $zeit[1] | ($zeit[0] << 16),
                    $crc, $laenge, $laenge, strlen($name), 0);
            $eintraege .= $kopf . $name . $daten;
            $verzeichnis .= "\x50\x4b\x01\x02" . pack('vvvvVVVVvvvvvVV', 20, 20, 0, 0,
                    $zeit[1] | ($zeit[0] << 16), $crc, $laenge, $laenge,
                    strlen($name), 0, 0, 0, 0, 32, $versatz) . $name;
            $versatz += strlen($kopf) + strlen($name) + $laenge;
        }
        $anzahl = count($dateien);
        return $eintraege . $verzeichnis . "\x50\x4b\x05\x06"
            . pack('vvvvVVv', 0, 0, $anzahl, $anzahl, strlen($verzeichnis), $versatz, 0);
    }

    /** @return array{0:int,1:int} Datum, Zeit im DOS-Format */
    private static function dosZeit(int $ts): array
    {
        $d = getdate($ts);
        if ($d['year'] < 1980) $d = getdate(315532800);
        return [
            (($d['year'] - 1980) << 9) | ($d['mon'] << 5) | $d['mday'],
            ($d['hours'] << 11) | ($d['minutes'] << 5) | ((int)($d['seconds'] / 2)),
        ];
    }
}
