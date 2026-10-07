<?php
declare(strict_types=1);
if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; }

/**
 * Systemisches Konsensieren: es gewinnt der breiteste Rueckhalt.
 *
 * Gemessen wird Zustimmung von 0 ("geht gar nicht fuer mich") bis 10 ("voll
 * dabei"). Das klassische Verfahren misst stattdessen Widerstand; beides ist
 * dieselbe Rechnung mit umgekehrtem Vorzeichen, denn Widerstand = 10 - Zustimmung.
 * Die Umkehrung ist ordnungsumkehrend, die Rangfolge also dieselbe - nur die
 * Aufmerksamkeit liegt jetzt darauf, was die Gruppe traegt, statt darauf, was sie
 * abwehrt. (Warum das so gewaehlt ist: Spec/50 und Spec/51.)
 *
 * Die Methode stammt aus dem Sitzungsraum, wo alle jeden Vorschlag bewerten.
 * Online fehlen Bewertungen - und dann ist die blosse Summe irrefuehrend:
 * Ein Vorschlag, den kaum jemand bewertet hat, saehe nach breitem Rueckhalt aus,
 * obwohl er in Wahrheit nur wenig Beachtung gefunden hat. Deshalb:
 *
 * 1. Gezaehlt wird nur, was wirklich abgegeben wurde. Fehlendes ist kein Wert 0 -
 *    und eine 0 waere hier ausgerechnet die schaerfste Ablehnung.
 * 2. Verglichen wird der *durchschnittliche* Rueckhalt, damit unterschiedliche
 *    Beteiligung die Rangfolge nicht verzerrt.
 * 3. Wer zu wenige Bewertungen hat, kommt nicht in die Rangfolge, sondern wird
 *    als "zu wenig Bewertungen" ausgewiesen. Das gilt auch fuer die Passivloesung:
 *    Laesst sich ihr Rueckhalt nicht belastbar messen, kann das Werkzeug auch
 *    nicht behaupten, ein Vorschlag sei besser oder schlechter als Nichtstun.
 * 4. Sichtbar bleibt, wo es klemmt: der niedrigste Einzelwert und die Zahl der
 *    sehr niedrigen Werte stehen gleichberechtigt neben dem Mittelwert. Positiv
 *    fragen heisst nicht, Bedenken zu verstecken - daran haengt die ganze Methode.
 */
