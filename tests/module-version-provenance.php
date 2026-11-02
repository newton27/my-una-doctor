<?php
/** Offline scanner regression: module metadata is read, never executed. */
define('BX_DOL', true);
if (($argv[1] ?? '') !== '--missing-core-version')
    define('BX_DOL_VERSION', '15.0.0-RC3');
$root = sys_get_temp_dir().'/doctor-version-'.bin2hex(random_bytes(6));
mkdir($root.'/modules/system/install', 0700, true);
define('BX_DIRECTORY_PATH_MODULES', $root.'/modules/');
require dirname(__DIR__).'/classes/MyUnaDoctorScanner.php';
class DoctorVersionDbFixture {
    public array $modules = array();
    public function getOne($sql) { return $this->modules[0]['version'] ?? ''; }
    public function getAll($sql) {
        return strpos($sql, 'GROUP BY') !== false ? array() : $this->modules;
    }
}
class DoctorVersionProbe extends MyUnaDoctorScanner {
    public function __construct($db) { $this->oDb = $db; }
    protected function tableExists($table) { return $table === 'sys_modules'; }
    public function readVersion($path, $system = false) { return $this->extractModulePackageVersion($path, $system); }
    public function scanVersions() { $this->aItems = array(); $this->scanModules(); return $this->aItems; }
    public function scanCore() { $this->aItems = array(); $this->scanSystem(); return $this->aItems; }
}
$db = new DoctorVersionDbFixture;
$probe = new DoctorVersionProbe($db);
$failed = 0;
function check($name, $ok) {
    global $failed;
    echo ($ok ? 'PASS ' : 'FAIL ').$name.PHP_EOL;
    if (!$ok) ++$failed;
}
function provenance($items) {
    foreach ($items as $item)
        if (isset($item['evidence']['mismatch_count'])) return $item;
    throw new RuntimeException('Missing aggregate provenance result');
}
$systemPath = $root.'/modules/system/install/config.php';
$constantConfig = '<?php $aConfig = [\'name\' => \'system\', \'version\' => BX_DOL_VERSION];';
try {
    file_put_contents($systemPath, $constantConfig);
    if (($argv[1] ?? '') === '--missing-core-version') {
        check('undefined core version remains unverified', $probe->readVersion($systemPath, true)['status'] === 'unverified');
    } else {
        check('System constant resolves to the loaded RC3 version', $probe->readVersion($systemPath, true)['version'] === '15.0.0-RC3');
        check('ordinary modules cannot borrow the core version', $probe->readVersion($systemPath)['status'] === 'unverified');
        file_put_contents($systemPath, '<?php $aConfig = ["version" => BX_DOL_VERSION . "-custom"];');
        check('computed System versions remain unverified', $probe->readVersion($systemPath, true)['status'] === 'unverified');
        file_put_contents($systemPath, '<?php $aConfig = ["version" => OTHER_VERSION];');
        check('unknown constants remain unverified', $probe->readVersion($systemPath, true)['status'] === 'unverified');
        file_put_contents($systemPath, '<?php $aConfig = ["version" => unsupported()]; $note = "\'version\' => BX_DOL_VERSION,";');
        check('constant text inside another string is not a declaration', $probe->readVersion($systemPath, true)['status'] === 'unverified');
        file_put_contents($systemPath, '<?php $aConfig = ["version" => unsupported(), "nested" => ["version" => BX_DOL_VERSION]];');
        check('a nested version cannot verify an unsupported System declaration', $probe->readVersion($systemPath, true)['status'] === 'unverified');
        file_put_contents($systemPath, '<?php $aConfig = ["version" => unsupported()]; $other = ["version" => BX_DOL_VERSION];');
        check('an unrelated array cannot supply the System version', $probe->readVersion($systemPath, true)['status'] === 'unverified');
        file_put_contents($systemPath, '<?php /* "version" => "old" */ $aConfig = ["version" => "0.1.7"];');
        check('quoted module versions and comment stripping still work', $probe->readVersion($systemPath)['version'] === '0.1.7');
        $marker = $root.'/executed';
        file_put_contents($systemPath, $constantConfig.' file_put_contents('.var_export($marker, true).', "bad");');
        $read = $probe->readVersion($systemPath, true);
        check('reading a core version never executes package code', $read['version'] === '15.0.0-RC3' && !file_exists($marker));
        file_put_contents($systemPath, $constantConfig);
        $db->modules[] = array('name'=>'system', 'title'=>'System', 'version'=>'15.0.0-RC3', 'path'=>'system/', 'class_prefix'=>'Bx', 'enabled'=>1);
        for ($i=0; $i<86; ++$i) {
            $name='example_'.$i;
            mkdir($root.'/modules/'.$name.'/install', 0700, true);
            file_put_contents($root.'/modules/'.$name.'/install/config.php', '<?php $aConfig = ["version" => "0.1.0"];');
            $db->modules[] = array('name'=>$name, 'title'=>$name, 'version'=>'0.1.0', 'path'=>$name.'/', 'class_prefix'=>'Example', 'enabled'=>1);
        }
        $result = provenance($probe->scanVersions());
        check('86 module packages plus matching RC3 System are all verified',
            $result['severity']==='ok' && $result['evidence']['modules_checked']===87 &&
            $result['evidence']['mismatch_count']===0 && $result['evidence']['unverified_count']===0);
        $db->modules[0]['version']='15.0.0-RC2';
        $result = provenance($probe->scanVersions());
        check('a genuine System database/file mismatch remains visible',
            $result['severity']==='warning' && $result['evidence']['mismatch_count']===1 &&
            $result['evidence']['mismatches'][0]['module']==='system' &&
            $result['evidence']['mismatches'][0]['package_version']==='15.0.0-RC3');
        $core = $probe->scanCore();
        check('the independent core file/database mismatch remains critical',
            $core[0]['severity']==='critical' && $core[0]['evidence']['files_version']==='15.0.0-RC3' &&
            $core[0]['evidence']['database_version']==='15.0.0-RC2');
        $db->modules[0]['version']='15.0.0-RC3';
        file_put_contents($systemPath, '<?php $aConfig = ["version" => unsupported()];');
        $result = provenance($probe->scanVersions());
        check('an unsupported System declaration remains explicitly unverified',
            $result['severity']==='info' && $result['evidence']['modules_checked']===86 &&
            $result['evidence']['unverified_count']===1 && $result['evidence']['unverified_modules'][0]['module']==='system');
    }
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($root);
}
exit($failed ? 1 : 0);
