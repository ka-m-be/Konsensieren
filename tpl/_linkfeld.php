<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<?php /** Ein Link zum Kopieren. Ohne JavaScript bleibt das Feld einfach markierbar. */ ?>
<p class="linkfeld">
  <label class="versteckt" for="<?= Util::esc($id) ?>"><?= Util::esc(t('link.adresse')) ?></label>
  <input type="text" id="<?= Util::esc($id) ?>" value="<?= Util::esc($link) ?>" readonly
         data-alles-markieren spellcheck="false">
  <button type="button" class="knopf klein kopieren" data-ziel="<?= Util::esc($id) ?>"><?= Util::esc(t('link.kopieren')) ?></button>
</p>
