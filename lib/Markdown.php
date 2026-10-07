<?php
declare(strict_types=1);
if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; }

/**
 * Markdown fuer die eigenen Texte im Hauptverzeichnis: Impressum.md und
 * Artikel.md (Spezifikation 20). Bewusst nur der Teil der Sprache, den diese
 * Dateien brauchen - Ueberschriften, Absaetze, Listen, Tabellen, Zitate, Code,
 * fett, kursiv, Links. Eine fremde Bibliothek kommt nicht ins Haus, und jede
 * Regel mehr waere eine Regel, die niemand prueft.
 *
 * Die App hat denselben Renderer in Konsens-App/markdown.js. tools/test.php und
 * Konsens-App/tools/test.js schicken dieselben Faelle aus
 * Konsens-App/tools/markdown-faelle.json durch beide: Was hier ein Zeichen anders
 * ausgibt als dort, faellt auf.
 *
 * Alles wird zuerst escaped. Die Dateien sind zwar eigene, aber wer eine
 * Installation betreibt, soll das Impressum schreiben koennen, ohne an HTML zu
 * denken; rohes HTML kommt deshalb nie durch. Links fuehren nur zu http, https,
 * mailto oder einer relativen Adresse - alles andere bleibt Text.
 *
 * Nicht dabei, mit Absicht: verschachtelte Listen, Unterstriche als Auszeichnung
 * (sie stehen in Adressen), harte Zeilenumbrueche, Bilder, Fussnoten.
 */
final class Markdown
{
    public static function html(string $md): string
    {
        $zeilen = explode("\n", str_replace("\r\n", "\n", $md));
        return implode("\n", self::bloecke($zeilen));
    }

    /** Die erste Ueberschrift als reiner Text, fuer den Seitentitel; leer ohne. */
    public static function titel(string $md): string
    {
        if (preg_match('/^#[ \t]+(.+?)[ \t]*$/m', $md, $t)) {
            return trim((string)preg_replace('/[*`]/', '', $t[1]));
        }
        return '';
    }

    private static function esc(string $s): string
    {
        return Util::esc($s);
    }

    /** trim() nur um ASCII-Leerraum, damit PHP und JavaScript dasselbe tun. */
    private static function putz(string $s): string
    {
        return trim($s, " \t\r\n");
    }

    /**
     * @param string[] $zeilen
     * @return string[] fertige Bloecke
     */
    private static function bloecke(array $zeilen): array
    {
        $aus = [];
        $n = count($zeilen);
        $i = 0;
        while ($i < $n) {
            $z = $zeilen[$i];
            if (self::putz($z) === '') { $i++; continue; }

            if (preg_match('/^```/', $z)) {
                $code = [];
                $i++;
                while ($i < $n && !preg_match('/^```/', $zeilen[$i])) $code[] = $zeilen[$i++];
                $i++;
                $aus[] = '<pre><code>' . self::esc(implode("\n", $code)) . '</code></pre>';
                continue;
            }
            if (preg_match('/^(#{1,6})[ \t]+(.*?)[ \t]*#*[ \t]*$/', $z, $t)) {
                $stufe = strlen($t[1]);
                $aus[] = '<h' . $stufe . '>' . self::inline($t[2]) . '</h' . $stufe . '>';
                $i++;
                continue;
            }
            if (preg_match('/^ {0,3}([-*_])( *\1){2,} *$/', $z)) {
                $aus[] = '<hr>';
                $i++;
                continue;
            }
            if (preg_match('/^ {0,3}>/', $z)) {
                $innen = [];
                while ($i < $n && preg_match('/^ {0,3}> ?(.*)$/', $zeilen[$i], $t)) { $innen[] = $t[1]; $i++; }
                $aus[] = "<blockquote>\n" . implode("\n", self::bloecke($innen)) . "\n</blockquote>";
                continue;
            }
            if (self::tabellenAnfang($zeilen, $i)) {
                $ausrichtung = self::ausrichtung($zeilen[$i + 1]);
                $html = "<div class=\"tabellenrahmen\"><table>\n<thead>\n"
                      . self::tabellenzeile($z, 'th', $ausrichtung) . "\n</thead>\n<tbody>";
                $i += 2;
                while ($i < $n && strpos($zeilen[$i], '|') !== false && self::putz($zeilen[$i]) !== '') {
                    $html .= "\n" . self::tabellenzeile($zeilen[$i], 'td', $ausrichtung);
                    $i++;
                }
                $aus[] = $html . "\n</tbody>\n</table></div>";
                continue;
            }
            if (preg_match('/^ {0,3}([-*+]|\d+\.) +/', $z, $t)) {
                $geordnet = strlen($t[1]) > 1;
                $muster = $geordnet ? '/^ {0,3}\d+\. +(.*)$/' : '/^ {0,3}[-*+] +(.*)$/';
                $punkte = [];
                while ($i < $n && preg_match($muster, $zeilen[$i], $t)) {
                    $text = $t[1];
                    $i++;
                    // Folgezeilen gehoeren zum Punkt, bis ein neuer Punkt, eine
                    // Leerzeile oder ein anderer Block beginnt.
                    while ($i < $n && self::putz($zeilen[$i]) !== '' && !preg_match($muster, $zeilen[$i])
                           && !self::blockAnfang($zeilen, $i)) {
                        $text .= "\n" . self::putz($zeilen[$i]);
                        $i++;
                    }
                    $punkte[] = '<li>' . self::inline($text) . '</li>';
                }
                $tag = $geordnet ? 'ol' : 'ul';
                $aus[] = "<$tag>\n" . implode("\n", $punkte) . "\n</$tag>";
                continue;
            }
            $text = self::putz($z);
            $i++;
            while ($i < $n && self::putz($zeilen[$i]) !== '' && !self::blockAnfang($zeilen, $i)) {
                $text .= "\n" . self::putz($zeilen[$i]);
                $i++;
            }
            $aus[] = '<p>' . self::inline($text) . '</p>';
        }
        return $aus;
    }

