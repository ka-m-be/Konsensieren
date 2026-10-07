<?php
declare(strict_types=1);

// Direkter Aufruf ueber den Webserver hat hier nichts zu suchen; ueber die
// Kommandozeile (die Werkzeuge in tools/) ist er erlaubt.
if (!defined('SK_EINSTIEG') && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (PHP_VERSION_ID < 70400) {
    http_response_code(500);
    exit('Dieses Werkzeug braucht mindestens PHP 7.4. / This tool needs PHP 7.4 or newer.');
}

/** Fassung dieser Installation - steht im Fuss jeder Seite, damit Rueckmeldungen
    aus einer Testrunde einer bestimmten Fassung zuzuordnen sind. */
define('SK_FASSUNG', '0.3.7');

$wurzel = dirname(__DIR__);
foreach (['Config', 'Util', 'I18n', 'Keys', 'Security', 'Storage', 'Router',
          'Poll', 'Vorschlaege', 'Bewertungen', 'Auswertung', 'Housekeeping',
          'Zip', 'Admin', 'Beispiel', 'Markdown', 'App'] as $klasse) {
    require __DIR__ . '/' . $klasse . '.php';
}

Config::laden($wurzel);
I18n::waehlen(isset($_GET['lang']) ? (string)$_GET['lang'] : null);
Util::profiWaehlen(isset($_GET['profi']) ? (string)$_GET['profi'] : null);
Security::kopfzeilen();

if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');
Storage::verzeichnisSichern(Config::verzeichnis());
Housekeeping::vielleicht();
