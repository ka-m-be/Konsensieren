<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<h1><?= (int)$code ?></h1>
<p class="fuehrung"><?= Util::esc($text) ?></p>
<p><a class="knopf" href="<?= Util::esc(Router::url('')) ?>"><?= Util::esc(t('fehler.zurueck')) ?></a></p>
