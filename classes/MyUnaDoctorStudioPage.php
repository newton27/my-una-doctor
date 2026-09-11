<?php defined('BX_DOL') or die('hack attempt');

class MyUnaDoctorStudioPage extends BxTemplStudioModule
{
    protected $oDoctor;

    public function __construct($sModule, $mixedPageName, $sPage = '')
    {
        parent::__construct($sModule, $mixedPageName, $sPage);

        $this->sPageDefault = 'settings';
        if ($sPage === '')
            $this->sPage = 'settings';

        $this->aMenuItems = array(
            'settings' => array('name' => 'settings', 'icon' => 'cogs', 'title' => '_my_una_doctor_settings'),
            'overview' => array('name' => 'overview', 'icon' => 'heartbeat', 'title' => '_my_una_doctor_overview'),
            'system' => array('name' => 'system', 'icon' => 'server', 'title' => '_my_una_doctor_system'),
            'module_health' => array('name' => 'module_health', 'icon' => 'puzzle-piece', 'title' => '_my_una_doctor_modules'),
            'file_integrity' => array('name' => 'file_integrity', 'icon' => 'shield-alt', 'title' => 'File Integrity'),
            'reference_packages' => array('name' => 'reference_packages', 'icon' => 'archive', 'title' => 'Module Reference Library'),
            'local_repair_knowledge' => array('name' => 'local_repair_knowledge', 'icon' => 'book', 'title' => 'Local Repair Knowledge'),
            'repair_candidates' => array('name' => 'repair_candidates', 'icon' => 'tools', 'title' => 'Repair Candidates'),
            'database' => array('name' => 'database', 'icon' => 'database', 'title' => '_my_una_doctor_database'),
            'media_health' => array('name' => 'media_health', 'icon' => 'images', 'title' => 'Storage & Media'),
            'logs' => array('name' => 'logs', 'icon' => 'file-alt', 'title' => '_my_una_doctor_logs'),
            'history' => array('name' => 'history', 'icon' => 'history', 'title' => '_my_una_doctor_history'),
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



    public function getReferencePackages()
    {
        $aComparison = null;
        $aKnowledgeSave = null;

        /*
         * Persistent Reference Library is read-only at this
         * stage. Every row returned here has already passed
         * the library backend's path, ZIP SHA-256 and immutable
         * manifest verification.
         */
        $aLibraryRows =
            $this->oDoctor
                ->getModuleReferenceLibrary();

        $aLibrarySummary =
            $this->oDoctor
                ->getModuleReferenceLibrarySummary();


        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && ($_POST['mud_action'] ?? '')
                === 'compare_reference_package'
        ) {
            if (!isAdmin()) {
                $aComparison = array(
                    'ok' => false,
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
                    $aComparison = array(
                        'ok' => false,
                        'changes_applied' => false,
                        'error' =>
                            'CSRF validation failed or the upload token expired.',
                    );
                } elseif (
                    !isset($_FILES['reference_zip'])
                    || !is_array(
                        $_FILES['reference_zip']
                    )
                ) {
                    $aComparison = array(
                        'ok' => false,
                        'changes_applied' => false,
                        'error' =>
                            'Select a module ZIP package to compare.',
                    );
                } else {
                    $aComparison =
                        $this->oDoctor
                            ->compareReferencePackageUpload(
                                $_FILES[
                                    'reference_zip'
                                ]
                            );

                    /*
                     * Fresh upload may have changed the
                     * Reference Library catalog.
                     */
                    if (
                        is_array($aComparison)
                        && !empty($aComparison['ok'])
                    ) {
                        $aLibraryRows =
                            $this->oDoctor
                                ->getModuleReferenceLibrary();

                        $aLibrarySummary =
                            $this->oDoctor
                                ->getModuleReferenceLibrarySummary();
                    }

                }
            }
        }

        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && ($_POST['mud_action'] ?? '')
                === 'compare_saved_reference'
        ) {
            if (!isAdmin()) {
                $aComparison = array(
                    'ok' => false,
                    'changes_applied' => false,
                    'error' =>
                        'Administrator authorization is required.',
                );
            } else {
                $sPostedCsrf =
                    (string)(
                        $_POST['csrf_token']
                        ?? ''
                    );

                if (
                    !BxDolForm::isCsrfTokenValid(
                        $sPostedCsrf
                    )
                ) {
                    $aComparison = array(
                        'ok' => false,
                        'changes_applied' => false,
                        'error' =>
                            'CSRF validation failed or the saved-reference token expired.',
                    );
                } else {
                    $sReferenceSha256 =
                        strtolower(
                            trim(
                                (string)(
                                    $_POST[
                                        'reference_sha256'
                                    ]
                                    ?? ''
                                )
                            )
                        );

                    $aComparison =
                        $this->oDoctor
                            ->compareSavedModuleReference(
                                $sReferenceSha256
                            );

                    /*
                     * A successful comparison updates the
                     * Reference Library history, so reload the
                     * cards before rendering this response.
                     */
                    $aLibraryRows =
                        $this->oDoctor
                            ->getModuleReferenceLibrary();

                    $aLibrarySummary =
                        $this->oDoctor
                            ->getModuleReferenceLibrarySummary();
                }
            }
        }


        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && ($_POST['mud_action'] ?? '')
                === 'save_local_repair_knowledge'
        ) {
            if (!isAdmin()) {
                $aKnowledgeSave = array(
                    'ok' => false,
                    'saved' => false,
                    'error' =>
                        'Administrator authorization is required.',
                );
            } else {
                $sCsrfToken =
                    (string)(
                        $_POST['csrf_token']
                        ?? ''
                    );

                if (
                    !BxDolForm::isCsrfTokenValid(
                        $sCsrfToken
                    )
                ) {
                    $aKnowledgeSave = array(
                        'ok' => false,
                        'saved' => false,
                        'error' =>
                            'CSRF validation failed or the Local Repair Knowledge token expired.',
                    );
                } else {
                    $aKnowledgeSave =
                        $this->oDoctor
                            ->saveLocalRepairKnowledge(
                                array(
                                    'module' =>
                                        (string)(
                                            $_POST[
                                                'knowledge_module'
                                            ] ?? ''
                                        ),
                                    'module_version' =>
                                        (string)(
                                            $_POST[
                                                'knowledge_version'
                                            ] ?? ''
                                        ),
                                    'file' =>
                                        (string)(
                                            $_POST[
                                                'knowledge_file'
                                            ] ?? ''
                                        ),
                                    'reference_md5' =>
                                        (string)(
                                            $_POST[
                                                'knowledge_reference_md5'
                                            ] ?? ''
                                        ),
                                    'approved_live_md5' =>
                                        (string)(
                                            $_POST[
                                                'knowledge_live_md5'
                                            ] ?? ''
                                        ),
                                    'repair_type' =>
                                        (string)(
                                            $_POST[
                                                'knowledge_type'
                                            ] ?? ''
                                        ),
                                    'reason' =>
                                        (string)(
                                            $_POST[
                                                'knowledge_reason'
                                            ] ?? ''
                                        ),
                                    'reference_sha256' =>
                                        (string)(
                                            $_POST[
                                                'knowledge_reference_sha256'
                                            ] ?? ''
                                        ),
                                    'reference_filename' =>
                                        (string)(
                                            $_POST[
                                                'knowledge_reference_filename'
                                            ] ?? ''
                                        ),
                                    'comparison_session_id' =>
                                        (string)(
                                            $_POST[
                                                'knowledge_session_id'
                                            ] ?? ''
                                        ),
                                )
                            );
                }
            }
        }


        /*
         * Generate the next token only after any submitted token
         * has been validated and consumed.
         */
        $sCsrfToken =
            BxDolForm::getCsrfToken();

        if ($sCsrfToken === false)
            $sCsrfToken = '';

        $sAction =
            BX_DOL_URL_STUDIO
            . 'module.php?name=my_una_doctor'
            . '&page=reference_packages';

        $s = $this->css() . $this->referencePackageCss();

        $s .= '<div class="mud-ref">';

        $s .=
            '<div class="mud-ref-hero">'
            . '<h2><span style="font-size:1.35em;font-weight:800;letter-spacing:.035em;">MODULE</span> Reference Library</h2>'
            . '<p>Upload an original, clean, or updated UNA module ZIP. '
            . 'Doctor will compare the package with the installed module '
            . 'without replacing or modifying live module files.</p>'
            . '</div>';

        $s .=
            '<div class="mud-ref-notice">'
            . '<strong>Comparison only.</strong> '
            . 'Uploading a module reference package does not install, update, '
            . 'overwrite, re-baseline, enable, disable, or repair the module.'
            . '</div>';

        if (is_array($aKnowledgeSave)) {
            $s .=
                $this->renderLocalRepairKnowledgeSaveResult(
                    $aKnowledgeSave
                );
        }



        $s .=
            '<div class="mud-ref-card">'
            . '<h3>Upload Module Reference ZIP</h3>'
            . '<form method="post" enctype="multipart/form-data" action="'
            . $this->esc($sAction)
            . '">'
            . '<input type="hidden" name="mud_action" '
            . 'value="compare_reference_package">'
            . '<input type="hidden" name="csrf_token" value="'
            . $this->esc((string)$sCsrfToken)
            . '">'
            . '<div class="mud-ref-upload-row">'
            . '<input '
            . 'id="mud-reference-zip" '
            . 'class="mud-ref-native-file" '
            . 'type="file" '
            . 'name="reference_zip" '
            . 'accept=".zip,application/zip" '
            . 'required>'
            . '<label '
            . 'id="mud-reference-picker" '
            . 'class="mud-ref-file-picker" '
            . 'for="mud-reference-zip" '
            . 'role="button" '
            . 'tabindex="0">'
            . 'Choose ZIP'
            . '</label>'
            . '<span '
            . 'id="mud-reference-file-name" '
            . 'class="mud-ref-file-name">'
            . 'No ZIP selected'
            . '</span>'
            . '<button '
            . 'id="mud-reference-submit" '
            . 'type="submit" '
            . 'class="bx-btn bx-btn-primary" '
            . 'disabled>'
            . 'Compare Reference ZIP'
            . '</button>'
            . '</div>'
            . '<p class="mud-ref-help">'
            . 'Doctor inspects the ZIP directly. '
            . 'The package is not extracted into the live module directory.'
            . '</p>'
            . '</form>'
            . '</div>';

        $s .= $this->renderModuleReferenceLibrary(
            $aLibraryRows,
            $aLibrarySummary,
            $sAction,
            (string)$sCsrfToken
        );

        $s .= <<<'HTML'
<script>
(function () {
    var input = document.getElementById(
        'mud-reference-zip'
    );

    var picker = document.getElementById(
        'mud-reference-picker'
    );

    var fileName = document.getElementById(
        'mud-reference-file-name'
    );

    var submit = document.getElementById(
        'mud-reference-submit'
    );

    if (!input || !picker || !fileName || !submit)
        return;

    function syncReferenceFileState() {
        var selected =
            input.files && input.files.length > 0
                ? input.files[0]
                : null;

        if (selected) {
            fileName.textContent = selected.name;

            /*
             * Keep the browser's own title synchronized too,
             * even though the native input is off-screen.
             */
            input.setAttribute(
                'title',
                selected.name
            );

            submit.disabled = false;

            picker.classList.add(
                'mud-ref-file-picker-selected'
            );
        } else {
            fileName.textContent =
                'No ZIP selected';

            input.removeAttribute('title');

            submit.disabled = true;

            picker.classList.remove(
                'mud-ref-file-picker-selected'
            );
        }
    }

    input.addEventListener(
        'change',
        syncReferenceFileState
    );

    picker.addEventListener(
        'keydown',
        function (event) {
            if (
                event.key === 'Enter'
                || event.key === ' '
            ) {
                event.preventDefault();
                input.click();
            }
        }
    );

    syncReferenceFileState();
})();
</script>
HTML;

        if (is_array($aComparison)) {
            $s .=
                $this->renderReferencePackageComparison(
                    $aComparison,
                    (string)$sCsrfToken
                );
        }

        $s .= '</div>';

