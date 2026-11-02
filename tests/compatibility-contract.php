<?php
/** Isolated official installer compatibility check; NOT native Studio acceptance. */
define('BX_DOL', true);
define('BX_DOL_MODULE_TYPE_MODULE', 'module');
class BxDolInstallerUtils {}
function bx_get_ver() { return $GLOBALS['compatibilityVersion']; }
$core = getenv('DOCTOR_UNA_INSTALLER');
if (!$core || !is_file($core)) throw new RuntimeException('Pinned official installer source required');
require $core;
class CompatibilityProbe extends BxDolStudioInstaller {
    public function __construct($config) { $this->_aConfig = $config; }
    public function accepts($version) {
        $GLOBALS['compatibilityVersion'] = $version;
        return $this->_isCompatibleWith();
    }
}
$cases = ['14.0.0' => false, '15.0.0-A1' => true, '15.0.0-RC2' => true,
          '15.0.0' => true, '15.0.4' => true, '16.0.0' => true];
foreach (array_slice($argv, 1) as $path) {
    $aConfig = [];
    require $path;
    if ($aConfig['compatible_with'] !== ['15.0.x']) throw new RuntimeException('Wrong compatibility: ' . $path);
    $probe = new CompatibilityProbe($aConfig);
    foreach ($cases as $version => $expected) {
        if ($probe->accepts($version) !== $expected) throw new RuntimeException('Unexpected result: ' . $path . ' / ' . $version);
    }
    echo 'PASS: unchanged official compatibility method, six versions: ' . $path . PHP_EOL;
}
