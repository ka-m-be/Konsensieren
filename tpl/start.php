<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<h1><?= Util::esc(t('start.titel')) ?></h1>
<p class="fuehrung"><?= Util::esc(t('start.fuehrung')) ?></p>

<?php if (!Util::profi()): ?>
<details class="hinweis">
  <summary><?= Util::esc(t('hinweis.was_ist_das')) ?></summary>
  <p><?= Util::esc(t('start.erklaerung1')) ?></p>
  <p><?= Util::esc(t('start.erklaerung2')) ?></p>
  <p><a href="<?= Util::esc(Router::url('hilfe')) ?>"><?= Util::esc(t('start.mehr')) ?></a></p>
  <p><a href="<?= Util::esc(Router::url('artikel')) ?>"><?= Util::esc(t('start.artikel')) ?></a></p>
</details>
<?php endif; ?>

<section id="gemerkt" class="karte" hidden>
  <h2><?= Util::esc(t('start.gemerkt')) ?></h2>
</section>

<form method="post" action="<?= Util::esc(Router::url('neu')) ?>" class="karte">
  <?= Security::tokenFeld('neu') ?>
  <h2><?= Util::esc(t('start.neue')) ?></h2>

  <p class="feld">
    <label for="titel"><?= Util::esc(t('feld.titel')) ?></label>
    <input type="text" id="titel" name="titel" required maxlength="200"
           placeholder="<?= Util::esc(t('feld.titel_beispiel')) ?>" value="<?= Util::esc(Util::post('titel')) ?>">
  </p>

  <p class="feld">
    <label for="beschreibung"><?= Util::esc(t('feld.beschreibung')) ?> <span class="dazu"><?= Util::esc(t('feld.freiwillig')) ?></span></label>
    <textarea id="beschreibung" name="beschreibung" rows="3"><?= Util::esc(Util::post('beschreibung')) ?></textarea>
  </p>

  <p class="feld">
    <label for="name"><?= Util::esc(t('feld.dein_name')) ?></label>
    <input type="text" id="name" name="name" required maxlength="60" value="<?= Util::esc(Util::post('name')) ?>">
    <?php erkl('feld.dein_name_dazu'); ?>
  </p>

  <div class="zwei">
    <p class="feld">
      <label for="ende_vorschlag"><?= Util::esc(t('feld.ende_vorschlag')) ?></label>
      <input type="date" id="ende_vorschlag" name="ende_vorschlag" required
             value="<?= Util::esc(Util::post('ende_vorschlag', Util::datumFeld(Util::jetzt() + 7 * 86400))) ?>">
    </p>
    <p class="feld">
      <label for="ende_bewertung"><?= Util::esc(t('feld.ende_bewertung')) ?></label>
      <input type="date" id="ende_bewertung" name="ende_bewertung" required
             value="<?= Util::esc(Util::post('ende_bewertung', Util::datumFeld(Util::jetzt() + 14 * 86400))) ?>">
    </p>
  </div>

  <details class="hinweis mehr">
    <summary><?= Util::esc(t('start.regeln')) ?></summary>

    <p class="feld schalter">
      <label><input type="checkbox" name="ohne_passiv" <?= Util::postAn('ohne_passiv') ? 'checked' : '' ?>>
        <?= Util::esc(t('feld.ohne_passiv')) ?></label>
      <?php erkl('feld.ohne_passiv_dazu'); ?>
    </p>

    <p class="feld">
      <label for="passiv_text"><?= Util::esc(t('feld.passiv_text')) ?></label>
      <input type="text" id="passiv_text" name="passiv_text" maxlength="500"
             value="<?= Util::esc(Util::post('passiv_text', t('passiv.vorgabe'))) ?>">
      <?php erkl('feld.passiv_text_dazu'); ?>
    </p>

    <p class="feld schalter">
      <label><input type="checkbox" name="mit_veto" <?= Util::postAn('mit_veto') ? 'checked' : '' ?>>
        <?= Util::esc(t('feld.mit_veto')) ?></label>
      <span class="dazu warnung"><?= Util::esc(t('feld.mit_veto_dazu')) ?></span>
    </p>

    <p class="feld">
      <label for="schwelle"><?= Util::esc(t('feld.schwelle')) ?></label>
      <?php /* Zahl und Einheit in einer Zeile: Das Feld ist eine Spalte, ein nacktes
               "%" hinter dem Eingabefeld rutschte darunter (0.3.5). */ ?>
      <span class="einheit"><input type="number" id="schwelle" name="schwelle" min="0" max="100" step="5"
             value="<?= Util::esc(Util::post('schwelle', '20')) ?>"> %</span>
      <?php erkl('feld.schwelle_dazu'); ?>
    </p>
    <p class="feld">
      <label for="quorum"><?= Util::esc(t('feld.quorum')) ?></label>
      <span class="einheit"><input type="number" id="quorum" name="quorum" min="0" max="100" step="10"
             value="<?= Util::esc(Util::post('quorum', '50')) ?>"> %</span>
      <?php erkl('feld.quorum_dazu'); ?>
    </p>
  </details>

  <?php if (!empty($kennwort_noetig)): ?>
    <p class="feld">
      <label for="kennwort"><?= Util::esc(t('feld.kennwort')) ?></label>
      <input type="password" id="kennwort" name="kennwort" required>
    </p>
  <?php endif; ?>

  <p><button type="submit" class="knopf gross"><?= Util::esc(t('start.anlegen')) ?></button></p>
  <?php erkl('start.keine_mail', [], 'p'); ?>
</form>

<?php /* Im Profi-Modus (Spezifikation 20) entfallen Beispiel und Raumkarte; auf
         der Hilfeseite bleiben sie, denn dort will man gerade erklaert bekommen. */ ?>
<?php if (!Util::profi()): ?>
<?php include __DIR__ . '/_beispielknopf.php'; ?>
<?php include __DIR__ . '/_raumkarte.php'; ?>
<?php endif; ?>

