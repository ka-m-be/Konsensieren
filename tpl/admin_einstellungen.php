<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<h2><?= Util::esc(t('nav.einstellungen')) ?></h2>

<form method="post" class="karte">
  <?= Security::tokenFeld('admin') ?>
  <input type="hidden" name="aktion" value="einstellungen">

  <p class="feld">
    <label for="titel"><?= Util::esc(t('feld.titel')) ?></label>
    <input type="text" id="titel" name="titel" maxlength="200" value="<?= Util::esc((string)$poll->v('titel')) ?>">
  </p>
  <p class="feld">
    <label for="beschreibung"><?= Util::esc(t('feld.beschreibung')) ?></label>
    <textarea id="beschreibung" name="beschreibung" rows="3"><?= Util::esc((string)$poll->v('beschreibung')) ?></textarea>
  </p>

  <div class="zwei">
    <p class="feld">
      <label for="ende_vorschlag"><?= Util::esc(t('feld.ende_vorschlag')) ?></label>
      <input type="date" id="ende_vorschlag" name="ende_vorschlag"
             value="<?= Util::esc(Util::datumFeld((int)$poll->v('ende_vorschlag'))) ?>"
             <?= $poll->phase() === Poll::PHASE_VORSCHLAG ? '' : 'disabled' ?>>
    </p>
    <p class="feld">
      <label for="ende_bewertung"><?= Util::esc(t('feld.ende_bewertung')) ?></label>
      <input type="date" id="ende_bewertung" name="ende_bewertung"
             value="<?= Util::esc(Util::datumFeld((int)$poll->v('ende_bewertung'))) ?>"
             <?= $poll->phase() === Poll::PHASE_ERGEBNIS ? 'disabled' : '' ?>>
    </p>
  </div>

  <?php if ($poll->phase() === Poll::PHASE_VORSCHLAG): ?>
    <fieldset class="regeln">
      <legend><?= Util::esc(t('start.regeln')) ?></legend>
      <p class="feld schalter">
        <label><input type="checkbox" name="ohne_passiv" <?= $poll->an('opt_passiv') ? '' : 'checked' ?>>
          <?= Util::esc(t('feld.ohne_passiv')) ?></label>
      </p>
      <p class="feld">
        <label for="passiv_text"><?= Util::esc(t('feld.passiv_text')) ?></label>
        <input type="text" id="passiv_text" name="passiv_text" maxlength="500"
               value="<?= Util::esc((string)$poll->v('passiv_text')) ?>">
      </p>
      <p class="feld schalter">
        <label><input type="checkbox" name="mit_veto" <?= $poll->an('opt_veto') ? 'checked' : '' ?>>
          <?= Util::esc(t('feld.mit_veto')) ?></label>
        <span class="dazu warnung"><?= Util::esc(t('feld.mit_veto_dazu')) ?></span>
      </p>
      <p class="feld">
        <label for="schwelle"><?= Util::esc(t('feld.schwelle')) ?></label>
        <span class="einheit"><input type="number" id="schwelle" name="schwelle" min="0" max="100" step="5"
               value="<?= (int)$poll->v('schwelle_prozent') ?>"> %</span>
      </p>
    </fieldset>
  <?php else: ?>
    <p class="dazu"><?= Util::esc(t('admin.regeln_fest')) ?></p>
  <?php endif; ?>

  <p class="feld">
    <label for="quorum"><?= Util::esc(t('feld.quorum')) ?></label>
    <span class="einheit"><input type="number" id="quorum" name="quorum" min="0" max="100" step="10"
           value="<?= (int)$poll->v('quorum_prozent') ?>"> %</span>
    <?php erkl('feld.quorum_dazu'); ?>
  </p>

  <p><button type="submit" class="knopf"><?= Util::esc(t('knopf.speichern')) ?></button></p>
</form>

<form method="post" class="karte gefahr">
  <?= Security::tokenFeld('admin') ?>
  <input type="hidden" name="aktion" value="loeschen">
  <h3><?= Util::esc(t('admin.loeschen')) ?></h3>
  <p class="dazu"><?= Util::esc(t('admin.loeschen_dazu')) ?></p>
  <p class="feld">
    <label for="sicher"><?= Util::esc(t('admin.loeschen_tippen')) ?></label>
    <input type="text" id="sicher" name="sicher" autocomplete="off" placeholder="LOESCHEN">
  </p>
  <p><button type="submit" class="knopf gefahr"><?= Util::esc(t('admin.loeschen')) ?></button></p>
</form>
