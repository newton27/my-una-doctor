<?php defined('BX_DOL') or die('hack attempt');

class MyUnaDoctorModule extends BxDolModule
{
    public function __construct(&$aModule)
    {
        parent::__construct($aModule);
    }

    public function runHealthScan()
    {
        require_once(BX_DIRECTORY_PATH_MODULES . $this->_aModule['path'] . 'classes/MyUnaDoctorScanner.php');
        $oScanner = new MyUnaDoctorScanner($this);
        $aScan = $oScanner->run();
        $this->_oDb->addScan($aScan);
        return $aScan;
    }



    public function getModuleIntegritySnapshot($sModule)
    {
        require_once(
            BX_DIRECTORY_PATH_MODULES
            . $this->_aModule['path']
            . 'classes/MyUnaDoctorScanner.php'
        );

        $oScanner =
            new MyUnaDoctorScanner($this);

        return $oScanner
            ->getModuleIntegritySnapshot(
                (string)$sModule
            );
    }


    public function getRepairPreview($sRule, $sModule = '')
    {
        $sRule = trim((string)$sRule);
        $sModule = trim((string)$sModule);

        /*
         * Current staged repair support includes preview, read-only preflight and guarded arm planning for the first proven
         * high-confidence repair workflow.
         */
        $aSupported = array(
            'runtime_content_update_preparation'
        );

        if (!in_array($sRule, $aSupported, true)) {
            return array(
                'ok' => false,
                'mode' => 'preview',
                'changes_applied' => false,
                'error' => 'This repair rule does not have a supported preview workflow.',
            );
        }

        $aScan = $this->getLatestHealthScan();

        if (empty($aScan) || empty($aScan['items'])) {
            return array(
                'ok' => false,
                'mode' => 'preview',
                'changes_applied' => false,
                'error' => 'No health scan is available. Run a health scan first.',
            );
        }

        $aMatch = array();

        foreach ($aScan['items'] as $aItem) {
            if (
                !isset($aItem['category']) ||
                $aItem['category'] !== 'repairs'
            ) {
                continue;
            }

            if (
                empty($aItem['evidence']['active_candidates']) ||
                !is_array(
                    $aItem['evidence']['active_candidates']
                )
            ) {
                continue;
            }

            foreach (
                $aItem['evidence']['active_candidates']
                as $aCandidate
            ) {
                if (
                    !isset($aCandidate['rule']) ||
                    $aCandidate['rule'] !== $sRule
                ) {
                    continue;
                }

                if (
                    $sModule !== '' &&
                    (
                        !isset($aCandidate['module']) ||
                        $aCandidate['module'] !== $sModule
                    )
                ) {
                    continue;
                }

                $aMatch = $aCandidate;
                break 2;
            }
        }

        if (empty($aMatch)) {
            return array(
                'ok' => false,
                'mode' => 'preview',
                'changes_applied' => false,
                'error' => 'The requested repair candidate is not present in the latest Doctor scan.',
            );
        }

        if (
            !isset($aMatch['automatic_repair']) ||
            $aMatch['automatic_repair'] !== false
        ) {
            return array(
                'ok' => false,
                'mode' => 'preview',
                'changes_applied' => false,
                'error' => 'Safety stop: this candidate does not explicitly declare automatic repair as disabled.',
            );
        }

        if (
            isset($aMatch['source_integrity']) &&
            $aMatch['source_integrity'] !== 'clean'
        ) {
            return array(
                'ok' => false,
                'mode' => 'preview',
                'changes_applied' => false,
                'error' => 'Safety stop: runtime-content update preparation requires clean tracked module source.',
            );
        }

        $sTargetModule = isset($aMatch['module'])
            ? (string)$aMatch['module']
            : $sModule;

        $sTitle = isset($aMatch['title'])
            ? (string)$aMatch['title']
            : $sTargetModule;

        $sDirectory = isset($aMatch['directory'])
            ? (string)$aMatch['directory']
            : '';

        $iRuntimeFiles = isset(
            $aMatch['runtime_file_count']
        )
            ? (int)$aMatch['runtime_file_count']
            : 0;

        $aWorkflow = !empty(
            $aMatch['recommended_workflow']
        ) && is_array(
            $aMatch['recommended_workflow']
        )
            ? $aMatch['recommended_workflow']
            : array();

        return array(
            'ok' => true,

            'mode' => 'preview',

            'changes_applied' => false,

            'rule' => $sRule,

            'module' => $sTargetModule,

            'title' => $sTitle,

            'confidence' => isset($aMatch['confidence'])
                ? (string)$aMatch['confidence']
                : 'unknown',

            'source_integrity' => isset(
                $aMatch['source_integrity']
            )
                ? (string)$aMatch['source_integrity']
                : 'unknown',

            'runtime_directory' => $sDirectory,

            'runtime_file_count' => $iRuntimeFiles,

            'automatic_repair' => false,

            'generator_discovery' =>
                isset($aMatch['generator_discovery'])
                && is_array($aMatch['generator_discovery'])
                    ? $aMatch['generator_discovery']
                    : array(),

            'preview_id' => substr(
                hash(
                    'sha256',
                    $sRule
                    . '|'
                    . $sTargetModule
                    . '|'
                    . $sDirectory
                    . '|'
                    . $iRuntimeFiles
                    . '|'
                    . (
                        isset($aScan['finished'])
                            ? (string)$aScan['finished']
                            : ''
                    )
                ),
                0,
                16
            ),

            'safety_backup_root' =>
                'tmp/my-una-doctor-repairs/'
                . (
                    $sTargetModule !== ''
                        ? $sTargetModule
                        : 'module'
                )
                . '/',

            'preconditions' => array(
                'The repair candidate must still exist in the latest Doctor scan.',
                'Tracked module source must remain checksum-clean.',
                'The runtime directory must still exist inside the module tree before any future Apply operation.',
                'The process that generates or imports runtime content must be paused before files are moved.',
                'A safety backup or My UNA World snapshot should exist before a future Apply operation.',
                'Administrator approval is required. Preview never counts as approval.'
            ),

            'proposed_steps' => $aWorkflow,

            'validation_after_future_apply' => array(
                'Confirm the protected runtime directory exists outside the module source tree.',
                'Run My UNA Doctor again.',
                'Confirm tracked source integrity is still clean.',
                'Confirm UNA Studio update readiness no longer counts the protected runtime files.',
                'After the approved module update, restore the runtime directory.',
                'Verify module operation and runtime content.',
                'Resume the module generator or import process only after validation succeeds.'
            ),

            'rollback_plan' => array(
                'Do not delete runtime content.',
                'If update preparation fails, move the protected runtime directory back to its original module location.',
                'Verify file count and directory accessibility.',
                'Run Doctor again before resuming the module generator or import process.'
            ),

            'warnings' => array(
                'This is a dry-run preview only.',
                'No files are moved.',
                'No cron jobs are changed.',
                'No database rows are changed.',
                'No module update is started.',
                'No module checksum baseline is rewritten.'
            ),
        );
    }


