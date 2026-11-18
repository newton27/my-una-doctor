<?php defined('BX_DOL') or die('Access denied');
class MyUnaDoctorUpdater extends BxDolStudioUpdater
{
    protected function actionCheckPaused($operation)
    {
        $q=BxDolDb::getLink()->query("SELECT enabled FROM sys_modules WHERE name='my_una_doctor' AND version='0.6.6'");
        $enabled=$q?$q->fetchColumn():false;
        return $enabled!==false&&(int)$enabled===0?BX_DOL_STUDIO_INSTALLER_SUCCESS:['code'=>BX_DOL_STUDIO_INSTALLER_FAILED,'content'=>'Disable My UNA Doctor in Studio before applying this update.'];
    }
}
