<?php
declare(strict_types=1);
define('BX_DOL', true);
define('BX_DOL_STUDIO_INSTALLER_SUCCESS', 0);
define('BX_DOL_STUDIO_INSTALLER_FAILED', 1);
class BxDolStudioUpdater {}
class BxDolDb { static function getLink() { return $GLOBALS['pdo']; } }
require dirname(__DIR__) . '/upgrade-templates/0.6.6_0.6.7/install/installer.php';
class TestUpdater extends MyUnaDoctorUpdater { function paused() { return $this->actionCheckPaused('update'); } }
function verifyUpdate(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE sys_modules(name TEXT, version TEXT, enabled INTEGER)');
$pdo->exec("INSERT INTO sys_modules VALUES('my_una_doctor','0.6.6',1)");
$updater = new TestUpdater();
verifyUpdate($updater->paused() !== 0, 'Enabled module must be paused before updating.');
$pdo->exec('UPDATE sys_modules SET enabled=0');
verifyUpdate($updater->paused() === 0, 'Correct disabled native version may update.');
$pdo->exec("UPDATE sys_modules SET version='0.6.7'");
verifyUpdate($updater->paused() !== 0, 'Repeated updater is rejected.');
$pdo->exec('DELETE FROM sys_modules');
verifyUpdate($updater->paused() !== 0, 'Unregistered module is rejected.');
require dirname(__DIR__) . '/upgrade-templates/0.6.6_0.6.7/install/config.php';
verifyUpdate($aConfig['version_from'] === '0.6.6' && $aConfig['version_to'] === '0.6.7', 'Native version transition is explicit.');
verifyUpdate(array_keys($aConfig['install']) === ['check_paused', 'update_files', 'update_languages', 'clear_db_cache', 'clear_template_cache'], 'Updater cannot execute full installation SQL.');
$base = json_decode(file_get_contents(__DIR__ . '/fixtures/languages-0.6.6.json'), true, 16, JSON_THROW_ON_ERROR);
$delta = simplexml_load_file(dirname(__DIR__) . '/upgrade-templates/0.6.6_0.6.7/install/langs/en.xml', 'SimpleXMLElement', LIBXML_NONET);
foreach ($delta->children() as $entry) {
    $tag = $entry->getName(); $key = (string)$entry['name'];
    verifyUpdate(in_array($tag, ['string_add', 'string_upd'], true), 'Native delta tag required.');
    verifyUpdate($tag === 'string_add' ? !array_key_exists($key, $base) : array_key_exists($key, $base), 'Delta respects the prior catalog.');
    $base[$key] = (string)$entry;
}
$current = [];
$xml = simplexml_load_file(dirname(__DIR__) . '/install/langs/en.xml', 'SimpleXMLElement', LIBXML_NONET);
foreach ($xml->string as $entry) $current[(string)$entry['name']] = (string)$entry;
ksort($base); ksort($current);
verifyUpdate($base === $current, 'Upgraded language catalog must equal a fresh installation.');
echo "PASS: native updater version, paused-state, SQL exclusion and exact language-delta checks.\n";
