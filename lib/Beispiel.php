<?php
declare(strict_types=1);
if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; }

/**
 * Die Beispiel-Abstimmung zum Ausprobieren: "Wohin faehrt die Gruppe?"
 *
 * Kein Schaubild, sondern eine *echte* Abstimmung mit erfundenen Daten. Wer sie
 * anlegt, bekommt Verwaltungs- und Teilnahmelink und kann alles anfassen.
 *
 * Die Zahlen sind nicht ausgewuerfelt, sondern so gebaut, dass sie die Methode
 * beweisen statt sie nur zu bebildern: Per Handzeichen gewaenne "Alpen" mit drei
 * von sieben Erststimmen - beim Konsensieren gewinnt "Nordsee", ohne eine einzige
 * 10, aber ohne dass jemand unter 6 faellt. Die beiden, die bei den Alpen auf 1
 * und 0 stehen, waeren ueberstimmt worden. "Krakau" liegt sogar unter der
 * Passivloesung und erklaert damit gleich die Messlatte mit.
 *
 * Der Zufallsgenerator fuer Lasttests ist ein anderes Werkzeug
 * (tools/testdaten.php); dieses hier ist das kuratierte Gegenstueck.
 */
final class Beispiel
{
    public const STADIEN = ['vorschlag', 'bewertung', 'ergebnis'];

    /** Die sieben Erfundenen. Der Besucher kommt als achte Person "Du" dazu. */
    private const LEUTE = ['anke', 'bernd', 'cem', 'dilek', 'erik', 'fatma', 'georg'];

    /** Die Vorschlaege in der Reihenfolge, in der sie eingebracht wurden. */
    private const VORSCHLAEGE = ['alpen', 'nordsee', 'krakau', 'zuhause'];

    /**
     * Zustimmungswerte, Zeile fuer Zeile: Person => Vorschlag => 0..10.
     * 'passiv' ist die Passivloesung.
     *
     *              Alpen  Nordsee  Krakau  zuhause  passiv
     *   Anke         10      7        8       4        5     Erststimme Alpen
     *   Bernd        10      8        3       5        4     Erststimme Alpen
     *   Cem           9      7        7       3        6     Erststimme Alpen
     *   Dilek         5      8        2       6        3     Erststimme Nordsee
     *   Erik          6      6        7       5        7
     *   Fatma         1      7        4       9        8     Erststimme zuhause
     *   Georg         0      8        6       8        5
     *   ---------------------------------------------------
     *   Ø Rueckhalt  5,9    7,3      5,3     5,7      5,4
     *   Mindestwert    0      6        2       3        3
     */
    private const WERTE = [
        'anke'  => ['alpen' => 10, 'nordsee' => 7, 'krakau' => 8, 'zuhause' => 4, 'passiv' => 5],
        'bernd' => ['alpen' => 10, 'nordsee' => 8, 'krakau' => 3, 'zuhause' => 5, 'passiv' => 4],
        'cem'   => ['alpen' =>  9, 'nordsee' => 7, 'krakau' => 7, 'zuhause' => 3, 'passiv' => 6],
        'dilek' => ['alpen' =>  5, 'nordsee' => 8, 'krakau' => 2, 'zuhause' => 6, 'passiv' => 3],
        'erik'  => ['alpen' =>  6, 'nordsee' => 6, 'krakau' => 7, 'zuhause' => 5, 'passiv' => 7],
        'fatma' => ['alpen' =>  1, 'nordsee' => 7, 'krakau' => 4, 'zuhause' => 9, 'passiv' => 8],
        'georg' => ['alpen' =>  0, 'nordsee' => 8, 'krakau' => 6, 'zuhause' => 8, 'passiv' => 5],
    ];

