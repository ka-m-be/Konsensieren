<?php
declare(strict_types=1);
if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; }

/** Eine Abstimmung samt Teilnehmenden, Phasenlogik und Einstellungen. */
final class Poll
{
    public const PHASE_VORSCHLAG = 'vorschlag';
    public const PHASE_BEWERTUNG = 'bewertung';
    public const PHASE_ERGEBNIS  = 'ergebnis';

    public PDO $db;
    /** @var array<string,mixed> */
    public array $d;

    private function __construct(PDO $db, array $daten)
    {
        $this->db = $db;
        $this->d  = $daten;
    }

    /* ------------------------------------------------------------ Anlegen */

    /**
     * @return array{0:Poll,1:string,2:string} Abstimmung, Admin-Geheimnis, Einladungs-Geheimnis
     */
    public static function anlegen(string $titel, string $beschreibung, int $endeVorschlag, int $endeBewertung, array $opt): array
    {
        do {
            $id = Keys::pollId();
        } while (Storage::existiert($id));

        $adminGeheim = Keys::geheimnis();
        $einladung   = Keys::geheimnis();
        $jetzt       = Util::jetzt();

        $db = Storage::oeffnen($id, true);
        $st = $db->prepare(
            'INSERT INTO poll (id, titel, beschreibung, phase, ende_vorschlag, ende_bewertung,
                loeschdatum, angelegt, admin_hash, einladung_geheim, opt_passiv, passiv_text,
                opt_veto, schwelle_prozent, quorum_prozent, sprache)
             VALUES (:id, :titel, :besch, :phase, :ev, :eb, :loesch, :ang, :ah, :eg, :passiv, :ptext,
                :veto, :schwelle, :quorum, :sprache)'
        );
        $st->execute([
            'id' => $id, 'titel' => $titel, 'besch' => $beschreibung,
            'phase' => self::PHASE_VORSCHLAG,
            'ev' => $endeVorschlag, 'eb' => $endeBewertung,
            'loesch' => $endeBewertung + (int)Config::get('aufbewahrung_tage') * 86400,
            'ang' => $jetzt,
            'ah' => Keys::hash($adminGeheim), 'eg' => $einladung,
            'passiv' => $opt['passiv'] ? 1 : 0,
            'ptext' => $opt['passiv_text'],
            'veto' => $opt['veto'] ? 1 : 0,
            'schwelle' => $opt['schwelle'],
            'quorum' => $opt['quorum'] ?? 50,
            'sprache' => I18n::sprache(),
        ]);

