<?php
declare(strict_types=1);
if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; }

/** Alles, was nur mit dem Admin-Schluessel geht. */
final class Admin
{
    public static function los(Poll $poll, string $unter): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::aktion($poll);
            return;
        }
        switch ($unter) {
            case 'einstellungen':
                App::zeigen('admin_einstellungen');
                return;
            case 'redaktion':
                App::zeigen('admin_redaktion', [
                    'liste' => Vorschlaege::geordnet($poll),
                ]);
                return;
            case 'teilnehmer':
                App::zeigen('admin_teilnehmer', ['leute' => $poll->teilnehmer()]);
                return;
            case 'protokoll':
                App::zeigen('admin_protokoll', ['eintraege' => $poll->protokoll()]);
                return;
            case 'ergebnis':
                App::zeigen('ergebnis', [
                    'offen'  => true,
                    'erg'    => Auswertung::rechnen($poll),
                    'matrix' => App::matrix($poll),
                ]);
                return;
            case 'export.csv':
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="konsensieren-' . $poll->id() . '.csv"');
                echo "\xEF\xBB\xBF" . Auswertung::csv($poll);
                return;
            case 'export.json':
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="konsensieren-' . $poll->id() . '.json"');
                echo Auswertung::json($poll);
                return;
            default:
                App::zeigen('admin', [
                    'einladung_link' => Keys::link('i', $poll->id(), (string)$poll->v('einladung_geheim')),
                    'nutzer_link'    => self::eigenerLink($poll),
                    'ergebnis_link'  => (string)$poll->v('ergebnis_geheim') !== ''
                        ? Keys::link('e', $poll->id(), (string)$poll->v('ergebnis_geheim')) : '',
                    'zahlen'         => self::zahlen($poll),
                ]);
        }
    }

    /**
     * Der Zugang, mit dem die verwaltende Person selbst mitmacht. Nur dieser eine
     * Schluessel liegt im Klartext, und zwar am Datensatz der Abstimmung - wer den
     * Admin-Link hat, ist ohnehin dieselbe Person.
     */
    private static function eigenerLink(Poll $poll): string
    {
        $geheim = (string)$poll->v('admin_nutzer_geheim');
        return $geheim === '' ? '' : Keys::link('u', $poll->id(), $geheim);
    }

    private static function zahlen(Poll $poll): array
    {
        $zettel = Vorschlaege::stimmzettel($poll);
        return [
            'teilnehmer'  => $poll->teilnehmerZahl(),
            'vorschlaege' => Vorschlaege::anzahl($poll),
            'stimmzettel' => count($zettel),
            'schwelle'    => $poll->schwelle(),
        ];
    }

    private static function aktion(Poll $poll): void
    {
        if (!Security::tokenPruefen('admin')) App::fehler(403, t('fehler.token'));
        $aktion = Util::post('aktion');
        $ziel = 'admin';

        switch ($aktion) {
            case 'einstellungen':
                self::einstellungen($poll);
                $ziel = 'einstellungen';
                break;

            case 'phase_bewertung':
                $poll->uebergangBewertung('admin');
                break;

            case 'phase_ergebnis':
                $poll->uebergangErgebnis('admin');
                break;

            case 'phase_zurueck':
                if (!$poll->zurueckZurVorschlagsphase()) {
                    Util::weiterleiten(App::ziel('', ['m' => 'zurueck_geht_nicht']));
                }
                break;

            case 'schalter':
                $feld = Util::post('feld');
                $erlaubt = ['sicht_bewertungen', 'sicht_ergebnis', 'zeige_namen',
                            'nur_vollstaendig', 'ergebnis_vorab'];
                if (in_array($feld, $erlaubt, true)) {
                    $poll->setzen([$feld => $poll->an($feld) ? 0 : 1]);
                    $poll->protokollieren('schalter', $feld . ' → ' . ($poll->an($feld) ? 'an' : 'aus'));
                }
                break;

            case 'ergebnislink_neu':
                $poll->ergebnisLinkErzeugen();
                break;

            case 'ergebnislink_weg':
                $poll->ergebnisLinkZuruecknehmen();
                break;

            case 'mischen':
                Vorschlaege::mischen($poll);
                $ziel = 'redaktion';
                break;

            case 'reihenfolge_neu':
                Vorschlaege::reihenfolgeChronologisch($poll);
                $ziel = 'redaktion';
                break;

            case 'redaktion_entfernen':
                Vorschlaege::adminStatus($poll, Util::postInt('id'), 'entfernt');
                $ziel = 'redaktion';
                break;

            case 'redaktion_zurueckholen':
                Vorschlaege::adminStatus($poll, Util::postInt('id'), 'aktiv');
                $ziel = 'redaktion';
                break;

            case 'redaktion_zusammen':
                $zielId = Util::postInt('ziel_id');
                Vorschlaege::adminStatus($poll, Util::postInt('id'), 'zusammengefuehrt', $zielId > 0 ? $zielId : null);
                $ziel = 'redaktion';
                break;

            case 'redaktion_aufnehmen':
                Vorschlaege::adminAufnahme($poll, Util::postInt('id'),
                    Util::post('wie') === 'erzwungen' ? 'erzwungen' : 'auto');
                $ziel = 'redaktion';
                break;

            case 'redaktion_notiz':
                Vorschlaege::adminNotiz($poll, Util::postInt('id'), Util::kuerzen(Util::post('notiz'), 500));
                $ziel = 'redaktion';
                break;

            case 'kommentar_weg':
                Vorschlaege::kommentarLoeschen($poll, Util::postInt('id'));
                $ziel = 'redaktion';
                break;

            case 'teilnehmer_neuer_link':
                $neu = $poll->teilnehmerNeuerSchluessel(Util::postInt('id'));
                // Kein Umweg ueber eine Weiterleitung: das neue Geheimnis darf nicht
                // in die Adresszeile und damit in Verlauf und Server-Protokoll.
                App::zeigen('admin_teilnehmer', [
                    'leute'      => $poll->teilnehmer(),
                    'neuer_name' => $neu !== null ? $neu[0] : '',
                    'neuer_link' => $neu !== null ? Keys::link('u', $poll->id(), $neu[1]) : '',
                ]);
                return;

            case 'teilnehmer_weg':
                $poll->teilnehmerEntfernen(Util::postInt('id'));
                $ziel = 'teilnehmer';
                break;

            case 'aufraeumen':
                $stand = Housekeeping::jetzt();
                Util::weiterleiten(App::ziel('', ['m' => 'aufgeraeumt', 'n' => $stand['geloescht']]));
                break;

            case 'loeschen':
                if (Util::post('sicher') !== 'LOESCHEN') {
                    Util::weiterleiten(App::ziel('einstellungen', ['m' => 'loeschen_bestaetigen']));
                }
                $id = $poll->id();
                unset($poll);
                Storage::loeschen($id);
                Util::weiterleiten(Router::url('', ['m' => 'geloescht']));
                break;
        }
        Util::weiterleiten(App::ziel($ziel === 'admin' ? '' : $ziel, ['m' => 'gespeichert']) . Util::anker());
    }

    private static function einstellungen(Poll $poll): void
    {
        $felder = [];
        $titel = Util::kuerzen(Util::post('titel'), 200);
        if ($titel !== '') $felder['titel'] = $titel;
        $felder['beschreibung'] = Util::kuerzen(Util::post('beschreibung'), (int)Config::get('max_textlaenge'));

        $maxTage = (int)Config::get('max_laufzeit_tage');
        $ev = Util::datumEinlesen(Util::post('ende_vorschlag'));
        $eb = Util::datumEinlesen(Util::post('ende_bewertung'));
        if ($ev !== null && $poll->phase() === Poll::PHASE_VORSCHLAG) $felder['ende_vorschlag'] = $ev;
        if ($eb !== null && $poll->phase() !== Poll::PHASE_ERGEBNIS) {
            $grenze = (int)$poll->v('angelegt') + $maxTage * 86400;
            $felder['ende_bewertung'] = min($eb, $grenze);
            $felder['loeschdatum'] = $felder['ende_bewertung'] + (int)Config::get('aufbewahrung_tage') * 86400;
        }

        // Vor der Bewertungsphase sind die Spielregeln noch aenderbar.
        if ($poll->phase() === Poll::PHASE_VORSCHLAG) {
            $passivAn = !Util::postAn('ohne_passiv');
            $felder['opt_passiv'] = $passivAn ? 1 : 0;
            $felder['passiv_text'] = Util::kuerzen(Util::post('passiv_text'), 500) ?: t('passiv.vorgabe');
            $felder['opt_veto'] = Util::postAn('mit_veto') ? 1 : 0;
            $felder['schwelle_prozent'] = max(0, min(100, Util::postInt('schwelle', 20)));
        }
        // Die Beteiligungsschwelle darf auch spaeter noch angepasst werden: sie
        // aendert nur die Lesart der Auswertung, nicht die abgegebenen Bewertungen.
        if (isset($_POST['quorum'])) {
            $felder['quorum_prozent'] = max(0, min(100, Util::postInt('quorum', 50)));
        }
        $poll->setzen($felder);
        if ($poll->phase() === Poll::PHASE_VORSCHLAG) {
            if ($poll->an('opt_passiv')) {
                $poll->passivAnlegen();
                $poll->db->prepare('UPDATE vorschlag SET text = ? WHERE ist_passiv = 1')
                         ->execute([(string)$poll->v('passiv_text')]);
            } else {
                $poll->passivEntfernen();
            }
        }
        $poll->protokollieren('einstellungen', '');
    }
}
