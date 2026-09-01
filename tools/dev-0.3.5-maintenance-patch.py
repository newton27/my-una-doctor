#!/usr/bin/env python3
from pathlib import Path
from datetime import datetime
import shutil
import sys

ROOT = Path(__file__).resolve().parents[1]
MODULE = ROOT / "classes" / "MyUnaDoctorModule.php"
CONFIG = ROOT / "install" / "config.php"
HELPER = ROOT / "classes" / "MyUnaDoctorMaintenance.php"

if not MODULE.is_file() or not CONFIG.is_file() or not HELPER.is_file():
    raise SystemExit("SAFETY STOP: expected 0.3.5 development files are missing.")

text = MODULE.read_text()

if "MyUnaDoctorMaintenance::buildPage" in text:
    print("0.3.5 maintenance integration already present; no Module.php changes made.")
    sys.exit(0)

stamp = datetime.now().strftime("%Y%m%d-%H%M%S")
backup = Path("/tmp") / f"my-una-doctor-0.3.5-{stamp}"
backup.mkdir(parents=True, exist_ok=False)
shutil.copy2(MODULE, backup / MODULE.name)
shutil.copy2(CONFIG, backup / CONFIG.name)
print(f"Backup: {backup}")


def replace_once(source: str, old: str, new: str, label: str) -> str:
    count = source.count(old)
    if count != 1:
        raise SystemExit(
            f"SAFETY STOP [{label}]: expected exactly one anchor, found {count}. "
            f"No patched file has been written. Backup: {backup}"
        )
    return source.replace(old, new, 1)

# 1. Arm Plan: require and snapshot UNA's soft-maintenance HTML option.
old = """        $sSoftMaintenance =
            (string)getParam('sys_maintenance_mode');

        $sTemplateCompression =
"""
new = """        $sSoftMaintenance =
            (string)getParam('sys_maintenance_mode');

        $iMaintenanceHtmlOptions =
            (int)$this->_oDb->getOne(
                \"SELECT COUNT(*)\n                 FROM `sys_options`\n                 WHERE `name` = ?\",
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
"""
text = replace_once(text, old, new, "arm-plan-maintenance-html-option")

old = """            'soft_maintenance_enabled' =>
                !empty($sSoftMaintenance),

            'template_cache_compress_value' =>
"""
new = """            'soft_maintenance_enabled' =>
                !empty($sSoftMaintenance),

            'soft_maintenance_html_value' =>
                $sSoftMaintenanceHtml,

            'soft_maintenance_html_sha256' =>
                hash(
                    'sha256',
                    $sSoftMaintenanceHtml
                ),

            'template_cache_compress_value' =>
"""
text = replace_once(text, old, new, "arm-plan-original-html")

text = replace_once(
    text,
    """            'Preserve the exact original maintenance and template-compression state.',
            'Disable UNA template cache compression so Studio CSS/JS use direct cache_public URLs.',
            'Verify the template-compression repair state before enabling maintenance.',
            'Enable UNA soft maintenance only if it is currently off.',
""",
    """            'Preserve the exact original maintenance HTML, maintenance switch and template-compression state.',
            'Disable UNA template cache compression so Studio CSS/JS use direct cache_public URLs.',
            'Verify the template-compression repair state before enabling maintenance.',
            'If Doctor is activating maintenance, stage the self-contained My UNA Doctor landing page with a live countdown.',
            'Enable UNA soft maintenance only if it is currently off.',
""",
    "arm-plan-steps",
)

text = replace_once(
    text,
    """            'Restore the exact original soft-maintenance value.',
            'Restore the exact original template-cache compression value.',
""",
    """            'Restore the exact original soft-maintenance value.',
            'Restore the exact original soft-maintenance HTML value.',
            'Restore the exact original template-cache compression value.',
""",
    "arm-plan-rollback",
)

old = """                . $sSoftMaintenance
                . '|'
                . $sTemplateCompression
"""
new = """                . $sSoftMaintenance
                . '|'
                . hash(
                    'sha256',
                    $sSoftMaintenanceHtml
                )
                . '|'
                . $sTemplateCompression
"""
text = replace_once(text, old, new, "arm-id-maintenance-html")

# 2. Rollback: restore the exact previous maintenance HTML whenever maintenance state is restored.
old = """            'cron_restored' => false,
            'maintenance_restored' => false,
            'compression_restored' => false,
"""
new = """            'cron_restored' => false,
            'maintenance_restored' => false,
            'maintenance_html_restored' => false,
            'compression_restored' => false,
"""
text = replace_once(text, old, new, "rollback-result-html")

anchor = """        /*
         * Restore compression only when this journal actually
         * recorded the original value. This keeps rollback
"""
insert = """        /*
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
                    \"SELECT `value`\n                     FROM `sys_options`\n                     WHERE `name` = ?\n                     LIMIT 1\",
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

""" + anchor
text = replace_once(text, anchor, insert, "rollback-maintenance-html")

# 3. Arm: require the freshly approved original HTML state.
old = """        $sOriginalMaintenance =
            (string)(
                $aOriginalState[
                    'soft_maintenance_value'
                ]
                ?? ''
            );

        if (
            !array_key_exists(
                'template_cache_compress_value',
"""
new = """        $sOriginalMaintenance =
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
"""
text = replace_once(text, old, new, "arm-original-html-required")

