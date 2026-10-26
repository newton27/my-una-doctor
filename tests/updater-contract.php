<?php
/** Official UNA updater class, isolated adapters: NOT a native Studio test. */
define('BX_DOL', true);
define('BX_DOL_STUDIO_INSTALLER_SUCCESS', 0);
define('BX_DOL_STUDIO_INSTALLER_FAILED', 1);
define('BX_FORCE_AUTOUPDATE_MAX_CHANGED_FILES_PERCENT', 0.05);
foreach (array('NOT_FOUND', 'NOT_AVAILABLE', 'WSV_MU', 'WMV', 'MODIFIED', 'CHECKSUM_FAILED') as $i => $name)
    define('BX_DOL_STUDIO_IU_RCE_' . $name, 100 + $i);
function _t($key, ...$args) { return $key; }
function getParam($name) { return $name === 'sys_revision' ? 1 : ''; }
function setParam($name, $value) { return true; }
function verify($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
function removeTree($path) {
    if (!file_exists($path)) return;
    if (is_dir($path)) {
        foreach (new FilesystemIterator($path) as $entry) removeTree($entry->getPathname());
        rmdir($path);
    } else unlink($path);
}
class DoctorUpdaterDbAdapter {
    public $module;
    public $saved = array('settings' => 'custom-value', 'scans' => array('kept'), 'references' => array('kept'));
    public function getModuleByUri($uri) { return $this->module; }
    public function getModulesBy($params) { return $this->module; }
    public function updateModule($set, $where) { $this->module = array_merge($this->module, $set); }
    public function deleteModuleTrackFiles($id) {}
    public function insertModuleTrack($id, $file) {}
}
class DoctorUpdaterFileAdapter {
    public function copy($source, $destination) {
        $destination = BX_DIRECTORY_PATH_ROOT . $destination;
        if (!is_dir($destination)) mkdir($destination, 0777, true);
        foreach (glob($source) as $entry) {
            $target = $destination . '/' . basename($entry);
            if (is_dir($entry)) {
                if (!$this->copy($entry . '/*', substr($target, strlen(BX_DIRECTORY_PATH_ROOT)))) return false;
            } elseif (!copy($entry, $target)) return false;
        }
        return true;
    }
    public function delete($path) { removeTree(BX_DIRECTORY_PATH_ROOT . $path); return true; }
}
// Only platform dependencies are adapted. update() and actionUpdateFiles() below
// come unchanged from the pinned official BxDolStudioUpdater source.
class BxDolStudioInstaller {
    protected $oDb;
    protected $_aConfig, $_sBasePath, $_sHomePath, $_sModulePath;
    protected $_aActions = array();
    public $actions = array();
    public $modified = false;
    public function __construct($config) {
        $this->oDb = $GLOBALS['doctorDb'];
        $this->_aConfig = $config;
        $this->_sBasePath = BX_DIRECTORY_PATH_ROOT . 'modules/';
        $this->_sHomePath = $this->_sBasePath . $config['home_dir'];
    }
    protected function _isCompatibleWith() { return true; }
    protected function _displayResult(...$args) { return ''; }
    protected function _getFileManager() { return new DoctorUpdaterFileAdapter(); }
    public static function getModuleConfig($path) { require $path; return $aConfig; }
    public function hashFiles($path, &$files) {
        $files = array();
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) if ($file->isFile()) $files[] = array('file' => $file->getPathname(), 'hash' => md5_file($file->getPathname()));
    }
    public function hashCheck($files, $id) { return $this->modified ? array(array('locally-edited.php'), 0.1) : array(array(), 0); }
    protected function _perform($operation, $params) {
        foreach ($this->_aConfig[$operation] as $action => $enabled) {
            if (!$enabled) continue;
            $this->actions[] = $action;
            if ($action === 'update_files') {
                if ($this->actionUpdateFiles() !== BX_DOL_STUDIO_INSTALLER_SUCCESS) return array('result' => false);
            } elseif (!in_array($action, array('clear_db_cache', 'clear_template_cache'), true)) {
                throw new RuntimeException('Unexpected updater action: ' . $action);
            }
        }
        return array('result' => true);
    }
    public function delete($params) { removeTree($this->_sHomePath); return array('result' => true); }
}
$core = getenv('DOCTOR_UNA_UPDATER');
verify($core && is_file($core), 'Pinned official updater source is required');
require $core;
$stage = getenv('DOCTOR_UPDATE_FIXTURES');
verify($stage && is_dir($stage), 'Extracted updater archives are required');
$site = sys_get_temp_dir() . '/doctor-updater-contract-' . bin2hex(random_bytes(8)) . '/';
mkdir($site, 0777, true);
define('BX_DIRECTORY_PATH_ROOT', $site);
try {
    for ($minor = 1; $minor <= 5; $minor++) {
        removeTree($site . 'modules');
        $previous = '0.6.' . $minor;
        $wrapper = 'update_' . $previous . '_0.6.6';
        $module = $site . 'modules/newton/my_una_doctor/';
        mkdir($module . 'updates/' . $wrapper, 0777, true);
        $fileManager = new DoctorUpdaterFileAdapter();
        verify($fileManager->copy($stage . '/' . $wrapper . '/*', 'modules/newton/my_una_doctor/updates/' . $wrapper), 'Stage update');
        file_put_contents($module . 'client-local-data.txt', 'preserve-client-data');
        $aConfig = array();
        require $module . 'updates/' . $wrapper . '/install/config.php';
        if (!class_exists('MyUnaDoctorUpdater', false)) require $module . 'updates/' . $wrapper . '/install/installer.php';
        $GLOBALS['doctorDb'] = new DoctorUpdaterDbAdapter();
        $db = $GLOBALS['doctorDb'];
        $db->module = array('id' => 42, 'name' => 'my_una_doctor', 'title' => 'My UNA Doctor', 'version' => '0.5.0', 'enabled' => 1);
        $saved = $db->saved;
        $updater = new MyUnaDoctorUpdater($aConfig);
        $result = $updater->update(array('module_name' => 'my_una_doctor'));
        verify(!$result['result'] && $result['code'] === BX_DOL_STUDIO_IU_RCE_WMV, 'Wrong source version must be rejected');
        verify(!$updater->actions, 'Rejected update changed files');
        $db->module['version'] = $previous;
        $updater = new MyUnaDoctorUpdater($aConfig);
        $updater->modified = true;
        $result = $updater->update(array('module_name' => 'my_una_doctor'));
        verify(!$result['result'] && $result['code'] === BX_DOL_STUDIO_IU_RCE_MODIFIED, 'Modified source must remain blocked');
        $updater = new MyUnaDoctorUpdater($aConfig);
        $result = $updater->update(array('module_name' => 'my_una_doctor'));
        verify($result['result'], 'Update failed for ' . $previous);
        verify($db->module['version'] === '0.6.6' && $db->module['enabled'] === 1, 'Version/enabled state mismatch');
        verify($db->saved === $saved, 'Saved data changed');
        verify(file_get_contents($module . 'client-local-data.txt') === 'preserve-client-data', 'Client file removed');
        verify($updater->actions === array('update_files', 'clear_db_cache', 'clear_template_cache'), 'Unsafe action list');
        verify(!file_exists($module . 'updates/' . $wrapper), 'Core did not clean up update package');
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage . '/' . $wrapper . '/source', FilesystemIterator::SKIP_DOTS)) as $file) {
            if (!$file->isFile()) continue;
            $relative = substr($file->getPathname(), strlen($stage . '/' . $wrapper . '/source/'));
            verify(hash_file('sha256', $file->getPathname()) === hash_file('sha256', $module . $relative), 'Payload mismatch: ' . $relative);
        }
        $result = $updater->update(array('module_name' => 'my_una_doctor'));
        verify(!$result['result'] && $result['code'] === BX_DOL_STUDIO_IU_RCE_WMV, 'Repeated updater must be rejected by version gate');
        echo 'PASS: official updater contract ' . $previous . ' -> 0.6.6; wrong-version and modified-source guards, file identity, saved state and cleanup.' . PHP_EOL;
    }
} finally {
    removeTree($site);
}
