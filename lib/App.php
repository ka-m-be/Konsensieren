<?php
declare(strict_types=1);
if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; }

/** Routen und Formularverarbeitung. */
final class App
{
    private static ?Poll $poll = null;
    private static string $rolle = '';
    private static ?array $ich = null;
    private static string $schluessel = '';

    public static function los(): void
    {
        $seg = Router::segmente();
        $kopf = $seg[0] ?? '';
        try {
            switch ($kopf) {
                case '':             self::start(); break;
                case 'neu':          self::neu(); break;
                case 'beispiel':     self::beispiel(); break;
                case 'hilfe':        self::seite('hilfe'); break;
                case 'quelltext':    self::seite('quelltext'); break;
                case 'impressum':    self::markdown('Impressum.md', 'Impressum.example.md'); break;
                case 'artikel':      self::markdown('Artikel.md'); break;
                case 'quelltext.zip': Zip::ausgeben('konsensieren.zip'); break;
                case 'a': case 'i': case 'u': case 'e': self::abstimmung($kopf, $seg); break;
                default:             self::fehler(404, t('fehler.unbekannt'));
            }
        } catch (Throwable $e) {
            // Ins Server-Protokoll, nicht auf die Seite: die Meldung koennte
            // Pfade oder Schluessel enthalten.
            error_log('Konsensieren: ' . $e->getMessage() . ' in '
                      . $e->getFile() . ':' . $e->getLine());
            self::fehler(500, t('fehler.intern'), $e);
        }
    }

    /* ------------------------------------------------------------ Ausgabe */

    public static function zeigen(string $vorlage, array $daten = []): void
    {
        $daten['poll']  = self::$poll;
        $daten['rolle'] = self::$rolle;
        $daten['ich']   = self::$ich;
        $daten['schluessel'] = self::$schluessel;
        $daten['meldung'] = Util::get('m');
        extract($daten, EXTR_SKIP);
        $inhalt = Config::get('wurzel') . '/tpl/' . $vorlage . '.php';
        include Config::get('wurzel') . '/tpl/layout.php';
    }

    private static function seite(string $name): void
    {
        self::zeigen($name);
    }

    /**
     * Eine der eigenen Markdown-Dateien im Hauptverzeichnis (Spezifikation 20).
     * Markdown statt einer Vorlage, damit die betreibende Person das Impressum
     * anpassen kann, ohne PHP oder HTML anzufassen; der Artikel wird ohnehin von
     * Hand weitergeschrieben. Fehlt die Datei, gibt es die Seite nicht - ausser es
     * gibt eine Vorlage: Das Impressum kommt wie config.php aus einer
     * ausgefuellten Datei, die weder im Repository noch im Quelltextpaket liegt,
     * und bis sie da ist, steht die Vorlage mit ihren Platzhaltern auf der Seite.
     */
    private static function markdown(string $datei, string $vorlage = ''): void
    {
        $pfad = Config::get('wurzel') . '/' . $datei;
        if (!is_file($pfad) && $vorlage !== '') $pfad = Config::get('wurzel') . '/' . $vorlage;
        if (!is_file($pfad)) self::fehler(404, t('fehler.unbekannt'));
        $md = (string)file_get_contents($pfad);
        self::zeigen('markdown', ['html' => Markdown::html($md), 'seitentitel' => Markdown::titel($md)]);
    }

    /** Ein Link, der nicht passt: verzoegern, mitzaehlen, abweisen. */
    private static function abgewiesen(int $code): void
    {
        $nochErlaubt = Security::fehlversuch();
        self::fehler($nochErlaubt ? $code : 429,
                     $nochErlaubt ? t('fehler.link') : t('fehler.zuviel'));
    }

    public static function fehler(int $code, string $text, ?Throwable $e = null): void
    {
        http_response_code($code);
        self::$poll = null;
        self::zeigen('fehler', ['code' => $code, 'text' => $text, 'ausnahme' => $e]);
        exit;
    }

    /** Adresse innerhalb der aktuellen Abstimmung. */
    public static function ziel(string $unter = '', array $parameter = []): string
    {
        return Router::url(self::rolle_kurz() . '/' . self::$schluessel . ($unter === '' ? '' : '/' . $unter), $parameter);
    }

