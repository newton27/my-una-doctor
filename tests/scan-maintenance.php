<?php
/** Real runHealthScan boundary; only the external UNA/database/scanner work is isolated. */
define('BX_DOL', true);
$root = sys_get_temp_dir() . '/doctor-scan-' . bin2hex(random_bytes(6));
mkdir($root . '/site/modules/newton/my_una_doctor/classes', 0700, true);
define('BX_DIRECTORY_PATH_ROOT', $root . '/site/');
define('BX_DIRECTORY_PATH_MODULES', BX_DIRECTORY_PATH_ROOT . 'modules/');
define('BX_DOL_URL_ROOT', 'https://example.invalid/');
define('BX_DOL_URL_MODULES', BX_DOL_URL_ROOT . 'modules/');
define('BX_MAINTENANCE_FILE', '.bx_maintenance');
file_put_contents(BX_DIRECTORY_PATH_MODULES . 'newton/my_una_doctor/classes/MyUnaDoctorScanner.php', '<?php');
foreach (array('MyUnaDoctorMaintenance.php', 'MyUnaMaintenanceLease.php') as $file) {
    $source = dirname(__DIR__) . '/classes/' . $file;
    if (is_file($source)) copy($source, BX_DIRECTORY_PATH_MODULES . 'newton/my_una_doctor/classes/' . $file);
}
class DoctorScanDb {
    public $scans = array();
    public $failSave = false;
    public function getOne($sql, $params = array()) {
        $name = $params[0] ?? '';
        if (strpos($sql, 'COUNT(') !== false) return array_key_exists($name, $GLOBALS['params']) ? 1 : 0;
        return $GLOBALS['params'][$name] ?? false;
    }
    public function getRow($sql, $params = array()) {
        $name = $params[0] ?? '';
        return array_key_exists($name, $GLOBALS['params']) ? array('value' => $GLOBALS['params'][$name]) : array();
    }
    public function query($sql, $bindings) {
        list($value, $name, $expected) = $bindings;
        if (($GLOBALS['failSet'] ?? '') === $name) return false;
        if ($GLOBALS['params'][$name] !== $expected) return 0;
        return setParam($name, $value) ? 1 : false;
    }
    public function cacheParams($force = false) { return true; }
    public function addScan($scan) {
        if ($this->failSave) throw new RuntimeException('save failed');
        $this->scans[] = $scan;
    }
}
class BxDolDb { public static function getInstance() { return $GLOBALS['db']; } }
class BxDolModule {
    protected $_aModule;
    protected $_oDb;
    public function __construct(&$module) { $this->_aModule = $module; $this->_oDb = $GLOBALS['db']; }
}
function getParam($name) { return $GLOBALS['params'][$name] ?? false; }
function setParam($name, $value) {
    if (($GLOBALS['failSet'] ?? '') === $name) return false;
    $GLOBALS['writes'][] = $name;
    $GLOBALS['params'][$name] = (string)$value;
    return true;
}
class MyUnaDoctorScanner {
    public function __construct($module, array $maintenanceOriginal = array()) { $GLOBALS['scannerOriginal'] = $maintenanceOriginal; }
    public function run() { return ($GLOBALS['scanWork'])(); }
}
require dirname(__DIR__) . '/classes/MyUnaDoctorModule.php';
$failed = 0;
function check($label, $condition) {
    global $failed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$condition) ++$failed;
}
function resetScanFixture() {
    $GLOBALS['db'] = new DoctorScanDb();
    $GLOBALS['writes'] = array();
    $GLOBALS['failSet'] = '';
    $GLOBALS['params'] = array(
        'sys_maintenance_mode' => '',
        'sys_maintenance_mode_html' => '<p>Original administrator page</p>',
        'sys_template_cache_compress_enable' => 'on',
        'my_una_doctor_use_maintenance_page' => 'on',
        'my_una_doctor_maintenance_show_header' => '',
        'my_una_doctor_maintenance_show_footer' => 'on',
        'my_una_doctor_maintenance_heading' => 'Site health check',
    );
    $module = array('path' => 'newton/my_una_doctor/');
    return new MyUnaDoctorModule($module);
}
try {
    $doctor = resetScanFixture();
    $original = $GLOBALS['params'];
    $GLOBALS['scanWork'] = static function () {
        check('public native maintenance is active for the actual scan', (bool)getParam('sys_maintenance_mode'));
        check('scanner receives the exact pre-scan diagnostic maintenance state', ($GLOBALS['scannerOriginal']['sys_maintenance_mode'] ?? null) === '' && ($GLOBALS['scannerOriginal']['sys_maintenance_mode_html'] ?? null) === '<p>Original administrator page</p>');
        check('Studio compressed assets remain available during scan', getParam('sys_template_cache_compress_enable') === '');
        $html = getParam('sys_maintenance_mode_html');
        check('configured Doctor page is installed during scan', strpos($html, 'id="mud-maintenance"') !== false && strpos($html, 'Site health check') !== false);
        check('scan does not create the hard-maintenance lock', !file_exists(BX_DIRECTORY_PATH_ROOT . BX_MAINTENANCE_FILE));
        return array('marker' => 'completed');
    };
    check('successful scan result is returned and recorded', $doctor->runHealthScan() === array('marker' => 'completed') && count($GLOBALS['db']->scans) === 1);
    check('successful scan restores exact original native options', $GLOBALS['params'] === $original);

    foreach (array('scanner', 'save') as $failure) {
        $doctor = resetScanFixture();
        $original = $GLOBALS['params'];
        $GLOBALS['db']->failSave = $failure === 'save';
        $GLOBALS['scanWork'] = static function () use ($failure) {
            if ($failure === 'scanner') throw new RuntimeException('scanner failed');
            return array('marker' => 'completed');
        };
        $caught = false;
        try { $doctor->runHealthScan(); } catch (RuntimeException $error) { $caught = true; }
        check($failure . ' failure is propagated and restores original native options', $caught && $GLOBALS['params'] === $original);
    }

    $doctor = resetScanFixture();
    $GLOBALS['params']['sys_maintenance_mode'] = 'administrator-enabled';
    $original = $GLOBALS['params'];
    $GLOBALS['scanWork'] = static function () {
        check('preexisting administrator maintenance and page remain exact', getParam('sys_maintenance_mode') === 'administrator-enabled' && getParam('sys_maintenance_mode_html') === '<p>Original administrator page</p>');
        return array();
    };
    $doctor->runHealthScan();
    check('preexisting maintenance remains enabled after scan', $GLOBALS['params'] === $original);

    $doctor = resetScanFixture();
    $GLOBALS['params']['my_una_doctor_use_maintenance_page'] = '';
    $GLOBALS['scanWork'] = static function () {
        check('page opt-out still locks public access and preserves configured HTML', (bool)getParam('sys_maintenance_mode') && getParam('sys_maintenance_mode_html') === '<p>Original administrator page</p>');
        return array();
    };
    $doctor->runHealthScan();

    $doctor = resetScanFixture();
    $GLOBALS['scanWork'] = static function () {
        setParam('sys_maintenance_mode_html', 'Administrator replaced page');
        setParam('sys_template_cache_compress_enable', 'administrator-value');
        return array();
    };
    $doctor->runHealthScan();
    check('scan cleanup preserves independently changed settings', getParam('sys_maintenance_mode_html') === 'Administrator replaced page' && getParam('sys_template_cache_compress_enable') === 'administrator-value');
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($root);
}
exit($failed ? 1 : 0);
