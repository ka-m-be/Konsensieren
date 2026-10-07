<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<?php
/**
 * Eine der eigenen Markdown-Dateien im Hauptverzeichnis, gerendert: das
 * Impressum und der Artikel (Spezifikation 20). $html kommt aus Markdown::html(),
 * das jeden Text escaped; deshalb steht es hier ohne Util::esc().
 * @var string $html
 */
?>
<article class="fliesstext markdown"><?= $html ?></article>
<p><a class="knopf" href="<?= Util::esc(Router::url('')) ?>"><?= Util::esc(t('hilfe.zurueck')) ?></a></p>
