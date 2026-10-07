<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<?php
/**
 * Der Einstieg ins Beispiel. Ausdruecklich ein Formular und kein Link: Ein Link
 * wuerde von Crawlern und Link-Vorschau-Diensten verfolgt, und jeder Aufruf legte
 * eine Abstimmung an.
 * @var bool $kennwort_noetig
 */
$kennwort_noetig = $kennwort_noetig ?? ((string)Config::get('anlegen_kennwort') !== '');
?>
<?php if (Beispiel::erlaubt()): ?>
<form method="post" action="<?= Util::esc(Router::url('beispiel')) ?>" class="karte beispielkarte">
  <?= Security::tokenFeld('neu') ?>
  <h2><?= Util::esc(t('beispiel.h')) ?></h2>
  <p><?= Util::esc(t('beispiel.einfuehrung', ['n' => (int)Config::get('beispiel_tage')])) ?></p>

  <fieldset class="stadien">
    <legend><?= Util::esc(t('beispiel.stadium')) ?></legend>
    <?php foreach (Beispiel::STADIEN as $i => $stadium): ?>
      <label class="stadium">
        <input type="radio" name="stadium" value="<?= Util::esc($stadium) ?>" <?= $i === 1 ? 'checked' : '' ?>>
        <span>
          <strong><?= Util::esc(t('beispiel.wahl_' . $stadium)) ?></strong>
          <span class="dazu"><?= Util::esc(t('beispiel.wahl_' . $stadium . '_dazu')) ?></span>
        </span>
      </label>
    <?php endforeach; ?>
  </fieldset>

  <?php if ($kennwort_noetig): ?>
    <p class="feld">
      <label for="beispiel_kennwort"><?= Util::esc(t('feld.kennwort')) ?></label>
      <input type="password" id="beispiel_kennwort" name="kennwort" autocomplete="off">
    </p>
  <?php endif; ?>

  <p><button type="submit" class="knopf"><?= Util::esc(t('beispiel.knopf')) ?></button></p>
</form>
<?php endif; ?>