        $poll = self::ausDb($db, $id);
        $poll->protokollieren('angelegt', $titel);
        if ($opt['passiv']) $poll->passivAnlegen();
        return [$poll, $adminGeheim, $einladung];
    }

    public static function laden(string $pollId): ?Poll
    {
        $db = Storage::oeffnen($pollId);
        if ($db === null) return null;
        try {
            return self::ausDb($db, $pollId);
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function ausDb(PDO $db, string $id): Poll
    {
        $st = $db->prepare('SELECT * FROM poll WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) throw new RuntimeException('Abstimmung nicht lesbar');
        return new Poll($db, $row);
    }

    /* ------------------------------------------------------ Werte, Setzen */

    /** @return mixed */
    public function v(string $feld)
    {
        return $this->d[$feld] ?? null;
    }

    public function an(string $feld): bool
    {
        return (int)($this->d[$feld] ?? 0) === 1;
    }

    public function id(): string
    {
        return (string)$this->d['id'];
    }

    /** @param array<string,mixed> $felder */
    public function setzen(array $felder): void
    {
        $erlaubt = ['titel','beschreibung','ende_vorschlag','ende_bewertung','loeschdatum',
            'opt_passiv','passiv_text','opt_veto','schwelle_prozent','schwelle_basis','quorum_prozent',
            'sicht_bewertungen','sicht_ergebnis','zeige_namen','nur_vollstaendig',
            'reihenfolge','ergebnis_vorab','sprache','phase','ergebnis_hash','ergebnis_geheim',
            'admin_nutzer_geheim','ist_beispiel','beispiel_admin_geheim'];
        $teile = [];
        $werte = [];
        foreach ($felder as $k => $v) {
            if (!in_array($k, $erlaubt, true)) continue;
            $teile[] = "$k = ?";
            $werte[] = $v;
            $this->d[$k] = $v;
        }
        if (!$teile) return;
        $werte[] = $this->id();
        $this->db->prepare('UPDATE poll SET ' . implode(', ', $teile) . ' WHERE id = ?')->execute($werte);
    }

    /* ---------------------------------------------------------- Protokoll */

    public function protokollieren(string $art, string $details = ''): void
    {
        $this->db->prepare('INSERT INTO protokoll (zeit, art, details) VALUES (?, ?, ?)')
                 ->execute([Util::jetzt(), $art, $details]);
    }

    /** @return array<int,array<string,mixed>> */
    public function protokoll(int $grenze = 100): array
    {
        return $this->db->query('SELECT * FROM protokoll ORDER BY id DESC LIMIT ' . $grenze)->fetchAll();
    }

    /* -------------------------------------------------------------- Phase */

    public function phase(): string
    {
        return (string)$this->d['phase'];
    }

    /**
     * Faule Phasenlogik: Es gibt keinen Cron, also wird bei jedem Aufruf geprueft,
     * ob ein Termin verstrichen ist. Der Uebergang laeuft in einer Transaktion,
     * damit er bei gleichzeitigen Zugriffen nur einmal stattfindet.
     */
    public function phaseNachziehen(): void
    {
        $jetzt = Util::jetzt();
        if ($this->phase() === self::PHASE_VORSCHLAG && $jetzt >= (int)$this->d['ende_vorschlag']) {
            $this->uebergangBewertung('termin');
        }
        if ($this->phase() === self::PHASE_BEWERTUNG && $jetzt >= (int)$this->d['ende_bewertung']) {
            $this->uebergangErgebnis('termin');
        }
    }

    public function uebergangBewertung(string $anlass): void
    {
        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare("UPDATE poll SET phase = ?, schwelle_basis = ?
                                      WHERE id = ? AND phase = ?");
            $basis = $this->teilnehmerZahl();
            $st->execute([self::PHASE_BEWERTUNG, $basis, $this->id(), self::PHASE_VORSCHLAG]);
            $geaendert = $st->rowCount() > 0;
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        if ($geaendert) {
            $this->d['phase'] = self::PHASE_BEWERTUNG;
            $this->d['schwelle_basis'] = $basis;
            if ($anlass !== 'termin') {
                $this->setzen(['ende_vorschlag' => Util::jetzt()]);
            }
            $this->protokollieren('phase.bewertung', $anlass);
        }
    }

    public function uebergangErgebnis(string $anlass): void
    {
        $st = $this->db->prepare('UPDATE poll SET phase = ? WHERE id = ? AND phase = ?');
        $st->execute([self::PHASE_ERGEBNIS, $this->id(), self::PHASE_BEWERTUNG]);
        if ($st->rowCount() > 0) {
            $this->d['phase'] = self::PHASE_ERGEBNIS;
            if ($anlass !== 'termin') {
                $this->setzen(['ende_bewertung' => Util::jetzt()]);
            }
            $this->protokollieren('phase.ergebnis', $anlass);
        }
    }

    /** Wurde schon bewertet? Dann ist der Weg zurueck versperrt. */
    public function schonBewertet(): bool
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM bewertung')->fetchColumn() > 0;
    }

    public function kannZurueck(): bool
    {
        return $this->phase() === self::PHASE_BEWERTUNG && !$this->schonBewertet();
    }

    /** Zurueck in die Vorschlagsphase, solange noch niemand bewertet hat. */
    public function zurueckZurVorschlagsphase(): bool
    {
        if (!$this->kannZurueck()) return false;
        $this->setzen([
            'phase' => self::PHASE_VORSCHLAG,
            'schwelle_basis' => null,
            'ende_vorschlag' => max(Util::jetzt() + 86400, (int)$this->d['ende_vorschlag']),
        ]);
        $this->protokollieren('phase.zurueck', '');
        return true;
    }

    public function endeDerPhase(): ?int
    {
        if ($this->phase() === self::PHASE_VORSCHLAG) return (int)$this->d['ende_vorschlag'];
        if ($this->phase() === self::PHASE_BEWERTUNG) return (int)$this->d['ende_bewertung'];
        return (int)$this->d['loeschdatum'];
    }

    /* -------------------------------------------------------- Teilnehmende */

    public function teilnehmerZahl(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM teilnehmer')->fetchColumn();
    }

    /** @return array{0:int,1:string} id und Geheimnis */
    public function teilnehmerAnlegen(string $name, bool $istAdmin = false): array
    {
        $geheim = Keys::geheimnis();
        $jetzt  = Util::jetzt();
        $this->db->prepare('INSERT INTO teilnehmer (name, key_hash, ist_admin, angelegt, gesehen)
                            VALUES (?, ?, ?, ?, ?)')
                 ->execute([$name, Keys::hash($geheim), $istAdmin ? 1 : 0, $jetzt, $jetzt]);
        $id = (int)$this->db->lastInsertId();
        $this->protokollieren('teilnehmer.neu', $name);
        return [$id, $geheim];
    }

    /** @return array<string,mixed>|null */
    public function teilnehmerPerHash(string $hash): ?array
    {
        $st = $this->db->prepare('SELECT * FROM teilnehmer WHERE key_hash = ?');
        $st->execute([$hash]);
        $row = $st->fetch();
        if (!$row) return null;
        $this->db->prepare('UPDATE teilnehmer SET gesehen = ? WHERE id = ?')
                 ->execute([Util::jetzt(), $row['id']]);
        return $row;
    }

    /** @return array<int,array<string,mixed>> */
    public function teilnehmer(): array
    {
        return $this->db->query('SELECT * FROM teilnehmer ORDER BY LOWER(name)')->fetchAll();
    }

    /**
     * Stellt einer Person einen neuen Zugang aus, weil sie ihren Link verloren hat.
     * Der bisherige wird damit ungueltig - anders geht es nicht, denn gespeichert
     * ist nur der Hash, und der laesst sich nicht zurueckrechnen.
     * @return array{0:string,1:string}|null Name und neues Geheimnis
     */
    public function teilnehmerNeuerSchluessel(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM teilnehmer WHERE id = ?');
        $st->execute([$id]);
        $zeile = $st->fetch();
        if (!$zeile) return null;

        $geheim = Keys::geheimnis();
        $this->db->prepare('UPDATE teilnehmer SET key_hash = ? WHERE id = ?')
                 ->execute([Keys::hash($geheim), $id]);
        if ((int)$zeile['ist_admin'] === 1) {
            $this->setzen(['admin_nutzer_geheim' => $geheim]);
        }
        $this->protokollieren('teilnehmer.neuer_schluessel', (string)$zeile['name']);
        return [(string)$zeile['name'], $geheim];
    }

    public function teilnehmerEntfernen(int $id): void
    {
        $st = $this->db->prepare('SELECT name FROM teilnehmer WHERE id = ?');
        $st->execute([$id]);
        $name = (string)$st->fetchColumn();
        $this->db->prepare('DELETE FROM teilnehmer WHERE id = ?')->execute([$id]);
        $this->protokollieren('teilnehmer.entfernt', $name);
    }

    public function nameFrei(string $name): bool
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM teilnehmer WHERE LOWER(name) = LOWER(?)');
        $st->execute([$name]);
        return (int)$st->fetchColumn() === 0;
    }

    /* --------------------------------------------------------- Schluessel */

    public function istAdminSchluessel(string $geheim): bool
    {
        return Keys::gleich((string)$this->d['admin_hash'], Keys::hash($geheim));
    }

    public function istEinladung(string $geheim): bool
    {
        return Keys::gleich((string)$this->d['einladung_geheim'], strtoupper($geheim));
    }

    public function istErgebnisSchluessel(string $geheim): bool
    {
        $h = (string)($this->d['ergebnis_hash'] ?? '');
        return $h !== '' && Keys::gleich($h, Keys::hash($geheim));
    }

    public function ergebnisLinkErzeugen(): string
    {
        $geheim = Keys::geheimnis();
        $this->setzen(['ergebnis_hash' => Keys::hash($geheim), 'ergebnis_geheim' => $geheim]);
        $this->protokollieren('ergebnislink.neu', '');
        return $geheim;
    }

    public function ergebnisLinkZuruecknehmen(): void
    {
        $this->setzen(['ergebnis_hash' => null, 'ergebnis_geheim' => null]);
        $this->protokollieren('ergebnislink.weg', '');
    }

    /* ------------------------------------------------------- Passivloesung */

    public function passivAnlegen(): void
    {
        $vorhanden = (int)$this->db->query('SELECT COUNT(*) FROM vorschlag WHERE ist_passiv = 1')->fetchColumn();
        if ($vorhanden > 0) return;
        $this->db->prepare('INSERT INTO vorschlag (titel, text, ist_passiv, angelegt, autor_name)
                            VALUES (?, ?, 1, ?, ?)')
                 ->execute([t('passiv.titel'), (string)$this->d['passiv_text'], Util::jetzt(), '']);
    }

    public function passivEntfernen(): void
    {
        $this->db->exec('DELETE FROM vorschlag WHERE ist_passiv = 1');
    }

    /** Wieviele Unterstuetzungen braucht ein Vorschlag? */
    public function schwelle(): int
    {
        $prozent = (int)$this->d['schwelle_prozent'];
        if ($prozent <= 0) return 0;
        $basis = $this->d['schwelle_basis'] !== null
            ? (int)$this->d['schwelle_basis']
            : $this->teilnehmerZahl();
        return max(1, (int)ceil($basis * $prozent / 100));
    }

    public function abgelaufen(): bool
    {
        return Util::jetzt() >= (int)$this->d['loeschdatum'];
    }
}
