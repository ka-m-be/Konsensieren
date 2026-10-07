<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<h1><?= Util::esc(t('hilfe.titel')) ?></h1>
<div class="fliesstext">
  <p class="fuehrung"><?= Util::esc(t('hilfe.kern')) ?></p>
  <?php /* Der Artikel oben und nicht erst unter "Zum Weiterlesen": Wer hierher kommt,
           will meist mehr wissen als die Kurzfassung (0.3.7). */ ?>
  <aside class="karte artikelkarte">
    <p><?= Util::esc(t('hilfe.artikel_oben')) ?></p>
    <p><a class="knopf still" href="<?= Util::esc(Router::url('artikel')) ?>"><?= Util::esc(t('hilfe.artikel_knopf')) ?></a></p>
  </aside>

  <h2><?= Util::esc(t('hilfe.h_idee')) ?></h2>
  <p><?= Util::esc(t('hilfe.idee1')) ?></p>
  <p><?= Util::esc(t('hilfe.idee2')) ?></p>

  <h2><?= Util::esc(t('hilfe.h_ablauf')) ?></h2>
  <ol>
    <li><strong><?= Util::esc(t('phase.vorschlag')) ?>.</strong> <?= Util::esc(t('hilfe.ablauf1')) ?></li>
    <li><strong><?= Util::esc(t('phase.bewertung')) ?>.</strong> <?= Util::esc(t('hilfe.ablauf2')) ?></li>
    <li><strong><?= Util::esc(t('phase.ergebnis')) ?>.</strong> <?= Util::esc(t('hilfe.ablauf3')) ?></li>
  </ol>

  <h2><?= Util::esc(t('hilfe.h_skala')) ?></h2>
  <p><?= Util::esc(t('hilfe.skala1')) ?></p>
  <?php /* Gleiche Leserichtung wie beim Bewerten: 0 links, 10 rechts. */ ?>
  <div class="skalabeispiel">
    <?php for ($i = 0; $i <= 10; $i++): ?>
      <span class="wert z<?= $i ?>"><?= $i ?></span>
    <?php endfor; ?>
  </div>
  <p class="beispiel-enden">
    <span class="links"><?= Util::esc(t('skala.links')) ?></span>
    <span class="rechts"><?= Util::esc(t('skala.rechts')) ?></span>
  </p>
  <p><?= Util::esc(t('hilfe.skala2')) ?></p>
  <p><?= Util::esc(t('hilfe.skala3')) ?></p>

  <h2><?= Util::esc(t('hilfe.h_passiv')) ?></h2>
  <p><?= Util::esc(t('hilfe.passiv1')) ?></p>
  <p><?= Util::esc(t('hilfe.passiv2')) ?></p>

  <h2><?= Util::esc(t('hilfe.h_veto')) ?></h2>
  <p><?= Util::esc(t('hilfe.veto1')) ?></p>

  <h2><?= Util::esc(t('hilfe.h_daten')) ?></h2>
  <p><?= Util::esc(t('hilfe.daten1')) ?></p>
  <p><?= Util::esc(t('hilfe.daten2')) ?></p>

  <h2><?= Util::esc(t('hilfe.h_quellen')) ?></h2>
  <p><?= Util::esc(t('hilfe.artikel')) ?>
     <a href="<?= Util::esc(Router::url('artikel')) ?>"><?= Util::esc(t('hilfe.artikel_link')) ?></a></p>
  <p><?= Util::esc(t('hilfe.quellen')) ?></p>
</div>
<?php include __DIR__ . '/_beispielknopf.php'; ?>
<?php include __DIR__ . '/_raumkarte.php'; ?>

<p><a class="knopf" href="<?= Util::esc(Router::url('')) ?>"><?= Util::esc(t('hilfe.zurueck')) ?></a></p>
