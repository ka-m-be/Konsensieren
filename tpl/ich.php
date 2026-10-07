<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<h2><?= Util::esc(t('ich.titel')) ?></h2>
<div class="karte">
  <p><?= Util::esc(t('ich.name_ist', ['name' => (string)$ich['name']])) ?></p>
  <p class="dazu"><?= Util::esc(t('ich.link_dazu')) ?></p>
  <?php $id = 'meinlink'; include __DIR__ . '/_linkfeld.php'; ?>
  <p class="feld schalter">
    <label><input type="checkbox" id="merken" data-link="<?= Util::esc($link) ?>"
           data-titel="<?= Util::esc((string)$poll->v('titel')) ?>">
      <?= Util::esc(t('ich.merken')) ?></label>
    <span class="dazu"><?= Util::esc(t('ich.merken_dazu')) ?></span>
  </p>
</div>

<form method="post" class="karte schmal">
  <?= Security::tokenFeld('user') ?>
  <input type="hidden" name="aktion" value="name_aendern">
  <h3><?= Util::esc(t('ich.name_aendern')) ?></h3>
  <p class="feld">
    <label for="name"><?= Util::esc(t('feld.dein_name')) ?></label>
    <input type="text" id="name" name="name" maxlength="60" value="<?= Util::esc((string)$ich['name']) ?>">
  </p>
  <p><button type="submit" class="knopf klein"><?= Util::esc(t('knopf.speichern')) ?></button></p>
</form>

<details class="hinweis">
  <summary><?= Util::esc(t('ich.datenschutz')) ?></summary>
  <p><?= Util::esc(t('ich.datenschutz_text', ['d' => Util::zeit((int)$poll->v('loeschdatum'), 'd.m.Y')])) ?></p>
</details>
