<?php if (!defined('SK_EINSTIEG')) { http_response_code(404); exit; } ?>
<?php /** @var string $inhalt  Pfad der einzubindenden Vorlage */ ?>
<!doctype html>
<html lang="<?= Util::esc(I18n::sprache()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= !empty($seitentitel) ? Util::esc((string)$seitentitel) . ' · ' : (isset($poll) && $poll ? Util::esc((string)$poll->v('titel')) . ' · ' : '') ?><?= Util::esc(t('app.name')) ?></title>
<link rel="stylesheet" href="<?= Util::esc(Util::datei('assets/app.css')) ?>">
<link rel="icon" href="data:,">
</head>
<body<?= Util::profi() ? ' class="profi"' : '' ?>>
<a class="sprungmarke" href="#inhalt"><?= Util::esc(t('nav.zum_inhalt')) ?></a>

<header class="kopf">
  <div class="innen kopf-zeile">
    <div class="kopf-links">
      <a class="marke" href="<?= Util::esc(Router::url('')) ?>"><?= Util::esc(t('app.name')) ?></a>
      <?php if (Util::raum() !== null): ?>
        <?php /* Derselbe Umschalter steht in der App (Spezifikation 19). Die Marke
                 fuehrt zur Startseite dieses Teils, der Umschalter in den anderen. */ ?>
        <nav class="suite" aria-label="<?= Util::esc(t('suite.aria')) ?>">
          <a class="aktiv" href="<?= Util::esc(Router::url('')) ?>" aria-current="true"><?= Util::esc(t('suite.online')) ?></a>
          <a href="<?= Util::esc((string)Util::raum()) ?>"><?= Util::esc(t('suite.raum')) ?></a>
        </nav>
      <?php endif; ?>
    </div>
    <?php include __DIR__ . '/_menue.php'; ?>
  </div>
</header>

<?php if (isset($poll) && $poll && $poll->an('ist_beispiel')): ?>
  <?php /* Auf jeder Seite, nicht nur beim Einstieg: Wer den Link weitergibt oder
           spaeter zurueckkommt, soll sofort sehen, dass die Daten erfunden sind. */ ?>
  <div class="beispielband"><div class="innen">
    <?= Util::esc(t('beispiel.band', ['datum' => Util::zeit((int)$poll->v('loeschdatum'), 'd.m.Y')])) ?>
  </div></div>
<?php endif; ?>

<?php if (isset($poll) && $poll): ?>
<div class="pollkopf">
  <div class="innen">
    <h1><?= Util::esc((string)$poll->v('titel')) ?></h1>
    <?php if ((string)$poll->v('beschreibung') !== ''): ?>
      <div class="beschreibung"><?= Util::absaetze((string)$poll->v('beschreibung')) ?></div>
    <?php endif; ?>
    <?php include __DIR__ . '/_phasen.php'; ?>
  </div>
</div>
<?php if ($rolle !== 'ergebnis'): ?>
<nav class="reiter"><div class="innen">
  <?php
  $ansicht = Router::segmente()[2] ?? '';
  $reiter = $rolle === 'admin'
    ? [['', 'nav.uebersicht'], ['redaktion', 'nav.redaktion'], ['teilnehmer', 'nav.teilnehmer'],
       ['ergebnis', 'nav.ergebnis'], ['einstellungen', 'nav.einstellungen'], ['protokoll', 'nav.protokoll']]
    : [['vorschlaege', 'nav.vorschlaege'], ['bewerten', 'nav.bewerten'], ['ergebnis', 'nav.ergebnis'], ['ich', 'nav.ich']];
  foreach ($reiter as [$pfad, $schluessel]):
      $aktiv = ($ansicht === $pfad) || ($ansicht === '' && $pfad === ($rolle === 'admin' ? '' : App::vorgabeAnsichtOeffentlich($poll)));
  ?>
    <a class="<?= $aktiv ? 'aktiv' : '' ?>" href="<?= Util::esc(App::ziel($pfad)) ?>"><?= Util::esc(t($schluessel)) ?></a>
  <?php endforeach; ?>
</div></nav>
<?php endif; ?>
<?php endif; ?>

<main id="inhalt" class="innen">
  <?php if (!empty($meldung)): ?>
    <p class="meldung" role="status"><?= Util::esc(t('meldung.' . $meldung, ['n' => (int)Util::get('n')])) ?></p>
  <?php endif; ?>
  <?php if (!empty($fehler)): ?>
    <p class="meldung fehler" role="alert"><?= Util::esc($fehler) ?></p>
  <?php endif; ?>
  <?php include $inhalt; ?>
</main>

<footer class="fuss">
  <div class="innen">
    <p>
      <a href="<?= Util::esc(Router::url('quelltext')) ?>"><?= Util::esc(t('nav.quelltext')) ?></a> ·
      <a href="<?= Util::esc(Router::url('impressum')) ?>"><?= Util::esc(t('nav.impressum')) ?></a>
      · <?= Util::esc(t('fuss.fassung', ['v' => SK_FASSUNG])) ?>
      <?php if (isset($poll) && $poll): ?>
        · <?= Util::esc(t('fuss.loeschung', ['d' => Util::zeit((int)$poll->v('loeschdatum'), 'd.m.Y')])) ?>
      <?php endif; ?>
    </p>
  </div>
</footer>
<script src="<?= Util::esc(Util::datei('assets/app.js')) ?>" defer></script>
</body>
</html>
