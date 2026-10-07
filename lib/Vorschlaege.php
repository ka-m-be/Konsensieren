<?php
declare(strict_types=1);
if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; }

/** Vorschlaege, ihre Modifikationsbezuege, Unterstuetzungen und Kommentare. */
final class Vorschlaege
{
    /* ------------------------------------------------------------ Anlegen */

    /** @param int[] $elternIds erster Eintrag ist der primaere Bezug */
    public static function anlegen(Poll $p, array $teilnehmer, string $titel, string $text,
                                   array $elternIds, bool $mitNamen): int
    {
        $jetzt = Util::jetzt();
        $p->db->prepare('INSERT INTO vorschlag (autor_id, autor_name, titel, text, angelegt)
                         VALUES (?, ?, ?, ?, ?)')
              ->execute([
                  (int)$teilnehmer['id'],
                  $mitNamen ? (string)$teilnehmer['name'] : '',
                  $titel, $text, $jetzt,
              ]);
        $id = (int)$p->db->lastInsertId();

        $erst = true;
        foreach ($elternIds as $eltern) {
            $eltern = (int)$eltern;
            if ($eltern <= 0 || $eltern === $id) continue;
            $p->db->prepare('INSERT OR IGNORE INTO bezug (kind_id, eltern_id, primaer) VALUES (?, ?, ?)')
                  ->execute([$id, $eltern, $erst ? 1 : 0]);
            $erst = false;
        }
        // Wer etwas vorschlaegt, unterstuetzt es selbst.
        $p->db->prepare('INSERT OR IGNORE INTO unterstuetzung (vorschlag_id, teilnehmer_id, angelegt)
                         VALUES (?, ?, ?)')->execute([$id, (int)$teilnehmer['id'], $jetzt]);
        $p->protokollieren('vorschlag.neu', $titel);
        return $id;
    }

    public static function bearbeiten(Poll $p, int $id, string $titel, string $text): void
    {
        $p->db->prepare('UPDATE vorschlag SET titel = ?, text = ?, geaendert = ?
                         WHERE id = ? AND ist_passiv = 0')
              ->execute([$titel, $text, Util::jetzt(), $id]);
    }

    public static function zurueckziehen(Poll $p, int $id): void
    {
        $p->db->prepare("UPDATE vorschlag SET status = 'zurueckgezogen' WHERE id = ? AND ist_passiv = 0")
              ->execute([$id]);
        $p->protokollieren('vorschlag.zurueck', (string)$id);
    }

    public static function existiert(Poll $p, int $id): bool
    {
        if ($id <= 0) return false;
        $st = $p->db->prepare('SELECT 1 FROM vorschlag WHERE id = ?');
        $st->execute([$id]);
        return (bool)$st->fetchColumn();
    }

    public static function einzeln(Poll $p, int $id): ?array
    {
        $st = $p->db->prepare('SELECT * FROM vorschlag WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /* -------------------------------------------------------------- Lesen */

    /**
     * Alle Vorschlaege mit Zusatzangaben, in der geltenden Reihenfolge und mit Tiefe.
     * @return array<int,array<string,mixed>>
     */
    public static function geordnet(Poll $p): array
    {
        $rows = $p->db->query(
            'SELECT v.*,
                    (SELECT COUNT(*) FROM unterstuetzung u WHERE u.vorschlag_id = v.id) AS unterstuetzer,
                    (SELECT COUNT(*) FROM kommentar k WHERE k.vorschlag_id = v.id) AS kommentare
             FROM vorschlag v'
        )->fetchAll();

        $bezuege = $p->db->query('SELECT * FROM bezug')->fetchAll();
        $liste = [];
        foreach ($rows as $r) {
            $r['id'] = (int)$r['id'];
            $r['eltern'] = [];
            $r['primaer'] = null;
            $r['kinder'] = [];
            $liste[$r['id']] = $r;
        }
        foreach ($bezuege as $b) {
            $kind = (int)$b['kind_id'];
            $eltern = (int)$b['eltern_id'];
            if (!isset($liste[$kind], $liste[$eltern])) continue;
            $liste[$kind]['eltern'][] = $eltern;
            if ((int)$b['primaer'] === 1) $liste[$kind]['primaer'] = $eltern;
        }
        foreach ($liste as $id => $r) {
            $prim = $r['primaer'];
            if ($prim !== null && isset($liste[$prim])) {
                $liste[$prim]['kinder'][] = $id;
            }
        }

        // Wurzeln bestimmen: alles ohne primaeren Bezug, ausser der Passivloesung.
        $wurzeln = [];
        foreach ($liste as $id => $r) {
            if ((int)$r['ist_passiv'] === 1) continue;
            if ($r['primaer'] === null || !isset($liste[$r['primaer']])) $wurzeln[] = $id;
        }

        // Sortierschluessel je Strang
        $strangInfo = [];
        foreach ($wurzeln as $w) {
            $mitglieder = self::strang($liste, $w);
            $neuestes = 0;
            foreach ($mitglieder as $m) $neuestes = max($neuestes, (int)$liste[$m]['angelegt']);
            $strangInfo[$w] = [
                'neuestes' => $neuestes,
                'mischidx' => $liste[$w]['mischidx'],
                'angelegt' => (int)$liste[$w]['angelegt'],
            ];
        }

        if ($p->v('reihenfolge') === 'gemischt') {
            usort($wurzeln, static function ($a, $b) use ($strangInfo) {
                $ia = $strangInfo[$a]['mischidx'];
                $ib = $strangInfo[$b]['mischidx'];
                if ($ia === null && $ib === null) return $strangInfo[$a]['angelegt'] <=> $strangInfo[$b]['angelegt'];
                if ($ia === null) return 1;   // spaeter dazugekommene hinten anhaengen
                if ($ib === null) return -1;
                return (int)$ia <=> (int)$ib;
            });
        } else {
            usort($wurzeln, static function ($a, $b) use ($strangInfo) {
                return $strangInfo[$b]['neuestes'] <=> $strangInfo[$a]['neuestes'];
            });
        }

        $aus = [];
        foreach ($wurzeln as $w) {
            self::einhaengen($liste, $w, 0, $aus);
        }
        // Passivloesung immer ans Ende
        foreach ($liste as $r) {
            if ((int)$r['ist_passiv'] === 1) {
                $r['tiefe'] = 0;
                $aus[] = $r;
            }
        }
        return $aus;
    }

    /** @return int[] */
    private static function strang(array $liste, int $wurzel): array
    {
        $out = [$wurzel];
        foreach ($liste[$wurzel]['kinder'] as $k) {
            $out = array_merge($out, self::strang($liste, $k));
        }
        return $out;
    }

    private static function einhaengen(array $liste, int $id, int $tiefe, array &$aus): void
    {
        $r = $liste[$id];
        $kinder = $r['kinder'];
        usort($kinder, static fn($a, $b) => (int)$liste[$a]['angelegt'] <=> (int)$liste[$b]['angelegt']);
        unset($r['kinder']);
        $r['tiefe'] = $tiefe;
        $aus[] = $r;
        foreach ($kinder as $k) {
            self::einhaengen($liste, $k, min($tiefe + 1, 4), $aus);
        }
    }

    /** Steht dieser Vorschlag auf dem Stimmzettel? */
    public static function aufStimmzettel(Poll $p, array $v): bool
    {
        if ((int)$v['ist_passiv'] === 1) return $p->an('opt_passiv');
        if ($v['status'] !== 'aktiv') return false;
        if ($v['aufnahme'] === 'erzwungen') return true;
        $schwelle = $p->schwelle();
        if ($schwelle <= 0) return true;
        return (int)($v['unterstuetzer'] ?? 0) >= $schwelle;
    }

    /** @return array<int,array<string,mixed>> */
    public static function stimmzettel(Poll $p): array
    {
        $aus = [];
        foreach (self::geordnet($p) as $v) {
            if (self::aufStimmzettel($p, $v)) $aus[] = $v;
        }
        return $aus;
    }

    public static function anzahl(Poll $p): int
    {
        return (int)$p->db->query('SELECT COUNT(*) FROM vorschlag WHERE ist_passiv = 0')->fetchColumn();
    }

    /* ------------------------------------------------------ Unterstuetzung */

    public static function unterstuetzen(Poll $p, int $vorschlagId, int $teilnehmerId, bool $ja): void
    {
        // Ohne diese Pruefung wuerde ein Fremdschluesselfehler die Anfrage mit 500
        // beenden, wenn jemand eine erfundene Vorschlagsnummer schickt.
        if (!self::existiert($p, $vorschlagId)) return;
        if ($ja) {
            $p->db->prepare('INSERT OR IGNORE INTO unterstuetzung (vorschlag_id, teilnehmer_id, angelegt)
                             VALUES (?, ?, ?)')->execute([$vorschlagId, $teilnehmerId, Util::jetzt()]);
        } else {
            $p->db->prepare('DELETE FROM unterstuetzung WHERE vorschlag_id = ? AND teilnehmer_id = ?')
                  ->execute([$vorschlagId, $teilnehmerId]);
        }
    }

    /** @return int[] Vorschlags-IDs, die diese Person unterstuetzt */
    public static function meineUnterstuetzungen(Poll $p, int $teilnehmerId): array
    {
        $st = $p->db->prepare('SELECT vorschlag_id FROM unterstuetzung WHERE teilnehmer_id = ?');
        $st->execute([$teilnehmerId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /* --------------------------------------------------------- Kommentare */

    public static function kommentieren(Poll $p, int $vorschlagId, array $teilnehmer, string $text, bool $mitNamen): void
    {
        if (!self::existiert($p, $vorschlagId)) return;
        $p->db->prepare('INSERT INTO kommentar (vorschlag_id, teilnehmer_id, autor_name, text, angelegt)
                         VALUES (?, ?, ?, ?, ?)')
              ->execute([$vorschlagId, (int)$teilnehmer['id'],
                         $mitNamen ? (string)$teilnehmer['name'] : '', $text, Util::jetzt()]);
    }

    /** @return array<int,array<int,array<string,mixed>>> nach Vorschlag gruppiert */
    public static function kommentareAlle(Poll $p): array
    {
        $rows = $p->db->query('SELECT * FROM kommentar ORDER BY id ASC')->fetchAll();
        $aus = [];
        foreach ($rows as $r) {
            $aus[(int)$r['vorschlag_id']][] = $r;
        }
        return $aus;
    }

    public static function kommentarLoeschen(Poll $p, int $id): void
    {
        $p->db->prepare('DELETE FROM kommentar WHERE id = ?')->execute([$id]);
    }

    /* ---------------------------------------------------------- Redaktion */

    public static function adminStatus(Poll $p, int $id, string $status, ?int $zielId = null): void
    {
        $p->db->prepare('UPDATE vorschlag SET status = ?, ziel_id = ? WHERE id = ? AND ist_passiv = 0')
              ->execute([$status, $zielId, $id]);
        $p->protokollieren('redaktion.status', $id . ' → ' . $status . ($zielId ? ' (' . $zielId . ')' : ''));
    }

    public static function adminAufnahme(Poll $p, int $id, string $aufnahme): void
    {
        $p->db->prepare('UPDATE vorschlag SET aufnahme = ? WHERE id = ?')->execute([$aufnahme, $id]);
        $p->protokollieren('redaktion.aufnahme', $id . ' → ' . $aufnahme);
    }

    public static function adminNotiz(Poll $p, int $id, string $notiz): void
    {
        $p->db->prepare('UPDATE vorschlag SET redaktionsnotiz = ? WHERE id = ?')->execute([$notiz, $id]);
        $p->protokollieren('redaktion.notiz', (string)$id);
    }

    /** Wuerfel-Werkzeug: feste Zufallsreihenfolge der Straenge. */
    public static function mischen(Poll $p): void
    {
        $wurzeln = [];
        foreach (self::geordnet($p) as $v) {
            if ((int)$v['tiefe'] === 0 && (int)$v['ist_passiv'] === 0) $wurzeln[] = (int)$v['id'];
        }
        shuffle($wurzeln);
        $p->db->exec('UPDATE vorschlag SET mischidx = NULL');
        $st = $p->db->prepare('UPDATE vorschlag SET mischidx = ? WHERE id = ?');
        foreach ($wurzeln as $i => $id) {
            $st->execute([$i, $id]);
        }
        $p->setzen(['reihenfolge' => 'gemischt']);
        $p->protokollieren('reihenfolge.gemischt', (string)count($wurzeln));
    }

    public static function reihenfolgeChronologisch(Poll $p): void
    {
        $p->setzen(['reihenfolge' => 'neu']);
        $p->protokollieren('reihenfolge.neu', '');
    }
}
