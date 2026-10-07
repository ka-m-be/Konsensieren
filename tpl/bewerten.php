<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<?php $veto = $poll->an('opt_veto'); ?>
<h2><?= Util::esc(t('bewerten.titel')) ?></h2>

<?php $kasten = 'bewertung'; include __DIR__ . '/_beispielkasten.php'; ?>


<p class="fortschritt" role="status">
  <?= Util::esc(t('bewerten.fortschritt', ['n' => $fortschritt['fertig'], 'g' => $fortschritt['gesamt']])) ?>
  <?php if ($fortschritt['offen'] > 0): ?>
    <span class="wink"><?= Util::esc(t('bewerten.noch_offen', ['n' => $fortschritt['offen']])) ?></span>
  <?php endif; ?>
</p>

<?php if ($fortschritt['passiv_offen']): ?>
  <p class="meldung wink"><?= Util::esc(t('bewerten.passiv_offen')) ?></p>
<?php endif; ?>

<details class="hinweis" open>
  <summary><?= Util::esc(t('hinweis.wie_geht_das')) ?></summary>
  <p><?= Util::esc(t('bewerten.erklaerung1')) ?></p>
  <p><?= Util::esc(t('bewerten.erklaerung2')) ?></p>
  <?php if ($veto): ?><p class="warnung"><?= Util::esc(t('bewerten.veto_erklaerung')) ?></p><?php endif; ?>
</details>

<?php /*
  Das Formular steht fuer sich und bleibt leer; die Bewertungsfelder haengen per
  form-Attribut daran. So koennen zwischen ihnen die Kommentarformulare stehen,
  ohne dass Formulare ineinander verschachtelt werden - das waere ungueltiges HTML.
*/ ?>
<form method="post" class="bewertungsform" id="bewertungsform">
  <?= Security::tokenFeld('user') ?>
  <input type="hidden" name="aktion" value="bewerten">
</form>

<?php include __DIR__ . '/_zettelkopf.php'; ?>

<?php foreach ($zettel as $v):
    $vid = (int)$v['id'];
    $b = $meine[$vid] ?? null;
    $wert = ($b === null || $b['wert'] === null) ? null : (int)$b['wert'];
?>
  <section class="bewertung <?= (int)$v['ist_passiv'] ? 'passiv' : '' ?> <?= $wert === null ? 'offen' : '' ?>">
   <div class="inhalt">
    <h3><?= Util::esc((string)$v['titel']) ?>
      <?php if ((int)$v['ist_passiv']): ?><span class="etikett passiv"><?= Util::esc(t('passiv.etikett')) ?></span><?php endif; ?>
    </h3>

    <?php if ((string)$v['autor_name'] !== '' || !empty($v['eltern'])): ?>
      <p class="zeile-klein">
        <?php if ((string)$v['autor_name'] !== ''): ?>
          <span><?= Util::esc(t('vorschlag.von', ['name' => (string)$v['autor_name']])) ?></span>
        <?php endif; ?>
        <?php if (!empty($v['eltern'])): ?>
          <span class="bezug"><?= Util::esc(t('vorschlag.bezieht_sich', ['n' => count($v['eltern'])])) ?></span>
        <?php endif; ?>
      </p>
    <?php endif; ?>

    <?php if ((string)$v['text'] !== ''): ?>
      <div class="text"><?= Util::absaetze((string)$v['text']) ?></div>
    <?php endif; ?>

    <?php if ((string)$v['redaktionsnotiz'] !== ''): ?>
      <p class="notiz"><strong><?= Util::esc(t('vorschlag.redaktionsnotiz')) ?></strong>
        <?= Util::esc((string)$v['redaktionsnotiz']) ?></p>
    <?php endif; ?>

    <?php $k = $kommentare[$vid] ?? []; $aufklappen = true; include __DIR__ . '/_kommentare.php'; ?>
   </div>

   <div class="wahl">
    <?php $formular = 'bewertungsform'; include __DIR__ . '/_skala.php'; ?>

    <?php if ($veto): ?>
      <details class="vetofeld" <?= ($b && (int)$b['veto'] === 1) ? 'open' : '' ?>>
        <summary><?= Util::esc(t('bewerten.veto_einlegen')) ?></summary>
        <p class="feld schalter">
          <label><input type="checkbox" form="bewertungsform" name="veto[<?= $vid ?>]" value="1"
                 <?= ($b && (int)$b['veto'] === 1) ? 'checked' : '' ?>>
            <?= Util::esc(t('bewerten.veto_ja')) ?></label>
        </p>
        <p class="feld">
          <label class="versteckt" for="vg<?= $vid ?>"><?= Util::esc(t('bewerten.veto_grund')) ?></label>
          <input type="text" form="bewertungsform" id="vg<?= $vid ?>" name="veto_grund[<?= $vid ?>]" maxlength="300"
                 placeholder="<?= Util::esc(t('bewerten.veto_grund')) ?>"
                 value="<?= Util::esc($b ? (string)$b['veto_grund'] : '') ?>">
        </p>
      </details>
    <?php endif; ?>

   </div>
  </section>
<?php endforeach; ?>

<div class="speicherleiste">
  <button type="submit" form="bewertungsform" class="knopf gross"><?= Util::esc(t('bewerten.speichern')) ?></button>
  <span class="dazu"><?= Util::esc(t('bewerten.aenderbar')) ?></span>
</div>
