<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<?php
$vorschlagsphase = $poll->phase() === Poll::PHASE_VORSCHLAG;
$schwelle = $poll->schwelle();
$aktive = array_values(array_filter($liste, static fn($v) => $v['status'] === 'aktiv' && (int)$v['ist_passiv'] === 0));
?>
<h2><?= Util::esc(t('vorschlaege.titel')) ?> <span class="zahl"><?= count($aktive) ?></span></h2>

<?php $kasten = 'vorschlag'; include __DIR__ . '/_beispielkasten.php'; ?>


<details class="hinweis">
  <summary><?= Util::esc(t('hinweis.wie_geht_das')) ?></summary>
  <p><?= Util::esc(t('vorschlaege.erklaerung')) ?></p>
  <?php if ($schwelle > 0): ?>
    <p><?= Util::esc(t('vorschlaege.schwelle_erklaerung', ['n' => $schwelle, 'p' => (int)$poll->v('schwelle_prozent')])) ?></p>
  <?php endif; ?>
</details>

<?php if (count($aktive) >= 20 && $vorschlagsphase): ?>
  <p class="meldung wink"><?= Util::esc(t('vorschlaege.viele')) ?></p>
<?php endif; ?>

<?php if (!$liste): ?>
  <p class="leer"><?= Util::esc(t('vorschlaege.keine')) ?></p>
<?php endif; ?>

<ol class="vorschlagsliste">
<?php foreach ($liste as $v):
    $auf = Vorschlaege::aufStimmzettel($poll, $v);
    $meins = (int)$v['autor_id'] === (int)($ich['id'] ?? 0);
    $unterstuetzt = in_array((int)$v['id'], $meine_u, true);
?>
  <li id="v<?= (int)$v['id'] ?>" class="vorschlag tiefe<?= (int)$v['tiefe'] ?> <?= $v['status'] !== 'aktiv' ? 'still' : '' ?> <?= (int)$v['ist_passiv'] ? 'passiv' : '' ?>">
    <article>
      <h3>
        <?= Util::esc((string)$v['titel']) ?>
        <?php if ((int)$v['ist_passiv']): ?><span class="etikett passiv"><?= Util::esc(t('passiv.etikett')) ?></span><?php endif; ?>
        <?php if ($v['status'] === 'zurueckgezogen'): ?><span class="etikett"><?= Util::esc(t('etikett.zurueckgezogen')) ?></span><?php endif; ?>
        <?php if ($v['status'] === 'entfernt'): ?><span class="etikett"><?= Util::esc(t('etikett.entfernt')) ?></span><?php endif; ?>
        <?php if ($v['status'] === 'zusammengefuehrt'): ?><span class="etikett"><?= Util::esc(t('etikett.zusammengefuehrt')) ?></span><?php endif; ?>
      </h3>

      <p class="zeile-klein">
        <?php if ((string)$v['autor_name'] !== ''): ?>
          <span><?= Util::esc(t('vorschlag.von', ['name' => (string)$v['autor_name']])) ?></span> ·
        <?php endif; ?>
        <span><?= Util::esc(Util::zeit((int)$v['angelegt'])) ?></span>
        <?php if ($v['geaendert']): ?>
          · <span class="geaendert"><?= Util::esc(t('vorschlag.geaendert', ['d' => Util::zeit((int)$v['geaendert'])])) ?></span>
        <?php endif; ?>
        <?php if ($v['eltern']): ?>
          · <span class="bezug"><?= Util::esc(t('vorschlag.bezieht_sich', ['n' => count($v['eltern'])])) ?></span>
        <?php endif; ?>
      </p>

      <?php if ((string)$v['text'] !== ''): ?>
        <div class="text"><?= Util::absaetze((string)$v['text']) ?></div>
      <?php endif; ?>

      <?php if ((string)$v['redaktionsnotiz'] !== ''): ?>
        <p class="notiz"><strong><?= Util::esc(t('vorschlag.redaktionsnotiz')) ?></strong>
          <?= Util::esc((string)$v['redaktionsnotiz']) ?></p>
      <?php endif; ?>

      <?php if ((int)$v['ist_passiv'] === 0 && $v['status'] === 'aktiv'): ?>
        <p class="unterstuetzung">
          <?php if ($schwelle > 0): ?>
            <span class="<?= $auf ? 'genug' : 'nochnicht' ?>">
              <?= Util::esc(t($auf ? 'vorschlag.auf_zettel' : 'vorschlag.braucht_noch',
                    ['n' => (int)$v['unterstuetzer'], 'k' => $schwelle])) ?>
            </span>
          <?php else: ?>
            <span><?= Util::esc(t('vorschlag.unterstuetzer', ['n' => (int)$v['unterstuetzer']])) ?></span>
          <?php endif; ?>
          <?php if ($vorschlagsphase && $rolle === 'user'): ?>
            <form method="post" class="inline">
              <?= Security::tokenFeld('user') ?>
              <input type="hidden" name="aktion" value="unterstuetzen">
              <input type="hidden" name="anker" value="v<?= (int)$v['id'] ?>">
              <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
              <input type="hidden" name="ja" value="<?= $unterstuetzt ? '' : '1' ?>">
              <button type="submit" class="knopf klein <?= $unterstuetzt ? 'an' : '' ?>">
                <?= Util::esc(t($unterstuetzt ? 'vorschlag.unterstuetze_ich' : 'vorschlag.unterstuetzen')) ?>
              </button>
            </form>
          <?php endif; ?>
        </p>
      <?php endif; ?>

      <?php $k = $kommentare[(int)$v['id']] ?? []; $vid = (int)$v['id']; include __DIR__ . '/_kommentare.php'; ?>

      <?php if ($vorschlagsphase && $meins && $v['status'] === 'aktiv' && (int)$v['ist_passiv'] === 0): ?>
        <details class="werkzeug">
          <summary><?= Util::esc(t('vorschlag.bearbeiten')) ?></summary>
          <form method="post">
            <?= Security::tokenFeld('user') ?>
            <input type="hidden" name="aktion" value="vorschlag_aendern">
              <input type="hidden" name="anker" value="v<?= (int)$v['id'] ?>">
            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <p class="feld"><label for="t<?= $vid ?>"><?= Util::esc(t('feld.titel')) ?></label>
              <input type="text" id="t<?= $vid ?>" name="titel" value="<?= Util::esc((string)$v['titel']) ?>" maxlength="200"></p>
            <p class="feld"><label for="x<?= $vid ?>"><?= Util::esc(t('feld.text')) ?></label>
              <textarea id="x<?= $vid ?>" name="text" rows="4"><?= Util::esc((string)$v['text']) ?></textarea></p>
            <p><button type="submit" class="knopf klein"><?= Util::esc(t('knopf.speichern')) ?></button></p>
          </form>
          <form method="post" data-bestaetigen="<?= Util::esc(t('vorschlag.zurueck_sicher')) ?>">
            <?= Security::tokenFeld('user') ?>
            <input type="hidden" name="aktion" value="vorschlag_zurueck">
              <input type="hidden" name="anker" value="v<?= (int)$v['id'] ?>">
            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <button type="submit" class="knopf klein still"><?= Util::esc(t('vorschlag.zurueckziehen')) ?></button>
          </form>
        </details>
      <?php endif; ?>
    </article>
  </li>
