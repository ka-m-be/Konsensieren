<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<?php
/**
 * Kommentare eines Vorschlags. Beim Bewerten sind sie aufgeklappt - dort helfen
 * sie beim Urteilen; in der langen Vorschlagsliste bleiben sie zu. Gibt es noch
 * keine, steht nur das Schreibfeld da und kein leerer Aufklapper davor.
 * @var array $k  @var int $vid  @var ?bool $aufklappen
 */
$darfSchreiben = ($rolle === 'user' && $poll->phase() !== Poll::PHASE_ERGEBNIS);
$offen = !empty($k) && !empty($aufklappen);
?>
<?php if (!empty($k)): ?>
  <details class="kommentare" <?= $offen ? 'open' : '' ?>>
    <summary><?= Util::esc(t('kommentar.anzahl', ['n' => count($k)])) ?></summary>
    <?php foreach ($k as $eintrag): ?>
      <div class="kommentar">
        <p class="kopf">
          <span class="wer"><?= Util::esc((string)$eintrag['autor_name'] !== ''
                ? (string)$eintrag['autor_name'] : t('kommentar.ohne_namen')) ?></span>
          <span class="wann"><?= Util::esc(Util::zeit((int)$eintrag['angelegt'], 'd.m.')) ?></span>
          <?php if ($rolle === 'admin'): ?>
            <form method="post" class="inline">
              <?= Security::tokenFeld('admin') ?>
              <input type="hidden" name="aktion" value="kommentar_weg">
              <input type="hidden" name="id" value="<?= (int)$eintrag['id'] ?>">
              <button type="submit" class="knopf winzig still"><?= Util::esc(t('knopf.loeschen')) ?></button>
            </form>
          <?php endif; ?>
        </p>
        <div class="text"><?= Util::absaetze((string)$eintrag['text']) ?></div>
      </div>
    <?php endforeach; ?>
    <?php if ($darfSchreiben) include __DIR__ . '/_kommentarform.php'; ?>
  </details>
<?php elseif ($darfSchreiben): ?>
  <div class="kommentare"><?php include __DIR__ . '/_kommentarform.php'; ?></div>
<?php endif; ?>