    private static function rolle_kurz(): string
    {
        return ['admin' => 'a', 'user' => 'u', 'einladung' => 'i', 'ergebnis' => 'e'][self::$rolle] ?? 'u';
    }

    /* ------------------------------------------------------------- Start */

    private static function start(): void
    {
        self::zeigen('start', ['kennwort_noetig' => (string)Config::get('anlegen_kennwort') !== '']);
    }

    private static function neu(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Security::tokenPruefen('neu')) {
            Util::weiterleiten(Router::url(''));
        }
        if (!Security::drosselung('anlegen', 20)) {
            self::fehler(429, t('fehler.zuviel'));
        }
        $kennwort = (string)Config::get('anlegen_kennwort');
        if ($kennwort !== '' && !hash_equals($kennwort, Util::post('kennwort'))) {
            Security::bremsen();
            self::zeigen('start', ['kennwort_noetig' => true, 'fehler' => t('start.kennwort_falsch')]);
            return;
        }

        $titel = Util::kuerzen(Util::post('titel'), 200);
        $name  = Util::kuerzen(Util::post('name'), 60);
        $fehler = [];
        if ($titel === '') $fehler[] = t('fehler.titel_fehlt');
        if ($name === '')  $fehler[] = t('fehler.name_fehlt');

        $maxTage = (int)Config::get('max_laufzeit_tage');
        $ev = Util::datumEinlesen(Util::post('ende_vorschlag'));
        $eb = Util::datumEinlesen(Util::post('ende_bewertung'));
        if ($ev === null || $eb === null) $fehler[] = t('fehler.datum');
        elseif ($ev <= Util::jetzt()) $fehler[] = t('fehler.datum_vergangen');
        elseif ($eb <= $ev) $fehler[] = t('fehler.datum_reihenfolge');
        elseif ($eb > Util::jetzt() + $maxTage * 86400) $fehler[] = t('fehler.datum_zu_weit', ['n' => $maxTage]);

        if ($fehler) {
            self::zeigen('start', ['kennwort_noetig' => $kennwort !== '', 'fehler' => implode(' ', $fehler)]);
            return;
        }

        [$poll, $adminGeheim, $einladung] = Poll::anlegen(
            $titel,
            Util::kuerzen(Util::post('beschreibung'), (int)Config::get('max_textlaenge')),
            (int)$ev, (int)$eb,
            [
                'passiv'      => !Util::postAn('ohne_passiv'),
                'passiv_text' => Util::kuerzen(Util::post('passiv_text'), 500) ?: t('passiv.vorgabe'),
                'veto'        => Util::postAn('mit_veto'),
                'schwelle'    => max(0, min(100, Util::postInt('schwelle', 20))),
                'quorum'      => max(0, min(100, Util::postInt('quorum', 50))),
            ]
        );
        [$tid, $nutzerGeheim] = $poll->teilnehmerAnlegen($name, true);
        $poll->setzen(['admin_nutzer_geheim' => $nutzerGeheim]);

