<?php
check_logged();
if (empty($aRequest) || empty($aRequest[0]))
    BxDolRequest::processAsFile($aModule, $aRequest);
else
    BxDolRequest::processAsAction($aModule, $aRequest);
