<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<h2><?= Util::esc(t('admin.titel')) ?></h2>

<div class="kacheln">
  <div class="kachel"><span class="gross"><?= (int)$zahlen['teilnehmer'] ?></span><span><?= Util::esc(t('admin.teilnehmende')) ?></span></div>
  <div class="kachel"><span class="gross"><?= (int)$zahlen['vorschlaege'] ?></span><span><?= Util::esc(t('admin.vorschlaege')) ?></span></div>
  <div class="kachel"><span class="gross"><?= (int)$zahlen['stimmzettel'] ?></span><span><?= Util::esc(t('admin.auf_zettel')) ?></span></div>
  <?php if ((int)$zahlen['schwelle'] > 0): ?>
    <div class="kachel"><span class="gross"><?= (int)$zahlen['schwelle'] ?></span><span><?= Util::esc(t('admin.schwelle')) ?></span></div>
  <?php endif; ?>
</div>

<div class="karte" id="links">
  <h3><?= Util::esc(t('admin.links')) ?></h3>
  <p class="dazu"><?= Util::esc(t('admin.einladung_dazu')) ?></p>
  <?php $link = $einladung_link; $id = 'la1'; include __DIR__ . '/_linkfeld.php'; ?>
  <?php if ($nutzer_link !== ''): ?>
    <p class="dazu"><?= Util::esc(t('admin.nutzer_dazu')) ?></p>
    <?php $link = $nutzer_link; $id = 'la2'; include __DIR__ . '/_linkfeld.php'; ?>
  <?php endif; ?>
</div>

<div class="karte" id="phase">
  <h3><?= Util::esc(t('admin.phase')) ?></h3>
  <p><?= Util::esc(t('admin.phase_jetzt', ['p' => t('phase.' . $poll->phase())])) ?> <?php erkl('admin.phase_jetzt_dazu'); ?></p>
  <div class="knopfreihe">
    <?php if ($poll->phase() === Poll::PHASE_VORSCHLAG): ?>
      <form method="post" data-bestaetigen="<?= Util::esc(t('admin.phase_bewertung_sicher')) ?>">
        <?= Security::tokenFeld('admin') ?><input type="hidden" name="aktion" value="phase_bewertung">
        <input type="hidden" name="anker" value="phase">
        <button class="knopf"><?= Util::esc(t('admin.zur_bewertung')) ?></button>
      </form>
    <?php elseif ($poll->phase() === Poll::PHASE_BEWERTUNG): ?>
      <form method="post" data-bestaetigen="<?= Util::esc(t('admin.phase_ergebnis_sicher')) ?>">
        <?= Security::tokenFeld('admin') ?><input type="hidden" name="aktion" value="phase_ergebnis">
        <input type="hidden" name="anker" value="phase">
        <button class="knopf"><?= Util::esc(t('admin.zum_ergebnis')) ?></button>
      </form>
      <?php if ($poll->kannZurueck()): ?>
        <form method="post">
          <?= Security::tokenFeld('admin') ?><input type="hidden" name="aktion" value="phase_zurueck">
          <input type="hidden" name="anker" value="phase">
          <button class="knopf still"><?= Util::esc(t('admin.zurueck_vorschlag')) ?></button>
        </form>
      <?php else: ?>
        <p class="dazu"><?= Util::esc(t('admin.zurueck_zu')) ?></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<div class="karte" id="sichtbarkeit">
  <h3><?= Util::esc(t('admin.sichtbarkeit')) ?></h3>
  <?php
  $schalter = [
    'sicht_bewertungen' => 'admin.s_bewertungen',
    'sicht_ergebnis'    => 'admin.s_ergebnis',
    'zeige_namen'       => 'admin.s_namen',
    'nur_vollstaendig'  => 'admin.s_vollstaendig',
  ];
  foreach ($schalter as $feld => $schluessel): ?>
    <form method="post" class="schalterzeile">
      <?= Security::tokenFeld('admin') ?>
      <input type="hidden" name="aktion" value="schalter">
      <input type="hidden" name="feld" value="<?= $feld ?>">
      <input type="hidden" name="anker" value="sichtbarkeit">
      <button type="submit" class="schieber <?= $poll->an($feld) ? 'an' : 'aus' ?>"
              aria-pressed="<?= $poll->an($feld) ? 'true' : 'false' ?>">
        <span class="knebel" aria-hidden="true"></span>
        <span class="beschriftung"><?= Util::esc(t($schluessel)) ?></span>
        <span class="zustand"><?= Util::esc(t($poll->an($feld) ? 'zustand.an' : 'zustand.aus')) ?></span>
      </button>
      <?php erkl($schluessel . '_dazu'); ?>
    </form>
  <?php endforeach; ?>
</div>

<div class="karte" id="ergebnislink">
  <h3><?= Util::esc(t('admin.ergebnislink')) ?></h3>
  <?php erkl('admin.ergebnislink_dazu', [], 'p'); ?>
  <?php if ($ergebnis_link !== ''): ?>
    <?php $link = $ergebnis_link; $id = 'la3'; include __DIR__ . '/_linkfeld.php'; ?>
    <form method="post" class="schalterzeile">
      <?= Security::tokenFeld('admin') ?>
      <input type="hidden" name="aktion" value="schalter">
      <input type="hidden" name="feld" value="ergebnis_vorab">
      <input type="hidden" name="anker" value="ergebnislink">
      <button type="submit" class="schieber <?= $poll->an('ergebnis_vorab') ? 'an' : 'aus' ?>"
              aria-pressed="<?= $poll->an('ergebnis_vorab') ? 'true' : 'false' ?>">
        <span class="knebel" aria-hidden="true"></span>
        <span class="beschriftung"><?= Util::esc(t('admin.s_vorab')) ?></span>
        <span class="zustand"><?= Util::esc(t($poll->an('ergebnis_vorab') ? 'zustand.an' : 'zustand.aus')) ?></span>
      </button>
    </form>
    <form method="post">
      <?= Security::tokenFeld('admin') ?><input type="hidden" name="aktion" value="ergebnislink_weg">
      <input type="hidden" name="anker" value="ergebnislink">
      <button class="knopf klein still"><?= Util::esc(t('admin.ergebnislink_weg')) ?></button>
    </form>
  <?php else: ?>
    <form method="post">
      <?= Security::tokenFeld('admin') ?><input type="hidden" name="aktion" value="ergebnislink_neu">
      <input type="hidden" name="anker" value="ergebnislink">
      <button class="knopf klein"><?= Util::esc(t('admin.ergebnislink_neu')) ?></button>
    </form>
  <?php endif; ?>
</div>

<div class="karte" id="pflege">
  <h3><?= Util::esc(t('admin.pflege')) ?></h3>
  <?php erkl('admin.aufraeumen_dazu', [], 'p'); ?>
  <form method="post">
    <?= Security::tokenFeld('admin') ?><input type="hidden" name="aktion" value="aufraeumen">
    <button class="knopf klein"><?= Util::esc(t('admin.aufraeumen')) ?></button>
  </form>
</div>
