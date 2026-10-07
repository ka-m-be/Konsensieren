<?php
declare(strict_types=1);
if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; }

final class Bewertungen
{
    /** @param int|null $wert 0..10 oder null fuer "unbewertet" */
    public static function speichern(Poll $p, int $teilnehmerId, int $vorschlagId,
                                     ?int $wert, bool $veto, string $grund): void
    {
        // Eine erfundene Vorschlagsnummer wuerde sonst als Fremdschluesselfehler
        // die ganze Anfrage abbrechen (HTTP 500).
        if (!Vorschlaege::existiert($p, $vorschlagId)) return;
        if ($wert !== null) $wert = max(0, min(10, $wert));
        if (!$p->an('opt_veto')) { $veto = false; $grund = ''; }
        if ($wert === null && !$veto) {
            $p->db->prepare('DELETE FROM bewertung WHERE vorschlag_id = ? AND teilnehmer_id = ?')
                  ->execute([$vorschlagId, $teilnehmerId]);
            return;
        }
        $p->db->prepare(
            'INSERT INTO bewertung (vorschlag_id, teilnehmer_id, wert, veto, veto_grund, geaendert)
             VALUES (:v, :t, :w, :veto, :grund, :zeit)
             ON CONFLICT(vorschlag_id, teilnehmer_id)
             DO UPDATE SET wert = :w, veto = :veto, veto_grund = :grund, geaendert = :zeit'
        )->execute([
            'v' => $vorschlagId, 't' => $teilnehmerId, 'w' => $wert,
            'veto' => $veto ? 1 : 0, 'grund' => Util::kuerzen($grund, 300),
            'zeit' => Util::jetzt(),
        ]);
    }

    /** @return array<int,array<string,mixed>> vorschlag_id => Zeile */
    public static function meine(Poll $p, int $teilnehmerId): array
    {
        $st = $p->db->prepare('SELECT * FROM bewertung WHERE teilnehmer_id = ?');
        $st->execute([$teilnehmerId]);
        $aus = [];
        foreach ($st->fetchAll() as $r) $aus[(int)$r['vorschlag_id']] = $r;
        return $aus;
    }

    /** @return array<int,array<int,array<string,mixed>>> vorschlag_id => teilnehmer_id => Zeile */
    public static function alle(Poll $p): array
    {
        $aus = [];
        foreach ($p->db->query('SELECT * FROM bewertung')->fetchAll() as $r) {
            $aus[(int)$r['vorschlag_id']][(int)$r['teilnehmer_id']] = $r;
        }
        return $aus;
    }
}
