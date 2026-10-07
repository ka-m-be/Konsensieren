<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<?php /** Nur-Lesen-Sicht fuer Aussenstehende: Zahlen ja, Personen nein. */ ?>
<h2><?= Util::esc(t('ergebnis.titel')) ?></h2>
<?php if ($poll->phase() !== Poll::PHASE_ERGEBNIS): ?>
  <p class="meldung wink"><?= Util::esc(t('ergebnis.zwischenstand')) ?></p>
<?php endif; ?>
<p class="dazu">
  <?= Util::esc(t('ergebnis.grundlage', ['n' => $erg['wertende'], 'g' => $erg['teilnehmer']])) ?>
  <?= Util::esc(t('ergebnis.quorum_erklaerung', ['k' => $erg['noetig'], 'g' => $erg['wertende']])) ?>
</p>
<?php if ($erg['passiv'] !== null && !$erg['passiv']['belastbar']): ?>
  <p class="meldung wink"><?= Util::esc(t('ergebnis.passiv_unklar', [
      'n' => (int)$erg['passiv']['bewertet'], 'k' => $erg['noetig']])) ?></p>
<?php endif; ?>

<ol class="ergebnisliste">
<?php foreach ($erg['zeilen'] as $z):
    $ist_sieger = $erg['sieger'] === (int)$z['id'];
    $stufe = $z['mittel'] === null ? 5 : max(0, min(10, (int)round((float)$z['mittel'])));
?>
  <li class="ergebniszeile <?= $ist_sieger ? 'sieger' : '' ?> <?= $z['gesperrt'] ? 'gesperrt' : '' ?>
             <?= $z['belastbar'] ? '' : 'unsicher' ?>">
    <div class="haupt">
      <h3><?= Util::esc((string)$z['titel']) ?>
        <?php if ((int)$z['ist_passiv']): ?><span class="etikett passiv"><?= Util::esc(t('passiv.etikett')) ?></span><?php endif; ?>
        <?php if ($ist_sieger): ?><span class="etikett gut"><?= Util::esc(t('ergebnis.geringster')) ?></span><?php endif; ?>
        <?php if ($z['gesperrt']): ?><span class="etikett schlecht"><?= Util::esc(t('ergebnis.blockiert')) ?></span><?php endif; ?>
        <?php if (!$z['belastbar']): ?><span class="etikett wink"><?= Util::esc(t('ergebnis.zu_wenig')) ?></span><?php endif; ?>
      </h3>
      <?php if ((string)$z['text'] !== ''): ?><div class="text"><?= Util::absaetze((string)$z['text']) ?></div><?php endif; ?>
      <?php /* Auch nach aussen steht der niedrigste Wert neben dem Mittelwert. Wer
               das Ergebnis von aussen liest, soll ebenfalls sehen, ob jemand kaum
               mitkommt - sonst ist die Zahl geschoenter, als sie sein darf. */ ?>
      <p class="kennzahlen">
        <span class="summe z<?= $stufe ?>">
          <span class="versteckt"><?= Util::esc(t('ergebnis.durchschnitt')) ?></span>
          <strong><?= $z['mittel'] === null ? '–' : Util::esc(number_format((float)$z['mittel'], 1, ',', '')) ?></strong></span>
        <span><?= Util::esc(t('ergebnis.bewertet_von', ['n' => (int)$z['bewertet'], 'g' => $erg['wertende']])) ?></span>
        <?php if ($z['minimal'] !== null): ?>
          <span><?= Util::esc(t('ergebnis.niedrigst', ['w' => (int)$z['minimal']])) ?></span>
        <?php endif; ?>
        <?php if ((int)$z['niedrig'] > 0): ?>
          <span class="wink"><?= Util::esc(t('ergebnis.schwach', ['n' => (int)$z['niedrig']])) ?></span>
        <?php endif; ?>
        <?php if ($z['kik'] !== null): ?>
          <span><?= Util::esc(t('ergebnis.kik', ['w' => number_format((float)$z['kik'], 1, ',', '')])) ?></span>
        <?php endif; ?>
      </p>
      <?php $verteilung = $z['verteilung']; include __DIR__ . '/_verteilung.php'; ?>
      <?php if ($z['vetos']): ?>
        <div class="vetos">
          <?php foreach ($z['vetos'] as $veto): ?>
            <p><strong><?= Util::esc(t('ergebnis.veto_anonym')) ?></strong> <?= Util::esc($veto['grund']) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </li>
<?php endforeach; ?>
</ol>
<p class="dazu"><?= Util::esc(t('oeffentlich.hinweis')) ?></p>
