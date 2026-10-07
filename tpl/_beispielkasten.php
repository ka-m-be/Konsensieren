<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<?php
/**
 * Der Wegweiser im Beispiel: ein paar Stichpunkte, je nach Seite andere. Ein
 * gefuellter Stimmzettel allein erklaert noch nicht, worauf zu achten ist.
 *
 * Wie viele Punkte es sind, sagt die Sprachdatei: gezaehlt wird, bis ein Schluessel
 * fehlt. So kann eine Uebersetzung einen Punkt mehr oder weniger haben, ohne dass
 * hier etwas anzupassen waere.
 *
 * Dazu der Weg in die jeweils andere Rolle. Die Stichpunkte verweisen auf den
 * Verwaltungsbereich; ohne diesen Link waere das ein Versprechen, das die Seite
 * nicht halten kann - aus dem gehashten Admin-Schluessel laesst sich der Link
 * nicht zurueckrechnen. Deshalb legt Beispiel::anlegen() ihn eigens ab.
 * @var string $kasten  vorschlag | bewertung | ergebnis
 */
?>
<?php if (isset($poll) && $poll && $poll->an('ist_beispiel')): ?>
<?php $beispielAdmin = (string)$poll->v('beispiel_admin_geheim'); ?>
<details class="hinweis beispielkasten" open>
  <summary><?= Util::esc(t('beispiel.was')) ?></summary>
  <ul>
    <?php for ($i = 0; $i <= 6; $i++):
        $schluessel = 'beispiel.' . $kasten . '_' . $i;
        $text = t($schluessel);
        if ($text === $schluessel) continue;   // kein Text hinterlegt
    ?>
      <li><?= Util::esc($text) ?></li>
    <?php endfor; ?>
  </ul>
  <?php if ($rolle !== 'admin' && $beispielAdmin !== ''): ?>
    <p class="rollenwechsel">
      <a href="<?= Util::esc(Keys::link('a', $poll->id(), $beispielAdmin)) ?>">
        <?= Util::esc(t('beispiel.zur_verwaltung')) ?></a>
    </p>
  <?php elseif ($rolle === 'admin' && (string)$poll->v('admin_nutzer_geheim') !== ''): ?>
    <p class="rollenwechsel">
      <a href="<?= Util::esc(Keys::link('u', $poll->id(), (string)$poll->v('admin_nutzer_geheim'))) ?>">
        <?= Util::esc(t('beispiel.zur_teilnahme')) ?></a>
    </p>
  <?php endif; ?>
</details>
<?php endif; ?>
