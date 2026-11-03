<?php
define('BX_DOL', true);
define('BX_DOL_VERSION', '15.0.0-RC3');
$root = sys_get_temp_dir() . '/scan-maintenance-state-' . bin2hex(random_bytes(6));
mkdir($root, 0700, true);
define('BX_DIRECTORY_PATH_ROOT', $root . '/');
class MaintenanceDiagnosticDb { public function getOne($sql) { return BX_DOL_VERSION; } }
class BxDolDb { public static function getInstance() { return new MaintenanceDiagnosticDb(); } }
function getParam($name) { return $name === 'sys_maintenance_mode' ? 'on' : 'temporary scan page'; }
require dirname(__DIR__) . '/classes/MyUnaDoctorScanner.php';
class MaintenanceDiagnosticScanner extends MyUnaDoctorScanner {
    protected function collectMaintenanceUpgradeLogSignals() { return array(); }
    protected function tableExists($table) { return false; }
    public function diagnose() {
        $this->aItems = array();
        $this->scanMaintenanceUpgradeState();
        $this->scanUpgradeReadinessSummary();
        return $this->aItems;
    }
}
$failed = 0;
function check($label, $ok) { global $failed; echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL; if (!$ok) ++$failed; }
try {
    $scanner = new MaintenanceDiagnosticScanner(new stdClass(), array('sys_maintenance_mode' => '', 'sys_maintenance_mode_html' => 'original page'));
    $items = $scanner->diagnose();
    check('temporary scan maintenance does not become a false outage finding', $items[0]['severity'] === 'ok' && $items[0]['evidence']['state'] === 'NORMAL');
    check('temporary scan maintenance does not block upgrade readiness', $items[1]['severity'] === 'ok');
    $scanner = new MaintenanceDiagnosticScanner(new stdClass(), array('sys_maintenance_mode' => 'administrator-enabled', 'sys_maintenance_mode_html' => 'admin page'));
    $items = $scanner->diagnose();
    check('preexisting administrator maintenance remains visible to diagnostics', $items[0]['evidence']['state'] === 'SOFT_MAINTENANCE' && $items[1]['severity'] !== 'ok');
} finally { rmdir($root); }
exit($failed ? 1 : 0);
