<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<?php /** Kleines Balkenbild ueber die Werte 0..10. @var array $verteilung */ ?>
<?php /* Ueber jedem Balken die Anzahl, darunter der Wert, darueber die Summe: So
         laesst sich das Bild ohne Mauszeiger lesen, auf dem Handy und beim
         Abschreiben. Gebaut wie in der App (Spezifikation 19). */ ?>
<p class="verteilung-summe"><?= Util::esc(t('ergebnis.abgegeben', ['n' => array_sum($verteilung)])) ?></p>
<div class="verteilung" role="img"
     aria-label="<?= Util::esc(t('ergebnis.verteilung_alt', ['w' => implode(', ', array_map(
        static fn($i, $n) => $i . ': ' . $n, array_keys($verteilung), $verteilung))])) ?>">
  <?php $hoechster = max(1, max($verteilung)); ?>
  <?php /* Gleiche Leserichtung wie beim Bewerten: 0 links, 10 rechts. */ ?>
  <?php foreach ($verteilung as $i => $n): ?>
    <?php /* Hoehe in Zehnteln als Klasse - Inline-Styles verbietet unsere CSP. */ ?>
    <span class="balken z<?= $i ?> h<?= (int)round($n / $hoechster * 10) ?>"><b><?= (int)$n ?></b><i></i><small><?= $i ?></small></span>
  <?php endforeach; ?>
</div>
