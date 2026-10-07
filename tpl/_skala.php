<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<?php
/**
 * Zustimmungsskala, von 0 nach 10: links "geht gar nicht", rechts "voll dabei".
 * Diese Richtung ist die aus der Umfrageforschung empfohlene - sie wirkt der
 * Ankerverzerrung entgegen, die zum Skalenanfang zieht, waehrend die soziale
 * Erwuenschtheit zum positiven Ende zieht. Die frueher umgekehrte Anordnung war
 * eine gewollte Irritation, solange die Skala Widerstand mass; dieser Grund ist
 * mit der Umstellung entfallen.
 *
 * Die Beschriftung der Enden steht einmal ueber dem ganzen Stimmzettel
 * (_zettelkopf.php) und nicht bei jedem Vorschlag - sonst frisst sie zu viel Hoehe.
 * @var int $vid  @var ?int $wert  @var ?string $formular
 */
$fa = isset($formular) ? ' form="' . Util::esc($formular) . '"' : '';
?>
<fieldset class="skala">
  <legend class="versteckt"><?= Util::esc(t('skala.legende')) ?></legend>
  <div class="skala-zeile">
    <div class="skala-felder">
      <?php for ($i = 0; $i <= 10; $i++): ?>
        <label class="wert z<?= $i ?> <?= $wert === $i ? 'gewaehlt' : '' ?>">
          <input type="radio"<?= $fa ?> name="wert[<?= (int)$vid ?>]" value="<?= $i ?>" <?= $wert === $i ? 'checked' : '' ?>>
          <span aria-hidden="true"><?= $i ?></span>
          <span class="versteckt"><?= Util::esc(t('skala.wert' . $i)) ?></span>
        </label>
      <?php endfor; ?>
    </div>
    <label class="wert leer <?= $wert === null ? 'gewaehlt' : '' ?>"
           title="<?= Util::esc(t('skala.unbewertet')) ?>">
      <input type="radio"<?= $fa ?> name="wert[<?= (int)$vid ?>]" value="" <?= $wert === null ? 'checked' : '' ?>>
      <span aria-hidden="true">–</span>
      <span class="versteckt"><?= Util::esc(t('skala.unbewertet')) ?></span>
    </label>
  </div>
  <p class="skala-wort" data-fuer="<?= (int)$vid ?>" aria-live="polite"></p>
</fieldset>
