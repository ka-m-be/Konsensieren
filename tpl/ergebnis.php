<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<h2><?= Util::esc(t('ergebnis.titel')) ?></h2>

<?php $kasten = 'ergebnis'; include __DIR__ . '/_beispielkasten.php'; ?>


<?php if (!$offen): ?>
  <p class="leer"><?= Util::esc(t('ergebnis.zu')) ?></p>
<?php else: ?>

<?php if ($poll->phase() !== Poll::PHASE_ERGEBNIS): ?>
  <p class="meldung wink"><?= Util::esc(t('ergebnis.zwischenstand')) ?></p>
<?php endif; ?>

<p class="dazu">
  <?= Util::esc(t('ergebnis.grundlage', ['n' => $erg['wertende'], 'g' => $erg['teilnehmer']])) ?>
  <?= Util::esc(t($erg['modus'] === 'vollstaendig' ? 'ergebnis.modus_voll' : 'ergebnis.modus_abgegeben')) ?>
  <?= Util::esc(t('ergebnis.quorum_erklaerung', ['k' => $erg['noetig'], 'g' => $erg['wertende']])) ?>
</p>

<?php
/* Drei verschiedene Lagen, drei verschiedene Saetze. Frueher stand hier bei jeder
   siegerlosen Auswertung "zu wenig Bewertungen" - auch dann, wenn reichlich
   bewertet wurde und bloss nichts das Nichtstun uebertraf. */
$passivGewinnt = $erg['passiv'] !== null && $erg['sieger'] === (int)$erg['passiv']['id'];
?>
<?php if ($erg['passiv'] !== null && !$erg['passiv']['belastbar']): ?>
  <p class="meldung wink"><?= Util::esc(t('ergebnis.passiv_unklar', [
      'n' => (int)$erg['passiv']['bewertet'], 'k' => $erg['noetig'],
  ])) ?></p>
<?php elseif ($passivGewinnt): ?>
  <p class="meldung"><?= Util::esc(t('ergebnis.nur_passiv')) ?></p>
<?php elseif ($erg['sieger'] === null): ?>
  <p class="meldung wink"><?= Util::esc(t('ergebnis.kein_sieger')) ?></p>
<?php endif; ?>

<?php /* Der Profi-Modus (Spezifikation 20) gilt nur der Verwaltung; wer teilnimmt,
         sieht die Erklaerung immer. */ ?>
<?php if (!($rolle === 'admin' && Util::profi())): ?>
<details class="hinweis">
  <summary><?= Util::esc(t('hinweis.wie_lesen')) ?></summary>
  <p><?= Util::esc(t('ergebnis.erklaerung1')) ?></p>
  <p><?= Util::esc(t('ergebnis.erklaerung2')) ?></p>
  <?php if ($erg['passiv']): ?><p><?= Util::esc(t('ergebnis.erklaerung_kik')) ?></p><?php endif; ?>
</details>
<?php endif; ?>

<ol class="ergebnisliste">
<?php foreach ($erg['zeilen'] as $z):
    $ist_sieger = $erg['sieger'] === (int)$z['id'];
    $stufe = $z['mittel'] === null ? 5 : max(0, min(10, (int)round((float)$z['mittel'])));