final class Auswertung
{
    /**
     * @return array{zeilen:array<int,array<string,mixed>>, teilnehmer:int, wertende:int,
     *               passiv:?array<string,mixed>, sieger:?int, modus:string, noetig:int}
     */
    public static function rechnen(Poll $p): array
    {
        $zettel     = Vorschlaege::stimmzettel($p);
        $alle       = Bewertungen::alle($p);
        $teilnehmer = $p->teilnehmer();
        $namen      = [];
        foreach ($teilnehmer as $t) $namen[(int)$t['id']] = (string)$t['name'];

        $nurVoll  = $p->an('nur_vollstaendig');
        $zaehlend = [];
        foreach ($teilnehmer as $t) {
            $id = (int)$t['id'];
            if (!$nurVoll) { $zaehlend[$id] = true; continue; }
            $vollstaendig = true;
            foreach ($zettel as $v) {
                $b = $alle[(int)$v['id']][$id] ?? null;
                if ($b === null || $b['wert'] === null) { $vollstaendig = false; break; }
            }
            if ($vollstaendig) $zaehlend[$id] = true;
        }
        $wertende = count($zaehlend);
        $noetig   = self::mindestBewertungen($p, $wertende);

        $zeilen = [];
        foreach ($zettel as $v) {
            $vid = (int)$v['id'];
            $summe = 0; $bewertet = 0; $minimal = 10; $niedrig = 0;
            $verteilung = array_fill(0, 11, 0);
            $vetos = [];
            foreach ($alle[$vid] ?? [] as $tid => $b) {
                if (!isset($zaehlend[$tid])) continue;
                if ((int)$b['veto'] === 1) {
                    $vetos[] = ['name' => $namen[$tid] ?? '?', 'grund' => (string)$b['veto_grund']];
                }
                if ($b['wert'] === null) continue;
                $w = (int)$b['wert'];
                $summe += $w;
                $bewertet++;
                $verteilung[$w]++;
                $minimal = min($minimal, $w);
                if ($w <= 2) $niedrig++;
            }
            $z = $v;
            $z['summe']       = $summe;
            $z['bewertet']    = $bewertet;
            $z['offen']       = max(0, $wertende - $bewertet);
            // Ausdruecklich als Gleitkommazahl: PHP liefert bei glatter Division
            // sonst einen ganzzahligen Wert, und die Anzeige wuerde uneinheitlich.
            $z['mittel']      = $bewertet > 0 ? (float)$summe / $bewertet : null;
            // Ohne eine einzige Bewertung gibt es keinen niedrigsten Wert.
            $z['minimal']     = $bewertet > 0 ? $minimal : null;
            $z['niedrig']     = $niedrig;
            $z['verteilung']  = $verteilung;
            $z['vetos']       = $p->an('opt_veto') ? $vetos : [];
            $z['gesperrt']    = $p->an('opt_veto') && count($vetos) > 0;
            $z['beteiligung'] = $wertende > 0 ? $bewertet / $wertende : 0.0;
            $z['belastbar']   = $bewertet >= $noetig && $bewertet > 0;
            $zeilen[$vid]     = $z;
        }

        // Die Passivloesung ist die Messlatte - aber nur, wenn sie selbst belastbar ist.
        $passiv = null;
        foreach ($zeilen as $z) {
            if ((int)$z['ist_passiv'] === 1) { $passiv = $z; break; }
        }
        $messlatte = ($passiv !== null && $passiv['belastbar']) ? (float)$passiv['mittel'] : null;

        foreach ($zeilen as $vid => $z) {
            if ($messlatte === null || (int)$z['ist_passiv'] === 1 || $z['mittel'] === null) {
                $zeilen[$vid]['kik'] = null;
                // null heisst hier ausdruecklich "nicht entscheidbar", nicht "nein".
                $zeilen[$vid]['legitimiert'] = ((int)$z['ist_passiv'] === 1) ? true : null;
                $zeilen[$vid]['gleichauf'] = false;
            } else {
                // Kraft im Konsens: um wieviel mehr Rueckhalt hat der Vorschlag als
                // das Nichtstun? Die Zahl ist dieselbe wie beim klassischen Verfahren -
                // die additive 10 kuerzt sich in der Differenz weg.
                $abstand = (float)$z['mittel'] - $messlatte;
                $zeilen[$vid]['kik'] = $abstand;
                // Gleichstand ist weder "besser" noch "schlechter". Er bekommt einen
                // eigenen Zustand, sonst behauptet die Anzeige etwas Falsches.
                $zeilen[$vid]['gleichauf']   = abs($abstand) < 1e-9;
                $zeilen[$vid]['legitimiert'] = $abstand > 1e-9;
            }
        }

        // Rangfolge: nur belastbare Zeilen, groesster Rueckhalt zuerst. Bei
        // Gleichstand entscheidet, wer die Zoegernden besser mitnimmt - erst der
        // hoehere Mindestwert, dann die geringere Zahl sehr niedriger Werte.
        // Negiert, damit dasselbe aufsteigende <=> auch hier passt.
        $sortiert = array_values($zeilen);
        usort($sortiert, static function ($a, $b) {
            if ($a['belastbar'] !== $b['belastbar']) return $a['belastbar'] ? -1 : 1;
            $ma = $a['mittel'] === null ? -999 : (int)round($a['mittel'] * 1000);
            $mb = $b['mittel'] === null ? -999 : (int)round($b['mittel'] * 1000);
            return [-$ma, -(int)($a['minimal'] ?? 0), $a['niedrig'], (int)$a['angelegt']]
               <=> [-$mb, -(int)($b['minimal'] ?? 0), $b['niedrig'], (int)$b['angelegt']];
        });
        // Ohne Rang bleibt, wer nicht belastbar bewertet ist - und wer durch ein Veto
        // blockiert ist: Ein blockierter Vorschlag steht nicht zur Wahl, also gehoert
        // er auch nicht in die Reihe. Seine Zahlen bleiben sichtbar (siehe 6.4 der
        // Spezifikation), nur eine Platzziffer bekommt er nicht.
        $rang = 0; $letzter = null;
        foreach ($sortiert as $i => $z) {
            if (!$z['belastbar'] || $z['gesperrt']) { $sortiert[$i]['rang'] = null; continue; }
            $wert = (int)round((float)$z['mittel'] * 1000);
            if ($letzter === null || $wert !== $letzter) { $rang++; $letzter = $wert; }
            $sortiert[$i]['rang'] = $rang;
        }

        $sieger = null;
        foreach ($sortiert as $z) {
            if (!$z['belastbar'] || $z['gesperrt'] || $z['legitimiert'] === false) continue;
            $sieger = (int)$z['id'];
            break;
        }

        return [
            'zeilen'     => $sortiert,
            'teilnehmer' => count($teilnehmer),
            'wertende'   => $wertende,
            'passiv'     => $passiv,
            'messlatte'  => $messlatte,
            'sieger'     => $sieger,
            'modus'      => $nurVoll ? 'vollstaendig' : 'abgegeben',
            'noetig'     => $noetig,
        ];
    }

