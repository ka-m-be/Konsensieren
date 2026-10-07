<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<h1><?= Util::esc(t('quelltext.titel')) ?></h1>
<div class="fliesstext">
  <p class="fuehrung"><?= Util::esc(t('quelltext.fuehrung')) ?></p>
  <p><?= Util::esc(t('quelltext.lizenz')) ?></p>
  <p><a class="knopf gross" href="<?= Util::esc(Router::url('quelltext.zip')) ?>"><?= Util::esc(t('quelltext.laden')) ?></a></p>
  <p class="dazu"><?= Util::esc(t('quelltext.inhalt')) ?>
     <?= Util::esc(t('fuss.fassung', ['v' => SK_FASSUNG])) ?>.</p>
  <h2><?= Util::esc(t('quelltext.h_technik')) ?></h2>
  <p><?= Util::esc(t('quelltext.technik')) ?></p>
</div>