?>
  <li class="ergebniszeile <?= $ist_sieger ? 'sieger' : '' ?> <?= $z['gesperrt'] ? 'gesperrt' : '' ?>
             <?= $z['belastbar'] ? '' : 'unsicher' ?> <?= $z['legitimiert'] === false ? 'unlegitim' : '' ?>">
    <?php /* Keine Platzziffer: Die Reihenfolge ist die Rangfolge (0.3.4, wie in der App). */ ?>
    <div class="haupt">
      <h3><?= Util::esc((string)$z['titel']) ?>
        <?php if ((int)$z['ist_passiv']): ?><span class="etikett passiv"><?= Util::esc(t('passiv.etikett')) ?></span><?php endif; ?>
        <?php if ($ist_sieger): ?><span class="etikett gut"><?= Util::esc(t('ergebnis.geringster')) ?></span><?php endif; ?>
        <?php if ($z['gesperrt']): ?><span class="etikett schlecht"><?= Util::esc(t('ergebnis.blockiert')) ?></span><?php endif; ?>
        <?php if (!$z['belastbar']): ?><span class="etikett wink"><?= Util::esc(t('ergebnis.zu_wenig')) ?></span><?php endif; ?>
        <?php if ($z['gleichauf']): ?><span class="etikett"><?= Util::esc(t('ergebnis.gleichauf')) ?></span>
        <?php elseif ($z['legitimiert'] === false): ?><span class="etikett"><?= Util::esc(t('ergebnis.nicht_legitimiert')) ?></span><?php endif; ?>
      </h3>
      <?php if ((string)$z['autor_name'] !== ''): ?>
        <p class="zeile-klein"><?= Util::esc(t('vorschlag.von', ['name' => (string)$z['autor_name']])) ?></p>
      <?php endif; ?>

      <?php /* Der Mittelwert allein verschweigt, wer kaum mitkommt. Deshalb stehen
               der niedrigste Einzelwert und die Zahl der Zoegernden gleich daneben
               und nicht irgendwo weiter unten. */ ?>
      <p class="kennzahlen">
        <span class="summe z<?= $stufe ?>">
          <span class="versteckt"><?= Util::esc(t('ergebnis.durchschnitt')) ?></span>
          <strong><?= $z['mittel'] === null ? '–' : Util::esc(number_format((float)$z['mittel'], 1, ',', '')) ?></strong></span>
        <span class="<?= $z['belastbar'] ? '' : 'wink' ?>">
          <?= Util::esc(t('ergebnis.bewertet_von', ['n' => (int)$z['bewertet'], 'g' => $erg['wertende']])) ?></span>
        <?php if ($z['minimal'] !== null): ?>
          <span><?= Util::esc(t('ergebnis.niedrigst', ['w' => (int)$z['minimal']])) ?></span>
        <?php endif; ?>
        <?php if ((int)$z['niedrig'] > 0): ?>
          <span class="wink"><?= Util::esc(t('ergebnis.schwach', ['n' => (int)$z['niedrig']])) ?></span>
        <?php endif; ?>
        <?php if ($z['kik'] !== null): ?>
          <span class="<?= (float)$z['kik'] > 0 ? 'gut' : ((float)$z['kik'] < 0 ? 'schlecht' : '') ?>">
            <?= Util::esc(t('ergebnis.kik', ['w' => number_format((float)$z['kik'], 1, ',', '')])) ?></span>
        <?php endif; ?>
      </p>

      <?php $verteilung = $z['verteilung']; include __DIR__ . '/_verteilung.php'; ?>

      <?php if ($z['vetos']): ?>
        <div class="vetos">
          <?php foreach ($z['vetos'] as $veto): ?>
            <p><strong><?= Util::esc(t('ergebnis.veto_von', ['name' => $veto['name']])) ?></strong>
               <?= Util::esc($veto['grund']) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </li>
<?php endforeach; ?>
</ol>

<?php if (!empty($matrix)): ?>
  <?php include __DIR__ . '/_matrix.php'; ?>
<?php elseif ($rolle === 'user' && !$poll->an('sicht_bewertungen')): ?>
  <p class="dazu"><?= Util::esc(t('ergebnis.matrix_zu')) ?></p>
<?php endif; ?>

<?php if ($rolle === 'admin'): ?>
  <p><a class="knopf klein" href="<?= Util::esc(App::ziel('export.csv')) ?>"><?= Util::esc(t('ergebnis.csv')) ?></a>
     <a class="knopf klein" href="<?= Util::esc(App::ziel('export.json')) ?>"><?= Util::esc(t('ergebnis.json')) ?></a></p>
<?php endif; ?>

<?php endif; ?>
