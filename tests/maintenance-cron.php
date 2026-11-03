<?php
define('BX_DOL', true);
$root = sys_get_temp_dir() . '/doctor-cron-' . bin2hex(random_bytes(6));
mkdir($root . '/site', 0700, true);
define('BX_DIRECTORY_PATH_ROOT', $root . '/site/');
class BxDolCron {}
class CronLeaseDb {
    public function getRow($sql, $params) { return array('value' => $GLOBALS['params'][$params[0]]); }
    public function query($sql, $bindings) {
        list($value, $name, $expected) = $bindings;
        if ($GLOBALS['params'][$name] !== $expected) return 0;
        $GLOBALS['params'][$name] = $value;
        return 1;
    }
    public function cacheParams($force = false) { return true; }
}
class BxDolDb { public static function getInstance() { return new CronLeaseDb(); } }
require dirname(__DIR__) . '/classes/MyUnaMaintenanceLease.php';
$file = dirname(__DIR__) . '/classes/MyUnaDoctorCron.php';
if (!is_file($file)) { fwrite(STDERR, "FAIL standalone Doctor maintenance recovery cron is missing\n"); rmdir($root . '/site'); rmdir($root); exit(1); }
require $file;
$GLOBALS['params'] = array('sys_maintenance_mode' => '', 'sys_maintenance_mode_html' => 'original page', 'sys_template_cache_compress_enable' => 'on');
$original = $GLOBALS['params'];
$prefix = $root . '/.my-una-maintenance-' . sha1(realpath(BX_DIRECTORY_PATH_ROOT) . DIRECTORY_SEPARATOR);
$failed = 0;
function check($label, $ok) { global $failed; echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL; if (!$ok) ++$failed; }
try {
    $cron = new MyUnaDoctorCron();
    $lease = new MyUnaMaintenanceLease(BX_DIRECTORY_PATH_ROOT);
    $lease->begin('active scan');
    $cron->processing();
    check('scheduled recovery leaves a running scan protected', (bool)$GLOBALS['params']['sys_maintenance_mode']);
    $lease->finish();
    $GLOBALS['params'] = array('sys_maintenance_mode' => 'on', 'sys_maintenance_mode_html' => 'interrupted scan', 'sys_template_cache_compress_enable' => '');
    file_put_contents($prefix . '.json', json_encode(array('schema' => 2, 'applied' => array('sys_maintenance_mode', 'sys_maintenance_mode_html', 'sys_template_cache_compress_enable'), 'pending' => null, 'site_root' => realpath(BX_DIRECTORY_PATH_ROOT) . DIRECTORY_SEPARATOR, 'owner' => 'abandoned', 'original' => $original, 'expected' => $GLOBALS['params'])));
    $cron->processing();
    check('standalone Doctor cron restores an interrupted scan without a new scan or World', $GLOBALS['params'] === $original && !file_exists($prefix . '.json'));
    file_put_contents($prefix . '.json', 'invalid evidence');
    $caught = false;
    try { $cron->processing(); } catch (RuntimeException $error) { $caught = true; }
    check('scheduled recovery surfaces invalid rollback evidence', $caught && file_exists($prefix . '.json'));
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $entry) { if ($entry->isDir()) rmdir($entry->getPathname()); else unlink($entry->getPathname()); }
    rmdir($root);
}
exit($failed ? 1 : 0);
