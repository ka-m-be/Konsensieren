<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<?php /**
 * Beschriftung der Skala, einmal fuer den ganzen Stimmzettel und beim Scrollen
 * mitlaufend. Sie sitzt genau ueber der Spalte, in der die Felder stehen.
 */ ?>
<div class="zettelkopf">
  <span class="platzhalter" aria-hidden="true"></span>
  <span class="enden">
    <span class="links"><?= Util::esc(t('skala.links')) ?></span>
    <span class="rechts"><?= Util::esc(t('skala.rechts')) ?></span>
  </span>
</div>