    public function getRepairPreflight($sRule, $sModule = '')
    {
        $aPreview = $this->getRepairPreview(
            $sRule,
            $sModule
        );

        $aResult = array(
            'ok' => false,
            'ready' => false,
            'mode' => 'preflight',
            'changes_applied' => false,
            'checks' => array(),
            'preview' => $aPreview,
        );

        if (empty($aPreview['ok'])) {
            $aResult['error'] = isset($aPreview['error'])
                ? $aPreview['error']
                : 'Repair preview is unavailable.';

            return $aResult;
        }

        if (
            $sRule
            !== 'runtime_content_update_preparation'
        ) {
            $aResult['error'] =
                'This repair rule does not have a supported preflight implementation.';

            return $aResult;
        }

        $oDb = BxDolDb::getInstance();

        $sModuleName =
            isset($aPreview['module'])
                ? (string)$aPreview['module']
                : trim((string)$sModule);

        $aModule = $oDb->getRow(
            "SELECT `id`, `name`, `title`, `version`,
                    `path`, `enabled`
             FROM `sys_modules`
             WHERE `name` = ?
             LIMIT 1",
            array($sModuleName)
        );

        if (empty($aModule)) {
            $aResult['error'] =
                'The candidate module is no longer registered.';

            return $aResult;
        }

        $addCheck = function(
            $sKey,
            $sLabel,
            $sStatus,
            $sDetail
        ) use (&$aResult) {
            $aResult['checks'][] = array(
                'key' => (string)$sKey,
                'label' => (string)$sLabel,
                'status' => (string)$sStatus,
                'detail' => (string)$sDetail,
            );
        };

        /*
         * Process identity is important because shell/root
         * writability is not proof that Studio PHP can write.
         */
        $iUid = function_exists('posix_geteuid')
            ? posix_geteuid()
            : null;

        $iGid = function_exists('posix_getegid')
            ? posix_getegid()
            : null;

        $sUser = '';

        if (
            $iUid !== null &&
            function_exists('posix_getpwuid')
        ) {
            $aPw = posix_getpwuid($iUid);

            if (
                is_array($aPw) &&
                isset($aPw['name'])
            ) {
                $sUser = (string)$aPw['name'];
            }
        }

        $aResult['process'] = array(
            'sapi' => php_sapi_name(),
            'uid' => $iUid,
            'gid' => $iGid,
            'user' => $sUser,
        );

        /*
         * A CLI/root test is diagnostic only.
         * Final readiness must be proven through UNA Studio.
         */
        $bStudioContext =
            php_sapi_name() !== 'cli';

        $addCheck(
            'execution_context',
            'Preflight execution context',
            $bStudioContext
                ? 'pass'
                : 'fail',
            $bStudioContext
                ? 'Preflight is running through a web PHP context.'
                : 'CLI preflight is diagnostic only. Run Preflight from UNA Studio to verify the actual web PHP identity and permissions.'
        );

        $addCheck(
            'module_registered',
            'Module registration',
            'pass',
            $aModule['title']
                . ' '
                . $aModule['version']
                . ' is registered as '
                . $aModule['name']
                . '.'
        );

        $addCheck(
            'module_enabled',
            'Module enabled',
            (int)$aModule['enabled'] === 1
                ? 'pass'
                : 'fail',
            (int)$aModule['enabled'] === 1
                ? 'The module is currently enabled.'
                : 'The module is not currently enabled.'
        );

        $sModuleRoot =
            rtrim(
                BX_DIRECTORY_PATH_MODULES
                . $aModule['path'],
                '/\\'
            );

        $sModuleReal = realpath($sModuleRoot);

        if (
            $sModuleReal === false ||
            !is_dir($sModuleReal)
        ) {
            $addCheck(
                'module_root',
                'Module directory',
                'fail',
                'The registered module directory could not be resolved.'
            );

            $aResult['error'] =
                'Module root is unavailable.';

            return $aResult;
        }

        $addCheck(
            'module_root',
            'Module directory',
            'pass',
            $sModuleReal
        );

        /*
         * Runtime directory supplied by the verified candidate.
         */
        $sRuntimeRelative =
            isset($aPreview['runtime_directory'])
                ? trim(
                    (string)$aPreview[
                        'runtime_directory'
                    ],
                    '/\\'
                )
                : '';

        if (
            $sRuntimeRelative === '' ||
            strpos($sRuntimeRelative, '..') !== false ||
            strpos($sRuntimeRelative, "\0") !== false
        ) {
            $addCheck(
                'runtime_path',
                'Runtime directory path',
                'fail',
                'The candidate runtime path is unsafe.'
            );

            $aResult['error'] =
                'Unsafe runtime directory path.';

            return $aResult;
        }

        $sRuntimePath =
            $sModuleReal
            . DIRECTORY_SEPARATOR
            . str_replace(
                array('/', '\\'),
                DIRECTORY_SEPARATOR,
                $sRuntimeRelative
            );

        $sRuntimeReal = realpath($sRuntimePath);

        if (
            $sRuntimeReal === false ||
            !is_dir($sRuntimeReal)
        ) {
            $addCheck(
                'runtime_exists',
                'Runtime directory exists',
                'fail',
                $sRuntimePath
            );

            $aResult['error'] =
                'Runtime directory no longer exists.';

            return $aResult;
        }

        $sModulePrefix =
            rtrim($sModuleReal, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR;

        if (
            strpos(
                $sRuntimeReal . DIRECTORY_SEPARATOR,
                $sModulePrefix
            ) !== 0
        ) {
            $addCheck(
                'runtime_scope',
                'Runtime directory scope',
                'fail',
                'Resolved runtime path is outside the module tree.'
            );

            $aResult['error'] =
                'Runtime path escaped the module tree.';

            return $aResult;
        }

        if (is_link($sRuntimePath)) {
            $addCheck(
                'runtime_symlink',
                'Runtime directory is not a symlink',
                'fail',
                'The runtime directory is a symbolic link.'
            );
        } else {
            $addCheck(
                'runtime_symlink',
                'Runtime directory is not a symlink',
                'pass',
                'The runtime directory resolves directly inside the module tree.'
            );
        }

        /*
         * Count current runtime files and bytes.
         */
        $iFiles = 0;
        $iBytes = 0;
        $iTrackedRuntimeFiles = 0;
        $iUntrackedRuntimeFiles = 0;
        $sCountError = '';

        $aTrackedRows = $oDb->getAll(
            "SELECT `file`
             FROM `sys_modules_file_tracks`
             WHERE `module_id` = ?",
            array((int)$aModule['id'])
        );

        $aTrackedFiles = array();

        foreach ($aTrackedRows as $aTrackedRow) {
            $sTrackedFile = str_replace(
                '\\',
                '/',
                ltrim(
                    (string)$aTrackedRow['file'],
                    '/\\'
                )
            );

            $aTrackedFiles[$sTrackedFile] = true;
        }

        $sModuleBaseNormalized =
            rtrim(
                str_replace(
                    '\\',
                    '/',
                    $sModuleReal
                ),
                '/'
            )
            . '/';

        try {
            $oDirectory =
                new RecursiveDirectoryIterator(
                    $sRuntimeReal,
                    FilesystemIterator::SKIP_DOTS
                );

            $oIterator =
                new RecursiveIteratorIterator(
                    $oDirectory,
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

            foreach ($oIterator as $oFile) {
                if (!$oFile->isFile())
                    continue;

                $iFiles++;
                $iBytes += (int)$oFile->getSize();

                $sCurrentPath = str_replace(
                    '\\',
                    '/',
                    $oFile->getPathname()
                );

                $sRelative = '';

                if (
                    strpos(
                        $sCurrentPath,
                        $sModuleBaseNormalized
                    ) === 0
                ) {
                    $sRelative = substr(
                        $sCurrentPath,
                        strlen($sModuleBaseNormalized)
                    );
                }

                if (
                    $sRelative !== '' &&
                    isset($aTrackedFiles[$sRelative])
                ) {
                    $iTrackedRuntimeFiles++;
                } else {
                    $iUntrackedRuntimeFiles++;
                }
            }

        } catch (Throwable $e) {
            $sCountError = $e->getMessage();
        }

        $aResult['runtime'] = array(
            'relative_directory' =>
                $sRuntimeRelative . '/',
            'path' => $sRuntimeReal,
            'file_count' => $iFiles,
            'tracked_file_count' =>
                $iTrackedRuntimeFiles,
            'untracked_file_count' =>
                $iUntrackedRuntimeFiles,
            'bytes' => $iBytes,
        );

        if ($sCountError !== '') {
            $addCheck(
                'runtime_inventory',
                'Runtime content inventory',
                'fail',
                $sCountError
            );
        } else {
            $addCheck(
                'runtime_inventory',
                'Runtime content inventory',
                'pass',
                number_format($iFiles)
                    . ' file(s), '
                    . number_format($iBytes)
                    . ' bytes.'
            );
        }

        /*
         * Candidate freshness.
         * If cron imported another file after the health scan,
         * Apply must not trust the old snapshot of reality.
         */
        $iExpectedFiles =
            isset($aPreview['runtime_file_count'])
                ? (int)$aPreview[
                    'runtime_file_count'
                ]
                : -1;

        $bFresh =
            $iExpectedFiles ===
            $iUntrackedRuntimeFiles;

        $addCheck(
            'candidate_freshness',
            'Latest scan matches live runtime content',
            $bFresh ? 'pass' : 'fail',
            $bFresh
                ? 'Latest scan and live directory both report '
                    . number_format(
                        $iUntrackedRuntimeFiles
                    )
                    . ' untracked runtime file(s). '
                    . number_format(
                        $iTrackedRuntimeFiles
                    )
                    . ' tracked file(s) also exist in the runtime directory.'
                : 'Latest scan reports '
                    . number_format($iExpectedFiles)
                    . ' untracked runtime file(s), but live inspection found '
                    . number_format(
                        $iUntrackedRuntimeFiles
                    )
                    . '. Run a new Doctor scan before proceeding.'
        );

        /*
         * Source integrity was established by the current
         * repair candidate.
         */
        $bSourceClean =
            isset($aPreview['source_integrity']) &&
            $aPreview['source_integrity'] === 'clean';

        $addCheck(
            'source_integrity',
            'Tracked source integrity',
            $bSourceClean ? 'pass' : 'fail',
            $bSourceClean
                ? 'Tracked module source is checksum-clean.'
                : 'Tracked module source is not verified as clean.'
        );

        /*
         * Repair destination validation.
         * Preflight does NOT create or chmod anything.
         */
        $sBackupRelative =
            isset($aPreview['safety_backup_root'])
                ? trim(
                    (string)$aPreview[
                        'safety_backup_root'
                    ],
                    '/\\'
                )
                : '';

        $sAllowedPrefix =
            'tmp/my-una-doctor-repairs/';

        $bBackupSafe =
            $sBackupRelative !== '' &&
            strpos(
                str_replace(
                    '\\',
                    '/',
                    $sBackupRelative
                ) . '/',
                $sAllowedPrefix
            ) === 0 &&
            strpos($sBackupRelative, '..') === false;

        if (!$bBackupSafe) {
            $addCheck(
                'backup_scope',
                'Safety destination scope',
                'fail',
                'Safety destination is outside Doctor repair storage.'
            );

            $sBackupPath = '';
            $sBackupReal = false;

        } else {
            $sBackupPath =
                BX_DIRECTORY_PATH_ROOT
                . $sBackupRelative;

            $sBackupReal =
                realpath($sBackupPath);

            $addCheck(
                'backup_scope',
                'Safety destination scope',
                'pass',
                $sBackupPath
            );
        }

        $bBackupExists =
            $sBackupReal !== false &&
            is_dir($sBackupReal);

        $addCheck(
            'backup_exists',
            'Safety destination exists',
            $bBackupExists ? 'pass' : 'fail',
            $bBackupExists
                ? $sBackupReal
                : 'The safety destination does not currently exist.'
        );

        $bBackupWritable =
            $bBackupExists &&
            is_writable($sBackupReal);

        $addCheck(
            'backup_writable',
            'Safety destination writable by this PHP process',
            $bBackupWritable ? 'pass' : 'fail',
            $bBackupWritable
                ? 'Writable by current PHP process'
                    . (
                        $sUser !== ''
                            ? ' (' . $sUser . ')'
                            : ''
                    )
                    . '.'
                : 'Not writable by the current PHP process'
                    . (
                        $sUser !== ''
                            ? ' (' . $sUser . ')'
                            : ''
                    )
                    . '.'
        );

        $bModuleWritable =
            is_writable($sModuleReal);

        $addCheck(
            'module_parent_writable',
            'Module directory writable for a future move',
            $bModuleWritable ? 'pass' : 'fail',
            $bModuleWritable
                ? 'Current PHP process can write the module directory.'
                : 'Current PHP process cannot write the module directory.'
        );

        /*
         * Filesystem/device comparison.
         * Same-device rename avoids copying 2.9 GB merely to
         * protect the directory during an update.
         */
        $aSourceStat = @stat($sRuntimeReal);

        $aTargetStat =
            $bBackupExists
                ? @stat($sBackupReal)
                : false;

        $bSameFilesystem =
            is_array($aSourceStat) &&
            is_array($aTargetStat) &&
            isset($aSourceStat['dev']) &&
            isset($aTargetStat['dev']) &&
            $aSourceStat['dev']
                === $aTargetStat['dev'];

        $addCheck(
            'same_filesystem',
            'Runtime and safety destination share a filesystem',
            $bSameFilesystem ? 'pass' : 'warn',
            $bSameFilesystem
                ? 'A future directory rename can remain on the same filesystem.'
                : 'Source and destination are not confirmed on the same filesystem; a future move could require copying data.'
        );

        $iFree =
            $bBackupExists
                ? (int)@disk_free_space(
                    $sBackupReal
                )
                : 0;

        $aResult['safety_destination'] = array(
            'path' =>
                $sBackupReal !== false
                    ? $sBackupReal
                    : $sBackupPath,

            'exists' =>
                $bBackupExists,

            'writable' =>
                $bBackupWritable,

            'device' =>
                is_array($aTargetStat)
                && isset($aTargetStat['dev'])
                    ? $aTargetStat['dev']
                    : null,

            'free_bytes' =>
                $iFree,
        );

        $aResult['storage'] = array(
            'runtime_bytes' => $iBytes,
            'destination_free_bytes' => $iFree,
            'same_filesystem' =>
                $bSameFilesystem,
        );

        $bSpace =
            $iFree > 0 &&
            (
                $bSameFilesystem ||
                $iFree >= (
                    $iBytes
                    + 536870912
                )
            );

        $addCheck(
            'free_space',
            'Safety destination capacity',
            $bSpace ? 'pass' : 'fail',
            number_format($iFree)
                . ' free byte(s); runtime content uses '
                . number_format($iBytes)
                . ' byte(s).'
        );

        /*
         * Re-verify the generator discovered during the health scan.
         *
         * Do not assume a naming convention such as
         * <module>_publishing. Discovery is based on actual
         * module file-path ownership.
         */
        $aDiscovery =
            isset($aPreview['generator_discovery'])
            && is_array($aPreview['generator_discovery'])
                ? $aPreview['generator_discovery']
                : array();

        $aDiscoveredCron =
            isset($aDiscovery['candidates'])
            && is_array($aDiscovery['candidates'])
                ? $aDiscovery['candidates']
                : array();

        $aResult['generator_discovery'] =
            $aDiscovery;

        $bCronExact =
            count($aDiscoveredCron) === 1;

        $aCronRows = array();

        if ($bCronExact) {
            $aDiscovered =
                $aDiscoveredCron[0];

            $iCronId =
                (int)($aDiscovered['id'] ?? 0);

            $aCron = $iCronId > 0
                ? $oDb->getRow(
                    "SELECT `id`, `name`, `time`, `class`,
                            `file`, `service_call`, `ts`, `timing`
                     FROM `sys_cron_jobs`
                     WHERE `id` = ?
                     LIMIT 1",
                    array($iCronId)
                )
                : array();

            if (!empty($aCron))
                $aCronRows[] = $aCron;

            $sExpectedPrefix =
                'modules/'
                . trim(
                    str_replace(
                        '\\',
                        '/',
                        (string)$aModule['path']
                    ),
                    '/'
                )
                . '/';

            $sLiveFile = !empty($aCron)
                ? str_replace(
                    '\\',
                    '/',
                    (string)$aCron['file']
                )
                : '';

            $bOwned =
                $sLiveFile !== ''
                && strpos(
                    $sLiveFile,
                    $sExpectedPrefix
                ) === 0;

            $bIdentityMatch =
                !empty($aCron)
                && (int)$aCron['id']
                    === (int)($aDiscovered['id'] ?? 0)
                && (string)$aCron['name']
                    === (string)($aDiscovered['name'] ?? '')
                && (string)$aCron['class']
                    === (string)($aDiscovered['class'] ?? '')
                && (string)$aCron['file']
                    === (string)($aDiscovered['file'] ?? '');

            $bCronVerified =
                $bOwned
                && $bIdentityMatch;

            $addCheck(
                'cron_target',
                'Exact generator/import cron identified',
                $bCronVerified ? 'pass' : 'fail',
                !empty($aCron)
                    ? 'ID '
                        . $aCron['id']
                        . ' / '
                        . $aCron['name']
                        . ' / schedule '
                        . $aCron['time']
                        . ' / class '
                        . $aCron['class']
                        . ' / '
                        . $aCron['file']
                    : 'The previously discovered cron row no longer exists.'
            );

            if ($bCronVerified) {
                $aResult['cron_pause_target'] =
                    $aCron;
            }

        } else {
            $addCheck(
                'cron_target',
                'Exact generator/import cron identified',
                'fail',
                'Generator discovery found '
                    . count($aDiscoveredCron)
                    . ' module-owned cron candidate(s). '
                    . 'Automatic pause requires exactly one proven candidate.'
            );
        }

        $aResult['cron_matches'] =
            $aCronRows;

        /*
         * No Apply implementation exists yet.
         */
        $aResult['future_mutations'] = array(
            'Pause only the exact verified publishing cron through the explicitly approved repair control barrier.',
            'Protect the runtime directory outside the module tree without deleting it.',
            'Never touch unrelated cron rows such as mz_evids_publishing.',
            'Never re-baseline generated runtime files.',
        );

        $aResult['warnings'] = array(
            'Preflight is read-only.',
            'No cron rows were changed.',
            'No files were moved.',
            'No directories were created, removed or chmodded.',
            'No database rows were changed.',
            'No module update was started.',
            'No checksum baseline was rewritten.',
        );

        $bFailure = false;

        foreach ($aResult['checks'] as $aCheck) {
            if ($aCheck['status'] === 'fail') {
                $bFailure = true;
                break;
            }
        }

        $aResult['ok'] = true;
        $aResult['ready'] = !$bFailure;

        $aVerifiedCron =
            isset($aResult['cron_pause_target'])
            && is_array($aResult['cron_pause_target'])
                ? $aResult['cron_pause_target']
                : array();

        $aPreflightFingerprint = array(
            'preview_id' =>
                $aPreview['preview_id'] ?? '',

            'module' =>
                $sModuleName,

            'runtime_path' =>
                $sRuntimeReal,

            'runtime_total_files' =>
                $iFiles,

            'runtime_tracked_files' =>
                $iTrackedRuntimeFiles,

            'runtime_untracked_files' =>
                $iUntrackedRuntimeFiles,

            'runtime_bytes' =>
                $iBytes,

            'safety_path' =>
                $aResult['safety_destination']['path']
                ?? '',

            'safety_device' =>
                $aResult['safety_destination']['device']
                ?? null,

            'cron_id' =>
                $aVerifiedCron['id'] ?? '',

            'cron_name' =>
                $aVerifiedCron['name'] ?? '',

            'cron_time' =>
                $aVerifiedCron['time'] ?? '',

            'cron_class' =>
                $aVerifiedCron['class'] ?? '',

            'cron_file' =>
                $aVerifiedCron['file'] ?? '',
        );

        $sPreflightFingerprint = json_encode(
            $aPreflightFingerprint,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($sPreflightFingerprint === false)
            $sPreflightFingerprint = serialize(
                $aPreflightFingerprint
            );

        $aResult['preflight_id'] = substr(
            hash(
                'sha256',
                $sPreflightFingerprint
            ),
            0,
            16
        );

        return $aResult;
    }


    /**
     * Build a read-only repair arming plan.
     *
     * No maintenance setting, cron row, file, database record,
     * or checksum baseline is changed here.
     */
    public function getRepairArmPlan($sRule, $sModule = '')
    {
        $aPreflight = $this->getRepairPreflight(
            $sRule,
            $sModule
        );

        $aResult = array(
            'executed' => true,
            'ok' => false,
            'ready' => false,
            'changes_applied' => false,
            'automatic_repair' => false,
            'rule' => $sRule,
            'module' => $sModule,
            'preflight_id' => $aPreflight['preflight_id'] ?? '',
        );

        if (
            empty($aPreflight['ok'])
            || empty($aPreflight['ready'])
        ) {
            $aResult['error'] =
                'Read-only preflight must pass before a repair can be armed.';

            return $aResult;
        }

        $aCron =
            $aPreflight['cron_pause_target']
            ?? array();

        if (empty($aCron['id'])) {
            $aResult['error'] =
                'No exact verified generator target is available.';

            return $aResult;
        }

        $sSoftMaintenance =
            (string)getParam('sys_maintenance_mode');

        $iMaintenanceHtmlOptions =
            (int)$this->_oDb->getOne(
                "SELECT COUNT(*)
                 FROM `sys_options`
                 WHERE `name` = ?",
                array('sys_maintenance_mode_html')
            );

        if ($iMaintenanceHtmlOptions !== 1) {
            $aResult['error'] =
                'UNA soft-maintenance HTML option is unavailable. Doctor cannot install the guarded maintenance landing page.';

            return $aResult;
        }

        $sSoftMaintenanceHtml =
            (string)getParam(
                'sys_maintenance_mode_html'
            );

        $sTemplateCompression =
            (string)getParam(
                'sys_template_cache_compress_enable'
            );

        $bHardMaintenance =
            file_exists(
                BX_DIRECTORY_PATH_ROOT
                . BX_MAINTENANCE_FILE
            );

        $aResult['original_state'] = array(
            'soft_maintenance_value' =>
                $sSoftMaintenance,

            'soft_maintenance_enabled' =>
                !empty($sSoftMaintenance),

            'soft_maintenance_html_value' =>
                $sSoftMaintenanceHtml,

            'soft_maintenance_html_sha256' =>
                hash(
                    'sha256',
                    $sSoftMaintenanceHtml
                ),

            'template_cache_compress_value' =>
                $sTemplateCompression,

            'template_cache_compress_enabled' =>
                $sTemplateCompression === 'on',

            'hard_maintenance_file' =>
                $bHardMaintenance,

            'generator' => array(
                'id' => (int)$aCron['id'],
                'name' => (string)$aCron['name'],
                'time' => (string)$aCron['time'],
                'class' => (string)$aCron['class'],
                'file' => (string)$aCron['file'],
                'service_call' =>
                    (string)($aCron['service_call'] ?? ''),
                'ts' => (int)($aCron['ts'] ?? 0),
                'timing' => (float)($aCron['timing'] ?? 0),
            ),
        );

        $aResult['runtime'] =
            $aPreflight['runtime']
            ?? array();

        $aResult['safety_destination'] =
            $aPreflight['safety_destination']
            ?? array();

        $aResult['planned_pause'] = array(
            'generator_id' => (int)$aCron['id'],
            'original_schedule' =>
                (string)$aCron['time'],
            'pause_schedule' => '0 0 31 2 *',
            'cache_to_clear' => 'sys_cron_jobs',
        );

        $aResult['planned_steps'] = array(
            'Preserve the exact original maintenance HTML, maintenance switch and template-compression state.',
            'Disable UNA template cache compression so Studio CSS/JS use direct cache_public URLs.',
            'Verify the template-compression repair state before enabling maintenance.',
            'If Doctor is activating maintenance, stage the self-contained My UNA Doctor landing page with a live countdown.',
            'Enable UNA soft maintenance only if it is currently off.',
            'Re-verify the exact discovered generator row.',
            'Pause only that row using the impossible schedule 0 0 31 2 *.',
            'Clear the sys_cron_jobs cache.',
            'Re-read and verify the paused generator state.',
            'Stop before any runtime files are moved.',
        );

        $aResult['rollback_plan'] = array(
            'Restore the exact original generator schedule.',
            'Clear the sys_cron_jobs cache again.',
            'Restore the exact original soft-maintenance value.',
            'Restore the exact original soft-maintenance HTML value.',
            'Restore the exact original template-cache compression value.',
        );

        if ($bHardMaintenance) {
            $aResult['error'] =
                'Hard UNA maintenance mode is already active. '
                . 'Interactive repair arming is not allowed.';

            return $aResult;
        }

        $aResult['arm_id'] = substr(
            hash(
                'sha256',
                ($aResult['preflight_id'] ?? '')
                . '|'
                . (int)$aCron['id']
                . '|'
                . (string)$aCron['time']
                . '|'
                . $sSoftMaintenance
                . '|'
                . hash(
                    'sha256',
                    $sSoftMaintenanceHtml
                )
                . '|'
                . $sTemplateCompression
            ),
            0,
            16
        );

        $aResult['ok'] = true;
        $aResult['ready'] = true;

        return $aResult;
    }



    protected function rollbackRepairArmState(
        array $aOriginalState,
        $bRestoreCron = true,
        $bRestoreMaintenance = true,
        $bRestoreCompression = true
    ) {
        $aResult = array(
            'ok' => true,
            'cron_restored' => false,
            'maintenance_restored' => false,
            'maintenance_html_restored' => false,
            'compression_restored' => false,
            'errors' => array(),
        );

        $aGenerator =
            $aOriginalState['generator']
            ?? array();

        if ($bRestoreCron && !empty($aGenerator['id'])) {
            $iCronId = (int)$aGenerator['id'];

            $aLive = $this->_oDb->getRow(
                "SELECT *
                 FROM `sys_cron_jobs`
                 WHERE `id` = ?
                 LIMIT 1",
                array($iCronId)
            );

            $bIdentityMatches =
                !empty($aLive)
                && (string)$aLive['name']
                    === (string)($aGenerator['name'] ?? '')
                && (string)$aLive['class']
                    === (string)($aGenerator['class'] ?? '')
                && (string)$aLive['file']
                    === (string)($aGenerator['file'] ?? '')
                && (string)($aLive['service_call'] ?? '')
                    === (string)($aGenerator['service_call'] ?? '');

            if (!$bIdentityMatches) {
                $aResult['ok'] = false;
                $aResult['errors'][] =
                    'Rollback refused to alter the cron row because its identity changed.';
            } else {
                $oCronQuery =
                    BxDolCronQuery::getInstance();

                $bUpdated =
                    $oCronQuery->updateJob(
                        $iCronId,
                        array(
                            'time' =>
                                (string)($aGenerator['time'] ?? ''),
                        )
                    );

                $oCronQuery->cleanCache(
                    'sys_cron_jobs'
                );

                $aVerify = $this->_oDb->getRow(
                    "SELECT *
                     FROM `sys_cron_jobs`
                     WHERE `id` = ?
                     LIMIT 1",
                    array($iCronId)
                );

                if (
                    $bUpdated !== false
                    && !empty($aVerify)
                    && (string)$aVerify['name']
                        === (string)($aGenerator['name'] ?? '')
                    && (string)$aVerify['class']
                        === (string)($aGenerator['class'] ?? '')
                    && (string)$aVerify['file']
                        === (string)($aGenerator['file'] ?? '')
                    && (string)($aVerify['service_call'] ?? '')
                        === (string)($aGenerator['service_call'] ?? '')
                    && (string)$aVerify['time']
                        === (string)($aGenerator['time'] ?? '')
                ) {
                    $aResult['cron_restored'] = true;
                } else {
                    $aResult['ok'] = false;
                    $aResult['errors'][] =
                        'Cron rollback could not be verified.';
                }
            }
        }

        if ($bRestoreMaintenance) {
            $sOriginal =
                (string)(
                    $aOriginalState[
                        'soft_maintenance_value'
                    ]
                    ?? ''
                );

            setParam(
                'sys_maintenance_mode',
                $sOriginal
            );

            $sStored = (string)$this->_oDb->getOne(
                "SELECT `value`
                 FROM `sys_options`
                 WHERE `name` = ?
                 LIMIT 1",
                array('sys_maintenance_mode')
            );

            if ($sStored === $sOriginal) {
                $aResult['maintenance_restored'] = true;
            } else {
                $aResult['ok'] = false;
                $aResult['errors'][] =
                    'Soft-maintenance rollback could not be verified.';
            }
        }

        /*
         * Restore the exact previous soft-maintenance HTML only
         * when this journal recorded it. 0.3.4 and older journals
         * remain backward-compatible.
         *
         * Maintenance itself is restored first, so if Doctor
         * originally enabled soft maintenance the public site is
         * already available again before its temporary landing
         * page is removed.
         */
        if (
            $bRestoreMaintenance
            && array_key_exists(
                'soft_maintenance_html_value',
                $aOriginalState
            )
        ) {
            $sOriginalMaintenanceHtml =
                (string)$aOriginalState[
                    'soft_maintenance_html_value'
                ];

            setParam(
                'sys_maintenance_mode_html',
                $sOriginalMaintenanceHtml
            );

            $sStoredMaintenanceHtml =
                (string)$this->_oDb->getOne(
                    "SELECT `value`
                     FROM `sys_options`
                     WHERE `name` = ?
                     LIMIT 1",
                    array(
                        'sys_maintenance_mode_html'
                    )
                );

            if (
                $sStoredMaintenanceHtml
                === $sOriginalMaintenanceHtml
            ) {
                $aResult['maintenance_html_restored'] =
                    true;
            } else {
                $aResult['ok'] = false;
                $aResult['errors'][] =
                    'Soft-maintenance HTML rollback could not be verified.';
            }
        }

        /*
         * Restore compression only when this journal actually
         * recorded the original value. This keeps rollback
         * backward-compatible with older repair journals.
         *
         * Compression is restored AFTER maintenance so an
         * archived gzip_loader.php asset route cannot make
         * Studio unusable while maintenance is still active.
         */
        if (
            $bRestoreCompression
            && array_key_exists(
                'template_cache_compress_value',
                $aOriginalState
            )
        ) {
            $sOriginalCompression =
                (string)$aOriginalState[
                    'template_cache_compress_value'
                ];

            setParam(
                'sys_template_cache_compress_enable',
                $sOriginalCompression
            );

            $sStoredCompression =
                (string)$this->_oDb->getOne(
                    "SELECT `value`
                     FROM `sys_options`
                     WHERE `name` = ?
                     LIMIT 1",
                    array(
                        'sys_template_cache_compress_enable'
                    )
                );

            if (
                $sStoredCompression
                === $sOriginalCompression
            ) {
                $aResult['compression_restored'] =
                    true;
            } else {
                $aResult['ok'] = false;
                $aResult['errors'][] =
                    'Template-cache compression rollback could not be verified.';
            }
        }

        return $aResult;
    }




    protected function requireRepairJournalUpdate(
        $iJournalId,
        array $aFields
    ) {
        $iJournalId = (int)$iJournalId;
        $sStatus =
            (string)($aFields['status'] ?? '');

        if (
            $iJournalId <= 0
            || $sStatus === ''
        ) {
            throw new RuntimeException(
                'Required repair journal update is missing its journal ID or status.'
            );
        }

        if (
            !$this->_oDb->updateRepairJournal(
                $iJournalId,
                $aFields
            )
        ) {
            throw new RuntimeException(
                'Required repair journal update failed at status: '
                . $sStatus
            );
        }

        $aVerify =
            $this->_oDb->getRow(
                "SELECT `id`, `status`, `preflight_id`, `arm_id`
                 FROM `my_una_doctor_repairs`
                 WHERE `id` = ?
                 LIMIT 1",
                array($iJournalId)
            );

        if (
            empty($aVerify)
            || (int)$aVerify['id'] !== $iJournalId
            || (string)$aVerify['status'] !== $sStatus
        ) {
            throw new RuntimeException(
                'Required repair journal status could not be verified: '
                . $sStatus
            );
        }

        return true;
    }




    public function getActiveRepairJournal(
        $sRule,
        $sModule
    ) {
        $aRow = $this->_oDb->getRow(
            "SELECT *
             FROM `my_una_doctor_repairs`
             WHERE `rule` = ?
               AND `module` = ?
               AND `status` IN (
                   'planned',
                   'arming',
                   'maintenance_enabled',
                   'generator_paused',
                   'armed',
                   'runtime_protecting',
                   'runtime_protected',
                   'runtime_restoring',
                   'runtime_rollback_failed',
                   'restoring',
                   'rollback_failed'
               )
             ORDER BY `id` DESC
             LIMIT 1",
            array(
                (string)$sRule,
                (string)$sModule
            )
        );

        if (empty($aRow))
            return array();

        foreach (
            array(
                'original_state_json',
                'planned_state_json',
                'result_json'
            )
            as $sField
        ) {
            $aDecoded = json_decode(
                (string)($aRow[$sField] ?? ''),
                true
            );

            $aRow[
                preg_replace('/_json$/', '', $sField)
            ] = is_array($aDecoded)
                ? $aDecoded
                : array();
        }

        return $aRow;
    }



    public function getRuntimeProtectionPlan($iJournalId)
    {
        $iJournalId = (int)$iJournalId;

        $aResult = array(
            'executed' => true,
            'ok' => false,
            'ready' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
        );

        if ($iJournalId <= 0) {
            $aResult['error'] =
                'A valid repair journal ID is required.';

            return $aResult;
        }

        $aJournal = $this->_oDb->getRow(
            "SELECT *
             FROM `my_una_doctor_repairs`
             WHERE `id` = ?
             LIMIT 1",
            array($iJournalId)
        );

        if (empty($aJournal)) {
            $aResult['error'] =
                'Repair journal was not found.';

            return $aResult;
        }

        if ((string)$aJournal['status'] !== 'armed') {
            $aResult['error'] =
                'Runtime protection planning requires an armed repair journal.';

            return $aResult;
        }

        $aOriginal = json_decode(
            (string)$aJournal['original_state_json'],
            true
        );

        $aPlanned = json_decode(
            (string)$aJournal['planned_state_json'],
            true
        );

        $aArmed = json_decode(
            (string)$aJournal['result_json'],
            true
        );

        if (
            !is_array($aOriginal)
            || !is_array($aPlanned)
            || !is_array($aArmed)
            || ($aArmed['phase'] ?? '') !== 'armed'
        ) {
            $aResult['error'] =
                'The armed repair journal is incomplete or unreadable.';

            return $aResult;
        }

        $aRuntime =
            $aPlanned['runtime']
            ?? array();

        $aSafety =
            $aPlanned['safety_destination']
            ?? array();

        $aGenerator =
            $aOriginal['generator']
            ?? array();

        $sRuntimePath =
            (string)($aRuntime['path'] ?? '');

        $sRuntimeRelative =
            trim(
                str_replace(
                    '\\',
                    '/',
                    (string)(
                        $aRuntime['relative_directory']
                        ?? ''
                    )
                ),
                '/'
            );

        $sSafetyBase =
            (string)($aSafety['path'] ?? '');

        if (
            $sRuntimePath === ''
            || $sRuntimeRelative === ''
            || $sSafetyBase === ''
        ) {
            $aResult['error'] =
                'Journaled runtime or safety destination data is incomplete.';

            return $aResult;
        }

        if (
            !is_dir($sRuntimePath)
            || is_link($sRuntimePath)
        ) {
            $aResult['error'] =
                'The journaled runtime directory is missing or unsafe.';

            return $aResult;
        }

        if (
            !is_dir($sSafetyBase)
            || is_link($sSafetyBase)
            || !is_writable($sSafetyBase)
        ) {
            $aResult['error'] =
                'The journaled safety destination is unavailable or not writable.';

            return $aResult;
        }

        $sRuntimeReal = realpath($sRuntimePath);
        $sSafetyReal = realpath($sSafetyBase);

        if (
            $sRuntimeReal === false
            || $sSafetyReal === false
        ) {
            $aResult['error'] =
                'Runtime or safety destination could not be resolved.';

            return $aResult;
        }

        /*
         * Re-verify the live control barrier before planning
         * any runtime movement.
         */
        $sLiveMaintenance =
            (string)$this->_oDb->getOne(
                "SELECT `value`
                 FROM `sys_options`
                 WHERE `name` = ?
                 LIMIT 1",
                array('sys_maintenance_mode')
            );

        $sExpectedMaintenance =
            (string)(
                $aArmed['maintenance_value']
                ?? ''
            );

        if (
            $sExpectedMaintenance === ''
            || $sLiveMaintenance
                !== $sExpectedMaintenance
        ) {
            $aResult['error'] =
                'The armed soft-maintenance barrier is no longer exact.';

            return $aResult;
        }

        $iCronId =
            (int)($aGenerator['id'] ?? 0);

        $aLiveCron = $this->_oDb->getRow(
            "SELECT *
             FROM `sys_cron_jobs`
             WHERE `id` = ?
             LIMIT 1",
            array($iCronId)
        );

        $sPausedSchedule =
            (string)(
                $aArmed['generator_schedule']
                ?? ''
            );

        $bCronExact =
            $iCronId > 0
            && !empty($aLiveCron)
            && (int)(
                $aArmed['generator_id']
                ?? 0
            ) === $iCronId
            && (string)$aLiveCron['name']
                === (string)($aGenerator['name'] ?? '')
            && (string)$aLiveCron['class']
                === (string)($aGenerator['class'] ?? '')
            && (string)$aLiveCron['file']
                === (string)($aGenerator['file'] ?? '')
            && (string)($aLiveCron['service_call'] ?? '')
                === (string)($aGenerator['service_call'] ?? '')
            && (string)$aLiveCron['time']
                === $sPausedSchedule;

        if (!$bCronExact) {
            $aResult['error'] =
                'The armed generator barrier is no longer exact.';

            return $aResult;
        }

        /*
         * timing=0 after a previously executed cron can mean
         * that a process is still active or did not finish
         * cleanly. Refuse automatic protection in that state.
         */
        if (
            (int)($aLiveCron['ts'] ?? 0) > 0
            && (float)($aLiveCron['timing'] ?? 0) <= 0
        ) {
            $aResult['error'] =
                'The generator does not appear quiescent. Runtime protection was refused.';

            return $aResult;
        }

        $aRuntimeStat = @stat($sRuntimeReal);
        $aSafetyStat = @stat($sSafetyReal);

        if (
            !is_array($aRuntimeStat)
            || !is_array($aSafetyStat)
        ) {
            $aResult['error'] =
                'Filesystem metadata could not be read.';

            return $aResult;
        }

        if (
            (int)$aRuntimeStat['dev']
            !== (int)$aSafetyStat['dev']
        ) {
            $aResult['error'] =
                'Runtime and safety destination are not on the same filesystem.';

            return $aResult;
        }

        /*
         * Inventory the complete live runtime tree.
         */
        $iFiles = 0;
        $iDirectories = 0;
        $iSymlinks = 0;
        $iHardlinks = 0;
        $iSpecial = 0;
        $iBytes = 0;
        $aRuntimeInventory = array();

        $sRuntimePrefix =
            rtrim(
                str_replace(
                    '\\',
                    '/',
                    $sRuntimeReal
                ),
                '/'
            )
            . '/';

        try {
            $oIterator =
                new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $sRuntimeReal,
                        FilesystemIterator::SKIP_DOTS
                    ),
                    RecursiveIteratorIterator::SELF_FIRST
                );

            foreach ($oIterator as $oEntry) {
                $sPath = $oEntry->getPathname();

                $sNormalizedPath =
                    str_replace(
                        '\\',
                        '/',
                        $sPath
                    );

                if (
                    strpos(
                        $sNormalizedPath,
                        $sRuntimePrefix
                    ) !== 0
                ) {
                    throw new RuntimeException(
                        'Runtime inventory escaped the verified runtime root.'
                    );
                }

                $sRelativePath =
                    substr(
                        $sNormalizedPath,
                        strlen($sRuntimePrefix)
                    );

                if (
                    $sRelativePath === ''
                    || $sRelativePath === '..'
                    || strpos(
                        '/' . $sRelativePath . '/',
                        '/../'
                    ) !== false
                ) {
                    throw new RuntimeException(
                        'Runtime inventory contains an unsafe relative path.'
                    );
                }

                $aEntryStat = @lstat($sPath);

                if (!is_array($aEntryStat)) {
                    throw new RuntimeException(
                        'Runtime entry metadata could not be read: '
                        . $sRelativePath
                    );
                }

                $sType = 'special';
                $iSize = 0;

                if (is_link($sPath)) {
                    ++$iSymlinks;
                    $sType = 'symlink';
                } elseif ($oEntry->isDir()) {
                    ++$iDirectories;
                    $sType = 'directory';
                } elseif ($oEntry->isFile()) {
                    ++$iFiles;

                    $iSize =
                        (int)$oEntry->getSize();

                    $iBytes += $iSize;
                    $sType = 'file';

                    if (
                        (int)(
                            $aEntryStat['nlink']
                            ?? 1
                        ) > 1
                    ) {
                        ++$iHardlinks;
                    }
                } else {
                    ++$iSpecial;
                }

                $aRuntimeInventory[] = array(
                    'path' =>
                        $sRelativePath,
                    'type' =>
                        $sType,
                    'size' =>
                        $iSize,
                    'mtime' =>
                        (int)(
                            $aEntryStat['mtime']
                            ?? 0
                        ),
                    'ctime' =>
                        (int)(
                            $aEntryStat['ctime']
                            ?? 0
                        ),
                    'mode' =>
                        sprintf(
                            '%04o',
                            (int)$aEntryStat['mode']
                            & 07777
                        ),
                    'uid' =>
                        (int)(
                            $aEntryStat['uid']
                            ?? -1
                        ),
                    'gid' =>
                        (int)(
                            $aEntryStat['gid']
                            ?? -1
                        ),
                );
            }
        } catch (Throwable $e) {
            $aResult['error'] =
                'Runtime inventory failed: '
                . $e->getMessage();

            return $aResult;
        }

        usort(
            $aRuntimeInventory,
            static function ($a, $b) {
                return strcmp(
                    (string)($a['path'] ?? ''),
                    (string)($b['path'] ?? '')
                );
            }
        );

        $sRuntimeInventoryJson = json_encode(
            $aRuntimeInventory,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($sRuntimeInventoryJson === false)
            $sRuntimeInventoryJson =
                serialize($aRuntimeInventory);

        $sRuntimeInventoryId =
            hash(
                'sha256',
                $sRuntimeInventoryJson
            );

        if (
            $iSymlinks > 0
            || $iHardlinks > 0
            || $iSpecial > 0
        ) {
            $aResult['error'] =
                'Runtime contains symlinks, hardlinks or special files and is not eligible for automatic atomic protection.';

            return $aResult;
        }

        if (
            $iFiles
                !== (int)($aRuntime['file_count'] ?? -1)
            || $iBytes
                !== (int)($aRuntime['bytes'] ?? -1)
        ) {
            $aResult['error'] =
                'Runtime inventory changed after the repair was armed.';

            return $aResult;
        }

        /*
         * Resolve the module and fingerprint every tracked file
         * that lives inside the runtime directory.
         */
        $sModule =
            (string)($aJournal['module'] ?? '');

        $aModule = $this->_oDb->getRow(
            "SELECT `id`, `name`, `path`
             FROM `sys_modules`
             WHERE `name` = ?
             LIMIT 1",
            array($sModule)
        );

        if (empty($aModule)) {
            $aResult['error'] =
                'The journaled module is no longer registered.';

            return $aResult;
        }

        $sModuleRoot = realpath(
            BX_DIRECTORY_PATH_MODULES
            . (string)$aModule['path']
        );

        if ($sModuleRoot === false) {
            $aResult['error'] =
                'The module source directory could not be resolved.';

            return $aResult;
        }

        $sTrackedPrefix =
            $sRuntimeRelative . '/';

        $aTrackedRows = $this->_oDb->getAll(
            "SELECT `file`, `hash`
             FROM `sys_modules_file_tracks`
             WHERE `module_id` = ?
               AND `file` LIKE ?
             ORDER BY `file`",
            array(
                (int)$aModule['id'],
                $sTrackedPrefix . '%'
            )
        );

        $aTrackedManifest = array();
        $iTrackedMismatch = 0;

        foreach ($aTrackedRows as $aTracked) {
            $sFile = str_replace(
                '\\',
                '/',
                (string)$aTracked['file']
            );

            $sAbsolute =
                $sModuleRoot
                . DIRECTORY_SEPARATOR
                . str_replace(
                    '/',
                    DIRECTORY_SEPARATOR,
                    $sFile
                );

            $sCurrentHash =
                is_file($sAbsolute)
                && !is_link($sAbsolute)
                    ? (string)md5_file($sAbsolute)
                    : '';

            $sBaselineHash =
                (string)$aTracked['hash'];

            $aTrackedStat =
                is_file($sAbsolute)
                && !is_link($sAbsolute)
                    ? @stat($sAbsolute)
                    : false;

            $bMatch =
                $sCurrentHash !== ''
                && strtolower($sCurrentHash)
                    === strtolower($sBaselineHash)
                && is_array($aTrackedStat);

            if (!$bMatch)
                ++$iTrackedMismatch;

            $aTrackedManifest[] = array(
                'file' =>
                    $sFile,
                'baseline_hash' =>
                    $sBaselineHash,
                'current_hash' =>
                    $sCurrentHash,
                'match' =>
                    $bMatch,
                'size' =>
                    is_array($aTrackedStat)
                        ? (int)$aTrackedStat['size']
                        : -1,
                'mode' =>
                    is_array($aTrackedStat)
                        ? sprintf(
                            '%04o',
                            (int)$aTrackedStat['mode']
                            & 07777
                        )
                        : '',
                'uid' =>
                    is_array($aTrackedStat)
                        ? (int)$aTrackedStat['uid']
                        : -1,
                'gid' =>
                    is_array($aTrackedStat)
                        ? (int)$aTrackedStat['gid']
                        : -1,
            );
        }

        if (
            count($aTrackedManifest)
                !== (int)(
                    $aRuntime['tracked_file_count']
                    ?? -1
                )
            || $iTrackedMismatch > 0
        ) {
            $aResult['error'] =
                'Tracked runtime source no longer matches the verified baseline.';

            return $aResult;
        }

        $sSafetyTarget =
            $sSafetyReal
            . DIRECTORY_SEPARATOR
            . 'runtime-journal-'
            . $iJournalId;

        if (
            file_exists($sSafetyTarget)
            || is_link($sSafetyTarget)
        ) {
            $aResult['error'] =
                'The journal-specific runtime safety destination already exists.';

            return $aResult;
        }

        $aDirectoryState = array(
            'mode' =>
                sprintf(
                    '%04o',
                    (int)$aRuntimeStat['mode']
                    & 07777
                ),
            'uid' =>
                (int)$aRuntimeStat['uid'],
            'gid' =>
                (int)$aRuntimeStat['gid'],
            'device' =>
                (int)$aRuntimeStat['dev'],
        );

        $aFingerprint = array(
            'journal_id' =>
                $iJournalId,
            'arm_id' =>
                (string)$aJournal['arm_id'],
            'maintenance' =>
                $sLiveMaintenance,
            'generator_id' =>
                $iCronId,
            'generator_schedule' =>
                $sPausedSchedule,
            'runtime_path' =>
                $sRuntimeReal,
            'runtime_files' =>
                $iFiles,
            'runtime_directories' =>
                $iDirectories,
            'runtime_bytes' =>
                $iBytes,
            'runtime_inventory_id' =>
                $sRuntimeInventoryId,
            'tracked_manifest' =>
                $aTrackedManifest,
            'safety_target' =>
                $sSafetyTarget,
            'directory_state' =>
                $aDirectoryState,
        );

        $sFingerprint = json_encode(
            $aFingerprint,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($sFingerprint === false)
            $sFingerprint = serialize($aFingerprint);

        $aResult['protection_id'] = substr(
            hash('sha256', $sFingerprint),
            0,
            16
        );

        $aResult['runtime'] = array(
            'path' => $sRuntimeReal,
            'relative_directory' =>
                $sRuntimeRelative . '/',
            'file_count' =>
                $iFiles,
            'subdirectory_count' =>
                $iDirectories,
            'tracked_file_count' =>
                count($aTrackedManifest),
            'untracked_file_count' =>
                $iFiles
                - count($aTrackedManifest),
            'bytes' =>
                $iBytes,
            'runtime_inventory_id' =>
                $sRuntimeInventoryId,
            'symlinks' =>
                $iSymlinks,
            'hardlinks' =>
                $iHardlinks,
            'special_files' =>
                $iSpecial,
            'directory_state' =>
                $aDirectoryState,
        );

        $aResult['tracked_manifest'] =
            $aTrackedManifest;

        $aResult['safety'] = array(
            'base_path' =>
                $sSafetyReal,
            'protected_runtime_path' =>
                $sSafetyTarget,
            'same_filesystem' =>
                true,
            'device' =>
                (int)$aSafetyStat['dev'],
            'free_bytes' =>
                (int)@disk_free_space(
                    $sSafetyReal
                ),
        );

        $aResult['generator'] = array(
            'id' =>
                $iCronId,
            'name' =>
                (string)$aLiveCron['name'],
            'paused_schedule' =>
                $sPausedSchedule,
            'ts' =>
                (int)($aLiveCron['ts'] ?? 0),
            'timing' =>
                (float)($aLiveCron['timing'] ?? 0),
            'quiescent' =>
                true,
        );

        $aResult['planned_steps'] = array(
            'Re-run this protection plan and require the same protection ID.',
            'Re-verify maintenance, generator identity and generator quiescence.',
            'Re-inventory the runtime tree immediately before protection.',
            'Atomically rename the entire runtime directory into the journal-specific safety destination.',
            'Recreate the runtime directory with its exact original mode, owner and group.',
            'Restore only tracked runtime files from the protected tree.',
            'Verify every restored tracked file against its UNA baseline hash.',
            'Verify the protected tree still contains the complete original runtime inventory.',
            'Stop before starting any module update.',
        );

        $aResult['rollback_plan'] = array(
            'Remove only the recreated live runtime tree after verifying it contains tracked files only.',
            'Atomically rename the protected runtime tree back to its exact original path.',
            'Restore the exact directory metadata if needed.',
            'Re-verify the complete runtime inventory.',
            'Keep the control barrier active until rollback verification succeeds.',
        );

        $aResult['ok'] = true;
        $aResult['ready'] = true;

        return $aResult;
    }



    protected function inspectRuntimeTree($sRoot)
    {
        $aResult = array(
            'ok' => false,
            'path' => (string)$sRoot,
            'files' => 0,
            'directories' => 0,
            'symlinks' => 0,
            'hardlinks' => 0,
            'special_files' => 0,
            'bytes' => 0,
            'entries' => array(),
            'error' => '',
        );

        $sRoot = (string)$sRoot;

        if (
            $sRoot === ''
            || !is_dir($sRoot)
            || is_link($sRoot)
        ) {
            $aResult['error'] =
                'Runtime tree is missing, not a directory or is a symlink.';

            return $aResult;
        }

        $sReal = realpath($sRoot);

        if ($sReal === false) {
            $aResult['error'] =
                'Runtime tree could not be resolved.';

            return $aResult;
        }

        $aResult['path'] = $sReal;

        try {
            $oIterator =
                new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $sReal,
                        FilesystemIterator::SKIP_DOTS
                    ),
                    RecursiveIteratorIterator::SELF_FIRST
                );

            foreach ($oIterator as $oEntry) {
                $sPath = $oEntry->getPathname();

                $sRelative = ltrim(
                    str_replace(
                        '\\',
                        '/',
                        substr(
                            $sPath,
                            strlen($sReal)
                        )
                    ),
                    '/'
                );

                $aEntryStat = @lstat($sPath);

                if (!is_array($aEntryStat)) {
                    $aResult['error'] =
                        'Runtime entry metadata could not be read: '
                        . $sRelative;

                    return $aResult;
                }

                $aEntry = array(
                    'relative_path' =>
                        $sRelative,
                    'type' =>
                        '',
                    'size' =>
                        0,
                    'links' =>
                        (int)(
                            $aEntryStat['nlink']
                            ?? 0
                        ),
                    'mtime' =>
                        (int)(
                            $aEntryStat['mtime']
                            ?? 0
                        ),
                    'ctime' =>
                        (int)(
                            $aEntryStat['ctime']
                            ?? 0
                        ),
                    'mode' =>
                        sprintf(
                            '%04o',
                            (int)$aEntryStat['mode']
                            & 07777
                        ),
                    'uid' =>
                        (int)(
                            $aEntryStat['uid']
                            ?? -1
                        ),
                    'gid' =>
                        (int)(
                            $aEntryStat['gid']
                            ?? -1
                        ),
                );

                if (is_link($sPath)) {
                    ++$aResult['symlinks'];
                    $aEntry['type'] = 'symlink';
                    $aResult['entries'][] = $aEntry;
                    continue;
                }

                if ($oEntry->isDir()) {
                    ++$aResult['directories'];
                    $aEntry['type'] = 'directory';
                    $aResult['entries'][] = $aEntry;
                    continue;
                }

                if ($oEntry->isFile()) {
                    ++$aResult['files'];

                    $iSize =
                        (int)$oEntry->getSize();

                    $aResult['bytes'] += $iSize;

                    $iLinks =
                        (int)(
                            $aEntryStat['nlink']
                            ?? 1
                        );

                    if ($iLinks > 1)
                        ++$aResult['hardlinks'];

                    $aEntry['type'] = 'file';
                    $aEntry['size'] = $iSize;
                    $aEntry['links'] = $iLinks;

                    $aResult['entries'][] = $aEntry;
                    continue;
                }

                ++$aResult['special_files'];
                $aEntry['type'] = 'special';
                $aResult['entries'][] = $aEntry;
            }
        } catch (Throwable $e) {
            $aResult['error'] =
                'Runtime tree inspection failed: '
                . $e->getMessage();

            return $aResult;
        }

        usort(
            $aResult['entries'],
            static function ($a, $b) {
                return strcmp(
                    (string)(
                        $a['relative_path']
                        ?? ''
                    ),
                    (string)(
                        $b['relative_path']
                        ?? ''
                    )
                );
            }
        );

        $aFingerprintEntries = array();

        foreach (
            $aResult['entries']
            as $aEntry
        ) {
            $aFingerprintEntries[] = array(
                'path' =>
                    (string)(
                        $aEntry['relative_path']
                        ?? ''
                    ),
                'type' =>
                    (string)(
                        $aEntry['type']
                        ?? ''
                    ),
                'size' =>
                    (int)(
                        $aEntry['size']
                        ?? 0
                    ),
                'mtime' =>
                    (int)(
                        $aEntry['mtime']
                        ?? 0
                    ),
                'ctime' =>
                    (int)(
                        $aEntry['ctime']
                        ?? 0
                    ),
                'mode' =>
                    (string)(
                        $aEntry['mode']
                        ?? ''
                    ),
                'uid' =>
                    (int)(
                        $aEntry['uid']
                        ?? -1
                    ),
                'gid' =>
                    (int)(
                        $aEntry['gid']
                        ?? -1
                    ),
            );
        }

        $sInventoryJson = json_encode(
            $aFingerprintEntries,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($sInventoryJson === false)
            $sInventoryJson =
                serialize($aFingerprintEntries);

        $aResult['runtime_inventory_id'] =
            hash(
                'sha256',
                $sInventoryJson
            );

        $aResult['ok'] = true;

        return $aResult;
    }



    protected function rollbackRuntimeProtection(array $aPlan)
    {
        $aResult = array(
            'ok' => false,
            'restored' => false,
            'protected_tree_verified' => false,
            'live_tree_removed' => false,
            'errors' => array(),
        );

        $aRuntime =
            $aPlan['runtime']
            ?? array();

        $aSafety =
            $aPlan['safety']
            ?? array();

        $aTracked =
            $aPlan['tracked_manifest']
            ?? array();

        $sRuntimePath =
            (string)($aRuntime['path'] ?? '');

        $sProtectedPath =
            (string)(
                $aSafety['protected_runtime_path']
                ?? ''
            );

        $sRuntimeRelative =
            trim(
                str_replace(
                    '\\',
                    '/',
                    (string)(
                        $aRuntime[
                            'relative_directory'
                        ]
                        ?? ''
                    )
                ),
                '/'
            );

        if (
            $sRuntimePath === ''
            || $sProtectedPath === ''
            || $sRuntimeRelative === ''
        ) {
            $aResult['errors'][] =
                'Runtime rollback plan is incomplete.';

            return $aResult;
        }

        /*
         * Verify the protected original tree BEFORE removing
         * anything from the recreated live runtime tree.
         */
        $aProtected =
            $this->inspectRuntimeTree(
                $sProtectedPath
            );

        if (empty($aProtected['ok'])) {
            $aResult['errors'][] =
                'Protected runtime tree could not be verified: '
                . (
                    $aProtected['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        if (
            (int)$aProtected['files']
                !== (int)(
                    $aRuntime['file_count']
                    ?? -1
                )
            || (int)$aProtected['directories']
                !== (int)(
                    $aRuntime['subdirectory_count']
                    ?? -1
                )
            || (int)$aProtected['bytes']
                !== (int)(
                    $aRuntime['bytes']
                    ?? -1
                )
            || (string)(
                    $aProtected[
                        'runtime_inventory_id'
                    ]
                    ?? ''
                )
                !== (string)(
                    $aRuntime[
                        'runtime_inventory_id'
                    ]
                    ?? ''
                )
            || (string)(
                    $aRuntime[
                        'runtime_inventory_id'
                    ]
                    ?? ''
                ) === ''
            || (int)$aProtected['symlinks'] !== 0
            || (int)$aProtected['hardlinks'] !== 0
            || (int)$aProtected['special_files'] !== 0
            || (string)(
                $aProtected['runtime_inventory_id']
                ?? ''
            ) !== (string)(
                $aRuntime['runtime_inventory_id']
                ?? ''
            )
        ) {
            $aResult['errors'][] =
                'Protected runtime inventory no longer matches the original plan.';

            return $aResult;
        }

        $aResult['protected_tree_verified'] =
            true;

        /*
         * Build the exact set of files and parent directories
         * that are allowed to exist in the recreated live tree.
         */
        $aAllowedFiles = array();
        $aAllowedDirectories = array();

        foreach ($aTracked as $aTrackedFile) {
            $sFile =
                str_replace(
                    '\\',
                    '/',
                    (string)(
                        $aTrackedFile['file']
                        ?? ''
                    )
                );

            $sPrefix =
                $sRuntimeRelative . '/';

            if (
                $sFile === ''
                || strpos($sFile, $sPrefix)
                    !== 0
            ) {
                $aResult['errors'][] =
                    'Tracked runtime manifest contains an out-of-scope file.';

                return $aResult;
            }

            $sRelative =
                substr(
                    $sFile,
                    strlen($sPrefix)
                );

            if (
                $sRelative === ''
                || strpos($sRelative, '../')
                    !== false
                || $sRelative === '..'
            ) {
                $aResult['errors'][] =
                    'Tracked runtime manifest contains an unsafe path.';

                return $aResult;
            }

            $aAllowedFiles[$sRelative] =
                true;

            $sParent =
                dirname($sRelative);

            while (
                $sParent !== '.'
                && $sParent !== ''
            ) {
                $aAllowedDirectories[
                    str_replace(
                        '\\',
                        '/',
                        $sParent
                    )
                ] = true;

                $sNext =
                    dirname($sParent);

                if ($sNext === $sParent)
                    break;

                $sParent = $sNext;
            }
        }

        if (
            file_exists($sRuntimePath)
            || is_link($sRuntimePath)
        ) {
            $aLive =
                $this->inspectRuntimeTree(
                    $sRuntimePath
                );

            if (empty($aLive['ok'])) {
                $aResult['errors'][] =
                    'Recreated live runtime tree could not be inspected: '
                    . (
                        $aLive['error']
                        ?? 'unknown error'
                    );

                return $aResult;
            }

            if (
                (int)$aLive['symlinks'] !== 0
                || (int)$aLive['hardlinks'] !== 0
                || (int)$aLive['special_files'] !== 0
            ) {
                $aResult['errors'][] =
                    'Recreated live runtime tree contains unsafe filesystem entries.';

                return $aResult;
            }

            foreach (
                $aLive['entries']
                ?? array()
                as $aEntry
            ) {
                $sRelative =
                    (string)(
                        $aEntry['relative_path']
                        ?? ''
                    );

                $sType =
                    (string)(
                        $aEntry['type']
                        ?? ''
                    );

                if (
                    $sType === 'file'
                    && empty(
                        $aAllowedFiles[
                            $sRelative
                        ]
                    )
                ) {
                    $aResult['errors'][] =
                        'Runtime rollback refused because an unexpected live file exists: '
                        . $sRelative;

                    return $aResult;
                }

                if (
                    $sType === 'directory'
                    && empty(
                        $aAllowedDirectories[
                            $sRelative
                        ]
                    )
                ) {
                    $aResult['errors'][] =
                        'Runtime rollback refused because an unexpected live directory exists: '
                        . $sRelative;

                    return $aResult;
                }
            }

            /*
             * The live tree now contains tracked content only.
             * Remove files first, then deepest directories,
             * then the runtime root itself.
             */
            $aDirectoriesToRemove =
                array();

            foreach (
                $aLive['entries']
                ?? array()
                as $aEntry
            ) {
                $sRelative =
                    (string)(
                        $aEntry['relative_path']
                        ?? ''
                    );

                $sAbsolute =
                    $sRuntimePath
                    . DIRECTORY_SEPARATOR
                    . str_replace(
                        '/',
                        DIRECTORY_SEPARATOR,
                        $sRelative
                    );

                if (
                    ($aEntry['type'] ?? '')
                    === 'file'
                ) {
                    if (!@unlink($sAbsolute)) {
                        $aResult['errors'][] =
                            'Could not remove recreated tracked runtime file: '
                            . $sRelative;

                        return $aResult;
                    }
                } elseif (
                    ($aEntry['type'] ?? '')
                    === 'directory'
                ) {
                    $aDirectoriesToRemove[] =
                        $sAbsolute;
                }
            }

            usort(
                $aDirectoriesToRemove,
                static function ($a, $b) {
                    return strlen($b)
                        <=> strlen($a);
                }
            );

            foreach (
                $aDirectoriesToRemove
                as $sDirectory
            ) {
                if (!@rmdir($sDirectory)) {
                    $aResult['errors'][] =
                        'Could not remove recreated runtime subdirectory: '
                        . $sDirectory;

                    return $aResult;
                }
            }

            if (!@rmdir($sRuntimePath)) {
                $aResult['errors'][] =
                    'Could not remove recreated runtime root.';

                return $aResult;
            }

            $aResult['live_tree_removed'] =
                true;
        }

        if (
            file_exists($sRuntimePath)
            || is_link($sRuntimePath)
        ) {
            $aResult['errors'][] =
                'Original runtime path is still occupied.';

            return $aResult;
        }

        /*
         * Same-filesystem rename restores the complete original
         * tree with its existing filesystem metadata intact.
         */
        if (
            !@rename(
                $sProtectedPath,
                $sRuntimePath
            )
        ) {
            $aResult['errors'][] =
                'Protected runtime tree could not be renamed back to its original path.';

            return $aResult;
        }

        $aRestored =
            $this->inspectRuntimeTree(
                $sRuntimePath
            );

        if (
            empty($aRestored['ok'])
            || (int)$aRestored['files']
                !== (int)(
                    $aRuntime['file_count']
                    ?? -1
                )
            || (int)$aRestored['directories']
                !== (int)(
                    $aRuntime['subdirectory_count']
                    ?? -1
                )
            || (int)$aRestored['bytes']
                !== (int)(
                    $aRuntime['bytes']
                    ?? -1
                )
            || (string)(
                    $aRestored[
                        'runtime_inventory_id'
                    ]
                    ?? ''
                )
                !== (string)(
                    $aRuntime[
                        'runtime_inventory_id'
                    ]
                    ?? ''
                )
            || (string)(
                    $aRuntime[
                        'runtime_inventory_id'
                    ]
                    ?? ''
                ) === ''
            || (int)$aRestored['symlinks'] !== 0
            || (int)$aRestored['hardlinks'] !== 0
            || (int)$aRestored['special_files'] !== 0
            || (string)(
                $aRestored['runtime_inventory_id']
                ?? ''
            ) !== (string)(
                $aRuntime['runtime_inventory_id']
                ?? ''
            )
        ) {
            $aResult['errors'][] =
                'Runtime tree was renamed back but final inventory verification failed.';

            return $aResult;
        }

        $aResult['ok'] = true;
        $aResult['restored'] = true;
        $aResult['restored_inventory'] =
            $aRestored;

        return $aResult;
    }




    protected function applyRuntimeFilesystemMetadata(
        $sPath,
        array $aOriginalStat
    ) {
        $aResult = array(
            'ok' => false,
            'error' => '',
        );

        $sPath = (string)$sPath;

        if (
            $sPath === ''
            || (
                !file_exists($sPath)
                && !is_link($sPath)
            )
        ) {
            $aResult['error'] =
                'Filesystem entry does not exist.';

            return $aResult;
        }

        if (is_link($sPath)) {
            $aResult['error'] =
                'Filesystem metadata will not be applied to a symlink.';

            return $aResult;
        }

        $iMode =
            (int)($aOriginalStat['mode'] ?? 0)
            & 07777;

        $iUid =
            (int)($aOriginalStat['uid'] ?? -1);

        $iGid =
            (int)($aOriginalStat['gid'] ?? -1);

        if ($iMode <= 0) {
            $aResult['error'] =
                'Original filesystem mode is invalid.';

            return $aResult;
        }

        if (!@chmod($sPath, $iMode)) {
            $aResult['error'] =
                'Could not restore filesystem mode.';

            return $aResult;
        }

        clearstatcache(true, $sPath);

        $aCurrent = @stat($sPath);

        if (!is_array($aCurrent)) {
            $aResult['error'] =
                'Filesystem metadata could not be read after chmod.';

            return $aResult;
        }

        if (
            $iUid >= 0
            && (int)$aCurrent['uid'] !== $iUid
        ) {
            @chown($sPath, $iUid);
            clearstatcache(true, $sPath);
        }

        $aCurrent = @stat($sPath);

        if (
            $iGid >= 0
            && is_array($aCurrent)
            && (int)$aCurrent['gid'] !== $iGid
        ) {
            @chgrp($sPath, $iGid);
            clearstatcache(true, $sPath);
        }

        clearstatcache(true, $sPath);

        $aVerify = @stat($sPath);

        if (!is_array($aVerify)) {
            $aResult['error'] =
                'Filesystem metadata could not be verified.';

            return $aResult;
        }

        if (
            (
                (int)$aVerify['mode']
                & 07777
            ) !== $iMode
            || (
                $iUid >= 0
                && (int)$aVerify['uid']
                    !== $iUid
            )
            || (
                $iGid >= 0
                && (int)$aVerify['gid']
                    !== $iGid
            )
        ) {
            $aResult['error'] =
                'Filesystem mode, owner or group could not be restored exactly.';

            return $aResult;
        }

        $aResult['ok'] = true;
        $aResult['mode'] =
            sprintf('%04o', $iMode);
        $aResult['uid'] =
            (int)$aVerify['uid'];
        $aResult['gid'] =
            (int)$aVerify['gid'];

        return $aResult;
    }



    protected function restoreTrackedRuntimeFiles(
        array $aPlan
    ) {
        $aResult = array(
            'ok' => false,
            'restored_files' => array(),
            'errors' => array(),
        );

        $aRuntime =
            $aPlan['runtime']
            ?? array();

        $aSafety =
            $aPlan['safety']
            ?? array();

        $aTracked =
            $aPlan['tracked_manifest']
            ?? array();

        $sRuntimePath =
            (string)($aRuntime['path'] ?? '');

        $sProtectedPath =
            (string)(
                $aSafety['protected_runtime_path']
                ?? ''
            );

        $sRuntimeRelative =
            trim(
                str_replace(
                    '\\',
                    '/',
                    (string)(
                        $aRuntime['relative_directory']
                        ?? ''
                    )
                ),
                '/'
            );

        if (
            $sRuntimePath === ''
            || $sProtectedPath === ''
            || $sRuntimeRelative === ''
            || !is_dir($sRuntimePath)
            || is_link($sRuntimePath)
            || !is_dir($sProtectedPath)
            || is_link($sProtectedPath)
        ) {
            $aResult['errors'][] =
                'Tracked runtime restoration paths are unavailable or unsafe.';

            return $aResult;
        }

        $sPrefix =
            $sRuntimeRelative . '/';

        foreach ($aTracked as $aTrackedFile) {
            $sTrackedFile =
                str_replace(
                    '\\',
                    '/',
                    (string)(
                        $aTrackedFile['file']
                        ?? ''
                    )
                );

            $sBaselineHash =
                strtolower(
                    (string)(
                        $aTrackedFile['baseline_hash']
                        ?? ''
                    )
                );

            if (
                $sTrackedFile === ''
                || $sBaselineHash === ''
                || strpos(
                    $sTrackedFile,
                    $sPrefix
                ) !== 0
            ) {
                $aResult['errors'][] =
                    'Tracked runtime manifest contains invalid data.';

                return $aResult;
            }

            $sRelative =
                substr(
                    $sTrackedFile,
                    strlen($sPrefix)
                );

            if (
                $sRelative === ''
                || $sRelative === '..'
                || strpos(
                    '/' . $sRelative . '/',
                    '/../'
                ) !== false
            ) {
                $aResult['errors'][] =
                    'Tracked runtime manifest contains an unsafe path.';

                return $aResult;
            }

            $sRelativeFs =
                str_replace(
                    '/',
                    DIRECTORY_SEPARATOR,
                    $sRelative
                );

            $sSource =
                $sProtectedPath
                . DIRECTORY_SEPARATOR
                . $sRelativeFs;

            $sDestination =
                $sRuntimePath
                . DIRECTORY_SEPARATOR
                . $sRelativeFs;

            if (
                !is_file($sSource)
                || is_link($sSource)
            ) {
                $aResult['errors'][] =
                    'Tracked source file is missing or unsafe: '
                    . $sTrackedFile;

                return $aResult;
            }

            $sSourceHash =
                strtolower(
                    (string)md5_file($sSource)
                );

            if ($sSourceHash !== $sBaselineHash) {
                $aResult['errors'][] =
                    'Protected tracked source hash does not match UNA baseline: '
                    . $sTrackedFile;

                return $aResult;
            }

            if (
                file_exists($sDestination)
                || is_link($sDestination)
            ) {
                $aResult['errors'][] =
                    'Tracked destination already exists unexpectedly: '
                    . $sTrackedFile;

                return $aResult;
            }

            /*
             * Recreate any required parent directories,
             * preserving their metadata from the protected tree.
             */
            $sRelativeParent =
                str_replace(
                    '\\',
                    '/',
                    dirname($sRelative)
                );

            if (
                $sRelativeParent !== '.'
                && $sRelativeParent !== ''
            ) {
                $aParts = explode(
                    '/',
                    $sRelativeParent
                );

                $sBuiltRelative = '';

                foreach ($aParts as $sPart) {
                    if (
                        $sPart === ''
                        || $sPart === '.'
                        || $sPart === '..'
                    ) {
                        $aResult['errors'][] =
                            'Tracked parent directory path is unsafe.';

                        return $aResult;
                    }

                    $sBuiltRelative =
                        $sBuiltRelative === ''
                            ? $sPart
                            : $sBuiltRelative
                                . '/'
                                . $sPart;

                    $sSourceDir =
                        $sProtectedPath
                        . DIRECTORY_SEPARATOR
                        . str_replace(
                            '/',
                            DIRECTORY_SEPARATOR,
                            $sBuiltRelative
                        );

                    $sDestinationDir =
                        $sRuntimePath
                        . DIRECTORY_SEPARATOR
                        . str_replace(
                            '/',
                            DIRECTORY_SEPARATOR,
                            $sBuiltRelative
                        );

                    if (
                        !is_dir($sSourceDir)
                        || is_link($sSourceDir)
                    ) {
                        $aResult['errors'][] =
                            'Protected parent directory is missing or unsafe: '
                            . $sBuiltRelative;

                        return $aResult;
                    }

                    if (!is_dir($sDestinationDir)) {
                        if (
                            file_exists($sDestinationDir)
                            || is_link($sDestinationDir)
                            || !@mkdir(
                                $sDestinationDir,
                                0700
                            )
                        ) {
                            $aResult['errors'][] =
                                'Could not recreate tracked parent directory: '
                                . $sBuiltRelative;

                            return $aResult;
                        }

                        $aSourceDirStat =
                            @stat($sSourceDir);

                        if (!is_array($aSourceDirStat)) {
                            $aResult['errors'][] =
                                'Could not read protected parent directory metadata: '
                                . $sBuiltRelative;

                            return $aResult;
                        }

                        $aMetadata =
                            $this
                                ->applyRuntimeFilesystemMetadata(
                                    $sDestinationDir,
                                    $aSourceDirStat
                                );

                        if (empty($aMetadata['ok'])) {
                            $aResult['errors'][] =
                                'Could not restore parent directory metadata: '
                                . $sBuiltRelative
                                . ' — '
                                . (
                                    $aMetadata['error']
                                    ?? 'unknown error'
                                );

                            return $aResult;
                        }
                    }
                }
            }

            $aSourceStat =
                @stat($sSource);

            if (!is_array($aSourceStat)) {
                $aResult['errors'][] =
                    'Could not read protected tracked file metadata: '
                    . $sTrackedFile;

                return $aResult;
            }

            try {
                $sNonce =
                    bin2hex(
                        random_bytes(6)
                    );
            } catch (Throwable $e) {
                $sNonce =
                    str_replace(
                        '.',
                        '',
                        uniqid('', true)
                    );
            }

            $sTemporary =
                dirname($sDestination)
                . DIRECTORY_SEPARATOR
                . '.mud-restore-'
                . $sNonce
                . '.tmp';

            if (
                file_exists($sTemporary)
                || is_link($sTemporary)
            ) {
                $aResult['errors'][] =
                    'Temporary tracked restore path already exists.';

                return $aResult;
            }

            if (!@copy($sSource, $sTemporary)) {
                $aResult['errors'][] =
                    'Could not copy tracked runtime file: '
                    . $sTrackedFile;

                return $aResult;
            }

            $aMetadata =
                $this
                    ->applyRuntimeFilesystemMetadata(
                        $sTemporary,
                        $aSourceStat
                    );

            if (empty($aMetadata['ok'])) {
                @unlink($sTemporary);

                $aResult['errors'][] =
                    'Could not restore tracked file metadata: '
                    . $sTrackedFile
                    . ' — '
                    . (
                        $aMetadata['error']
                        ?? 'unknown error'
                    );

                return $aResult;
            }

            $sTemporaryHash =
                strtolower(
                    (string)md5_file(
                        $sTemporary
                    )
                );

            if (
                $sTemporaryHash
                !== $sBaselineHash
            ) {
                @unlink($sTemporary);

                $aResult['errors'][] =
                    'Copied tracked file failed baseline verification: '
                    . $sTrackedFile;

                return $aResult;
            }

            if (
                !@rename(
                    $sTemporary,
                    $sDestination
                )
            ) {
                @unlink($sTemporary);

                $aResult['errors'][] =
                    'Could not atomically install tracked runtime file: '
                    . $sTrackedFile;

                return $aResult;
            }

            $sFinalHash =
                strtolower(
                    (string)md5_file(
                        $sDestination
                    )
                );

            if ($sFinalHash !== $sBaselineHash) {
                $aResult['errors'][] =
                    'Installed tracked runtime file failed final verification: '
                    . $sTrackedFile;

                return $aResult;
            }

            $aFinalStat =
                @stat($sDestination);

            if (
                !is_array($aFinalStat)
                || (
                    (int)$aFinalStat['mode']
                    & 07777
                ) !== (
                    (int)$aSourceStat['mode']
                    & 07777
                )
                || (int)$aFinalStat['uid']
                    !== (int)$aSourceStat['uid']
                || (int)$aFinalStat['gid']
                    !== (int)$aSourceStat['gid']
            ) {
                $aResult['errors'][] =
                    'Installed tracked runtime file metadata failed final verification: '
                    . $sTrackedFile;

                return $aResult;
            }

            $aResult['restored_files'][] =
                array(
                    'file' =>
                        $sTrackedFile,
                    'hash' =>
                        $sFinalHash,
                    'size' =>
                        (int)$aFinalStat['size'],
                    'mode' =>
                        sprintf(
                            '%04o',
                            (int)$aFinalStat['mode']
                            & 07777
                        ),
                    'uid' =>
                        (int)$aFinalStat['uid'],
                    'gid' =>
                        (int)$aFinalStat['gid'],
                );
        }

        if (
            count($aResult['restored_files'])
            !== count($aTracked)
        ) {
            $aResult['errors'][] =
                'Not every tracked runtime file was restored.';

            return $aResult;
        }

        $aResult['ok'] = true;

        return $aResult;
    }



    protected function verifyTrackedOnlyRuntimeTree(
        array $aPlan
    ) {
        $aResult = array(
            'ok' => false,
            'error' => '',
        );

        $aRuntime =
            $aPlan['runtime']
            ?? array();

        $aTracked =
            $aPlan['tracked_manifest']
            ?? array();

        $sRuntimePath =
            (string)(
                $aRuntime['path']
                ?? ''
            );

        $sRuntimeRelative =
            trim(
                str_replace(
                    '\\',
                    '/',
                    (string)(
                        $aRuntime[
                            'relative_directory'
                        ]
                        ?? ''
                    )
                ),
                '/'
            );

        if (
            $sRuntimePath === ''
            || $sRuntimeRelative === ''
            || !is_dir($sRuntimePath)
            || is_link($sRuntimePath)
        ) {
            $aResult['error'] =
                'Live runtime tree is unavailable or unsafe.';

            return $aResult;
        }

        $aAllowedFiles = array();
        $aAllowedMetadata = array();
        $aAllowedDirectories = array();

        $sPrefix =
            $sRuntimeRelative . '/';

        foreach ($aTracked as $aTrackedFile) {
            $sFile =
                str_replace(
                    '\\',
                    '/',
                    (string)(
                        $aTrackedFile['file']
                        ?? ''
                    )
                );

            if (
                $sFile === ''
                || strpos($sFile, $sPrefix) !== 0
            ) {
                $aResult['error'] =
                    'Tracked manifest contains an out-of-scope file.';

                return $aResult;
            }

            $sRelative =
                substr(
                    $sFile,
                    strlen($sPrefix)
                );

            if (
                $sRelative === ''
                || $sRelative === '..'
                || strpos(
                    '/' . $sRelative . '/',
                    '/../'
                ) !== false
            ) {
                $aResult['error'] =
                    'Tracked manifest contains an unsafe path.';

                return $aResult;
            }

            $aAllowedFiles[$sRelative] =
                strtolower(
                    (string)(
                        $aTrackedFile[
                            'baseline_hash'
                        ]
                        ?? ''
                    )
                );

            $aAllowedMetadata[$sRelative] =
                array(
                    'size' =>
                        (int)(
                            $aTrackedFile['size']
                            ?? -1
                        ),
                    'mode' =>
                        (string)(
                            $aTrackedFile['mode']
                            ?? ''
                        ),
                    'uid' =>
                        (int)(
                            $aTrackedFile['uid']
                            ?? -1
                        ),
                    'gid' =>
                        (int)(
                            $aTrackedFile['gid']
                            ?? -1
                        ),
                );

            $sParent =
                str_replace(
                    '\\',
                    '/',
                    dirname($sRelative)
                );

            while (
                $sParent !== '.'
                && $sParent !== ''
            ) {
                $aAllowedDirectories[
                    $sParent
                ] = true;

                $sNext =
                    str_replace(
                        '\\',
                        '/',
                        dirname($sParent)
                    );

                if ($sNext === $sParent)
                    break;

                $sParent = $sNext;
            }
        }

        $aInspection =
            $this->inspectRuntimeTree(
                $sRuntimePath
            );

        if (empty($aInspection['ok'])) {
            $aResult['error'] =
                'Live runtime inspection failed: '
                . (
                    $aInspection['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        if (
            (int)$aInspection['symlinks'] !== 0
            || (int)$aInspection['hardlinks'] !== 0
            || (int)$aInspection['special_files'] !== 0
        ) {
            $aResult['error'] =
                'Live runtime contains unsafe filesystem entries.';

            return $aResult;
        }

        foreach (
            $aInspection['entries']
            ?? array()
            as $aEntry
        ) {
            $sRelative =
                (string)(
                    $aEntry['relative_path']
                    ?? ''
                );

            $sType =
                (string)(
                    $aEntry['type']
                    ?? ''
                );

            if ($sType === 'directory') {
                if (
                    empty(
                        $aAllowedDirectories[
                            $sRelative
                        ]
                    )
                ) {
                    $aResult['error'] =
                        'Unexpected live runtime directory detected: '
                        . $sRelative;

                    return $aResult;
                }

                continue;
            }

            if ($sType !== 'file') {
                $aResult['error'] =
                    'Unexpected live runtime entry detected: '
                    . $sRelative;

                return $aResult;
            }

            if (
                !array_key_exists(
                    $sRelative,
                    $aAllowedFiles
                )
            ) {
                $aResult['error'] =
                    'Unexpected live runtime file detected: '
                    . $sRelative;

                return $aResult;
            }

            $sAbsolute =
                $sRuntimePath
                . DIRECTORY_SEPARATOR
                . str_replace(
                    '/',
                    DIRECTORY_SEPARATOR,
                    $sRelative
                );

            $sHash =
                is_file($sAbsolute)
                && !is_link($sAbsolute)
                    ? strtolower(
                        (string)md5_file(
                            $sAbsolute
                        )
                    )
                    : '';

            if (
                $sHash === ''
                || $sHash
                    !== $aAllowedFiles[
                        $sRelative
                    ]
            ) {
                $aResult['error'] =
                    'Tracked live runtime file failed baseline verification: '
                    . $sRelative;

                return $aResult;
            }

            $aFileStat = @stat($sAbsolute);
            $aExpectedMetadata =
                $aAllowedMetadata[$sRelative]
                ?? array();

            if (
                !is_array($aFileStat)
                || (int)$aFileStat['size']
                    !== (int)(
                        $aExpectedMetadata['size']
                        ?? -1
                    )
                || sprintf(
                    '%04o',
                    (int)$aFileStat['mode']
                    & 07777
                ) !== (string)(
                    $aExpectedMetadata['mode']
                    ?? ''
                )
                || (int)$aFileStat['uid']
                    !== (int)(
                        $aExpectedMetadata['uid']
                        ?? -1
                    )
                || (int)$aFileStat['gid']
                    !== (int)(
                        $aExpectedMetadata['gid']
                        ?? -1
                    )
            ) {
                $aResult['error'] =
                    'Tracked live runtime file metadata failed verification: '
                    . $sRelative;

                return $aResult;
            }
        }

        if (
            (int)$aInspection['files']
            !== count($aAllowedFiles)
            || (int)$aInspection['directories']
                !== count($aAllowedDirectories)
        ) {
            $aResult['error'] =
                'Live runtime tracked-only inventory is incomplete.';

            return $aResult;
        }

        $aResult['ok'] = true;
        $aResult['inspection'] =
            $aInspection;

        return $aResult;
    }



    protected function verifyRuntimeTreeAgainstPlan(
        $sPath,
        array $aRuntime
    ) {
        $aResult = array(
            'ok' => false,
            'error' => '',
        );

        $sPath = (string)$sPath;

        if (
            $sPath === ''
            || !is_dir($sPath)
            || is_link($sPath)
        ) {
            $aResult['error'] =
                'Runtime tree is missing or unsafe.';

            return $aResult;
        }

        $aInspection =
            $this->inspectRuntimeTree(
                $sPath
            );

        if (empty($aInspection['ok'])) {
            $aResult['error'] =
                'Runtime tree inspection failed: '
                . (
                    $aInspection['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        $sExpectedInventory =
            (string)(
                $aRuntime[
                    'runtime_inventory_id'
                ]
                ?? ''
            );

        $sActualInventory =
            (string)(
                $aInspection[
                    'runtime_inventory_id'
                ]
                ?? ''
            );

        if (
            $sExpectedInventory === ''
            || $sActualInventory === ''
            || !hash_equals(
                $sExpectedInventory,
                $sActualInventory
            )
            || (int)$aInspection['files']
                !== (int)(
                    $aRuntime['file_count']
                    ?? -1
                )
            || (int)$aInspection['directories']
                !== (int)(
                    $aRuntime[
                        'subdirectory_count'
                    ]
                    ?? -1
                )
            || (int)$aInspection['bytes']
                !== (int)(
                    $aRuntime['bytes']
                    ?? -1
                )
            || (int)$aInspection['symlinks'] !== 0
            || (int)$aInspection['hardlinks'] !== 0
            || (int)$aInspection['special_files'] !== 0
        ) {
            $aResult['error'] =
                'Runtime tree does not match the approved inventory.';

            return $aResult;
        }

        $aRootStat = @stat($sPath);

        if (!is_array($aRootStat)) {
            $aResult['error'] =
                'Runtime root metadata could not be read.';

            return $aResult;
        }

        $aExpectedState =
            $aRuntime['directory_state']
            ?? array();

        $sActualMode =
            sprintf(
                '%04o',
                (int)$aRootStat['mode']
                & 07777
            );

        if (
            $sActualMode
                !== (string)(
                    $aExpectedState['mode']
                    ?? ''
                )
            || (int)$aRootStat['uid']
                !== (int)(
                    $aExpectedState['uid']
                    ?? -1
                )
            || (int)$aRootStat['gid']
                !== (int)(
                    $aExpectedState['gid']
                    ?? -1
                )
            || (int)$aRootStat['dev']
                !== (int)(
                    $aExpectedState['device']
                    ?? -1
                )
        ) {
            $aResult['error'] =
                'Runtime root metadata does not match the approved state.';

            return $aResult;
        }

        $aResult['ok'] = true;
        $aResult['inspection'] =
            $aInspection;
        $aResult['root_stat'] =
            $aRootStat;

        return $aResult;
    }





    public function getPostUpdateRuntimeMergePlan($iJournalId)
    {
        $iJournalId = (int)$iJournalId;

        $aResult = array(
            'ok' => false,
            'ready' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
        );

        if ($iJournalId <= 0) {
            $aResult['error'] =
                'A valid repair journal ID is required.';
            return $aResult;
        }

        $aJournal = $this->_oDb->getRow(
            "SELECT *
             FROM `my_una_doctor_repairs`
             WHERE `id` = ?
             LIMIT 1",
            array($iJournalId)
        );

        if (empty($aJournal)) {
            $aResult['error'] =
                'Repair journal was not found.';
            return $aResult;
        }

        if (
            (string)$aJournal['status']
            !== 'runtime_protected'
        ) {
            $aResult['error'] =
                'Post-update runtime merge planning requires a runtime-protected repair journal.';
            return $aResult;
        }

        $aOriginal = json_decode(
            (string)$aJournal['original_state_json'],
            true
        );

        $aProtected = json_decode(
            (string)$aJournal['result_json'],
            true
        );

        if (
            !is_array($aOriginal)
            || !is_array($aProtected)
            || ($aProtected['phase'] ?? '')
                !== 'runtime_protected'
            || empty($aProtected['runtime_moved'])
        ) {
            $aResult['error'] =
                'Runtime-protected journal data is incomplete or inconsistent.';
            return $aResult;
        }

        $sModule =
            (string)($aJournal['module'] ?? '');

        $aRuntime =
            $aProtected['runtime']
            ?? array();

        $aSafety =
            $aProtected['safety']
            ?? array();

        $aOldTrackedManifest =
            $aProtected['tracked_manifest']
            ?? array();

        $sRuntimePath =
            (string)($aRuntime['path'] ?? '');

        $sRuntimeRelative = trim(
            str_replace(
                '\\',
                '/',
                (string)(
                    $aRuntime['relative_directory']
                    ?? ''
                )
            ),
            '/'
        );

        if ($sRuntimeRelative !== '')
            $sRuntimeRelative .= '/';

        $sProtectedPath =
            (string)(
                $aSafety['protected_runtime_path']
                ?? ''
            );

        if (
            $sModule === ''
            || $sRuntimePath === ''
            || $sRuntimeRelative === ''
            || $sProtectedPath === ''
        ) {
            $aResult['error'] =
                'Journaled module, runtime or safety data is incomplete.';
            return $aResult;
        }

        /*
         * Verify the control barrier has not changed.
         */
        $sExpectedMaintenance =
            (string)(
                $aProtected['maintenance_value']
                ?? ''
            );

        $iGeneratorId =
            (int)(
                $aProtected['generator_id']
                ?? 0
            );

        $sExpectedSchedule =
            (string)(
                $aProtected['generator_schedule']
                ?? ''
            );

        $aOriginalGenerator =
            $aOriginal['generator']
            ?? array();

        $sLiveMaintenance =
            (string)$this->_oDb->getOne(
                "SELECT `value`
                 FROM `sys_options`
                 WHERE `name` = ?
                 LIMIT 1",
                array('sys_maintenance_mode')
            );

        if (
            $sExpectedMaintenance === ''
            || $sLiveMaintenance
                !== $sExpectedMaintenance
        ) {
            $aResult['error'] =
                'Soft-maintenance control barrier no longer matches the protected journal.';
            return $aResult;
        }

        $aLiveCron = $this->_oDb->getRow(
            "SELECT *
             FROM `sys_cron_jobs`
             WHERE `id` = ?
             LIMIT 1",
            array($iGeneratorId)
        );

        $bCronExact =
            !empty($aLiveCron)
            && (int)$aLiveCron['id']
                === $iGeneratorId
            && (string)$aLiveCron['name']
                === (string)(
                    $aOriginalGenerator['name']
                    ?? ''
                )
            && (string)$aLiveCron['class']
                === (string)(
                    $aOriginalGenerator['class']
                    ?? ''
                )
            && (string)$aLiveCron['file']
                === (string)(
                    $aOriginalGenerator['file']
                    ?? ''
                )
            && (string)(
                $aLiveCron['service_call']
                ?? ''
            ) === (string)(
                $aOriginalGenerator['service_call']
                ?? ''
            )
            && (string)$aLiveCron['time']
                === $sExpectedSchedule;

        if (!$bCronExact) {
            $aResult['error'] =
                'Generator identity or paused schedule no longer matches the protected journal.';
            return $aResult;
        }

        if (
            (int)($aLiveCron['ts'] ?? 0) > 0
            && (float)($aLiveCron['timing'] ?? 0)
                <= 0
        ) {
            $aResult['error'] =
                'Generator may still be active or incomplete.';
            return $aResult;
        }

        /*
         * The protected original must still match its exact
         * approved pre-update fingerprint.
         */
        $aProtectedVerify =
            $this->verifyRuntimeTreeAgainstPlan(
                $sProtectedPath,
                $aRuntime
            );

        if (empty($aProtectedVerify['ok'])) {
            $aResult['error'] =
                'Protected original runtime verification failed: '
                . (
                    $aProtectedVerify['error']
                    ?? 'unknown error'
                );
            return $aResult;
        }

        /*
         * Read the CURRENT UNA baseline. After a real update,
         * this is intentionally the new module baseline.
         */
        $aIntegrity =
            $this->getModuleIntegritySnapshot(
                $sModule
            );

        if (empty($aIntegrity['ok'])) {
            $aResult['error'] =
                'Current module integrity snapshot failed: '
                . (
                    $aIntegrity['error']
                    ?? 'unknown error'
                );
            return $aResult;
        }

        if (
            ($aIntegrity['source_integrity'] ?? '')
            !== 'clean'
        ) {
            $aResult['error'] =
                'Current module tracked source is not clean. Runtime merge planning is refused.';
            $aResult['integrity'] =
                $aIntegrity;
            return $aResult;
        }

        /*
         * Inspect the current live runtime tree independently.
         * Unsafe filesystem objects stop planning.
         */
        $aLiveRuntime =
            $this->inspectRuntimeTree(
                $sRuntimePath
            );

        if (empty($aLiveRuntime['ok'])) {
            $aResult['error'] =
                'Current live runtime inspection failed.';
            return $aResult;
        }

        if (
            (int)($aLiveRuntime['symlinks'] ?? 0) > 0
            || (int)($aLiveRuntime['hardlinks'] ?? 0) > 0
            || (int)($aLiveRuntime['special_files'] ?? 0) > 0
        ) {
            $aResult['error'] =
                'Current live runtime contains unsafe filesystem objects.';
            return $aResult;
        }

        /*
         * Build the old tracked set from the protection journal.
         * Old tracked source is NEVER treated as generated data,
         * even if a later version stops tracking that path.
         */
        $aOldTracked = array();

        foreach ($aOldTrackedManifest as $aFile) {
            $sFile =
                (string)($aFile['file'] ?? '');

            if ($sFile !== '')
                $aOldTracked[$sFile] = true;
        }

        /*
         * Current tracked set comes from the current UNA
         * baseline, which may be a post-update baseline.
         */
        $aCurrentTracked =
            $aIntegrity['tracked']
            ?? array();

        $aRestore = array();
        $aOldTrackedSkipped = array();
        $aNewlyTrackedCollisions = array();
        $aLiveCollisions = array();

        $aProtectedInspection =
            $aProtectedVerify['inspection']
            ?? array();

        foreach (
            ($aProtectedInspection['entries'] ?? array())
            as $aEntry
        ) {
            if (
                (string)($aEntry['type'] ?? '')
                !== 'file'
            ) {
                continue;
            }

            $sRelative = ltrim(
                str_replace(
                    '\\',
                    '/',
                    (string)(
                        $aEntry['relative_path']
                        ?? ''
                    )
                ),
                '/'
            );

            if ($sRelative === '')
                continue;

            $sModuleFile =
                $sRuntimeRelative
                . $sRelative;

            /*
             * Anything tracked before protection is source,
             * not generated runtime content.
             */
            if (isset($aOldTracked[$sModuleFile])) {
                $aOldTrackedSkipped[] =
                    $sModuleFile;
                continue;
            }

            /*
             * A formerly-untracked runtime path may become
             * tracked in the new version. Never overwrite it.
             */
            if (isset($aCurrentTracked[$sModuleFile])) {
                $aNewlyTrackedCollisions[] =
                    $sModuleFile;
                continue;
            }

            $sLivePath =
                rtrim($sRuntimePath, '/\\')
                . DIRECTORY_SEPARATOR
                . str_replace(
                    '/',
                    DIRECTORY_SEPARATOR,
                    $sRelative
                );

            /*
             * Never silently overwrite anything already created
             * by the update or another process.
             */
            if (
                file_exists($sLivePath)
                || is_link($sLivePath)
            ) {
                $aLiveCollisions[] =
                    $sModuleFile;
                continue;
            }

            /*
             * Parent path components must either be absent or
             * real directories. Symlink/file parents collide.
             */
            $sRuntimeRoot =
                rtrim($sRuntimePath, '/\\');

            $sParent =
                dirname($sLivePath);

            $bParentCollision = false;

            while (
                $sParent !== $sRuntimeRoot
                && strpos(
                    $sParent,
                    $sRuntimeRoot
                    . DIRECTORY_SEPARATOR
                ) === 0
            ) {
                if (
                    is_link($sParent)
                    || (
                        file_exists($sParent)
                        && !is_dir($sParent)
                    )
                ) {
                    $bParentCollision = true;
                    break;
                }

                $sNext =
                    dirname($sParent);

                if ($sNext === $sParent)
                    break;

                $sParent = $sNext;
            }

            if ($bParentCollision) {
                $aLiveCollisions[] =
                    $sModuleFile;
                continue;
            }

            $aRestore[] =
                $sModuleFile;
        }

        sort($aRestore);
        sort($aOldTrackedSkipped);
        sort($aNewlyTrackedCollisions);
        sort($aLiveCollisions);

        /*
         * The approval fingerprint includes every classified
         * path, not only the UI previews.
         */
        $aFingerprint = array(
            'journal_id' =>
                $iJournalId,
            'protection_id' =>
                (string)(
                    $aProtected['protection_id']
                    ?? ''
                ),
            'protected_inventory_id' =>
                (string)(
                    $aProtectedInspection[
                        'runtime_inventory_id'
                    ]
                    ?? ''
                ),
            'module' =>
                $sModule,
            'version' =>
                (string)(
                    $aIntegrity['version']
                    ?? ''
                ),
            'tracked' =>
                $aCurrentTracked,
            'restore' =>
                $aRestore,
            'old_tracked_skipped' =>
                $aOldTrackedSkipped,
            'newly_tracked_collisions' =>
                $aNewlyTrackedCollisions,
            'live_collisions' =>
                $aLiveCollisions,
        );

        $sJson = json_encode(
            $aFingerprint,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($sJson === false)
            $sJson = serialize($aFingerprint);

        $aResult['merge_plan_id'] =
            substr(
                hash('sha256', $sJson),
                0,
                16
            );

        $aResult['module'] =
            $sModule;

        $aResult['version'] =
            (string)(
                $aIntegrity['version']
                ?? ''
            );

        $aResult['protected_inventory_id'] =
            (string)(
                $aProtectedInspection[
                    'runtime_inventory_id'
                ]
                ?? ''
            );

        $aResult['old_tracked_count'] =
            count($aOldTrackedSkipped);

        $aResult['restore_candidate_count'] =
            count($aRestore);

        $aResult[
            'newly_tracked_collision_count'
        ] =
            count($aNewlyTrackedCollisions);

        $aResult['live_collision_count'] =
            count($aLiveCollisions);

        $aResult['current_tracked_count'] =
            (int)(
                $aIntegrity['tracked_count']
                ?? 0
            );

        $aResult[
            'current_tracked_runtime_count'
        ] =
            count(
                array_filter(
                    array_keys($aCurrentTracked),
                    static function ($sFile)
                        use ($sRuntimeRelative) {
                        return strpos(
                            (string)$sFile,
                            $sRuntimeRelative
                        ) === 0;
                    }
                )
            );

        $aResult['restore_preview'] =
            array_slice($aRestore, 0, 50);

        $aResult['old_tracked_preview'] =
            array_slice(
                $aOldTrackedSkipped,
                0,
                50
            );

        $aResult[
            'newly_tracked_collision_preview'
        ] =
            array_slice(
                $aNewlyTrackedCollisions,
                0,
                50
            );

        $aResult['live_collision_preview'] =
            array_slice(
                $aLiveCollisions,
                0,
                50
            );

        $aResult['integrity_summary'] = array(
            'tracked_count' =>
                (int)(
                    $aIntegrity['tracked_count']
                    ?? 0
                ),
            'modified_tracked_count' =>
                (int)(
                    $aIntegrity[
                        'modified_tracked_count'
                    ]
                    ?? 0
                ),
            'missing_tracked_count' =>
                (int)(
                    $aIntegrity[
                        'missing_tracked_count'
                    ]
                    ?? 0
                ),
            'source_integrity' =>
                (string)(
                    $aIntegrity[
                        'source_integrity'
                    ]
                    ?? ''
                ),
        );

        if (
            !empty($aNewlyTrackedCollisions)
            || !empty($aLiveCollisions)
        ) {
            $aResult['ok'] = true;
            $aResult['ready'] = false;
            $aResult['error'] =
                'Runtime merge collisions require review. No files were changed.';
            return $aResult;
        }

        $aResult['ok'] = true;
        $aResult['ready'] = true;

        return $aResult;
    }


    public function protectArmedRuntime(
        $iJournalId,
        $sApprovedProtectionId
    ) {
        $iJournalId = (int)$iJournalId;
        $sApprovedProtectionId =
            trim((string)$sApprovedProtectionId);

        $aResult = array(
            'ok' => false,
            'protected' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'protection_id' =>
                $sApprovedProtectionId,
        );

        if (
            PHP_SAPI === 'cli'
            || ($_SERVER['REQUEST_METHOD'] ?? '')
                !== 'POST'
        ) {
            $aResult['error'] =
                'Runtime protection is allowed only from an authenticated web POST.';

            return $aResult;
        }

        if (!isAdmin()) {
            $aResult['error'] =
                'Administrator authorization is required.';

            return $aResult;
        }

        if (
            $iJournalId <= 0
            || $sApprovedProtectionId === ''
        ) {
            $aResult['error'] =
                'A valid journal ID and approved protection ID are required.';

            return $aResult;
        }

        /*
         * The journal itself must still be in the exact state
         * for which runtime protection is allowed.
         */
        $aJournal = $this->_oDb->getRow(
            "SELECT *
             FROM `my_una_doctor_repairs`
             WHERE `id` = ?
             LIMIT 1",
            array($iJournalId)
        );

        if (empty($aJournal)) {
            $aResult['error'] =
                'Repair journal was not found.';

            return $aResult;
        }

        if ((string)$aJournal['status'] !== 'armed') {
            $aResult['error'] =
                'Runtime protection requires an armed repair journal.';

            return $aResult;
        }

        $aOriginal = json_decode(
            (string)$aJournal['original_state_json'],
            true
        );

        $aArmed = json_decode(
            (string)$aJournal['result_json'],
            true
        );

        if (
            !is_array($aOriginal)
            || !is_array($aArmed)
            || ($aArmed['phase'] ?? '')
                !== 'armed'
            || !empty($aArmed['runtime_moved'])
        ) {
            $aResult['error'] =
                'Armed repair journal state is incomplete or inconsistent.';

            return $aResult;
        }

        /*
         * Rebuild the complete read-only plan from current
         * filesystem and database state. Never trust a plan
         * supplied by the browser.
         */
        $aPlan =
            $this->getRuntimeProtectionPlan(
                $iJournalId
            );

        if (
            empty($aPlan['ok'])
            || empty($aPlan['ready'])
        ) {
            $aResult['error'] =
                'Fresh runtime protection preflight is no longer ready: '
                . (
                    $aPlan['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        $sFreshProtectionId =
            (string)(
                $aPlan['protection_id']
                ?? ''
            );

        if (
            $sFreshProtectionId === ''
            || !hash_equals(
                $sFreshProtectionId,
                $sApprovedProtectionId
            )
        ) {
            $aResult['error'] =
                'Runtime protection approval is stale. Re-preview and approve the current protection plan.';

            $aResult['fresh_protection_id'] =
                $sFreshProtectionId;

            return $aResult;
        }

        /*
         * Preserve the exact armed control-barrier values in
         * the durable runtime-protection journal state.
         */
        $sExpectedMaintenance =
            (string)(
                $aArmed['maintenance_value']
                ?? ''
            );

        $iExpectedGeneratorId =
            (int)(
                $aArmed['generator_id']
                ?? 0
            );

        $sExpectedSchedule =
            (string)(
                $aArmed['generator_schedule']
                ?? ''
            );

        $aOriginalGenerator =
            $aOriginal['generator']
            ?? array();

        if (
            $sExpectedMaintenance === ''
            || $iExpectedGeneratorId <= 0
            || $sExpectedSchedule === ''
            || (int)(
                $aOriginalGenerator['id']
                ?? 0
            ) !== $iExpectedGeneratorId
        ) {
            $aResult['error'] =
                'Armed control-barrier state is incomplete.';

            return $aResult;
        }

        $encode = static function ($value) {
            $json = json_encode(
                $value,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

            return $json === false
                ? '{}'
                : $json;
        };

        /*
         * IMPORTANT:
         * Persist everything required for crash recovery BEFORE
         * the first filesystem rename can occur.
         */
        $aProtectingState = array(
            'phase' =>
                'runtime_protecting',

            'changes_applied' =>
                true,

            'protection_id' =>
                $sFreshProtectionId,

            'maintenance_value' =>
                $sExpectedMaintenance,

            'generator_id' =>
                $iExpectedGeneratorId,

            'generator_schedule' =>
                $sExpectedSchedule,

            'runtime_moved' =>
                false,

            'runtime' =>
                $aPlan['runtime']
                ?? array(),

            'safety' =>
                $aPlan['safety']
                ?? array(),

            'tracked_manifest' =>
                $aPlan['tracked_manifest']
                ?? array(),

            'generator' =>
                $aPlan['generator']
                ?? array(),

            'runtime_recovery' => array(
                'phase' =>
                    'pre_rename',

                'original_runtime_verified' =>
                    true,

                'runtime_inventory_id' =>
                    (string)(
                        $aPlan['runtime'][
                            'runtime_inventory_id'
                        ]
                        ?? ''
                    ),

                'protected_tree_exists' =>
                    false,
            ),
        );

        $bProtectingJournalPersisted = false;

        try {
            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' =>
                        'runtime_protecting',

                    'result_json' =>
                        $encode(
                            $aProtectingState
                        ),

                    'error_text' =>
                        '',
                )
            );

            $bProtectingJournalPersisted =
                true;

            /*
             * Re-verify the exact control barrier after the
             * durable journal transition and immediately before
             * allowing the filesystem executor to run.
             */
            $sLiveMaintenance =
                (string)$this->_oDb->getOne(
                    "SELECT `value`
                     FROM `sys_options`
                     WHERE `name` = ?
                     LIMIT 1",
                    array(
                        'sys_maintenance_mode'
                    )
                );

            if (
                $sLiveMaintenance
                !== $sExpectedMaintenance
            ) {
                throw new RuntimeException(
                    'Soft-maintenance state changed immediately before runtime protection.'
                );
            }

            $aLiveCron =
                $this->_oDb->getRow(
                    "SELECT *
                     FROM `sys_cron_jobs`
                     WHERE `id` = ?
                     LIMIT 1",
                    array(
                        $iExpectedGeneratorId
                    )
                );

            $bCronExact =
                !empty($aLiveCron)
                && (int)$aLiveCron['id']
                    === $iExpectedGeneratorId
                && (string)$aLiveCron['name']
                    === (string)(
                        $aOriginalGenerator['name']
                        ?? ''
                    )
                && (string)$aLiveCron['class']
                    === (string)(
                        $aOriginalGenerator['class']
                        ?? ''
                    )
                && (string)$aLiveCron['file']
                    === (string)(
                        $aOriginalGenerator['file']
                        ?? ''
                    )
                && (string)(
                    $aLiveCron['service_call']
                    ?? ''
                ) === (string)(
                    $aOriginalGenerator[
                        'service_call'
                    ]
                    ?? ''
                )
                && (string)$aLiveCron['time']
                    === $sExpectedSchedule;

            if (!$bCronExact) {
                throw new RuntimeException(
                    'Generator identity or paused schedule changed immediately before runtime protection.'
                );
            }

            if (
                (int)($aLiveCron['ts'] ?? 0) > 0
                && (float)(
                    $aLiveCron['timing']
                    ?? 0
                ) <= 0
            ) {
                throw new RuntimeException(
                    'Generator may still be active or incomplete. Runtime protection refused.'
                );
            }

            /*
             * The executor performs another full inventory and
             * root-metadata verification immediately before its
             * atomic rename.
             */
            $aExecution =
                $this->executeRuntimeProtectionPlan(
                    $aPlan
                );

            if (
                empty($aExecution['ok'])
                || empty(
                    $aExecution['runtime_moved']
                )
                || empty(
                    $aExecution[
                        'protected_verified'
                    ]
                )
                || empty(
                    $aExecution[
                        'live_tracked_verified'
                    ]
                )
            ) {
                throw new RuntimeException(
                    'Runtime protection executor did not reach a fully verified protected state.'
                );
            }

            $aProtectedState =
                $aProtectingState;

            $aProtectedState['phase'] =
                'runtime_protected';

            $aProtectedState['runtime_moved'] =
                true;

            $aProtectedState[
                'runtime_recovery'
            ] = array(
                'phase' =>
                    'runtime_protected',

                'original_runtime_verified' =>
                    true,

                'runtime_inventory_id' =>
                    (string)(
                        $aExecution[
                            'protected_inventory_id'
                        ]
                        ?? ''
                    ),

                'protected_tree_exists' =>
                    true,

                'live_tracked_only_verified' =>
                    true,
            );

            $aProtectedState['execution'] =
                array(
                    'protected_verified' =>
                        true,

                    'live_tracked_verified' =>
                        true,

                    'restored_files' =>
                        $aExecution[
                            'restored_files'
                        ]
                        ?? array(),
                );

            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' =>
                        'runtime_protected',

                    'result_json' =>
                        $encode(
                            $aProtectedState
                        ),

                    'error_text' =>
                        '',
                )
            );

            $aResult['ok'] = true;
            $aResult['protected'] = true;
            $aResult['changes_applied'] = true;
            $aResult['protection_id'] =
                $sFreshProtectionId;
            $aResult['state'] =
                $aProtectedState;

            return $aResult;
        } catch (Throwable $e) {
            /*
             * If the durable runtime_protecting state was
             * reached, use the existing crash-aware restore
             * path. It distinguishes:
             *
             *  - journaled before rename, no move occurred;
             *  - rename occurred and protected tree exists.
             */
            $aRecovery = array();

            if ($bProtectingJournalPersisted) {
                try {
                    $aRecovery =
                        $this
                            ->restoreProtectedRuntime(
                                $iJournalId
                            );
                } catch (Throwable $recoveryError) {
                    $aRecovery = array(
                        'ok' => false,
                        'error' =>
                            $recoveryError
                                ->getMessage(),
                    );
                }
            }

            $aResult['error'] =
                $e->getMessage();

            $aResult['recovery'] =
                $aRecovery;

            if (
                $bProtectingJournalPersisted
                && !empty($aRecovery['ok'])
            ) {
                $aResult['error'] .=
                    ' Automatic runtime recovery succeeded; the journal returned to the armed state.';
            } elseif ($bProtectingJournalPersisted) {
                $aResult['error'] .=
                    ' Automatic runtime recovery did not complete; keep the control barrier active and review the journal before further action.';
            }

            return $aResult;
        }
    }


    protected function executeRuntimeProtectionPlan(
        array $aPlan
    ) {
        $aResult = array(
            'ok' => false,
            'runtime_moved' => false,
            'protected_verified' => false,
            'live_tracked_verified' => false,
        );

        $aRuntime =
            $aPlan['runtime']
            ?? array();

        $aSafety =
            $aPlan['safety']
            ?? array();

        $sRuntimePath =
            (string)(
                $aRuntime['path']
                ?? ''
            );

        $sProtectedPath =
            (string)(
                $aSafety[
                    'protected_runtime_path'
                ]
                ?? ''
            );

        $sSafetyBase =
            (string)(
                $aSafety['base_path']
                ?? ''
            );

        if (
            $sRuntimePath === ''
            || $sProtectedPath === ''
            || $sSafetyBase === ''
        ) {
            throw new RuntimeException(
                'Runtime protection paths are incomplete.'
            );
        }

        if (
            file_exists($sProtectedPath)
            || is_link($sProtectedPath)
        ) {
            throw new RuntimeException(
                'Journal-specific protected runtime path already exists.'
            );
        }

        /*
         * Final pre-rename verification of the exact approved
         * runtime inventory and root metadata.
         */
        $aLiveBefore =
            $this->verifyRuntimeTreeAgainstPlan(
                $sRuntimePath,
                $aRuntime
            );

        if (empty($aLiveBefore['ok'])) {
            throw new RuntimeException(
                'Live runtime failed final pre-rename verification: '
                . (
                    $aLiveBefore['error']
                    ?? 'unknown error'
                )
            );
        }

        $aSafetyStat =
            @stat($sSafetyBase);

        if (!is_array($aSafetyStat)) {
            throw new RuntimeException(
                'Runtime safety destination metadata could not be read.'
            );
        }

        if (
            (int)$aSafetyStat['dev']
            !== (int)(
                $aRuntime[
                    'directory_state'
                ]['device']
                ?? -1
            )
        ) {
            throw new RuntimeException(
                'Runtime and safety destination are no longer on the same filesystem.'
            );
        }

        /*
         * One same-filesystem rename protects the complete
         * runtime tree atomically.
         */
        if (
            !@rename(
                $sRuntimePath,
                $sProtectedPath
            )
        ) {
            throw new RuntimeException(
                'Atomic runtime protection rename failed.'
            );
        }

        $aResult['runtime_moved'] = true;

        if (
            file_exists($sRuntimePath)
            || is_link($sRuntimePath)
        ) {
            throw new RuntimeException(
                'Original runtime path is unexpectedly still occupied after rename.'
            );
        }

        $aProtected =
            $this->verifyRuntimeTreeAgainstPlan(
                $sProtectedPath,
                $aRuntime
            );

        if (empty($aProtected['ok'])) {
            throw new RuntimeException(
                'Protected runtime tree failed verification after rename: '
                . (
                    $aProtected['error']
                    ?? 'unknown error'
                )
            );
        }

        $aResult['protected_verified'] =
            true;

        /*
         * Recreate the live runtime root. mkdir() is deliberately
         * restrictive first; exact original metadata is then
         * restored from the protected directory's raw stat().
         */
        if (
            !@mkdir(
                $sRuntimePath,
                0700
            )
        ) {
            throw new RuntimeException(
                'Live runtime root could not be recreated.'
            );
        }

        $aProtectedRootStat =
            $aProtected['root_stat']
            ?? array();

        if (!is_array($aProtectedRootStat)) {
            throw new RuntimeException(
                'Protected runtime root metadata is unavailable.'
            );
        }

        $aRootMetadata =
            $this->applyRuntimeFilesystemMetadata(
                $sRuntimePath,
                $aProtectedRootStat
            );

        if (empty($aRootMetadata['ok'])) {
            throw new RuntimeException(
                'Live runtime root metadata could not be restored: '
                . (
                    $aRootMetadata['error']
                    ?? 'unknown error'
                )
            );
        }

        $aLiveRootStat =
            @stat($sRuntimePath);

        if (
            !is_array($aLiveRootStat)
            || (int)$aLiveRootStat['dev']
                !== (int)(
                    $aRuntime[
                        'directory_state'
                    ]['device']
                    ?? -1
                )
        ) {
            throw new RuntimeException(
                'Recreated runtime root is not on the expected filesystem.'
            );
        }

        /*
         * Copy back only files UNA already tracks.
         * Runtime-generated files remain protected outside
         * the module source tree.
         */
        $aTrackedRestore =
            $this->restoreTrackedRuntimeFiles(
                $aPlan
            );

        if (empty($aTrackedRestore['ok'])) {
            throw new RuntimeException(
                'Tracked runtime restoration failed: '
                . implode(
                    ' | ',
                    $aTrackedRestore['errors']
                    ?? array(
                        'unknown error'
                    )
                )
            );
        }

        /*
         * Independent post-operation verification:
         * live tree = tracked content only;
         * protected tree = complete original inventory.
         */
        $aLiveTracked =
            $this->verifyTrackedOnlyRuntimeTree(
                $aPlan
            );

        if (empty($aLiveTracked['ok'])) {
            throw new RuntimeException(
                'Recreated live runtime failed tracked-only verification: '
                . (
                    $aLiveTracked['error']
                    ?? 'unknown error'
                )
            );
        }

        $aResult['live_tracked_verified'] =
            true;

        $aProtectedFinal =
            $this->verifyRuntimeTreeAgainstPlan(
                $sProtectedPath,
                $aRuntime
            );

        if (empty($aProtectedFinal['ok'])) {
            throw new RuntimeException(
                'Protected runtime changed during protection: '
                . (
                    $aProtectedFinal['error']
                    ?? 'unknown error'
                )
            );
        }

        $aResult['protected_verified'] =
            true;

        $aResult['restored_files'] =
            $aTrackedRestore[
                'restored_files'
            ]
            ?? array();

        $aResult['protected_inventory_id'] =
            (string)(
                $aProtectedFinal[
                    'inspection'
                ]['runtime_inventory_id']
                ?? ''
            );

        $aResult['live_inventory'] =
            $aLiveTracked['inspection']
            ?? array();

        $aResult['ok'] = true;

        return $aResult;
    }


    public function restoreProtectedRuntime($iJournalId)
    {
        $iJournalId = (int)$iJournalId;

        $aResult = array(
            'ok' => false,
            'restored' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
        );

        if (
            PHP_SAPI === 'cli'
            || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        ) {
            $aResult['error'] =
                'Runtime restore is allowed only from an authenticated web POST.';

            return $aResult;
        }

        if (!isAdmin()) {
            $aResult['error'] =
                'Administrator authorization is required.';

            return $aResult;
        }

        if ($iJournalId <= 0) {
            $aResult['error'] =
                'A valid repair journal ID is required.';

            return $aResult;
        }

        $aJournal = $this->_oDb->getRow(
            "SELECT *
             FROM `my_una_doctor_repairs`
             WHERE `id` = ?
             LIMIT 1",
            array($iJournalId)
        );

        if (empty($aJournal)) {
            $aResult['error'] =
                'Repair journal was not found.';

            return $aResult;
        }

        $sJournalStatus =
            (string)$aJournal['status'];

        if (
            !in_array(
                $sJournalStatus,
                array(
                    'runtime_protecting',
                    'runtime_protected',
                    'runtime_restoring',
                ),
                true
            )
        ) {
            $aResult['error'] =
                'Only a runtime-protecting, runtime-protected or runtime-restoring repair journal can restore runtime content.';

            return $aResult;
        }

        $aOriginal = json_decode(
            (string)$aJournal['original_state_json'],
            true
        );

        $aProtected = json_decode(
            (string)$aJournal['result_json'],
            true
        );

        $sRuntimePhase =
            is_array($aProtected)
                ? (string)(
                    $aProtected['phase']
                    ?? ''
                )
                : '';

        if (
            !is_array($aOriginal)
            || !is_array($aProtected)
            || !in_array(
                $sRuntimePhase,
                array(
                    'runtime_protecting',
                    'runtime_protected',
                    'runtime_restoring',
                ),
                true
            )
        ) {
            $aResult['error'] =
                'Runtime protection journal data is incomplete or unreadable.';

            return $aResult;
        }

        if (
            $sRuntimePhase === 'runtime_protected'
            && empty($aProtected['runtime_moved'])
        ) {
            $aResult['error'] =
                'Protected runtime journal does not record a completed runtime move.';

            return $aResult;
        }

        $aGenerator =
            $aOriginal['generator']
            ?? array();

        $iCronId =
            (int)($aGenerator['id'] ?? 0);

        $sExpectedMaintenance =
            (string)(
                $aProtected['maintenance_value']
                ?? ''
            );

        $sExpectedSchedule =
            (string)(
                $aProtected['generator_schedule']
                ?? ''
            );

        if (
            $iCronId <= 0
            || $sExpectedMaintenance === ''
            || $sExpectedSchedule === ''
            || (int)(
                $aProtected['generator_id']
                ?? 0
            ) !== $iCronId
        ) {
            $aResult['error'] =
                'Protected-runtime control-barrier data is incomplete.';

            return $aResult;
        }

        /*
         * The control barrier must still be exact before
         * runtime content can be moved back.
         */
        $sLiveMaintenance =
            (string)$this->_oDb->getOne(
                "SELECT `value`
                 FROM `sys_options`
                 WHERE `name` = ?
                 LIMIT 1",
                array('sys_maintenance_mode')
            );

        if (
            $sLiveMaintenance
            !== $sExpectedMaintenance
        ) {
            $aResult['error'] =
                'Soft-maintenance state changed after runtime protection. Restore refused.';

            return $aResult;
        }

        $aLiveCron = $this->_oDb->getRow(
            "SELECT *
             FROM `sys_cron_jobs`
             WHERE `id` = ?
             LIMIT 1",
            array($iCronId)
        );

        $bCronExact =
            !empty($aLiveCron)
            && (string)$aLiveCron['name']
                === (string)($aGenerator['name'] ?? '')
            && (string)$aLiveCron['class']
                === (string)($aGenerator['class'] ?? '')
            && (string)$aLiveCron['file']
                === (string)($aGenerator['file'] ?? '')
            && (string)($aLiveCron['service_call'] ?? '')
                === (string)($aGenerator['service_call'] ?? '')
            && (string)$aLiveCron['time']
                === $sExpectedSchedule;

        if (!$bCronExact) {
            $aResult['error'] =
                'Generator barrier changed after runtime protection. Restore refused.';

            return $aResult;
        }

        $aPlan = array(
            'runtime' =>
                $aProtected['runtime']
                ?? array(),

            'safety' =>
                $aProtected['safety']
                ?? array(),

            'tracked_manifest' =>
                $aProtected['tracked_manifest']
                ?? array(),
        );

        $sProtectedPath =
            (string)(
                $aPlan['safety'][
                    'protected_runtime_path'
                ]
                ?? ''
            );

        $encode = static function ($value) {
            $json = json_encode(
                $value,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

            return $json === false
                ? '{}'
                : $json;
        };

        if ($sProtectedPath === '') {
            $aResult['error'] =
                'Protected runtime safety path is missing from the journal.';

            return $aResult;
        }

        /*
         * Crash recovery before the atomic rename:
         *
         * If the journal reached runtime_protecting but the
         * journal-specific protected tree does not exist, the
         * rename may never have happened.
         *
         * Do not assume that. Prove that the complete original
         * live runtime tree is still present and has the exact
         * journaled inventory fingerprint before returning the
         * journal to armed.
         */
        if (
            $sJournalStatus === 'runtime_protecting'
            && !file_exists($sProtectedPath)
            && !is_link($sProtectedPath)
        ) {
            if (!empty($aProtected['runtime_moved'])) {
                $aResult['error'] =
                    'Runtime journal claims the tree was moved, but the protected tree is missing. Automatic recovery refused.';

                return $aResult;
            }

            $sLiveRuntimePath =
                (string)(
                    $aPlan['runtime']['path']
                    ?? ''
                );

            $sExpectedInventoryId =
                (string)(
                    $aPlan['runtime'][
                        'runtime_inventory_id'
                    ]
                    ?? ''
                );

            if (
                $sLiveRuntimePath === ''
                || $sExpectedInventoryId === ''
            ) {
                $aResult['error'] =
                    'Pre-rename recovery data is incomplete.';

                return $aResult;
            }

            $aLiveInspection =
                $this->inspectRuntimeTree(
                    $sLiveRuntimePath
                );

            $sLiveInventoryId =
                (string)(
                    $aLiveInspection[
                        'runtime_inventory_id'
                    ]
                    ?? ''
                );

            $bInventoryExact =
                !empty($aLiveInspection['ok'])
                && (int)(
                    $aLiveInspection['files']
                    ?? -1
                ) === (int)(
                    $aPlan['runtime']['file_count']
                    ?? -2
                )
                && (int)(
                    $aLiveInspection['directories']
                    ?? -1
                ) === (int)(
                    $aPlan['runtime'][
                        'subdirectory_count'
                    ]
                    ?? -2
                )
                && (int)(
                    $aLiveInspection['bytes']
                    ?? -1
                ) === (int)(
                    $aPlan['runtime']['bytes']
                    ?? -2
                )
                && (int)(
                    $aLiveInspection['symlinks']
                    ?? -1
                ) === 0
                && (int)(
                    $aLiveInspection['hardlinks']
                    ?? -1
                ) === 0
                && (int)(
                    $aLiveInspection['special_files']
                    ?? -1
                ) === 0
                && $sLiveInventoryId !== ''
                && hash_equals(
                    $sExpectedInventoryId,
                    $sLiveInventoryId
                );

            if (!$bInventoryExact) {
                $aResult['error'] =
                    'Pre-rename crash recovery could not prove the original runtime tree is intact. Journal remains unresolved.';

                return $aResult;
            }

            $aRecoveredArmed = array(
                'phase' =>
                    'armed',

                'changes_applied' =>
                    true,

                'maintenance_value' =>
                    $sExpectedMaintenance,

                'generator_id' =>
                    $iCronId,

                'generator_schedule' =>
                    $sExpectedSchedule,

                'runtime_moved' =>
                    false,

                'last_runtime_protection_id' =>
                    (string)(
                        $aProtected['protection_id']
                        ?? ''
                    ),

                'runtime_recovery' => array(
                    'phase' =>
                        'pre_rename',

                    'original_runtime_verified' =>
                        true,

                    'runtime_inventory_id' =>
                        $sLiveInventoryId,

                    'protected_tree_exists' =>
                        false,
                ),
            );

            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' =>
                        'armed',

                    'result_json' =>
                        $encode(
                            $aRecoveredArmed
                        ),

                    'error_text' =>
                        '',
                )
            );

            $aResult['ok'] = true;
            $aResult['restored'] = true;
            $aResult['changes_applied'] = false;
            $aResult['state'] =
                $aRecoveredArmed;

            return $aResult;
        }

        /*
         * Crash recovery after the rollback rename:
         *
         * runtime_restoring is journaled before rollback begins.
         * If the protected tree is now absent, rollback may have
         * already atomically renamed the complete original tree
         * back into the live runtime path before PHP stopped.
         *
         * Never assume success. Prove the live tree exactly
         * matches the approved original inventory and root
         * metadata before returning the journal to armed.
         */
        if (
            $sJournalStatus === 'runtime_restoring'
            && !file_exists($sProtectedPath)
            && !is_link($sProtectedPath)
        ) {
            $sLiveRuntimePath =
                (string)(
                    $aPlan['runtime']['path']
                    ?? ''
                );

            if ($sLiveRuntimePath === '') {
                $aResult['error'] =
                    'Post-rename runtime recovery data is incomplete.';

                return $aResult;
            }

            $aLiveVerification =
                $this->verifyRuntimeTreeAgainstPlan(
                    $sLiveRuntimePath,
                    $aPlan['runtime']
                        ?? array()
                );

            if (empty($aLiveVerification['ok'])) {
                $aResult['error'] =
                    'Post-rename crash recovery could not prove the original runtime tree is intact: '
                    . (
                        $aLiveVerification['error']
                        ?? 'unknown error'
                    );

                return $aResult;
            }

            $sRecoveredInventoryId =
                (string)(
                    $aLiveVerification[
                        'inspection'
                    ]['runtime_inventory_id']
                    ?? ''
                );

            $aRecoveredArmed = array(
                'phase' =>
                    'armed',

                'changes_applied' =>
                    true,

                'maintenance_value' =>
                    $sExpectedMaintenance,

                'generator_id' =>
                    $iCronId,

                'generator_schedule' =>
                    $sExpectedSchedule,

                'runtime_moved' =>
                    false,

                'last_runtime_protection_id' =>
                    (string)(
                        $aProtected['protection_id']
                        ?? ''
                    ),

                'runtime_restore' => array(
                    'restored' =>
                        true,

                    'crash_recovery' =>
                        true,

                    'phase' =>
                        'post_rename',

                    'original_runtime_verified' =>
                        true,

                    'runtime_inventory_id' =>
                        $sRecoveredInventoryId,

                    'protected_tree_exists' =>
                        false,
                ),
            );

            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' =>
                        'armed',

                    'result_json' =>
                        $encode(
                            $aRecoveredArmed
                        ),

                    'error_text' =>
                        '',
                )
            );

            $aResult['ok'] = true;
            $aResult['restored'] = true;
            $aResult['changes_applied'] = false;
            $aResult['state'] =
                $aRecoveredArmed;

            return $aResult;
        }


        if (
            !is_dir($sProtectedPath)
            || is_link($sProtectedPath)
        ) {
            $aResult['error'] =
                'Protected runtime tree is missing or unsafe.';

            return $aResult;
        }

        try {
            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' =>
                        'runtime_restoring',

                    'result_json' =>
                        $encode(
                            array_merge(
                                $aProtected,
                                array(
                                    'phase' =>
                                        'runtime_restoring',
                                )
                            )
                        ),
                )
            );

            $aRollback =
                $this->rollbackRuntimeProtection(
                    $aPlan
                );

            if (
                empty($aRollback['ok'])
                || empty($aRollback['restored'])
            ) {
                throw new RuntimeException(
                    'Protected runtime content could not be fully restored.'
                );
            }

            /*
             * Runtime is now back in place, while the control
             * barrier intentionally remains active.
             */
            $aArmedResult = array(
                'phase' =>
                    'armed',

                'changes_applied' =>
                    true,

                'maintenance_value' =>
                    $sExpectedMaintenance,

                'generator_id' =>
                    $iCronId,

                'generator_schedule' =>
                    $sExpectedSchedule,

                'runtime_moved' =>
                    false,

                'last_runtime_protection_id' =>
                    (string)(
                        $aProtected['protection_id']
                        ?? ''
                    ),

                'runtime_restore' => array(
                    'restored' =>
                        true,

                    'protected_tree_verified' =>
                        !empty(
                            $aRollback[
                                'protected_tree_verified'
                            ]
                        ),

                    'live_tree_removed' =>
                        !empty(
                            $aRollback[
                                'live_tree_removed'
                            ]
                        ),
                ),
            );

            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' =>
                        'armed',

                    'result_json' =>
                        $encode($aArmedResult),

                    'error_text' =>
                        '',
                )
            );

            $aResult['ok'] = true;
            $aResult['restored'] = true;
            $aResult['changes_applied'] = true;
            $aResult['state'] =
                $aArmedResult;

            return $aResult;
        } catch (Throwable $e) {
            /*
             * Do not pretend an incomplete runtime rollback is
             * safe. Keep the control barrier active and block
             * further automatic arming.
             */
            $this->_oDb->updateRepairJournal(
                $iJournalId,
                array(
                    'status' =>
                        'runtime_rollback_failed',

                    'result_json' =>
                        $encode(
                            array_merge(
                                $aProtected,
                                array(
                                    'phase' =>
                                        'runtime_rollback_failed',

                                    'runtime_moved' =>
                                        true,
                                )
                            )
                        ),

                    'error_text' =>
                        $e->getMessage(),
                )
            );

            $aResult['error'] =
                $e->getMessage();

            return $aResult;
        }
    }


    public function restoreArmedRepair($iJournalId)
    {
        $iJournalId = (int)$iJournalId;

        $aResult = array(
            'ok' => false,
            'restored' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
        );

        if (
            PHP_SAPI === 'cli'
            || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        ) {
            $aResult['error'] =
                'Repair restore is allowed only from an authenticated web POST.';

            return $aResult;
        }

        if (!isAdmin()) {
            $aResult['error'] =
                'Administrator authorization is required.';

            return $aResult;
        }

        if ($iJournalId <= 0) {
            $aResult['error'] =
                'A valid repair journal ID is required.';

            return $aResult;
        }

        $aJournal =
            $this->_oDb->getRow(
                "SELECT *
                 FROM `my_una_doctor_repairs`
                 WHERE `id` = ?
                 LIMIT 1",
                array($iJournalId)
            );

        if (empty($aJournal)) {
            $aResult['error'] =
                'Repair journal was not found.';

            return $aResult;
        }

        if ((string)$aJournal['status'] !== 'armed') {
            $aResult['error'] =
                'Only an armed repair journal can be restored.';

            return $aResult;
        }

        $aOriginalState = json_decode(
            (string)$aJournal['original_state_json'],
            true
        );

        $aArmedResult = json_decode(
            (string)$aJournal['result_json'],
            true
        );

        if (
            !is_array($aOriginalState)
            || !is_array($aArmedResult)
        ) {
            $aResult['error'] =
                'Repair journal state is invalid or unreadable.';

            return $aResult;
        }

        if (
            ($aArmedResult['phase'] ?? '')
            !== 'armed'
        ) {
            $aResult['error'] =
                'Repair journal does not contain a verified armed result.';

            return $aResult;
        }

        $aGenerator =
            $aOriginalState['generator']
            ?? array();

        $iCronId =
            (int)($aGenerator['id'] ?? 0);

        if ($iCronId <= 0) {
            $aResult['error'] =
                'Journaled generator identity is incomplete.';

            return $aResult;
        }

        $sExpectedArmedMaintenance =
            (string)(
                $aArmedResult['maintenance_value']
                ?? ''
            );

        $sExpectedPausedSchedule =
            (string)(
                $aArmedResult['generator_schedule']
                ?? ''
            );

        $bHasCompressionState =
            array_key_exists(
                'template_cache_compress_value',
                $aOriginalState
            );

        $sExpectedArmedCompression = '';

        if ($bHasCompressionState) {
            if (
                !array_key_exists(
                    'compression_value',
                    $aArmedResult
                )
            ) {
                $aResult['error'] =
                    'Journaled Barrier 2 compression state is missing.';

                return $aResult;
            }

            $sExpectedArmedCompression =
                (string)$aArmedResult[
                    'compression_value'
                ];
        }

        if (
            $sExpectedArmedMaintenance === ''
            || $sExpectedPausedSchedule === ''
            || (int)(
                $aArmedResult['generator_id']
                ?? 0
            ) !== $iCronId
        ) {
            $aResult['error'] =
                'Journaled armed-state verification data is incomplete.';

            return $aResult;
        }

        /*
         * Verify the live barrier still exactly matches the
         * state produced by the armed repair.
         */
        $sLiveMaintenance =
            (string)$this->_oDb->getOne(
                "SELECT `value`
                 FROM `sys_options`
                 WHERE `name` = ?
                 LIMIT 1",
                array('sys_maintenance_mode')
            );

        if (
            $sLiveMaintenance
            !== $sExpectedArmedMaintenance
        ) {
            $aResult['error'] =
                'Soft-maintenance state changed after the repair was armed. Restore refused.';

            return $aResult;
        }

        if ($bHasCompressionState) {
            $sLiveCompression =
                (string)$this->_oDb->getOne(
                    "SELECT `value`
                     FROM `sys_options`
                     WHERE `name` = ?
                     LIMIT 1",
                    array(
                        'sys_template_cache_compress_enable'
                    )
                );

            if (
                $sLiveCompression
                !== $sExpectedArmedCompression
            ) {
                $aResult['error'] =
                    'Template-cache compression state changed after the repair was armed. Restore refused.';

                return $aResult;
            }
        }

        $aLiveCron =
            $this->_oDb->getRow(
                "SELECT *
                 FROM `sys_cron_jobs`
                 WHERE `id` = ?
                 LIMIT 1",
                array($iCronId)
            );

        $bLiveCronExact =
            !empty($aLiveCron)
            && (string)$aLiveCron['name']
                === (string)($aGenerator['name'] ?? '')
            && (string)$aLiveCron['class']
                === (string)($aGenerator['class'] ?? '')
            && (string)$aLiveCron['file']
                === (string)($aGenerator['file'] ?? '')
            && (string)($aLiveCron['service_call'] ?? '')
                === (string)($aGenerator['service_call'] ?? '')
            && (string)$aLiveCron['time']
                === $sExpectedPausedSchedule;

        if (!$bLiveCronExact) {
            $aResult['error'] =
                'Generator state changed after the repair was armed. Restore refused.';

            return $aResult;
        }

        $encode = static function ($value) {
            $json = json_encode(
                $value,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

            return $json === false
                ? '{}'
                : $json;
        };

        try {
            /*
             * Record intent before changing either controlled
             * live state.
             */
            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' => 'restoring',
                    'result_json' => $encode(
                        array(
                            'phase' => 'restoring',
                            'changes_applied' => false,
                        )
                    ),
                )
            );

            $aRestore =
                $this->rollbackRepairArmState(
                    $aOriginalState,
                    true,
                    true,
                    true
                );

            if (empty($aRestore['ok'])) {
                throw new RuntimeException(
                    'Original repair state could not be fully restored.'
                );
            }

            /*
             * Independent final verification.
             */
            $sExpectedOriginalMaintenance =
                (string)(
                    $aOriginalState[
                        'soft_maintenance_value'
                    ]
                    ?? ''
                );

            $sVerifyMaintenance =
                (string)$this->_oDb->getOne(
                    "SELECT `value`
                     FROM `sys_options`
                     WHERE `name` = ?
                     LIMIT 1",
                    array('sys_maintenance_mode')
                );

            $sExpectedOriginalMaintenanceHtml =
                array_key_exists(
                    'soft_maintenance_html_value',
                    $aOriginalState
                )
                    ? (string)$aOriginalState[
                        'soft_maintenance_html_value'
                    ]
                    : null;

            $sVerifyMaintenanceHtml =
                $sExpectedOriginalMaintenanceHtml !== null
                    ? (string)$this->_oDb->getOne(
                        "SELECT `value`
                         FROM `sys_options`
                         WHERE `name` = ?
                         LIMIT 1",
                        array(
                            'sys_maintenance_mode_html'
                        )
                    )
                    : null;

            $sExpectedOriginalCompression = '';

            $sVerifyCompression = '';

            if ($bHasCompressionState) {
                $sExpectedOriginalCompression =
                    (string)$aOriginalState[
                        'template_cache_compress_value'
                    ];

                $sVerifyCompression =
                    (string)$this->_oDb->getOne(
                        "SELECT `value`
                         FROM `sys_options`
                         WHERE `name` = ?
                         LIMIT 1",
                        array(
                            'sys_template_cache_compress_enable'
                        )
                    );
            }

            $aVerifyCron =
                $this->_oDb->getRow(
                    "SELECT *
                     FROM `sys_cron_jobs`
                     WHERE `id` = ?
                     LIMIT 1",
                    array($iCronId)
                );

            $bFinalVerified =
                $sVerifyMaintenance
                    === $sExpectedOriginalMaintenance
                && (
                    $sExpectedOriginalMaintenanceHtml === null
                    || $sVerifyMaintenanceHtml
                        === $sExpectedOriginalMaintenanceHtml
                )
                && (
                    !$bHasCompressionState
                    || $sVerifyCompression
                        === $sExpectedOriginalCompression
                )
                && !empty($aVerifyCron)
                && (string)$aVerifyCron['name']
                    === (string)($aGenerator['name'] ?? '')
                && (string)$aVerifyCron['class']
                    === (string)($aGenerator['class'] ?? '')
                && (string)$aVerifyCron['file']
                    === (string)($aGenerator['file'] ?? '')
                && (string)($aVerifyCron['service_call'] ?? '')
                    === (string)($aGenerator['service_call'] ?? '')
                && (string)$aVerifyCron['time']
                    === (string)($aGenerator['time'] ?? '');

            if (!$bFinalVerified) {
                throw new RuntimeException(
                    'Final restored-state verification failed.'
                );
            }

            $aRestoredResult = array(
                'phase' => 'restored',
                'changes_applied' => true,
                'maintenance_value' =>
                    $sVerifyMaintenance,
                'maintenance_html_sha256' =>
                    $sVerifyMaintenanceHtml === null
                        ? null
                        : hash(
                            'sha256',
                            $sVerifyMaintenanceHtml
                        ),
                'compression_value' =>
                    $bHasCompressionState
                        ? $sVerifyCompression
                        : null,
                'generator_id' =>
                    $iCronId,
                'generator_schedule' =>
                    (string)$aVerifyCron['time'],
                'runtime_moved' => false,
            );

            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' => 'restored',
                    'result_json' =>
                        $encode($aRestoredResult),
                    'error_text' => '',
                )
            );

            $aResult['ok'] = true;
            $aResult['restored'] = true;
            $aResult['changes_applied'] = true;
            $aResult['state'] =
                $aRestoredResult;

            return $aResult;
        } catch (Throwable $e) {
            /*
             * A failed deliberate restore is unresolved and must
             * block another automatic arming attempt.
             */
            $this->_oDb->updateRepairJournal(
                $iJournalId,
                array(
                    'status' =>
                        'rollback_failed',
                    'result_json' =>
                        $encode(
                            array(
                                'phase' =>
                                    'rollback_failed',
                                'changes_applied' =>
                                    true,
                            )
                        ),
                    'error_text' =>
                        $e->getMessage(),
                )
            );

            $aResult['error'] =
                $e->getMessage();

            return $aResult;
        }
    }