    /**
     * Mittendrin heisst: Erik und Georg haben noch gar nicht bewertet, und Fatma
     * hat Krakau offen gelassen. So zeigt schon der Zwischenstand, dass "von 4 der
     * 8 bewertet" etwas anderes ist als eine 0 - und Krakau liegt mit genau vier
     * Bewertungen haarscharf auf der Beteiligungsschwelle.
     */
    private const NOCH_NICHT = ['erik', 'georg'];
    private const LUECKE     = ['fatma' => 'krakau'];

    /** Wer hat welchen Vorschlag unterstuetzt? Der Urheber zaehlt automatisch mit. */
    private const UNTERSTUETZUNG = [
        'alpen'   => ['bernd', 'cem'],
        'nordsee' => ['dilek', 'georg', 'erik'],
        'krakau'  => ['anke'],
        'zuhause' => ['georg'],
    ];

    /** Kommentare: [Vorschlag, wer, welcher Textbaustein]. */
    private const KOMMENTARE = [
        ['alpen',   'fatma', 1],
        ['alpen',   'anke',  2],
        ['nordsee', 'dilek', 1],
        ['krakau',  'erik',  1],
    ];

    /** Wer hat was eingebracht? "nordsee" ist eine Abwandlung von "alpen". */
    private const URHEBER = [
        'alpen' => 'anke', 'nordsee' => 'dilek', 'krakau' => 'erik', 'zuhause' => 'fatma',
    ];
    private const ABWANDLUNG_VON = ['nordsee' => 'alpen'];

    public static function erlaubt(): bool
    {
        return (bool)Config::get('beispiel_erlauben');
    }

    /**
     * Legt die Beispiel-Abstimmung an und gibt sie samt Zugaengen zurueck.
     *
     * @param string $stadium eines aus STADIEN
     * @return array{0:Poll,1:string,2:string,3:string} Poll, Admin-, Nutzer-, Einladungsgeheimnis
     */
    public static function anlegen(string $stadium): array
    {
        if (!in_array($stadium, self::STADIEN, true)) $stadium = 'bewertung';
        $jetzt = Util::jetzt();
        $tage  = max(1, (int)Config::get('beispiel_tage'));

        // Die Termine liegen so, dass die gewuenschte Phase laeuft und der naechste
        // Wechsel nicht waehrend des Ausprobierens dazwischenfunkt.
        $frist = $jetzt + $tage * 86400;
        [$endeVorschlag, $endeBewertung] = [
            'vorschlag' => [$frist, $frist + 86400],
            'bewertung' => [$jetzt - 3600, $frist],
            'ergebnis'  => [$jetzt - 7200, $jetzt - 3600],
        ][$stadium];

        [$poll, $adminGeheim, $einladung] = Poll::anlegen(
            t('beispiel.titel'), t('beispiel.beschreibung'),
            $endeVorschlag, $endeBewertung,
            ['passiv' => true, 'passiv_text' => t('beispiel.passiv'),
             'veto' => false, 'schwelle' => 0, 'quorum' => 50]
        );

        // Kurze Lebensdauer statt der ueblichen drei Monate: Ein Beispiel muss
        // nicht lange herumliegen, und auf einer oeffentlichen Installation legen
        // viele Leute viele davon an.
        $poll->setzen(['ist_beispiel' => 1, 'loeschdatum' => $frist]);

        // Dritte Ausnahme zur Regel "Admin-Schluessel nur gehasht" (Spezifikation 3),
        // und die einzige, die auf Beispiele beschraenkt ist. Sonst waere die
        // Verwaltung nach dem Umleiten in die Teilnehmeransicht unerreichbar: Aus
        // dem Hash laesst sich der Link nicht zurueckrechnen. Bei einem Beispiel
        // haelt eine einzige echte Person beide Rollen, die uebrigen sieben sind
        // erfunden und ihre Schluessel hat niemand. Die Daten sind ausgedacht, und
        // nach wenigen Tagen ist die ganze Abstimmung geloescht.
        $poll->setzen(['beispiel_admin_geheim' => $adminGeheim]);

        // Der Besucher ist zugleich Verwalter und Teilnehmer - wie bei einer
        // echten Abstimmung, die man selbst anlegt.
        [, $adminNutzerGeheim] = $poll->teilnehmerAnlegen(t('beispiel.du'), true);
        $poll->setzen(['admin_nutzer_geheim' => $adminNutzerGeheim]);

        $leute = [];
        foreach (self::LEUTE as $kurz) {
            [$id] = $poll->teilnehmerAnlegen(t('beispiel.name_' . $kurz));
            $leute[$kurz] = ['id' => $id, 'name' => t('beispiel.name_' . $kurz)];
        }

        $vorschlaege = self::vorschlaegeAnlegen($poll, $leute);
        self::unterstuetzungen($poll, $leute, $vorschlaege);
        self::kommentare($poll, $leute, $vorschlaege);

        if ($stadium !== 'vorschlag') {
            $poll->uebergangBewertung('beispiel');
            self::bewertungen($poll, $leute, $vorschlaege, $stadium === 'ergebnis');
        }
        if ($stadium === 'ergebnis') {
            $poll->uebergangErgebnis('beispiel');
            // Im Ergebnisstadium ist die Auswertung sichtbar - sonst staende der
            // Besucher vor einer verschlossenen Tuer, und genau diese Seite ist
            // der Grund, warum es das Beispiel gibt.
            $poll->setzen(['sicht_ergebnis' => 1, 'sicht_bewertungen' => 1, 'zeige_namen' => 1]);
        }

        return [$poll, $adminGeheim, $adminNutzerGeheim, $einladung];
    }

