<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<?php /** Das Schreibfeld, immer hinter einem eigenen Aufklapper. @var int $vid */ ?>
<details class="schreiben">
  <summary><?= Util::esc(t('kommentar.neuer')) ?></summary>
  <form method="post" class="kommentarform">
    <?= Security::tokenFeld('user') ?>
    <input type="hidden" name="aktion" value="kommentar">
    <input type="hidden" name="id" value="<?= (int)$vid ?>">
    <input type="hidden" name="anker" value="v<?= (int)$vid ?>">
    <input type="hidden" name="zurueck" value="<?= Util::esc(Router::segmente()[2] ?? '') ?>">
    <p class="feld">
      <label class="versteckt" for="kt<?= (int)$vid ?>"><?= Util::esc(t('kommentar.neuer')) ?></label>
      <textarea id="kt<?= (int)$vid ?>" name="text" rows="2"></textarea>
    </p>
    <p class="feld schalter">
      <label><input type="checkbox" name="mit_namen" checked> <?= Util::esc(t('feld.mit_namen')) ?></label>
      <button type="submit" class="knopf klein"><?= Util::esc(t('kommentar.absenden')) ?></button>
    </p>
  </form>
</details>
