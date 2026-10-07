<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<?php
/**
 * Das Menue rechts in der Kopfzeile (Spezifikation 19): ein details-Element, das
 * ohne JavaScript mit dem Stapel-Knopf auf- und zugeht; assets/app.js schliesst es
 * zusaetzlich bei einem Klick daneben und mit Escape. Die App hat dasselbe Menue
 * mit ihren eigenen Eintraegen, aus demselben CSS-Block.
 *
 * Der Profi-Modus gilt der Person, die anlegt und verwaltet (Spezifikation 20);
 * Teilnehmende und der oeffentliche Ergebnis-Link bekommen den Eintrag nicht.
 * Die gewaehlte Sprache steht da, ohne Link: Sie ist ein Zustand, keine Aktion.
 */
$aufHilfe = (Router::segmente()[0] ?? '') === 'hilfe';
$profiEintrag = !isset($poll) || !$poll || $rolle === 'admin';
$sprachnamen = ['de' => 'Deutsch', 'en' => 'English'];
?>
<details class="menue">
  <summary class="menue-knopf"><span class="stapel" aria-hidden="true"></span><span class="versteckt"><?= Util::esc(t('nav.menue')) ?></span></summary>
  <nav class="menue-liste" aria-label="<?= Util::esc(t('nav.menue')) ?>">
    <a href="<?= Util::esc(Router::url('hilfe')) ?>"<?= $aufHilfe ? ' aria-current="page"' : '' ?>><?= Util::esc(t('nav.hilfe')) ?></a>
    <?php if ($profiEintrag): ?>
      <a href="?profi=<?= Util::profi() ? 'aus' : 'an' ?>" rel="nofollow"><?= Util::esc(t(Util::profi() ? 'profi.ausschalten' : 'profi.einschalten')) ?></a>
      <?php erkl('profi.dazu'); ?>
    <?php endif; ?>
    <div class="menue-sprache">
      <span class="gruppe"><?= Util::esc(t('nav.sprache')) ?></span>
      <span class="sprachen">
        <?php foreach (I18n::SPRACHEN as $sp): ?>
          <?php if ($sp === I18n::sprache()): ?>
            <span class="aktiv" lang="<?= $sp ?>" aria-current="true"><?= Util::esc($sprachnamen[$sp] ?? $sp) ?></span>
          <?php else: ?>
            <a href="?lang=<?= $sp ?>" hreflang="<?= $sp ?>" lang="<?= $sp ?>"><?= Util::esc($sprachnamen[$sp] ?? $sp) ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </span>
    </div>
  </nav>
</details>
