<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<h2><?= Util::esc(t('nav.redaktion')) ?></h2>
<?php if (!Util::profi()): ?>
<details class="hinweis">
  <summary><?= Util::esc(t('hinweis.wie_geht_das')) ?></summary>
  <p><?= Util::esc(t('redaktion.erklaerung')) ?></p>
</details>
<?php endif; ?>

<div class="karte" id="reihenfolge">
  <h3><?= Util::esc(t('redaktion.reihenfolge')) ?></h3>
  <p><?= Util::esc(t($poll->v('reihenfolge') === 'gemischt' ? 'redaktion.jetzt_gemischt' : 'redaktion.jetzt_neu')) ?></p>
  <div class="knopfreihe">
    <form method="post">
      <?= Security::tokenFeld('admin') ?><input type="hidden" name="aktion" value="mischen">
      <input type="hidden" name="anker" value="reihenfolge">
      <button class="knopf klein"><?= Util::esc(t('redaktion.wuerfeln')) ?></button>
    </form>
    <form method="post">
      <?= Security::tokenFeld('admin') ?><input type="hidden" name="aktion" value="reihenfolge_neu">
      <input type="hidden" name="anker" value="reihenfolge">
      <button class="knopf klein still"><?= Util::esc(t('redaktion.chronologisch')) ?></button>
    </form>
  </div>
</div>

<?php $andere = array_values(array_filter($liste, static fn($v) => (int)$v['ist_passiv'] === 0)); ?>
<ol class="vorschlagsliste redaktion">
<?php foreach ($liste as $v):
    if ((int)$v['ist_passiv'] === 1) continue;
    $auf = Vorschlaege::aufStimmzettel($poll, $v);
?>
  <li id="v<?= (int)$v['id'] ?>" class="vorschlag tiefe<?= (int)$v['tiefe'] ?> <?= $v['status'] !== 'aktiv' ? 'still' : '' ?>">
    <article>
      <h3><?= Util::esc((string)$v['titel']) ?>
        <span class="etikett <?= $auf ? 'gut' : '' ?>"><?= Util::esc(t($auf ? 'vorschlag.zettel_ja' : 'vorschlag.zettel_nein')) ?></span>
        <?php if ($v['aufnahme'] === 'erzwungen'): ?><span class="etikett"><?= Util::esc(t('redaktion.erzwungen')) ?></span><?php endif; ?>
        <?php if ($v['status'] !== 'aktiv'): ?><span class="etikett"><?= Util::esc(t('etikett.' . $v['status'])) ?></span><?php endif; ?>
      </h3>
      <p class="zeile-klein">
        <?= Util::esc(t('vorschlag.unterstuetzer', ['n' => (int)$v['unterstuetzer']])) ?>
        · <?= Util::esc(t('kommentar.anzahl', ['n' => (int)$v['kommentare']])) ?>
        <?php if ((string)$v['autor_name'] !== ''): ?> · <?= Util::esc((string)$v['autor_name']) ?><?php endif; ?>
      </p>
      <?php if ((string)$v['text'] !== ''): ?><div class="text"><?= Util::absaetze((string)$v['text']) ?></div><?php endif; ?>

      <div class="werkzeugreihe">
        <?php if ($v['status'] === 'aktiv'): ?>
          <form method="post"><?= Security::tokenFeld('admin') ?>
            <input type="hidden" name="aktion" value="redaktion_entfernen">
            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <input type="hidden" name="anker" value="v<?= (int)$v['id'] ?>">
            <button class="knopf winzig still"><?= Util::esc(t('redaktion.entfernen')) ?></button>
          </form>
          <form method="post"><?= Security::tokenFeld('admin') ?>
            <input type="hidden" name="aktion" value="redaktion_aufnehmen">
            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <input type="hidden" name="anker" value="v<?= (int)$v['id'] ?>">
            <input type="hidden" name="wie" value="<?= $v['aufnahme'] === 'erzwungen' ? 'auto' : 'erzwungen' ?>">
            <button class="knopf winzig"><?= Util::esc(t($v['aufnahme'] === 'erzwungen' ? 'redaktion.nicht_erzwingen' : 'redaktion.erzwingen')) ?></button>
          </form>
          <form method="post" class="zusammen"><?= Security::tokenFeld('admin') ?>
            <input type="hidden" name="aktion" value="redaktion_zusammen">
            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <input type="hidden" name="anker" value="v<?= (int)$v['id'] ?>">
            <label class="versteckt" for="z<?= (int)$v['id'] ?>"><?= Util::esc(t('redaktion.zusammen')) ?></label>
            <select id="z<?= (int)$v['id'] ?>" name="ziel_id">
              <option value="0"><?= Util::esc(t('redaktion.zusammen_waehlen')) ?></option>
              <?php foreach ($andere as $a): if ((int)$a['id'] === (int)$v['id']) continue; ?>
                <option value="<?= (int)$a['id'] ?>"><?= Util::esc(Util::kuerzen((string)$a['titel'], 60)) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="knopf winzig"><?= Util::esc(t('redaktion.zusammen')) ?></button>
          </form>
        <?php else: ?>
          <form method="post"><?= Security::tokenFeld('admin') ?>
            <input type="hidden" name="aktion" value="redaktion_zurueckholen">
            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <input type="hidden" name="anker" value="v<?= (int)$v['id'] ?>">
            <button class="knopf winzig"><?= Util::esc(t('redaktion.zurueckholen')) ?></button>
          </form>
        <?php endif; ?>
      </div>

      <form method="post" class="notizform"><?= Security::tokenFeld('admin') ?>
        <input type="hidden" name="aktion" value="redaktion_notiz">
        <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <input type="hidden" name="anker" value="v<?= (int)$v['id'] ?>">
        <label class="versteckt" for="n<?= (int)$v['id'] ?>"><?= Util::esc(t('vorschlag.redaktionsnotiz')) ?></label>
        <input type="text" id="n<?= (int)$v['id'] ?>" name="notiz" maxlength="500"
               placeholder="<?= Util::esc(t('redaktion.notiz_platzhalter')) ?>"
               value="<?= Util::esc((string)$v['redaktionsnotiz']) ?>">
        <button class="knopf winzig"><?= Util::esc(t('knopf.speichern')) ?></button>
      </form>
    </article>
  </li>
<?php endforeach; ?>
</ol>