# 4. Re-read and compare the maintenance HTML before creating the journal.
old = """        if ($sLiveMaintenance !== $sOriginalMaintenance) {
            $aResult['error'] =
                'Soft-maintenance state changed after approval.';

            return $aResult;
        }

        $sLiveCompression =
"""
new = """        if ($sLiveMaintenance !== $sOriginalMaintenance) {
            $aResult['error'] =
                'Soft-maintenance state changed after approval.';

            return $aResult;
        }

        $sLiveMaintenanceHtml =
            (string)$this->_oDb->getOne(
                \"SELECT `value`\n                 FROM `sys_options`\n                 WHERE `name` = ?\n                 LIMIT 1\",
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
"""
text = replace_once(text, old, new, "arm-live-html-compare")

# 5. Build the deterministic landing target before durable journal creation.
old = """        $aPlannedState = array(
            'template_cache_compress_target' =>
                '',

            'soft_maintenance_target' =>
"""
new = """        $iMaintenanceEstimateSeconds =
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
                    'id=\"mud-maintenance\"'
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
"""
text = replace_once(text, old, new, "arm-build-landing")

old = """            'soft_maintenance_target' =>
                $sOriginalMaintenance !== ''
                    ? $sOriginalMaintenance
                    : 'on',

            'generator' => array(
"""
new = """            'soft_maintenance_target' =>
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
"""
text = replace_once(text, old, new, "planned-maintenance-landing")

old = """        $bCompressionMutationAttempted = false;
        $bMaintenanceMutationAttempted = false;
        $bCronMutationAttempted = false;
"""
new = """        $bCompressionMutationAttempted = false;
        $bMaintenanceHtmlMutationAttempted = false;
        $bMaintenanceMutationAttempted = false;
        $bCronMutationAttempted = false;
"""
text = replace_once(text, old, new, "mutation-flags-html")

# 6. Stage the self-contained page after compression is safe and before maintenance is enabled.
anchor = """            /*
             * Enable soft maintenance only if it was originally
             * disabled. Preserve any existing non-empty value.
             */
"""
insert = """            /*
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
                    \"SELECT `value`\n                     FROM `sys_options`\n                     WHERE `name` = ?\n                     LIMIT 1\",
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

""" + anchor
text = replace_once(text, anchor, insert, "stage-maintenance-landing")

old = """                            'maintenance_value' =>
                                $sVerifyMaintenance,
                            'changes_applied' =>
                                $bMaintenanceMutationAttempted,
"""
new = """                            'maintenance_value' =>
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
"""
text = replace_once(text, old, new, "maintenance-enabled-result")

# 7. Final arm verification includes exact maintenance HTML.
old = """            $sFinalCompression =
                (string)$this->_oDb->getOne(
"""
new = """            $sFinalMaintenanceHtml =
                (string)$this->_oDb->getOne(
                    \"SELECT `value`\n                     FROM `sys_options`\n                     WHERE `name` = ?\n                     LIMIT 1\",
                    array(
                        'sys_maintenance_mode_html'
                    )
                );

            $sFinalCompression =
                (string)$this->_oDb->getOne(
"""
text = replace_once(text, old, new, "final-read-maintenance-html")

old = """                $sFinalCompression !== ''
                || $sFinalMaintenance
                    !== $sExpectedMaintenance
                || empty($aFinalCron)
"""
new = """                $sFinalCompression !== ''
                || $sFinalMaintenance
                    !== $sExpectedMaintenance
                || $sFinalMaintenanceHtml
                    !== $sExpectedMaintenanceHtml
                || empty($aFinalCron)
"""
text = replace_once(text, old, new, "final-verify-maintenance-html")

old = """                'maintenance_value' =>
                    $sFinalMaintenance,
                'compression_value' =>
                    $sFinalCompression,
"""
new = """                'maintenance_value' =>
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
"""
text = replace_once(text, old, new, "armed-result-maintenance-html")

# 8. Deliberate restore: independently verify the original HTML was restored.
old = """            $sExpectedOriginalCompression = '';

            $sVerifyCompression = '';
"""
new = """            $sExpectedOriginalMaintenanceHtml =
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
                        \"SELECT `value`\n                         FROM `sys_options`\n                         WHERE `name` = ?\n                         LIMIT 1\",
                        array(
                            'sys_maintenance_mode_html'
                        )
                    )
                    : null;

            $sExpectedOriginalCompression = '';

            $sVerifyCompression = '';
"""
text = replace_once(text, old, new, "restore-read-original-html")

old = """                $sVerifyMaintenance
                    === $sExpectedOriginalMaintenance
                && (
                    !$bHasCompressionState
"""
new = """                $sVerifyMaintenance
                    === $sExpectedOriginalMaintenance
                && (
                    $sExpectedOriginalMaintenanceHtml === null
                    || $sVerifyMaintenanceHtml
                        === $sExpectedOriginalMaintenanceHtml
                )
                && (
                    !$bHasCompressionState
"""
text = replace_once(text, old, new, "restore-final-html-verify")

old = """                'maintenance_value' =>
                    $sVerifyMaintenance,
                'compression_value' =>
"""
new = """                'maintenance_value' =>
                    $sVerifyMaintenance,
                'maintenance_html_sha256' =>
                    $sVerifyMaintenanceHtml === null
                        ? null
                        : hash(
                            'sha256',
                            $sVerifyMaintenanceHtml
                        ),
                'compression_value' =>
"""
text = replace_once(text, old, new, "restored-result-html")

# Write Module.php only after every semantic edit succeeded.
MODULE.write_text(text)

config = CONFIG.read_text()
if "'version' => '0.3.5'" not in config:
    config = replace_once(
        config,
        "'version' => '0.3.4'",
        "'version' => '0.3.5'",
        "config-version",
    )
    CONFIG.write_text(config)

print("Patched:")
print(f"  {MODULE}")
print(f"  {CONFIG}")
print("Next: run PHP lint before committing or deploying anything.")