<?php endforeach; ?>
</ol>

<?php if ($vorschlagsphase && $rolle === 'user'): ?>
<form method="post" class="karte" id="neuer-vorschlag">
  <?= Security::tokenFeld('user') ?>
  <input type="hidden" name="aktion" value="vorschlag_neu">
  <h2><?= Util::esc(t('vorschlaege.neuer')) ?></h2>
  <p class="feld">
    <label for="n_titel"><?= Util::esc(t('feld.titel')) ?></label>
    <input type="text" id="n_titel" name="titel" required maxlength="200">
  </p>
  <p class="feld">
    <label for="n_text"><?= Util::esc(t('feld.text')) ?> <span class="dazu"><?= Util::esc(t('feld.freiwillig')) ?></span></label>
    <textarea id="n_text" name="text" rows="4"></textarea>
  </p>
  <?php if ($aktive): ?>
  <p class="feld">
    <label for="n_primaer"><?= Util::esc(t('feld.modifikation')) ?></label>
    <select id="n_primaer" name="primaer">
      <option value="0"><?= Util::esc(t('feld.eigenstaendig')) ?></option>
      <?php foreach ($aktive as $a): ?>
        <option value="<?= (int)$a['id'] ?>"><?= Util::esc(Util::kuerzen((string)$a['titel'], 80)) ?></option>
      <?php endforeach; ?>
    </select>
    <span class="dazu"><?= Util::esc(t('feld.modifikation_dazu')) ?></span>
  </p>
  <details class="hinweis mehr">
    <summary><?= Util::esc(t('feld.weitere_bezuege')) ?></summary>
    <ul class="kaestchen">
      <?php foreach ($aktive as $a): ?>
        <li><label><input type="checkbox" name="weitere[]" value="<?= (int)$a['id'] ?>">
          <?= Util::esc(Util::kuerzen((string)$a['titel'], 80)) ?></label></li>
      <?php endforeach; ?>
    </ul>
  </details>
  <?php endif; ?>
  <p class="feld schalter">
    <label><input type="checkbox" name="mit_namen" checked> <?= Util::esc(t('feld.mit_namen')) ?></label>
  </p>
  <p><button type="submit" class="knopf gross"><?= Util::esc(t('vorschlaege.einbringen')) ?></button></p>
</form>
<?php elseif ($rolle === 'user'): ?>
  <p class="leer"><?= Util::esc(t('vorschlaege.phase_vorbei')) ?></p>
<?php endif; ?>
