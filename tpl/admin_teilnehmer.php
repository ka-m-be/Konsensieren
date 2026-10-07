<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<h2><?= Util::esc(t('nav.teilnehmer')) ?></h2>
<?php erkl('admin.teilnehmer_dazu', [], 'p'); ?>
<?php erkl('admin.links_dazu', [], 'p'); ?>

<?php if (!empty($neuer_link)): ?>
  <div class="karte">
    <h3><?= Util::esc(t('admin.neuer_link_da', ['name' => (string)$neuer_name])) ?></h3>
    <p class="dazu"><?= Util::esc(t('admin.neuer_link_dazu')) ?></p>
    <?php $link = $neuer_link; $id = 'neuerlink'; include __DIR__ . '/_linkfeld.php'; ?>
  </div>
<?php endif; ?>
<div class="tabellenrahmen">
<table class="liste">
  <thead><tr>
    <th scope="col"><?= Util::esc(t('feld.dein_name')) ?></th>
    <th scope="col"><?= Util::esc(t('admin.beigetreten')) ?></th>
    <th scope="col"><?= Util::esc(t('admin.zuletzt')) ?></th>
    <th scope="col"><?= Util::esc(t('admin.zugang')) ?></th>
    <th scope="col"></th>
  </tr></thead>
  <tbody>
  <?php foreach ($leute as $l): ?>
    <tr>
      <td><?= Util::esc((string)$l['name']) ?>
        <?php if ((int)$l['ist_admin'] === 1): ?><span class="etikett"><?= Util::esc(t('admin.rolle')) ?></span><?php endif; ?></td>
      <td><?= Util::esc(Util::zeit((int)$l['angelegt'])) ?></td>
      <td><?= Util::esc(Util::zeit((int)$l['gesehen'])) ?></td>
      <td class="zugang">
        <form method="post" data-bestaetigen="<?= Util::esc(t('admin.neuer_link_sicher')) ?>">
          <?= Security::tokenFeld('admin') ?>
          <input type="hidden" name="aktion" value="teilnehmer_neuer_link">
          <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
          <button class="knopf winzig still"><?= Util::esc(t('admin.neuer_link')) ?></button>
        </form>
      </td>
      <td>
        <?php if ((int)$l['ist_admin'] === 0): ?>
        <form method="post" data-bestaetigen="<?= Util::esc(t('admin.entfernen_sicher')) ?>">
          <?= Security::tokenFeld('admin') ?>
          <input type="hidden" name="aktion" value="teilnehmer_weg">
          <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
          <button class="knopf winzig still"><?= Util::esc(t('knopf.entfernen')) ?></button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