        self::$poll = $poll;
        self::$rolle = 'admin';
        self::$schluessel = $poll->id() . '.' . $adminGeheim;
        self::zeigen('angelegt', [
            'admin_link'     => Keys::link('a', $poll->id(), $adminGeheim),
            'einladung_link' => Keys::link('i', $poll->id(), $einladung),
            'nutzer_link'    => Keys::link('u', $poll->id(), $nutzerGeheim),
        ]);
    }

    /**
     * Beispiel-Abstimmung zum Ausprobieren.
     *
     * Ausdruecklich nur per POST mit Token: Ein schlichter Link waere von
     * Suchmaschinen-Crawlern und Link-Vorschau-Diensten verfolgt worden, und jeder
     * von ihnen haette eine Abstimmung angelegt. Drosselung und Kennwort gelten
     * wie beim normalen Anlegen - sonst waere das Beispiel eine Umgehung der Sperre.
     */
    private static function beispiel(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Security::tokenPruefen('neu')) {
            Util::weiterleiten(Router::url(''));
        }
        if (!Beispiel::erlaubt()) {
            self::fehler(404, t('fehler.unbekannt'));
        }
        if (!Security::drosselung('anlegen', 20)) {
            self::fehler(429, t('fehler.zuviel'));
        }
        $kennwort = (string)Config::get('anlegen_kennwort');
        if ($kennwort !== '' && !hash_equals($kennwort, Util::post('kennwort'))) {
            Security::bremsen();
            self::zeigen('start', ['kennwort_noetig' => true, 'fehler' => t('start.kennwort_falsch')]);
            return;
        }

        $stadium = Util::post('stadium');
        [$poll, $adminGeheim, $nutzerGeheim] = Beispiel::anlegen($stadium);

        self::$poll = $poll;
        self::$rolle = 'admin';
        self::$schluessel = $poll->id() . '.' . $adminGeheim;
        // Direkt dorthin, wo im jeweiligen Stadium etwas zu sehen ist. Die
        // Linkuebersicht entfaellt; den Weg in die Verwaltung haelt stattdessen der
        // Erklaerkasten offen (siehe Beispiel::anlegen()).
        $ziel = ['vorschlag' => 'vorschlaege', 'bewertung' => 'bewerten', 'ergebnis' => 'ergebnis'];
        Util::weiterleiten(Keys::link('u', $poll->id(), $nutzerGeheim)
                           . '/' . ($ziel[$stadium] ?? 'bewerten'));
    }

    /* ------------------------------------------------- Abstimmung oeffnen */

    private static function abstimmung(string $art, array $seg): void
    {
        $teile = Keys::zerlegen($seg[1] ?? '');
        if ($teile === null) self::abgewiesen(404);
        [$pollId, $geheim] = $teile;

        $poll = Poll::laden($pollId);
        if ($poll === null) self::abgewiesen(404);
        if ($poll->abgelaufen()) { Storage::loeschen($pollId); self::fehler(404, t('fehler.geloescht')); }

        // Sagt der Browser nichts Brauchbares und wurde nichts gewaehlt, folgen wir
        // der Sprache, in der die Abstimmung angelegt wurde.
        if (!I18n::selbstGewaehlt()) I18n::laden((string)$poll->v('sprache'));

        $poll->phaseNachziehen();
        self::$poll = $poll;
        self::$schluessel = $pollId . '.' . $geheim;
        Security::kontext(self::$schluessel);
        $unter = $seg[2] ?? '';

        switch ($art) {
            case 'a':
                if (!$poll->istAdminSchluessel($geheim)) self::abgewiesen(403);
                self::$rolle = 'admin';
                $st = $poll->db->query('SELECT * FROM teilnehmer WHERE ist_admin = 1 LIMIT 1')->fetch();
                self::$ich = $st ?: null;
                Admin::los($poll, $unter);
                return;

            case 'i':
                if (!$poll->istEinladung($geheim)) self::abgewiesen(403);
                self::$rolle = 'einladung';
                self::beitreten($poll);
                return;

            case 'u':
                $ich = $poll->teilnehmerPerHash(Keys::hash($geheim));
                if ($ich === null) self::abgewiesen(403);
                self::$rolle = 'user';
                self::$ich = $ich;
                self::teilnehmer($poll, $unter);
                return;

            case 'e':
                if (!$poll->istErgebnisSchluessel($geheim)) self::abgewiesen(403);
                if ($poll->phase() !== Poll::PHASE_ERGEBNIS && !$poll->an('ergebnis_vorab')) {
                    self::fehler(403, t('fehler.ergebnis_spaeter'));
                }
                self::$rolle = 'ergebnis';
                self::zeigen('oeffentlich', ['erg' => Auswertung::rechnen($poll)]);
                return;
        }
    }

    /* ---------------------------------------------------------- Beitreten */

    private static function beitreten(Poll $poll): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Security::tokenPruefen('beitritt')) self::fehler(403, t('fehler.token'));
            $name = Util::kuerzen(Util::post('name'), 60);
            if ($name === '') {
                self::zeigen('beitritt', ['fehler' => t('fehler.name_fehlt')]);
                return;
            }
            if (!$poll->nameFrei($name)) {
                self::zeigen('beitritt', ['fehler' => t('fehler.name_belegt'), 'name' => $name]);
                return;
            }
            if ($poll->teilnehmerZahl() >= (int)Config::get('max_teilnehmer')) {
                self::fehler(403, t('fehler.voll'));
            }
            [$id, $geheim] = $poll->teilnehmerAnlegen($name);
            // Gleich auf die Zugangsseite: dort steht der persoenliche Link, den
            // sich die Person sichern muss - eine Mail zum Nachschicken gibt es nicht.
            Util::weiterleiten(Keys::link('u', $poll->id(), $geheim) . '/ich?m=willkommen');
        }
        self::zeigen('beitritt', []);
    }

    /* -------------------------------------------------------- Teilnehmer */

    private static function teilnehmer(Poll $poll, string $unter): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::teilnehmerAktion($poll);
            return;
        }
        $ansicht = $unter !== '' ? $unter : self::vorgabeAnsicht($poll);
        switch ($ansicht) {
            case 'bewerten':
                // Ausserhalb der Bewertungsphase gibt es nichts zu bewerten - ein
                // Formular, das nichts speichert, waere eine Falle.
                if ($poll->phase() === Poll::PHASE_VORSCHLAG) Util::weiterleiten(self::ziel('vorschlaege') . Util::anker());
                if ($poll->phase() === Poll::PHASE_ERGEBNIS)  Util::weiterleiten(self::ziel('ergebnis'));
                self::zeigen('bewerten', [
                    'zettel'      => Vorschlaege::stimmzettel($poll),
                    'meine'       => Bewertungen::meine($poll, (int)self::$ich['id']),
                    'fortschritt' => Auswertung::fortschritt($poll, (int)self::$ich['id']),
                    'kommentare'  => Vorschlaege::kommentareAlle($poll),
                ]);
                return;
            case 'ergebnis':
                $offen = $poll->phase() === Poll::PHASE_ERGEBNIS || $poll->an('sicht_ergebnis');
                self::zeigen('ergebnis', [
                    'offen' => $offen,
                    'erg'   => $offen ? Auswertung::rechnen($poll) : null,
                    'matrix' => ($offen && $poll->an('sicht_bewertungen')) ? self::matrix($poll) : null,
                ]);
                return;
            case 'ich':
                self::zeigen('ich', ['link' => Keys::link('u', $poll->id(), explode('.', self::$schluessel)[1])]);
                return;
            default:
                self::zeigen('vorschlaege', [
                    'liste'      => Vorschlaege::geordnet($poll),
                    'kommentare' => Vorschlaege::kommentareAlle($poll),
                    'meine_u'    => Vorschlaege::meineUnterstuetzungen($poll, (int)self::$ich['id']),
                ]);
        }
    }

    public static function vorgabeAnsichtOeffentlich(Poll $poll): string
    {
        return self::vorgabeAnsicht($poll);
    }

    private static function vorgabeAnsicht(Poll $poll): string
    {
        if ($poll->phase() === Poll::PHASE_BEWERTUNG) return 'bewerten';
        if ($poll->phase() === Poll::PHASE_ERGEBNIS)  return 'ergebnis';
        return 'vorschlaege';
    }

    /** Tabelle aller Bewertungen, wenn der Admin sie freigegeben hat. */
    public static function matrix(Poll $poll): array
    {
        return [
            'teilnehmer' => $poll->teilnehmer(),
            'werte'      => Bewertungen::alle($poll),
            'zettel'     => Vorschlaege::stimmzettel($poll),
            'namen'      => $poll->an('zeige_namen'),
        ];
    }

    private static function teilnehmerAktion(Poll $poll): void
    {
        if (!Security::tokenPruefen('user')) self::fehler(403, t('fehler.token'));
        $ich = self::$ich;
        $aktion = Util::post('aktion');
        $zurueck = Util::post('zurueck');

        if ($poll->phase() === Poll::PHASE_VORSCHLAG) {
            switch ($aktion) {
                case 'vorschlag_neu':
                    if (Vorschlaege::anzahl($poll) >= (int)Config::get('max_vorschlaege')) {
                        self::fehler(403, t('fehler.zuviele_vorschlaege'));
                    }
                    $titel = Util::kuerzen(Util::post('titel'), 200);
                    if ($titel === '') Util::weiterleiten(self::ziel('vorschlaege', ['m' => 'titel_fehlt']));
                    $eltern = [];
                    $primaer = Util::postInt('primaer');
                    if ($primaer > 0) $eltern[] = $primaer;
                    foreach ((array)($_POST['weitere'] ?? []) as $w) {
                        $w = (int)$w;
                        if ($w > 0 && $w !== $primaer) $eltern[] = $w;
                    }
                    Vorschlaege::anlegen($poll, $ich, $titel,
                        Util::kuerzen(Util::post('text'), (int)Config::get('max_textlaenge')),
                        $eltern, Util::postAn('mit_namen'));
                    Util::weiterleiten(self::ziel('vorschlaege', ['m' => 'vorschlag_da']) . Util::anker());
                    break;

                case 'vorschlag_aendern':
                    $v = Vorschlaege::einzeln($poll, Util::postInt('id'));
                    if ($v && (int)$v['autor_id'] === (int)$ich['id']) {
                        Vorschlaege::bearbeiten($poll, (int)$v['id'], Util::kuerzen(Util::post('titel'), 200),
                            Util::kuerzen(Util::post('text'), (int)Config::get('max_textlaenge')));
                    }
                    Util::weiterleiten(self::ziel('vorschlaege', ['m' => 'gespeichert']) . Util::anker());
                    break;

                case 'vorschlag_zurueck':
                    $v = Vorschlaege::einzeln($poll, Util::postInt('id'));
                    if ($v && (int)$v['autor_id'] === (int)$ich['id']) {
                        Vorschlaege::zurueckziehen($poll, (int)$v['id']);
                    }
                    Util::weiterleiten(self::ziel('vorschlaege', ['m' => 'zurueckgezogen']) . Util::anker());
                    break;

                case 'unterstuetzen':
                    Vorschlaege::unterstuetzen($poll, Util::postInt('id'), (int)$ich['id'], Util::postAn('ja'));
                    Util::weiterleiten(self::ziel('vorschlaege') . Util::anker());
                    break;
            }
        }

        if ($aktion === 'kommentar') {
            $text = Util::kuerzen(Util::post('text'), 2000);
            if ($text !== '') {
                Vorschlaege::kommentieren($poll, Util::postInt('id'), $ich, $text, Util::postAn('mit_namen'));
            }
            Util::weiterleiten(self::ziel($zurueck ?: 'vorschlaege', ['m' => 'kommentar_da']) . Util::anker());
        }

        if ($aktion === 'bewerten' && $poll->phase() === Poll::PHASE_BEWERTUNG) {
            $werte = (array)($_POST['wert'] ?? []);
            foreach ($werte as $vid => $wert) {
                $vid = (int)$vid;
                $wert = (string)$wert;
                $zahl = ($wert === '' || $wert === 'null') ? null : (int)$wert;
                $veto = !empty($_POST['veto'][$vid]);
                $grund = (string)($_POST['veto_grund'][$vid] ?? '');
                if ($veto && trim($grund) === '') { $veto = false; }
                Bewertungen::speichern($poll, (int)$ich['id'], $vid, $zahl, $veto, $grund);
            }
            Util::weiterleiten(self::ziel('bewerten', ['m' => 'gespeichert']));
        }

        if ($aktion === 'name_aendern') {
            $name = Util::kuerzen(Util::post('name'), 60);
            if ($name !== '' && ($name === $ich['name'] || $poll->nameFrei($name))) {
                $poll->db->prepare('UPDATE teilnehmer SET name = ? WHERE id = ?')
                         ->execute([$name, (int)$ich['id']]);
            }
            Util::weiterleiten(self::ziel('ich', ['m' => 'gespeichert']));
        }

        Util::weiterleiten(self::ziel());
    }
}