        return $s;
    }



    /**
     * Render saved immutable Module Reference Library items.
     *
     * UI stage #3 is deliberately read-only. There is no
     * action button capable of executing a comparison from a
     * stored ZIP yet.
     */
    protected function renderModuleReferenceLibrary(
        array $aRows,
        array $aSummary,
        $sAction,
        $sCsrfToken
    ) {
        $iTotal =
            (int)(
                $aSummary['total']
                ?? count($aRows)
            );

        $iVerified =
            (int)(
                $aSummary['verified']
                ?? 0
            );

        $iFailed =
            (int)(
                $aSummary['failed']
                ?? 0
            );

        $iModules =
            (int)(
                $aSummary['module_count']
                ?? 0
            );

        $s = '';

        $s .= <<<'HTML'
<style>
.mud-library {
    margin: 1.15rem 0 1.5rem 0;
}
.mud-library-head {
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:1rem;
    margin-bottom:.9rem;
}
.mud-library-head h3 {
    margin:0 0 .25rem 0;
}
.mud-library-head p {
    margin:0;
    opacity:.78;
    max-width:58rem;
}

.mud-library-view-options {
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:.5rem;
    margin:.75rem 0 .9rem 0;
}

.mud-library-view-label {
    font-weight:600;
}

.mud-library-text-size-control {
    display:inline-flex;
    align-items:center;
    gap:.45rem;
    padding:.3rem .55rem;
    border:1px solid rgba(127,127,127,.22);
    border-radius:.5rem;
    background:rgba(127,127,127,.055);
}

.mud-library-text-size-slider {
    width:130px;
    margin:0;
}

.mud-library-text-size-value {
    min-width:42px;
    text-align:center;
    font-weight:600;
}

.mud-library-summary,
.mud-library-grid,
.mud-library-empty {
    font-size:var(--mud-doctor-library-text-size, 100%);
}
.mud-library-summary {
    display:grid;
    grid-template-columns:
        repeat(auto-fit,minmax(130px,1fr));
    gap:.65rem;
    margin:.8rem 0 1rem 0;
}
.mud-library-summary-card {
    border:1px solid rgba(128,128,128,.24);
    border-radius:.8rem;
    padding:.75rem .85rem;
    background:rgba(127,127,127,.045);
}
.mud-library-summary-card small {
    display:block;
    opacity:.68;
    margin-bottom:.2rem;
}
.mud-library-summary-card strong {
    font-size:1.12em;
}
.mud-library-grid {
    display:grid;
    grid-template-columns:
        repeat(auto-fit,minmax(310px,1fr));
    gap:.85rem;
}
.mud-library-card {
    border:1px solid rgba(128,128,128,.28);
    border-radius:.9rem;
    padding:1rem;
    background:rgba(127,127,127,.035);
    overflow:hidden;
}
.mud-library-card-top {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:.8rem;
    margin-bottom:.8rem;
}
.mud-library-title {
    font-weight:750;
    font-size:1.05em;
}
.mud-library-subtitle {
    opacity:.68;
    margin-top:.15rem;
    font-size:.9em;
}
.mud-library-badge {
    display:inline-block;
    border-radius:999px;
    padding:.28rem .58rem;
    font-size:.78em;
    font-weight:700;
    white-space:nowrap;
}
.mud-library-badge-ok {
    background:rgba(33,150,83,.13);
    border:1px solid rgba(33,150,83,.35);
}
.mud-library-badge-warn {
    background:rgba(242,153,74,.13);
    border:1px solid rgba(242,153,74,.36);
}
.mud-library-badge-bad {
    background:rgba(235,87,87,.13);
    border:1px solid rgba(235,87,87,.36);
}
.mud-library-meta {
    display:grid;
    grid-template-columns:
        minmax(110px,.7fr) minmax(0,1.3fr);
    gap:.38rem .75rem;
    font-size:.9em;
}
.mud-library-meta-label {
    opacity:.68;
}
.mud-library-meta-value {
    min-width:0;
    overflow-wrap:anywhere;
}
.mud-library-sha {
    font-family:monospace;
    font-size:.78em;
}
.mud-library-actions {
    margin-top:.8rem;
    display:flex;
    gap:.65rem;
    align-items:center;
    flex-wrap:wrap;
}
.mud-library-actions form {
    margin:0;
}
.mud-library-action-note {
    font-size:.82em;
    opacity:.68;
}
.mud-library-note {
    margin-top:.8rem;
    padding:.65rem .75rem;
    border-radius:.65rem;
    background:rgba(127,127,127,.07);
    font-size:.88em;
}
.mud-library-empty {
    border:1px dashed rgba(128,128,128,.35);
    border-radius:.8rem;
    padding:1rem;
}
@media (max-width:700px) {
    .mud-library-card-top {
        display:block;
    }
    .mud-library-badge {
        margin-top:.5rem;
    }
    .mud-library-meta {
        grid-template-columns:1fr;
        gap:.12rem;
    }
    .mud-library-meta-value {
        margin-bottom:.45rem;
    }
}
</style>
HTML;

        $s .= '<section class="mud-library">';

        $s .=
            '<div class="mud-library-head">'
            . '<div>'
            . '<h3>Saved Reference Library</h3>'
            . '<p>Known-good or original module packages preserved by '
            . 'My UNA Doctor. Doctor verifies the saved ZIP and its '
            . 'immutable manifest before showing a reference as trusted.'
            . '</p>'
            . '</div>'
            . '</div>';

        $s .= <<<'HTML'
<div class="mud-library-view-options">
    <span class="mud-library-view-label">View Options:</span>

    <span class="mud-library-text-size-control">
        <span>Text Size</span>

        <input
            type="range"
            class="mud-library-text-size-slider"
            min="50"
            max="125"
            step="5"
            value="100"
            aria-label="Reference Library text size"
        >

        <output class="mud-library-text-size-value">100%</output>

        <button
            type="button"
            class="bx-btn mud-library-text-size-reset"
        >Reset</button>
    </span>
</div>

<script>
(function () {
    var storageKey =
        "myUnaDoctorReferenceLibraryTextSize";

    var library =
        document.querySelector(".mud-library");

    var slider =
        document.querySelector(
            ".mud-library-text-size-slider"
        );

    var value =
        document.querySelector(
            ".mud-library-text-size-value"
        );

    if (!library || !slider || !value)
        return;

    function applyTextSize(size, persist) {
        size = parseInt(size, 10);

        if (isNaN(size))
            size = 100;

        size = Math.max(
            50,
            Math.min(125, size)
        );

        library.style.setProperty(
            "--mud-doctor-library-text-size",
            size + "%"
        );

        slider.value = size;
        value.textContent = size + "%";

        if (persist) {
            try {
                localStorage.setItem(
                    storageKey,
                    String(size)
                );
            } catch (e) {}
        }
    }

    var savedSize = 100;

    try {
        var stored =
            localStorage.getItem(storageKey);

        if (stored !== null)
            savedSize = stored;
    } catch (e) {}

    applyTextSize(savedSize, false);

    slider.addEventListener(
        "input",
        function () {
            applyTextSize(
                this.value,
                true
            );
        }
    );

    document.addEventListener(
        "click",
        function (event) {
            if (
                event.target.closest(
                    ".mud-library-text-size-reset"
                )
            ) {
                applyTextSize(
                    100,
                    true
                );
            }
        }
    );
})();
</script>
HTML;

        $s .= '<div class="mud-library-summary">';

        $aSummaryCards = array(
            'Saved references' => $iTotal,
            'Library verified' => $iVerified,
            'Integrity failures' => $iFailed,
            'Modules covered' => $iModules,
        );

        foreach (
            $aSummaryCards
            as $sLabel => $mixedValue
        ) {
            $s .=
                '<div class="mud-library-summary-card">'
                . '<small>'
                . $this->esc($sLabel)
                . '</small>'
                . '<strong>'
                . $this->esc((string)$mixedValue)
                . '</strong>'
                . '</div>';
        }

        $s .= '</div>';

        if (empty($aRows)) {
            $s .=
                '<div class="mud-library-empty">'
                . '<strong>No saved module references yet.</strong>'
                . '<p>Upload a clean/original module ZIP below. '
                . 'After verification, Doctor can preserve it as '
                . 'Reference Library evidence.</p>'
                . '</div>';

            $s .= '</section>';

            return $s;
        }

        $s .= '<div class="mud-library-grid">';

        foreach ($aRows as $aRow) {
            $sModule =
                (string)(
                    $aRow['module_name']
                    ?? ''
                );

            $sTitle =
                trim(
                    (string)(
                        $aRow['module_title']
                        ?? ''
                    )
                );

            if ($sTitle === '')
                $sTitle = $sModule;

            $sVendor =
                trim(
                    (string)(
                        $aRow['vendor']
                        ?? ''
                    )
                );

            $sVersion =
                (string)(
                    $aRow[
                        'reference_version'
                    ]
                    ?? ''
                );

            $sFilename =
                (string)(
                    $aRow[
                        'original_filename'
                    ]
                    ?? ''
                );

            $sSha =
                strtolower(
                    (string)(
                        $aRow[
                            'reference_sha256'
                        ]
                        ?? ''
                    )
                );

            $iComparisons =
                (int)(
                    $aRow[
                        'comparison_count'
                    ]
                    ?? 0
                );

            $sFirst =
                (string)(
                    $aRow[
                        'first_seen_utc'
                    ]
                    ?? ''
                );

            $sLast =
                (string)(
                    $aRow[
                        'last_seen_utc'
                    ]
                    ?? ''
                );

            $sBaseline =
                strtolower(
                    trim(
                        (string)(
                            $aRow[
                                'baseline_authenticity'
                            ]
                            ?? ''
                        )
                    )
                );

            $bLibraryVerified =
                !empty(
                    $aRow[
                        'library_verified'
                    ]
                );

            if ($bLibraryVerified) {
                $sLibraryBadge =
                    '<span class="mud-library-badge '
                    . 'mud-library-badge-ok">'
                    . 'LIBRARY VERIFIED'
                    . '</span>';
            } else {
                $sLibraryBadge =
                    '<span class="mud-library-badge '
                    . 'mud-library-badge-bad">'
                    . 'INTEGRITY FAILURE'
                    . '</span>';
            }

            if ($sBaseline === 'verified') {
                $sBaselineLabel =
                    'VERIFIED';
                $sBaselineClass =
                    'mud-library-badge-ok';
            } elseif (
                $sBaseline === 'not_verified'
            ) {
                $sBaselineLabel =
                    'NOT VERIFIED';
                $sBaselineClass =
                    'mud-library-badge-warn';
            } else {
                $sBaselineLabel =
                    'NOT RECORDED';
                $sBaselineClass =
                    'mud-library-badge-warn';
            }

            $s .= '<article class="mud-library-card">';

            $s .=
                '<div class="mud-library-card-top">'
                . '<div>'
                . '<div class="mud-library-title">'
                . $this->esc($sTitle)
                . '</div>'
                . '<div class="mud-library-subtitle">'
                . $this->esc($sModule)
                . (
                    $sVersion !== ''
                        ? ' &middot; v'
                            . $this->esc($sVersion)
                        : ''
                )
                . '</div>'
                . '</div>'
                . $sLibraryBadge
                . '</div>';

            $s .= '<div class="mud-library-meta">';

            $aMeta = array(
                'Vendor' =>
                    $sVendor !== ''
                        ? $sVendor
                        : 'Not declared',

                'Original ZIP' =>
                    $sFilename !== ''
                        ? $sFilename
                        : 'reference.zip',

                'Comparisons' =>
                    (string)$iComparisons,

                'First preserved' =>
                    $sFirst !== ''
                        ? $sFirst . ' UTC'
                        : 'Unknown',

                'Last compared' =>
                    $sLast !== ''
                        ? $sLast . ' UTC'
                        : 'Unknown',
            );

            foreach ($aMeta as $sLabel => $sValue) {
                $s .=
                    '<div class="mud-library-meta-label">'
                    . $this->esc($sLabel)
                    . '</div>'
                    . '<div class="mud-library-meta-value">'
                    . $this->esc($sValue)
                    . '</div>';
            }

            $s .=
                '<div class="mud-library-meta-label">'
                . 'UNA baseline authenticity'
                . '</div>'
                . '<div class="mud-library-meta-value">'
                . '<span class="mud-library-badge '
                . $sBaselineClass
                . '">'
                . $this->esc($sBaselineLabel)
                . '</span>'
                . '</div>';

            $s .=
                '<div class="mud-library-meta-label">'
                . 'SHA-256'
                . '</div>'
                . '<div class="mud-library-meta-value '
                . 'mud-library-sha">'
                . $this->esc($sSha)
                . '</div>';

            $s .= '</div>';

            if (!$bLibraryVerified) {
                $aErrors =
                    isset(
                        $aRow['library_errors']
                    )
                    && is_array(
                        $aRow['library_errors']
                    )
                        ? $aRow[
                            'library_errors'
                        ]
                        : array();

                $s .=
                    '<div class="mud-library-note">'
                    . '<strong>Reference integrity problem.</strong>';

                foreach ($aErrors as $sError) {
                    $s .=
                        '<div>'
                        . $this->esc(
                            (string)$sError
                        )
                        . '</div>';
                }

                $s .= '</div>';
            } else {
                $s .=
                    '<div class="mud-library-note">'
                    . '<strong>Verified saved evidence.</strong> '
                    . 'Doctor will reverify this preserved package '
                    . 'before every comparison.'
                    . '</div>';

                $s .=
                    '<div class="mud-library-actions">'
                    . '<form method="post" action="'
                    . $this->esc(
                        (string)$sAction
                    )
                    . '">'
                    . '<input type="hidden" '
                    . 'name="mud_action" '
                    . 'value="compare_saved_reference">'
                    . '<input type="hidden" '
                    . 'name="csrf_token" value="'
                    . $this->esc(
                        (string)$sCsrfToken
                    )
                    . '">'
                    . '<input type="hidden" '
                    . 'name="reference_sha256" value="'
                    . $this->esc($sSha)
                    . '">'
                    . '<button type="submit" '
                    . 'class="bx-btn">'
                    . '<i class="sys-icon sync-alt"></i> '
                    . 'Compare Again'
                    . '</button>'
                    . '</form>'
                    . '<span class="mud-library-action-note">'
                    . 'Comparison only — no module files are changed.'
                    . '</span>'
                    . '</div>';
            }

            $s .= '</article>';
        }

        $s .= '</div>';
        $s .= '</section>';

        return $s;
    }


    protected function renderReferencePackageComparison(
        array $aResult,
        $sCsrfToken = ''
    ) {
        if (empty($aResult['ok'])) {
            return
                '<div class="mud-ref-card mud-ref-error">'
                . '<h3>Comparison stopped safely</h3>'
                . '<p>'
                . $this->esc(
                    isset($aResult['error'])
                        ? (string)$aResult['error']
                        : 'The reference package could not be compared.'
                )
                . '</p>'
                . '<p>No live module files were changed.</p>'
                . '</div>';
        }

        $aPackage =
            isset($aResult['package'])
            && is_array($aResult['package'])
                ? $aResult['package']
                : array();

        $aInstalled =
            isset($aResult['installed'])
            && is_array($aResult['installed'])
                ? $aResult['installed']
                : array();

        $aComparison =
            isset($aResult['comparison'])
            && is_array($aResult['comparison'])
                ? $aResult['comparison']
                : array();

        $aBaseline =
            isset($aResult['baseline'])
            && is_array($aResult['baseline'])
                ? $aResult['baseline']
                : array();

        $aEvidence =
            isset($aResult['evidence'])
            && is_array($aResult['evidence'])
                ? $aResult['evidence']
                : array();

        $sPackageVersion =
            isset($aPackage['version'])
                ? (string)$aPackage['version']
                : '';

        $sRegisteredVersion =
            isset($aInstalled['registered_version'])
                ? (string)$aInstalled[
                    'registered_version'
                ]
                : '';

        $sLiveVersion =
            isset($aInstalled['live_package_version'])
                ? (string)$aInstalled[
                    'live_package_version'
                ]
                : '';

        $bVersionDifferent =
            $sPackageVersion !== ''
            && (
                (
                    $sRegisteredVersion !== ''
                    && $sPackageVersion
                        !== $sRegisteredVersion
                )
                || (
                    $sLiveVersion !== ''
                    && $sPackageVersion
                        !== $sLiveVersion
                )
            );

        $iModified =
            isset($aComparison['modified_count'])
                ? (int)$aComparison['modified_count']
                : 0;

        $iBaselineBacked =
            isset(
                $aComparison[
                    'baseline_backed_modified_count'
                ]
            )
                ? (int)$aComparison[
                    'baseline_backed_modified_count'
                ]
                : 0;

        $iApprovedModified =
            isset(
                $aComparison[
                    'approved_modified_count'
                ]
            )
                ? (int)$aComparison[
                    'approved_modified_count'
                ]
                : 0;

        $iUnexplainedModified =
            isset(
                $aComparison[
                    'unexplained_modified_count'
                ]
            )
                ? (int)$aComparison[
                    'unexplained_modified_count'
                ]
                : $iModified;

        $iMissing =
            isset($aComparison['missing_count'])
                ? (int)$aComparison['missing_count']
                : 0;

        $iLiveOnly =
            isset($aComparison['live_only_count'])
                ? (int)$aComparison['live_only_count']
                : 0;

        $iRuntimeLiveOnly =
            isset(
                $aComparison[
                    'runtime_generated_live_only_count'
                ]
            )
                ? (int)$aComparison[
                    'runtime_generated_live_only_count'
                ]
                : 0;

        $iBaselineBackedLiveOnly =
            isset(
                $aComparison[
                    'baseline_backed_live_only_count'
                ]
            )
                ? (int)$aComparison[
                    'baseline_backed_live_only_count'
                ]
                : 0;

        $iUnexplainedLiveOnly =
            isset(
                $aComparison[
                    'unexplained_live_only_count'
                ]
            )
                ? (int)$aComparison[
                    'unexplained_live_only_count'
                ]
                : $iLiveOnly;

        $iMatching =
            isset($aComparison['matching_count'])
                ? (int)$aComparison['matching_count']
                : 0;

        $s = '';

        $s .=
            '<div class="mud-ref-card mud-ref-success">'
            . '<h3>Reference package inspected successfully</h3>'
            . '<p><strong>No live module files were changed.</strong> '
            . 'Doctor completed a read-only comparison and preserved '
            . 'the evidence under its protected temporary evidence area.</p>'
            . '</div>';

        $s .= '<div class="mud-ref-grid">';

        $s .=
            $this->referencePackageMetric(
                'Matching',
                $iMatching
            );

        $s .=
            $this->referencePackageMetric(
                'UNA baseline-backed',
                $iBaselineBacked
            );

        $s .=
            $this->referencePackageMetric(
                'Approved changes',
                $iApprovedModified
            );

        $s .=
            $this->referencePackageMetric(
                'Unexplained changes',
                $iUnexplainedModified
            );

        $s .=
            $this->referencePackageMetric(
                'Missing',
                $iMissing
            );

        $s .=
            $this->referencePackageMetric(
                'Live-only total',
                $iLiveOnly
            );

        $s .= '</div>';

        $s .=
            '<div class="mud-ref-card">'
            . '<h3>Live-only Classification</h3>'
            . '<div class="mud-ref-grid">'
            . $this->referencePackageMetric(
                'Runtime-generated',
                $iRuntimeLiveOnly
            )
            . $this->referencePackageMetric(
                'UNA baseline-backed',
                $iBaselineBackedLiveOnly
            )
            . $this->referencePackageMetric(
                'Unexplained',
                $iUnexplainedLiveOnly
            )
            . '</div>'
            . '<p class="mud-ref-help">'
            . 'Doctor preserves the complete raw live-only list in '
            . 'the comparison evidence. This classification separates '
            . 'known runtime content and UNA-tracked files from files '
            . 'that still require investigation.'
            . '</p>'
            . '</div>';


        $s .=
            '<div class="mud-ref-card">'
            . '<h3>Package Identity</h3>'
            . '<table class="mud-ref-table">'
            . '<tbody>'
            . $this->referencePackageRow(
                'Uploaded file',
                $aPackage['original_filename']
                    ?? ''
            )
            . $this->referencePackageRow(
                'Detected module',
                $aPackage['name']
                    ?? ''
            )
            . $this->referencePackageRow(
                'Vendor',
                $aPackage['vendor']
                    ?? ''
            )
            . $this->referencePackageRow(
                'Reference version',
                $sPackageVersion
            )
            . $this->referencePackageRow(
                'Registered version',
                $sRegisteredVersion
            )
            . $this->referencePackageRow(
                'Live package version',
                $sLiveVersion
            )
            . $this->referencePackageRow(
                'Installed path',
                $aInstalled['path']
                    ?? ''
            )
            . $this->referencePackageRow(
                'Reference files',
                $aComparison['reference_files']
                    ?? 0
            )
            . $this->referencePackageRow(
                'Live files',
                $aComparison['live_files']
                    ?? 0
            )
            . '</tbody>'
            . '</table>'
            . '</div>';

        if ($bVersionDifferent) {
            $s .=
                '<div class="mud-ref-card mud-ref-warning">'
                . '<h3>Package version differs</h3>'
                . '<p>The uploaded reference package does not have the '
                . 'same version as the currently registered/live package. '
                . 'That may be intentional when comparing a newer or older '
                . 'vendor release. Doctor has not changed either version.</p>'
                . '</div>';
        }


        if (!empty($aBaseline['available'])) {
            $bAuthentic =
                isset($aBaseline['authenticity'])
                && $aBaseline['authenticity']
                    === 'verified';

            $s .=
                '<div class="mud-ref-card '
                . ($bAuthentic
                    ? 'mud-ref-success'
                    : 'mud-ref-warning')
                . '">'
                . '<h3>Reference Package Authenticity: '
                . ($bAuthentic
                    ? 'VERIFIED'
                    : 'NOT VERIFIED')
                . '</h3>'
                . '<p>'
                . $this->esc(
                    isset(
                        $aBaseline[
                            'authenticity_reason'
                        ]
                    )
                        ? (string)$aBaseline[
                            'authenticity_reason'
                        ]
                        : ''
                )
                . '</p>'
                . '</div>';

            $s .=
                '<div class="mud-ref-card">'
                . '<h3>UNA Checksum Baseline Comparison</h3>'
                . '<div class="mud-ref-grid mud-ref-grid-small">'
                . $this->referencePackageMetric(
                    'Tracked',
                    $aBaseline[
                        'tracked_count'
                    ] ?? 0
                )
                . $this->referencePackageMetric(
                    'Baseline matches',
                    $aBaseline[
                        'matching_count'
                    ] ?? 0
                )
                . $this->referencePackageMetric(
                    'Baseline differences',
                    $aBaseline[
                        'different_count'
                    ] ?? 0
                )
                . $this->referencePackageMetric(
                    'Reference untracked',
                    $aBaseline[
                        'reference_untracked_count'
                    ] ?? 0
                )
                . '</div>'
                . '<p class="mud-ref-help">'
                . 'This compares the uploaded reference package with UNA’s '
                . 'recorded checksum baseline. It does not modify the baseline.'
                . '</p>'
                . '</div>';
        }


        $s .=
            $this->renderReferencePackageFileSection(
                'Live files matching UNA checksum baseline',
                $aComparison[
                    'baseline_backed_modified_files'
                ] ?? array()
            );

        $s .=
            $this->renderReferencePackageFileSection(
                'Approved compatibility changes',
                $aComparison[
                    'approved_modified_files'
                ] ?? array()
            );

        $s .=
            $this->renderLocalRepairKnowledgeCandidates(
                $aComparison[
                    'unexplained_modified_files'
                ] ?? array(),
                $aPackage,
                $aInstalled,
                $aEvidence,
                (string)$sCsrfToken
            );

        $s .=
            $this->renderReferencePackageFileSection(
                'Missing from live module',
                $aComparison['missing_files']
                    ?? array()
            );

        $s .=
            $this->renderRuntimeGeneratedLiveOnlySummary(
                $aComparison
            );

        $s .=
            $this->renderReferencePackageFileSection(
                'UNA baseline-backed live-only files',
                $aComparison[
                    'baseline_backed_live_only_files'
                ] ?? array()
            );

        $s .=
            $this->renderReferencePackageFileSection(
                'Unexplained live-only files',
                $aComparison[
                    'unexplained_live_only_files'
                ] ?? array()
            );

        if (!empty($aBaseline['available'])) {
            $s .=
                $this->renderReferencePackageFileSection(
                    'Reference files differing from UNA baseline',
                    $aBaseline['different_files']
                        ?? array()
                );

            $s .=
                $this->renderReferencePackageFileSection(
                    'Reference files not tracked by UNA baseline',
                    $aBaseline[
                        'reference_untracked_files'
                    ] ?? array()
                );

            $s .=
                $this->renderReferencePackageFileSection(
                    'UNA baseline files absent from reference package',
                    $aBaseline[
                        'baseline_missing_from_reference'
                    ] ?? array()
                );
        }

        if (
            isset($aEvidence['session_id'])
            && (string)$aEvidence['session_id']
                !== ''
        ) {
            $s .=
                '<div class="mud-ref-card">'
                . '<h3>Preserved Evidence</h3>'
                . '<p>Comparison session: <code>'
                . $this->esc(
                    (string)$aEvidence['session_id']
                )
                . '</code></p>'
                . '<p class="mud-ref-help">'
                . 'The uploaded reference ZIP and complete JSON comparison '
                . 'report were preserved in Doctor’s protected tmp evidence area.'
                . '</p>'
                . '</div>';
        }

        return $s;
    }


    protected function renderRuntimeGeneratedLiveOnlySummary(
        array $aComparison
    ) {
        $iCount =
            (int)(
                $aComparison[
                    'runtime_generated_live_only_count'
                ] ?? 0
            );

        if ($iCount <= 0)
            return '';

        $aEvidence =
            isset(
                $aComparison[
                    'runtime_generated_live_only_evidence'
                ]
            )
            && is_array(
                $aComparison[
                    'runtime_generated_live_only_evidence'
                ]
            )
                ? $aComparison[
                    'runtime_generated_live_only_evidence'
                ]
                : array();

        $aFiles =
            isset(
                $aComparison[
                    'runtime_generated_live_only_files'
                ]
            )
            && is_array(
                $aComparison[
                    'runtime_generated_live_only_files'
                ]
            )
                ? $aComparison[
                    'runtime_generated_live_only_files'
                ]
                : array();

        $sDominant =
            (string)(
                $aEvidence[
                    'dominant_directory'
                ] ?? ''
            );

        $sConfidence =
            strtoupper(
                (string)(
                    $aEvidence[
                        'confidence'
                    ] ?? ''
                )
            );

        $sPercent =
            isset(
                $aEvidence[
                    'dominant_percent'
                ]
            )
                ? number_format(
                    (float)$aEvidence[
                        'dominant_percent'
                    ],
                    2
                )
                : '';

        $s =
            '<div class="mud-ref-card">'
            . '<h3>Runtime-generated live-only content</h3>'
            . '<p><strong>'
            . number_format($iCount)
            . ' files</strong> were classified as '
            . 'high-confidence runtime-generated module content.</p>'
            . '<table class="mud-ref-table"><tbody>'
            . $this->referencePackageRow(
                'Dominant directory',
                $sDominant
            )
            . $this->referencePackageRow(
                'Confidence',
                $sConfidence
            )
            . $this->referencePackageRow(
                'Dominant share',
                $sPercent !== ''
                    ? $sPercent . '%'
                    : ''
            )
            . '</tbody></table>'
            . '<p class="mud-ref-help">'
            . 'These files remain part of UNA Studio checksum/update '
            . 'readiness because they live inside the module tree. '
            . 'Doctor does not re-baseline, delete, or modify them.'
            . '</p>';

        if (!empty($aFiles)) {
            $s .=
                '<details class="mud-ref-knowledge-item">'
                . '<summary>Show sample runtime files ('
                . count($aFiles)
                . ' shown)</summary>'
                . '<div class="mud-ref-knowledge-body">';

            foreach ($aFiles as $sFile) {
                $s .=
                    '<div><code>'
                    . $this->esc(
                        (string)$sFile
                    )
                    . '</code></div>';
            }

            $s .=
                '</div>'
                . '</details>';
        }

        $s .= '</div>';

        return $s;
    }


    protected function renderLocalRepairKnowledgeSaveResult(
        array $aResult
    ) {
        if (
            !empty($aResult['ok'])
            && !empty($aResult['saved'])
        ) {
            return
                '<div class="mud-ref-card mud-ref-success">'
                . '<h3>Local Repair Knowledge saved</h3>'
                . '<p><strong>'
                . $this->esc(
                    (string)(
                        $aResult['file']
                        ?? ''
                    )
                )
                . '</strong> is now tied to its exact module version, '
                . 'reference MD5, approved live MD5, and preserved '
                . 'Reference Package evidence.</p>'
                . '<p>Upload the same reference ZIP again to have '
                . 'Doctor reclassify this exact difference automatically.</p>'
                . '</div>';
        }

        return
            '<div class="mud-ref-card mud-ref-error">'
            . '<h3>Local Repair Knowledge was not saved</h3>'
            . '<p>'
            . $this->esc(
                (string)(
                    $aResult['error']
                    ?? 'The record could not be verified.'
                )
            )
            . '</p>'
            . '<p>No live module file was changed.</p>'
            . '</div>';
    }


    protected function renderLocalRepairKnowledgeCandidates(
        array $aFiles,
        array $aPackage,
        array $aInstalled,
        array $aEvidence,
        $sCsrfToken
    ) {
        if (empty($aFiles))
            return '';

        $sAction =
            BX_DOL_URL_STUDIO
            . 'module.php?name=my_una_doctor'
            . '&page=reference_packages';

        $sModule =
            (string)(
                $aInstalled['name']
                ?? ''
            );

        $sVersion =
            (string)(
                $aInstalled['registered_version']
                ?? ''
            );

        $sReferenceSha =
            (string)(
                $aPackage['sha256']
                ?? ''
            );

        $sReferenceFilename =
            (string)(
                $aPackage['original_filename']
                ?? ''
            );

        $sSession =
            (string)(
                $aEvidence['session_id']
                ?? ''
            );

        $s =
            '<div class="mud-ref-card">'
            . '<h3>Unexplained modified files ('
            . count($aFiles)
            . ')</h3>'
            . '<p class="mud-ref-help">'
            . 'If you already know why a modification is required and '
            . 'have verified that the current file is working correctly, '
            . 'record that knowledge here. Doctor will save the exact '
            . 'reference/live hash pair and verify it on future comparisons.'
            . '</p>';

        foreach ($aFiles as $aFile) {
            if (!is_array($aFile))
                continue;

            $sFile =
                (string)(
                    $aFile['file']
                    ?? ''
                );

            $sReferenceMd5 =
                (string)(
                    $aFile['reference_md5']
                    ?? ''
                );

            $sLiveMd5 =
                (string)(
                    $aFile['live_md5']
                    ?? ''
                );

            $s .=
                '<details class="mud-ref-knowledge-item">'
                . '<summary><code>'
                . $this->esc($sFile)
                . '</code></summary>'
                . '<div class="mud-ref-knowledge-body">'
                . '<div class="mud-ref-hash">'
                . 'Reference: '
                . $this->esc($sReferenceMd5)
                . '<br>Live: '
                . $this->esc($sLiveMd5)
                . '</div>'
                . '<form method="post" action="'
                . $this->esc($sAction)
                . '" class="mud-ref-knowledge-form">'

                . '<input type="hidden" name="mud_action" '
                . 'value="save_local_repair_knowledge">'

                . '<input type="hidden" name="csrf_token" value="'
                . $this->esc($sCsrfToken)
                . '">'

                . '<input type="hidden" name="knowledge_module" value="'
                . $this->esc($sModule)
                . '">'

                . '<input type="hidden" name="knowledge_version" value="'
                . $this->esc($sVersion)
                . '">'

                . '<input type="hidden" name="knowledge_file" value="'
                . $this->esc($sFile)
                . '">'

                . '<input type="hidden" '
                . 'name="knowledge_reference_md5" value="'
                . $this->esc($sReferenceMd5)
                . '">'

                . '<input type="hidden" '
                . 'name="knowledge_live_md5" value="'
                . $this->esc($sLiveMd5)
                . '">'

                . '<input type="hidden" '
                . 'name="knowledge_reference_sha256" value="'
                . $this->esc($sReferenceSha)
                . '">'

                . '<input type="hidden" '
                . 'name="knowledge_reference_filename" value="'
                . $this->esc($sReferenceFilename)
                . '">'

                . '<input type="hidden" '
                . 'name="knowledge_session_id" value="'
                . $this->esc($sSession)
                . '">'

                . '<label class="mud-ref-knowledge-label">'
                . 'Repair type'
                . '<select name="knowledge_type" required>'
                . '<option value="local_fix">Local fix</option>'
                . '<option value="una_compatibility">UNA compatibility</option>'
                . '<option value="php_compatibility">PHP compatibility</option>'
                . '<option value="namespace_compatibility">Namespace compatibility</option>'
                . '<option value="vendor_hotfix">Vendor hotfix</option>'
                . '<option value="other">Other</option>'
                . '</select>'
                . '</label>'

                . '<label class="mud-ref-knowledge-label">'
                . 'What was fixed, and why is this current file known-good?'
                . '<textarea '
                . 'name="knowledge_reason" '
                . 'rows="4" '
                . 'required '
                . 'placeholder="Example: Updated for UNA 15 compatibility; tested successfully after the original module failed under the current API."></textarea>'
                . '</label>'

                . '<div class="mud-ref-notice">'
                . '<strong>This records knowledge only.</strong> '
                . 'Doctor will not change, replace, or re-baseline the live file.'
                . '</div>'

                . '<button type="submit" '
                . 'class="bx-btn bx-btn-primary">'
                . 'Save Verified Local Repair'
                . '</button>'

                . '</form>'
                . '</div>'
                . '</details>';
        }

        $s .= '</div>';

        return $s;
    }


    protected function referencePackageMetric(
        $sLabel,
        $mixedValue
    ) {
        return
            '<div class="mud-ref-metric">'
            . '<div class="mud-ref-metric-value">'
            . $this->esc((string)$mixedValue)
            . '</div>'
            . '<div class="mud-ref-metric-label">'
            . $this->esc((string)$sLabel)
            . '</div>'
            . '</div>';
    }


    protected function referencePackageRow(
        $sLabel,
        $mixedValue
    ) {
        return
            '<tr>'
            . '<th>'
            . $this->esc((string)$sLabel)
            . '</th>'
            . '<td>'
            . $this->esc((string)$mixedValue)
            . '</td>'
            . '</tr>';
    }


    protected function renderReferencePackageFileSection(
        $sTitle,
        array $aFiles
    ) {
        if (empty($aFiles))
            return '';

        $s =
            '<div class="mud-ref-card">'
            . '<details>'
            . '<summary>'
            . $this->esc($sTitle)
            . ' ('
            . count($aFiles)
            . ' shown)</summary>'
            . '<div class="mud-ref-files">';

        foreach ($aFiles as $mixedFile) {
            if (is_array($mixedFile)) {
                $sFile =
                    isset($mixedFile['file'])
                        ? (string)$mixedFile['file']
                        : '';

                $s .=
                    '<div class="mud-ref-file">'
                    . '<code>'
                    . $this->esc($sFile)
                    . '</code>';

                if (
                    isset(
                        $mixedFile[
                            'reference_md5'
                        ]
                    )
                    && isset(
                        $mixedFile[
                            'live_md5'
                        ]
                    )
                ) {
                    $s .=
                        '<div class="mud-ref-hash">'
                        . 'Reference: '
                        . $this->esc(
                            (string)$mixedFile[
                                'reference_md5'
                            ]
                        )
                        . '<br>Live: '
                        . $this->esc(
                            (string)$mixedFile[
                                'live_md5'
                            ]
                        )
                        . '</div>';
                } elseif (
                    isset(
                        $mixedFile[
                            'baseline_md5'
                        ]
                    )
                    && isset(
                        $mixedFile[
                            'reference_md5'
                        ]
                    )
                ) {
                    $s .=
                        '<div class="mud-ref-hash">'
                        . 'UNA baseline: '
                        . $this->esc(
                            (string)$mixedFile[
                                'baseline_md5'
                            ]
                        )
                        . '<br>Reference: '
                        . $this->esc(
                            (string)$mixedFile[
                                'reference_md5'
                            ]
                        )
                        . '</div>';
                }

                if (
                    isset($mixedFile['approval'])
                    && is_array(
                        $mixedFile['approval']
                    )
                    && !empty(
                        $mixedFile['approval']['reason']
                    )
                ) {
                    $s .=
                        '<div class="mud-ref-approved">'
                        . 'Approved compatibility change: '
                        . $this->esc(
                            (string)$mixedFile[
                                'approval'
                            ]['reason']
                        )
                        . '</div>';
                }

                $s .= '</div>';
            } else {
                $s .=
                    '<div class="mud-ref-file"><code>'
                    . $this->esc(
                        (string)$mixedFile
                    )
                    . '</code></div>';
            }
        }

        $s .=
            '</div>'
            . '</details>'
            . '</div>';

        return $s;
    }


    protected function referencePackageCss()
    {
        return <<<'HTML'
<style>
.mud-ref {
    max-width: 1200px;
}
.mud-ref-hero {
    margin-bottom: 1rem;
}
.mud-ref-hero h2 {
    margin-bottom: .35rem;
}
.mud-ref-card,
.mud-ref-notice {
    border: 1px solid rgba(127,127,127,.25);
    border-radius: .6rem;
    padding: 1rem;
    margin: 0 0 1rem 0;
    background: rgba(127,127,127,.055);
}
.mud-ref-notice {
    border-left: .3rem solid #d69e00;
}
.mud-ref-success {
    border-left: .3rem solid #299447;
}
.mud-ref-warning {
    border-left: .3rem solid #d69e00;
}
.mud-ref-error {
    border-left: .3rem solid #c0392b;
}
.mud-ref-grid {
    display: grid;
    grid-template-columns:
        repeat(auto-fit, minmax(150px, 1fr));
    gap: .75rem;
    margin-bottom: 1rem;
}
.mud-ref-grid-small {
    margin-top: .75rem;
}
.mud-ref-metric {
    border: 1px solid rgba(127,127,127,.22);
    border-radius: .5rem;
    padding: .8rem;
    text-align: center;
    background: rgba(127,127,127,.045);
}
.mud-ref-metric-value {
    font-size: 1.55rem;
    font-weight: 700;
}
.mud-ref-metric-label {
    opacity: .8;
    margin-top: .15rem;
}
.mud-ref-upload-row {
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
    align-items: center;
}
.mud-ref-native-file {
    position: absolute !important;
    left: -10000px !important;
    width: 1px !important;
    height: 1px !important;
    opacity: 0 !important;
}
.mud-ref-file-picker {
    display: inline-flex;
    align-items: center;
    min-height: 2.25rem;
    padding: .35rem .8rem;
    border: 1px solid rgba(127,127,127,.55);
    border-radius: .35rem;
    cursor: pointer;
    font-weight: 600;
    background: rgba(127,127,127,.08);
}
.mud-ref-file-picker:hover,
.mud-ref-file-picker:focus {
    background: rgba(127,127,127,.16);
    outline: none;
}
.mud-ref-file-picker-selected {
    border-color: currentColor;
}
.mud-ref-file-name {
    min-width: 170px;
    max-width: 420px;
    overflow-wrap: anywhere;
    font-family: monospace;
}
#mud-reference-submit:disabled {
    opacity: .45;
    cursor: not-allowed;
}
.mud-ref-help {
    opacity: .75;
    font-size: .93em;
}
.mud-ref-table {
    width: 100%;
    border-collapse: collapse;
}
.mud-ref-table th,
.mud-ref-table td {
    text-align: left;
    vertical-align: top;
    padding: .45rem .6rem;
    border-bottom: 1px solid rgba(127,127,127,.18);
}
.mud-ref-table th {
    width: 220px;
}
.mud-ref-files {
    margin-top: .75rem;
    max-height: 440px;
    overflow: auto;
}
.mud-ref-file {
    padding: .45rem 0;
    border-bottom: 1px solid rgba(127,127,127,.15);
    overflow-wrap: anywhere;
}
.mud-ref-hash {
    margin-top: .25rem;
    font-size: .82em;
    opacity: .72;
    font-family: monospace;
}
.mud-ref-approved {
    margin-top: .35rem;
    font-size: .9em;
    font-weight: 600;
}
.mud-ref-knowledge-item {
    margin-top: .65rem;
    border-top: 1px solid rgba(127,127,127,.18);
    padding-top: .65rem;
}
.mud-ref-knowledge-item summary {
    cursor: pointer;
    font-weight: 600;
}
.mud-ref-knowledge-body {
    padding: .75rem 0 .25rem 0;
}
.mud-ref-knowledge-form {
    margin-top: .8rem;
}
.mud-ref-knowledge-label {
    display: block;
    margin: 0 0 .8rem 0;
    font-weight: 600;
}
.mud-ref-knowledge-label select,
.mud-ref-knowledge-label textarea {
    display: block;
    width: 100%;
    max-width: 760px;
    margin-top: .3rem;
}
.mud-ref details summary {
    cursor: pointer;
    font-weight: 600;
}
</style>
HTML;
    }



    public function getLocalRepairKnowledge()
    {
        $aActionResult = null;

        /*
         * Protected registry actions.
         *
         * These actions affect Doctor's knowledge registry only.
         * They never modify module files or the UNA checksum
         * baseline.
         */
        if (
            ($_SERVER['REQUEST_METHOD'] ?? '')
                === 'POST'
        ) {
            $sAction =
                (string)(
                    $_POST['mud_action']
                    ?? ''
                );

            if (
                in_array(
                    $sAction,
                    array(
                        'toggle_local_repair_knowledge',
                        'delete_local_repair_knowledge',
                    ),
                    true
                )
            ) {
                if (!isAdmin()) {
                    $aActionResult = array(
                        'ok' => false,
                        'error' =>
                            'Administrator authorization is required.',
                    );
                } else {
                    $sSubmittedToken =
                        (string)(
                            $_POST['csrf_token']
                            ?? ''
                        );

                    if (
                        !BxDolForm::isCsrfTokenValid(
                            $sSubmittedToken
                        )
                    ) {
                        $aActionResult = array(
                            'ok' => false,
                            'error' =>
                                'CSRF validation failed or the action token expired.',
                        );
                    } else {
                        $iKnowledgeId =
                            (int)(
                                $_POST[
                                    'knowledge_id'
                                ] ?? 0
                            );

                        if (
                            $sAction
                            ===
                            'toggle_local_repair_knowledge'
                        ) {
                            $bEnabled =
                                !empty(
                                    $_POST[
                                        'knowledge_enabled'
                                    ]
                                );

                            $aActionResult =
                                $this->oDoctor
                                    ->setLocalRepairKnowledgeEnabled(
                                        $iKnowledgeId,
                                        $bEnabled
                                    );
                        } else {
                            $aActionResult =
                                $this->oDoctor
                                    ->deleteLocalRepairKnowledge(
                                        $iKnowledgeId
                                    );
                        }
                    }
                }
            }
        }

        $aRows =
            $this->oDoctor
                ->getLocalRepairKnowledgeWithStatus();

        $sCsrfToken =
            BxDolForm::getCsrfToken();

        /*
         * Overall registry counts.
         */
        $iVerified = 0;
        $iStale = 0;
        $iDisabled = 0;
        $iOther = 0;

        /*
         * Group records by module + module version.
         */
        $aGroups = array();

        foreach ($aRows as $aRow) {
            $sStatus =
                (string)(
                    $aRow[
                        'verification_status'
                    ] ?? ''
                );

            if ($sStatus === 'verified')
                $iVerified++;
            elseif ($sStatus === 'stale')
                $iStale++;
            elseif ($sStatus === 'disabled')
                $iDisabled++;
            else
                $iOther++;

            $sModule =
                (string)(
                    $aRow['module']
                    ?? ''
                );

            $sVersion =
                (string)(
                    $aRow['module_version']
                    ?? ''
                );

            $sGroupKey =
                $sModule
                . "\n"
                . $sVersion;

            if (!isset($aGroups[$sGroupKey])) {
                $aGroups[$sGroupKey] = array(
                    'module' => $sModule,
                    'version' => $sVersion,
                    'rows' => array(),
                    'verified' => 0,
                    'stale' => 0,
                    'disabled' => 0,
                    'other' => 0,
                );
            }

            $aGroups[$sGroupKey]['rows'][] =
                $aRow;

            if ($sStatus === 'verified') {
                $aGroups[
                    $sGroupKey
                ]['verified']++;
            } elseif ($sStatus === 'stale') {
                $aGroups[
                    $sGroupKey
                ]['stale']++;
            } elseif ($sStatus === 'disabled') {
                $aGroups[
                    $sGroupKey
                ]['disabled']++;
            } else {
                $aGroups[
                    $sGroupKey
                ]['other']++;
            }
        }

        $s =
            $this->css()
            . <<<'HTML'
<style>
.mud-knowledge-stats {
    display: grid !important;
    grid-template-columns:
        repeat(5, minmax(120px, 1fr));
    gap: .75rem;
    margin: 1rem 0;
}

.mud-knowledge-stats > div {
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 78px;
    padding: .8rem 1rem;
    border: 1px solid rgba(127,127,127,.22);
    border-radius: .5rem;
    background: rgba(127,127,127,.035);
}

.mud-knowledge-stats > div strong,
.mud-knowledge-stats > div > :first-child {
    font-size: 1.35rem;
    font-weight: 700;
    line-height: 1.15;
}

.mud-knowledge-stats > div span,
.mud-knowledge-stats > div > :last-child {
    margin-top: .25rem;
}

.mud-knowledge-help {
    margin: 1rem 0;
    padding: .85rem 1rem !important;
    border: 1px solid rgba(127,127,127,.20);
    border-radius: .5rem;
    background: rgba(127,127,127,.025);
}

.mud-knowledge-help h3 {
    margin-top: 0;
    margin-bottom: .45rem;
    font-size: 1.05rem;
}

.mud-knowledge-help p {
    margin: .3rem 0;
    line-height: 1.45;
}

.mud-knowledge-list {
    display: grid;
    gap: .85rem;
    margin-top: 1rem;
}

.mud-knowledge-module {
    border: 1px solid rgba(127,127,127,.25);
    border-radius: .55rem;
    overflow: hidden;
}

.mud-knowledge-module > summary {
    cursor: pointer;
    padding: .9rem 1rem;
    list-style: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    min-height: 56px;
    background: rgba(127,127,127,.025);
}

.mud-knowledge-module > summary:hover {
    background: rgba(127,127,127,.07);
}

.mud-knowledge-module > summary::-webkit-details-marker {
    display: none;
}

.mud-knowledge-module > summary::before {
    content: '▶';
    display: inline-block;
    margin-right: .55rem;
    font-size: .8em;
}

.mud-knowledge-module[open] > summary::before {
    content: '▼';
}

.mud-knowledge-module-title {
    font-weight: 700;
    font-size: 1.05rem;
    flex: 1 1 auto;
}

.mud-knowledge-module-counts {
    display: inline-flex;
    align-items: center;
    font-size: .86rem;
    opacity: .9;
    text-align: right;
    padding: .22rem .55rem;
    border: 1px solid rgba(127,127,127,.22);
    border-radius: 999px;
    white-space: nowrap;
}

.mud-knowledge-module-body {
    padding: 0 1rem 1rem 1rem;
}

.mud-knowledge-record {
    border-top: 1px solid rgba(127,127,127,.18);
}

.mud-knowledge-record:first-child {
    border-top: 0;
}

.mud-knowledge-record > summary {
    cursor: pointer;
    list-style: none;
    padding: .8rem .5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    border-radius: .35rem;
}

.mud-knowledge-record > summary:hover {
    background: rgba(127,127,127,.055);
}

.mud-knowledge-record > summary::-webkit-details-marker {
    display: none;
}

.mud-knowledge-record > summary::before {
    content: '▶';
    margin-right: .45rem;
    font-size: .75em;
}

.mud-knowledge-record[open] > summary::before {
    content: '▼';
}

.mud-knowledge-file {
    flex: 1 1 auto;
    overflow-wrap: anywhere;
}

.mud-knowledge-status {
    display: inline-flex;
    align-items: center;
    padding: .18rem .5rem;
    border: 1px solid currentColor;
    border-radius: 999px;
    font-size: .78rem;
    font-weight: 700;
    white-space: nowrap;
}

.mud-knowledge-record-body {
    padding: 0 .25rem 1rem .25rem;
}

.mud-knowledge-reason {
    margin-top: 1rem;
    padding: .75rem;
    border: 1px solid rgba(127,127,127,.18);
    border-radius: .4rem;
}

.mud-knowledge-actions {
    display: flex;
    gap: .6rem;
    flex-wrap: wrap;
    margin-top: 1rem;
}

.mud-knowledge-help {
    margin-bottom: 1rem;
}

@media (max-width: 1000px) {
    .mud-knowledge-stats {
        grid-template-columns:
            repeat(3, minmax(120px, 1fr));
    }
}

@media (max-width: 700px) {
    .mud-knowledge-stats {
        grid-template-columns:
            repeat(2, minmax(120px, 1fr));
    }

    .mud-knowledge-module > summary {
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .mud-knowledge-module-counts {
        width: 100%;
        text-align: left;
        padding-left: 1.35rem;
    }

    .mud-knowledge-record > summary {
        align-items: flex-start;
        flex-wrap: wrap;
    }
}

@media (max-width: 430px) {
    .mud-knowledge-stats {
        grid-template-columns: 1fr;
    }
}
</style>
HTML
            . '<div class="mud-wrap">';

        $s .=
            '<div class="mud-hero">'
            . '<div>'
            . '<h1>Local Repair Knowledge</h1>'
            . '<p>Persistent, evidence-backed knowledge about '
            . 'intentional module modifications that Doctor can '
            . 're-verify on future comparisons.</p>'
            . '</div>'
            . '</div>';

        /*
         * Action result.
         */
        if (is_array($aActionResult)) {
            if (!empty($aActionResult['ok'])) {
                $s .=
                    '<div class="mud-ref-card mud-ref-success">'
                    . '<h3>Knowledge record updated</h3>'
                    . '<p>'
                    . $this->esc(
                        (string)(
                            $aActionResult['message']
                            ?? 'The knowledge registry was updated.'
                        )
                    )
                    . '</p>'
                    . '<p>No module file and no UNA checksum '
                    . 'baseline was changed.</p>'
                    . '</div>';
            } else {
                $s .=
                    '<div class="mud-ref-card mud-ref-error">'
                    . '<h3>Knowledge record was not changed</h3>'
                    . '<p>'
                    . $this->esc(
                        (string)(
                            $aActionResult['error']
                            ?? 'The requested action failed.'
                        )
                    )
                    . '</p>'
                    . '</div>';
            }
        }

        /*
         * Global scorecard.
         */
        $s .=
            '<div class="mud-ref-grid mud-knowledge-stats">'
            . $this->referencePackageMetric(
                'Total records',
                count($aRows)
            )
            . $this->referencePackageMetric(
                'Verified',
                $iVerified
            )
            . $this->referencePackageMetric(
                'Stale',
                $iStale
            )
            . $this->referencePackageMetric(
                'Disabled',
                $iDisabled
            )
            . $this->referencePackageMetric(
                'Needs review',
                $iOther
            )
            . '</div>';

        $s .=
            '<div class="mud-ref-card mud-knowledge-help">'
            . '<h3>How Doctor uses this knowledge</h3>'
            . '<p>Modules are collapsed by default. Open a module '
            . 'to see its saved repair records, then open an '
            . 'individual file only when you need its detailed '
            . 'hashes, explanation, reference package, or controls.</p>'
            . '<p>Enabled records are accepted only when the '
            . 'registered module version and current live file '
            . 'still match the saved approved hash. A changed file '
            . 'becomes <strong>STALE</strong> instead of being '
            . 'silently trusted.</p>'
            . '<p><strong>Disable</strong> keeps the evidence but '
            . 'stops Doctor from trusting it. '
            . '<strong>Delete</strong> removes only the knowledge '
            . 'record. Neither action edits the module itself.</p>'
            . '</div>';

        if (empty($aRows)) {
            $s .=
                '<div class="mud-empty">'
                . '<h2>No Local Repair Knowledge has been saved yet.</h2>'
                . '<p>Records can be created from unexplained '
                . 'differences in Module Reference Packages.</p>'
                . '</div>'
                . '</div>';

            return $s;
        }

        $sActionUrl =
            BX_DOL_URL_STUDIO
            . 'module.php?name=my_una_doctor'
            . '&page=local_repair_knowledge';

        /*
         * Module cards.
         */
        $s .= '<div class="mud-knowledge-list">';

        foreach ($aGroups as $aGroup) {
            $sModule =
                (string)$aGroup['module'];

            $sVersion =
                (string)$aGroup['version'];

            $iRecords =
                count($aGroup['rows']);

            $aCountBits = array();

            $aCountBits[] =
                $iRecords
                . ' '
                . ($iRecords === 1
                    ? 'record'
                    : 'records');

            if (
                (int)$aGroup['verified'] > 0
            ) {
                $aCountBits[] =
                    (int)$aGroup['verified']
                    . ' verified';
            }

            if (
                (int)$aGroup['stale'] > 0
            ) {
                $aCountBits[] =
                    (int)$aGroup['stale']
                    . ' stale';
            }

            if (
                (int)$aGroup['disabled'] > 0
            ) {
                $aCountBits[] =
                    (int)$aGroup['disabled']
                    . ' disabled';
            }

            if (
                (int)$aGroup['other'] > 0
            ) {
                $aCountBits[] =
                    (int)$aGroup['other']
                    . ' need review';
            }

            $s .=
                '<details class="mud-knowledge-module">'
                . '<summary>'
                . '<span class="mud-knowledge-module-title">'
                . $this->esc(
                    $sModule
                    . ' '
                    . $sVersion
                )
                . '</span>'
                . '<span class="mud-knowledge-module-counts">'
                . $this->esc(
                    implode(
                        ' • ',
                        $aCountBits
                    )
                )
                . '</span>'
                . '</summary>'
                . '<div class="mud-knowledge-module-body">';

            /*
             * Individual repair records.
             */
            foreach ($aGroup['rows'] as $aRow) {
                $iId =
                    (int)(
                        $aRow['id']
                        ?? 0
                    );

                $sStatus =
                    (string)(
                        $aRow[
                            'verification_label'
                        ] ?? 'UNVERIFIED'
                    );

                $sFile =
                    (string)(
                        $aRow['file']
                        ?? ''
                    );

                $sType =
                    ucwords(
                        str_replace(
                            '_',
                            ' ',
                            (string)(
                                $aRow[
                                    'repair_type'
                                ] ?? ''
                            )
                        )
                    );

                $sReason =
                    (string)(
                        $aRow['reason']
                        ?? ''
                    );

                $sReferenceMd5 =
                    (string)(
                        $aRow[
                            'reference_md5'
                        ] ?? ''
                    );

                $sApprovedMd5 =
                    (string)(
                        $aRow[
                            'approved_live_md5'
                        ] ?? ''
                    );

                $sCurrentMd5 =
                    (string)(
                        $aRow[
                            'current_live_md5'
                        ] ?? ''
                    );

                $sReferenceFilename =
                    (string)(
                        $aRow[
                            'reference_filename'
                        ] ?? ''
                    );

                $sSession =
                    (string)(
                        $aRow[
                            'comparison_session_id'
                        ] ?? ''
                    );

                $iCreated =
                    (int)(
                        $aRow['created']
                        ?? 0
                    );

                $iUpdated =
                    (int)(
                        $aRow['updated']
                        ?? 0
                    );

                $bEnabled =
                    !empty(
                        $aRow['enabled']
                    );

                $s .=
                    '<details class="mud-knowledge-record">'
                    . '<summary>'
                    . '<span class="mud-knowledge-file"><code>'
                    . $this->esc($sFile)
                    . '</code></span>'
                    . '<span class="mud-knowledge-status">'
                    . $this->esc($sStatus)
                    . '</span>'
                    . '</summary>'

                    . '<div class="mud-knowledge-record-body">'

                    . '<table class="mud-ref-table"><tbody>'

                    . $this->referencePackageRow(
                        'Repair type',
                        $sType
                    )

                    . $this->referencePackageRow(
                        'Reference MD5',
                        $sReferenceMd5
                    )

                    . $this->referencePackageRow(
                        'Approved live MD5',
                        $sApprovedMd5
                    )

                    . $this->referencePackageRow(
                        'Current live MD5',
                        $sCurrentMd5 !== ''
                            ? $sCurrentMd5
                            : 'Unavailable'
                    )

                    . $this->referencePackageRow(
                        'Reference ZIP',
                        $sReferenceFilename !== ''
                            ? $sReferenceFilename
                            : 'Not recorded'
                    )

                    . $this->referencePackageRow(
                        'Comparison session',
                        $sSession !== ''
                            ? $sSession
                            : 'Not recorded'
                    )

                    . $this->referencePackageRow(
                        'Saved',
                        $iCreated > 0
                            ? date(
                                'Y-m-d H:i:s',
                                $iCreated
                            )
                            : ''
                    )

                    . $this->referencePackageRow(
                        'Last updated',
                        $iUpdated > 0
                            ? date(
                                'Y-m-d H:i:s',
                                $iUpdated
                            )
                            : ''
                    )

                    . '</tbody></table>'

                    . '<div class="mud-knowledge-reason">'
                    . '<strong>Why this modification is known-good</strong>'
                    . '<p>'
                    . nl2br(
                        $this->esc(
                            $sReason
                        )
                    )
                    . '</p>'
                    . '</div>'

                    . '<div class="mud-knowledge-actions">'

                    /*
                     * Enable / Disable.
                     */
                    . '<form method="post" action="'
                    . $this->esc($sActionUrl)
                    . '">'

                    . '<input type="hidden" '
                    . 'name="mud_action" '
                    . 'value="toggle_local_repair_knowledge">'

                    . '<input type="hidden" '
                    . 'name="csrf_token" value="'
                    . $this->esc(
                        (string)$sCsrfToken
                    )
                    . '">'

                    . '<input type="hidden" '
                    . 'name="knowledge_id" value="'
                    . $iId
                    . '">'

                    . '<input type="hidden" '
                    . 'name="knowledge_enabled" value="'
                    . ($bEnabled ? '0' : '1')
                    . '">'

                    . '<button type="submit" class="bx-btn">'
                    . ($bEnabled ? 'Disable' : 'Enable')
                    . '</button>'

                    . '</form>'

                    /*
                     * Delete knowledge only.
                     */
                    . '<form method="post" action="'
                    . $this->esc($sActionUrl)
                    . '" onsubmit="return confirm('
                    . '\'Delete this Local Repair Knowledge record? '
                    . 'The live module file will NOT be deleted or changed.\');">'

                    . '<input type="hidden" '
                    . 'name="mud_action" '
                    . 'value="delete_local_repair_knowledge">'

                    . '<input type="hidden" '
                    . 'name="csrf_token" value="'
                    . $this->esc(
                        (string)$sCsrfToken
                    )
                    . '">'

                    . '<input type="hidden" '
                    . 'name="knowledge_id" value="'
                    . $iId
                    . '">'

                    . '<button type="submit" class="bx-btn">'
                    . 'Delete Record'
                    . '</button>'

                    . '</form>'

                    . '</div>'
                    . '</div>'
                    . '</details>';
            }

            $s .=
                '</div>'
                . '</details>';
        }

        $s .=
            '</div>'
            . '</div>';

        return $s;
    }


    public function getRepairCandidates()
    {
        $aApprovalValidation = null;
        $aArmExecution = null;
        $aRestoreExecution = null;
        $aRuntimeProtectionExecution = null;
        $aRuntimeRestoreExecution = null;
        $aRuntimeMergeExecution = null;
        $aRuntimeMergeBatchExecution = null;

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
                            'Explicit Prepare Repair confirmation is required.',
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
                === 'begin_runtime_merge'
        ) {
            if (!isAdmin()) {
                $aRuntimeMergeExecution = array(
                    'ok' => false,
                    'checkpointed' => false,
                    'changes_applied' => false,
                    'runtime_files_changed' => false,
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
                    $aRuntimeMergeExecution = array(
                        'ok' => false,
                        'checkpointed' => false,
                        'changes_applied' => false,
                        'runtime_files_changed' => false,
                        'error' =>
                            'CSRF validation failed or the runtime-merge token expired.',
                    );
                } elseif (
                    ($_POST['confirm_runtime_merge'] ?? '')
                    !== 'yes'
                ) {
                    $aRuntimeMergeExecution = array(
                        'ok' => false,
                        'checkpointed' => false,
                        'changes_applied' => false,
                        'runtime_files_changed' => false,
                        'error' =>
                            'Explicit runtime-merge checkpoint confirmation is required.',
                    );
                } else {
                    $aRuntimeMergeExecution =
                        $this->oDoctor
                            ->beginRuntimeMerge(
                                (int)(
                                    $_POST['journal_id']
                                    ?? 0
                                ),
                                (string)(
                                    $_POST['merge_plan_id']
                                    ?? ''
                                )
                            );
                }
            }
        }


        /*
         * Protected runtime merge continuation.
         *
         * Studio supplies no manifest index and no batch size.
         * Doctor derives the next bounded batch from authoritative
         * durable state. The guarded gateway independently enforces
         * the internal maximum of 25 runtime files.
         */
        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && ($_POST['mud_action'] ?? '')
                === 'continue_repair'
        ) {
            if (!isAdmin()) {
                $aRuntimeMergeBatchExecution = array(
                    'ok' => false,
                    'changes_applied' => false,
                    'installed_count' => 0,
                    'operation' => 'continue_repair',
                    'requested_count' => 0,
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
                    $aRuntimeMergeBatchExecution = array(
                        'ok' => false,
                        'changes_applied' => false,
                        'installed_count' => 0,
                        'operation' => 'continue_repair',
                        'requested_count' => 0,
                        'error' =>
                            'CSRF validation failed or the protected repair token expired.',
                    );
                } elseif (
                    (
                        $_POST[
                            'confirm_runtime_merge_batch'
                        ]
                        ?? ''
                    ) !== 'yes'
                ) {
                    $aRuntimeMergeBatchExecution = array(
                        'ok' => false,
                        'changes_applied' => false,
                        'installed_count' => 0,
                        'operation' => 'continue_repair',
                        'requested_count' => 0,
                        'error' =>
                            'Explicit protected repair confirmation is required.',
                    );
                } else {
                    /*
                     * The guarded gateway derives both the next manifest
                     * index and bounded file count from authoritative
                     * durable state. No POST field can alter either.
                     */
                    $aRuntimeMergeBatchExecution =
                        $this->oDoctor
                            ->continueRuntimeMergeAdaptiveWorkCycle(
                                (int)(
                                    $_POST['journal_id']
                                    ?? 0
                                ),
                                (int)(
                                    $_POST[
                                        'expected_ledger_events'
                                    ]
                                    ?? -1
                                )
                            );

                    if (
                        is_array(
                            $aRuntimeMergeBatchExecution
                        )
                    ) {
                        $aRuntimeMergeBatchExecution[
                            'operation'
                        ] = 'continue_repair';

                        $aRuntimeMergeBatchExecution[
                            'requested_count'
                        ] = (int)(
                            $aRuntimeMergeBatchExecution[
                                'requested_files'
                            ]
                            ?? 0
                        );
                    }
                }
            }
        }


        /*
         * Settings-aware protected rollback work cycle.
         *
         * The browser supplies no manifest indexes. Doctor derives
         * the highest installed runtime range authoritatively.
         * rollbackRuntimeMergeWorkCycle() retains the proven
         * 10-file internal rollback chunk ceiling.
         */
        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && ($_POST['mud_action'] ?? '')
                === 'runtime_merge_rollback_work_cycle'
        ) {
            $iWorkCycleSize =
                $this->runtimeMergeWorkCycleSize();

            if (!isAdmin()) {
                $aRuntimeMergeBatchExecution = array(
                    'ok' => false,
                    'changes_applied' => false,
                    'rolled_back_count' => 0,
                    'operation' => 'batch_rollback',
                    'requested_count' => $iWorkCycleSize,
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
                    $aRuntimeMergeBatchExecution = array(
                        'ok' => false,
                        'changes_applied' => false,
                        'rolled_back_count' => 0,
                        'operation' => 'batch_rollback',
                        'requested_count' => $iWorkCycleSize,
                        'error' =>
                            'CSRF validation failed or the protected rollback token expired.',
                    );
                } elseif (
                    (
                        $_POST[
                            'confirm_runtime_merge_rollback_work_cycle'
                        ]
                        ?? ''
                    ) !== 'yes'
                ) {
                    $aRuntimeMergeBatchExecution = array(
                        'ok' => false,
                        'changes_applied' => false,
                        'rolled_back_count' => 0,
                        'operation' => 'batch_rollback',
                        'requested_count' => $iWorkCycleSize,
                        'error' =>
                            'Explicit protected rollback confirmation is required.',
                    );
                } else {
                    $iJournalId =
                        (int)(
                            $_POST['journal_id']
                            ?? 0
                        );

                    $iExpectedEvents =
                        (int)(
                            $_POST[
                                'expected_ledger_events'
                            ]
                            ?? -1
                        );

                    $aNext =
                        $this->oDoctor
                            ->getRuntimeMergeNextBatchState(
                                $iJournalId
                            );

                    if (
                        empty($aNext['ok'])
                        || (int)(
                            $aNext['unjournaled_events']
                            ?? -1
                        ) !== 0
                        || (int)(
                            $aNext['event_count']
                            ?? -1
                        ) !== $iExpectedEvents
                        || (int)(
                            $aNext['journal_event_count']
                            ?? -2
                        ) !== $iExpectedEvents
                    ) {
                        $aRuntimeMergeBatchExecution = array(
                            'ok' => false,
                            'changes_applied' => false,
                            'rolled_back_count' => 0,
                            'operation' => 'batch_rollback',
                            'requested_count' => $iWorkCycleSize,
                            'error' =>
                                'Protected rollback checkpoint is stale. Reload the repair state.',
                        );
                    } else {
                        $iInstalled =
                            (int)(
                                $aNext['installed_count']
                                ?? -1
                            );

                        $iNextStart =
                            (int)(
                                $aNext['next_start_index']
                                ?? -1
                            );

                        if (
                            $iInstalled <= 0
                            || $iNextStart !== $iInstalled
                        ) {
                            $aRuntimeMergeBatchExecution = array(
                                'ok' => false,
                                'changes_applied' => false,
                                'rolled_back_count' => 0,
                                'operation' => 'batch_rollback',
                                'requested_count' => $iWorkCycleSize,
                                'error' =>
                                    'Protected rollback is not at a valid synchronized runtime checkpoint.',
                            );
                        } else {
                            $iCount =
                                min(
                                    $iWorkCycleSize,
                                    $iInstalled
                                );

                            $iStart =
                                $iNextStart - $iCount;

                            $aRuntimeMergeBatchExecution =
                                $this->oDoctor
                                    ->rollbackRuntimeMergeWorkCycle(
                                        $iJournalId,
                                        $iStart,
                                        $iCount,
                                        $iExpectedEvents
                                    );

                            if (
                                is_array(
                                    $aRuntimeMergeBatchExecution
                                )
                            ) {
                                $aRuntimeMergeBatchExecution[
                                    'operation'
                                ] = 'batch_rollback';

                                $aRuntimeMergeBatchExecution[
                                    'requested_count'
                                ] = $iCount;
                            }
                        }
                    }
                }
            }
        }



        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && ($_POST['mud_action'] ?? '')
                === 'finalize_successful_runtime_merge'
        ) {
            $aRuntimeMergeFinalizeExecution =
                array(
                    'ok' =>
                        false,

                    'committed' =>
                        false,

                    'restored' =>
                        false,

                    'operation' =>
                        'finalize_successful_runtime_merge',

                    'error' =>
                        '',
                );

            if (!isAdmin()) {
                $aRuntimeMergeFinalizeExecution[
                    'error'
                ] =
                    'Administrator authorization is required.';
            } else {
                $sCsrfToken =
                    (string)(
                        $_POST[
                            'csrf_token'
                        ]
                        ?? ''
                    );

                if (
                    !BxDolForm::isCsrfTokenValid(
                        $sCsrfToken
                    )
                ) {
                    $aRuntimeMergeFinalizeExecution[
                        'error'
                    ] =
                        'CSRF validation failed or the finalization token expired.';
                } elseif (
                    (
                        $_POST[
                            'confirm_runtime_merge_finalize'
                        ]
                        ?? ''
                    ) !== 'yes'
                ) {
                    $aRuntimeMergeFinalizeExecution[
                        'error'
                    ] =
                        'Explicit successful-runtime finalization confirmation is required.';
                } else {
                    $iJournalId =
                        (int)(
                            $_POST[
                                'journal_id'
                            ]
                            ?? 0
                        );

                    $iExpectedLedgerEvents =
                        (int)(
                            $_POST[
                                'expected_ledger_events'
                            ]
                            ?? -1
                        );

                    $aCommit =
                        $this->oDoctor
                            ->commitSuccessfulRuntimeMerge(
                                $iJournalId,
                                $iExpectedLedgerEvents
                            );

                    $aRuntimeMergeFinalizeExecution[
                        'commit'
                    ] =
                        $aCommit;

                    if (empty($aCommit['ok'])) {
                        $aRuntimeMergeFinalizeExecution[
                            'error'
                        ] =
                            (string)(
                                $aCommit['error']
                                ?? 'Successful runtime merge could not be committed.'
                            );
                    } else {
                        /*
                         * The journal is now a verified armed repair.
                         *
                         * restoreArmedRepair() restores only the
                         * maintenance/compression/generator barrier.
                         * It does not call restoreProtectedRuntime()
                         * and therefore does not put the old runtime
                         * tree back into service.
                         */
                        $aRestore =
                            $this->oDoctor
                                ->restoreArmedRepair(
                                    $iJournalId
                                );

                        $aRuntimeMergeFinalizeExecution[
                            'restore'
                        ] =
                            $aRestore;

                        if (
                            empty(
                                $aRestore['ok']
                            )
                            || empty(
                                $aRestore[
                                    'restored'
                                ]
                            )
                        ) {
                            $aRuntimeMergeFinalizeExecution[
                                'committed'
                            ] =
                                true;

                            $aRuntimeMergeFinalizeExecution[
                                'error'
                            ] =
                                'The merged runtime was committed successfully, but Doctor could not fully restore the original site-control state: '
                                . (
                                    $aRestore['error']
                                    ?? 'unknown restore failure'
                                );
                        } else {
                            $aRuntimeMergeFinalizeExecution[
                                'ok'
                            ] =
                                true;

                            $aRuntimeMergeFinalizeExecution[
                                'committed'
                            ] =
                                true;

                            $aRuntimeMergeFinalizeExecution[
                                'restored'
                            ] =
                                true;
                        }
                    }
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
            . '<strong>Safe Repair Mode.</strong> '
            . 'Doctor helps you review repair recommendations and safely continue '
            . 'repairs already in progress. It will ask for your approval before '
            . 'making changes.'
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

        $aLatestActiveRepairJournal =
            $this->oDoctor->getLatestActiveRepairJournal();

        $bHasActiveRepair =
            is_array($aLatestActiveRepairJournal)
            && !empty($aLatestActiveRepairJournal['id']);

        $s .= '<div style="height:18px"></div>';
        $s .= '<h2>Recommended Repairs</h2>';

        $s .= $this->renderRepairCandidateCards(
            $aScan,
            $bHasActiveRepair
        );


        $sPreflightRule = bx_get('preflight');

        if (
            $sPreflightRule !== false &&
            trim((string)$sPreflightRule) !== ''
        ) {
            $sPreflightModule = bx_get('module');

            $sPreflightReferenceSha256 =
                bx_get('reference_sha256');

            $aPreflight =
                $this->oDoctor->getRepairPreflight(
                    (string)$sPreflightRule,
                    $sPreflightModule !== false
                        ? (string)$sPreflightModule
                        : '',
                    $sPreflightReferenceSha256 !== false
                        ? (string)$sPreflightReferenceSha256
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
                $aRuntimeRestoreExecution,
                $aRuntimeMergeExecution,
                $aRuntimeMergeBatchExecution
            );
        }

        /*
         * An active durable repair journal must remain visible even
         * when the administrator opens the plain Repair Candidates
         * page without an arm_plan query parameter.
         *
         * Preserve the existing exact rule/module lookup semantics:
         * first find the latest active journal, then rebuild its
         * normal Arm Plan context from that journal's own identifiers.
         */
        if (
            $sArmPlanRule === false
            || trim((string)$sArmPlanRule) === ''
        ) {
            if (
                is_array($aLatestActiveRepairJournal)
                && !empty(
                    $aLatestActiveRepairJournal['id']
                )
            ) {
                $sActiveRepairRule =
                    (string)(
                        $aLatestActiveRepairJournal[
                            'rule'
                        ]
                        ?? ''
                    );

                $sActiveRepairModule =
                    (string)(
                        $aLatestActiveRepairJournal[
                            'module'
                        ]
                        ?? ''
                    );

                if (
                    $sActiveRepairRule !== ''
                    && $sActiveRepairModule !== ''
                ) {
                    $aActiveArmPlan =
                        $this->oDoctor
                            ->getRepairArmPlan(
                                $sActiveRepairRule,
                                $sActiveRepairModule
                            );

                    $s .= '<div style="height:18px"></div>';

                    $s .= $this->renderRepairArmPlan(
                        $aActiveArmPlan,
                        $aApprovalValidation,
                        $aArmExecution,
                        $aLatestActiveRepairJournal,
                        $aRestoreExecution,
                        $aRuntimeProtectionExecution,
                        $aRuntimeRestoreExecution,
                        $aRuntimeMergeExecution,
                        $aRuntimeMergeBatchExecution
                    );
                }
            }
        }

        $sPreviewRule = bx_get('preview');

        if (
            $sPreviewRule !== false &&
            trim((string)$sPreviewRule) !== ''
        ) {
            $sPreviewModule = bx_get('module');

            $sPreviewReferenceSha256 =
                bx_get('reference_sha256');

            $aPreview = $this->oDoctor->getRepairPreview(
                (string)$sPreviewRule,
                $sPreviewModule !== false
                    ? (string)$sPreviewModule
                    : '',
                $sPreviewReferenceSha256 !== false
                    ? (string)$sPreviewReferenceSha256
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
        /*
         * Use UNA's native module settings renderer.
         *
         * BxBaseStudioModule::getSettings() loads the required
         * options CSS/JS and renders BxTemplStudioOptions for
         * this module's registered sys_options type/category.
         */
        $sNativeSettings =
            parent::getSettings();

        $s = $this->css()
            . '<div class="mud-wrap">';

        $s .= '<div class="mud-section">';
        $s .= '<h1>My UNA Doctor Settings</h1>';

        $s .= '<div class="mud-note">'
            . '<strong>Administrator preferences</strong><br>'
            . 'These settings control Doctor presentation and protected '
            . 'work-cycle preferences. Core repair safeguards cannot be '
            . 'disabled from this page.'
            . '</div>';

        $s .= '</div>';

        /*
         * UNA-native settings form.
         */
        $s .= $sNativeSettings;

        /*
         * Keep the safety model visible beneath the configurable
         * administrator preferences.
         */
        $s .= '<div class="mud-section" style="margin-top:18px">';

        $s .= '<h1>Safety Model</h1>';

        $s .= '<div class="mud-note">'
            . '<strong>My UNA Doctor uses guarded, evidence-driven '
            . 'repair staging.</strong> Health scans remain diagnostic. '
            . 'Supported repair workflows require preview, live '
            . 'preflight, explicit approval, durable rollback state '
            . 'and verified execution gates before any controlled '
            . 'mutation is allowed.'
            . '</div>';

        $s .= '<h2>Safety protections are mandatory</h2>';

        $s .= '<p>'
            . 'Integrity verification, durable journaling, rollback '
            . 'protection, file identity checks, explicit approval '
            . 'gates and recovery verification are enforced by Doctor '
            . 'and cannot be disabled through Settings.'
            . '</p>';

        $s .= '<h2>Current diagnostic coverage</h2>';

        $s .= '<p>'
            . 'System, filesystem, database, modules, module file '
            . 'integrity, Studio update readiness, repair-candidate '
            . 'detection, notifications, cron, logs, plus read-only '
            . 'Albums and Timeline media relationship integrity.'
            . '</p>';

        $s .= '<h2>Repair candidate gate</h2>';

        $s .= '<p>'
            . 'Doctor can identify high-confidence repair patterns, '
            . 'generate dry-run previews, run live preflight validation '
            . 'and explicitly prepare supported repairs. Durable '
            . 'rollback state is written before protected runtime '
            . 'changes are allowed.'
            . '</p>';

        $s .= '<h2>My UNA World integration</h2>';

        $s .= '<p>'
            . 'A later release can request a My UNA World safety '
            . 'snapshot before an approved repair when that module '
            . 'is installed and active.'
            . '</p>';

        $s .= '</div></div>';

        return $s;
    }


    protected function renderRepairCandidateCards(
        array $aScan,
        $bHasActiveRepair = false
    ) {
        /*
         * Update preparation is intentionally separate from repairs.
         *
         * Runtime-generated content can affect UNA Studio update
         * readiness while the module's tracked source remains clean.
         * That is not evidence that the module is damaged.
         */
        $aPreparations = array();

        if (!empty($aScan['items'])) {
            foreach ($aScan['items'] as $aItem) {
                $aEvidence =
                    isset($aItem['evidence'])
                    && is_array($aItem['evidence'])
                        ? $aItem['evidence']
                        : array();

                /*
                 * Preferred source: File Integrity's dedicated
                 * runtime-generated-content INFO evidence.
                 */
                if (
                    !empty(
                        $aEvidence[
                            'runtime_content_modules'
                        ]
                    )
                    && is_array(
                        $aEvidence[
                            'runtime_content_modules'
                        ]
                    )
                ) {
                    foreach (
                        $aEvidence[
                            'runtime_content_modules'
                        ]
                        as $aRuntime
                    ) {
                        if (
                            !is_array($aRuntime)
                            || empty($aRuntime['module'])
                        ) {
                            continue;
                        }

                        /*
                         * Never offer update preparation when Doctor
                         * has already classified tracked source as
                         * drifted or otherwise unverified.
                         */
                        if (
                            (string)(
                                $aRuntime[
                                    'source_integrity'
                                ]
                                ?? ''
                            ) !== 'clean'
                        ) {
                            continue;
                        }

                        $sModule =
                            (string)$aRuntime['module'];

                        $aPreparations[$sModule] =
                            array(
                                'module' =>
                                    $sModule,

                                'title' =>
                                    (string)(
                                        $aRuntime['title']
                                        ?? $sModule
                                    ),

                                'directory' =>
                                    (string)(
                                        $aRuntime[
                                            'dominant_directory'
                                        ]
                                        ?? ''
                                    ),

                                'runtime_file_count' =>
                                    (int)(
                                        $aRuntime[
                                            'new_untracked_count'
                                        ]
                                        ?? 0
                                    ),

                                'confidence' =>
                                    (string)(
                                        $aRuntime[
                                            'confidence'
                                        ]
                                        ?? 'high'
                                    ),

                                'source_integrity' =>
                                    'clean',
                            );
                    }
                }

                /*
                 * Compatibility with a scan produced immediately
                 * before this classification change.
                 *
                 * It may still contain the former active-candidate
                 * representation. Treat it only as update-preparation
                 * evidence here, never as a repair recommendation.
                 */
                if (
                    !empty(
                        $aEvidence[
                            'active_candidates'
                        ]
                    )
                    && is_array(
                        $aEvidence[
                            'active_candidates'
                        ]
                    )
                ) {
                    foreach (
                        $aEvidence[
                            'active_candidates'
                        ]
                        as $aCandidate
                    ) {
                        if (
                            !is_array($aCandidate)
                            || (
                                $aCandidate['rule']
                                ?? ''
                            ) !==
                                'runtime_content_update_preparation'
                            || (
                                $aCandidate[
                                    'source_integrity'
                                ]
                                ?? ''
                            ) !== 'clean'
                            || empty(
                                $aCandidate[
                                    'module'
                                ]
                            )
                        ) {
                            continue;
                        }

                        $sModule =
                            (string)$aCandidate[
                                'module'
                            ];

                        if (
                            !isset(
                                $aPreparations[
                                    $sModule
                                ]
                            )
                        ) {
                            $aPreparations[$sModule] =
                                $aCandidate;
                        }
                    }
                }
            }
        }

        /*
         * Collect Doctor's actual repair recommendations.
         *
         * Update preparation is deliberately excluded here because it
         * is rendered separately below and is not evidence of damage.
         *
         * Display only:
         * no preview, preflight, arm or execution endpoint is invoked.
         */
        $aRepairs = array();

        if (!empty($aScan['items'])) {
            foreach ($aScan['items'] as $aItem) {
                if (
                    !is_array($aItem)
                    || (string)($aItem['category'] ?? '')
                        !== 'repairs'
                ) {
                    continue;
                }

                $aEvidence =
                    isset($aItem['evidence'])
                    && is_array($aItem['evidence'])
                        ? $aItem['evidence']
                        : array();

                if (
                    empty($aEvidence['active_candidates'])
                    || !is_array(
                        $aEvidence['active_candidates']
                    )
                ) {
                    continue;
                }

                foreach (
                    $aEvidence['active_candidates']
                    as $aCandidate
                ) {
                    if (!is_array($aCandidate))
                        continue;

                    $sCandidateRule =
                        (string)(
                            $aCandidate['rule']
                            ?? ''
                        );

                    if (
                        $sCandidateRule === ''
                        || $sCandidateRule
                            === 'runtime_content_update_preparation'
                    ) {
                        continue;
                    }

                    $sCandidateModule =
                        (string)(
                            $aCandidate['module']
                            ?? ''
                        );

                    $sCandidateFile =
                        (string)(
                            $aCandidate['file']
                            ?? ''
                        );

                    $sCandidateReferenceSha256 =
                        strtolower(
                            trim(
                                (string)(
                                    $aCandidate[
                                        'reference_sha256'
                                    ]
                                    ?? ''
                                )
                            )
                        );

                    $sCandidateKey =
                        $sCandidateRule
                        . '|'
                        . $sCandidateModule
                        . '|'
                        . $sCandidateFile
                        . '|'
                        . $sCandidateReferenceSha256;

                    $aRepairs[$sCandidateKey] =
                        $aCandidate;
                }
            }
        }

        /*
         * Recommended Repairs remains truthful even when an
         * update-preparation opportunity exists.
         */
        if (!empty($aRepairs)) {
            $s = '<div class="mud-findings">';

            foreach ($aRepairs as $aCandidate) {
                $sRule =
                    (string)(
                        $aCandidate['rule']
                        ?? 'repair_review'
                    );

                $sSummary =
                    trim(
                        (string)(
                            $aCandidate['summary']
                            ?? ''
                        )
                    );

                if ($sSummary === '') {
                    $sSummary =
                        ucwords(
                            str_replace(
                                '_',
                                ' ',
                                $sRule
                            )
                        );
                }

                $sConfidence =
                    strtoupper(
                        (string)(
                            $aCandidate['confidence']
                            ?? 'unknown'
                        )
                    );

                $sModule =
                    trim(
                        (string)(
                            $aCandidate['module']
                            ?? ''
                        )
                    );

                $sFile =
                    trim(
                        (string)(
                            $aCandidate['file']
                            ?? ''
                        )
                    );

                $sAction =
                    trim(
                        (string)(
                            $aCandidate[
                                'recommended_action'
                            ]
                            ?? ''
                        )
                    );

                $s .= '<div class="mud-note" '
                    . 'style="margin-bottom:14px">';

                $s .= '<strong>'
                    . $this->esc($sSummary)
                    . '</strong><br>';

                $s .= 'Confidence: '
                    . $this->esc($sConfidence);

                if ($sModule !== '') {
                    $s .= '<br>Module: '
                        . $this->esc($sModule);
                }

                if ($sFile !== '') {
                    $s .= '<br>File: '
                        . $this->esc($sFile);
                }

                if ($sAction !== '') {
                    $s .= '<br><br>'
                        . $this->esc($sAction);
                }

                $s .= '<br><br><strong>REVIEW ONLY</strong>'
                    . ' — Doctor has not changed any files and '
                    . 'has not armed this recommendation for repair.';

                if (
                    $sRule
                    === 'verified_module_reference_drift'
                    && $sModule !== ''
                ) {
                    $sReferenceSha256 =
                        strtolower(
                            trim(
                                (string)(
                                    $aCandidate[
                                        'reference_sha256'
                                    ]
                                    ?? ''
                                )
                            )
                        );

                    if (
                        strlen($sReferenceSha256) === 64
                        && ctype_xdigit(
                            $sReferenceSha256
                        )
                    ) {
                        $sPreviewUrl =
                            BX_DOL_URL_STUDIO
                            . 'module.php?name=my_una_doctor'
                            . '&page=repair_candidates'
                            . '&preview='
                            . rawurlencode(
                                $sRule
                            )
                            . '&module='
                            . rawurlencode(
                                $sModule
                            )
                            . '&reference_sha256='
                            . rawurlencode(
                                $sReferenceSha256
                            );

                        $s .= '<br><br>'
                            . '<a class="bx-btn" href="'
                            . $this->esc(
                                $sPreviewUrl
                            )
                            . '">Preview Verified Repair</a>';
                    } else {
                        $s .= '<br><br><strong>'
                            . 'Preview unavailable: invalid reference SHA-256.'
                            . '</strong>';
                    }
                }

                $s .= '</div>';
            }

            $s .= '</div>';
        } else {
            $s =
                '<div class="mud-empty">'
                . (
                    $bHasActiveRepair
                        ? 'A repair is already in progress. Continue with the active repair session below.'
                        : 'No new repair is currently recommended.'
                )
                . '</div>';
        }

        if (empty($aPreparations)) {
            return $s;
        }

        $s .= '<div style="margin-top:26px">';
        $s .= '<h3>Update Preparation</h3>';

        $s .= '<div class="mud-note">'
            . '<strong>OPTIONAL ADMINISTRATOR WORKFLOW</strong><br>'
            . 'Doctor found runtime-generated content that may affect '
            . 'UNA Studio update readiness. The tracked module source '
            . 'is clean. No repair is currently required.'
            . '</div>';

        $s .= '<div class="mud-findings">';

        foreach ($aPreparations as $aCandidate) {
            $sModule =
                isset($aCandidate['module'])
                    ? (string)$aCandidate['module']
                    : '';

            $sTitle =
                isset($aCandidate['title'])
                    ? (string)$aCandidate['title']
                    : $sModule;

            $sDirectory =
                isset($aCandidate['directory'])
                    ? (string)$aCandidate[
                        'directory'
                    ]
                    : (
                        isset(
                            $aCandidate[
                                'dominant_directory'
                            ]
                        )
                            ? (string)$aCandidate[
                                'dominant_directory'
                            ]
                            : ''
                    );

            $iFiles =
                isset(
                    $aCandidate[
                        'runtime_file_count'
                    ]
                )
                    ? (int)$aCandidate[
                        'runtime_file_count'
                    ]
                    : (
                        isset(
                            $aCandidate[
                                'new_untracked_count'
                            ]
                        )
                            ? (int)$aCandidate[
                                'new_untracked_count'
                            ]
                            : 0
                    );

            $sConfidence =
                isset($aCandidate['confidence'])
                    ? ucfirst(
                        (string)$aCandidate[
                            'confidence'
                        ]
                    )
                    : 'Unknown';

            $sSource =
                isset(
                    $aCandidate[
                        'source_integrity'
                    ]
                )
                    ? ucfirst(
                        (string)$aCandidate[
                            'source_integrity'
                        ]
                    )
                    : 'Unknown';

            $sUrl =
                BX_DOL_URL_STUDIO
                . 'module.php?name=my_una_doctor'
                . '&page=repair_candidates'
                . '&preview='
                . rawurlencode(
                    'runtime_content_update_preparation'
                )
                . '&module='
                . rawurlencode(
                    $sModule
                );

            $s .= '<div class="mud-finding info">';

            $s .= '<div class="mud-sev">'
                . '<i class="sys-icon tools"></i>'
                . '</div>';

            $s .= '<div class="mud-find-body">';

            $s .= '<div class="mud-find-head">'
                . '<strong>'
                . $this->esc($sTitle)
                . ' — Runtime Content Detected'
                . '</strong>'
                . '<span>UPDATE PREPARATION AVAILABLE</span>'
                . '</div>';

            $s .= '<p>'
                . 'Doctor identified runtime-generated content inside '
                . 'this module. Its tracked source files are clean, '
                . 'so this is not a repair condition.'
                . '</p>';

            $s .= '<details'
                . $this->technicalDetailsOpenAttribute()
                . '>'
                . '<summary style="cursor:pointer;font-weight:600">'
                . 'Technical Details'
                . '</summary>'
                . '<div style="margin-top:8px">'
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
                . '</div>'
                . '</details>';

            $s .= '<p>'
                . 'Use Prepare for Update only when you intend to '
                . 'update this module. Doctor will review the protected '
                . 'update workflow before any files are moved.'
                . '</p>';

            if ($bHasActiveRepair) {
                $s .= '<div class="mud-note">'
                    . 'Finish the active repair before starting '
                    . 'another update-preparation workflow.'
                    . '</div>';
            } else {
                $s .= '<div style="margin-top:12px">'
                    . '<a class="bx-btn" href="'
                    . $this->esc($sUrl)
                    . '">'
                    . '<i class="sys-icon shield-alt"></i> '
                    . 'Prepare for Update'
                    . '</a>'
                    . '</div>';
            }

            $s .= '</div>';
            $s .= '</div>';
        }

        $s .= '</div>';
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

        if (
            (string)(
                $aPreview['rule']
                ?? ''
            )
            === 'verified_module_reference_drift'
        ) {
            $aRows['Module version'] =
                (string)(
                    $aPreview[
                        'module_version'
                    ]
                    ?? ''
                );

            $aRows['Reference SHA-256'] =
                (string)(
                    $aPreview[
                        'reference_sha256'
                    ]
                    ?? ''
                );

            $aRows['Reference file'] =
                (string)(
                    $aPreview[
                        'reference_filename'
                    ]
                    ?? ''
                );

            $aRows['Unexplained modified'] =
                number_format(
                    (int)(
                        $aPreview[
                            'unexplained_modified_count'
                        ]
                        ?? 0
                    )
                );

            $aRows['Missing from live'] =
                number_format(
                    (int)(
                        $aPreview[
                            'missing_count'
                        ]
                        ?? 0
                    )
                );

            $aRows['Unexplained live-only'] =
                number_format(
                    (int)(
                        $aPreview[
                            'unexplained_live_only_count'
                        ]
                        ?? 0
                    )
                );

            $aRows['Preflight available'] =
                !empty(
                    $aPreview[
                        'preflight_available'
                    ]
                )
                    ? 'YES'
                    : 'NO';
        }

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

        if (
            (string)(
                $aPreview['rule']
                ?? ''
            )
            === 'verified_module_reference_drift'
        ) {
            $aSections =
                array_merge(
                    array(
                        'Unexplained modified files' =>
                            $aPreview[
                                'unexplained_modified_files'
                            ] ?? array(),

                        'Missing files' =>
                            $aPreview[
                                'missing_files'
                            ] ?? array(),

                        'Unexplained live-only files' =>
                            $aPreview[
                                'unexplained_live_only_files'
                            ] ?? array(),

                        'Explained differences Doctor will leave untouched' =>
                            $aPreview[
                                'explained_files_left_untouched'
                            ] ?? array(),
                    ),
                    $aSections
                );
        }

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

        if (
            (string)(
                $aPreview['rule']
                ?? ''
            )
            === 'verified_module_reference_drift'
            && !empty(
                $aPreview[
                    'preflight_available'
                ]
            )
            && !empty(
                $aPreview['module']
            )
        ) {
            $sPreflightUrl =
                BX_DOL_URL_STUDIO
                . 'module.php?name=my_una_doctor'
                . '&page=repair_candidates'
                . '&preflight=verified_module_reference_drift'
                . '&module='
                . rawurlencode(
                    (string)$aPreview['module']
                )
                . '&reference_sha256='
                . rawurlencode(
                    (string)(
                        $aPreview[
                            'reference_sha256'
                        ]
                        ?? ''
                    )
                );

            $s .= '<div style="margin-top:20px">'
                . '<a class="bx-btn" href="'
                . $this->esc(
                    $sPreflightUrl
                )
                . '">'
                . '<i class="sys-icon shield-alt"></i> '
                . 'Run Read-only Preflight'
                . '</a>'
                . '</div>';
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
        $aRuntimeRestoreExecution = null,
        $aRuntimeMergeExecution = null,
        $aRuntimeMergeBatchExecution = null
    )
    {
        $s = '<div class="mud-section">';
        $s .= '<h2>Repair Preparation</h2>';


        if (is_array($aRuntimeMergeExecution)) {
            if (
                !empty($aRuntimeMergeExecution['ok'])
                && !empty(
                    $aRuntimeMergeExecution[
                        'checkpointed'
                    ]
                )
                && empty(
                    $aRuntimeMergeExecution[
                        'runtime_files_changed'
                    ]
                )
            ) {
                $s .= '<div class="mud-note">'
                    . '<strong>RUNTIME MERGE CHECKPOINT CREATED — 0 FILES COPIED</strong><br>'
                    . 'Doctor verified the approved merge plan, '
                    . 'built the SHA-256 execution manifest, '
                    . 'persisted its durable sidecar and entered '
                    . '<code>runtime_merging</code>. '
                    . '<strong>No generated runtime destination '
                    . 'files were changed.</strong>'
                    . '</div>';
            } else {
                $s .= '<div class="mud-finding warning">'
                    . '<div class="mud-find-body">'
                    . '<div class="mud-find-head">'
                    . '<strong>Runtime merge checkpoint did not complete</strong>'
                    . '</div>'
                    . '<p>'
                    . $this->esc(
                        (string)(
                            $aRuntimeMergeExecution['error']
                            ?? 'Unknown runtime-merge checkpoint error.'
                        )
                    )
                    . '</p>'
                    . '</div></div>';
            }
        }


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
                            ?? 'Prepare Repair failed.'
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
                . '<strong>ACTIVE REPAIR SESSION</strong><br>'
                . 'Doctor is protecting your site while this repair '
                . 'is in progress.'
                . '</div>';

                        $s .= '<details'
                . $this->technicalDetailsOpenAttribute()
                . ' style="margin:14px 0 22px">';
            $s .= '<summary style="cursor:pointer;font-weight:600">'
                . 'Technical Details'
                . '</summary>';
            $s .= '<div style="margin-top:12px">';

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

            $s .= '</div></details>';

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
                    . 'Return Site to Normal Operation'
                    . '</h3>';

                $s .= '<div class="mud-note">'
                    . '<strong>Doctor is ready to return the site to normal operation.</strong> '
                    . 'Doctor will restore the site settings that were temporarily '
                    . 'changed for this repair and verify that normal operation '
                    . 'has been restored. '
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
                    . 'I approve Doctor returning the site to its '
                    . 'original operating state.'
                    . '</label>';

                $s .= '<button type="submit" '
                    . 'class="bx-btn">'
                    . '<i class="sys-icon undo"></i> '
                    . 'Return Site to Normal Operation'
                    . '</button>';

                $s .= '</form>';
            } elseif (
                $sStatus === 'runtime_merging'
            ) {
                /*
                 * Runtime merging is a durable mutation/recovery
                 * checkpoint. Do not expose Restore or Disarm
                 * until the merge executor has either completed
                 * verification or entered an explicit rollback
                 * state.
                 */
                $s .= '<hr style="margin:24px 0">';

                $s .= '<h3>Repair In Progress</h3>';

                $s .= '<div class="mud-finding warning">'
                    . '<div class="mud-sev">'
                    . '<i class="sys-icon shield-alt"></i>'
                    . '</div>'
                    . '<div class="mud-find-body">'
                    . '<div class="mud-find-head">'
                    . '<strong>SITE PROTECTION ACTIVE</strong>'
                    . '</div>'
                    . '<div class="mud-find-text">'
                    . 'Doctor is safely working through this repair. '
                    . 'Your protected recovery copy remains available while '
                    . 'Doctor keeps the site in a safe maintenance state. '
                    . 'Recovery controls stay protected until this repair step '
                    . 'has been fully verified.'
                    . '</div>'
                    . '</div>'
                    . '</div>';

                /*
                 * Show the result of the guarded one-file execution
                 * request before presenting another action.
                 */
                /*
                 * Render the bounded rollback result first and consume
                 * it so it can never fall through into the installer
                 * result renderer.
                 */
                if (
                    is_array(
                        $aRuntimeMergeBatchExecution
                    )
                    && (string)(
                        $aRuntimeMergeBatchExecution[
                            'operation'
                        ]
                        ?? ''
                    ) === 'batch_rollback'
                ) {
                    if (
                        !empty(
                            $aRuntimeMergeBatchExecution['ok']
                        )
                    ) {
                        $s .= '<div class="mud-finding ok">'
                            . '<div class="mud-sev">'
                            . '<i class="sys-icon check-circle"></i>'
                            . '</div>'
                            . '<div class="mud-find-body">'
                            . '<div class="mud-find-head">'
                            . '<strong>PREVIOUS REPAIR STEP SAFELY REVERSED</strong>'
                            . '</div>'
                            . '<div class="mud-find-text">'
                            . 'Requested batch size: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergeBatchExecution[
                                            'requested_count'
                                        ]
                                        ?? 5
                                    )
                                )
                            )
                            . '<br>Rolled back this request: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergeBatchExecution[
                                            'rolled_back_count'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . '<br>Starting ledger events: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergeBatchExecution[
                                            'starting_ledger_events'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . '<br>Final ledger events: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergeBatchExecution[
                                            'final_ledger_events'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . '<br>Remaining copied files: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergeBatchExecution[
                                            'copied_files'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . '<br>Remaining copied bytes: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergeBatchExecution[
                                            'copied_bytes'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . '</div>'
                            . '</div>'
                            . '</div>';
                    } else {
                        $s .= '<div class="mud-finding warning">'
                            . '<div class="mud-sev">'
                            . '<i class="sys-icon exclamation-triangle"></i>'
                            . '</div>'
                            . '<div class="mud-find-body">'
                            . '<div class="mud-find-head">'
                            . '<strong>Runtime merge batch rollback did not complete</strong>'
                            . '</div>'
                            . '<div class="mud-find-text">'
                            . $this->esc(
                                (string)(
                                    $aRuntimeMergeBatchExecution[
                                        'error'
                                    ]
                                    ?? 'Unknown runtime-merge batch rollback error.'
                                )
                            )
                            . '</div>'
                            . '</div>'
                            . '</div>';
                    }

                    $aRuntimeMergeBatchExecution = null;
                }

                /*
                 * Render only an explicitly identified batch-install
                 * result here.
                 */
                if (
                    is_array(
                        $aRuntimeMergeBatchExecution
                    )
                    && (string)(
                        $aRuntimeMergeBatchExecution[
                            'operation'
                        ]
                        ?? ''
                    ) === 'continue_repair'
                ) {
                    if (
                        !empty(
                            $aRuntimeMergeBatchExecution['ok']
                        )
                    ) {
                        $s .= '<div class="mud-note">'
                            . '<strong>CONTINUE REPAIR WORK CYCLE VERIFIED</strong><br>'
                            . 'Mode: '
                            . (
                                !empty(
                                    $aRuntimeMergeBatchExecution[
                                        'large_module_mode'
                                    ]
                                )
                                    ? '<strong>LARGE MODULE PROTECTED</strong>'
                                    : 'Standard protected cycle'
                            )
                            . '<br>Requested files this cycle: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergeBatchExecution[
                                            'requested_files'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . '<br>Installed this request: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergeBatchExecution[
                                            'installed_count'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . '<br>Installed bytes: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergeBatchExecution[
                                            'installed_bytes'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . '<br>Next manifest index: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergeBatchExecution[
                                            'end_index_exclusive'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . '<br>Progress: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergeBatchExecution[
                                            'progress_completed'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . ' / '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergeBatchExecution[
                                            'manifest_count'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . ' — '
                            . $this->esc(
                                number_format(
                                    (float)(
                                        $aRuntimeMergeBatchExecution[
                                            'progress_percent'
                                        ]
                                        ?? 0
                                    ),
                                    1
                                )
                            )
                            . '%'
                            . '<br>Remaining files: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergeBatchExecution[
                                            'remaining_files'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . '<br>Approx. protected cycles remaining: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergeBatchExecution[
                                            'estimated_clicks_remaining'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . '<br>Final ledger events: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergeBatchExecution[
                                            'final_ledger_events'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . '<br>Verified internal batch operations: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergeBatchExecution[
                                            'batches_completed'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . '<br>Verified 250-file work cycles: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergeBatchExecution[
                                            'work_cycles_completed'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . '<br>Elapsed: '
                            . $this->esc(
                                number_format(
                                    (float)(
                                        $aRuntimeMergeBatchExecution[
                                            'elapsed_seconds'
                                        ]
                                        ?? 0
                                    ),
                                    2
                                )
                            )
                            . ' sec'
                            . (
                                !empty(
                                    $aRuntimeMergeBatchExecution[
                                        'time_limit_reached'
                                    ]
                                )
                                    ? '<br><strong>TIME CEILING REACHED AT VERIFIED CHECKPOINT</strong>'
                                    : ''
                            )
                            . '</div>';
                    } else {
                        $s .= '<div class="mud-finding warning">'
                            . '<div class="mud-sev">'
                            . '<i class="sys-icon exclamation-triangle"></i>'
                            . '</div>'
                            . '<div class="mud-find-body">'
                            . '<div class="mud-find-head">'
                            . '<strong>Continue Repair did not complete</strong>'
                            . '</div>'
                            . '<div class="mud-find-text">'
                            . $this->esc(
                                (string)(
                                    $aRuntimeMergeBatchExecution[
                                        'error'
                                    ]
                                    ?? 'Unknown runtime-merge batch execution error.'
                                )
                            )
                            . '</div>'
                            . '</div>'
                            . '</div>';
                    }

                    $aRuntimeMergeBatchExecution = null;
                }

                /*
                 * Fail closed if some unexpected result shape reaches
                 * this renderer.
                 */
                if (
                    is_array(
                        $aRuntimeMergeBatchExecution
                    )
                ) {
                    $s .= '<div class="mud-finding warning">'
                        . '<div class="mud-sev">'
                        . '<i class="sys-icon exclamation-triangle"></i>'
                        . '</div>'
                        . '<div class="mud-find-body">'
                        . '<div class="mud-find-head">'
                        . '<strong>Unknown runtime merge operation result</strong>'
                        . '</div>'
                        . '<div class="mud-find-text">'
                        . 'Doctor refused to interpret an unexpected '
                        . 'runtime-merge result type.'
                        . '</div>'
                        . '</div>'
                        . '</div>';

                    $aRuntimeMergeBatchExecution = null;
                }

                $sCsrfToken =
                    BxDolForm::getCsrfToken();

                $sRuntimeMergeActionUrl =
                    BX_DOL_URL_STUDIO
                    . 'module.php?name=my_una_doctor'
                    . '&page=repair_candidates';

                $iRuntimeMergeJournalId =
                    (int)(
                        $aActiveRepairJournal['id']
                        ?? 0
                    );

        /*
         * Production runtime-merge state.
         *
         * The whole-manifest planner independently proves the active
         * runtime-merge journal, protected-runtime state, durable
         * ledger synchronization and absence of unresolved intent.
         */
        $aRuntimeMergeNextBatchState =
            $this->oDoctor
                ->getRuntimeMergeNextBatchState(
                    $iRuntimeMergeJournalId
                );

        $bRuntimeMergeNextBatchReady =
            !empty(
                $aRuntimeMergeNextBatchState['ok']
            )
            && (int)(
                $aRuntimeMergeNextBatchState[
                    'unjournaled_events'
                ]
                ?? -1
            ) === 0
            && (int)(
                $aRuntimeMergeNextBatchState[
                    'event_count'
                ]
                ?? -1
            ) === (int)(
                $aRuntimeMergeNextBatchState[
                    'journal_event_count'
                ]
                ?? -2
            );

        if (!$bRuntimeMergeNextBatchReady) {
            $s .= '<div class="mud-finding warning">'
                . '<div class="mud-sev">'
                . '<i class="sys-icon exclamation-triangle"></i>'
                . '</div>'
                . '<div class="mud-find-body">'
                . '<div class="mud-find-head">'
                . '<strong>Runtime merge controls locked</strong>'
                . '</div>'
                . '<div class="mud-find-text">'
                . 'Doctor could not prove a synchronized '
                . 'whole-manifest runtime-merge state. '
                . 'No forward repair action is available.'
                . (
                    !empty(
                        $aRuntimeMergeNextBatchState['error']
                    )
                        ? '<br><br>'
                            . $this->esc(
                                (string)
                                $aRuntimeMergeNextBatchState[
                                    'error'
                                ]
                            )
                        : ''
                )
                . '</div>'
                . '</div>'
                . '</div>';
        } else {
            $s .= '<div class="mud-note">'
                . '<strong>SAFETY CHECK PASSED</strong><br>'
                . 'Doctor verified the current whole-manifest '
                . 'repair state and confirmed that it is synchronized.'
                . '</div>';

            $s .= '<details'
                . $this->technicalDetailsOpenAttribute()
                . ' style="margin:-10px 0 22px">'
                . '<summary style="cursor:pointer;font-weight:600">'
                . 'Technical Details'
                . '</summary>'
                . '<div style="margin-top:10px">'
                . 'Installed files: '
                . $this->esc(
                    number_format(
                        (int)(
                            $aRuntimeMergeNextBatchState[
                                'installed_count'
                            ]
                            ?? 0
                        )
                    )
                )
                . '<br>Remaining files: '
                . $this->esc(
                    number_format(
                        (int)(
                            $aRuntimeMergeNextBatchState[
                                'passive_count'
                            ]
                            ?? 0
                        )
                    )
                )
                . '<br>Next batch: '
                . $this->esc(
                    number_format(
                        (int)(
                            $aRuntimeMergeNextBatchState[
                                'next_batch_files'
                            ]
                            ?? 0
                        )
                    )
                )
                . '<br>Ledger events: '
                . $this->esc(
                    number_format(
                        (int)(
                            $aRuntimeMergeNextBatchState[
                                'event_count'
                            ]
                            ?? 0
                        )
                    )
                )
                . '</div>'
                . '</details>';

            if (
                empty(
                    $aRuntimeMergeNextBatchState[
                        'complete'
                    ]
                )
                && (int)(
                    $aRuntimeMergeNextBatchState[
                        'next_batch_files'
                    ]
                    ?? 0
                ) > 0
            ) {
                $s .= '<div class="mud-note">'
                    . '<strong>READY TO CONTINUE</strong><br>'
                    . 'Doctor has verified the next protected repair step. '
                    . 'Your recovery protection remains active.'
                    . '</div>';

                $s .= '<form method="post" action="'
                    . $this->esc(
                        $sRuntimeMergeActionUrl
                    )
                    . '">';

                $s .= '<input type="hidden" '
                    . 'name="mud_action" '
                    . 'value="continue_repair">';

                $s .= '<input type="hidden" '
                    . 'name="journal_id" value="'
                    . $this->esc(
                        (string)$iRuntimeMergeJournalId
                    )
                    . '">';

                $s .= '<input type="hidden" '
                    . 'name="expected_ledger_events" value="'
                    . $this->esc(
                        (string)(
                            $aRuntimeMergeNextBatchState[
                                'event_count'
                            ]
                            ?? -1
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
                    . 'name="confirm_runtime_merge_batch" '
                    . 'value="yes" required> '
                    . 'I approve Doctor continuing this repair '
                    . 'from the verified safety checkpoint.'
                    . '</label>';

                $s .= '<button type="submit" '
                    . 'class="bx-btn">'
                    . '<i class="sys-icon play"></i> '
                    . 'Continue Repair'
                    . '</button>';

                $s .= '</form>';

                /*
                 * Browser-driven protected Auto Continue.
                 *
                 * This does not create a new repair endpoint. It submits
                 * the existing Continue Repair form after each page has
                 * returned from a successful synchronized repair cycle.
                 *
                 * sessionStorage makes Auto Continue tab-scoped. Closing
                 * the tab ends the browser-driven continuation.
                 */
                $s .= <<<'MUDAUTOCONTINUE'
<div id="mud-auto-continue-controls"
     style="margin:16px 0 22px;padding:16px;border:1px solid rgba(128,128,128,.35);border-radius:8px">

    <strong>Automatic Protected Repair</strong><br>

    <span style="display:block;margin:6px 0 12px">
        Doctor can automatically continue one verified protected cycle
        at a time. Every cycle must return to a synchronized safety
        checkpoint before the next cycle is submitted.
    </span>

    <button type="button"
            data-mud-auto-start
            class="bx-btn">
        Start Auto Continue
    </button>

    <button type="button"
            data-mud-auto-stop
            class="bx-btn"
            style="display:none">
        Stop Auto Continue
    </button>

    <div data-mud-auto-status
         style="margin-top:10px;font-weight:600">
        Auto Continue is OFF.
    </div>
</div>

<script>
(function () {
    'use strict';

    var root =
        document.getElementById(
            'mud-auto-continue-controls'
        );

    if (!root) {
        return;
    }

    /*
     * Discover Doctor's existing protected form dynamically.
     * We deliberately do not duplicate journal IDs, ledger counters,
     * confirmations or other protected form fields.
     */
    var forms =
        Array.prototype.slice.call(
            document.forms
        );

    var form =
        forms.find(function (candidate) {
            return !!candidate.querySelector(
                'input[name="mud_action"][value="continue_repair"]'
            );
        });

    if (!form) {
        root.style.display = 'none';
        return;
    }

    var journalInput =
        form.querySelector(
            'input[name="journal_id"]'
        );

    if (!journalInput || !journalInput.value) {
        root.style.display = 'none';
        return;
    }

    var storageKey =
        'mudAutoContinueJournal:'
        + journalInput.value;

    var startButton =
        root.querySelector(
            '[data-mud-auto-start]'
        );

    var stopButton =
        root.querySelector(
            '[data-mud-auto-stop]'
        );

    var status =
        root.querySelector(
            '[data-mud-auto-status]'
        );

    var countdownTimer = null;
    var submitted = false;

    function isEnabled()
    {
        return sessionStorage.getItem(
            storageKey
        ) === 'yes';
    }

    function setEnabled(enabled)
    {
        if (enabled) {
            sessionStorage.setItem(
                storageKey,
                'yes'
            );
        } else {
            sessionStorage.removeItem(
                storageKey
            );
        }
    }

    function showState(message)
    {
        var enabled = isEnabled();

        startButton.style.display =
            enabled ? 'none' : '';

        stopButton.style.display =
            enabled ? '' : 'none';

        status.textContent =
            message
            || (
                enabled
                    ? 'Auto Continue is ON.'
                    : 'Auto Continue is OFF.'
            );
    }

    /*
     * Starting Auto Continue is the administrator's approval to
     * repeat this same protected Continue Repair action. Check the
     * approval checkbox already belonging to Doctor's form.
     */
    function approveProtectedForm()
    {
        var checks =
            form.querySelectorAll(
                'input[type="checkbox"]'
            );

        Array.prototype.forEach.call(
            checks,
            function (checkbox) {
                checkbox.checked = true;
            }
        );
    }

    function submitProtectedCycle()
    {
        if (
            !isEnabled()
            || submitted
        ) {
            return;
        }

        submitted = true;

        approveProtectedForm();

        showState(
            'Submitting the next protected repair cycle...'
        );

        if (
            typeof form.requestSubmit
            === 'function'
        ) {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    function stopAuto(message)
    {
        setEnabled(false);

        if (countdownTimer) {
            clearInterval(
                countdownTimer
            );

            countdownTimer = null;
        }

        showState(
            message
            || 'Auto Continue is OFF.'
        );
    }

    startButton.addEventListener(
        'click',
        function () {
            setEnabled(true);

            showState(
                'Auto Continue started. '
                + 'Submitting the first protected cycle...'
            );

            window.setTimeout(
                submitProtectedCycle,
                400
            );
        }
    );

    stopButton.addEventListener(
        'click',
        function () {
            stopAuto(
                'Auto Continue stopped by administrator.'
            );
        }
    );

    showState();

    /*
     * Nothing else happens unless the administrator previously
     * enabled Auto Continue in this browser tab.
     */
    if (!isEnabled()) {
        return;
    }

    var pageText =
        document.body
            ? document.body.innerText
            : '';

    var cycleVerified =
        pageText.indexOf(
            'CONTINUE REPAIR WORK CYCLE VERIFIED'
        ) !== -1;

    var safetyPassed =
        pageText.indexOf(
            'SAFETY CHECK PASSED'
        ) !== -1;

    var ready =
        pageText.indexOf(
            'READY TO CONTINUE'
        ) !== -1;

    var failed =
        pageText.indexOf(
            'Continue Repair did not complete'
        ) !== -1
        || pageText.indexOf(
            'Runtime merge controls locked'
        ) !== -1;

    /*
     * Fail closed.
     *
     * Auto Continue only proceeds when this exact page came back from
     * a successful verified cycle AND Doctor independently rendered
     * both the synchronized safety check and READY TO CONTINUE.
     */
    if (
        failed
        || !cycleVerified
        || !safetyPassed
        || !ready
    ) {
        stopAuto(
            'Auto Continue stopped because Doctor did not return '
            + 'a fully verified READY TO CONTINUE checkpoint.'
        );

        return;
    }

    var seconds = 5;

    showState(
        'Verified checkpoint passed. '
        + 'Next protected cycle in '
        + seconds
        + ' seconds...'
    );

    countdownTimer =
        window.setInterval(
            function () {
                seconds--;

                if (seconds <= 0) {
                    clearInterval(
                        countdownTimer
                    );

                    countdownTimer = null;

                    submitProtectedCycle();

                    return;
                }

                showState(
                    'Verified checkpoint passed. '
                    + 'Next protected cycle in '
                    + seconds
                    + ' seconds...'
                );
            },
            1000
        );

    window.addEventListener(
        'beforeunload',
        function () {
            if (countdownTimer) {
                clearInterval(
                    countdownTimer
                );
            }
        }
    );
})();
</script>
MUDAUTOCONTINUE;

            } elseif (
                !empty(
                    $aRuntimeMergeNextBatchState[
                        'complete'
                    ]
                )
            ) {
                $s .= '<div class="mud-note">'
                    . '<strong>RUNTIME MERGE COMPLETE</strong><br>'
                    . 'Doctor verified that no passive runtime files '
                    . 'remain in the merge manifest.'
                    . '</div>';

                $s .= '<div class="mud-note" '
                    . 'style="margin-top:16px">'
                    . '<strong>SUCCESSFUL REPAIR READY TO FINALIZE</strong><br>'
                    . 'Doctor will keep the newly merged runtime in place, '
                    . 'restore the original maintenance, compression and '
                    . 'generator settings, independently verify the final '
                    . 'site state, and then close this repair journal.'
                    . '</div>';

                $sCsrfToken =
                    BxDolForm::getCsrfToken();

                $s .= '<form method="post" action="'
                    . $this->esc(
                        $sRuntimeMergeActionUrl
                    )
                    . '">';

                $s .= '<input type="hidden" '
                    . 'name="mud_action" '
                    . 'value="finalize_successful_runtime_merge">';

                $s .= '<input type="hidden" '
                    . 'name="journal_id" value="'
                    . $this->esc(
                        (string)$iRuntimeMergeJournalId
                    )
                    . '">';

                $s .= '<input type="hidden" '
                    . 'name="expected_ledger_events" value="'
                    . $this->esc(
                        (string)(
                            $aRuntimeMergeNextBatchState[
                                'event_count'
                            ]
                            ?? -1
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
                    . 'name="confirm_runtime_merge_finalize" '
                    . 'value="yes" required> '
                    . 'I approve Doctor finalizing this verified successful repair '
                    . 'and restoring the original site-control settings.'
                    . '</label>';

                $s .= '<button type="submit" '
                    . 'class="bx-btn">'
                    . '<i class="sys-icon check"></i> '
                    . 'Finalize Successful Repair'
                    . '</button>';

                $s .= '</form>';
            } else {
                $s .= '<div class="mud-finding warning">'
                    . '<div class="mud-sev">'
                    . '<i class="sys-icon exclamation-triangle"></i>'
                    . '</div>'
                    . '<div class="mud-find-body">'
                    . '<div class="mud-find-head">'
                    . '<strong>Runtime merge state is not actionable</strong>'
                    . '</div>'
                    . '<div class="mud-find-text">'
                    . 'Doctor proved the durable whole-manifest state, '
                    . 'but no valid next forward batch is available.'
                    . '</div>'
                    . '</div>'
                    . '</div>';
            }
        }

                /*
                 * Settings-aware protected rollback work-cycle control.
                 */
                if (
                    !empty(
                        $aRuntimeMergeNextBatchState['ok']
                    )
                    && empty(
                        $aRuntimeMergeNextBatchState[
                            'complete'
                        ]
                    )
                    && (int)(
                        $aRuntimeMergeNextBatchState[
                            'installed_count'
                        ]
                        ?? -1
                    ) > 0
                    && (int)(
                        $aRuntimeMergeNextBatchState[
                            'installed_count'
                        ]
                        ?? -1
                    ) ===
                    (int)(
                        $aRuntimeMergeNextBatchState[
                            'next_start_index'
                        ]
                        ?? -2
                    )
                    && (int)(
                        $aRuntimeMergeNextBatchState[
                            'unjournaled_events'
                        ]
                        ?? -1
                    ) === 0
                    && (int)(
                        $aRuntimeMergeNextBatchState[
                            'event_count'
                        ]
                        ?? -1
                    ) ===
                    (int)(
                        $aRuntimeMergeNextBatchState[
                            'journal_event_count'
                        ]
                        ?? -2
                    )
                ) {
                    $iWorkCycleSize =
                        $this->runtimeMergeWorkCycleSize();

                    $s .= '<hr style="margin:24px 0">';

                    $s .= '<div class="mud-note">'
                        . '<strong>PROTECTED ROLLBACK WORK CYCLE READY</strong><br>'
                        . 'Doctor will reverse up to '
                        . $this->esc(
                            (string)$iWorkCycleSize
                        )
                        . ' of the most recently installed runtime files '
                        . 'using separately verified 10-file rollback chunks.'
                        . '</div>';

                    $s .= '<form method="post" action="'
                        . $this->esc(
                            $sRuntimeMergeActionUrl
                        )
                        . '">';

                    $s .= '<input type="hidden" '
                        . 'name="mud_action" '
                        . 'value="runtime_merge_rollback_work_cycle">';

                    $s .= '<input type="hidden" '
                        . 'name="journal_id" value="'
                        . $this->esc(
                            (string)$iRuntimeMergeJournalId
                        )
                        . '">';

                    $s .= '<input type="hidden" '
                        . 'name="expected_ledger_events" value="'
                        . $this->esc(
                            (string)(
                                $aRuntimeMergeNextBatchState[
                                    'event_count'
                                ]
                                ?? -1
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
                        . 'name="confirm_runtime_merge_rollback_work_cycle" '
                        . 'value="yes" required> '
                        . 'I approve Doctor running this protected '
                        . $this->esc(
                            (string)$iWorkCycleSize
                        )
                        . '-file rollback work cycle.'
                        . '</label>';

                    $s .= '<button type="submit" '
                        . 'class="bx-btn">'
                        . '<i class="sys-icon undo"></i> '
                        . 'Continue Rollback — Up to '
                        . $this->esc(
                            (string)$iWorkCycleSize
                        )
                        . ' Files'
                        . '</button>';

                    $s .= '</form>';
                }

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

                /*
                 * Runtime merge checkpoint is available only
                 * from a fully verified runtime_protected state.
                 * This stage hashes and persists the execution
                 * contract but copies ZERO generated files.
                 */
                if ($bProtected) {
                    $iRuntimeJournalId =
                        (int)(
                            $aActiveRepairJournal['id']
                            ?? 0
                        );

                    $aRuntimeMergePlan =
                        $iRuntimeJournalId > 0
                            ? $this->oDoctor
                                ->getPostUpdateRuntimeMergePlan(
                                    $iRuntimeJournalId
                                )
                            : array();

                    $s .= '<hr style="margin:24px 0">';

                    $s .= '<h3>'
                        . 'Prepare Runtime Merge Checkpoint'
                        . '</h3>';

                    if (
                        !empty($aRuntimeMergePlan['ok'])
                        && !empty(
                            $aRuntimeMergePlan['ready']
                        )
                        && !empty(
                            $aRuntimeMergePlan[
                                'merge_plan_id'
                            ]
                        )
                    ) {
                        $sMergePlanId =
                            (string)$aRuntimeMergePlan[
                                'merge_plan_id'
                            ];

                        $s .= '<div class="mud-note">'
                            . '<strong>READY FOR CRYPTOGRAPHIC MERGE CHECKPOINT</strong><br>'
                            . 'Merge Plan ID: <code>'
                            . $this->esc($sMergePlanId)
                            . '</code><br>'
                            . 'Restore candidates: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergePlan[
                                            'restore_candidate_count'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . ' &nbsp;|&nbsp; Old tracked skipped: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergePlan[
                                            'old_tracked_count'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . ' &nbsp;|&nbsp; Newly tracked collisions: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergePlan[
                                            'newly_tracked_collision_count'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . ' &nbsp;|&nbsp; Live collisions: '
                            . $this->esc(
                                number_format(
                                    (int)(
                                        $aRuntimeMergePlan[
                                            'live_collision_count'
                                        ]
                                        ?? 0
                                    )
                                )
                            )
                            . '<br><br>'
                            . 'Doctor will recompute the approved plan, '
                            . 'SHA-256 hash every approved generated '
                            . 'runtime source, atomically persist the '
                            . 'journal-specific execution manifest and '
                            . 'enter <code>runtime_merging</code>. '
                            . '<strong>ZERO generated files will be '
                            . 'copied during this checkpoint.</strong> '
                            . 'The maintenance, template-compression '
                            . 'and generator barriers remain active.'
                            . '</div>';

                        $s .= '<form method="post" action="'
                            . $this->esc(
                                $sRuntimeRestoreActionUrl
                            )
                            . '">';

                        $s .= '<input type="hidden" '
                            . 'name="mud_action" '
                            . 'value="begin_runtime_merge">';

                        $s .= '<input type="hidden" '
                            . 'name="journal_id" value="'
                            . $this->esc(
                                (string)$iRuntimeJournalId
                            )
                            . '">';

                        $s .= '<input type="hidden" '
                            . 'name="merge_plan_id" value="'
                            . $this->esc(
                                $sMergePlanId
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
                            . 'name="confirm_runtime_merge" '
                            . 'value="yes" required> '
                            . 'I approve creating the cryptographic '
                            . 'runtime merge checkpoint for repair '
                            . 'journal '
                            . $this->esc(
                                (string)$iRuntimeJournalId
                            )
                            . '. I understand that this checkpoint '
                            . 'copies zero generated runtime files '
                            . 'and locks the control barrier in '
                            . '<code>runtime_merging</code>.'
                            . '</label>';

                        $s .= '<button type="submit" '
                            . 'class="bx-btn">'
                            . '<i class="sys-icon fingerprint"></i> '
                            . 'Create Merge Checkpoint — Copy 0 Files'
                            . '</button>';

                        $s .= '</form>';

                    } else {
                        $s .= '<div class="mud-finding warning">'
                            . '<div class="mud-find-body">'
                            . '<div class="mud-find-head">'
                            . '<strong>Runtime merge checkpoint is not ready</strong>'
                            . '</div>'
                            . '<p>'
                            . $this->esc(
                                (string)(
                                    $aRuntimeMergePlan['error']
                                    ?? 'The current runtime merge plan did not pass all safety gates.'
                                )
                            )
                            . '</p>'
                            . '</div></div>';
                    }
                }

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
                . '<strong>Your recovery copy remains protected throughout this repair.</strong> '
                . 'Doctor verifies the recovery copy and safety checks '
                . 'before making protected file changes.'
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

        $s .= '<h3>Prepare Repair</h3>';

        $s .= '<div class="mud-note">'
            . '<strong>Doctor is ready to prepare the site safely.</strong> '
            . 'Doctor will protect the repair session, place the site '
            . 'in a safe maintenance state and temporarily pause only '
            . 'the verified background task required for this repair. '
            . '<strong>No runtime files will be moved yet.</strong>'
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
            . 'I approve Doctor preparing the site for this repair. '
            . 'No runtime files will be moved yet.'
            . '</label>';

        $s .= '<button type="submit" class="bx-btn">'
            . '<i class="sys-icon shield-alt"></i> '
            . 'Prepare Repair'
            . '</button>';

        $s .= '</form>';


        $s .= '<div class="mud-note">'
            . '<strong>Prepare Repair establishes the site safety protections first.</strong> '
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

        $bReferencePreflight =
            (string)(
                $aPreflight['rule']
                ?? ''
            )
            === 'verified_module_reference_drift';

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

        $bPreflightComplete =
            !empty(
                $aPreflight[
                    'preflight_complete'
                ]
            );

        if (
            $bReferencePreflight
            && $bPreflightComplete
            && !$bReady
        ) {
            $s .= '<div class="mud-note">'
                . '<strong>'
                . 'READ-ONLY PREFLIGHT COMPLETE'
                . '</strong><br>'
                . 'All implemented verified-reference safety checks passed. '
                . 'Repair arming remains deliberately disabled at this development stage.'
                . '<br><strong>No changes were made.</strong>'
                . '</div>';
        } else {
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
        }

        if ($bReferencePreflight) {
            $s .= '<div class="mud-note">'
                . '<strong>Verified reference:</strong> '
                . $this->esc(
                    (string)(
                        $aPreflight[
                            'reference_sha256'
                        ]
                        ?? ''
                    )
                )
                . '<br><strong>Module version:</strong> '
                . $this->esc(
                    (string)(
                        $aPreflight[
                            'module_version'
                        ]
                        ?? ''
                    )
                )
                . '<br><strong>Preflight ID:</strong> '
                . $this->esc(
                    (string)(
                        $aPreflight[
                            'preflight_id'
                        ]
                        ?? ''
                    )
                )
                . '<br><strong>Arm available:</strong> '
                . (
                    !empty(
                        $aPreflight[
                            'arm_available'
                        ]
                    )
                        ? 'YES'
                        : 'NO'
                )
                . '<br><strong>Ready for arm:</strong> '
                . (
                    !empty(
                        $aPreflight[
                            'ready_for_arm'
                        ]
                    )
                        ? 'YES'
                        : 'NO'
                )
                . '<br><strong>Changes applied:</strong> '
                . (
                    !empty(
                        $aPreflight[
                            'changes_applied'
                        ]
                    )
                        ? 'YES'
                        : 'NO'
                )
                . '</div>';
        }

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

        if (
            $bReferencePreflight
            && !empty(
                $aPreflight[
                    'operations'
                ]
            )
            && is_array(
                $aPreflight[
                    'operations'
                ]
            )
        ) {
            $s .= '<h3 style="margin-top:20px">'
                . 'Verified Future Operations'
                . '</h3>';

            $s .= '<div class="mud-note">'
                . 'These are classifications only. '
                . 'Preflight does not restore, create, quarantine, move or delete any file.'
                . '</div>';

            $s .= '<div class="mud-table-wrap">';
            $s .= '<table class="mud-table">';
            $s .= '<thead><tr>'
                . '<th>Future action</th>'
                . '<th>File</th>'
                . '<th>Path safety</th>'
                . '<th>Future write check</th>'
                . '</tr></thead><tbody>';

            foreach (
                $aPreflight[
                    'operations'
                ]
                as $aOperation
            ) {
                $s .= '<tr>'
                    . '<td><strong>'
                    . $this->esc(
                        (string)(
                            $aOperation[
                                'action'
                            ]
                            ?? ''
                        )
                    )
                    . '</strong></td>'

                    . '<td>'
                    . $this->esc(
                        (string)(
                            $aOperation[
                                'file'
                            ]
                            ?? ''
                        )
                    )
                    . '</td>'

                    . '<td>'
                    . $this->esc(
                        (string)(
                            $aOperation[
                                'path_status'
                            ]
                            ?? ''
                        )
                    )
                    . '</td>'

                    . '<td>'
                    . $this->esc(
                        (string)(
                            $aOperation[
                                'write_status'
                            ]
                            ?? ''
                        )
                    )
                    . '</td>'
                    . '</tr>';
            }

            $s .= '</tbody></table></div>';
        }

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
                . 'The verified background task can be paused only after an explicitly approved Prepare Repair.'
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

        if ($bReferencePreflight) {
            $s .= '<div class="mud-note">'
                . '<strong>Reference repair arming remains disabled.</strong> '
                . 'This preflight performs no file restoration, file creation, '
                . 'quarantine, deletion, database mutation, repair-journal creation '
                . 'or checksum re-baselining.'
                . '</div>';
        } else {
            $s .= '<div class="mud-note">'
                . '<strong>Apply Repair remains disabled.</strong> '
                . 'Preflight performs no cron mutation, file movement, database mutation, '
                . 'module update or checksum re-baselining.'
                . '</div>';
        }

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
                $s .= '<div><strong>What this means:</strong> '
                    . $this->esc(
                        $aHuman['health_impact']
                    )
                    . '</div>';
            }

            if (!empty($aHuman['repair_required'])) {
                $s .= '<div><strong>What happens next:</strong> '
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
                $s .= '<details'
                    . $this->technicalDetailsOpenAttribute()
                    . ' class="mud-evidence">'
                    . '<summary>Technical evidence</summary>'
                    . '<pre>'
                    . $this->esc($sJson)
                    . '</pre></details>';
            }
        }

        return $s;
    }


    protected function runtimeMergeWorkCycleSize()
    {
        $iSize =
            (int)getParam(
                'my_una_doctor_work_cycle_size'
            );

        /*
         * Only explicitly supported values are accepted.
         *
         * Invalid, missing or manually tampered values fall
         * back to 30. The administrator setting can therefore
         * never raise Doctor above its hard browser-cycle limit.
         */
        if (
            !in_array(
                $iSize,
                array(10, 20, 30),
                true
            )
        ) {
            $iSize = 30;
        }

        return min(
            30,
            max(10, $iSize)
        );
    }


    protected function technicalDetailsOpenAttribute()
    {
        return (
            (string)getParam(
                'my_una_doctor_expand_technical_details'
            ) === 'on'
        )
            ? ' open'
            : '';
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

        .mud-activity-overlay{
            position:fixed;
            inset:0;
            z-index:999999;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px;
            background:rgba(0,0,0,.58);
            backdrop-filter:blur(2px)
        }
        .mud-activity-panel{
            width:min(520px,calc(100vw - 40px));
            padding:28px;
            border-radius:16px;
            background:Canvas;
            color:CanvasText;
            box-shadow:0 18px 60px rgba(0,0,0,.35);
            text-align:center
        }
        .mud-activity-panel strong{
            display:block;
            margin-top:14px;
            font-size:22px
        }
        .mud-activity-copy{
            margin-top:8px;
            line-height:1.5;
            opacity:.78
        }
        .mud-activity-warning{
            margin-top:14px;
            font-weight:700;
            font-size:13px
        }
        .mud-activity-detail{
            display:flex;
            justify-content:space-between;
            gap:16px;
            margin-top:10px;
            font-size:12px;
            opacity:.68
        }
        .mud-activity-phase{text-align:left}
        .mud-activity-elapsed{
            flex:none;
            font-variant-numeric:tabular-nums
        }
        .mud-activity-icon{
            width:44px;
            height:44px;
            margin:0 auto;
            border:4px solid rgba(128,128,128,.28);
            border-top-color:currentColor;
            border-radius:50%;
            animation:mud-doctor-spin .8s linear infinite
        }
        .mud-activity-track{
            position:relative;
            height:10px;
            margin-top:20px;
            overflow:hidden;
            border-radius:999px;
            background:rgba(128,128,128,.22)
        }
        .mud-activity-track span{
            position:absolute;
            top:0;
            bottom:0;
            left:-38%;
            width:38%;
            border-radius:999px;
            background:currentColor;
            opacity:.7;
            animation:mud-doctor-progress 1.15s ease-in-out infinite
        }
        .mud-working-button{
            pointer-events:none!important;
            opacity:.72!important
        }
        @keyframes mud-doctor-spin{
            to{transform:rotate(360deg)}
        }
        @keyframes mud-doctor-progress{
            0%{left:-38%}
            100%{left:100%}
        }
        @media (prefers-reduced-motion:reduce){
            .mud-activity-icon,
            .mud-activity-track span{
                animation-duration:2.5s
            }
        }
        </style>

        <script>
        const mudShowActivity = ' . (
            (string)getParam(
                'my_una_doctor_show_activity'
            ) === 'on'
                ? 'true'
                : 'false'
        ) . ';

        function mudStartActivityMotion(overlay, phases) {
            const phaseNode = overlay.querySelector(".mud-activity-phase");
            const elapsedNode = overlay.querySelector(".mud-activity-elapsed");
            const startedAt = Date.now();
            const activityPhases =
                Array.isArray(phases) && phases.length
                    ? phases
                    : ["Starting…", "Working…", "Still working safely…"];

            const updateActivity = function() {
                const elapsedSeconds = Math.max(
                    0,
                    Math.floor((Date.now() - startedAt) / 1000)
                );
                const minutes = Math.floor(elapsedSeconds / 60);
                const seconds = String(elapsedSeconds % 60).padStart(2, "0");
                const phaseIndex =
                    Math.floor(elapsedSeconds / 5) % activityPhases.length;

                if (phaseNode)
                    phaseNode.textContent = activityPhases[phaseIndex];

                if (elapsedNode)
                    elapsedNode.textContent =
                        minutes + ":" + seconds + " elapsed";
            };

            updateActivity();
            overlay.dataset.mudActivityTimer =
                String(window.setInterval(updateActivity, 1000));
        }

        function mudContinueAfterPaint(callback) {
            window.requestAnimationFrame(function() {
                window.requestAnimationFrame(callback);
            });
        }

        document.addEventListener("submit", function(event) {
            const form = event.target;

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            const actionInput =
                form.querySelector("input[name=mud_action]");
            const referenceAction =
                actionInput ? actionInput.value : "";
            const isReferenceComparison =
                !!form.closest(".mud-ref") &&
                (
                    referenceAction === "compare_reference_package" ||
                    referenceAction === "compare_saved_reference"
                );

            if (!form.closest(".mud-wrap") && !isReferenceComparison) {
                return;
            }

            if (!form.querySelector("input[name=mud_action]")) {
                return;
            }

            if (form.dataset.mudRelease === "1") {
                delete form.dataset.mudRelease;
                return;
            }

            if (form.dataset.mudBusy === "1") {
                event.preventDefault();
                return;
            }

            form.dataset.mudBusy = "1";

            const button =
                event.submitter ||
                form.querySelector(
                    "button[type=submit],input[type=submit]"
                );

            if (button) {
                button.classList.add("mud-working-button");
                button.setAttribute("aria-disabled", "true");

                if (button.tagName === "INPUT") {
                    button.value = "Doctor Is Working…";
                } else {
                    button.textContent = "Doctor Is Working…";
                }
            }

            /*
             * Button locking and duplicate-submit protection remain
             * active even when the visual activity graphic is disabled.
             */
            if (!mudShowActivity) {
                return;
            }

            const overlay = document.createElement("div");
            overlay.className = "mud-activity-overlay";

            overlay.innerHTML = `
                <div class="mud-activity-panel"
                     role="status"
                     aria-live="assertive">
                    <div class="mud-activity-icon"
                         aria-hidden="true"></div>

                    <strong>Doctor is working…</strong>

                    <div class="mud-activity-copy">
                        Protecting, verifying, and recording
                        this repair step.
                    </div>

                    <div class="mud-activity-track"
                         aria-hidden="true">
                        <span></span>
                    </div>

                    <div class="mud-activity-detail"
                         aria-hidden="true">
                        <span class="mud-activity-phase">Starting…</span>
                        <span class="mud-activity-elapsed">0:00 elapsed</span>
                    </div>

                    <div class="mud-activity-warning">
                        Do not refresh or submit again.
                    </div>
                </div>
            `;

            if (isReferenceComparison) {
                overlay.querySelector("strong").textContent =
                    referenceAction === "compare_reference_package"
                        ? "Doctor is uploading and comparing the reference ZIP…"
                        : "Doctor is comparing the saved reference…";

                overlay.querySelector(".mud-activity-copy").textContent =
                    "Checking the reference package against the installed module. "
                    + "This comparison does not replace or modify live module files.";

                overlay.querySelector(".mud-activity-warning").textContent =
                    "Keep this page open until the comparison finishes. Do not submit again.";
            }

            document.body.appendChild(overlay);

            mudStartActivityMotion(
                overlay,
                [
                    "Securing the request…",
                    "Verifying current evidence…",
                    "Recording the result…",
                    "Still working safely…"
                ]
            );

            /*
             * Give the browser two frames to paint the activity popup
             * before the long-running submission begins.
             */
            event.preventDefault();

            mudContinueAfterPaint(function() {
                form.dataset.mudRelease = "1";

                if (button && typeof form.requestSubmit === "function")
                    form.requestSubmit(button);
                else
                    form.submit();
            });
        }, true);

        /* MUD_LONG_GET_ACTIVITY_START */

        /*
         * Long-running Doctor GET actions.
         *
         * Full Health Scan, verified repair Preview and read-only
         * Preflight use normal links rather than forms, so reuse
         * the existing Doctor activity overlay for those requests.
         *
         * Visual feedback only. No maintenance or repair mutation.
         */
        document.addEventListener("click", function(event) {
            if (
                event.defaultPrevented ||
                event.button !== 0 ||
                event.metaKey ||
                event.ctrlKey ||
                event.shiftKey ||
                event.altKey
            ) {
                return;
            }

            const link =
                event.target.closest("a[href]");

            if (!link) {
                return;
            }

            let url;

            try {
                url = new URL(
                    link.href,
                    window.location.href
                );
            } catch (error) {
                return;
            }

            if (
                url.searchParams.get("name")
                !== "my_una_doctor"
            ) {
                return;
            }

            let title = "";
            let copy = "";

            if (
                url.searchParams.get("page") === "overview" &&
                url.searchParams.get("run") === "1"
            ) {
                title =
                    "Doctor is running a full health scan…";

                copy =
                    "Checking UNA health, module integrity, "
                    + "update readiness, logs, storage, "
                    + "reference evidence and repair candidates.";
            } else if (
                url.searchParams.get("page")
                    === "repair_candidates" &&
                url.searchParams.get("preview")
            ) {
                title =
                    "Doctor is preparing the repair preview…";

                copy =
                    "Re-checking the current repair evidence "
                    + "before showing the verified preview.";
            } else if (
                url.searchParams.get("page")
                    === "repair_candidates" &&
                url.searchParams.get("preflight")
            ) {
                title =
                    "Doctor is running read-only preflight…";

                copy =
                    "Revalidating the reference, module identity, "
                    + "paths, rollback readiness and safety gates.";
            } else {
                return;
            }

            if (link.dataset.mudBusy === "1") {
                event.preventDefault();
                return;
            }

            link.dataset.mudBusy = "1";

            link.classList.add(
                "mud-working-button"
            );

            link.setAttribute(
                "aria-disabled",
                "true"
            );

            /*
             * Existing Show Activity setting controls graphics.
             * Duplicate-click protection remains active regardless.
             */
            if (!mudShowActivity) {
                return;
            }

            const activityOverlay =
                document.createElement("div");

            activityOverlay.className =
                "mud-activity-overlay";

            activityOverlay.innerHTML = `
                <div class="mud-activity-panel"
                     role="status"
                     aria-live="assertive">

                    <div class="mud-activity-icon"
                         aria-hidden="true"></div>

                    <strong></strong>

                    <div class="mud-activity-copy"></div>

                    <div class="mud-activity-track"
                         aria-hidden="true">
                        <span></span>
                    </div>

                    <div class="mud-activity-detail"
                         aria-hidden="true">
                        <span class="mud-activity-phase">Starting…</span>
                        <span class="mud-activity-elapsed">0:00 elapsed</span>
                    </div>

                    <div class="mud-activity-warning">
                        Keep this page open until Doctor finishes.
                    </div>
                </div>
            `;

            const titleNode =
                activityOverlay.querySelector(
                    "strong"
                );

            const copyNode =
                activityOverlay.querySelector(
                    ".mud-activity-copy"
                );

            if (titleNode) {
                titleNode.textContent = title;
            }

            if (copyNode) {
                copyNode.textContent = copy;
            }

            /*
             * Paint the popup and start its CSS animations before
             * navigation hands control to the long-running request.
             */
            event.preventDefault();

            document.body.appendChild(
                activityOverlay
            );

            mudStartActivityMotion(
                activityOverlay,
                [
                    "Starting the requested check…",
                    "Scanning current site state…",
                    "Testing integrity and readiness…",
                    "Reviewing evidence…",
                    "Still working safely…"
                ]
            );

            mudContinueAfterPaint(function() {
                window.location.assign(url.href);
            });
        }, true);

        /*
         * Clear stale activity state if the browser restores this
         * page from the back-forward cache.
         */
        window.addEventListener(
            "pageshow",
            function() {
                document
                    .querySelectorAll(
                        ".mud-activity-overlay"
                    )
                    .forEach(function(node) {
                        const timer = Number(
                            node.dataset.mudActivityTimer
                        );

                        if (Number.isFinite(timer))
                            window.clearInterval(timer);

                        node.remove();
                    });

                document
                    .querySelectorAll(
                        "[data-mud-busy=\"1\"]"
                    )
                    .forEach(function(node) {
                        delete node.dataset.mudBusy;

                        node.classList.remove(
                            "mud-working-button"
                        );

                        node.removeAttribute(
                            "aria-disabled"
                        );
                    });
            }
        );

        /* MUD_LONG_GET_ACTIVITY_END */

        </script>';
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
