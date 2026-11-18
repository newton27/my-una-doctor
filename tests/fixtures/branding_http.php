<?php
declare(strict_types=1);

// Test-only UNA boundary. This router is served only on loopback during CI.
define('BX_DOL', true);
define('BX_DIRECTORY_PATH_ROOT', getenv('BRANDING_HTTP_ROOT') . '/public_html/');
define('BX_DOL_URL_ROOT', 'http://' . $_SERVER['HTTP_HOST'] . '/');
define('BX_DOL_URL_STUDIO', BX_DOL_URL_ROOT . 'studio/');
define('CHECK_ACTION_RESULT', 'result');
define('CHECK_ACTION_RESULT_ALLOWED', 1);
$_SERVER['DOCUMENT_ROOT'] = BX_DIRECTORY_PATH_ROOT;
$moduleRoot = dirname(__DIR__, 2);
$role = $_GET['role'] ?? 'admin';
$enabled = $role !== 'disabled';
session_start();
$strings = [];
$xml = simplexml_load_file($moduleRoot . '/install/langs/en.xml', 'SimpleXMLElement', LIBXML_NONET);
foreach ($xml->string as $string) $strings[(string)$string['name']] = (string)$string;
function _t($key) { return $GLOBALS['strings'][$key] ?? $key; }
function isLogged() { return $GLOBALS['role'] !== 'guest'; }
function isAdmin() { return in_array($GLOBALS['role'], ['admin', 'disabled'], true); }
function getLoggedId() { return isAdmin() ? 10 : 11; }
function bx_get_logged_profile_id() { return getLoggedId() + 10; }
function MsgBox($text) { return '<p role="alert">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>'; }
function getParam($key) { return $key === 'site_title' ? 'Fixture community' : (str_ends_with($key, 'show_header') || str_ends_with($key, 'show_footer') ? 'on' : ''); }
function checkActionModule(...$args) { return ['result' => 1]; }
class BxDolSession {
    static function getInstance() { return new self(); }
    function getId() { return session_id(); }
    function start($value) {}
    function getValue($key) { return $_SESSION[$key] ?? null; }
    function setValue($key, $value) { $_SESSION[$key] = $value; }
}
class BxDolProfile {
    static function getInstance($id) { return new self(); }
    function getAccountId() { return getLoggedId(); }
    function getStatus() { return 'active'; }
}
class BxDolAccount {
    static function getInstance($id) { return new self(); }
    function getInfo() { return ['id' => getLoggedId()]; }
    function isLocked() { return false; }
    function isConfirmed() { return true; }
}
class ReadOnlyDb {
    function catalog() { return ['settings' => ['enabled' => 1, 'version' => 8, 'review_state' => 'approved', 'rules' => 'Preserved rules'], 'prizes' => [['id' => 9, 'title' => 'Preserved reward', 'kind' => 'coupon', 'weight' => 1]], 'total' => 1, 'has_reward' => true]; }
    function nextAt($id) { return time() + 86400; }
    function history($id) { return []; }
    function __call($name, $args) { throw new RuntimeException('Branding must not mutate rewards: ' . $name); }
}
class FakeConfig { function getBaseUri() { return 'rewards_wheel/'; } }
class FakeTemplate { function addCss($value) {} function addJs($value) {} }
class BxDolModule {
    protected $_aModule = [];
    public $_oConfig;
    public $_oTemplate;
    public $_oDb;
    function __construct() { $this->_oConfig = new FakeConfig(); $this->_oTemplate = new FakeTemplate(); $this->_oDb = new ReadOnlyDb(); }
    function isEnabled() { return $GLOBALS['enabled']; }
    function getName() { return 'nw_rewards_wheel'; }
    static function getInstance($name) { return $GLOBALS['nativeModule']; }
}
class BxTemplStudioModule {
    public $sPageDefault, $sPage, $aMenuItems;
    function __construct(...$args) {}
    function init() {}
}
$nativeModule = new BxDolModule();
if (($_GET['surface'] ?? '') === 'public') {
    require $moduleRoot . '/classes/MyUnaDoctorMaintenance.php';
    echo MyUnaDoctorMaintenance::buildPage(1700000000, 1700000300);
} else {
    require $moduleRoot . '/classes/MyUnaDoctorStudioPage.php';
    $page = new MyUnaDoctorStudioPage('my_una_doctor', 'branding', 'branding');
    $page->init();
    echo $page->getBranding();
}