    /** @return array<string,int> Kurzname => vorschlag_id */
    private static function vorschlaegeAnlegen(Poll $poll, array $leute): array
    {
        $ids = [];
        foreach (self::VORSCHLAEGE as $kurz) {
            $urheber = $leute[self::URHEBER[$kurz]];
            $eltern  = [];
            if (isset(self::ABWANDLUNG_VON[$kurz])) {
                $eltern = [$ids[self::ABWANDLUNG_VON[$kurz]]];
            }
            $ids[$kurz] = Vorschlaege::anlegen(
                $poll, $urheber, t('beispiel.v_' . $kurz), t('beispiel.v_' . $kurz . '_text'),
                $eltern, true
            );
        }
        return $ids;
    }

    private static function unterstuetzungen(Poll $poll, array $leute, array $ids): void
    {
        foreach (self::UNTERSTUETZUNG as $kurz => $wer) {
            foreach ($wer as $person) {
                Vorschlaege::unterstuetzen($poll, $ids[$kurz], (int)$leute[$person]['id'], true);
            }
        }
    }

    private static function kommentare(Poll $poll, array $leute, array $ids): void
    {
        foreach (self::KOMMENTARE as [$kurz, $person, $nr]) {
            Vorschlaege::kommentieren($poll, $ids[$kurz], $leute[$person],
                                      t('beispiel.k_' . $kurz . '_' . $nr), true);
        }
    }

    private static function bewertungen(Poll $poll, array $leute, array $ids, bool $vollstaendig): void
    {
        $passiv = null;
        foreach (Vorschlaege::stimmzettel($poll) as $v) {
            if ((int)$v['ist_passiv'] === 1) $passiv = (int)$v['id'];
        }
        foreach (self::WERTE as $person => $zeile) {
            if (!$vollstaendig && in_array($person, self::NOCH_NICHT, true)) continue;
            foreach ($zeile as $kurz => $wert) {
                if (!$vollstaendig && (self::LUECKE[$person] ?? null) === $kurz) continue;
                $vid = $kurz === 'passiv' ? $passiv : $ids[$kurz];
                if ($vid === null) continue;
                Bewertungen::speichern($poll, (int)$leute[$person]['id'], $vid, $wert, false, '');
            }
        }
    }
}
