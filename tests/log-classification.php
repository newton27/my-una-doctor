<?php
// Standalone regression test: no UNA bootstrap, database, files or network.
define('BX_DOL', true);
require dirname(__DIR__) . '/classes/MyUnaDoctorScanner.php';
class DoctorLogClassificationProbe extends MyUnaDoctorScanner
{
    public function __construct() {}
    protected function inspectMissingTranscoderSource($text) { return array(); }
    protected function inspectResolvedHistoricalLogError($text) { return array(); }
    public function classify($text, $fatal, $error) {
        return $this->classifyLogEntry($text, $fatal, $error);
    }
}
$probe = new DoctorLogClassificationProbe();
$poll = '[upgrade] Cannot get a list of modules which require to be updated.';
$cases = array(
    array($poll, false, true, 'una_upgrade_poll', false),
    array('ERROR ' . $poll, false, true, 'una_upgrade_poll', false),
    array($poll, true, true, 'fatal_error', true),
    array('ERROR database connection failed', false, true, 'active_error', true),
    array('[upgrade] Download failed', false, true, 'active_error', true),
    array('Normal informational entry', false, false, 'informational', false),
);
foreach ($cases as $case) {
    $actual = $probe->classify($case[0], $case[1], $case[2]);
    if ($actual['classification'] !== $case[3] || $actual['health_impact'] !== $case[4]) {
        fwrite(STDERR, "FAIL: " . json_encode($case) . "\n");
        exit(1);
    }
}
echo "PASS: six log-classification regressions; fatal and unrelated errors retain health impact.\n";
