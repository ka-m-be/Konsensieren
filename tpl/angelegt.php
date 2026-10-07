<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<h1><?= Util::esc(t('angelegt.titel')) ?></h1>
<p class="fuehrung warnung"><?= Util::esc(t('angelegt.warnung')) ?></p>

<div class="karte">
  <h2><?= Util::esc(t('angelegt.admin')) ?></h2>
  <p class="dazu"><?= Util::esc(t('angelegt.admin_dazu')) ?></p>
  <?php $link = $admin_link; $id = 'l1'; include __DIR__ . '/_linkfeld.php'; ?>

  <h2><?= Util::esc(t('angelegt.einladung')) ?></h2>
  <p class="dazu"><?= Util::esc(t('angelegt.einladung_dazu')) ?></p>
  <?php $link = $einladung_link; $id = 'l2'; include __DIR__ . '/_linkfeld.php'; ?>

  <h2><?= Util::esc(t('angelegt.ich')) ?></h2>
  <p class="dazu"><?= Util::esc(t('angelegt.ich_dazu')) ?></p>
  <?php $link = $nutzer_link; $id = 'l3'; include __DIR__ . '/_linkfeld.php'; ?>
</div>

<p><a class="knopf" href="<?= Util::esc($admin_link) ?>"><?= Util::esc(t('angelegt.weiter')) ?></a></p>