    /**
     * Wie viele Bewertungen braucht ein Vorschlag, damit sein Wert etwas aussagt?
     * Der Anteil ist einstellbar; unter zwei Bewertungen ist ein Durchschnitt
     * ohnehin nur die Meinung einer einzelnen Person. Bei genau einer wertenden
     * Person laesst sich das nicht einhalten - dann genuegt eine Bewertung, sonst
     * waere in einer solchen Abstimmung nie etwas belastbar.
     */
    public static function mindestBewertungen(Poll $p, int $wertende): int
    {
        if ($wertende <= 0) return 1;
        $prozent = (int)$p->v('quorum_prozent');
        $noetig  = (int)ceil($wertende * $prozent / 100);
        return max(1, min($wertende, max($noetig, min(2, $wertende))));
    }

    /** Wieviele der Stimmzettel-Vorschlaege hat diese Person bewertet? */
    public static function fortschritt(Poll $p, int $teilnehmerId): array
    {
        $zettel = Vorschlaege::stimmzettel($p);
        $meine  = Bewertungen::meine($p, $teilnehmerId);
        $fertig = 0;
        $passivOffen = false;
        foreach ($zettel as $v) {
            $b = $meine[(int)$v['id']] ?? null;
            $da = $b !== null && $b['wert'] !== null;
            if ($da) $fertig++;
            elseif ((int)$v['ist_passiv'] === 1) $passivOffen = true;
        }
        return [
            'fertig'       => $fertig,
            'gesamt'       => count($zettel),
            'offen'        => count($zettel) - $fertig,
            'passiv_offen' => $passivOffen,
        ];
    }

    /** CSV-Ausgabe fuer den Export. */
    public static function csv(Poll $p): string
    {
        $erg = self::rechnen($p);
        $out = fopen('php://temp', 'r+');
        // Trennzeichen ausdruecklich angeben: PHP 8.4 mahnt sonst den Vorgabewert an.
        $zeile = static function ($fh, array $felder): void { fputcsv($fh, $felder, ',', '"', ''); };
        $zeile($out, ['Rang', 'Vorschlag', 'Autor', 'Rueckhalt im Schnitt', 'Bewertet von',
                      'Wertende insgesamt', 'Niedrigster Einzelwert', 'Werte bis 2', 'Vetos',
                      'Kraft im Konsens', 'Belastbar', 'Widerstand im Schnitt']);
        foreach ($erg['zeilen'] as $z) {
            $zeile($out, [
                $z['rang'] ?? '', $z['titel'], $z['autor_name'],
                $z['mittel'] !== null ? number_format((float)$z['mittel'], 2, ',', '') : '',
                $z['bewertet'], $erg['wertende'], $z['minimal'] ?? '', $z['niedrig'],
                count($z['vetos']),
                $z['kik'] !== null ? number_format((float)$z['kik'], 2, ',', '') : '',
                $z['belastbar'] ? 'ja' : 'nein',
                // Bruecke fuer Gruppen, die ihre Zahlen mit SK-Literatur vergleichen.
                $z['mittel'] !== null ? number_format(10 - (float)$z['mittel'], 2, ',', '') : '',
            ]);
        }
        rewind($out);
        return (string)stream_get_contents($out);
    }

    /** Vollstaendiger Datensatz als JSON. */
    public static function json(Poll $p): string
    {
        $erg = self::rechnen($p);
        $daten = [
            'titel'        => $p->v('titel'),
            'beschreibung' => $p->v('beschreibung'),
            'phase'        => $p->phase(),
            'stand'        => date('c'),
            'teilnehmende' => $erg['teilnehmer'],
            'gewertet'     => $erg['wertende'],
            'modus'        => $erg['modus'],
            'mindestbewertungen' => $erg['noetig'],
            'skala' => ['groesse' => 'zustimmung', 'von' => 0, 'bis' => 10,
                        'gross_ist_gut' => true],
            'vorschlaege'  => [],
        ];
        foreach ($erg['zeilen'] as $z) {
            $daten['vorschlaege'][] = [
                'rang' => $z['rang'], 'titel' => $z['titel'], 'text' => $z['text'],
                'autor' => $z['autor_name'], 'passivloesung' => (bool)$z['ist_passiv'],
                'rueckhalt_im_schnitt' => $z['mittel'], 'bewertet_von' => $z['bewertet'],
                'niedrigster_wert' => $z['minimal'], 'werte_bis_2' => $z['niedrig'],
                'verteilung' => $z['verteilung'], 'vetos' => count($z['vetos']),
                'kik' => $z['kik'], 'legitimiert' => $z['legitimiert'],
                'gleichauf_mit_nichtstun' => $z['gleichauf'], 'belastbar' => $z['belastbar'],
                // Dieselbe Lage in der klassischen Zaehlweise: Widerstand = 10 - Zustimmung.
                'widerstand_im_schnitt' => $z['mittel'] === null ? null : 10 - (float)$z['mittel'],
            ];
        }
        return (string)json_encode($daten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
