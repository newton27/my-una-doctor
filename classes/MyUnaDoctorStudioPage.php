<?php defined('BX_DOL') or die('hack attempt');

class MyUnaDoctorStudioPage extends BxTemplStudioModule
{
    protected $oDoctor;

    public function __construct($sModule, $mixedPageName, $sPage = '')
    {
        parent::__construct($sModule, $mixedPageName, $sPage);

        $this->sPageDefault = 'overview';
        if ($sPage === '')
            $this->sPage = 'overview';

        $this->aMenuItems = array(
            'overview' => array('name' => 'overview', 'icon' => 'heartbeat', 'title' => '_my_una_doctor_overview'),
            'system' => array('name' => 'system', 'icon' => 'server', 'title' => '_my_una_doctor_system'),
            'module_health' => array('name' => 'module_health', 'icon' => 'puzzle-piece', 'title' => '_my_una_doctor_modules'),
            'file_integrity' => array('name' => 'file_integrity', 'icon' => 'shield-alt', 'title' => 'File Integrity'),
            'repair_candidates' => array('name' => 'repair_candidates', 'icon' => 'tools', 'title' => 'Repair Candidates'),
            'database' => array('name' => 'database', 'icon' => 'database', 'title' => '_my_una_doctor_database'),
            'media_health' => array('name' => 'media_health', 'icon' => 'images', 'title' => 'Storage & Media'),
            'logs' => array('name' => 'logs', 'icon' => 'file-alt', 'title' => '_my_una_doctor_logs'),
            'history' => array('name' => 'history', 'icon' => 'history', 'title' => '_my_una_doctor_history'),
            'settings' => array('name' => 'settings', 'icon' => 'cogs', 'title' => '_my_una_doctor_settings'),
        );
    }

    public function init()
    {
        parent::init();
        $this->oDoctor = BxDolModule::getInstance('my_una_doctor');
    }

    public function getOverview()
    {
        $aScan = array();
        if (bx_get('run') !== false && (string)bx_get('run') === '1')
            $aScan = $this->oDoctor->runHealthScan();
        if (empty($aScan))
            $aScan = $this->oDoctor->getLatestHealthScan();

        $sRunUrl = BX_DOL_URL_STUDIO . 'module.php?name=my_una_doctor&page=overview&run=1';

        $s = $this->css();
        $s .= '<div class="mud-wrap">';
        $s .= '<div class="mud-hero"><div><h1>My UNA Doctor</h1><p>Diagnose first. Repair only when the evidence is clear.</p></div>';
        $s .= '<a class="bx-btn mud-run" href="' . $this->esc($sRunUrl) . '"><i class="sys-icon heartbeat"></i> Run Full Health Scan</a></div>';

        if (empty($aScan)) {
            $s .= '<div class="mud-empty"><h2>No health scan has been recorded yet.</h2><p>Run the first scan to establish this UNA site’s baseline.</p></div></div>';
            return $s;
        }

        $aSummary = $aScan['summary'];
        $s .= $this->summaryCards($aSummary, $aScan);
        $s .= '<div class="mud-section"><h2>Findings</h2>' . $this->renderItems($aScan['items']) . '</div>';
        $s .= '</div>';
        return $s;
    }

    public function getSystem()
    {
        return $this->renderCategoryPage('System & Filesystem', array('system', 'filesystem', 'cron'));
    }

    public function getModuleHealth()
    {
        return $this->renderCategoryPage('Modules', array('modules', 'notifications'));
    }


    public function getFileIntegrity()
    {
        return $this->renderCategoryPage(
            'File Integrity & Update Readiness',
            array('file_integrity')
        );
    }



