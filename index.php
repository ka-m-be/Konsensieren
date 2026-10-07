<?php
declare(strict_types=1);
/**
 * Tool fuer systemisches Konsensieren - einziger Einstiegspunkt.
 * Kein Framework, keine Abhaengigkeiten: was hochgeladen wird, laeuft.
 */
define('SK_EINSTIEG', true);
require __DIR__ . '/lib/bootstrap.php';
App::los();