    /** Beginnt in Zeile i etwas anderes als Fliesstext? Dann endet der Absatz davor. */
    private static function blockAnfang(array $zeilen, int $i): bool
    {
        $z = $zeilen[$i];
        return preg_match('/^(```|#{1,6}[ \t]| {0,3}>| {0,3}([-*+]|\d+\.) +| {0,3}([-*_])( *\3){2,} *$)/', $z) === 1
            || self::tabellenAnfang($zeilen, $i);
    }

    private static function tabellenAnfang(array $zeilen, int $i): bool
    {
        return strpos($zeilen[$i], '|') !== false && isset($zeilen[$i + 1]) && self::istTrennzeile($zeilen[$i + 1]);
    }

    private static function istTrennzeile(string $z): bool
    {
        if (strpos($z, '|') === false || strpos($z, '-') === false) return false;
        foreach (self::zellen($z) as $c) {
            if (!preg_match('/^:?-+:?$/', $c)) return false;
        }
        return true;
    }

    /** @return string[] */
    private static function zellen(string $z): array
    {
        $z = self::putz($z);
        if ($z !== '' && $z[0] === '|') $z = substr($z, 1);
        if ($z !== '' && substr($z, -1) === '|') $z = substr($z, 0, -1);
        return array_map([self::class, 'putz'], explode('|', $z));
    }

    /** @return string[] je Spalte '', 'mitte' oder 'rechts' */
    private static function ausrichtung(string $trennzeile): array
    {
        $aus = [];
        foreach (self::zellen($trennzeile) as $c) {
            $links = $c[0] === ':';
            $rechts = substr($c, -1) === ':';
            $aus[] = $links && $rechts ? 'mitte' : ($rechts ? 'rechts' : '');
        }
        return $aus;
    }

    private static function tabellenzeile(string $z, string $tag, array $ausrichtung): string
    {
        $html = '<tr>';
        foreach (self::zellen($z) as $k => $c) {
            $klasse = $ausrichtung[$k] ?? '';
            $html .= '<' . $tag . ($klasse !== '' ? ' class="' . $klasse . '"' : '') . '>'
                   . self::inline($c) . '</' . $tag . '>';
        }
        return $html . '</tr>';
    }

    /**
     * Auszeichnung innerhalb eines Blocks. Code zuerst, damit darin nichts als
     * Auszeichnung gilt: Jede Code-Spanne wird durch einen Platzhalter ersetzt,
     * der Rest als Ganzes ausgezeichnet, dann kommt der Code zurueck. So darf
     * Fett oder Kursiv ueber eine Code-Spanne hinwegreichen.
     */
    private static function inline(string $s): string
    {
        $teile = explode('`', $s);
        if (count($teile) % 2 === 0) {
            // Ein einzelner Backtick ohne Partner bleibt ein Zeichen.
            $letzte = array_pop($teile);
            $teile[count($teile) - 1] .= '`' . $letzte;
        }
        $codes = [];
        $text = '';
        foreach ($teile as $k => $t) {
            if ($k % 2 === 1) {
                $codes[] = '<code>' . self::esc($t) . '</code>';
                $text .= "\x00" . (count($codes) - 1) . "\x00";
            } else {
                $text .= $t;
            }
        }
        return (string)preg_replace_callback('/\x00(\d+)\x00/', static function (array $m) use ($codes): string {
            return $codes[(int)$m[1]];
        }, self::text($text));
    }

    private static function text(string $t): string
    {
        $s = self::esc($t);
        $s = (string)preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', static function (array $m): string {
            return self::linkErlaubt($m[2]) ? '<a href="' . $m[2] . '">' . $m[1] . '</a>' : $m[0];
        }, $s);
        $s = (string)preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s);
        // Auch ueber den Zeilenumbruch, denn Absaetze sind umbrochen: Der kursive
        // Vorspann des Impressums zeigte sonst seine Sternchen.
        $s = (string)preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $s);
        return $s;
    }

    /** http, https, mailto oder relativ; jede andere Angabe mit Doppelpunkt bleibt Text. */
    private static function linkErlaubt(string $ziel): bool
    {
        return preg_match('#^(https?://|mailto:)#i', $ziel) === 1 || strpos($ziel, ':') === false;
    }
}
