<?php
declare(strict_types=1);
if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; }

/**
 * Schluessel haben die Form <pollid>.<geheimnis>.
 * Die pollid zeigt direkt auf die Datei, das Geheimnis wird nur als Hash gespeichert.
 * Alphabet: Crockford-Base32 ohne I, L, O und U - schwer zu verwechseln.
 */
final class Keys
{
    public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function zufall(int $zeichen): string
    {
        $bytes = random_bytes($zeichen);
        $out = '';
        for ($i = 0; $i < $zeichen; $i++) {
            $out .= self::ALPHABET[ord($bytes[$i]) & 31];
        }
        return $out;
    }

    /** 10 Zeichen = 50 Bit, reicht als Dateiname. */
    public static function pollId(): string
    {
        return self::zufall(10);
    }

    /** 26 Zeichen = 130 Bit. */
    public static function geheimnis(): string
    {
        return self::zufall(26);
    }

    public static function hash(string $geheimnis): string
    {
        return hash('sha256', 'sk-key|' . Config::salt() . '|' . strtoupper($geheimnis));
    }

    public static function gleich(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }

    /** Aus "abc.DEF" wird ['abc','DEF']; ungueltiges gibt null. */
    public static function zerlegen(string $roh): ?array
    {
        $roh = strtoupper(preg_replace('/[^0-9A-Za-z.]/', '', $roh) ?? '');
        if (substr_count($roh, '.') !== 1) return null;
        [$id, $geheim] = explode('.', $roh);
        if (!preg_match('/^[0-9A-Z]{10}$/', $id)) return null;
        if (!preg_match('/^[0-9A-Z]{20,40}$/', $geheim)) return null;
        return [$id, $geheim];
    }

    /** Zur Anzeige in Vierergruppen, damit man ihn notfalls abtippen kann. */
    public static function lesbar(string $geheimnis): string
    {
        return trim(chunk_split($geheimnis, 4, ' '));
    }

    public static function link(string $rolle, string $pollId, string $geheimnis): string
    {
        return Router::url($rolle . '/' . $pollId . '.' . $geheimnis);
    }
}
