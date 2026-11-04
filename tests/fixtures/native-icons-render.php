<?php
// Adapt only the unavailable UNA shell and empty data source. The menu
// constructor and overview HTML are the real Doctor implementation.
define('BX_DOL', true);
define('BX_DOL_URL_STUDIO', 'https://fixture.invalid/studio/');
function bx_get($name) { return false; }
function getParam($name) { return ''; }
class BxTemplStudioModule {
    protected $aMenuItems, $sPageDefault, $sPage;
    public function __construct(...$args) {}
}
class NativeIconDoctorFixture {
    public function getLatestHealthScan() { return []; }
}
require dirname(__DIR__, 2) . '/classes/MyUnaDoctorStudioPage.php';
class NativeIconPageProbe extends MyUnaDoctorStudioPage {
    public function menuIcons() { return array_column($this->aMenuItems, 'icon'); }
    public function withEmptyDoctor() { $this->oDoctor = new NativeIconDoctorFixture(); }
}
$page = new NativeIconPageProbe('my_una_doctor', 'overview');
$page->withEmptyDoctor();
echo json_encode(['menu_icons' => $page->menuIcons(), 'overview_html' => $page->getOverview()]);
