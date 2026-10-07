<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<h2><?= Util::esc(t('nav.protokoll')) ?></h2>
<?php erkl('admin.protokoll_dazu', [], 'p'); ?>
<table class="liste">
  <thead><tr><th scope="col"><?= Util::esc(t('admin.wann')) ?></th><th scope="col"><?= Util::esc(t('admin.was')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($eintraege as $e): ?>
    <tr>
      <td><?= Util::esc(Util::zeit((int)$e['zeit'])) ?></td>
      <td><?= Util::esc(t('protokoll.' . $e['art'])) ?>
        <?php if ((string)$e['details'] !== ''): ?>
          <span class="dazu"><?= Util::esc(Util::kuerzen((string)$e['details'], 120)) ?></span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
