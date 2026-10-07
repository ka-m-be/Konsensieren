<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<?php /** Phasenleiste: wo stehen wir gerade und wie lange noch. */ ?>
<ol class="phasen">
  <?php
  $stufen = [
      Poll::PHASE_VORSCHLAG => 'phase.vorschlag',
      Poll::PHASE_BEWERTUNG => 'phase.bewertung',
      Poll::PHASE_ERGEBNIS  => 'phase.ergebnis',
  ];
  $reihe = array_keys($stufen);
  $jetzt = array_search($poll->phase(), $reihe, true);
  foreach ($reihe as $i => $stufe):
      $zustand = $i < $jetzt ? 'vorbei' : ($i === $jetzt ? 'jetzt' : 'kommt');
  ?>
    <li class="<?= $zustand ?>">
      <span class="punkt" aria-hidden="true"></span>
      <span class="name"><?= Util::esc(t($stufen[$stufe])) ?></span>
      <?php if ($zustand === 'jetzt'): ?>
        <span class="rest"><?= Util::esc(Util::restzeit($poll->endeDerPhase())) ?>
          (<?= Util::esc(Util::zeit($poll->endeDerPhase())) ?>)</span>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ol>