    public function getRepairCandidates()
    {
        $aApprovalValidation = null;
        $aArmExecution = null;
        $aRestoreExecution = null;
        $aRuntimeProtectionExecution = null;
        $aRuntimeRestoreExecution = null;

        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && ($_POST['mud_action'] ?? '')
                === 'validate_arm_approval'
        ) {
            if (!isAdmin()) {
                $aApprovalValidation = array(
                    'ok' => false,
                    'approved' => false,
                    'changes_applied' => false,
                    'error' =>
                        'Administrator authorization is required.',
                );
            } else {
                $sCsrfToken =
                    (string)($_POST['csrf_token'] ?? '');

                if (
                    !BxDolForm::isCsrfTokenValid(
                        $sCsrfToken
                    )
                ) {
                    $aApprovalValidation = array(
                        'ok' => false,
                        'approved' => false,
                        'changes_applied' => false,
                        'error' =>
                            'CSRF validation failed or the approval token expired.',
                    );
                } else {
                    $aApprovalValidation =
                        $this->oDoctor
                            ->validateRepairArmApproval(
                                (string)(
                                    $_POST['repair_rule']
                                    ?? ''
                                ),
                                (string)(
                                    $_POST['repair_module']
                                    ?? ''
                                ),
                                (string)(
                                    $_POST['preflight_id']
                                    ?? ''
                                ),
                                (string)(
                                    $_POST['arm_id']
                                    ?? ''
                                )
                            );
                }
            }
        }

        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && ($_POST['mud_action'] ?? '')
                === 'arm_approved_repair'
        ) {
            if (!isAdmin()) {
                $aArmExecution = array(
                    'ok' => false,
                    'armed' => false,
                    'changes_applied' => false,
                    'error' =>
                        'Administrator authorization is required.',
                );
            } else {
                $sCsrfToken =
                    (string)($_POST['csrf_token'] ?? '');

                if (
                    !BxDolForm::isCsrfTokenValid(
                        $sCsrfToken
                    )
                ) {
                    $aArmExecution = array(
                        'ok' => false,
                        'armed' => false,
                        'changes_applied' => false,
                        'error' =>
                            'CSRF validation failed or the approval token expired.',
                    );
                } elseif (
                    ($_POST['confirm_arm'] ?? '')
                    !== 'yes'
                ) {
                    $aArmExecution = array(
                        'ok' => false,
                        'armed' => false,
                        'changes_applied' => false,
                        'error' =>
                            'Explicit Arm Repair confirmation is required.',
                    );
                } else {
                    $aArmExecution =
                        $this->oDoctor
                            ->armApprovedRepair(
                                (string)(
                                    $_POST['repair_rule']
                                    ?? ''
                                ),
                                (string)(
                                    $_POST['repair_module']
                                    ?? ''
                                ),
                                (string)(
                                    $_POST['preflight_id']
                                    ?? ''
                                ),
                                (string)(
                                    $_POST['arm_id']
                                    ?? ''
                                )
                            );
                }
            }
        }

        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && ($_POST['mud_action'] ?? '')
                === 'protect_runtime'
        ) {
            if (!isAdmin()) {
                $aRuntimeProtectionExecution = array(
                    'ok' => false,
                    'protected' => false,
                    'changes_applied' => false,
                    'error' =>
                        'Administrator authorization is required.',
                );
            } else {
                $sCsrfToken =
                    (string)($_POST['csrf_token'] ?? '');

                if (
                    !BxDolForm::isCsrfTokenValid(
                        $sCsrfToken
                    )
                ) {
                    $aRuntimeProtectionExecution = array(
                        'ok' => false,
                        'protected' => false,
                        'changes_applied' => false,
                        'error' =>
                            'CSRF validation failed or the runtime-protection token expired.',
                    );
                } elseif (
                    ($_POST['confirm_protect'] ?? '')
                    !== 'yes'
                ) {
                    $aRuntimeProtectionExecution = array(
                        'ok' => false,
                        'protected' => false,
                        'changes_applied' => false,
                        'error' =>
                            'Explicit runtime-protection confirmation is required.',
                    );
                } else {
                    $aRuntimeProtectionExecution =
                        $this->oDoctor
                            ->protectArmedRuntime(
                                (int)(
                                    $_POST['journal_id']
                                    ?? 0
                                ),
                                (string)(
                                    $_POST['protection_id']
                                    ?? ''
                                )
                            );
                }
            }
        }


        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && ($_POST['mud_action'] ?? '')
                === 'restore_protected_runtime'
        ) {
            if (!isAdmin()) {
                $aRuntimeRestoreExecution = array(
                    'ok' => false,
                    'restored' => false,
                    'changes_applied' => false,
                    'error' =>
                        'Administrator authorization is required.',
                );
            } else {
                $sCsrfToken =
                    (string)($_POST['csrf_token'] ?? '');

                if (
                    !BxDolForm::isCsrfTokenValid(
                        $sCsrfToken
                    )
                ) {
                    $aRuntimeRestoreExecution = array(
                        'ok' => false,
                        'restored' => false,
                        'changes_applied' => false,
                        'error' =>
                            'CSRF validation failed or the runtime-restore token expired.',
                    );
                } elseif (
                    ($_POST['confirm_runtime_restore'] ?? '')
                    !== 'yes'
                ) {
                    $aRuntimeRestoreExecution = array(
                        'ok' => false,
                        'restored' => false,
                        'changes_applied' => false,
                        'error' =>
                            'Explicit protected-runtime restore confirmation is required.',
                    );
                } else {
                    $aRuntimeRestoreExecution =
                        $this->oDoctor
                            ->restoreProtectedRuntime(
                                (int)(
                                    $_POST['journal_id']
                                    ?? 0
                                )
                            );
                }
            }
        }


        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && ($_POST['mud_action'] ?? '')
                === 'restore_armed_repair'
        ) {
            if (!isAdmin()) {
                $aRestoreExecution = array(
                    'ok' => false,
                    'restored' => false,
                    'changes_applied' => false,
                    'error' =>
                        'Administrator authorization is required.',
                );
            } else {
                $sCsrfToken =
                    (string)($_POST['csrf_token'] ?? '');

                if (
                    !BxDolForm::isCsrfTokenValid(
                        $sCsrfToken
                    )
                ) {
                    $aRestoreExecution = array(
                        'ok' => false,
                        'restored' => false,
                        'changes_applied' => false,
                        'error' =>
                            'CSRF validation failed or the restore token expired.',
                    );
                } elseif (
                    ($_POST['confirm_restore'] ?? '')
                    !== 'yes'
                ) {
                    $aRestoreExecution = array(
                        'ok' => false,
                        'restored' => false,
                        'changes_applied' => false,
                        'error' =>
                            'Explicit restore confirmation is required.',
                    );
                } else {
                    $aRestoreExecution =
                        $this->oDoctor
                            ->restoreArmedRepair(
                                (int)(
                                    $_POST['journal_id']
                                    ?? 0
                                )
                            );
                }
            }
        }

        $aScan = $this->oDoctor->getLatestHealthScan();

        /*
         * Generate one fresh CSRF token before rendering any
         * repair action forms. Active-journal forms appear before
         * the Arm Plan forms and must not receive an undefined
         * or empty token.
         */
        $sCsrfToken =
            BxDolForm::getCsrfToken();

        $s = $this->css();
        $s .= '<div class="mud-wrap">';
        $s .= '<div class="mud-section">';
        $s .= '<h1>Repair Candidates</h1>';

        $s .= '<div class="mud-note">'
            . '<strong>Guarded repair safety mode.</strong> '
            . 'My UNA Doctor can diagnose, preview, preflight and explicitly arm supported repair workflows. '
            . 'Arm Repair can disable template-cache compression, enable UNA soft maintenance and pause only a verified generator after approval validation. '
            . 'Runtime files are not moved, updates are not started and checksum baselines are not rewritten at this stage.'
            . '</div>';

        if (empty($aScan)) {
            $s .= '<div class="mud-empty">'
                . 'Run a health scan from Overview first.'
                . '</div></div></div>';

            return $s;
        }

        $aRepairItems = array();

        foreach ($aScan['items'] as $aItem) {
            if (
                isset($aItem['category']) &&
                $aItem['category'] === 'repairs'
            ) {
                $aRepairItems[] = $aItem;
            }
        }

        $s .= $this->renderItems($aRepairItems);

        $s .= '<div style="height:18px"></div>';
        $s .= '<h2>Previewable Repairs</h2>';

        $s .= $this->renderRepairCandidateCards(
            $aScan
        );


        $sPreflightRule = bx_get('preflight');

        if (
            $sPreflightRule !== false &&
            trim((string)$sPreflightRule) !== ''
        ) {
            $sPreflightModule = bx_get('module');

            $aPreflight =
                $this->oDoctor->getRepairPreflight(
                    (string)$sPreflightRule,
                    $sPreflightModule !== false
                        ? (string)$sPreflightModule
                        : ''
                );

            $s .= '<div style="height:18px"></div>';
            $s .= $this->renderRepairPreflight(
                $aPreflight
            );
        }

        $sArmPlanRule = bx_get('arm_plan');

        if (
            $sArmPlanRule !== false &&
            trim((string)$sArmPlanRule) !== ''
        ) {
            $sArmPlanModule = bx_get('module');

            $aArmPlan =
                $this->oDoctor->getRepairArmPlan(
                    (string)$sArmPlanRule,
                    $sArmPlanModule !== false
                        ? (string)$sArmPlanModule
                        : ''
                );

            $s .= '<div style="height:18px"></div>';
            $aActiveRepairJournal =
                $this->oDoctor->getActiveRepairJournal(
                    (string)$sArmPlanRule,
                    $sArmPlanModule !== false
                        ? (string)$sArmPlanModule
                        : ''
                );

            $s .= $this->renderRepairArmPlan(
                $aArmPlan,
                $aApprovalValidation,
                $aArmExecution,
                $aActiveRepairJournal,
                $aRestoreExecution,
                $aRuntimeProtectionExecution,
                $aRuntimeRestoreExecution
            );
        }

        $sPreviewRule = bx_get('preview');

        if (
            $sPreviewRule !== false &&
            trim((string)$sPreviewRule) !== ''
        ) {
            $sPreviewModule = bx_get('module');

            $aPreview = $this->oDoctor->getRepairPreview(
                (string)$sPreviewRule,
                $sPreviewModule !== false
                    ? (string)$sPreviewModule
                    : ''
            );

            $s .= '<div style="height:18px"></div>';
            $s .= $this->renderRepairPreview(
                $aPreview
            );
        }

        $s .= '</div></div>';

        return $s;
    }

    public function getDatabase()
    {
        return $this->renderCategoryPage('Database', array('database'));
    }

    public function getMediaHealth()
    {
        return $this->renderCategoryPage('Storage & Media', array('storage'));
    }

    public function getLogs()
    {
        return $this->renderCategoryPage('Logs', array('logs'));
    }

    public function getHistory()
    {
        $aRows = $this->oDoctor->getHealthScanHistory(50);
        $s = $this->css() . '<div class="mud-wrap"><div class="mud-section"><h1>Scan History</h1>';
        if (empty($aRows))
            return $s . '<div class="mud-empty">No scans have been recorded.</div></div></div>';

        $s .= '<div class="mud-table-wrap"><table class="mud-table"><thead><tr><th>ID</th><th>Completed</th><th>Score</th><th>Critical</th><th>Warnings</th><th>Checks OK</th><th>Duration</th></tr></thead><tbody>';
        foreach ($aRows as $aRow) {
            $s .= '<tr>';
            $s .= '<td>#' . (int)$aRow['id'] . '</td>';
            $s .= '<td>' . $this->esc(date('Y-m-d H:i:s', (int)$aRow['finished'])) . '</td>';
            $s .= '<td><strong>' . (int)$aRow['health_score'] . '%</strong></td>';
            $s .= '<td>' . (int)$aRow['critical_count'] . '</td>';
            $s .= '<td>' . (int)$aRow['warning_count'] . '</td>';
            $s .= '<td>' . (int)$aRow['ok_count'] . '</td>';
            $s .= '<td>' . (int)$aRow['duration_ms'] . ' ms</td>';
            $s .= '</tr>';
        }
        $s .= '</tbody></table></div></div></div>';
        return $s;
    }

    public function getSettings()
    {
        $s = $this->css() . '<div class="mud-wrap"><div class="mud-section">';
        $s .= '<h1>Safety Model</h1>';
        $s .= '<div class="mud-note"><strong>My UNA Doctor uses guarded, evidence-driven repair staging.</strong> Health scans remain diagnostic. Supported repair workflows require preview, live preflight, explicit approval, durable rollback state and verified execution gates before any controlled mutation is allowed.</div>';
        $s .= '<h2>Current diagnostic coverage</h2><p>System, filesystem, database, modules, module file integrity, Studio update readiness, repair-candidate detection, notifications, cron, logs, plus read-only Albums and Timeline media relationship integrity.</p>';
        $s .= '<h2>Repair candidate gate</h2><p>Doctor can identify high-confidence repair patterns, generate dry-run previews, run live preflight validation and explicitly arm supported repairs. Arming writes durable rollback state first, may enable soft maintenance and pause a verified generator, and stops before runtime files are protected or an update is started.</p>';
        $s .= '<h2>My UNA World integration</h2><p>A later release can request a My UNA World safety snapshot before an approved repair when that module is installed and active.</p>';
        $s .= '</div></div>';
        return $s;
    }


    protected function renderRepairCandidateCards(array $aScan)
    {
        $aCandidates = array();

        if (!empty($aScan['items'])) {
            foreach ($aScan['items'] as $aItem) {
                if (
                    !isset($aItem['category']) ||
                    $aItem['category'] !== 'repairs'
                ) {
                    continue;
                }

                if (
                    empty(
                        $aItem['evidence'][
                            'active_candidates'
                        ]
                    ) ||
                    !is_array(
                        $aItem['evidence'][
                            'active_candidates'
                        ]
                    )
                ) {
                    continue;
                }

                foreach (
                    $aItem['evidence'][
                        'active_candidates'
                    ]
                    as $aCandidate
                ) {
                    if (
                        isset($aCandidate['rule']) &&
                        $aCandidate['rule']
                            ===
                            'runtime_content_update_preparation'
                    ) {
                        $aCandidates[] = $aCandidate;
                    }
                }
            }
        }

        if (empty($aCandidates)) {
            return '<div class="mud-empty">'
                . 'No currently supported repair previews are available.'
                . '</div>';
        }

        $s = '<div class="mud-findings">';

        foreach ($aCandidates as $aCandidate) {
            $sModule = isset($aCandidate['module'])
                ? (string)$aCandidate['module']
                : '';

            $sTitle = isset($aCandidate['title'])
                ? (string)$aCandidate['title']
                : $sModule;

            $sDirectory = isset(
                $aCandidate['directory']
            )
                ? (string)$aCandidate['directory']
                : '';

            $iFiles = isset(
                $aCandidate['runtime_file_count']
            )
                ? (int)$aCandidate['runtime_file_count']
                : 0;

            $sConfidence = isset(
                $aCandidate['confidence']
            )
                ? ucfirst(
                    (string)$aCandidate['confidence']
                )
                : 'Unknown';

            $sSource = isset(
                $aCandidate['source_integrity']
            )
                ? ucfirst(
                    (string)$aCandidate[
                        'source_integrity'
                    ]
                )
                : 'Unknown';


            $sPreflightUrl = BX_DOL_URL_STUDIO
                . 'module.php?name=my_una_doctor'
                . '&page=repair_candidates'
                . '&preflight='
                . rawurlencode(
                    'runtime_content_update_preparation'
                )
                . '&module='
                . rawurlencode($sModule);

            $sArmPlanUrl = BX_DOL_URL_STUDIO
                . 'module.php?name=my_una_doctor'
                . '&page=repair_candidates'
                . '&arm_plan='
                . rawurlencode(
                    'runtime_content_update_preparation'
                )
                . '&module='
                . rawurlencode($sModule);

            $sUrl = BX_DOL_URL_STUDIO
                . 'module.php?name=my_una_doctor'
                . '&page=repair_candidates'
                . '&preview='
                . rawurlencode(
                    'runtime_content_update_preparation'
                )
                . '&module='
                . rawurlencode($sModule);

            $s .= '<div class="mud-finding info">';
            $s .= '<div class="mud-sev">'
                . '<i class="sys-icon tools"></i>'
                . '</div>';

            $s .= '<div class="mud-find-body">';

            $s .= '<div class="mud-find-head">'
                . '<strong>'
                . $this->esc($sTitle)
                . ' — Prepare for Update'
                . '</strong>'
                . '<span>PREVIEW AVAILABLE</span>'
                . '</div>';

            $s .= '<p>'
                . '<strong>Confidence:</strong> '
                . $this->esc($sConfidence)
                . '<br>'
                . '<strong>Source integrity:</strong> '
                . $this->esc($sSource)
                . '<br>'
                . '<strong>Runtime directory:</strong> '
                . $this->esc($sDirectory)
                . '<br>'
                . '<strong>Runtime files:</strong> '
                . number_format($iFiles)
                . '</p>';

            $s .= '<p>'
                . 'This preview describes how Doctor would protect runtime content '
                . 'before an approved module update. No changes are made.'
                . '</p>';

            $s .= '<div style="margin-top:12px">'
                . '<a class="bx-btn" href="'
                . $this->esc($sUrl)
                . '">'
                . '<i class="sys-icon search"></i> '
                . 'Preview Repair'
                . '</a>'
                . ' '
                . '<a class="bx-btn" href="'
                . $this->esc($sPreflightUrl)
                . '">'
                . '<i class="sys-icon clipboard-check"></i> '
                . 'Run Preflight'
                . '</a>'
                . ' '
                . '<a class="bx-btn" href="'
                . $this->esc($sArmPlanUrl)
                . '">'
                . '<i class="sys-icon shield-alt"></i> '
                . 'Arm Plan'
                . '</a>'
                . '</div>';

            $s .= '</div></div>';
        }

        $s .= '</div>';

        return $s;
    }

    protected function renderRepairPreview(array $aPreview)
    {
        $s = '<div class="mud-section">';

        $s .= '<h2>Repair Preview</h2>';

        if (
            empty($aPreview['ok'])
        ) {
            $s .= '<div class="mud-finding warning">'
                . '<div class="mud-sev">'
                . '<i class="sys-icon exclamation-circle"></i>'
                . '</div>'
                . '<div class="mud-find-body">'
                . '<div class="mud-find-head">'
                . '<strong>Preview unavailable</strong>'
                . '<span>NO CHANGES MADE</span>'
                . '</div>'
                . '<p>'
                . $this->esc(
                    isset($aPreview['error'])
                        ? $aPreview['error']
                        : 'Unknown preview error.'
                )
                . '</p>'
                . '</div></div>';

            return $s . '</div>';
        }

        $s .= '<div class="mud-note">'
            . '<strong>DRY RUN — NO CHANGES MADE</strong><br>'
            . 'This preview is informational only. '
            . 'It does not authorize or perform the repair.'
            . '</div>';

        $s .= '<div class="mud-table-wrap">';
        $s .= '<table class="mud-table"><tbody>';

        $aRows = array(
            'Preview ID' =>
                $aPreview['preview_id'] ?? '',
            'Module' =>
                $aPreview['title'] ?? '',
            'Rule' =>
                $aPreview['rule'] ?? '',
            'Confidence' =>
                ucfirst(
                    (string)(
                        $aPreview['confidence']
                        ?? 'unknown'
                    )
                ),
            'Source integrity' =>
                ucfirst(
                    (string)(
                        $aPreview['source_integrity']
                        ?? 'unknown'
                    )
                ),
            'Runtime directory' =>
                $aPreview['runtime_directory'] ?? '',
            'Runtime files' =>
                number_format(
                    (int)(
                        $aPreview['runtime_file_count']
                        ?? 0
                    )
                ),
            'Safety backup root' =>
                $aPreview['safety_backup_root']
                ?? '',
            'Changes applied' =>
                !empty(
                    $aPreview['changes_applied']
                )
                    ? 'YES'
                    : 'NO',
            'Automatic repair' =>
                !empty(
                    $aPreview['automatic_repair']
                )
                    ? 'YES'
                    : 'NO',
        );

        foreach ($aRows as $sLabel => $sValue) {
            $s .= '<tr>'
                . '<th>'
                . $this->esc($sLabel)
                . '</th>'
                . '<td>'
                . $this->esc($sValue)
                . '</td>'
                . '</tr>';
        }

        $s .= '</tbody></table></div>';

        $aSections = array(
            'Preconditions' =>
                $aPreview['preconditions'] ?? array(),

            'Proposed operation' =>
                $aPreview['proposed_steps'] ?? array(),

            'Validation after a future approved repair' =>
                $aPreview[
                    'validation_after_future_apply'
                ] ?? array(),

            'Rollback plan' =>
                $aPreview['rollback_plan'] ?? array(),

            'Safety restrictions' =>
                $aPreview['warnings'] ?? array(),
        );

        foreach ($aSections as $sTitle => $aItems) {
            if (
                empty($aItems) ||
                !is_array($aItems)
            ) {
                continue;
            }

            $s .= '<h3 style="margin-top:20px">'
                . $this->esc($sTitle)
                . '</h3>';

            $s .= '<ol>';

            foreach ($aItems as $sItem) {
                $s .= '<li style="margin:6px 0">'
                    . $this->esc($sItem)
                    . '</li>';
            }

            $s .= '</ol>';
        }

        $s .= '<div class="mud-note">'
            . '<strong>Repair execution remains deliberately staged.</strong> '
            . 'Doctor requires explicit approval, verified preflight, durable rollback '
            . 'state and protected runtime handling before any module update step can occur.'
            . '</div>';

        $s .= '</div>';

        return $s;
    }



    protected function renderRepairArmPlan(
        array $aPlan,
        $aApprovalValidation = null,
        $aArmExecution = null,
        $aActiveRepairJournal = array(),
        $aRestoreExecution = null,
        $aRuntimeProtectionExecution = null,
        $aRuntimeRestoreExecution = null
    )
    {
        $s = '<div class="mud-section">';
        $s .= '<h2>Repair Arm Plan</h2>';


        if (is_array($aRuntimeRestoreExecution)) {
            if (
                !empty($aRuntimeRestoreExecution['ok'])
                && !empty(
                    $aRuntimeRestoreExecution['restored']
                )
            ) {
                $s .= '<div class="mud-note">'
                    . '<strong>RUNTIME RESTORED — CONTROL BARRIER STILL ACTIVE</strong><br>'
                    . 'The complete original runtime tree was verified '
                    . 'and returned to its live module location. '
                    . 'The repair journal is back in the armed state. '
                    . 'You may now either protect it again or disarm '
                    . 'and restore the original maintenance and generator state.'
                    . '</div>';
            } else {
                $s .= '<div class="mud-finding warning">'
                    . '<div class="mud-find-body">'
                    . '<div class="mud-find-head">'
                    . '<strong>Runtime restore/recovery did not complete</strong>'
                    . '</div>'
                    . '<p>'
                    . $this->esc(
                        (string)(
                            $aRuntimeRestoreExecution['error']
                            ?? 'Unknown protected-runtime restore error.'
                        )
                    )
                    . '</p>'
                    . '</div></div>';
            }
        }


        if (is_array($aRuntimeProtectionExecution)) {
            if (
                !empty($aRuntimeProtectionExecution['ok'])
                && !empty(
                    $aRuntimeProtectionExecution['protected']
                )
            ) {
                $s .= '<div class="mud-note">'
                    . '<strong>RUNTIME PROTECTED — VERIFIED</strong><br>'
                    . 'The original runtime tree was moved to its '
                    . 'journal-specific safety destination. '
                    . 'Only UNA-tracked runtime files remain in the '
                    . 'live module tree. The control barrier remains active.'
                    . '</div>';
            } else {
                $s .= '<div class="mud-finding warning">'
                    . '<div class="mud-find-body">'
                    . '<div class="mud-find-head">'
                    . '<strong>Runtime protection did not complete</strong>'
                    . '</div>'
                    . '<p>'
                    . $this->esc(
                        (string)(
                            $aRuntimeProtectionExecution['error']
                            ?? 'Unknown runtime-protection error.'
                        )
                    )
                    . '</p>'
                    . '</div></div>';
            }
        }


        if (is_array($aArmExecution)) {
            $bArmed =
                !empty($aArmExecution['ok'])
                && !empty($aArmExecution['armed']);

            $s .= '<div class="mud-finding '
                . ($bArmed ? 'ok' : 'warning')
                . '">';

            $s .= '<div class="mud-sev">'
                . '<i class="sys-icon '
                . ($bArmed
                    ? 'check-circle'
                    : 'exclamation-circle')
                . '"></i>'
                . '</div>';

            $s .= '<div class="mud-find-body">'
                . '<div class="mud-find-head">'
                . '<strong>'
                . (
                    $bArmed
                        ? 'REPAIR ARMED — CONTROL BARRIER ACTIVE'
                        : 'ARM REPAIR FAILED'
                )
                . '</strong>'
                . '<span>'
                . (
                    $bArmed
                        ? 'RUNTIME FILES NOT MOVED'
                        : 'REVIEW REQUIRED'
                )
                . '</span>'
                . '</div>';

            if ($bArmed) {
                $aState =
                    $aArmExecution['state']
                    ?? array();

                $s .= '<p>'
                    . '<strong>Journal ID:</strong> '
                    . $this->esc(
                        (string)(
                            $aArmExecution['journal_id']
                            ?? ''
                        )
                    )
                    . '<br><strong>Soft maintenance:</strong> '
                    . $this->esc(
                        (string)(
                            $aState['maintenance_value']
                            ?? ''
                        )
                    )
                    . '<br><strong>Generator ID:</strong> '
                    . $this->esc(
                        (string)(
                            $aState['generator_id']
                            ?? ''
                        )
                    )
                    . '<br><strong>Paused schedule:</strong> '
                    . $this->esc(
                        (string)(
                            $aState['generator_schedule']
                            ?? ''
                        )
                    )
                    . '<br><strong>Runtime moved:</strong> NO'
                    . '</p>';
            } else {
                $s .= '<p>'
                    . $this->esc(
                        (string)(
                            $aArmExecution['error']
                            ?? 'Arm Repair failed.'
                        )
                    )
                    . '</p>';

                if (
                    isset($aArmExecution['rollback'])
                    && is_array(
                        $aArmExecution['rollback']
                    )
                ) {
                    $s .= '<p><strong>Automatic rollback:</strong> '
                        . (
                            !empty(
                                $aArmExecution[
                                    'rollback'
                                ]['ok']
                            )
                                ? 'VERIFIED'
                                : 'REQUIRES REVIEW'
                        )
                        . '</p>';
                }
            }

            $s .= '</div></div>';
        }


        /*
         * Show the result of a deliberate restore before rebuilding
         * any fresh Arm Plan.
         */
        if (is_array($aRestoreExecution)) {
            $bRestored =
                !empty($aRestoreExecution['ok'])
                && !empty($aRestoreExecution['restored']);

            $s .= '<div class="mud-finding '
                . ($bRestored ? 'ok' : 'warning')
                . '">';

            $s .= '<div class="mud-sev">'
                . '<i class="sys-icon '
                . ($bRestored
                    ? 'check-circle'
                    : 'exclamation-circle')
                . '"></i>'
                . '</div>';

            $s .= '<div class="mud-find-body">'
                . '<div class="mud-find-head">'
                . '<strong>'
                . (
                    $bRestored
                        ? 'ORIGINAL STATE RESTORED'
                        : 'RESTORE FAILED'
                )
                . '</strong>'
                . '<span>'
                . (
                    $bRestored
                        ? 'CONTROL BARRIER REMOVED'
                        : 'REVIEW REQUIRED'
                )
                . '</span>'
                . '</div>';

            if ($bRestored) {
                $aRestoreState =
                    $aRestoreExecution['state']
                    ?? array();

                $s .= '<p>'
                    . '<strong>Journal ID:</strong> '
                    . $this->esc(
                        (string)(
                            $aRestoreExecution['journal_id']
                            ?? ''
                        )
                    )
                    . '<br><strong>Maintenance:</strong> '
                    . (
                        (string)(
                            $aRestoreState['maintenance_value']
                            ?? ''
                        ) === ''
                            ? 'OFF'
                            : $this->esc(
                                (string)$aRestoreState[
                                    'maintenance_value'
                                ]
                            )
                    )
                    . '<br><strong>Generator ID:</strong> '
                    . $this->esc(
                        (string)(
                            $aRestoreState['generator_id']
                            ?? ''
                        )
                    )
                    . '<br><strong>Restored schedule:</strong> '
                    . $this->esc(
                        (string)(
                            $aRestoreState['generator_schedule']
                            ?? ''
                        )
                    )
                    . '<br><strong>Runtime moved:</strong> NO'
                    . '</p>';
            } else {
                $s .= '<p>'
                    . $this->esc(
                        (string)(
                            $aRestoreExecution['error']
                            ?? 'Restore failed.'
                        )
                    )
                    . '</p>';
            }

            $s .= '</div></div>';
        }

        /*
         * An active durable journal is authoritative. Do not build
         * or expose another Arm Repair while one is active.
         */
        $bActiveRepair =
            is_array($aActiveRepairJournal)
            && !empty($aActiveRepairJournal['id']);

        if ($bActiveRepair) {
            $sStatus =
                (string)(
                    $aActiveRepairJournal['status']
                    ?? ''
                );

            $aActiveResult =
                $aActiveRepairJournal['result']
                ?? array();

            $aActiveOriginal =
                $aActiveRepairJournal['original_state']
                ?? array();

            $aActiveGenerator =
                $aActiveOriginal['generator']
                ?? array();

            $s .= '<div class="mud-note">'
                . '<strong>ACTIVE REPAIR JOURNAL — '
                . $this->esc(
                    strtoupper($sStatus)
                )
                . '</strong><br>'
                . 'This durable journal is now the authoritative '
                . 'repair state. A second Arm Repair is disabled.'
                . '</div>';

            $s .= '<div class="mud-table-wrap">';
            $s .= '<table class="mud-table"><tbody>';

            $aActiveRows = array(
                'Journal ID' =>
                    $aActiveRepairJournal['id']
                    ?? '',

                'Status' =>
                    $sStatus,

                'Rule' =>
                    $aActiveRepairJournal['rule']
                    ?? '',

                'Module' =>
                    $aActiveRepairJournal['module']
                    ?? '',

                'Approved Preflight ID' =>
                    $aActiveRepairJournal['preflight_id']
                    ?? '',

                'Approved Arm ID' =>
                    $aActiveRepairJournal['arm_id']
                    ?? '',

                'Current maintenance' =>
                    (
                        (string)(
                            $aActiveResult[
                                'maintenance_value'
                            ]
                            ?? ''
                        ) === ''
                            ? 'OFF'
                            : (
                                $aActiveResult[
                                    'maintenance_value'
                                ]
                                ?? ''
                            )
                    ),

                'Current template compression' =>
                    (
                        array_key_exists(
                            'compression_value',
                            $aActiveResult
                        )
                            ? (
                                (string)$aActiveResult[
                                    'compression_value'
                                ] === 'on'
                                    ? 'ON'
                                    : (
                                        (string)$aActiveResult[
                                            'compression_value'
                                        ] === ''
                                            ? 'OFF'
                                            : (string)$aActiveResult[
                                                'compression_value'
                                            ]
                                    )
                            )
                            : 'UNKNOWN'
                    ),

                'Generator ID' =>
                    $aActiveResult['generator_id']
                    ?? (
                        $aActiveGenerator['id']
                        ?? ''
                    ),

                'Current generator schedule' =>
                    $aActiveResult[
                        'generator_schedule'
                    ]
                    ?? '',

                'Original maintenance' =>
                    (
                        (string)(
                            $aActiveOriginal[
                                'soft_maintenance_value'
                            ]
                            ?? ''
                        ) === ''
                            ? 'OFF'
                            : $aActiveOriginal[
                                'soft_maintenance_value'
                            ]
                    ),

                'Original template compression' =>
                    (
                        array_key_exists(
                            'template_cache_compress_value',
                            $aActiveOriginal
                        )
                            ? (
                                (string)$aActiveOriginal[
                                    'template_cache_compress_value'
                                ] === 'on'
                                    ? 'ON'
                                    : (
                                        (string)$aActiveOriginal[
                                            'template_cache_compress_value'
                                        ] === ''
                                            ? 'OFF'
                                            : (string)$aActiveOriginal[
                                                'template_cache_compress_value'
                                            ]
                                    )
                            )
                            : 'UNKNOWN'
                    ),

                'Original generator schedule' =>
                    $aActiveGenerator['time']
                    ?? '',

                'Runtime moved' =>
                    !empty(
                        $aActiveResult['runtime_moved']
                    )
                        ? 'YES'
                        : 'NO',
            );

            foreach (
                $aActiveRows
                as $sLabel => $sValue
            ) {
                $s .= '<tr>'
                    . '<th>'
                    . $this->esc($sLabel)
                    . '</th>'
                    . '<td>'
                    . $this->esc(
                        (string)$sValue
                    )
                    . '</td>'
                    . '</tr>';
            }

            $s .= '</tbody></table></div>';

            if ($sStatus === 'armed') {
                $sCsrfToken =
                    BxDolForm::getCsrfToken();

                $sRestoreActionUrl =
                    BX_DOL_URL_STUDIO
                    . 'module.php?name=my_una_doctor'
                    . '&page=repair_candidates'
                    . '&arm_plan='
                    . rawurlencode(
                        (string)(
                            $aActiveRepairJournal['rule']
                            ?? ''
                        )
                    )
                    . '&module='
                    . rawurlencode(
                        (string)(
                            $aActiveRepairJournal['module']
                            ?? ''
                        )
                    );

                $iActiveJournalId =
                    (int)(
                        $aActiveRepairJournal['id']
                        ?? 0
                    );

                $aRuntimeProtectionPlan =
                    $iActiveJournalId > 0
                        ? $this->oDoctor
                            ->getRuntimeProtectionPlan(
                                $iActiveJournalId
                            )
                        : array();

                $s .= '<hr style="margin:24px 0">';

                $s .= '<h3>Protect Runtime for Update</h3>';

                if (
                    !empty($aRuntimeProtectionPlan['ok'])
                    && !empty(
                        $aRuntimeProtectionPlan['ready']
                    )
                    && !empty(
                        $aRuntimeProtectionPlan[
                            'protection_id'
                        ]
                    )
                ) {
                    $sProtectionId =
                        (string)$aRuntimeProtectionPlan[
                            'protection_id'
                        ];

                    $aRuntimePlanData =
                        $aRuntimeProtectionPlan['runtime']
                        ?? array();

                    $s .= '<div class="mud-note">'
                        . '<strong>READY FOR ATOMIC RUNTIME PROTECTION</strong><br>'
                        . 'Protection ID: <code>'
                        . $this->esc($sProtectionId)
                        . '</code><br>'
                        . 'Runtime files: '
                        . $this->esc(
                            number_format(
                                (int)(
                                    $aRuntimePlanData[
                                        'file_count'
                                    ]
                                    ?? 0
                                )
                            )
                        )
                        . ' &nbsp;|&nbsp; Tracked: '
                        . $this->esc(
                            number_format(
                                (int)(
                                    $aRuntimePlanData[
                                        'tracked_file_count'
                                    ]
                                    ?? 0
                                )
                            )
                        )
                        . ' &nbsp;|&nbsp; Untracked: '
                        . $this->esc(
                            number_format(
                                (int)(
                                    $aRuntimePlanData[
                                        'untracked_file_count'
                                    ]
                                    ?? 0
                                )
                            )
                        )
                        . '<br>'
                        . 'This will atomically protect the complete '
                        . 'runtime tree, recreate the live runtime '
                        . 'directory, restore only UNA-tracked files '
                        . 'and verify both trees. '
                        . '<strong>No module update will be started.</strong>'
                        . '</div>';

                    $s .= '<form method="post" action="'
                        . $this->esc(
                            $sRestoreActionUrl
                        )
                        . '">';

                    $s .= '<input type="hidden" '
                        . 'name="mud_action" '
                        . 'value="protect_runtime">';

                    $s .= '<input type="hidden" '
                        . 'name="journal_id" value="'
                        . $this->esc(
                            (string)$iActiveJournalId
                        )
                        . '">';

                    $s .= '<input type="hidden" '
                        . 'name="protection_id" value="'
                        . $this->esc(
                            $sProtectionId
                        )
                        . '">';

                    $s .= '<input type="hidden" '
                        . 'name="csrf_token" value="'
                        . $this->esc(
                            $sCsrfToken !== false
                                ? (string)$sCsrfToken
                                : ''
                        )
                        . '">';

                    $s .= '<label '
                        . 'style="display:block;margin:16px 0">'
                        . '<input type="checkbox" '
                        . 'name="confirm_protect" '
                        . 'value="yes" required> '
                        . 'I approve protecting the runtime content '
                        . 'for repair journal '
                        . $this->esc(
                            (string)$iActiveJournalId
                        )
                        . '. The control barrier must remain active.'
                        . '</label>';

                    $s .= '<button type="submit" '
                        . 'class="bx-btn">'
                        . '<i class="sys-icon shield-alt"></i> '
                        . 'Protect Runtime'
                        . '</button>';

                    $s .= '</form>';
                } else {
                    $s .= '<div class="mud-finding warning">'
                        . '<div class="mud-find-body">'
                        . '<div class="mud-find-head">'
                        . '<strong>Runtime protection is not ready</strong>'
                        . '</div>'
                        . '<p>'
                        . $this->esc(
                            (string)(
                                $aRuntimeProtectionPlan[
                                    'error'
                                ]
                                ?? 'The current runtime protection plan did not pass all safety gates.'
                            )
                        )
                        . '</p>'
                        . '</div></div>';
                }

                $s .= '<hr style="margin:24px 0">';

                $s .= '<h3>'
                    . 'Disarm / Restore Original State'
                    . '</h3>';

                $s .= '<div class="mud-note">'
                    . '<strong>This removes the active control barrier.</strong> '
                    . 'Doctor will restore the exact journaled generator '
                    . 'schedule, clear the cron cache, restore the exact '
                    . 'original soft-maintenance value, restore the exact '
                    . 'original template-cache compression value and verify '
                    . 'compression, maintenance and generator state independently. '
                    . '<strong>No runtime files will be moved.</strong>'
                    . '</div>';

                $s .= '<form method="post" action="'
                    . $this->esc(
                        $sRestoreActionUrl
                    )
                    . '">';

                $s .= '<input type="hidden" '
                    . 'name="mud_action" '
                    . 'value="restore_armed_repair">';

                $s .= '<input type="hidden" '
                    . 'name="journal_id" value="'
                    . $this->esc(
                        (string)(
                            $aActiveRepairJournal['id']
                            ?? ''
                        )
                    )
                    . '">';

                $s .= '<input type="hidden" '
                    . 'name="csrf_token" value="'
                    . $this->esc(
                        $sCsrfToken !== false
                            ? (string)$sCsrfToken
                            : ''
                    )
                    . '">';

                $s .= '<label '
                    . 'style="display:block;margin:16px 0">'
                    . '<input type="checkbox" '
                    . 'name="confirm_restore" '
                    . 'value="yes" required> '
                    . 'I approve restoring the exact original '
                    . 'template compression, maintenance and generator state from '
                    . 'repair journal '
                    . $this->esc(
                        (string)(
                            $aActiveRepairJournal['id']
                            ?? ''
                        )
                    )
                    . '.'
                    . '</label>';

                $s .= '<button type="submit" '
                    . 'class="bx-btn">'
                    . '<i class="sys-icon undo"></i> '
                    . 'Disarm / Restore Original State'
                    . '</button>';

                $s .= '</form>';
            } elseif (
                in_array(
                    $sStatus,
                    array(
                        'runtime_protecting',
                        'runtime_protected',
                        'runtime_restoring',
                    ),
                    true
                )
            ) {
                $sCsrfToken =
                    BxDolForm::getCsrfToken();

                $sRuntimeRestoreActionUrl =
                    BX_DOL_URL_STUDIO
                    . 'module.php?name=my_una_doctor'
                    . '&page=repair_candidates'
                    . '&arm_plan='
                    . rawurlencode(
                        (string)(
                            $aActiveRepairJournal['rule']
                            ?? ''
                        )
                    )
                    . '&module='
                    . rawurlencode(
                        (string)(
                            $aActiveRepairJournal['module']
                            ?? ''
                        )
                    );

                $bProtected =
                    $sStatus === 'runtime_protected';

                $s .= '<hr style="margin:24px 0">';

                $s .= '<h3>'
                    . (
                        $bProtected
                            ? 'Restore Protected Runtime'
                            : 'Resume Runtime Recovery'
                    )
                    . '</h3>';

                $s .= '<div class="mud-note">'
                    . '<strong>'
                    . (
                        $bProtected
                            ? 'The original runtime tree is protected outside the live module tree.'
                            : 'Doctor has an interrupted or in-progress runtime operation to resolve.'
                    )
                    . '</strong><br>'
                    . 'Doctor will keep the maintenance and generator '
                    . 'control barrier active, verify the journaled '
                    . 'runtime fingerprint, restore the complete '
                    . 'original runtime tree when required and return '
                    . 'the journal to <code>armed</code>. '
                    . '<strong>Disarm remains unavailable until '
                    . 'runtime recovery is verified.</strong>'
                    . '</div>';

                $s .= '<form method="post" action="'
                    . $this->esc(
                        $sRuntimeRestoreActionUrl
                    )
                    . '">';

                $s .= '<input type="hidden" '
                    . 'name="mud_action" '
                    . 'value="restore_protected_runtime">';

                $s .= '<input type="hidden" '
                    . 'name="journal_id" value="'
                    . $this->esc(
                        (string)(
                            $aActiveRepairJournal['id']
                            ?? ''
                        )
                    )
                    . '">';

                $s .= '<input type="hidden" '
                    . 'name="csrf_token" value="'
                    . $this->esc(
                        $sCsrfToken !== false
                            ? (string)$sCsrfToken
                            : ''
                    )
                    . '">';

                $s .= '<label '
                    . 'style="display:block;margin:16px 0">'
                    . '<input type="checkbox" '
                    . 'name="confirm_runtime_restore" '
                    . 'value="yes" required> '
                    . 'I approve '
                    . (
                        $bProtected
                            ? 'restoring the protected original runtime tree'
                            : 'resuming guarded runtime recovery'
                    )
                    . ' for repair journal '
                    . $this->esc(
                        (string)(
                            $aActiveRepairJournal['id']
                            ?? ''
                        )
                    )
                    . '.'
                    . '</label>';

                $s .= '<button type="submit" '
                    . 'class="bx-btn">'
                    . '<i class="sys-icon undo"></i> '
                    . (
                        $bProtected
                            ? 'Restore Protected Runtime'
                            : 'Resume Runtime Recovery'
                    )
                    . '</button>';

                $s .= '</form>';

            } elseif (
                in_array(
                    $sStatus,
                    array(
                        'rollback_failed',
                        'runtime_rollback_failed',
                    ),
                    true
                )
            ) {
                $s .= '<div class="mud-finding warning">'
                    . '<div class="mud-sev">'
                    . '<i class="sys-icon exclamation-circle"></i>'
                    . '</div>'
                    . '<div class="mud-find-body">'
                    . '<div class="mud-find-head">'
                    . '<strong>Manual review required</strong>'
                    . '<span>DO NOT RE-ARM</span>'
                    . '</div>'
                    . '<p>'
                    . $this->esc(
                        (string)(
                            $aActiveRepairJournal['error_text']
                            ?? 'Rollback verification failed.'
                        )
                    )
                    . '</p>'
                    . '</div></div>';
            }

            $s .= '<div class="mud-note">'
                . '<strong>Runtime protection is available while this journal is armed.</strong> '
                . 'Doctor requires a fresh protection fingerprint, '
                . 'explicit administrator confirmation and durable '
                . 'recovery state before any runtime tree is moved.'
                . '</div>';

            return $s . '</div>';
        }

        if (empty($aPlan['ok'])) {
            $s .= '<div class="mud-finding warning">'
                . '<div class="mud-sev">'
                . '<i class="sys-icon exclamation-circle"></i>'
                . '</div>'
                . '<div class="mud-find-body">'
                . '<div class="mud-find-head">'
                . '<strong>Arm plan unavailable</strong>'
                . '<span>NO CHANGES MADE</span>'
                . '</div>'
                . '<p>'
                . $this->esc(
                    $aPlan['error']
                    ?? 'Unknown arm-plan error.'
                )
                . '</p>'
                . '</div></div>';

            return $s . '</div>';
        }

        $aOriginal =
            $aPlan['original_state']
            ?? array();

        $aGenerator =
            $aOriginal['generator']
            ?? array();

        $aPause =
            $aPlan['planned_pause']
            ?? array();

        $s .= '<div class="mud-note">'
            . '<strong>READ-ONLY ARMING PLAN — NO CHANGES MADE</strong><br>'
            . 'Doctor has verified the repair can be armed, '
            . 'but maintenance mode, cron state and files remain unchanged.'
            . '</div>';

        $s .= '<div class="mud-table-wrap">';
        $s .= '<table class="mud-table"><tbody>';

        $aRows = array(
            'Arm ID' =>
                $aPlan['arm_id'] ?? '',

            'Preflight ID' =>
                $aPlan['preflight_id'] ?? '',

            'Rule' =>
                $aPlan['rule'] ?? '',

            'Module' =>
                $aPlan['module'] ?? '',

            'Changes applied' =>
                !empty($aPlan['changes_applied'])
                    ? 'YES'
                    : 'NO',

            'Original soft maintenance' =>
                !empty(
                    $aOriginal[
                        'soft_maintenance_enabled'
                    ]
                )
                    ? 'ON'
                    : 'OFF',

            'Hard maintenance file' =>
                !empty(
                    $aOriginal[
                        'hard_maintenance_file'
                    ]
                )
                    ? 'YES'
                    : 'NO',

            'Generator ID' =>
                $aGenerator['id'] ?? '',

            'Generator name' =>
                $aGenerator['name'] ?? '',

            'Original schedule' =>
                $aPause['original_schedule']
                ?? '',

            'Planned pause schedule' =>
                $aPause['pause_schedule']
                ?? '',

            'Cron cache to clear' =>
                $aPause['cache_to_clear']
                ?? '',
        );

        foreach ($aRows as $sLabel => $sValue) {
            $s .= '<tr>'
                . '<th>'
                . $this->esc($sLabel)
                . '</th>'
                . '<td>'
                . $this->esc((string)$sValue)
                . '</td>'
                . '</tr>';
        }

        $s .= '</tbody></table></div>';

        $aSections = array(
            'Planned arming sequence' =>
                $aPlan['planned_steps']
                ?? array(),

            'Rollback plan' =>
                $aPlan['rollback_plan']
                ?? array(),
        );

        foreach ($aSections as $sTitle => $aItems) {
            if (
                empty($aItems)
                || !is_array($aItems)
            ) {
                continue;
            }

            $s .= '<h3 style="margin-top:20px">'
                . $this->esc($sTitle)
                . '</h3>';

            $s .= '<ol>';

            foreach ($aItems as $sItem) {
                $s .= '<li style="margin:6px 0">'
                    . $this->esc($sItem)
                    . '</li>';
            }

            $s .= '</ol>';
        }

        if (is_array($aApprovalValidation)) {
            $bApproved =
                !empty($aApprovalValidation['ok'])
                && !empty($aApprovalValidation['approved']);

            $s .= '<div class="mud-finding '
                . ($bApproved ? 'ok' : 'warning')
                . '">';

            $s .= '<div class="mud-sev">'
                . '<i class="sys-icon '
                . ($bApproved ? 'check-circle' : 'exclamation-circle')
                . '"></i>'
                . '</div>';

            $s .= '<div class="mud-find-body">'
                . '<div class="mud-find-head">'
                . '<strong>'
                . (
                    $bApproved
                        ? 'Approval validation passed'
                        : 'Approval validation failed'
                )
                . '</strong>'
                . '<span>NO CHANGES MADE</span>'
                . '</div>';

            $s .= '<p>'
                . $this->esc(
                    $bApproved
                        ? 'POST, CSRF token, Preflight ID and Arm ID all validated against fresh live state.'
                        : (
                            $aApprovalValidation['error']
                            ?? 'Approval validation failed.'
                        )
                )
                . '</p>';

            $s .= '</div></div>';
        }


        /*
         * renderRepairArmPlan() has its own PHP method scope.
         * Generate a CSRF token locally for the approval and
         * Arm Repair forms rendered below.
         */
        $sCsrfToken =
            BxDolForm::getCsrfToken();

        $sActionUrl = BX_DOL_URL_STUDIO
            . 'module.php?name=my_una_doctor'
            . '&page=repair_candidates'
            . '&arm_plan='
            . rawurlencode(
                (string)($aPlan['rule'] ?? '')
            )
            . '&module='
            . rawurlencode(
                (string)($aPlan['module'] ?? '')
            );

        $s .= '<h3 style="margin-top:20px">'
            . 'Validate Explicit Approval'
            . '</h3>';

        $s .= '<p>'
            . 'This performs only the approval safety check. '
            . 'It does not arm or apply the repair.'
            . '</p>';

        $s .= '<form method="post" action="'
            . $this->esc($sActionUrl)
            . '">';

        $s .= '<input type="hidden" name="mud_action" '
            . 'value="validate_arm_approval">';

        $s .= '<input type="hidden" name="repair_rule" value="'
            . $this->esc(
                (string)($aPlan['rule'] ?? '')
            )
            . '">';

        $s .= '<input type="hidden" name="repair_module" value="'
            . $this->esc(
                (string)($aPlan['module'] ?? '')
            )
            . '">';

        $s .= '<input type="hidden" name="preflight_id" value="'
            . $this->esc(
                (string)($aPlan['preflight_id'] ?? '')
            )
            . '">';

        $s .= '<input type="hidden" name="arm_id" value="'
            . $this->esc(
                (string)($aPlan['arm_id'] ?? '')
            )
            . '">';

        $s .= '<input type="hidden" name="csrf_token" value="'
            . $this->esc(
                $sCsrfToken !== false
                    ? (string)$sCsrfToken
                    : ''
            )
            . '">';

        $s .= '<button type="submit" class="bx-btn">'
            . '<i class="sys-icon shield-alt"></i> '
            . 'Validate Approval'
            . '</button>';

        $s .= '</form>';

        $s .= '<hr style="margin:24px 0">';

        $s .= '<h3>Arm Repair</h3>';

        $s .= '<div class="mud-note">'
            . '<strong>This action changes live site state.</strong> '
            . 'Doctor will write the durable repair journal first, '
            . 'disable and verify template-cache compression, '
            . 'enable UNA soft maintenance if currently off, '
            . 'pause only the verified generator, clear the cron cache '
            . 'and verify the complete control barrier. '
            . '<strong>No runtime files will be moved.</strong>'
            . '</div>';

        $s .= '<form method="post" action="'
            . $this->esc($sActionUrl)
            . '">';

        $s .= '<input type="hidden" name="mud_action" '
            . 'value="arm_approved_repair">';

        $s .= '<input type="hidden" name="repair_rule" value="'
            . $this->esc(
                (string)($aPlan['rule'] ?? '')
            )
            . '">';

        $s .= '<input type="hidden" name="repair_module" value="'
            . $this->esc(
                (string)($aPlan['module'] ?? '')
            )
            . '">';

        $s .= '<input type="hidden" name="preflight_id" value="'
            . $this->esc(
                (string)($aPlan['preflight_id'] ?? '')
            )
            . '">';

        $s .= '<input type="hidden" name="arm_id" value="'
            . $this->esc(
                (string)($aPlan['arm_id'] ?? '')
            )
            . '">';

        $s .= '<input type="hidden" name="csrf_token" value="'
            . $this->esc(
                $sCsrfToken !== false
                    ? (string)$sCsrfToken
                    : ''
            )
            . '">';

        $s .= '<label style="display:block;margin:16px 0">'
            . '<input type="checkbox" '
            . 'name="confirm_arm" '
            . 'value="yes" required> '
            . 'I approve disabling template-cache compression, '
            . 'enabling soft maintenance and pausing the verified generator. '
            . 'No runtime files will be moved.'
            . '</label>';

        $s .= '<button type="submit" class="bx-btn">'
            . '<i class="sys-icon shield-alt"></i> '
            . 'Arm Repair'
            . '</button>';

        $s .= '</form>';


        $s .= '<div class="mud-note">'
            . '<strong>Arm Repair establishes the three-part control barrier first.</strong> '
            . 'Runtime protection is a separate explicitly approved step. '
            . 'Doctor does not automatically start a module update '
            . 'or rewrite checksum baselines.'
            . '</div>';

        $s .= '</div>';

        return $s;
    }


    protected function renderRepairPreflight(array $aPreflight)
    {
        $s = '<div class="mud-section">';
        $s .= '<h2>Repair Preflight</h2>';

        if (empty($aPreflight['ok'])) {
            $s .= '<div class="mud-finding warning">'
                . '<div class="mud-sev">'
                . '<i class="sys-icon exclamation-circle"></i>'
                . '</div>'
                . '<div class="mud-find-body">'
                . '<div class="mud-find-head">'
                . '<strong>Preflight unavailable</strong>'
                . '<span>NO CHANGES MADE</span>'
                . '</div>'
                . '<p>'
                . $this->esc(
                    isset($aPreflight['error'])
                        ? $aPreflight['error']
                        : 'Unknown preflight error.'
                )
                . '</p>'
                . '</div></div>';

            return $s . '</div>';
        }

        $bReady = !empty($aPreflight['ready']);

        $s .= '<div class="mud-note">'
            . '<strong>'
            . (
                $bReady
                    ? 'PREFLIGHT PASSED'
                    : 'PREFLIGHT NOT READY'
            )
            . '</strong><br>'
            . (
                $bReady
                    ? 'All mandatory read-only preflight checks passed.'
                    : 'One or more mandatory checks failed. Apply Repair remains unavailable.'
            )
            . '<br><strong>No changes were made.</strong>'
            . '</div>';

        if (!empty($aPreflight['process'])) {
            $p = $aPreflight['process'];

            $s .= '<p>'
                . '<strong>PHP process:</strong> '
                . $this->esc(
                    isset($p['user'])
                        && $p['user'] !== ''
                            ? $p['user']
                            : 'unknown user'
                )
                . ' / '
                . $this->esc(
                    isset($p['sapi'])
                        ? $p['sapi']
                        : ''
                )
                . '</p>';
        }

        if (!empty($aPreflight['runtime'])) {
            $r = $aPreflight['runtime'];

            $s .= '<p>'
                . '<strong>Live runtime content:</strong> '
                . number_format(
                    (int)(
                        $r['file_count']
                        ?? 0
                    )
                )
                . ' total files'
                . '<br>'
                . '<strong>Tracked in runtime directory:</strong> '
                . number_format(
                    (int)(
                        $r['tracked_file_count']
                        ?? 0
                    )
                )
                . '<br>'
                . '<strong>Untracked/runtime:</strong> '
                . number_format(
                    (int)(
                        $r['untracked_file_count']
                        ?? 0
                    )
                )
                . '<br>'
                . '<strong>Runtime size:</strong> '
                . $this->esc(
                    $this->formatBytes(
                        (int)(
                            $r['bytes']
                            ?? 0
                        )
                    )
                )
                . '<br>'
                . '<strong>Directory:</strong> '
                . $this->esc(
                    $r['relative_directory']
                    ?? ''
                )
                . '</p>';
        }

        $s .= '<div class="mud-table-wrap">';
        $s .= '<table class="mud-table">';
        $s .= '<thead>'
            . '<tr>'
            . '<th>Check</th>'
            . '<th>Status</th>'
            . '<th>Evidence</th>'
            . '</tr>'
            . '</thead><tbody>';

        foreach (
            $aPreflight['checks'] ?? array()
            as $aCheck
        ) {
            $sStatus = strtoupper(
                (string)(
                    $aCheck['status']
                    ?? 'unknown'
                )
            );

            $s .= '<tr>'
                . '<td><strong>'
                . $this->esc(
                    $aCheck['label']
                    ?? $aCheck['key']
                    ?? ''
                )
                . '</strong></td>'
                . '<td>'
                . $this->esc($sStatus)
                . '</td>'
                . '<td>'
                . $this->esc(
                    $aCheck['detail']
                    ?? ''
                )
                . '</td>'
                . '</tr>';
        }

        $s .= '</tbody></table></div>';

        if (!empty(
            $aPreflight['cron_pause_target']
        )) {
            $c =
                $aPreflight[
                    'cron_pause_target'
                ];

            $s .= '<h3 style="margin-top:20px">'
                . 'Verified Future Cron Pause Target'
                . '</h3>';

            $s .= '<div class="mud-note">'
                . '<strong>ID:</strong> '
                . (int)$c['id']
                . '<br><strong>Name:</strong> '
                . $this->esc($c['name'])
                . '<br><strong>Schedule:</strong> '
                . $this->esc($c['time'])
                . '<br><strong>Class:</strong> '
                . $this->esc($c['class'])
                . '<br><strong>File:</strong> '
                . $this->esc($c['file'])
                . '<br><br>'
                . 'Read-only preflight does not alter this cron row. '
                . 'The verified generator can be paused only by an explicitly approved Arm Repair.'
                . '</div>';
        }

        if (!empty($aPreflight['storage'])) {
            $st = $aPreflight['storage'];

            $s .= '<h3 style="margin-top:20px">'
                . 'Storage'
                . '</h3>';

            $s .= '<p>'
                . '<strong>Runtime size:</strong> '
                . $this->esc(
                    $this->formatBytes(
                        (int)(
                            $st['runtime_bytes']
                            ?? 0
                        )
                    )
                )
                . '<br>'
                . '<strong>Destination free:</strong> '
                . $this->esc(
                    $this->formatBytes(
                        (int)(
                            $st[
                                'destination_free_bytes'
                            ]
                            ?? 0
                        )
                    )
                )
                . '<br>'
                . '<strong>Same filesystem:</strong> '
                . (
                    !empty(
                        $st['same_filesystem']
                    )
                        ? 'YES'
                        : 'NO'
                )
                . '</p>';
        }

        $s .= '<div class="mud-note">'
            . '<strong>Apply Repair remains disabled.</strong> '
            . 'Preflight performs no cron mutation, file movement, database mutation, '
            . 'module update or checksum re-baselining.'
            . '</div>';

        $s .= '</div>';

        return $s;
    }

    protected function renderCategoryPage($sTitle, array $aCategories)
    {
        $aScan = $this->oDoctor->getLatestHealthScan();
        $s = $this->css() . '<div class="mud-wrap"><div class="mud-section"><h1>' . $this->esc($sTitle) . '</h1>';
        if (empty($aScan))
            return $s . '<div class="mud-empty">Run a health scan from Overview first.</div></div></div>';

        $aItems = array();
        foreach ($aScan['items'] as $aItem) {
            if (in_array($aItem['category'], $aCategories, true))
                $aItems[] = $aItem;
        }
        $s .= $this->renderItems($aItems);
        $s .= '</div></div>';
        return $s;
    }

    protected function summaryCards(array $aSummary, array $aScan)
    {
        $iScore = (int)$aSummary['health_score'];
        $sClass = $iScore >= 90 ? 'good' : ($iScore >= 70 ? 'warn' : 'bad');
        $s = '<div class="mud-grid">';
        $s .= '<div class="mud-card score ' . $sClass . '"><span>UNA Health</span><strong>' . $iScore . '%</strong></div>';
        $s .= '<div class="mud-card"><span>Critical</span><strong>' . (int)$aSummary['critical'] . '</strong></div>';
        $s .= '<div class="mud-card"><span>Warnings</span><strong>' . (int)$aSummary['warning'] . '</strong></div>';
        $s .= '<div class="mud-card"><span>Checks</span><strong>' . (int)$aSummary['checks'] . '</strong></div>';
        $s .= '</div>';
        $s .= '<div class="mud-meta">Completed ' . $this->esc(date('Y-m-d H:i:s', (int)$aScan['finished'])) . ' • ' . (int)$aScan['duration_ms'] . ' ms • My UNA Doctor ' . $this->esc($aScan['module_version']) . '</div>';
        return $s;
    }

    protected function renderItems(array $aItems)
    {
        if (empty($aItems))
            return '<div class="mud-empty">No findings in this section.</div>';

        $aOrder = array('critical' => 0, 'warning' => 1, 'info' => 2, 'ok' => 3);
        usort($aItems, function ($a, $b) use ($aOrder) {
            return $aOrder[$a['severity']] <=> $aOrder[$b['severity']];
        });

        $s = '<div class="mud-findings">';
        foreach ($aItems as $aItem) {
            $sSeverity = $aItem['severity'];
            $sIcon = $sSeverity === 'critical' ? 'exclamation-triangle' : ($sSeverity === 'warning' ? 'exclamation-circle' : ($sSeverity === 'ok' ? 'check-circle' : 'info-circle'));
            $s .= '<div class="mud-finding ' . $this->esc($sSeverity) . '">';
            $s .= '<div class="mud-sev"><i class="sys-icon ' . $sIcon . '"></i></div><div class="mud-find-body">';
            $s .= '<div class="mud-find-head"><strong>' . $this->esc($aItem['title']) . '</strong><span>' . $this->esc(strtoupper($sSeverity)) . '</span></div>';
            if ($aItem['detail'] !== '')
                $s .= '<p>' . nl2br($this->esc($aItem['detail'])) . '</p>';
            if (!empty($aItem['evidence']))
                $s .= $this->renderEvidence($aItem['evidence'], $sSeverity);
            $s .= '</div></div>';
        }
        return $s . '</div>';
    }


    protected function renderEvidence(array $aEvidence, $sSeverity)
    {
        if (empty($aEvidence))
            return '';

        $s = '';

        if (
            isset($aEvidence['human']) &&
            is_array($aEvidence['human'])
        ) {
            $aHuman = $aEvidence['human'];

            if (!empty($aHuman['summary'])) {
                $s .= '<div class="mud-human-summary"><strong>'
                    . $this->esc($aHuman['summary'])
                    . '</strong></div>';
            }

            if (
                !empty($aHuman['sections']) &&
                is_array($aHuman['sections'])
            ) {
                foreach ($aHuman['sections'] as $aSection) {
                    if (!is_array($aSection))
                        continue;

                    if (!empty($aSection['title'])) {
                        $s .= '<h4>'
                            . $this->esc($aSection['title'])
                            . '</h4>';
                    }

                    if (
                        !empty($aSection['items']) &&
                        is_array($aSection['items'])
                    ) {
                        $s .= '<ul>';

                        foreach ($aSection['items'] as $sItem)
                            $s .= '<li>'
                                . $this->esc($sItem)
                                . '</li>';

                        $s .= '</ul>';
                    }
                }
            }

            if (!empty($aHuman['health_impact'])) {
                $s .= '<div><strong>Health impact:</strong> '
                    . $this->esc(
                        $aHuman['health_impact']
                    )
                    . '</div>';
            }

            if (!empty($aHuman['repair_required'])) {
                $s .= '<div><strong>Repair required:</strong> '
                    . $this->esc(
                        $aHuman['repair_required']
                    )
                    . '</div>';
            }
        }

        $aTechnical =
            $this->meaningfulTechnicalEvidence($aEvidence);

        if (!empty($aTechnical)) {
            $sJson = json_encode(
                $aTechnical,
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_SLASHES |
                JSON_UNESCAPED_UNICODE
            );

            if ($sJson !== false && $sJson !== '') {
                $s .= '<details class="mud-evidence">'
                    . '<summary>Technical evidence</summary>'
                    . '<pre>'
                    . $this->esc($sJson)
                    . '</pre></details>';
            }
        }

        return $s;
    }


    protected function meaningfulTechnicalEvidence(array $aEvidence)
    {
        unset($aEvidence['human']);

        $filter = function($value) use (&$filter) {
            if (is_array($value)) {
                $result = array();

                foreach ($value as $key => $item) {
                    $filtered = $filter($item);

                    if (
                        $filtered === null ||
                        $filtered === array()
                    )
                        continue;

                    $result[$key] = $filtered;
                }

                return $result;
            }

            if ($value === null)
                return null;

            if (is_string($value)) {
                $trimmed = trim($value);

                if ($trimmed === '')
                    return null;

                if (in_array(
                    strtolower($trimmed),
                    array(
                        'n/a',
                        'not applicable',
                        'placeholder',
                        '{}',
                        '[]'
                    ),
                    true
                ))
                    return null;

                return $trimmed;
            }

            /*
             * Keep 0 and false: both can be meaningful evidence.
             */
            return $value;
        };

        $result = $filter($aEvidence);

        return is_array($result)
            ? $result
            : array();
    }

    protected function css()
    {
        return '<style>
        .mud-wrap{max-width:1400px;margin:0 auto;padding:8px 4px 32px}.mud-hero{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:26px;border:1px solid rgba(128,128,128,.25);border-radius:16px;background:rgba(128,128,128,.06);margin-bottom:18px}.mud-hero h1,.mud-section h1{margin:0 0 6px;font-size:28px}.mud-hero p{margin:0;opacity:.72}.mud-run{white-space:nowrap}.mud-grid{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:12px;margin-bottom:10px}.mud-card{padding:18px;border:1px solid rgba(128,128,128,.25);border-radius:14px;background:rgba(128,128,128,.04)}.mud-card span{display:block;font-size:13px;opacity:.68;margin-bottom:8px}.mud-card strong{font-size:30px}.mud-card.score.good{box-shadow:inset 4px 0 0 #239b56}.mud-card.score.warn{box-shadow:inset 4px 0 0 #d4a017}.mud-card.score.bad{box-shadow:inset 4px 0 0 #c0392b}.mud-meta{font-size:12px;opacity:.62;margin:0 2px 20px}.mud-section{padding:22px;border:1px solid rgba(128,128,128,.22);border-radius:14px;background:rgba(128,128,128,.025)}.mud-section h2{margin:0 0 14px}.mud-findings{display:flex;flex-direction:column;gap:10px}.mud-finding{display:flex;gap:12px;padding:14px;border:1px solid rgba(128,128,128,.2);border-radius:12px}.mud-finding.critical{box-shadow:inset 4px 0 0 #c0392b}.mud-finding.warning{box-shadow:inset 4px 0 0 #d4a017}.mud-finding.info{box-shadow:inset 4px 0 0 #2980b9}.mud-finding.ok{box-shadow:inset 4px 0 0 #239b56}.mud-sev{width:28px;padding-top:2px;font-size:18px}.mud-find-body{min-width:0;flex:1}.mud-find-head{display:flex;justify-content:space-between;gap:16px}.mud-find-head span{font-size:11px;font-weight:700;opacity:.58}.mud-find-body p{margin:7px 0 0;opacity:.82}.mud-human-evidence{margin-top:10px;padding:12px 14px;border-radius:10px;background:rgba(128,128,128,.07)}.mud-human-summary{font-weight:600;margin-bottom:8px}.mud-human-title{display:block;margin:10px 0 4px}.mud-human-evidence ul{margin:4px 0 8px 20px;padding:0}.mud-human-evidence li{margin:3px 0}.mud-human-flags{display:grid;grid-template-columns:1fr;gap:5px;margin-top:10px;font-size:12px;opacity:.86}.mud-human-flag{display:block;line-height:1.45}.mud-find-body details{margin-top:9px}.mud-find-body pre{overflow:auto;max-height:260px;padding:10px;border-radius:8px;background:rgba(0,0,0,.08);font-size:11px;color:inherit;white-space:pre-wrap;word-break:break-word}.mud-empty{padding:24px;border:1px dashed rgba(128,128,128,.38);border-radius:12px;opacity:.75}.mud-note{padding:16px;border-radius:10px;background:rgba(41,128,185,.1);margin:14px 0 22px}.mud-table-wrap{overflow:auto}.mud-table{width:100%;border-collapse:collapse}.mud-table th,.mud-table td{text-align:left;padding:10px 12px;border-bottom:1px solid rgba(128,128,128,.2)}.mud-table th{font-size:12px;opacity:.65}@media(max-width:850px){.mud-grid{grid-template-columns:repeat(2,1fr)}.mud-hero{align-items:flex-start;flex-direction:column}}@media(max-width:520px){.mud-grid{grid-template-columns:1fr}}
        </style>';
    }


    protected function formatBytes($iBytes)
    {
        $fBytes = max(0, (float)$iBytes);

        $aUnits = array(
            'B',
            'KB',
            'MB',
            'GB',
            'TB'
        );

        $iUnit = 0;

        while (
            $fBytes >= 1024 &&
            $iUnit < count($aUnits) - 1
        ) {
            $fBytes /= 1024;
            $iUnit++;
        }

        return number_format(
            $fBytes,
            $iUnit === 0 ? 0 : 2
        )
            . ' '
            . $aUnits[$iUnit];
    }

    protected function esc($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}
