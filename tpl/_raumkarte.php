<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<?php
/**
 * Die Schwester des Werkzeugs: die App fuer den Sitzungsraum (Spezifikation 19).
 * Steht auf Start- und Hilfeseite wie die Beispielkarte. Die Anleitung selbst
 * liegt in der App, damit es sie nur einmal gibt.
 */
$raum = Util::raum();
?>
<?php if ($raum !== null): ?>
<section class="karte raumkarte">
  <h2><?= Util::esc(t('raum.h')) ?></h2>
  <p><?= Util::esc(t('raum.text')) ?></p>
  <p class="knopfreihe">
    <a class="knopf" href="<?= Util::esc($raum) ?>"><?= Util::esc(t('raum.oeffnen')) ?></a>
    <a class="knopf still" href="<?= Util::esc((string)Util::raum('anleitung.html')) ?>"><?= Util::esc(t('raum.anleitung')) ?></a>
  </p>
  <?php if (t('raum.sprache') !== ''): ?>
    <p class="dazu"><?= Util::esc(t('raum.sprache')) ?></p>
  <?php endif; ?>
</section>
<?php endif; ?>
