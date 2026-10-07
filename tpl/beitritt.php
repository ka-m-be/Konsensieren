<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<h1><?= Util::esc(t('beitritt.titel')) ?></h1>
<p class="fuehrung"><?= Util::esc(t('beitritt.fuehrung')) ?></p>

<form method="post" class="karte schmal">
  <?= Security::tokenFeld('beitritt') ?>
  <p class="feld">
    <label for="name"><?= Util::esc(t('feld.dein_name')) ?></label>
    <input type="text" id="name" name="name" required maxlength="60" autofocus
           value="<?= Util::esc($name ?? '') ?>">
    <span class="dazu"><?= Util::esc(t('beitritt.name_dazu')) ?></span>
  </p>
  <p><button type="submit" class="knopf gross"><?= Util::esc(t('beitritt.mitmachen')) ?></button></p>
</form>

<details class="hinweis">
  <summary><?= Util::esc(t('hinweis.was_passiert')) ?></summary>
  <p><?= Util::esc(t('beitritt.erklaerung')) ?></p>
  <p><?= Util::esc(t('beitritt.anonym_hinweis')) ?></p>
</details>
