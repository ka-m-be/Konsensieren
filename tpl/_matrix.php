<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<?php /** Tabelle aller Bewertungen, wenn der Admin sie freigegeben hat. */ ?>
<h3><?= Util::esc(t('matrix.titel')) ?></h3>
<p class="dazu"><?= Util::esc(t($matrix['namen'] ? 'matrix.mit_namen' : 'matrix.ohne_namen')) ?></p>
<div class="tabellenrahmen">
<table class="matrix">
  <thead>
    <tr>
      <th scope="col"><?= Util::esc(t('matrix.person')) ?></th>
      <?php foreach ($matrix['zettel'] as $v): ?>
        <th scope="col"><span class="drehen"><?= Util::esc(Util::kuerzen((string)$v['titel'], 30)) ?></span></th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
  <?php $nr = 0; foreach ($matrix['teilnehmer'] as $t): $nr++; ?>
    <tr>
      <th scope="row"><?= Util::esc($matrix['namen'] ? (string)$t['name'] : t('matrix.person_nr', ['n' => $nr])) ?></th>
      <?php foreach ($matrix['zettel'] as $v):
          $b = $matrix['werte'][(int)$v['id']][(int)$t['id']] ?? null;
          $w = ($b === null || $b['wert'] === null) ? null : (int)$b['wert'];
      ?>
        <td class="<?= $w === null ? 'leer' : 'z' . $w ?>">
          <?= $w === null ? '–' : $w ?><?php if ($b && (int)$b['veto'] === 1): ?><span class="vetozeichen" title="<?= Util::esc(t('ergebnis.blockiert')) ?>">!</span><?php endif; ?>
        </td>
      <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
