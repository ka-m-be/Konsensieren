<?php
declare(strict_types=1);
if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; }

final class I18n
{
    /** @var array<string,string> */
    private static array $texte = [];
    private static string $sprache = 'de';

    public const SPRACHEN = ['de', 'en'];

    public static function waehlen(?string $wunsch): void
    {
        $moeglich = self::SPRACHEN;
        $s = null;
        if ($wunsch !== null && in_array($wunsch, $moeglich, true)) {
            $s = $wunsch;
            // Ausdrueckliche Wahl merken, damit sie nicht bei jedem Klick verfaellt.
            // Ausser diesem Cookie gibt es nur noch das des Profi-Modus (Util::profiWaehlen()).
            @setcookie('sprache', $s, [
                'expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax',
                'httponly' => true, 'secure' => !empty($_SERVER['HTTPS']),
            ]);
        } elseif (isset($_COOKIE['sprache']) && in_array($_COOKIE['sprache'], $moeglich, true)) {
            $s = (string)$_COOKIE['sprache'];
        } else {
            $kopf = strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
            foreach (explode(',', $kopf) as $teil) {
                $code = substr(trim(explode(';', $teil)[0]), 0, 2);
                if (in_array($code, $moeglich, true)) { $s = $code; break; }
            }
        }
        self::laden($s ?? (string)Config::get('sprache'));
    }

    /** Nachtraeglich umschalten, etwa auf die Sprache einer Abstimmung. */
    public static function laden(string $sprache): void
    {
        if (!in_array($sprache, self::SPRACHEN, true)) return;
        self::$sprache = $sprache;
        self::$texte = require Config::get('wurzel') . '/lang/' . $sprache . '.php';
    }

    /** Hat die lesende Person selbst eine Sprache gewaehlt? */
    public static function selbstGewaehlt(): bool
    {
        return isset($_GET['lang']) || isset($_COOKIE['sprache'])
            || strpos(strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')), 'en') !== false
            || strpos(strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')), 'de') !== false;
    }

    public static function sprache(): string
    {
        return self::$sprache;
    }

    /**
     * Einzahl und Mehrzahl: Gibt es zum Schluessel eine Fassung mit dem Anhang
     * "_eins" und ist die Anzahl {n} genau eins, wird sie genommen. Mehr Regeln
     * braucht es fuer Deutsch und Englisch nicht.
     * @param array<string,string|int> $werte
     */
    public static function t(string $schluessel, array $werte = []): string
    {
        if (isset($werte['n']) && (int)$werte['n'] === 1 && isset(self::$texte[$schluessel . '_eins'])) {
            $schluessel .= '_eins';
        }
        $text = self::$texte[$schluessel] ?? $schluessel;
        foreach ($werte as $k => $v) {
            $text = str_replace('{' . $k . '}', (string)$v, $text);
        }
        return $text;
    }
}

/** Kurzform, weil das im Template sonst unlesbar wird. */
function t(string $schluessel, array $werte = []): string
{
    return I18n::t($schluessel, $werte);
}

/** Kurzform mit Ausgabe und Escaping. */
function te(string $schluessel, array $werte = []): void
{
    echo Util::esc(I18n::t($schluessel, $werte));
}

/**
 * Eine Erlaeuterung, die im Profi-Modus wegfaellt (Spezifikation 20): ein
 * <span class="dazu"> oder <p class="dazu"> unter einem Feld, Schalter oder Knopf.
 * Warnungen und Beschriftungen laufen nicht hierueber, die bleiben immer stehen.
 */
function erkl(string $schluessel, array $werte = [], string $tag = 'span'): void
{
    if (Util::profi()) return;
    echo '<' . $tag . ' class="dazu">' . Util::esc(I18n::t($schluessel, $werte)) . '</' . $tag . '>';
}