    public function armApprovedRepair(
        $sRule,
        $sModule,
        $sApprovedPreflightId,
        $sApprovedArmId
    ) {
        $aResult = array(
            'ok' => false,
            'armed' => false,
            'changes_applied' => false,
            'journal_id' => 0,
        );

        /*
         * Mutating repair actions are never allowed from CLI
         * and must originate from an authenticated web POST.
         */
        if (
            PHP_SAPI === 'cli'
            || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        ) {
            $aResult['error'] =
                'Repair arming is allowed only from an authenticated web POST.';

            return $aResult;
        }

        if (!isAdmin()) {
            $aResult['error'] =
                'Administrator authorization is required.';

            return $aResult;
        }

        /*
         * Re-run the complete read-only approval chain against
         * fresh live state before creating any repair journal.
         */
        $aApproval = $this->validateRepairArmApproval(
            $sRule,
            $sModule,
            $sApprovedPreflightId,
            $sApprovedArmId
        );

        if (
            empty($aApproval['ok'])
            || empty($aApproval['approved'])
        ) {
            $aResult['error'] =
                $aApproval['error']
                ?? 'Fresh repair approval validation failed.';

            return $aResult;
        }

        $aPlan =
            $aApproval['plan']
            ?? array();

        $aOriginalState =
            $aPlan['original_state']
            ?? array();

        $aGenerator =
            $aOriginalState['generator']
            ?? array();

        $aPause =
            $aPlan['planned_pause']
            ?? array();

        if (
            empty($aGenerator['id'])
            || empty($aPause['pause_schedule'])
        ) {
            $aResult['error'] =
                'The approved Arm Plan is incomplete.';

            return $aResult;
        }

        if (
            file_exists(
                BX_DIRECTORY_PATH_ROOT
                . BX_MAINTENANCE_FILE
            )
        ) {
            $aResult['error'] =
                'Hard UNA maintenance mode became active. Repair arming was refused.';

            return $aResult;
        }

        $iCronId = (int)$aGenerator['id'];
        $sOriginalSchedule =
            (string)$aGenerator['time'];
        $sPauseSchedule =
            (string)$aPause['pause_schedule'];
        $sOriginalMaintenance =
            (string)(
                $aOriginalState[
                    'soft_maintenance_value'
                ]
                ?? ''
            );

        if (
            !array_key_exists(
                'soft_maintenance_html_value',
                $aOriginalState
            )
        ) {
            $aResult['error'] =
                'The approved Arm Plan does not contain the original soft-maintenance HTML state.';

            return $aResult;
        }

        $sOriginalMaintenanceHtml =
            (string)$aOriginalState[
                'soft_maintenance_html_value'
            ];

        if (
            !array_key_exists(
                'template_cache_compress_value',
                $aOriginalState
            )
        ) {
            $aResult['error'] =
                'The approved Arm Plan does not contain the original template-cache compression state.';

            return $aResult;
        }

        $sOriginalCompression =
            (string)$aOriginalState[
                'template_cache_compress_value'
            ];

        /*
         * Re-read all mutable control states immediately before
         * the journal is created.
         */
        $sLiveMaintenance =
            (string)$this->_oDb->getOne(
                "SELECT `value`
                 FROM `sys_options`
                 WHERE `name` = ?
                 LIMIT 1",
                array('sys_maintenance_mode')
            );

        if ($sLiveMaintenance !== $sOriginalMaintenance) {
            $aResult['error'] =
                'Soft-maintenance state changed after approval.';

            return $aResult;
        }

        $sLiveMaintenanceHtml =
            (string)$this->_oDb->getOne(
                "SELECT `value`
                 FROM `sys_options`
                 WHERE `name` = ?
                 LIMIT 1",
                array(
                    'sys_maintenance_mode_html'
                )
            );

        if (
            $sLiveMaintenanceHtml
            !== $sOriginalMaintenanceHtml
        ) {
            $aResult['error'] =
                'Soft-maintenance HTML changed after approval.';

            return $aResult;
        }

        $sLiveCompression =
            (string)$this->_oDb->getOne(
                "SELECT `value`
                 FROM `sys_options`
                 WHERE `name` = ?
                 LIMIT 1",
                array(
                    'sys_template_cache_compress_enable'
                )
            );

        if (
            $sLiveCompression
            !== $sOriginalCompression
        ) {
            $aResult['error'] =
                'Template-cache compression state changed after approval.';

            return $aResult;
        }

        $aLiveCron =
            $this->_oDb->getRow(
                "SELECT *
                 FROM `sys_cron_jobs`
                 WHERE `id` = ?
                 LIMIT 1",
                array($iCronId)
            );

        $bCronIdentityMatches =
            !empty($aLiveCron)
            && (string)$aLiveCron['name']
                === (string)($aGenerator['name'] ?? '')
            && (string)$aLiveCron['class']
                === (string)($aGenerator['class'] ?? '')
            && (string)$aLiveCron['file']
                === (string)($aGenerator['file'] ?? '')
            && (string)($aLiveCron['service_call'] ?? '')
                === (string)($aGenerator['service_call'] ?? '')
            && (string)$aLiveCron['time']
                === $sOriginalSchedule;

        if (!$bCronIdentityMatches) {
            $aResult['error'] =
                'Generator state changed after approval.';

            return $aResult;
        }

        /*
         * Preserve the latest observed ts/timing values in the
         * durable journal. Rollback itself restores only state
         * that Doctor changes: schedule and maintenance.
         */
        $aOriginalState['generator']['ts'] =
            (int)($aLiveCron['ts'] ?? 0);
        $aOriginalState['generator']['timing'] =
            (float)($aLiveCron['timing'] ?? 0);

        /*
         * Refuse to start another arming workflow while an
         * earlier repair for this rule/module remains active
         * or has an unresolved rollback failure.
         */
        $iActive = (int)$this->_oDb->getOne(
            "SELECT COUNT(*)
             FROM `my_una_doctor_repairs`
             WHERE `rule` = ?
               AND `module` = ?
               AND `status` IN (
                   'planned',
                   'arming',
                   'maintenance_enabled',
                   'generator_paused',
                   'armed',
                   'runtime_protecting',
                   'runtime_protected',
                   'runtime_restoring',
                   'runtime_rollback_failed',
                   'restoring',
                   'rollback_failed'
               )",
            array(
                (string)$sRule,
                (string)$sModule
            )
        );

        if ($iActive > 0) {
            $aResult['error'] =
                'An active or unresolved repair journal already exists for this module.';

            return $aResult;
        }

        $iMaintenanceEstimateSeconds =
            2 * 60 * 60;

        $iMaintenanceStartedAt = time();
        $iMaintenanceEtaAt =
            $iMaintenanceStartedAt
            + $iMaintenanceEstimateSeconds;

        $bDoctorMaintenanceLanding =
            $sOriginalMaintenance === '';

        $sExpectedMaintenanceHtml =
            $sOriginalMaintenanceHtml;

        if ($bDoctorMaintenanceLanding) {
            require_once(
                BX_DIRECTORY_PATH_MODULES
                . $this->_aModule['path']
                . 'classes/MyUnaDoctorMaintenance.php'
            );

            $sExpectedMaintenanceHtml =
                MyUnaDoctorMaintenance::buildPage(
                    $iMaintenanceStartedAt,
                    $iMaintenanceEtaAt
                );

            if (
                $sExpectedMaintenanceHtml === ''
                || strpos(
                    $sExpectedMaintenanceHtml,
                    'id="mud-maintenance"'
                ) === false
            ) {
                $aResult['error'] =
                    'Doctor maintenance landing page could not be generated safely.';

                return $aResult;
            }
        }

        $sExpectedMaintenanceHtmlSha256 =
            hash(
                'sha256',
                $sExpectedMaintenanceHtml
            );

        $aPlannedState = array(
            'template_cache_compress_target' =>
                '',

            'soft_maintenance_target' =>
                $sOriginalMaintenance !== ''
                    ? $sOriginalMaintenance
                    : 'on',

            'maintenance_landing' => array(
                'doctor_owned' =>
                    $bDoctorMaintenanceLanding,
                'started_at' =>
                    $iMaintenanceStartedAt,
                'eta_at' =>
                    $iMaintenanceEtaAt,
                'estimate_seconds' =>
                    $iMaintenanceEstimateSeconds,
                'html_sha256' =>
                    $sExpectedMaintenanceHtmlSha256,
            ),

            'generator' => array(
                'id' => $iCronId,
                'name' =>
                    (string)($aGenerator['name'] ?? ''),
                'class' =>
                    (string)($aGenerator['class'] ?? ''),
                'file' =>
                    (string)($aGenerator['file'] ?? ''),
                'service_call' =>
                    (string)($aGenerator['service_call'] ?? ''),
                'original_schedule' =>
                    $sOriginalSchedule,
                'pause_schedule' =>
                    $sPauseSchedule,
            ),

            'cron_cache_to_clear' =>
                'sys_cron_jobs',

            'runtime' =>
                $aPlan['runtime']
                ?? array(),

            'safety_destination' =>
                $aPlan['safety_destination']
                ?? array(),
        );

        try {
            $sNonce =
                bin2hex(random_bytes(8));
        } catch (Throwable $e) {
            $sNonce =
                str_replace(
                    '.',
                    '',
                    uniqid('', true)
                );
        }

        $sRepairKey = substr(
            hash(
                'sha256',
                (string)$sRule
                . '|'
                . (string)$sModule
                . '|'
                . (string)$sApprovedArmId
                . '|'
                . $sNonce
            ),
            0,
            48
        );

        /*
         * The rollback journal MUST exist before the first
         * mutation is attempted.
         */
        $iJournalId =
            $this->_oDb->addRepairJournal(
                array(
                    'repair_key' =>
                        $sRepairKey,
                    'rule' =>
                        (string)$sRule,
                    'module' =>
                        (string)$sModule,
                    'status' =>
                        'planned',
                    'preflight_id' =>
                        (string)$sApprovedPreflightId,
                    'arm_id' =>
                        (string)$sApprovedArmId,
                    'original_state' =>
                        $aOriginalState,
                    'planned_state' =>
                        $aPlannedState,
                    'result' => array(
                        'phase' =>
                            'journal_created',
                        'changes_applied' =>
                            false,
                    ),
                )
            );

        if ($iJournalId <= 0) {
            $aResult['error'] =
                'Durable repair journal could not be created. No changes were made.';

            return $aResult;
        }

        $aResult['journal_id'] =
            $iJournalId;

        $encode = static function ($value) {
            $json = json_encode(
                $value,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

            return $json === false
                ? '{}'
                : $json;
        };

        $bCompressionMutationAttempted = false;
        $bMaintenanceHtmlMutationAttempted = false;
        $bMaintenanceMutationAttempted = false;
        $bCronMutationAttempted = false;

        try {
            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' => 'arming',
                    'result_json' => $encode(
                        array(
                            'phase' => 'arming',
                            'changes_applied' => false,
                        )
                    ),
                )
            );

            /*
             * Repair Barrier 2:
             *
             * Disable template-cache compression BEFORE enabling
             * maintenance. This forces Studio CSS/JS to use direct
             * cache_public URLs instead of gzip_loader.php, which
             * UNA maintenance mode otherwise blocks with HTTP 503.
             */
            if ($sOriginalCompression !== '') {
                $bCompressionMutationAttempted = true;

                setParam(
                    'sys_template_cache_compress_enable',
                    ''
                );
            }

            $sVerifyCompression =
                (string)$this->_oDb->getOne(
                    "SELECT `value`
                     FROM `sys_options`
                     WHERE `name` = ?
                     LIMIT 1",
                    array(
                        'sys_template_cache_compress_enable'
                    )
                );

            if ($sVerifyCompression !== '') {
                throw new RuntimeException(
                    'Template-cache compression could not be disabled before maintenance.'
                );
            }

            /*
             * Persist the Barrier 2 checkpoint before maintenance
             * is changed. Status remains arming so existing active
             * journal semantics stay backward-compatible.
             */
            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' => 'arming',
                    'result_json' => $encode(
                        array(
                            'phase' =>
                                'compression_disabled',
                            'compression_value' =>
                                $sVerifyCompression,
                            'changes_applied' =>
                                $bCompressionMutationAttempted,
                        )
                    ),
                )
            );

            /*
             * Stage Doctor's self-contained public landing page
             * only when Doctor itself is about to activate soft
             * maintenance. Existing administrator maintenance is
             * treated as externally owned and is not replaced.
             */
            if ($bDoctorMaintenanceLanding) {
                $bMaintenanceHtmlMutationAttempted =
                    true;

                setParam(
                    'sys_maintenance_mode_html',
                    $sExpectedMaintenanceHtml
                );
            }

            $sVerifyMaintenanceHtml =
                (string)$this->_oDb->getOne(
                    "SELECT `value`
                     FROM `sys_options`
                     WHERE `name` = ?
                     LIMIT 1",
                    array(
                        'sys_maintenance_mode_html'
                    )
                );

            if (
                $sVerifyMaintenanceHtml
                !== $sExpectedMaintenanceHtml
            ) {
                throw new RuntimeException(
                    'Doctor maintenance landing page could not be staged and verified.'
                );
            }

            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' => 'arming',
                    'result_json' => $encode(
                        array(
                            'phase' =>
                                'maintenance_page_staged',
                            'maintenance_html_sha256' =>
                                hash(
                                    'sha256',
                                    $sVerifyMaintenanceHtml
                                ),
                            'maintenance_started_at' =>
                                $iMaintenanceStartedAt,
                            'maintenance_eta_at' =>
                                $iMaintenanceEtaAt,
                            'changes_applied' =>
                                $bMaintenanceHtmlMutationAttempted,
                        )
                    ),
                )
            );

            /*
             * Enable soft maintenance only if it was originally
             * disabled. Preserve any existing non-empty value.
             */
            if ($sOriginalMaintenance === '') {
                $bMaintenanceMutationAttempted = true;

                setParam(
                    'sys_maintenance_mode',
                    'on'
                );
            }

            $sVerifyMaintenance =
                (string)$this->_oDb->getOne(
                    "SELECT `value`
                     FROM `sys_options`
                     WHERE `name` = ?
                     LIMIT 1",
                    array('sys_maintenance_mode')
                );

            $sExpectedMaintenance =
                $sOriginalMaintenance !== ''
                    ? $sOriginalMaintenance
                    : 'on';

            if (
                $sVerifyMaintenance
                !== $sExpectedMaintenance
            ) {
                throw new RuntimeException(
                    'Soft-maintenance activation could not be verified.'
                );
            }

            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' =>
                        'maintenance_enabled',
                    'result_json' => $encode(
                        array(
                            'phase' =>
                                'maintenance_enabled',
                            'maintenance_value' =>
                                $sVerifyMaintenance,
                            'maintenance_html_sha256' =>
                                hash(
                                    'sha256',
                                    $sVerifyMaintenanceHtml
                                ),
                            'maintenance_started_at' =>
                                $iMaintenanceStartedAt,
                            'maintenance_eta_at' =>
                                $iMaintenanceEtaAt,
                            'changes_applied' =>
                                $bMaintenanceMutationAttempted
                                || $bMaintenanceHtmlMutationAttempted,
                        )
                    ),
                )
            );

            /*
             * Re-read the target once more after maintenance
             * activation and before changing its schedule.
             */
            $aBeforePause =
                $this->_oDb->getRow(
                    "SELECT *
                     FROM `sys_cron_jobs`
                     WHERE `id` = ?
                     LIMIT 1",
                    array($iCronId)
                );

            $bStillExact =
                !empty($aBeforePause)
                && (string)$aBeforePause['name']
                    === (string)($aGenerator['name'] ?? '')
                && (string)$aBeforePause['class']
                    === (string)($aGenerator['class'] ?? '')
                && (string)$aBeforePause['file']
                    === (string)($aGenerator['file'] ?? '')
                && (string)($aBeforePause['service_call'] ?? '')
                    === (string)($aGenerator['service_call'] ?? '')
                && (string)$aBeforePause['time']
                    === $sOriginalSchedule;

            if (!$bStillExact) {
                throw new RuntimeException(
                    'Generator state changed before pause.'
                );
            }

            $oCronQuery =
                BxDolCronQuery::getInstance();

            $bCronMutationAttempted = true;

            $bPaused =
                $oCronQuery->updateJob(
                    $iCronId,
                    array(
                        'time' =>
                            $sPauseSchedule,
                    )
                );

            $oCronQuery->cleanCache(
                'sys_cron_jobs'
            );

            $aPaused =
                $this->_oDb->getRow(
                    "SELECT *
                     FROM `sys_cron_jobs`
                     WHERE `id` = ?
                     LIMIT 1",
                    array($iCronId)
                );

            $bPauseVerified =
                $bPaused !== false
                && !empty($aPaused)
                && (string)$aPaused['name']
                    === (string)($aGenerator['name'] ?? '')
                && (string)$aPaused['class']
                    === (string)($aGenerator['class'] ?? '')
                && (string)$aPaused['file']
                    === (string)($aGenerator['file'] ?? '')
                && (string)($aPaused['service_call'] ?? '')
                    === (string)($aGenerator['service_call'] ?? '')
                && (string)$aPaused['time']
                    === $sPauseSchedule;

            if (!$bPauseVerified) {
                throw new RuntimeException(
                    'Paused generator state could not be verified.'
                );
            }

            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' =>
                        'generator_paused',
                    'result_json' => $encode(
                        array(
                            'phase' =>
                                'generator_paused',
                            'generator_id' =>
                                $iCronId,
                            'pause_schedule' =>
                                $sPauseSchedule,
                            'changes_applied' =>
                                true,
                        )
                    ),
                )
            );

            /*
             * Final arming verification. Runtime content is NOT
             * moved by this method.
             */
            $sFinalMaintenance =
                (string)$this->_oDb->getOne(
                    "SELECT `value`
                     FROM `sys_options`
                     WHERE `name` = ?
                     LIMIT 1",
                    array('sys_maintenance_mode')
                );

            $sFinalMaintenanceHtml =
                (string)$this->_oDb->getOne(
                    "SELECT `value`
                     FROM `sys_options`
                     WHERE `name` = ?
                     LIMIT 1",
                    array(
                        'sys_maintenance_mode_html'
                    )
                );

            $sFinalCompression =
                (string)$this->_oDb->getOne(
                    "SELECT `value`
                     FROM `sys_options`
                     WHERE `name` = ?
                     LIMIT 1",
                    array(
                        'sys_template_cache_compress_enable'
                    )
                );

            $aFinalCron =
                $this->_oDb->getRow(
                    "SELECT *
                     FROM `sys_cron_jobs`
                     WHERE `id` = ?
                     LIMIT 1",
                    array($iCronId)
                );

            if (
                $sFinalCompression !== ''
                || $sFinalMaintenance
                    !== $sExpectedMaintenance
                || $sFinalMaintenanceHtml
                    !== $sExpectedMaintenanceHtml
                || empty($aFinalCron)
                || (string)$aFinalCron['time']
                    !== $sPauseSchedule
                || (string)$aFinalCron['name']
                    !== (string)($aGenerator['name'] ?? '')
                || (string)$aFinalCron['class']
                    !== (string)($aGenerator['class'] ?? '')
                || (string)$aFinalCron['file']
                    !== (string)($aGenerator['file'] ?? '')
                || (string)($aFinalCron['service_call'] ?? '')
                    !== (string)($aGenerator['service_call'] ?? '')
            ) {
                throw new RuntimeException(
                    'Final armed-state verification failed.'
                );
            }

            $aSuccess = array(
                'phase' => 'armed',
                'changes_applied' => true,
                'maintenance_value' =>
                    $sFinalMaintenance,
                'maintenance_html_sha256' =>
                    hash(
                        'sha256',
                        $sFinalMaintenanceHtml
                    ),
                'maintenance_started_at' =>
                    $iMaintenanceStartedAt,
                'maintenance_eta_at' =>
                    $iMaintenanceEtaAt,
                'compression_value' =>
                    $sFinalCompression,
                'generator_id' =>
                    $iCronId,
                'generator_schedule' =>
                    $sPauseSchedule,
                'runtime_moved' =>
                    false,
            );

            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' => 'armed',
                    'result_json' =>
                        $encode($aSuccess),
                    'error_text' => '',
                )
            );

            $aResult['ok'] = true;
            $aResult['armed'] = true;
            $aResult['changes_applied'] = true;
            $aResult['state'] = $aSuccess;

            return $aResult;
        } catch (Throwable $e) {
            /*
             * Any failure after journal creation immediately
             * restores the exact original states we control.
             */
            $aRollback =
                $this->rollbackRepairArmState(
                    $aOriginalState,
                    $bCronMutationAttempted,
                    true
                );

            $sStatus =
                !empty($aRollback['ok'])
                    ? 'rolled_back'
                    : 'rollback_failed';

            $this->_oDb->updateRepairJournal(
                $iJournalId,
                array(
                    'status' =>
                        $sStatus,
                    'result_json' =>
                        $encode(
                            array(
                                'phase' =>
                                    $sStatus,
                                'changes_applied' =>
                                    false,
                                'rollback' =>
                                    $aRollback,
                            )
                        ),
                    'error_text' =>
                        $e->getMessage(),
                )
            );

            $aResult['error'] =
                $e->getMessage();

            $aResult['rollback'] =
                $aRollback;

            return $aResult;
        }
    }


    public function validateRepairArmApproval(
        $sRule,
        $sModule,
        $sApprovedPreflightId,
        $sApprovedArmId
    ) {
        $aPlan = $this->getRepairArmPlan(
            $sRule,
            $sModule
        );

        $aResult = array(
            'ok' => false,
            'approved' => false,
            'changes_applied' => false,
            'plan' => $aPlan,
        );

        if (
            empty($aPlan['ok'])
            || empty($aPlan['ready'])
        ) {
            $aResult['error'] =
                'Fresh Arm Plan is not ready.';

            return $aResult;
        }

        if (
            trim((string)$sApprovedPreflightId) === ''
            || trim((string)$sApprovedArmId) === ''
        ) {
            $aResult['error'] =
                'Approval identifiers are required.';

            return $aResult;
        }

        if (
            !hash_equals(
                (string)$aPlan['preflight_id'],
                (string)$sApprovedPreflightId
            )
        ) {
            $aResult['error'] =
                'Preflight state changed after approval.';

            return $aResult;
        }

        if (
            !hash_equals(
                (string)$aPlan['arm_id'],
                (string)$sApprovedArmId
            )
        ) {
            $aResult['error'] =
                'Arm Plan state changed after approval.';

            return $aResult;
        }

        $aResult['ok'] = true;
        $aResult['approved'] = true;

        return $aResult;
    }


    public function getLatestHealthScan()
    {
        return $this->_oDb->getLatestScan();
    }

    public function getHealthScanHistory($iLimit = 20)
    {
        return $this->_oDb->getScans($iLimit);
    }
}
