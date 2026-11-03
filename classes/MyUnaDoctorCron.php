<?php defined('BX_DOL') or die('hack attempt');

class MyUnaDoctorCron extends BxDolCron
{
    public function processing()
    {
        if (!class_exists('MyUnaMaintenanceLease', false))
            require_once(__DIR__ . '/MyUnaMaintenanceLease.php');
        $result = MyUnaMaintenanceLease::recover(BX_DIRECTORY_PATH_ROOT);
        if (!$result['ok'] && !$result['busy'])
            throw new RuntimeException('Doctor maintenance recovery failed: ' . implode('; ', $result['errors']));
    }
}
