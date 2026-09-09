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


    public function compareReferencePackageUpload(
        array $aUpload
    ) {
        $sFile =
            BX_DIRECTORY_PATH_MODULES
            . 'newton/my_una_doctor/classes/'
            . 'MyUnaDoctorReferencePackage.php';

        if (!is_file($sFile)) {
            return array(
                'ok' => false,
                'changes_applied' => false,
                'error' =>
                    'Reference package comparison engine is missing.',
            );
        }

        require_once $sFile;

        $oComparator =
            new MyUnaDoctorReferencePackage($this);

        $aResult =
            $oComparator->compareUpload(
                $aUpload
            );

        if (empty($aResult['ok']))
            return $aResult;

        $oLibrary =
            $this->getModuleReferenceLibraryManager();

        if (!$oLibrary) {
            $aResult['library'] = array(
                'ok' => false,
                'saved' => false,
                'error' =>
                    'The comparison succeeded, but the persistent Module Reference Library is unavailable.',
            );

            return $aResult;
        }

        $aResult['library'] =
            $oLibrary
                ->promoteComparisonResult(
                    $aResult
                );

        return $aResult;
    }



    /**
     * Compare one verified saved Module Reference against the
     * currently installed module without creating comparison
     * evidence or changing Reference Library history.
     *
     * Read-only health/intelligence path.
     */
    public function compareSavedModuleReferenceReadOnly(
        $sSha256
    ) {
        $sSha256 =
            strtolower(
                trim((string)$sSha256)
            );

        $aItem =
            $this->getModuleReferenceLibraryItem(
                $sSha256
            );

        if (
            empty($aItem)
            || empty(
                $aItem['library_verified']
            )
        ) {
            return array(
                'ok' => false,
                'changes_applied' => false,
                'error' =>
                    'The saved reference did not pass current Reference Library verification.',
            );
        }

        $sRelPath =
            (string)(
                $aItem['storage_relpath']
                ?? ''
            );

        $sFullPath =
            rtrim(
                BX_DIRECTORY_PATH_ROOT,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $sRelPath
            );

        $sComparator =
            BX_DIRECTORY_PATH_MODULES
            . 'newton/my_una_doctor/classes/'
            . 'MyUnaDoctorReferencePackage.php';

        if (!is_file($sComparator)) {
            return array(
                'ok' => false,
                'changes_applied' => false,
                'error' =>
                    'Reference package comparison engine is missing.',
            );
        }

        require_once $sComparator;

        $oComparator =
            new MyUnaDoctorReferencePackage(
                $this
            );

        return $oComparator
            ->compareStoredReferenceFileReadOnly(
                $sFullPath,
                (string)(
                    $aItem['original_filename']
                    ?? 'reference.zip'
                ),
                $sSha256
            );
    }


    /**
     * Return the read-only persistent Module Reference Library
     * manager.
     */

    /**
     * Re-run the normal comparison engine against one saved,
     * immutable Module Reference Library ZIP.
     *
     * Comparison only. No installed module file is modified.
     */
    public function compareSavedModuleReference(
        $sSha256
    ) {
        $sSha256 =
            strtolower(
                trim((string)$sSha256)
            );

        $aItem =
            $this->getModuleReferenceLibraryItem(
                $sSha256
            );

        if (
            empty($aItem)
            || empty(
                $aItem['library_verified']
            )
        ) {
            return array(
                'ok' => false,
                'changes_applied' => false,
                'error' =>
                    'The saved reference did not pass current Reference Library verification.',
            );
        }

        $sRelPath =
            (string)(
                $aItem['storage_relpath']
                ?? ''
            );

        $sFullPath =
            rtrim(
                BX_DIRECTORY_PATH_ROOT,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $sRelPath
            );

        $sComparator =
            BX_DIRECTORY_PATH_MODULES
            . 'newton/my_una_doctor/classes/'
            . 'MyUnaDoctorReferencePackage.php';

        if (!is_file($sComparator)) {
            return array(
                'ok' => false,
                'changes_applied' => false,
                'error' =>
                    'Reference package comparison engine is missing.',
            );
        }

        require_once $sComparator;

        $oComparator =
            new MyUnaDoctorReferencePackage(
                $this
            );

        $aResult =
            $oComparator
                ->compareStoredReferenceFile(
                    $sFullPath,
                    (string)(
                        $aItem[
                            'original_filename'
                        ]
                        ?? 'reference.zip'
                    ),
                    $sSha256
                );

        if (empty($aResult['ok']))
            return $aResult;

        $sSessionId =
            (string)(
                $aResult['evidence'][
                    'session_id'
                ]
                ?? ''
            );

        $sBaseline =
            (string)(
                $aResult['baseline'][
                    'authenticity'
                ]
                ?? ''
            );

        $oLibrary =
            $this
                ->getModuleReferenceLibraryManager();

        $bRecorded =
            $oLibrary
                ? $oLibrary->recordComparison(
                    $sSha256,
                    $sSessionId,
                    $sBaseline
                )
                : false;

        $aResult['library'] = array(
            'reference_sha256' =>
                $sSha256,

            'history_recorded' =>
                (bool)$bRecorded,

            'source' =>
                'saved_reference_library',
        );

        return $aResult;
    }


    protected function getModuleReferenceLibraryManager()
    {
        $sFile =
            BX_DIRECTORY_PATH_MODULES
            . 'newton/my_una_doctor/classes/'
            . 'MyUnaDoctorReferenceLibrary.php';

        if (!is_file($sFile))
            return null;

        require_once $sFile;

        if (!class_exists(
            'MyUnaDoctorReferenceLibrary'
        )) {
            return null;
        }

        return new MyUnaDoctorReferenceLibrary(
            $this
        );
    }


    /**
     * Return every saved Module Reference Library item
     * with its current cryptographic verification state.
     *
     * Read-only.
     */
    public function getModuleReferenceLibrary()
    {
        $oLibrary =
            $this->getModuleReferenceLibraryManager();

        if (!$oLibrary)
            return array();

        return $oLibrary->getAll();
    }


    /**
     * Return one saved reference by immutable SHA-256.
     *
     * Read-only.
     */
    public function getModuleReferenceLibraryItem(
        $sSha256
    ) {
        $oLibrary =
            $this->getModuleReferenceLibraryManager();

        if (!$oLibrary)
            return array();

        return $oLibrary->getBySha256(
            $sSha256
        );
    }


    /**
     * Return aggregate Module Reference Library status.
     *
     * Read-only.
     */
    public function getModuleReferenceLibrarySummary()
    {
        $oLibrary =
            $this->getModuleReferenceLibraryManager();

        if (!$oLibrary) {
            return array(
                'total' => 0,
                'verified' => 0,
                'failed' => 0,
                'module_count' => 0,
                'modules' => array(),
            );
        }

        return $oLibrary->getSummary();
    }


    protected function getLocalRepairKnowledgeManager()
    {
        $sFile =
            BX_DIRECTORY_PATH_MODULES
            . 'newton/my_una_doctor/classes/'
            . 'MyUnaDoctorRepairKnowledge.php';

        if (!is_file($sFile))
            return null;

        require_once $sFile;

        return new MyUnaDoctorRepairKnowledge(
            $this
        );
    }


    public function saveLocalRepairKnowledge(
        array $aData
    ) {
        $oManager =
            $this->getLocalRepairKnowledgeManager();

        if (!$oManager) {
            return array(
                'ok' => false,
                'saved' => false,
                'error' =>
                    'Local Repair Knowledge backend is missing.',
            );
        }

        return $oManager->save($aData);
    }


    public function findLocalRepairKnowledge(
        $sModule,
        $sVersion,
        $sFile,
        $sReferenceMd5,
        $sLiveMd5
    ) {
        $oManager =
            $this->getLocalRepairKnowledgeManager();

        if (!$oManager)
            return array();

        return $oManager->findExact(
            $sModule,
            $sVersion,
            $sFile,
            $sReferenceMd5,
            $sLiveMd5
        );
    }


    public function getLocalRepairKnowledge(
        $sModule = ''
    ) {
        $oManager =
            $this->getLocalRepairKnowledgeManager();

        if (!$oManager)
            return array();

        return $oManager->getAll(
            (string)$sModule
        );
    }


    public function getLocalRepairKnowledgeWithStatus(
        $sModule = ''
    ) {
        $oManager =
            $this->getLocalRepairKnowledgeManager();

        if (!$oManager)
            return array();

        return $oManager->getAllWithStatus(
            (string)$sModule
        );
    }


    public function setLocalRepairKnowledgeEnabled(
        $iId,
        $bEnabled
    ) {
        $oManager =
            $this->getLocalRepairKnowledgeManager();

        if (!$oManager) {
            return array(
                'ok' => false,
                'knowledge_changed' => false,
                'error' =>
                    'Local Repair Knowledge backend is missing.',
            );
        }

        return $oManager->setEnabled(
            (int)$iId,
            (bool)$bEnabled
        );
    }


    public function deleteLocalRepairKnowledge(
        $iId
    ) {
        $oManager =
            $this->getLocalRepairKnowledgeManager();

        if (!$oManager) {
            return array(
                'ok' => false,
                'knowledge_changed' => false,
                'error' =>
                    'Local Repair Knowledge backend is missing.',
            );
        }

        return $oManager->deleteRecord(
            (int)$iId
        );
    }


    public function getRepairPreview(
        $sRule,
        $sModule = '',
        $sRequestedReferenceSha256 = ''
    )
    {
        $sRule = trim((string)$sRule);
        $sModule = trim((string)$sModule);

        $sRequestedReferenceSha256 =
            strtolower(
                trim(
                    (string)$sRequestedReferenceSha256
                )
            );

        if (
            $sRule
                === 'verified_module_reference_drift'
            && (
                strlen(
                    $sRequestedReferenceSha256
                ) !== 64
                || !ctype_xdigit(
                    $sRequestedReferenceSha256
                )
            )
        ) {
            return array(
                'ok' => false,
                'mode' => 'preview',
                'changes_applied' => false,
                'error' =>
                    'Safety stop: an exact verified Reference Library SHA-256 is required for this repair preview.',
            );
        }

        /*
         * Current staged repair support includes preview, read-only preflight and guarded arm planning for the first proven
         * high-confidence repair workflow.
         */
        $aSupported = array(
            'runtime_content_update_preparation',
            'verified_module_reference_drift'
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

                if (
                    $sRule
                        === 'verified_module_reference_drift'
                ) {
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

                    if (
                        $sCandidateReferenceSha256
                        !== $sRequestedReferenceSha256
                    ) {
                        continue;
                    }
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

        /*
         * Verified Module Reference Repair Preview.
         *
         * Separate read-only path. Reference repair evidence has
         * different safety semantics from runtime-content update
         * preparation.
         *
         * Always re-run the cryptographically verified comparison
         * instead of relying only on the health-scan snapshot.
         */
        if ($sRule === 'verified_module_reference_drift') {
            $sReferenceSha256 =
                strtolower(
                    trim(
                        (string)(
                            $aMatch[
                                'reference_sha256'
                            ]
                            ?? ''
                        )
                    )
                );

            if (
                strlen($sReferenceSha256) !== 64
                || !ctype_xdigit($sReferenceSha256)
            ) {
                return array(
                    'ok' => false,
                    'mode' => 'preview',
                    'changes_applied' => false,
                    'error' =>
                        'Safety stop: the repair candidate does not contain a valid verified Reference Library SHA-256.',
                );
            }

            if (
                $sReferenceSha256
                !== $sRequestedReferenceSha256
            ) {
                return array(
                    'ok' => false,
                    'mode' => 'preview',
                    'changes_applied' => false,
                    'error' =>
                        'Safety stop: the matched candidate SHA-256 does not equal the explicitly requested reference SHA-256.',
                );
            }

            $aFreshComparison =
                $this->compareSavedModuleReferenceReadOnly(
                    $sReferenceSha256
                );

            if (
                !is_array($aFreshComparison)
                || empty($aFreshComparison['ok'])
            ) {
                return array(
                    'ok' => false,
                    'mode' => 'preview',
                    'changes_applied' => false,
                    'error' =>
                        isset($aFreshComparison['error'])
                            ? (string)$aFreshComparison['error']
                            : 'The verified reference could not be compared to the current live module.',
                );
            }

            if (
                !isset(
                    $aFreshComparison[
                        'safety'
                    ][
                        'comparison_persisted'
                    ]
                )
                || $aFreshComparison[
                    'safety'
                ][
                        'comparison_persisted'
                    ] !== false
            ) {
                return array(
                    'ok' => false,
                    'mode' => 'preview',
                    'changes_applied' => false,
                    'error' =>
                        'Safety stop: the reference preview did not prove that comparison persistence was disabled.',
                );
            }

            $aComparison =
                isset($aFreshComparison['comparison'])
                && is_array(
                    $aFreshComparison['comparison']
                )
                    ? $aFreshComparison['comparison']
                    : array();

            $iUnexplainedModified =
                (int)(
                    $aComparison[
                        'unexplained_modified_count'
                    ]
                    ?? 0
                );

            $iMissing =
                (int)(
                    $aComparison['missing_count']
                    ?? 0
                );

            $iUnexplainedLiveOnly =
                (int)(
                    $aComparison[
                        'unexplained_live_only_count'
                    ]
                    ?? 0
                );

            $iActionable =
                $iUnexplainedModified
                + $iMissing
                + $iUnexplainedLiveOnly;

            if ($iActionable <= 0) {
                return array(
                    'ok' => false,
                    'mode' => 'preview',
                    'changes_applied' => false,
                    'error' =>
                        'The previously detected verified reference drift is no longer present. Run a new health scan before continuing.',
                );
            }

            $getFiles = function($sKey) use ($aComparison) {
                return isset($aComparison[$sKey])
                    && is_array($aComparison[$sKey])
                        ? $aComparison[$sKey]
                        : array();
            };

            /*
             * Comparison evidence is intentionally richer for
             * modified files than for missing/live-only files.
             *
             * Modified entries may be structured records containing
             * file/hash/classification fields. Missing and live-only
             * evidence may simply be filenames.
             *
             * Normalize both shapes for the generic Studio preview
             * renderer without discarding useful hash evidence.
             */
            $formatEvidenceFiles = function(array $aItems) {
                $aFormatted = array();

                foreach ($aItems as $mixedItem) {
                    if (!is_array($mixedItem)) {
                        $sValue =
                            trim((string)$mixedItem);

                        if ($sValue !== '')
                            $aFormatted[] = $sValue;

                        continue;
                    }

                    $sFile =
                        trim(
                            (string)(
                                $mixedItem['file']
                                ?? ''
                            )
                        );

                    if ($sFile === '')
                        $sFile = '(unnamed file)';

                    $aDetails = array();

                    $aDetailFields = array(
                        'reference_md5' =>
                            'reference MD5',

                        'live_md5' =>
                            'live MD5',

                        'baseline_md5' =>
                            'UNA baseline MD5',

                        'classification' =>
                            'classification',
                    );

                    foreach (
                        $aDetailFields
                        as $sKey => $sLabel
                    ) {
                        $sValue =
                            trim(
                                (string)(
                                    $mixedItem[$sKey]
                                    ?? ''
                                )
                            );

                        if ($sValue !== '') {
                            $aDetails[] =
                                $sLabel
                                . ': '
                                . $sValue;
                        }
                    }

                    $sFormatted = $sFile;

                    if (!empty($aDetails)) {
                        $sFormatted .=
                            ' — '
                            . implode(
                                '; ',
                                $aDetails
                            );
                    }

                    $aFormatted[] =
                        $sFormatted;
                }

                return $aFormatted;
            };

            $aUnexplainedModifiedFiles =
                $formatEvidenceFiles(
                    $getFiles(
                        'unexplained_modified_files'
                    )
                );

            $aMissingFiles =
                $formatEvidenceFiles(
                    $getFiles(
                        'missing_files'
                    )
                );

            $aUnexplainedLiveOnlyFiles =
                $formatEvidenceFiles(
                    $getFiles(
                        'unexplained_live_only_files'
                    )
                );

            $aExplained = array();

            foreach (
                $formatEvidenceFiles(
                    $getFiles(
                        'baseline_backed_modified_files'
                    )
                )
                as $sFile
            ) {
                $aExplained[] =
                    '[UNA baseline-backed modification] '
                    . $sFile;
            }

            foreach (
                $formatEvidenceFiles(
                    $getFiles(
                        'approved_modified_files'
                    )
                )
                as $sFile
            ) {
                $aExplained[] =
                    '[Approved modification] '
                    . $sFile;
            }

            foreach (
                $formatEvidenceFiles(
                    $getFiles(
                        'baseline_backed_live_only_files'
                    )
                )
                as $sFile
            ) {
                $aExplained[] =
                    '[UNA baseline-backed live-only file] '
                    . $sFile;
            }

            foreach (
                $formatEvidenceFiles(
                    $getFiles(
                        'runtime_generated_live_only_files'
                    )
                )
                as $sFile
            ) {
                $aExplained[] =
                    '[Runtime-generated live-only file] '
                    . $sFile;
            }

            $sTargetModule =
                isset($aMatch['module'])
                    ? (string)$aMatch['module']
                    : $sModule;

            $sTitle =
                isset($aMatch['title'])
                    ? (string)$aMatch['title']
                    : $sTargetModule;

            $sVersion =
                (string)(
                    $aMatch[
                        'module_version'
                    ]
                    ?? (
                        $aFreshComparison[
                            'installed'
                        ][
                            'registered_version'
                        ]
                        ?? ''
                    )
                );

            $sPreviewId =
                hash(
                    'sha256',
                    implode(
                        '|',
                        array(
                            'verified_module_reference_drift',
                            $sTargetModule,
                            $sVersion,
                            $sReferenceSha256,
                            (string)$iUnexplainedModified,
                            (string)$iMissing,
                            (string)$iUnexplainedLiveOnly,
                        )
                    )
                );

            return array(
                'ok' => true,
                'mode' => 'preview',

                'preview_id' =>
                    $sPreviewId,

                'changes_applied' =>
                    false,

                'automatic_repair' =>
                    false,

                'preflight_available' =>
                    true,

                'rule' =>
                    'verified_module_reference_drift',

                'module' =>
                    $sTargetModule,

                'title' =>
                    $sTitle,

                'module_version' =>
                    $sVersion,

                'confidence' =>
                    isset($aMatch['confidence'])
                        ? (string)$aMatch['confidence']
                        : 'high',

                'source_integrity' =>
                    'verified_reference_drift',

                'reference_sha256' =>
                    $sReferenceSha256,

                'reference_filename' =>
                    (string)(
                        $aMatch[
                            'reference_filename'
                        ]
                        ?? ''
                    ),

                'unexplained_modified_count' =>
                    $iUnexplainedModified,

                'missing_count' =>
                    $iMissing,

                'unexplained_live_only_count' =>
                    $iUnexplainedLiveOnly,

                'unexplained_modified_files' =>
                    $aUnexplainedModifiedFiles,

                'missing_files' =>
                    $aMissingFiles,

                'unexplained_live_only_files' =>
                    $aUnexplainedLiveOnlyFiles,

                'explained_files_left_untouched' =>
                    $aExplained,

                'preconditions' => array(
                    'The saved Module Reference must continue to pass current Reference Library ZIP and manifest verification.',
                    'The Reference Library SHA-256 must remain exactly ' . $sReferenceSha256 . '.',
                    'The installed module must still match the identity and version represented by the verified reference.',
                    'Doctor must re-run the verified comparison immediately before any future repair stage.',
                    'Automatic repair remains disabled.',
                ),

                'proposed_steps' => array(
                    'For each unexplained modified file, a future approved repair may restore the verified reference copy only after its current live hash is revalidated.',
                    'For each missing file, a future approved repair may restore the exact file from the verified reference package.',
                    'Unexplained live-only files require individual review before Doctor may quarantine or remove anything.',
                    'Approved modifications, UNA-baseline-backed differences and recognized runtime-generated files will be left untouched.',
                ),

                'validation_after_future_apply' => array(
                    'Re-run the same verified reference comparison.',
                    'Confirm every approved restored file hashes exactly to the verified reference.',
                    'Confirm every approved missing file has been restored.',
                    'Confirm no explained or protected file was changed.',
                    'Run Doctor file-integrity and module-version checks again.',
                ),

                'rollback_plan' => array(
                    'Before any future write, preserve every affected live file or absence-state in the durable repair journal.',
                    'Never overwrite a rollback copy after repair execution begins.',
                    'If validation fails, restore the exact pre-repair live state before releasing the repair barrier.',
                ),

                'warnings' => array(
                    'PREVIEW ONLY — no module file has been changed.',
                    'Read-only preflight is available. Repair arming is not enabled for verified reference drift.',
                    'Unexplained live-only files may be legitimate local additions and are not automatically deletable.',
                    'A verified reference proves package integrity and identity; it does not by itself authorize destructive changes.',
                ),

                'safety_backup_root' =>
                    BX_DIRECTORY_PATH_ROOT
                    . 'tmp/my-una-doctor-repairs/',
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


    public function getRepairPreflight(
        $sRule,
        $sModule = '',
        $sReferenceSha256 = ''
    )
    {
        $aPreview = $this->getRepairPreview(
            $sRule,
            $sModule,
            $sReferenceSha256
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

        /*
         * Verified Module Reference Repair Preflight.
         *
         * This stage is intentionally read-only and intentionally
         * NOT arm-ready.
         *
         * It proves whether the current candidate could be safely
         * described as a future repair operation. It does not create
         * a journal, backup, quarantine, directory or file.
         */
        if ($sRule === 'verified_module_reference_drift') {
            $aResult['rule'] =
                'verified_module_reference_drift';

            $aResult['module'] =
                (string)(
                    $aPreview['module']
                    ?? $sModule
                );

            $aResult['automatic_repair'] =
                false;

            $aResult['arm_available'] =
                false;

            $aResult['ready_for_arm'] =
                false;

            $aResult['preflight_complete'] =
                false;

            $addCheck = function(
                $sKey,
                $sLabel,
                $sStatus,
                $sDetail
            ) use (&$aResult) {
                $aResult['checks'][] = array(
                    'key' =>
                        (string)$sKey,

                    'label' =>
                        (string)$sLabel,

                    'status' =>
                        (string)$sStatus,

                    'detail' =>
                        (string)$sDetail,
                );
            };

            /*
             * Preview itself performs a fresh non-persistent
             * comparison. If Preview succeeded, we already know
             * that the latest scan candidate still exists and the
             * comparison explicitly proved persistence was off.
             */
            $addCheck(
                'fresh_preview',
                'Fresh verified reference preview',
                'pass',
                'Doctor re-ran the current verified-reference preview successfully. No changes were applied.'
            );

            $sReferenceSha256 =
                strtolower(
                    trim(
                        (string)(
                            $aPreview[
                                'reference_sha256'
                            ]
                            ?? ''
                        )
                    )
                );

            $aReference =
                $this->getModuleReferenceLibraryItem(
                    $sReferenceSha256
                );

            if (
                !is_array($aReference)
                || empty(
                    $aReference[
                        'library_verified'
                    ]
                )
            ) {
                $addCheck(
                    'reference_library',
                    'Reference Library verification',
                    'fail',
                    'The selected reference no longer passes current Reference Library verification.'
                );

                $aResult['error'] =
                    'Verified reference preflight failed: Reference Library verification is no longer valid.';

                return $aResult;
            }

            $addCheck(
                'reference_library',
                'Reference Library verification',
                'pass',
                'The selected ZIP and immutable manifest remain verified under SHA-256 '
                . $sReferenceSha256
                . '.'
            );

            $oDb = BxDolDb::getInstance();

            $sModuleName =
                trim(
                    (string)(
                        $aPreview['module']
                        ?? $sModule
                    )
                );

            $aModule =
                $oDb->getRow(
                    "SELECT `id`, `name`, `title`, `version`,
                            `path`, `enabled`
                     FROM `sys_modules`
                     WHERE `name` = ?
                     LIMIT 1",
                    array(
                        $sModuleName
                    )
                );

            if (empty($aModule)) {
                $addCheck(
                    'module_registration',
                    'Installed module registration',
                    'fail',
                    'The candidate module is no longer registered.'
                );

                $aResult['error'] =
                    'Verified reference preflight failed: module registration is missing.';

                return $aResult;
            }

            $sExpectedVersion =
                trim(
                    (string)(
                        $aPreview[
                            'module_version'
                        ]
                        ?? ''
                    )
                );

            $sInstalledVersion =
                trim(
                    (string)(
                        $aModule['version']
                        ?? ''
                    )
                );

            $sReferenceModule =
                trim(
                    (string)(
                        $aReference[
                            'module_name'
                        ]
                        ?? ''
                    )
                );

            $sReferenceVersion =
                trim(
                    (string)(
                        $aReference[
                            'reference_version'
                        ]
                        ?? ''
                    )
                );

            if (
                $sModuleName === ''
                || $sModuleName !== $sReferenceModule
                || $sInstalledVersion === ''
                || $sInstalledVersion !== $sExpectedVersion
                || $sInstalledVersion !== $sReferenceVersion
            ) {
                $addCheck(
                    'module_identity',
                    'Module and version identity',
                    'fail',
                    'The installed module identity/version no longer exactly matches the verified reference and Preview.'
                );

                $aResult['error'] =
                    'Verified reference preflight failed: module identity or version changed.';

                return $aResult;
            }

            $addCheck(
                'module_identity',
                'Module and version identity',
                'pass',
                'Installed '
                . $sModuleName
                . ' '
                . $sInstalledVersion
                . ' exactly matches the verified reference identity.'
            );

            $sModulePath =
                trim(
                    (string)(
                        $aModule['path']
                        ?? ''
                    ),
                    "/\\"
                );

            if ($sModulePath === '') {
                $addCheck(
                    'module_root',
                    'Live module root',
                    'fail',
                    'The registered module path is empty.'
                );

                $aResult['error'] =
                    'Verified reference preflight failed: live module root is unavailable.';

                return $aResult;
            }

            $sModuleRoot =
                realpath(
                    rtrim(
                        BX_DIRECTORY_PATH_MODULES,
                        DIRECTORY_SEPARATOR
                    )
                    . DIRECTORY_SEPARATOR
                    . str_replace(
                        '/',
                        DIRECTORY_SEPARATOR,
                        $sModulePath
                    )
                );

            if (
                $sModuleRoot === false
                || !is_dir($sModuleRoot)
            ) {
                $addCheck(
                    'module_root',
                    'Live module root',
                    'fail',
                    'Doctor could not resolve the registered live module directory.'
                );

                $aResult['error'] =
                    'Verified reference preflight failed: live module root could not be resolved.';

                return $aResult;
            }

            $sModuleRoot =
                rtrim(
                    $sModuleRoot,
                    DIRECTORY_SEPARATOR
                )
                . DIRECTORY_SEPARATOR;

            $addCheck(
                'module_root',
                'Live module root',
                'pass',
                'Resolved module root: '
                . $sModuleRoot
            );

            /*
             * Preview evidence is presentation-normalized.
             * Modified entries can include:
             *
             *   path — reference MD5: ...; live MD5: ...
             *
             * The path is always the portion before that evidence
             * separator.
             */
            $extractEvidencePath =
                function($mixedValue) {
                    $sValue =
                        trim(
                            (string)$mixedValue
                        );

                    $iSeparator =
                        strpos(
                            $sValue,
                            ' — '
                        );

                    if ($iSeparator !== false) {
                        $sValue =
                            substr(
                                $sValue,
                                0,
                                $iSeparator
                            );
                    }

                    return trim($sValue);
                };

            $aOperations = array();

            foreach (
                array(
                    'unexplained_modified_files'
                        => 'restore_verified_reference',

                    'missing_files'
                        => 'create_from_verified_reference',

                    'unexplained_live_only_files'
                        => 'quarantine_review_only',
                )
                as $sEvidenceKey => $sOperation
            ) {
                $aItems =
                    isset($aPreview[$sEvidenceKey])
                    && is_array(
                        $aPreview[$sEvidenceKey]
                    )
                        ? $aPreview[$sEvidenceKey]
                        : array();

                foreach ($aItems as $mixedItem) {
                    $sRelative =
                        $extractEvidencePath(
                            $mixedItem
                        );

                    $aOperations[] = array(
                        'action' =>
                            $sOperation,

                        'file' =>
                            $sRelative,
                    );
                }
            }

            if (empty($aOperations)) {
                $addCheck(
                    'actionable_evidence',
                    'Current actionable drift',
                    'fail',
                    'The fresh Preview contains no actionable unexplained drift.'
                );

                $aResult['error'] =
                    'Verified reference preflight failed: actionable drift disappeared.';

                return $aResult;
            }

            $addCheck(
                'actionable_evidence',
                'Current actionable drift',
                'pass',
                'Fresh Preview contains '
                . count($aOperations)
                . ' actionable unexplained item(s).'
            );

            $bPathFailure =
                false;

            $bWritableFailure =
                false;

            $bLiveOnlyReview =
                false;

            $aValidatedOperations =
                array();

            foreach ($aOperations as $aOperation) {
                $sRelative =
                    trim(
                        str_replace(
                            '\\',
                            '/',
                            (string)(
                                $aOperation['file']
                                ?? ''
                            )
                        )
                    );

                $sAction =
                    (string)(
                        $aOperation['action']
                        ?? ''
                    );

                $bRelativeSafe =
                    $sRelative !== ''
                    && $sRelative[0] !== '/'
                    && strpos(
                        $sRelative,
                        "\0"
                    ) === false
                    && !preg_match(
                        '#(^|/)\.\.(/|$)#',
                        $sRelative
                    )
                    && !preg_match(
                        '#(^|/)\.(/|$)#',
                        $sRelative
                    );

                if (!$bRelativeSafe) {
                    $bPathFailure =
                        true;

                    $aValidatedOperations[] =
                        array(
                            'action' =>
                                $sAction,

                            'file' =>
                                $sRelative,

                            'path_status' =>
                                'fail',

                            'write_status' =>
                                'not_checked',
                        );

                    continue;
                }

                $sAbsolute =
                    $sModuleRoot
                    . str_replace(
                        '/',
                        DIRECTORY_SEPARATOR,
                        $sRelative
                    );

                /*
                 * Existing target: resolve its real path and reject
                 * links or escape outside the module root.
                 *
                 * Missing target: resolve the nearest existing
                 * parent and prove that parent remains inside the
                 * module root.
                 */
                $bExists =
                    file_exists($sAbsolute)
                    || is_link($sAbsolute);

                $bSymlink =
                    is_link($sAbsolute);

                $sResolvedBoundary =
                    false;

                if ($bExists) {
                    $sResolvedBoundary =
                        realpath($sAbsolute);
                } else {
                    $sParent =
                        dirname($sAbsolute);

                    while (
                        !file_exists($sParent)
                        && $sParent !== dirname($sParent)
                    ) {
                        $sParent =
                            dirname($sParent);
                    }

                    $sResolvedBoundary =
                        realpath($sParent);
                }

                $bInsideRoot =
                    !$bSymlink
                    && $sResolvedBoundary !== false
                    && (
                        $sResolvedBoundary
                            === rtrim(
                                $sModuleRoot,
                                DIRECTORY_SEPARATOR
                            )
                        || strpos(
                            rtrim(
                                $sResolvedBoundary,
                                DIRECTORY_SEPARATOR
                            )
                            . DIRECTORY_SEPARATOR,
                            $sModuleRoot
                        ) === 0
                    );

                if (!$bInsideRoot) {
                    $bPathFailure =
                        true;

                    $aValidatedOperations[] =
                        array(
                            'action' =>
                                $sAction,

                            'file' =>
                                $sRelative,

                            'path_status' =>
                                $bSymlink
                                    ? 'symlink_rejected'
                                    : 'outside_or_unresolved',

                            'write_status' =>
                                'not_checked',
                        );

                    continue;
                }

                /*
                 * Future restore/create/quarantine operations need
                 * the target parent to be writable by Studio PHP.
                 * We check permissions only; we create nothing.
                 */
                $sParent =
                    dirname($sAbsolute);

                $sWritableProbe =
                    $sParent;

                while (
                    !file_exists($sWritableProbe)
                    && $sWritableProbe !== dirname($sWritableProbe)
                ) {
                    $sWritableProbe =
                        dirname($sWritableProbe);
                }

                $bWritable =
                    is_dir($sWritableProbe)
                    && is_writable($sWritableProbe);

                if (!$bWritable)
                    $bWritableFailure = true;

                if (
                    $sAction
                    === 'quarantine_review_only'
                ) {
                    $bLiveOnlyReview = true;
                }

                $aValidatedOperations[] =
                    array(
                        'action' =>
                            $sAction,

                        'file' =>
                            $sRelative,

                        'path_status' =>
                            'inside_module_root',

                        'exists' =>
                            $bExists,

                        'write_status' =>
                            $bWritable
                                ? 'parent_writable'
                                : 'parent_not_writable',
                    );
            }

            if ($bPathFailure) {
                $addCheck(
                    'path_confinement',
                    'Actionable path confinement',
                    'fail',
                    'One or more candidate paths were unsafe, unresolved, symbolic links or escaped the registered module root.'
                );

                $aResult['operations'] =
                    $aValidatedOperations;

                $aResult['error'] =
                    'Verified reference preflight failed: actionable path confinement could not be proven.';

                return $aResult;
            }

            $addCheck(
                'path_confinement',
                'Actionable path confinement',
                'pass',
                'Every actionable path resolved safely inside the registered module root and no target symlink was accepted.'
            );

            if ($bWritableFailure) {
                $addCheck(
                    'future_write_permissions',
                    'Future write locations',
                    'fail',
                    'One or more future operation parents are not writable by the current Studio PHP process.'
                );
            } else {
                $addCheck(
                    'future_write_permissions',
                    'Future write locations',
                    'pass',
                    'Every future operation parent is currently writable by the Studio PHP process. No write was attempted.'
                );
            }

            $sRepairRoot =
                BX_DIRECTORY_PATH_ROOT
                . 'tmp/my-una-doctor-repairs/';

            $sRepairParent =
                dirname(
                    rtrim(
                        $sRepairRoot,
                        DIRECTORY_SEPARATOR
                    )
                );

            $bRepairStorageReady =
                (
                    is_dir($sRepairRoot)
                    && is_writable($sRepairRoot)
                )
                || (
                    !file_exists($sRepairRoot)
                    && is_dir($sRepairParent)
                    && is_writable($sRepairParent)
                );

            if ($bRepairStorageReady) {
                $addCheck(
                    'rollback_storage',
                    'Rollback storage availability',
                    'pass',
                    'Doctor repair backup storage is available for a future guarded stage. No backup directory was created.'
                );
            } else {
                $addCheck(
                    'rollback_storage',
                    'Rollback storage availability',
                    'fail',
                    'Doctor repair backup storage is not currently writable by Studio PHP.'
                );
            }

            $aActiveJournal =
                $this->getActiveRepairJournal(
                    $sRule,
                    $sModuleName
                );

            if (!empty($aActiveJournal)) {
                $addCheck(
                    'repair_journal_conflict',
                    'Active repair journal',
                    'fail',
                    'An active repair journal already exists for this repair rule/module.'
                );
            } else {
                $addCheck(
                    'repair_journal_conflict',
                    'Active repair journal',
                    'pass',
                    'No active repair journal exists for this repair rule/module.'
                );
            }

            if ($bLiveOnlyReview) {
                $addCheck(
                    'live_only_policy',
                    'Unexplained live-only policy',
                    'review',
                    'At least one unexplained live-only file is present. Doctor classifies it as quarantine-review-only; deletion is not authorized.'
                );
            } else {
                $addCheck(
                    'live_only_policy',
                    'Unexplained live-only policy',
                    'pass',
                    'No unexplained live-only deletion decision is required by this candidate.'
                );
            }

            $aResult['operations'] =
                $aValidatedOperations;

            $aResult['reference_sha256'] =
                $sReferenceSha256;

            $aResult['module_version'] =
                $sInstalledVersion;

            $aResult['repair_backup_root'] =
                $sRepairRoot;

            /*
             * Deterministic read-only preflight identity.
             * This ID is evidence only. It is NOT currently accepted
             * for reference repair arming.
             */
            $aResult['preflight_id'] =
                hash(
                    'sha256',
                    json_encode(
                        array(
                            'rule' =>
                                $sRule,

                            'module' =>
                                $sModuleName,

                            'version' =>
                                $sInstalledVersion,

                            'reference_sha256' =>
                                $sReferenceSha256,

                            'preview_id' =>
                                (string)(
                                    $aPreview[
                                        'preview_id'
                                    ]
                                    ?? ''
                                ),

                            'operations' =>
                                $aValidatedOperations,
                        ),
                        JSON_UNESCAPED_SLASHES
                    )
                );

            $bChecksPassed =
                !$bPathFailure
                && !$bWritableFailure
                && $bRepairStorageReady
                && empty($aActiveJournal);

            $aResult['ok'] =
                $bChecksPassed;

            $aResult['preflight_complete'] =
                $bChecksPassed;

            /*
             * HARD SAFETY BARRIER:
             *
             * Existing getRepairArmPlan() requires ready=true.
             * Reference repair execution is not implemented yet, so
             * this preflight can never arm the repair.
             */
            $aResult['ready'] =
                false;

            $aResult['ready_for_arm'] =
                false;

            $aResult['arm_available'] =
                false;

            $aResult['changes_applied'] =
                false;

            if (!$bChecksPassed) {
                $aResult['error'] =
                    'One or more read-only verified reference preflight checks failed.';
            } elseif ($bLiveOnlyReview) {
                $aResult['status'] =
                    'checks_passed_review_required';

                $aResult['message'] =
                    'Read-only preflight checks passed. Live-only evidence still requires explicit quarantine policy review. Repair arming remains disabled.';
            } else {
                $aResult['status'] =
                    'checks_passed_arm_disabled';

                $aResult['message'] =
                    'Read-only preflight checks passed. Repair arming remains deliberately disabled until the reference repair executor is implemented and tested.';
            }

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
                   'runtime_merging',
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



    public function getLatestActiveRepairJournal()
    {
        $aRow = $this->_oDb->getRow(
            "SELECT *
             FROM `my_una_doctor_repairs`
             WHERE `status` IN (
                 'planned',
                 'arming',
                 'maintenance_enabled',
                 'generator_paused',
                 'armed',
                 'runtime_protecting',
                 'runtime_protected',
                 'runtime_merging',
                 'runtime_restoring',
                 'runtime_rollback_failed',
                 'restoring',
                 'rollback_failed'
             )
             ORDER BY `id` DESC
             LIMIT 1"
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

        /*
         * Complete deterministic restore-path set for the
         * guarded merge executor. Studio should continue using
         * restore_preview for display.
         */
        $aResult['restore_files'] =
            $aRestore;

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


    protected function buildRuntimeMergeExecutionManifest(
        $iJournalId,
        $sApprovedMergePlanId
    ) {
        $iJournalId = (int)$iJournalId;
        $sApprovedMergePlanId =
            trim((string)$sApprovedMergePlanId);

        $aResult = array(
            'ok' => false,
            'ready' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'merge_plan_id' =>
                $sApprovedMergePlanId,
            'manifest' => array(),
        );

        if (
            $iJournalId <= 0
            || $sApprovedMergePlanId === ''
        ) {
            $aResult['error'] =
                'A valid journal ID and approved merge plan ID are required.';

            return $aResult;
        }

        /*
         * Recompute the complete read-only classification.
         * Never trust restore paths supplied by a browser.
         */
        $aPlan =
            $this->getPostUpdateRuntimeMergePlan(
                $iJournalId
            );

        if (
            empty($aPlan['ok'])
            || empty($aPlan['ready'])
        ) {
            $aResult['error'] =
                'Fresh runtime merge planning is not ready: '
                . (
                    $aPlan['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        $sFreshMergePlanId =
            (string)(
                $aPlan['merge_plan_id']
                ?? ''
            );

        if (
            $sFreshMergePlanId === ''
            || !hash_equals(
                $sFreshMergePlanId,
                $sApprovedMergePlanId
            )
        ) {
            $aResult['error'] =
                'Runtime merge approval is stale. Re-preview and approve the current merge plan.';

            $aResult['fresh_merge_plan_id'] =
                $sFreshMergePlanId;

            return $aResult;
        }

        $aRestoreFiles =
            $aPlan['restore_files']
            ?? array();

        if (
            !is_array($aRestoreFiles)
            || count($aRestoreFiles)
                !== (int)(
                    $aPlan['restore_candidate_count']
                    ?? -1
                )
        ) {
            $aResult['error'] =
                'Runtime merge restore-path manifest is incomplete.';

            return $aResult;
        }

        $aJournal = $this->_oDb->getRow(
            "SELECT *
             FROM `my_una_doctor_repairs`
             WHERE `id` = ?
             LIMIT 1",
            array($iJournalId)
        );

        if (
            empty($aJournal)
            || (string)$aJournal['status']
                !== 'runtime_protected'
        ) {
            $aResult['error'] =
                'Runtime merge manifest requires a runtime-protected journal.';

            return $aResult;
        }

        $aProtected = json_decode(
            (string)$aJournal['result_json'],
            true
        );

        if (
            !is_array($aProtected)
            || ($aProtected['phase'] ?? '')
                !== 'runtime_protected'
            || empty($aProtected['runtime_moved'])
        ) {
            $aResult['error'] =
                'Runtime-protected journal state is incomplete.';

            return $aResult;
        }

        $aRuntime =
            $aProtected['runtime']
            ?? array();

        $aSafety =
            $aProtected['safety']
            ?? array();

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
            $sRuntimeRelative === ''
            || $sProtectedPath === ''
            || !is_dir($sProtectedPath)
            || is_link($sProtectedPath)
        ) {
            $aResult['error'] =
                'Protected runtime path is unavailable or unsafe.';

            return $aResult;
        }

        $sProtectedReal =
            realpath($sProtectedPath);

        if ($sProtectedReal === false) {
            $aResult['error'] =
                'Protected runtime path could not be resolved.';

            return $aResult;
        }

        $aManifest = array();
        $iManifestBytes = 0;

        foreach ($aRestoreFiles as $sModuleFile) {
            $sModuleFile =
                str_replace(
                    '\\',
                    '/',
                    (string)$sModuleFile
                );

            if (
                $sModuleFile === ''
                || strpos(
                    $sModuleFile,
                    $sRuntimeRelative
                ) !== 0
            ) {
                $aResult['error'] =
                    'Runtime merge contains a path outside the journaled runtime directory.';

                return $aResult;
            }

            $sRelative =
                substr(
                    $sModuleFile,
                    strlen($sRuntimeRelative)
                );

            if ($sRelative === '') {
                $aResult['error'] =
                    'Runtime merge contains an empty relative path.';

                return $aResult;
            }

            $aParts =
                explode('/', $sRelative);

            foreach ($aParts as $sPart) {
                if (
                    $sPart === ''
                    || $sPart === '.'
                    || $sPart === '..'
                ) {
                    $aResult['error'] =
                        'Runtime merge contains an unsafe relative path: '
                        . $sModuleFile;

                    return $aResult;
                }
            }

            $sSource =
                $sProtectedReal
                . DIRECTORY_SEPARATOR
                . str_replace(
                    '/',
                    DIRECTORY_SEPARATOR,
                    $sRelative
                );

            if (
                !is_file($sSource)
                || is_link($sSource)
            ) {
                $aResult['error'] =
                    'Protected merge source is missing or unsafe: '
                    . $sModuleFile;

                return $aResult;
            }

            $sSourceReal =
                realpath($sSource);

            if (
                $sSourceReal === false
                || strpos(
                    $sSourceReal,
                    $sProtectedReal
                    . DIRECTORY_SEPARATOR
                ) !== 0
            ) {
                $aResult['error'] =
                    'Protected merge source resolves outside the protected runtime tree: '
                    . $sModuleFile;

                return $aResult;
            }

            $aBefore =
                @lstat($sSourceReal);

            if (!is_array($aBefore)) {
                $aResult['error'] =
                    'Protected merge source metadata could not be read: '
                    . $sModuleFile;

                return $aResult;
            }

            if (
                (int)($aBefore['nlink'] ?? 1)
                !== 1
            ) {
                $aResult['error'] =
                    'Protected merge source has an unsafe hard-link count: '
                    . $sModuleFile;

                return $aResult;
            }

            $sSha256 =
                @hash_file(
                    'sha256',
                    $sSourceReal
                );

            if (
                $sSha256 === false
                || strlen($sSha256) !== 64
            ) {
                $aResult['error'] =
                    'Could not calculate SHA-256 for protected merge source: '
                    . $sModuleFile;

                return $aResult;
            }

            clearstatcache(
                true,
                $sSourceReal
            );

            $aAfter =
                @lstat($sSourceReal);

            if (!is_array($aAfter)) {
                $aResult['error'] =
                    'Protected merge source metadata could not be re-read after hashing: '
                    . $sModuleFile;

                return $aResult;
            }

            /*
             * Refuse a source that changed while its content
             * hash was being calculated.
             */
            foreach (
                array(
                    'dev',
                    'ino',
                    'size',
                    'mtime',
                    'ctime',
                    'uid',
                    'gid',
                    'nlink',
                )
                as $sKey
            ) {
                if (
                    (int)($aBefore[$sKey] ?? -1)
                    !==
                    (int)($aAfter[$sKey] ?? -1)
                ) {
                    $aResult['error'] =
                        'Protected merge source changed while being hashed: '
                        . $sModuleFile;

                    return $aResult;
                }
            }

            if (
                (
                    (int)($aBefore['mode'] ?? 0)
                    & 07777
                )
                !==
                (
                    (int)($aAfter['mode'] ?? 0)
                    & 07777
                )
            ) {
                $aResult['error'] =
                    'Protected merge source mode changed while being hashed: '
                    . $sModuleFile;

                return $aResult;
            }

            $iSize =
                (int)($aAfter['size'] ?? 0);

            $aManifest[] = array(
                'file' =>
                    $sModuleFile,

                'relative_path' =>
                    $sRelative,

                'sha256' =>
                    strtolower($sSha256),

                'size' =>
                    $iSize,

                'mode' =>
                    sprintf(
                        '%04o',
                        (int)$aAfter['mode']
                        & 07777
                    ),

                'uid' =>
                    (int)($aAfter['uid'] ?? -1),

                'gid' =>
                    (int)($aAfter['gid'] ?? -1),

                /*
                 * Source-state evidence only. Current metadata
                 * restoration promises mode/uid/gid, not times.
                 */
                'mtime' =>
                    (int)($aAfter['mtime'] ?? 0),

                'ctime' =>
                    (int)($aAfter['ctime'] ?? 0),
            );

            $iManifestBytes +=
                $iSize;
        }

        usort(
            $aManifest,
            static function ($a, $b) {
                return strcmp(
                    (string)($a['file'] ?? ''),
                    (string)($b['file'] ?? '')
                );
            }
        );

        if (
            count($aManifest)
            !== count($aRestoreFiles)
        ) {
            $aResult['error'] =
                'Not every approved runtime restore source was hashed.';

            return $aResult;
        }

        $aFingerprint = array(
            'journal_id' =>
                $iJournalId,

            'protection_id' =>
                (string)(
                    $aProtected['protection_id']
                    ?? ''
                ),

            'merge_plan_id' =>
                $sFreshMergePlanId,

            'protected_inventory_id' =>
                (string)(
                    $aPlan['protected_inventory_id']
                    ?? ''
                ),

            'module' =>
                (string)(
                    $aPlan['module']
                    ?? ''
                ),

            'version' =>
                (string)(
                    $aPlan['version']
                    ?? ''
                ),

            'manifest' =>
                $aManifest,
        );

        $sJson = json_encode(
            $aFingerprint,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($sJson === false)
            $sJson = serialize($aFingerprint);

        $aResult['ok'] = true;
        $aResult['ready'] = true;

        $aResult['fresh_merge_plan_id'] =
            $sFreshMergePlanId;

        $aResult['protection_id'] =
            (string)(
                $aProtected['protection_id']
                ?? ''
            );

        $aResult['protected_inventory_id'] =
            (string)(
                $aPlan['protected_inventory_id']
                ?? ''
            );

        $aResult['module'] =
            (string)(
                $aPlan['module']
                ?? ''
            );

        $aResult['version'] =
            (string)(
                $aPlan['version']
                ?? ''
            );

        $aResult['manifest_count'] =
            count($aManifest);

        $aResult['manifest_bytes'] =
            $iManifestBytes;

        $aResult['execution_manifest_id'] =
            hash('sha256', $sJson);

        $aResult['manifest'] =
            $aManifest;

        return $aResult;
    }


    protected function persistRuntimeMergeExecutionManifest(
        $iJournalId,
        array $aExecution
    ) {
        $iJournalId = (int)$iJournalId;

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'manifest_path' => '',
            'manifest_file_sha256' => '',
            'manifest_file_bytes' => 0,
            'error' => '',
        );

        if (
            $iJournalId <= 0
            || empty($aExecution['ok'])
            || empty($aExecution['ready'])
        ) {
            $aResult['error'] =
                'A valid ready execution manifest is required.';

            return $aResult;
        }

        $sExecutionManifestId =
            strtolower(
                trim(
                    (string)(
                        $aExecution[
                            'execution_manifest_id'
                        ]
                        ?? ''
                    )
                )
            );

        $sMergePlanId =
            trim(
                (string)(
                    $aExecution['fresh_merge_plan_id']
                    ?? ''
                )
            );

        $sProtectionId =
            trim(
                (string)(
                    $aExecution['protection_id']
                    ?? ''
                )
            );

        $aManifest =
            $aExecution['manifest']
            ?? array();

        if (
            strlen($sExecutionManifestId) !== 64
            || $sMergePlanId === ''
            || $sProtectionId === ''
            || !is_array($aManifest)
            || count($aManifest)
                !== (int)(
                    $aExecution['manifest_count']
                    ?? -1
                )
        ) {
            $aResult['error'] =
                'Execution manifest identity or contents are incomplete.';

            return $aResult;
        }

        /*
         * Re-read the authoritative protected journal.
         */
        $aJournal = $this->_oDb->getRow(
            "SELECT *
             FROM `my_una_doctor_repairs`
             WHERE `id` = ?
             LIMIT 1",
            array($iJournalId)
        );

        if (
            empty($aJournal)
            || (string)$aJournal['status']
                !== 'runtime_protected'
        ) {
            $aResult['error'] =
                'Manifest persistence requires a runtime-protected journal.';

            return $aResult;
        }

        $aProtected = json_decode(
            (string)$aJournal['result_json'],
            true
        );

        if (
            !is_array($aProtected)
            || ($aProtected['phase'] ?? '')
                !== 'runtime_protected'
            || empty($aProtected['runtime_moved'])
        ) {
            $aResult['error'] =
                'Runtime-protected journal state is incomplete.';

            return $aResult;
        }

        if (
            !hash_equals(
                (string)(
                    $aProtected['protection_id']
                    ?? ''
                ),
                $sProtectionId
            )
        ) {
            $aResult['error'] =
                'Execution manifest protection ID no longer matches the journal.';

            return $aResult;
        }

        $aSafety =
            $aProtected['safety']
            ?? array();

        $sBasePath =
            (string)(
                $aSafety['base_path']
                ?? ''
            );

        $sProtectedPath =
            (string)(
                $aSafety['protected_runtime_path']
                ?? ''
            );

        if (
            $sBasePath === ''
            || $sProtectedPath === ''
            || !is_dir($sBasePath)
            || is_link($sBasePath)
            || !is_dir($sProtectedPath)
            || is_link($sProtectedPath)
        ) {
            $aResult['error'] =
                'Journal safety paths are unavailable or unsafe.';

            return $aResult;
        }

        $sBaseReal =
            realpath($sBasePath);

        $sProtectedReal =
            realpath($sProtectedPath);

        if (
            $sBaseReal === false
            || $sProtectedReal === false
        ) {
            $aResult['error'] =
                'Journal safety paths could not be resolved.';

            return $aResult;
        }

        /*
         * The manifest must live BESIDE the protected runtime
         * tree, never inside it. Otherwise it would modify the
         * protected runtime inventory fingerprint.
         */
        if (
            strpos(
                $sProtectedReal,
                $sBaseReal . DIRECTORY_SEPARATOR
            ) !== 0
        ) {
            $aResult['error'] =
                'Protected runtime tree is not inside the journal safety base.';

            return $aResult;
        }

        $sManifestPath =
            $sBaseReal
            . DIRECTORY_SEPARATOR
            . 'runtime-merge-journal-'
            . $iJournalId
            . '.json';

        /*
         * Persist the complete cryptographic execution contract,
         * not merely the raw file array.
         */
        $aPayload = array(
            'schema' =>
                'my_una_doctor_runtime_merge_manifest_v1',

            'journal_id' =>
                $iJournalId,

            'module' =>
                (string)(
                    $aExecution['module']
                    ?? ''
                ),

            'version' =>
                (string)(
                    $aExecution['version']
                    ?? ''
                ),

            'protection_id' =>
                $sProtectionId,

            'merge_plan_id' =>
                $sMergePlanId,

            'execution_manifest_id' =>
                $sExecutionManifestId,

            'protected_inventory_id' =>
                (string)(
                    $aExecution[
                        'protected_inventory_id'
                    ]
                    ?? ''
                ),

            'manifest_count' =>
                (int)(
                    $aExecution['manifest_count']
                    ?? 0
                ),

            'manifest_bytes' =>
                (int)(
                    $aExecution['manifest_bytes']
                    ?? 0
                ),

            'manifest' =>
                $aManifest,
        );

        $sJson = json_encode(
            $aPayload,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($sJson === false) {
            $aResult['error'] =
                'Execution manifest JSON encoding failed.';

            return $aResult;
        }

        $sExpectedFileHash =
            hash(
                'sha256',
                $sJson
            );

        /*
         * Idempotent handling:
         * if the exact durable manifest already exists, verify
         * it instead of rewriting it.
         */
        if (
            file_exists($sManifestPath)
            || is_link($sManifestPath)
        ) {
            if (
                !is_file($sManifestPath)
                || is_link($sManifestPath)
            ) {
                $aResult['error'] =
                    'Existing runtime merge manifest path is unsafe.';

                return $aResult;
            }

            $sExistingHash =
                @hash_file(
                    'sha256',
                    $sManifestPath
                );

            if (
                $sExistingHash === false
                || !hash_equals(
                    $sExpectedFileHash,
                    strtolower($sExistingHash)
                )
            ) {
                $aResult['error'] =
                    'A different runtime merge manifest already exists for this journal.';

                return $aResult;
            }

            $aResult['ok'] = true;
            $aResult['manifest_path'] =
                $sManifestPath;
            $aResult['manifest_file_sha256'] =
                $sExpectedFileHash;
            $aResult['manifest_file_bytes'] =
                (int)filesize($sManifestPath);

            return $aResult;
        }

        try {
            $sNonce =
                bin2hex(
                    random_bytes(8)
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
            $sBaseReal
            . DIRECTORY_SEPARATOR
            . '.runtime-merge-journal-'
            . $iJournalId
            . '-'
            . $sNonce
            . '.tmp';

        $h = @fopen(
            $sTemporary,
            'xb'
        );

        if ($h === false) {
            $aResult['error'] =
                'Could not create temporary runtime merge manifest.';

            return $aResult;
        }

        $bWriteOk = true;
        $iLength = strlen($sJson);
        $iOffset = 0;

        while ($iOffset < $iLength) {
            $iWritten =
                @fwrite(
                    $h,
                    substr(
                        $sJson,
                        $iOffset
                    )
                );

            if (
                $iWritten === false
                || $iWritten <= 0
            ) {
                $bWriteOk = false;
                break;
            }

            $iOffset += $iWritten;
        }

        if ($bWriteOk)
            $bWriteOk = @fflush($h);

        if (
            $bWriteOk
            && function_exists('fsync')
        ) {
            $bWriteOk = @fsync($h);
        }

        @fclose($h);

        if (!$bWriteOk) {
            @unlink($sTemporary);

            $aResult['error'] =
                'Could not durably write runtime merge manifest.';

            return $aResult;
        }

        if (!@chmod($sTemporary, 0600)) {
            @unlink($sTemporary);

            $aResult['error'] =
                'Could not secure temporary runtime merge manifest permissions.';

            return $aResult;
        }

        $sTemporaryHash =
            @hash_file(
                'sha256',
                $sTemporary
            );

        if (
            $sTemporaryHash === false
            || !hash_equals(
                $sExpectedFileHash,
                strtolower($sTemporaryHash)
            )
        ) {
            @unlink($sTemporary);

            $aResult['error'] =
                'Temporary runtime merge manifest failed SHA-256 verification.';

            return $aResult;
        }

        /*
         * link() gives us atomic create-without-overwrite
         * semantics on the same filesystem.
         */
        if (!@link(
            $sTemporary,
            $sManifestPath
        )) {
            @unlink($sTemporary);

            $aResult['error'] =
                'Could not atomically publish runtime merge manifest.';

            return $aResult;
        }

        @unlink($sTemporary);

        clearstatcache(
            true,
            $sManifestPath
        );

        if (
            !is_file($sManifestPath)
            || is_link($sManifestPath)
        ) {
            $aResult['error'] =
                'Published runtime merge manifest is unavailable or unsafe.';

            return $aResult;
        }

        $sFinalHash =
            @hash_file(
                'sha256',
                $sManifestPath
            );

        if (
            $sFinalHash === false
            || !hash_equals(
                $sExpectedFileHash,
                strtolower($sFinalHash)
            )
        ) {
            $aResult['error'] =
                'Published runtime merge manifest failed final SHA-256 verification.';

            return $aResult;
        }

        $aResult['ok'] = true;
        $aResult['changes_applied'] = true;
        $aResult['manifest_path'] =
            $sManifestPath;
        $aResult['manifest_file_sha256'] =
            $sExpectedFileHash;
        $aResult['manifest_file_bytes'] =
            (int)filesize($sManifestPath);

        return $aResult;
    }


    protected function loadAndVerifyRuntimeMergeManifest(
        $iJournalId
    ) {
        $iJournalId = (int)$iJournalId;

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'manifest' => array(),
            'error' => '',
        );

        if ($iJournalId <= 0) {
            $aResult['error'] =
                'A valid runtime merge journal ID is required.';

            return $aResult;
        }

        $aJournal = $this->_oDb->getRow(
            "SELECT *
             FROM `my_una_doctor_repairs`
             WHERE `id` = ?
             LIMIT 1",
            array($iJournalId)
        );

        if (
            empty($aJournal)
            || (string)$aJournal['status']
                !== 'runtime_merging'
        ) {
            $aResult['error'] =
                'Runtime merge manifest verification requires a runtime-merging journal.';

            return $aResult;
        }

        $aState = json_decode(
            (string)$aJournal['result_json'],
            true
        );

        if (
            !is_array($aState)
            || ($aState['phase'] ?? '')
                !== 'runtime_merging'
            || empty($aState['runtime_moved'])
        ) {
            $aResult['error'] =
                'Runtime-merging journal state is incomplete.';

            return $aResult;
        }

        $aMerge =
            $aState['merge']
            ?? array();

        $aRuntime =
            $aState['runtime']
            ?? array();

        $aSafety =
            $aState['safety']
            ?? array();

        if (
            !is_array($aMerge)
            || !is_array($aRuntime)
            || !is_array($aSafety)
        ) {
            $aResult['error'] =
                'Runtime merge recovery state is incomplete.';

            return $aResult;
        }

        $sManifestPath =
            (string)(
                $aMerge['manifest_path']
                ?? ''
            );

        $sExpectedFileHash =
            strtolower(
                (string)(
                    $aMerge[
                        'manifest_file_sha256'
                    ]
                    ?? ''
                )
            );

        $sExecutionManifestId =
            strtolower(
                (string)(
                    $aMerge[
                        'execution_manifest_id'
                    ]
                    ?? ''
                )
            );

        $sMergePlanId =
            (string)(
                $aMerge['merge_plan_id']
                ?? ''
            );

        $sProtectionId =
            (string)(
                $aState['protection_id']
                ?? ''
            );

        $sProtectedInventoryId =
            (string)(
                $aMerge[
                    'protected_inventory_id'
                ]
                ?? ''
            );

        $sBasePath =
            (string)(
                $aSafety['base_path']
                ?? ''
            );

        $sProtectedPath =
            (string)(
                $aSafety[
                    'protected_runtime_path'
                ]
                ?? ''
            );

        if (
            $sManifestPath === ''
            || strlen($sExpectedFileHash) !== 64
            || strlen($sExecutionManifestId) !== 64
            || $sMergePlanId === ''
            || $sProtectionId === ''
            || $sProtectedInventoryId === ''
            || $sBasePath === ''
            || $sProtectedPath === ''
        ) {
            $aResult['error'] =
                'Runtime merge manifest identity is incomplete.';

            return $aResult;
        }

        if (
            !is_dir($sBasePath)
            || is_link($sBasePath)
            || !is_dir($sProtectedPath)
            || is_link($sProtectedPath)
            || !is_file($sManifestPath)
            || is_link($sManifestPath)
        ) {
            $aResult['error'] =
                'Runtime merge safety or manifest path is unavailable or unsafe.';

            return $aResult;
        }

        $sBaseReal =
            realpath($sBasePath);

        $sProtectedReal =
            realpath($sProtectedPath);

        $sManifestReal =
            realpath($sManifestPath);

        if (
            $sBaseReal === false
            || $sProtectedReal === false
            || $sManifestReal === false
        ) {
            $aResult['error'] =
                'Runtime merge recovery paths could not be resolved.';

            return $aResult;
        }

        /*
         * Both protected runtime and sidecar must be direct
         * journal-safety children. The sidecar must never be
         * inside the protected runtime inventory.
         */
        if (
            dirname($sManifestReal) !== $sBaseReal
            || basename($sManifestReal)
                !== (
                    'runtime-merge-journal-'
                    . $iJournalId
                    . '.json'
                )
            || strpos(
                $sProtectedReal,
                $sBaseReal
                . DIRECTORY_SEPARATOR
            ) !== 0
            || strpos(
                $sManifestReal,
                $sProtectedReal
                . DIRECTORY_SEPARATOR
            ) === 0
        ) {
            $aResult['error'] =
                'Runtime merge sidecar location does not match the journal safety contract.';

            return $aResult;
        }

        $aManifestStat =
            @lstat($sManifestReal);

        if (
            !is_array($aManifestStat)
            || (int)(
                $aManifestStat['nlink']
                ?? 0
            ) !== 1
            || (
                (int)(
                    $aManifestStat['mode']
                    ?? 0
                )
                & 07777
            ) !== 0600
        ) {
            $aResult['error'] =
                'Runtime merge sidecar filesystem metadata is unsafe.';

            return $aResult;
        }

        $iExpectedFileBytes =
            (int)(
                $aMerge[
                    'manifest_file_bytes'
                ]
                ?? -1
            );

        if (
            $iExpectedFileBytes < 0
            || (int)$aManifestStat['size']
                !== $iExpectedFileBytes
        ) {
            $aResult['error'] =
                'Runtime merge sidecar size no longer matches the journal.';

            return $aResult;
        }

        $sActualFileHash =
            @hash_file(
                'sha256',
                $sManifestReal
            );

        if (
            $sActualFileHash === false
            || !hash_equals(
                $sExpectedFileHash,
                strtolower($sActualFileHash)
            )
        ) {
            $aResult['error'] =
                'Runtime merge sidecar SHA-256 no longer matches the journal.';

            return $aResult;
        }

        $sJson =
            @file_get_contents(
                $sManifestReal
            );

        if ($sJson === false) {
            $aResult['error'] =
                'Runtime merge sidecar could not be read.';

            return $aResult;
        }

        $aPayload =
            json_decode(
                $sJson,
                true
            );

        if (
            !is_array($aPayload)
            || (
                $aPayload['schema']
                ?? ''
            ) !==
                'my_una_doctor_runtime_merge_manifest_v1'
        ) {
            $aResult['error'] =
                'Runtime merge sidecar schema is invalid.';

            return $aResult;
        }

        /*
         * Verify every durable identity copied into the sidecar.
         */
        if (
            (int)(
                $aPayload['journal_id']
                ?? 0
            ) !== $iJournalId
            || (string)(
                $aPayload['module']
                ?? ''
            ) !== (string)(
                $aJournal['module']
                ?? ''
            )
            || !hash_equals(
                $sProtectionId,
                (string)(
                    $aPayload[
                        'protection_id'
                    ]
                    ?? ''
                )
            )
            || !hash_equals(
                $sMergePlanId,
                (string)(
                    $aPayload[
                        'merge_plan_id'
                    ]
                    ?? ''
                )
            )
            || !hash_equals(
                $sExecutionManifestId,
                strtolower(
                    (string)(
                        $aPayload[
                            'execution_manifest_id'
                        ]
                        ?? ''
                    )
                )
            )
            || !hash_equals(
                $sProtectedInventoryId,
                (string)(
                    $aPayload[
                        'protected_inventory_id'
                    ]
                    ?? ''
                )
            )
            || (string)(
                $aPayload['version']
                ?? ''
            ) !== (string)(
                $aMerge['module_version']
                ?? ''
            )
        ) {
            $aResult['error'] =
                'Runtime merge sidecar identity does not match its journal.';

            return $aResult;
        }

        $aManifest =
            $aPayload['manifest']
            ?? array();

        $iExpectedCount =
            (int)(
                $aMerge['manifest_count']
                ?? -1
            );

        $iExpectedBytes =
            (int)(
                $aMerge['manifest_bytes']
                ?? -1
            );

        if (
            !is_array($aManifest)
            || $iExpectedCount < 0
            || count($aManifest)
                !== $iExpectedCount
            || (int)(
                $aPayload['manifest_count']
                ?? -1
            ) !== $iExpectedCount
            || (int)(
                $aPayload['manifest_bytes']
                ?? -1
            ) !== $iExpectedBytes
        ) {
            $aResult['error'] =
                'Runtime merge sidecar manifest counts are inconsistent.';

            return $aResult;
        }

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

        if ($sRuntimeRelative !== '')
            $sRuntimeRelative .= '/';

        if ($sRuntimeRelative === '') {
            $aResult['error'] =
                'Runtime merge journal has no valid runtime-relative prefix.';

            return $aResult;
        }

        $aSeen = array();
        $iCalculatedBytes = 0;
        $sPreviousFile = '';

        foreach ($aManifest as $aEntry) {
            if (!is_array($aEntry)) {
                $aResult['error'] =
                    'Runtime merge manifest contains a malformed entry.';

                return $aResult;
            }

            $sFile =
                str_replace(
                    '\\',
                    '/',
                    (string)(
                        $aEntry['file']
                        ?? ''
                    )
                );

            $sRelative =
                str_replace(
                    '\\',
                    '/',
                    (string)(
                        $aEntry[
                            'relative_path'
                        ]
                        ?? ''
                    )
                );

            $sSha256 =
                strtolower(
                    (string)(
                        $aEntry['sha256']
                        ?? ''
                    )
                );

            $iSize =
                (int)(
                    $aEntry['size']
                    ?? -1
                );

            $sMode =
                (string)(
                    $aEntry['mode']
                    ?? ''
                );

            $iUid =
                (int)(
                    $aEntry['uid']
                    ?? -1
                );

            $iGid =
                (int)(
                    $aEntry['gid']
                    ?? -1
                );

            if (
                $sFile === ''
                || $sRelative === ''
                || strpos(
                    $sFile,
                    $sRuntimeRelative
                ) !== 0
                || substr(
                    $sFile,
                    strlen($sRuntimeRelative)
                ) !== $sRelative
                || !preg_match(
                    '/^[a-f0-9]{64}$/',
                    $sSha256
                )
                || $iSize < 0
                || !preg_match(
                    '/^[0-7]{4}$/',
                    $sMode
                )
                || $iUid < 0
                || $iGid < 0
            ) {
                $aResult['error'] =
                    'Runtime merge manifest contains invalid file identity or metadata.';

                return $aResult;
            }

            $aParts =
                explode(
                    '/',
                    $sRelative
                );

            foreach ($aParts as $sPart) {
                if (
                    $sPart === ''
                    || $sPart === '.'
                    || $sPart === '..'
                ) {
                    $aResult['error'] =
                        'Runtime merge manifest contains an unsafe path: '
                        . $sFile;

                    return $aResult;
                }
            }

            if (
                isset($aSeen[$sFile])
                || (
                    $sPreviousFile !== ''
                    && strcmp(
                        $sPreviousFile,
                        $sFile
                    ) >= 0
                )
            ) {
                $aResult['error'] =
                    'Runtime merge manifest contains duplicate or unsorted paths.';

                return $aResult;
            }

            $aSeen[$sFile] = true;
            $sPreviousFile = $sFile;
            $iCalculatedBytes += $iSize;
        }

        if ($iCalculatedBytes !== $iExpectedBytes) {
            $aResult['error'] =
                'Runtime merge manifest byte total does not match the journal.';

            return $aResult;
        }

        /*
         * Reconstruct the exact fingerprint used when the
         * execution_manifest_id was originally created.
         */
        $aFingerprint = array(
            'journal_id' =>
                $iJournalId,

            'protection_id' =>
                $sProtectionId,

            'merge_plan_id' =>
                $sMergePlanId,

            'protected_inventory_id' =>
                $sProtectedInventoryId,

            'module' =>
                (string)(
                    $aPayload['module']
                    ?? ''
                ),

            'version' =>
                (string)(
                    $aPayload['version']
                    ?? ''
                ),

            'manifest' =>
                $aManifest,
        );

        $sFingerprintJson =
            json_encode(
                $aFingerprint,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

        if ($sFingerprintJson === false)
            $sFingerprintJson =
                serialize($aFingerprint);

        $sCalculatedExecutionId =
            hash(
                'sha256',
                $sFingerprintJson
            );

        if (
            !hash_equals(
                $sExecutionManifestId,
                $sCalculatedExecutionId
            )
        ) {
            $aResult['error'] =
                'Runtime merge execution manifest ID could not be reproduced.';

            return $aResult;
        }

        /*
         * The original protected runtime metadata inventory must
         * also remain exact before any merge recovery operation
         * is allowed to trust this sidecar.
         */
        $aProtectedVerify =
            $this->verifyRuntimeTreeAgainstPlan(
                $sProtectedReal,
                $aRuntime
            );

        if (empty($aProtectedVerify['ok'])) {
            $aResult['error'] =
                'Protected runtime verification failed while loading merge manifest: '
                . (
                    $aProtectedVerify['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        if (
            !hash_equals(
                $sProtectedInventoryId,
                (string)(
                    $aProtectedVerify[
                        'inspection'
                    ][
                        'runtime_inventory_id'
                    ]
                    ?? ''
                )
            )
        ) {
            $aResult['error'] =
                'Protected runtime inventory no longer matches the merge journal.';

            return $aResult;
        }

        $aResult['ok'] = true;
        $aResult['manifest_path'] =
            $sManifestReal;
        $aResult['manifest_file_sha256'] =
            $sActualFileHash;
        $aResult['execution_manifest_id'] =
            $sCalculatedExecutionId;
        $aResult['manifest_count'] =
            count($aManifest);
        $aResult['manifest_bytes'] =
            $iCalculatedBytes;
        $aResult['manifest'] =
            $aManifest;
        $aResult['state'] =
            $aState;

        return $aResult;
    }


    protected function inspectRuntimeMergeDestinationEntry(
        $iJournalId,
        $iManifestIndex
    ) {
        $iJournalId = (int)$iJournalId;
        $iManifestIndex = (int)$iManifestIndex;

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'manifest_index' => $iManifestIndex,
            'state' => '',
            'matches_manifest' => false,
            'error' => '',
        );

        $aVerified =
            $this->loadAndVerifyRuntimeMergeManifest(
                $iJournalId
            );

        if (empty($aVerified['ok'])) {
            $aResult['error'] =
                'Runtime merge manifest could not be verified: '
                . (
                    $aVerified['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        $aManifest =
            $aVerified['manifest']
            ?? array();

        if (
            $iManifestIndex < 0
            || !isset($aManifest[$iManifestIndex])
            || !is_array(
                $aManifest[$iManifestIndex]
            )
        ) {
            $aResult['error'] =
                'Runtime merge manifest index is invalid.';

            return $aResult;
        }

        $aEntry =
            $aManifest[$iManifestIndex];

        $aState =
            $aVerified['state']
            ?? array();

        $aRuntime =
            $aState['runtime']
            ?? array();

        $sRuntimePath =
            (string)(
                $aRuntime['path']
                ?? ''
            );

        $sRelative =
            str_replace(
                '\\',
                '/',
                (string)(
                    $aEntry['relative_path']
                    ?? ''
                )
            );

        if (
            $sRuntimePath === ''
            || $sRelative === ''
            || !is_dir($sRuntimePath)
            || is_link($sRuntimePath)
        ) {
            $aResult['error'] =
                'Live runtime path is unavailable or unsafe.';

            return $aResult;
        }

        $sRuntimeReal =
            realpath($sRuntimePath);

        if ($sRuntimeReal === false) {
            $aResult['error'] =
                'Live runtime path could not be resolved.';

            return $aResult;
        }

        /*
         * relative_path has already been validated by the
         * immutable sidecar verifier, but rebuild defensively.
         */
        $aParts =
            explode('/', $sRelative);

        foreach ($aParts as $sPart) {
            if (
                $sPart === ''
                || $sPart === '.'
                || $sPart === '..'
            ) {
                $aResult['error'] =
                    'Runtime merge destination path is unsafe.';

                return $aResult;
            }
        }

        $sDestination =
            $sRuntimeReal
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $sRelative
            );

        /*
         * If the destination does not exist, verify any existing
         * parent components are real directories, not symlinks
         * or files.
         */
        if (
            !file_exists($sDestination)
            && !is_link($sDestination)
        ) {
            $sParent =
                dirname($sDestination);

            while (
                $sParent !== $sRuntimeReal
                && strpos(
                    $sParent,
                    $sRuntimeReal
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
                    $aResult['error'] =
                        'Runtime merge destination has an unsafe parent path.';

                    return $aResult;
                }

                $sNext =
                    dirname($sParent);

                if ($sNext === $sParent)
                    break;

                $sParent = $sNext;
            }

            $aResult['ok'] = true;
            $aResult['state'] = 'absent';
            $aResult['file'] =
                (string)(
                    $aEntry['file']
                    ?? ''
                );
            $aResult['relative_path'] =
                $sRelative;
            $aResult['destination'] =
                $sDestination;
            $aResult['entry'] =
                $aEntry;

            return $aResult;
        }

        /*
         * Existing destinations must be plain single-link files.
         */
        if (
            !is_file($sDestination)
            || is_link($sDestination)
        ) {
            $aResult['error'] =
                'Existing runtime merge destination is not a safe regular file.';

            return $aResult;
        }

        $sDestinationReal =
            realpath($sDestination);

        if (
            $sDestinationReal === false
            || strpos(
                $sDestinationReal,
                $sRuntimeReal
                . DIRECTORY_SEPARATOR
            ) !== 0
        ) {
            $aResult['error'] =
                'Runtime merge destination resolves outside the live runtime tree.';

            return $aResult;
        }

        $aBefore =
            @lstat($sDestinationReal);

        if (
            !is_array($aBefore)
            || (int)(
                $aBefore['nlink']
                ?? 0
            ) !== 1
        ) {
            $aResult['error'] =
                'Runtime merge destination metadata or hard-link count is unsafe.';

            return $aResult;
        }

        $sActualSha256 =
            @hash_file(
                'sha256',
                $sDestinationReal
            );

        if ($sActualSha256 === false) {
            $aResult['error'] =
                'Runtime merge destination SHA-256 could not be calculated.';

            return $aResult;
        }

        clearstatcache(
            true,
            $sDestinationReal
        );

        $aAfter =
            @lstat($sDestinationReal);

        if (!is_array($aAfter)) {
            $aResult['error'] =
                'Runtime merge destination metadata could not be re-read.';

            return $aResult;
        }

        /*
         * Refuse a destination that changes during inspection.
         */
        foreach (
            array(
                'dev',
                'ino',
                'size',
                'mtime',
                'ctime',
                'uid',
                'gid',
                'nlink',
            )
            as $sKey
        ) {
            if (
                (int)($aBefore[$sKey] ?? -1)
                !==
                (int)($aAfter[$sKey] ?? -1)
            ) {
                $aResult['error'] =
                    'Runtime merge destination changed during verification.';

                return $aResult;
            }
        }

        $sExpectedSha256 =
            strtolower(
                (string)(
                    $aEntry['sha256']
                    ?? ''
                )
            );

        $iExpectedSize =
            (int)(
                $aEntry['size']
                ?? -1
            );

        $iExpectedMode =
            octdec(
                (string)(
                    $aEntry['mode']
                    ?? '0000'
                )
            );

        $iExpectedUid =
            (int)(
                $aEntry['uid']
                ?? -1
            );

        $iExpectedGid =
            (int)(
                $aEntry['gid']
                ?? -1
            );

        $bMatches =
            hash_equals(
                $sExpectedSha256,
                strtolower($sActualSha256)
            )
            && (int)$aAfter['size']
                === $iExpectedSize
            && (
                (int)$aAfter['mode']
                & 07777
            ) === $iExpectedMode
            && (int)$aAfter['uid']
                === $iExpectedUid
            && (int)$aAfter['gid']
                === $iExpectedGid;

        $aResult['ok'] = true;
        $aResult['state'] =
            $bMatches
                ? 'exact'
                : 'mismatch';

        $aResult['matches_manifest'] =
            $bMatches;

        $aResult['file'] =
            (string)(
                $aEntry['file']
                ?? ''
            );

        $aResult['relative_path'] =
            $sRelative;

        $aResult['destination'] =
            $sDestinationReal;

        $aResult['actual_sha256'] =
            strtolower($sActualSha256);

        $aResult['actual_size'] =
            (int)$aAfter['size'];

        $aResult['actual_mode'] =
            sprintf(
                '%04o',
                (int)$aAfter['mode']
                & 07777
            );

        $aResult['actual_uid'] =
            (int)$aAfter['uid'];

        $aResult['actual_gid'] =
            (int)$aAfter['gid'];

        $aResult['actual_dev'] =
            (int)($aAfter['dev'] ?? -1);

        $aResult['actual_ino'] =
            (int)($aAfter['ino'] ?? -1);

        $aResult['actual_nlink'] =
            (int)($aAfter['nlink'] ?? -1);

        $aResult['actual_mtime'] =
            (int)($aAfter['mtime'] ?? -1);

        $aResult['actual_ctime'] =
            (int)($aAfter['ctime'] ?? -1);

        $aResult['entry'] =
            $aEntry;

        return $aResult;
    }


    protected function inspectRuntimeMergeDestinationEntryFromSession(
        array $aSession,
        $iManifestIndex
    ) {
        $iManifestIndex = (int)$iManifestIndex;

        $iJournalId =
            (int)(
                $aSession['journal_id']
                ?? 0
            );

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'manifest_index' => $iManifestIndex,
            'state' => '',
            'matches_manifest' => false,
            'error' => '',
        );

        /*
         * The session itself must already have been established
         * by prepareRuntimeMergeBatchSession().
         */
        if (
            empty($aSession['ok'])
            || $iJournalId <= 0
            || empty($aSession['session_id'])
        ) {
            $aResult['error'] =
                'Runtime merge batch session is invalid.';

            return $aResult;
        }

        $aManifest =
            $aSession['manifest']
            ?? array();

        if (
            $iManifestIndex < 0
            || !isset($aManifest[$iManifestIndex])
            || !is_array(
                $aManifest[$iManifestIndex]
            )
        ) {
            $aResult['error'] =
                'Runtime merge manifest index is invalid.';

            return $aResult;
        }

        $aEntry =
            $aManifest[$iManifestIndex];

        $aState =
            $aSession['state']
            ?? array();

        if (
            !is_array($aState)
            || ($aState['phase'] ?? '')
                !== 'runtime_merging'
            || empty($aState['runtime_moved'])
        ) {
            $aResult['error'] =
                'Runtime merge batch session state is invalid.';

            return $aResult;
        }

        $aRuntime =
            $aState['runtime']
            ?? array();

        $sRuntimePath =
            (string)(
                $aRuntime['path']
                ?? ''
            );

        $sRelative =
            str_replace(
                '\\',
                '/',
                (string)(
                    $aEntry['relative_path']
                    ?? ''
                )
            );

        if (
            $sRuntimePath === ''
            || $sRelative === ''
            || !is_dir($sRuntimePath)
            || is_link($sRuntimePath)
        ) {
            $aResult['error'] =
                'Live runtime path is unavailable or unsafe.';

            return $aResult;
        }

        $sRuntimeReal =
            realpath($sRuntimePath);

        if ($sRuntimeReal === false) {
            $aResult['error'] =
                'Live runtime path could not be resolved.';

            return $aResult;
        }

        /*
         * relative_path was validated by the immutable manifest
         * verifier when the session was created, but rebuild
         * defensively exactly as the reference inspector does.
         */
        $aParts =
            explode(
                '/',
                $sRelative
            );

        foreach ($aParts as $sPart) {
            if (
                $sPart === ''
                || $sPart === '.'
                || $sPart === '..'
            ) {
                $aResult['error'] =
                    'Runtime merge destination path is unsafe.';

                return $aResult;
            }
        }

        $sDestination =
            $sRuntimeReal
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $sRelative
            );

        /*
         * Absent destination: every existing parent component
         * beneath the runtime root must remain a real directory.
         */
        if (
            !file_exists($sDestination)
            && !is_link($sDestination)
        ) {
            $sParent =
                dirname($sDestination);

            while (
                $sParent !== $sRuntimeReal
                && strpos(
                    $sParent,
                    $sRuntimeReal
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
                    $aResult['error'] =
                        'Runtime merge destination has an unsafe parent path.';

                    return $aResult;
                }

                $sNext =
                    dirname($sParent);

                if ($sNext === $sParent)
                    break;

                $sParent =
                    $sNext;
            }

            $aResult['ok'] = true;
            $aResult['state'] =
                'absent';

            $aResult['file'] =
                (string)(
                    $aEntry['file']
                    ?? ''
                );

            $aResult['relative_path'] =
                $sRelative;

            $aResult['destination'] =
                $sDestination;

            $aResult['entry'] =
                $aEntry;

            return $aResult;
        }

        /*
         * Existing destinations must be plain single-link files.
         */
        if (
            !is_file($sDestination)
            || is_link($sDestination)
        ) {
            $aResult['error'] =
                'Existing runtime merge destination is not a safe regular file.';

            return $aResult;
        }

        $sDestinationReal =
            realpath($sDestination);

        if (
            $sDestinationReal === false
            || strpos(
                $sDestinationReal,
                $sRuntimeReal
                . DIRECTORY_SEPARATOR
            ) !== 0
        ) {
            $aResult['error'] =
                'Runtime merge destination resolves outside the live runtime tree.';

            return $aResult;
        }

        $aBefore =
            @lstat(
                $sDestinationReal
            );

        if (
            !is_array($aBefore)
            || (int)(
                $aBefore['nlink']
                ?? 0
            ) !== 1
        ) {
            $aResult['error'] =
                'Runtime merge destination metadata or hard-link count is unsafe.';

            return $aResult;
        }

        $sActualSha256 =
            @hash_file(
                'sha256',
                $sDestinationReal
            );

        if ($sActualSha256 === false) {
            $aResult['error'] =
                'Runtime merge destination SHA-256 could not be calculated.';

            return $aResult;
        }

        clearstatcache(
            true,
            $sDestinationReal
        );

        $aAfter =
            @lstat(
                $sDestinationReal
            );

        if (!is_array($aAfter)) {
            $aResult['error'] =
                'Runtime merge destination metadata could not be re-read.';

            return $aResult;
        }

        /*
         * Refuse a destination that changes during inspection.
         */
        foreach (
            array(
                'dev',
                'ino',
                'size',
                'mtime',
                'ctime',
                'uid',
                'gid',
                'nlink',
            )
            as $sKey
        ) {
            if (
                (int)(
                    $aBefore[$sKey]
                    ?? -1
                )
                !==
                (int)(
                    $aAfter[$sKey]
                    ?? -1
                )
            ) {
                $aResult['error'] =
                    'Runtime merge destination changed during verification.';

                return $aResult;
            }
        }

        $sExpectedSha256 =
            strtolower(
                (string)(
                    $aEntry['sha256']
                    ?? ''
                )
            );

        $iExpectedSize =
            (int)(
                $aEntry['size']
                ?? -1
            );

        $iExpectedMode =
            octdec(
                (string)(
                    $aEntry['mode']
                    ?? '0000'
                )
            );

        $iExpectedUid =
            (int)(
                $aEntry['uid']
                ?? -1
            );

        $iExpectedGid =
            (int)(
                $aEntry['gid']
                ?? -1
            );

        $bMatches =
            hash_equals(
                $sExpectedSha256,
                strtolower(
                    $sActualSha256
                )
            )
            && (int)$aAfter['size']
                === $iExpectedSize
            && (
                (int)$aAfter['mode']
                & 07777
            ) === $iExpectedMode
            && (int)$aAfter['uid']
                === $iExpectedUid
            && (int)$aAfter['gid']
                === $iExpectedGid;

        $aResult['ok'] =
            true;

        $aResult['state'] =
            $bMatches
                ? 'exact'
                : 'mismatch';

        $aResult['matches_manifest'] =
            $bMatches;

        $aResult['file'] =
            (string)(
                $aEntry['file']
                ?? ''
            );

        $aResult['relative_path'] =
            $sRelative;

        $aResult['destination'] =
            $sDestinationReal;

        $aResult['actual_sha256'] =
            strtolower(
                $sActualSha256
            );

        $aResult['actual_size'] =
            (int)$aAfter['size'];

        $aResult['actual_mode'] =
            sprintf(
                '%04o',
                (int)$aAfter['mode']
                & 07777
            );

        $aResult['actual_uid'] =
            (int)$aAfter['uid'];

        $aResult['actual_gid'] =
            (int)$aAfter['gid'];

        $aResult['actual_dev'] =
            (int)(
                $aAfter['dev']
                ?? -1
            );

        $aResult['actual_ino'] =
            (int)(
                $aAfter['ino']
                ?? -1
            );

        $aResult['actual_nlink'] =
            (int)(
                $aAfter['nlink']
                ?? -1
            );

        $aResult['actual_mtime'] =
            (int)(
                $aAfter['mtime']
                ?? -1
            );

        $aResult['actual_ctime'] =
            (int)(
                $aAfter['ctime']
                ?? -1
            );

        $aResult['entry'] =
            $aEntry;

        return $aResult;
    }


    protected function ensureRuntimeMergeLedger(
        $iJournalId
    ) {
        $iJournalId = (int)$iJournalId;

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'created' => false,
            'reused' => false,
            'journal_id' => $iJournalId,
            'ledger_path' => '',
            'ledger_id' => '',
            'error' => '',
        );

        $aVerified =
            $this->loadAndVerifyRuntimeMergeManifest(
                $iJournalId
            );

        if (empty($aVerified['ok'])) {
            $aResult['error'] =
                'Runtime merge manifest could not be verified before ledger initialization: '
                . (
                    $aVerified['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        $aState =
            $aVerified['state']
            ?? array();

        $aMerge =
            $aState['merge']
            ?? array();

        $aSafety =
            $aState['safety']
            ?? array();

        $aProgress =
            $aMerge['progress']
            ?? array();

        if (
            !is_array($aState)
            || !is_array($aMerge)
            || !is_array($aSafety)
            || !is_array($aProgress)
            || ($aState['phase'] ?? '')
                !== 'runtime_merging'
            || empty($aState['runtime_moved'])
        ) {
            $aResult['error'] =
                'Runtime merge journal state is incomplete for ledger initialization.';

            return $aResult;
        }

        $sBasePath =
            (string)(
                $aSafety['base_path']
                ?? ''
            );

        if (
            $sBasePath === ''
            || !is_dir($sBasePath)
            || is_link($sBasePath)
        ) {
            $aResult['error'] =
                'Runtime merge safety base is unavailable or unsafe.';

            return $aResult;
        }

        $sBaseReal =
            realpath($sBasePath);

        if ($sBaseReal === false) {
            $aResult['error'] =
                'Runtime merge safety base could not be resolved.';

            return $aResult;
        }

        $sLedgerPath =
            $sBaseReal
            . DIRECTORY_SEPARATOR
            . 'runtime-merge-journal-'
            . $iJournalId
            . '-ledger.jsonl';

        $aIdentity = array(
            'schema' =>
                'my_una_doctor_runtime_merge_ledger_v1',

            'journal_id' =>
                $iJournalId,

            'module' =>
                (string)(
                    $aState['module']
                    ?? ''
                ),

            'merge_plan_id' =>
                (string)(
                    $aMerge['merge_plan_id']
                    ?? ''
                ),

            'execution_manifest_id' =>
                (string)(
                    $aMerge[
                        'execution_manifest_id'
                    ]
                    ?? ''
                ),

            'manifest_file_sha256' =>
                (string)(
                    $aMerge[
                        'manifest_file_sha256'
                    ]
                    ?? ''
                ),

            'manifest_count' =>
                (int)(
                    $aMerge['manifest_count']
                    ?? 0
                ),

            'manifest_bytes' =>
                (int)(
                    $aMerge['manifest_bytes']
                    ?? 0
                ),

            /*
             * Use the durable merge start time rather than
             * time() so retries produce the same header.
             */
            'started_at' =>
                (int)(
                    $aMerge['started_at']
                    ?? 0
                ),
        );

        $sIdentityJson =
            json_encode(
                $aIdentity,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

        if ($sIdentityJson === false) {
            $aResult['error'] =
                'Runtime merge ledger identity could not be encoded.';

            return $aResult;
        }

        $sLedgerId =
            hash(
                'sha256',
                $sIdentityJson
            );

        $aHeader =
            $aIdentity;

        $aHeader['ledger_id'] =
            $sLedgerId;

        $aHeader['event'] =
            'ledger_header';

        $sHeaderJson =
            json_encode(
                $aHeader,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

        if ($sHeaderJson === false) {
            $aResult['error'] =
                'Runtime merge ledger header could not be encoded.';

            return $aResult;
        }

        $sHeaderLine =
            $sHeaderJson . "\n";

        $sStoredLedgerPath =
            (string)(
                $aMerge['ledger_path']
                ?? ''
            );

        $sStoredLedgerId =
            (string)(
                $aMerge['ledger_id']
                ?? ''
            );

        /*
         * Journal metadata must be all-or-nothing.
         */
        if (
            ($sStoredLedgerPath === '')
            !== ($sStoredLedgerId === '')
        ) {
            $aResult['error'] =
                'Runtime merge journal contains incomplete ledger identity.';

            return $aResult;
        }

        if ($sStoredLedgerPath !== '') {
            if (
                $sStoredLedgerPath !== $sLedgerPath
                || !hash_equals(
                    $sLedgerId,
                    $sStoredLedgerId
                )
            ) {
                $aResult['error'] =
                    'Runtime merge journal ledger identity does not match the current merge contract.';

                return $aResult;
            }
        }

        $verifyLedger = static function (
            $sPath,
            $sExpectedHeader,
            $sExpectedLedgerId
        ) {
            $aVerify = array(
                'ok' => false,
                'error' => '',
            );

            if (
                !is_file($sPath)
                || is_link($sPath)
            ) {
                $aVerify['error'] =
                    'Runtime merge ledger is not a safe regular file.';

                return $aVerify;
            }

            $aStat =
                @lstat($sPath);

            if (
                !is_array($aStat)
                || (int)(
                    $aStat['nlink']
                    ?? 0
                ) !== 1
                || (
                    (int)(
                        $aStat['mode']
                        ?? 0
                    )
                    & 07777
                ) !== 0600
            ) {
                $aVerify['error'] =
                    'Runtime merge ledger filesystem metadata is unsafe.';

                return $aVerify;
            }

            $h =
                @fopen(
                    $sPath,
                    'rb'
                );

            if ($h === false) {
                $aVerify['error'] =
                    'Runtime merge ledger could not be opened.';

                return $aVerify;
            }

            $sFirstLine =
                fgets($h);

            @fclose($h);

            if ($sFirstLine === false) {
                $aVerify['error'] =
                    'Runtime merge ledger header could not be read.';

                return $aVerify;
            }

            $aHeader =
                json_decode(
                    rtrim(
                        $sFirstLine,
                        "\r\n"
                    ),
                    true
                );

            if (
                !is_array($aHeader)
                || (
                    $aHeader['event']
                    ?? ''
                ) !== 'ledger_header'
                || (
                    $aHeader['schema']
                    ?? ''
                ) !==
                    'my_una_doctor_runtime_merge_ledger_v1'
                || !hash_equals(
                    $sExpectedLedgerId,
                    (string)(
                        $aHeader['ledger_id']
                        ?? ''
                    )
                )
                || $sFirstLine
                    !== $sExpectedHeader
            ) {
                $aVerify['error'] =
                    'Runtime merge ledger header does not match the durable merge contract.';

                return $aVerify;
            }

            $aVerify['ok'] = true;

            return $aVerify;
        };

        if (
            file_exists($sLedgerPath)
            || is_link($sLedgerPath)
        ) {
            $aLedgerVerify =
                $verifyLedger(
                    $sLedgerPath,
                    $sHeaderLine,
                    $sLedgerId
                );

            if (empty($aLedgerVerify['ok'])) {
                $aResult['error'] =
                    $aLedgerVerify['error'];

                return $aResult;
            }

            /*
             * If the journal does not yet know this ledger,
             * only the pristine header is acceptable. Any
             * event beyond the header would be unjournaled
             * mutation history and must fail closed.
             */
            if ($sStoredLedgerPath === '') {
                $sExisting =
                    @file_get_contents(
                        $sLedgerPath
                    );

                if (
                    $sExisting === false
                    || $sExisting !== $sHeaderLine
                ) {
                    $aResult['error'] =
                        'An unjournaled runtime merge ledger already contains events.';

                    return $aResult;
                }
            }

            $aResult['reused'] = true;
        } else {
            /*
             * A brand-new ledger may be created only before
             * any generated runtime file has been installed.
             */
            if (
                (int)(
                    $aProgress['copied_files']
                    ?? -1
                ) !== 0
                || (int)(
                    $aProgress['copied_bytes']
                    ?? -1
                ) !== 0
                || !empty(
                    $aProgress[
                        'runtime_files_changed'
                    ]
                )
            ) {
                $aResult['error'] =
                    'Runtime merge ledger cannot be created after runtime file mutation has begun.';

                return $aResult;
            }

            try {
                $sRandom =
                    bin2hex(
                        random_bytes(8)
                    );
            } catch (Throwable $e) {
                $aResult['error'] =
                    'Could not generate a secure temporary ledger name.';

                return $aResult;
            }

            $sTemporary =
                $sBaseReal
                . DIRECTORY_SEPARATOR
                . '.runtime-merge-ledger-'
                . $iJournalId
                . '-'
                . getmypid()
                . '-'
                . $sRandom
                . '.tmp';

            $h =
                @fopen(
                    $sTemporary,
                    'xb'
                );

            if ($h === false) {
                $aResult['error'] =
                    'Could not create temporary runtime merge ledger.';

                return $aResult;
            }

            $iOffset = 0;
            $iLength =
                strlen(
                    $sHeaderLine
                );

            $bWriteOk = true;

            while ($iOffset < $iLength) {
                $iWritten =
                    @fwrite(
                        $h,
                        substr(
                            $sHeaderLine,
                            $iOffset
                        )
                    );

                if (
                    $iWritten === false
                    || $iWritten <= 0
                ) {
                    $bWriteOk = false;
                    break;
                }

                $iOffset +=
                    $iWritten;
            }

            if ($bWriteOk)
                $bWriteOk =
                    @fflush($h);

            if (
                $bWriteOk
                && function_exists('fsync')
            ) {
                $bWriteOk =
                    @fsync($h);
            }

            @fclose($h);

            if (!$bWriteOk) {
                @unlink($sTemporary);

                $aResult['error'] =
                    'Could not durably write runtime merge ledger header.';

                return $aResult;
            }

            if (
                !@chmod(
                    $sTemporary,
                    0600
                )
            ) {
                @unlink($sTemporary);

                $aResult['error'] =
                    'Could not secure temporary runtime merge ledger permissions.';

                return $aResult;
            }

            /*
             * Atomic create-without-overwrite on the same
             * filesystem, matching the manifest publisher.
             */
            if (
                !@link(
                    $sTemporary,
                    $sLedgerPath
                )
            ) {
                @unlink($sTemporary);

                $aResult['error'] =
                    'Could not atomically publish runtime merge ledger.';

                return $aResult;
            }

            @unlink($sTemporary);

            clearstatcache(
                true,
                $sLedgerPath
            );

            $aLedgerVerify =
                $verifyLedger(
                    $sLedgerPath,
                    $sHeaderLine,
                    $sLedgerId
                );

            if (empty($aLedgerVerify['ok'])) {
                $aResult['error'] =
                    'Published runtime merge ledger failed verification: '
                    . $aLedgerVerify['error'];

                return $aResult;
            }

            $aResult['created'] = true;
            $aResult['changes_applied'] = true;
        }

        /*
         * If the journal already contains exact ledger
         * metadata, no DB mutation is required.
         */
        if ($sStoredLedgerPath === '') {
            $aState['merge']['ledger_schema'] =
                'my_una_doctor_runtime_merge_ledger_v1';

            $aState['merge']['ledger_path'] =
                $sLedgerPath;

            $aState['merge']['ledger_id'] =
                $sLedgerId;

            $aState['merge']['ledger_events'] =
                0;

            $sStateJson =
                json_encode(
                    $aState,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_INVALID_UTF8_SUBSTITUTE
                );

            if ($sStateJson === false) {
                $aResult['error'] =
                    'Runtime merge state could not be encoded with ledger identity.';

                return $aResult;
            }

            try {
                $this->requireRepairJournalUpdate(
                    $iJournalId,
                    array(
                        'status' =>
                            'runtime_merging',

                        'result_json' =>
                            $sStateJson,

                        'error_text' =>
                            '',
                    )
                );
            } catch (Throwable $e) {
                $aResult['error'] =
                    'Runtime merge ledger was created but its journal identity could not be persisted: '
                    . $e->getMessage();

                return $aResult;
            }

            /*
             * Independently verify the exact merge ledger
             * identity survived the DB write.
             */
            $aJournalVerify =
                $this->_oDb->getRow(
                    "SELECT `status`, `result_json`
                     FROM `my_una_doctor_repairs`
                     WHERE `id` = ?
                     LIMIT 1",
                    array($iJournalId)
                );

            $aStateVerify =
                json_decode(
                    (string)(
                        $aJournalVerify[
                            'result_json'
                        ]
                        ?? ''
                    ),
                    true
                );

            $aMergeVerify =
                is_array($aStateVerify)
                    ? (
                        $aStateVerify['merge']
                        ?? array()
                    )
                    : array();

            if (
                empty($aJournalVerify)
                || (string)(
                    $aJournalVerify['status']
                    ?? ''
                ) !== 'runtime_merging'
                || !is_array($aStateVerify)
                || (
                    $aStateVerify['phase']
                    ?? ''
                ) !== 'runtime_merging'
                || !hash_equals(
                    $sLedgerPath,
                    (string)(
                        $aMergeVerify[
                            'ledger_path'
                        ]
                        ?? ''
                    )
                )
                || !hash_equals(
                    $sLedgerId,
                    (string)(
                        $aMergeVerify[
                            'ledger_id'
                        ]
                        ?? ''
                    )
                )
                || (int)(
                    $aMergeVerify[
                        'ledger_events'
                    ]
                    ?? -1
                ) !== 0
                || (int)(
                    $aMergeVerify[
                        'progress'
                    ]['copied_files']
                    ?? -1
                ) !== 0
                || !empty(
                    $aMergeVerify[
                        'progress'
                    ]['runtime_files_changed']
                )
            ) {
                $aResult['error'] =
                    'Runtime merge ledger journal verification failed.';

                return $aResult;
            }

            $aResult['changes_applied'] = true;
        }

        $aResult['ok'] = true;
        $aResult['ledger_path'] =
            $sLedgerPath;
        $aResult['ledger_id'] =
            $sLedgerId;

        return $aResult;
    }


    protected function readAndVerifyRuntimeMergeLedger(
        $iJournalId
    ) {
        $iJournalId = (int)$iJournalId;

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'event_count' => 0,
            'journal_event_count' => 0,
            'unjournaled_events' => 0,
            'events' => array(),
            'entry_states' => array(),
            'error' => '',
        );

        $aVerified =
            $this->loadAndVerifyRuntimeMergeManifest(
                $iJournalId
            );

        if (empty($aVerified['ok'])) {
            $aResult['error'] =
                'Runtime merge manifest could not be verified before reading the ledger: '
                . (
                    $aVerified['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        $aState =
            $aVerified['state']
            ?? array();

        $aMerge =
            $aState['merge']
            ?? array();

        $aRuntime =
            $aState['runtime']
            ?? array();

        $aManifest =
            $aVerified['manifest']
            ?? array();

        if (
            !is_array($aState)
            || !is_array($aMerge)
            || !is_array($aRuntime)
            || !is_array($aManifest)
            || ($aState['phase'] ?? '')
                !== 'runtime_merging'
        ) {
            $aResult['error'] =
                'Runtime merge state is incomplete for ledger verification.';

            return $aResult;
        }

        $sLedgerPath =
            (string)(
                $aMerge['ledger_path']
                ?? ''
            );

        $sLedgerId =
            strtolower(
                (string)(
                    $aMerge['ledger_id']
                    ?? ''
                )
            );

        $sExecutionManifestId =
            strtolower(
                (string)(
                    $aMerge[
                        'execution_manifest_id'
                    ]
                    ?? ''
                )
            );

        $iJournalEventCount =
            (int)(
                $aMerge['ledger_events']
                ?? -1
            );

        if (
            $sLedgerPath === ''
            || strlen($sLedgerId) !== 64
            || strlen($sExecutionManifestId) !== 64
            || $iJournalEventCount < 0
        ) {
            $aResult['error'] =
                'Runtime merge ledger identity is not journaled completely.';

            return $aResult;
        }

        if (
            !is_file($sLedgerPath)
            || is_link($sLedgerPath)
        ) {
            $aResult['error'] =
                'Runtime merge ledger is unavailable or unsafe.';

            return $aResult;
        }

        $aLedgerStat =
            @lstat($sLedgerPath);

        if (
            !is_array($aLedgerStat)
            || (int)(
                $aLedgerStat['nlink']
                ?? 0
            ) !== 1
            || (
                (int)(
                    $aLedgerStat['mode']
                    ?? 0
                )
                & 07777
            ) !== 0600
        ) {
            $aResult['error'] =
                'Runtime merge ledger filesystem metadata is unsafe.';

            return $aResult;
        }

        $h =
            @fopen(
                $sLedgerPath,
                'rb'
            );

        if ($h === false) {
            $aResult['error'] =
                'Runtime merge ledger could not be opened.';

            return $aResult;
        }

        $sHeaderLine =
            fgets($h);

        if ($sHeaderLine === false) {
            @fclose($h);

            $aResult['error'] =
                'Runtime merge ledger header is missing.';

            return $aResult;
        }

        $aHeader =
            json_decode(
                rtrim(
                    $sHeaderLine,
                    "\r\n"
                ),
                true
            );

        if (
            !is_array($aHeader)
            || (
                $aHeader['event']
                ?? ''
            ) !== 'ledger_header'
            || (
                $aHeader['schema']
                ?? ''
            ) !==
                'my_una_doctor_runtime_merge_ledger_v1'
            || (int)(
                $aHeader['journal_id']
                ?? 0
            ) !== $iJournalId
            || !hash_equals(
                $sLedgerId,
                strtolower(
                    (string)(
                        $aHeader['ledger_id']
                        ?? ''
                    )
                )
            )
            || !hash_equals(
                $sExecutionManifestId,
                strtolower(
                    (string)(
                        $aHeader[
                            'execution_manifest_id'
                        ]
                        ?? ''
                    )
                )
            )
            || !hash_equals(
                (string)(
                    $aMerge['merge_plan_id']
                    ?? ''
                ),
                (string)(
                    $aHeader['merge_plan_id']
                    ?? ''
                )
            )
            || !hash_equals(
                (string)(
                    $aMerge[
                        'manifest_file_sha256'
                    ]
                    ?? ''
                ),
                (string)(
                    $aHeader[
                        'manifest_file_sha256'
                    ]
                    ?? ''
                )
            )
            || (int)(
                $aHeader['manifest_count']
                ?? -1
            ) !== (int)(
                $aMerge['manifest_count']
                ?? -2
            )
            || (int)(
                $aHeader['manifest_bytes']
                ?? -1
            ) !== (int)(
                $aMerge['manifest_bytes']
                ?? -2
            )
        ) {
            @fclose($h);

            $aResult['error'] =
                'Runtime merge ledger header does not match the journal contract.';

            return $aResult;
        }

        /*
         * Reproduce ledger_id from the same immutable identity
         * used by ensureRuntimeMergeLedger().
         */
        $aIdentity =
            $aHeader;

        unset(
            $aIdentity['ledger_id'],
            $aIdentity['event']
        );

        $sIdentityJson =
            json_encode(
                $aIdentity,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

        if (
            $sIdentityJson === false
            || !hash_equals(
                $sLedgerId,
                hash(
                    'sha256',
                    $sIdentityJson
                )
            )
        ) {
            @fclose($h);

            $aResult['error'] =
                'Runtime merge ledger ID could not be reproduced.';

            return $aResult;
        }

        $sRuntimePath =
            (string)(
                $aRuntime['path']
                ?? ''
            );

        if (
            $sRuntimePath === ''
            || !is_dir($sRuntimePath)
            || is_link($sRuntimePath)
        ) {
            @fclose($h);

            $aResult['error'] =
                'Live runtime root is unavailable or unsafe during ledger verification.';

            return $aResult;
        }

        $sRuntimeReal =
            realpath($sRuntimePath);

        if ($sRuntimeReal === false) {
            @fclose($h);

            $aResult['error'] =
                'Live runtime root could not be resolved during ledger verification.';

            return $aResult;
        }

        $aEvents = array();
        $aEntryStates = array();
        $iExpectedSequence = 1;

        while (($sLine = fgets($h)) !== false) {
            if (
                $sLine === ''
                || substr($sLine, -1) !== "\n"
            ) {
                @fclose($h);

                $aResult['error'] =
                    'Runtime merge ledger contains an incomplete event line.';

                return $aResult;
            }

            $aEvent =
                json_decode(
                    rtrim(
                        $sLine,
                        "\r\n"
                    ),
                    true
                );

            if (
                !is_array($aEvent)
                || (
                    $aEvent['schema']
                    ?? ''
                ) !==
                    'my_una_doctor_runtime_merge_ledger_event_v1'
                || !hash_equals(
                    $sLedgerId,
                    strtolower(
                        (string)(
                            $aEvent['ledger_id']
                            ?? ''
                        )
                    )
                )
                || (int)(
                    $aEvent['sequence']
                    ?? 0
                ) !== $iExpectedSequence
            ) {
                @fclose($h);

                $aResult['error'] =
                    'Runtime merge ledger contains an invalid or out-of-sequence event.';

                return $aResult;
            }

            $sEvent =
                (string)(
                    $aEvent['event']
                    ?? ''
                );

            $iManifestIndex =
                (int)(
                    $aEvent[
                        'manifest_index'
                    ]
                    ?? -1
                );

            if (
                !in_array(
                    $sEvent,
                    array(
                        'intent',
                        'installed',
                        'rolled_back',
                    ),
                    true
                )
                || $iManifestIndex < 0
                || !isset(
                    $aManifest[
                        $iManifestIndex
                    ]
                )
                || !is_array(
                    $aManifest[
                        $iManifestIndex
                    ]
                )
            ) {
                @fclose($h);

                $aResult['error'] =
                    'Runtime merge ledger references an invalid event or manifest index.';

                return $aResult;
            }

            $aManifestEntry =
                $aManifest[
                    $iManifestIndex
                ];

            $sRelative =
                (string)(
                    $aManifestEntry[
                        'relative_path'
                    ]
                    ?? ''
                );

            $sExpectedDestination =
                $sRuntimeReal
                . DIRECTORY_SEPARATOR
                . str_replace(
                    '/',
                    DIRECTORY_SEPARATOR,
                    $sRelative
                );

            if (
                (string)(
                    $aEvent['file']
                    ?? ''
                ) !== (string)(
                    $aManifestEntry[
                        'file'
                    ]
                    ?? ''
                )
                || (string)(
                    $aEvent['relative_path']
                    ?? ''
                ) !== $sRelative
                || !hash_equals(
                    strtolower(
                        (string)(
                            $aManifestEntry[
                                'sha256'
                            ]
                            ?? ''
                        )
                    ),
                    strtolower(
                        (string)(
                            $aEvent['sha256']
                            ?? ''
                        )
                    )
                )
                || (int)(
                    $aEvent['size']
                    ?? -1
                ) !== (int)(
                    $aManifestEntry['size']
                    ?? -2
                )
                || (string)(
                    $aEvent['mode']
                    ?? ''
                ) !== (string)(
                    $aManifestEntry['mode']
                    ?? ''
                )
                || (int)(
                    $aEvent['uid']
                    ?? -1
                ) !== (int)(
                    $aManifestEntry['uid']
                    ?? -2
                )
                || (int)(
                    $aEvent['gid']
                    ?? -1
                ) !== (int)(
                    $aManifestEntry['gid']
                    ?? -2
                )
                || (string)(
                    $aEvent['destination']
                    ?? ''
                ) !== $sExpectedDestination
            ) {
                @fclose($h);

                $aResult['error'] =
                    'Runtime merge ledger event does not match its immutable manifest entry.';

                return $aResult;
            }

            if (
                $sEvent === 'installed'
                && (
                    (int)(
                        $aEvent['installed_dev']
                        ?? -1
                    ) < 0
                    || (int)(
                        $aEvent['installed_ino']
                        ?? -1
                    ) <= 0
                    || (int)(
                        $aEvent['installed_ctime']
                        ?? -1
                    ) < 0
                )
            ) {
                @fclose($h);

                $aResult['error'] =
                    'Installed runtime merge ledger event has no filesystem identity.';

                return $aResult;
            }

            $sPreviousState =
                (string)(
                    $aEntryStates[
                        $iManifestIndex
                    ]
                    ?? ''
                );

            $bTransitionOk =
                (
                    $sEvent === 'intent'
                    && (
                        $sPreviousState === ''
                        || $sPreviousState
                            === 'rolled_back'
                    )
                )
                || (
                    $sEvent === 'installed'
                    && $sPreviousState
                        === 'intent'
                )
                || (
                    $sEvent === 'rolled_back'
                    && in_array(
                        $sPreviousState,
                        array(
                            'intent',
                            'installed',
                        ),
                        true
                    )
                );

            if (!$bTransitionOk) {
                @fclose($h);

                $aResult['error'] =
                    'Runtime merge ledger contains an invalid state transition for manifest entry '
                    . $iManifestIndex
                    . '.';

                return $aResult;
            }

            $aEntryStates[
                $iManifestIndex
            ] = $sEvent;

            $aEvents[] =
                $aEvent;

            $iExpectedSequence++;
        }

        if (!feof($h)) {
            @fclose($h);

            $aResult['error'] =
                'Runtime merge ledger could not be read completely.';

            return $aResult;
        }

        @fclose($h);

        $iEventCount =
            count($aEvents);

        if ($iJournalEventCount > $iEventCount) {
            $aResult['error'] =
                'Runtime merge journal claims more ledger events than exist durably.';

            return $aResult;
        }

        /*
         * A durable ledger may be ahead of the DB after a crash
         * between fsync() and journal update. Preserve this as
         * explicit recovery information rather than pretending
         * the histories are synchronized.
         */
        $aResult['ok'] = true;
        $aResult['ledger_path'] =
            $sLedgerPath;
        $aResult['ledger_id'] =
            $sLedgerId;
        $aResult['event_count'] =
            $iEventCount;
        $aResult['journal_event_count'] =
            $iJournalEventCount;
        $aResult['unjournaled_events'] =
            $iEventCount
            - $iJournalEventCount;
        $aResult['events'] =
            $aEvents;
        $aResult['entry_states'] =
            $aEntryStates;
        $aResult['manifest'] =
            $aManifest;
        $aResult['state'] =
            $aState;

        return $aResult;
    }


    protected function appendRuntimeMergeLedgerEvent(
        $iJournalId,
        $sEvent,
        $iManifestIndex
    ) {
        $iJournalId = (int)$iJournalId;
        $sEvent = trim((string)$sEvent);
        $iManifestIndex = (int)$iManifestIndex;

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'event' => $sEvent,
            'manifest_index' => $iManifestIndex,
            'sequence' => 0,
            'error' => '',
        );

        if (
            !in_array(
                $sEvent,
                array(
                    'intent',
                    'installed',
                    'rolled_back',
                ),
                true
            )
        ) {
            $aResult['error'] =
                'Unsupported runtime merge ledger event.';

            return $aResult;
        }

        /*
         * Get the journaled ledger path first, then lock the
         * ledger for the complete verify -> append -> DB update
         * transaction.
         */
        $aJournal =
            $this->_oDb->getRow(
                "SELECT `status`, `result_json`
                 FROM `my_una_doctor_repairs`
                 WHERE `id` = ?
                 LIMIT 1",
                array($iJournalId)
            );

        $aJournalState =
            json_decode(
                (string)(
                    $aJournal['result_json']
                    ?? ''
                ),
                true
            );

        $sLedgerPath =
            is_array($aJournalState)
                ? (string)(
                    $aJournalState[
                        'merge'
                    ]['ledger_path']
                    ?? ''
                )
                : '';

        if (
            empty($aJournal)
            || (string)(
                $aJournal['status']
                ?? ''
            ) !== 'runtime_merging'
            || $sLedgerPath === ''
            || !is_file($sLedgerPath)
            || is_link($sLedgerPath)
        ) {
            $aResult['error'] =
                'Runtime merge ledger is not ready for event append.';

            return $aResult;
        }

        $h =
            @fopen(
                $sLedgerPath,
                'c+b'
            );

        if ($h === false) {
            $aResult['error'] =
                'Runtime merge ledger could not be opened for append.';

            return $aResult;
        }

        if (!@flock($h, LOCK_EX)) {
            @fclose($h);

            $aResult['error'] =
                'Runtime merge ledger could not be locked.';

            return $aResult;
        }

        /*
         * Re-read everything while this writer owns the ledger
         * lock. A prior crash may have left a durable event ahead
         * of the DB; that requires recovery, not another append.
         */
        $aLedger =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (
            empty($aLedger['ok'])
            || (int)(
                $aLedger['unjournaled_events']
                ?? -1
            ) !== 0
        ) {
            @flock($h, LOCK_UN);
            @fclose($h);

            $aResult['error'] =
                !empty($aLedger['error'])
                    ? (string)$aLedger['error']
                    : 'Runtime merge ledger has an unjournaled durable tail and requires recovery.';

            return $aResult;
        }

        $aManifest =
            $aLedger['manifest']
            ?? array();

        if (
            $iManifestIndex < 0
            || !isset($aManifest[$iManifestIndex])
            || !is_array(
                $aManifest[$iManifestIndex]
            )
        ) {
            @flock($h, LOCK_UN);
            @fclose($h);

            $aResult['error'] =
                'Runtime merge ledger event references an invalid manifest index.';

            return $aResult;
        }

        $sPreviousState =
            (string)(
                $aLedger[
                    'entry_states'
                ][$iManifestIndex]
                ?? ''
            );

        $bTransitionOk =
            (
                $sEvent === 'intent'
                && (
                    $sPreviousState === ''
                    || $sPreviousState
                        === 'rolled_back'
                )
            )
            || (
                $sEvent === 'installed'
                && $sPreviousState
                    === 'intent'
            )
            || (
                $sEvent === 'rolled_back'
                && in_array(
                    $sPreviousState,
                    array(
                        'intent',
                        'installed',
                    ),
                    true
                )
            );

        if (!$bTransitionOk) {
            @flock($h, LOCK_UN);
            @fclose($h);

            $aResult['error'] =
                'Runtime merge ledger state transition is not allowed.';

            return $aResult;
        }

        /*
         * Tie every event to the current filesystem proof.
         */
        $aDestination =
            $this->inspectRuntimeMergeDestinationEntry(
                $iJournalId,
                $iManifestIndex
            );

        $sRequiredState =
            $sEvent === 'installed'
                ? 'exact'
                : 'absent';

        if (
            empty($aDestination['ok'])
            || (string)(
                $aDestination['state']
                ?? ''
            ) !== $sRequiredState
        ) {
            @flock($h, LOCK_UN);
            @fclose($h);

            $aResult['error'] =
                'Runtime merge ledger event refused because destination state is not '
                . $sRequiredState
                . '.';

            return $aResult;
        }

        $aEntry =
            $aManifest[$iManifestIndex];

        $iSequence =
            (int)(
                $aLedger['event_count']
                ?? 0
            )
            + 1;

        $aEvent = array(
            'schema' =>
                'my_una_doctor_runtime_merge_ledger_event_v1',

            'ledger_id' =>
                (string)(
                    $aLedger['ledger_id']
                    ?? ''
                ),

            'sequence' =>
                $iSequence,

            'event' =>
                $sEvent,

            'manifest_index' =>
                $iManifestIndex,

            'file' =>
                (string)(
                    $aEntry['file']
                    ?? ''
                ),

            'relative_path' =>
                (string)(
                    $aEntry['relative_path']
                    ?? ''
                ),

            'sha256' =>
                (string)(
                    $aEntry['sha256']
                    ?? ''
                ),

            'size' =>
                (int)(
                    $aEntry['size']
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

            'destination' =>
                (string)(
                    $aDestination[
                        'destination'
                    ]
                    ?? ''
                ),

            'installed_dev' =>
                $sEvent === 'installed'
                    ? (int)(
                        $aDestination['actual_dev']
                        ?? -1
                    )
                    : 0,

            'installed_ino' =>
                $sEvent === 'installed'
                    ? (int)(
                        $aDestination['actual_ino']
                        ?? -1
                    )
                    : 0,

            'installed_ctime' =>
                $sEvent === 'installed'
                    ? (int)(
                        $aDestination['actual_ctime']
                        ?? -1
                    )
                    : 0,

            'recorded_at' =>
                time(),
        );

        $sLine =
            json_encode(
                $aEvent,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

        if ($sLine === false) {
            @flock($h, LOCK_UN);
            @fclose($h);

            $aResult['error'] =
                'Runtime merge ledger event could not be encoded.';

            return $aResult;
        }

        $sLine .= "\n";

        if (
            @fseek($h, 0, SEEK_END) !== 0
        ) {
            @flock($h, LOCK_UN);
            @fclose($h);

            $aResult['error'] =
                'Runtime merge ledger could not seek to its append position.';

            return $aResult;
        }

        $iOffset = 0;
        $iLength = strlen($sLine);
        $bWriteOk = true;

        while ($iOffset < $iLength) {
            $iWritten =
                @fwrite(
                    $h,
                    substr(
                        $sLine,
                        $iOffset
                    )
                );

            if (
                $iWritten === false
                || $iWritten <= 0
            ) {
                $bWriteOk = false;
                break;
            }

            $iOffset += $iWritten;
        }

        if ($bWriteOk)
            $bWriteOk = @fflush($h);

        if (
            $bWriteOk
            && function_exists('fsync')
        ) {
            $bWriteOk = @fsync($h);
        }

        if (!$bWriteOk) {
            @flock($h, LOCK_UN);
            @fclose($h);

            $aResult['error'] =
                'Runtime merge ledger event could not be durably appended.';

            return $aResult;
        }

        /*
         * The ledger event is now durable. Advance only the
         * compact journal ledger counter. Runtime copy progress
         * is deliberately managed separately.
         */
        $aState =
            $aLedger['state']
            ?? array();

        $aState['merge']['ledger_events'] =
            $iSequence;

        $sStateJson =
            json_encode(
                $aState,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

        if ($sStateJson === false) {
            @flock($h, LOCK_UN);
            @fclose($h);

            $aResult['error'] =
                'Ledger event is durable but merge journal state could not be encoded. Recovery is required.';

            return $aResult;
        }

        try {
            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' =>
                        'runtime_merging',

                    'result_json' =>
                        $sStateJson,

                    'error_text' =>
                        '',
                )
            );
        } catch (Throwable $e) {
            @flock($h, LOCK_UN);
            @fclose($h);

            $aResult['error'] =
                'Ledger event is durable but journal advancement failed. Recovery is required: '
                . $e->getMessage();

            return $aResult;
        }

        /*
         * Independent verification: the durable ledger and DB
         * must now agree exactly.
         */
        $aVerify =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (
            empty($aVerify['ok'])
            || (int)(
                $aVerify['event_count']
                ?? -1
            ) !== $iSequence
            || (int)(
                $aVerify['journal_event_count']
                ?? -1
            ) !== $iSequence
            || (int)(
                $aVerify['unjournaled_events']
                ?? -1
            ) !== 0
            || (string)(
                $aVerify[
                    'entry_states'
                ][$iManifestIndex]
                ?? ''
            ) !== $sEvent
        ) {
            @flock($h, LOCK_UN);
            @fclose($h);

            $aResult['error'] =
                'Runtime merge ledger event journal verification failed.';

            return $aResult;
        }

        @flock($h, LOCK_UN);
        @fclose($h);

        $aResult['ok'] = true;
        $aResult['changes_applied'] = true;
        $aResult['sequence'] =
            $iSequence;
        $aResult['ledger_id'] =
            (string)(
                $aVerify['ledger_id']
                ?? ''
            );

        return $aResult;
    }


    protected function appendRuntimeMergeLedgerEventFromSession(
        array &$aSession,
        $hLedger,
        $sEvent,
        $iManifestIndex
    ) {
        $sEvent =
            trim(
                (string)$sEvent
            );

        $iManifestIndex =
            (int)$iManifestIndex;

        $iJournalId =
            (int)(
                $aSession['journal_id']
                ?? 0
            );

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'event' => $sEvent,
            'manifest_index' => $iManifestIndex,
            'sequence' => 0,
            'ledger_file_bytes' => 0,
            'error' => '',
        );

        if (
            empty($aSession['ok'])
            || $iJournalId <= 0
            || empty($aSession['session_id'])
        ) {
            $aResult['error'] =
                'Runtime merge batch session is invalid.';

            return $aResult;
        }

        if (
            !in_array(
                $sEvent,
                array(
                    'intent',
                    'installed',
                    'rolled_back',
                ),
                true
            )
        ) {
            $aResult['error'] =
                'Unsupported runtime merge ledger event.';

            return $aResult;
        }

        if (!is_resource($hLedger)) {
            $aResult['error'] =
                'Runtime merge ledger lock handle is invalid.';

            return $aResult;
        }

        /*
         * The future optimized batch executor owns this lock for
         * the entire bounded batch. Reassert it non-blockingly
         * here but deliberately do not release it here.
         */
        if (
            !@flock(
                $hLedger,
                LOCK_EX | LOCK_NB
            )
        ) {
            $aResult['error'] =
                'Runtime merge ledger is not exclusively locked by the batch session.';

            return $aResult;
        }

        $aManifest =
            $aSession['manifest']
            ?? array();

        if (
            $iManifestIndex < 0
            || !isset($aManifest[$iManifestIndex])
            || !is_array(
                $aManifest[$iManifestIndex]
            )
        ) {
            $aResult['error'] =
                'Runtime merge ledger event references an invalid manifest index.';

            return $aResult;
        }

        $aEntry =
            $aManifest[$iManifestIndex];

        $sLedgerPath =
            (string)(
                $aSession['ledger_path']
                ?? ''
            );

        $aIdentity =
            $aSession['identity']
            ?? array();

        $sExpectedLedgerId =
            (string)(
                $aIdentity['ledger_id']
                ?? ''
            );

        $sExpectedExecutionId =
            (string)(
                $aIdentity[
                    'execution_manifest_id'
                ]
                ?? ''
            );

        $sExpectedManifestHash =
            (string)(
                $aIdentity[
                    'manifest_file_sha256'
                ]
                ?? ''
            );

        $iExpectedLedgerDev =
            (int)(
                $aIdentity['ledger_dev']
                ?? -1
            );

        $iExpectedLedgerIno =
            (int)(
                $aIdentity['ledger_ino']
                ?? -1
            );

        $iExpectedBytes =
            (int)(
                $aSession[
                    'ledger_file_bytes'
                ]
                ?? -1
            );

        $iPreviousSequence =
            (int)(
                $aSession[
                    'ledger_event_count'
                ]
                ?? -1
            );

        if (
            $sLedgerPath === ''
            || !is_file($sLedgerPath)
            || is_link($sLedgerPath)
            || $sExpectedLedgerId === ''
            || strlen($sExpectedExecutionId) !== 64
            || strlen($sExpectedManifestHash) !== 64
            || $iExpectedBytes < 1
            || $iPreviousSequence < 0
        ) {
            $aResult['error'] =
                'Runtime merge batch session ledger boundary is incomplete.';

            return $aResult;
        }

        /*
         * Constant-time boundary proof under the exclusive lock:
         * the pathname and open handle must still reference the
         * exact ledger inode captured at session preparation.
         */
        clearstatcache(
            true,
            $sLedgerPath
        );

        $aPathStat =
            @lstat(
                $sLedgerPath
            );

        $aHandleStat =
            @fstat(
                $hLedger
            );

        if (
            !is_array($aPathStat)
            || !is_array($aHandleStat)
            || (int)(
                $aPathStat['dev']
                ?? -1
            ) !== $iExpectedLedgerDev
            || (int)(
                $aPathStat['ino']
                ?? -1
            ) !== $iExpectedLedgerIno
            || (int)(
                $aHandleStat['dev']
                ?? -2
            ) !== $iExpectedLedgerDev
            || (int)(
                $aHandleStat['ino']
                ?? -2
            ) !== $iExpectedLedgerIno
            || (int)(
                $aPathStat['nlink']
                ?? 0
            ) !== 1
            || (int)(
                $aHandleStat['nlink']
                ?? 0
            ) !== 1
            || (
                (int)$aPathStat['mode']
                & 07777
            ) !== 0600
            || (
                (int)$aHandleStat['mode']
                & 07777
            ) !== 0600
            || (int)(
                $aPathStat['size']
                ?? -1
            ) !== $iExpectedBytes
            || (int)(
                $aHandleStat['size']
                ?? -1
            ) !== $iExpectedBytes
        ) {
            $aResult['error'] =
                'Runtime merge ledger no longer matches the locked batch-session boundary.';

            return $aResult;
        }

        /*
         * The DB ledger counter must agree with the in-memory
         * locked boundary before another event is permitted.
         */
        $aJournal =
            $this->_oDb->getRow(
                "SELECT `status`, `result_json`
                 FROM `my_una_doctor_repairs`
                 WHERE `id` = ?
                 LIMIT 1",
                array($iJournalId)
            );

        $aJournalState =
            json_decode(
                (string)(
                    $aJournal['result_json']
                    ?? ''
                ),
                true
            );

        $aJournalMerge =
            is_array($aJournalState)
                ? (
                    $aJournalState['merge']
                    ?? array()
                )
                : array();

        if (
            empty($aJournal)
            || (string)(
                $aJournal['status']
                ?? ''
            ) !== 'runtime_merging'
            || !is_array($aJournalState)
            || (
                $aJournalState['phase']
                ?? ''
            ) !== 'runtime_merging'
            || empty(
                $aJournalState[
                    'runtime_moved'
                ]
            )
            || (string)(
                $aJournalMerge['ledger_path']
                ?? ''
            ) !== $sLedgerPath
            || (string)(
                $aJournalMerge['ledger_id']
                ?? ''
            ) !== $sExpectedLedgerId
            || (string)(
                $aJournalMerge[
                    'execution_manifest_id'
                ]
                ?? ''
            ) !== $sExpectedExecutionId
            || (string)(
                $aJournalMerge[
                    'manifest_file_sha256'
                ]
                ?? ''
            ) !== $sExpectedManifestHash
            || (int)(
                $aJournalMerge[
                    'ledger_events'
                ]
                ?? -1
            ) !== $iPreviousSequence
        ) {
            $aResult['error'] =
                'Runtime merge journal no longer matches the locked batch-session boundary.';

            return $aResult;
        }

        $sPreviousState =
            (string)(
                $aSession[
                    'entry_states'
                ][$iManifestIndex]
                ?? ''
            );

        $bTransitionOk =
            (
                $sEvent === 'intent'
                && (
                    $sPreviousState === ''
                    || $sPreviousState
                        === 'rolled_back'
                )
            )
            || (
                $sEvent === 'installed'
                && $sPreviousState
                    === 'intent'
            )
            || (
                $sEvent === 'rolled_back'
                && in_array(
                    $sPreviousState,
                    array(
                        'intent',
                        'installed',
                    ),
                    true
                )
            );

        if (!$bTransitionOk) {
            $aResult['error'] =
                'Runtime merge ledger state transition is not allowed.';

            return $aResult;
        }

        /*
         * Same destination proof as the reference appender,
         * using the already-verified session manifest.
         */
        $aDestination =
            $this->inspectRuntimeMergeDestinationEntryFromSession(
                $aSession,
                $iManifestIndex
            );

        $sRequiredState =
            $sEvent === 'installed'
                ? 'exact'
                : 'absent';

        if (
            empty($aDestination['ok'])
            || (string)(
                $aDestination['state']
                ?? ''
            ) !== $sRequiredState
        ) {
            $aResult['error'] =
                'Runtime merge ledger event refused because destination state is not '
                . $sRequiredState
                . '.';

            return $aResult;
        }

        $iSequence =
            $iPreviousSequence + 1;

        $aEvent = array(
            'schema' =>
                'my_una_doctor_runtime_merge_ledger_event_v1',

            'ledger_id' =>
                $sExpectedLedgerId,

            'sequence' =>
                $iSequence,

            'event' =>
                $sEvent,

            'manifest_index' =>
                $iManifestIndex,

            'file' =>
                (string)(
                    $aEntry['file']
                    ?? ''
                ),

            'relative_path' =>
                (string)(
                    $aEntry['relative_path']
                    ?? ''
                ),

            'sha256' =>
                (string)(
                    $aEntry['sha256']
                    ?? ''
                ),

            'size' =>
                (int)(
                    $aEntry['size']
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

            'destination' =>
                (string)(
                    $aDestination[
                        'destination'
                    ]
                    ?? ''
                ),

            'installed_dev' =>
                $sEvent === 'installed'
                    ? (int)(
                        $aDestination[
                            'actual_dev'
                        ]
                        ?? -1
                    )
                    : 0,

            'installed_ino' =>
                $sEvent === 'installed'
                    ? (int)(
                        $aDestination[
                            'actual_ino'
                        ]
                        ?? -1
                    )
                    : 0,

            'installed_ctime' =>
                $sEvent === 'installed'
                    ? (int)(
                        $aDestination[
                            'actual_ctime'
                        ]
                        ?? -1
                    )
                    : 0,

            'recorded_at' =>
                time(),
        );

        $sLine =
            json_encode(
                $aEvent,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

        if ($sLine === false) {
            $aResult['error'] =
                'Runtime merge ledger event could not be encoded.';

            return $aResult;
        }

        $sLine .= "\n";

        /*
         * The append must begin at exactly the byte boundary
         * established by this locked session.
         */
        if (
            @fseek(
                $hLedger,
                0,
                SEEK_END
            ) !== 0
        ) {
            $aResult['error'] =
                'Runtime merge ledger could not seek to its append position.';

            return $aResult;
        }

        $iStartOffset =
            @ftell(
                $hLedger
            );

        if (
            $iStartOffset === false
            || (int)$iStartOffset
                !== $iExpectedBytes
        ) {
            $aResult['error'] =
                'Runtime merge ledger append offset does not match the batch-session boundary.';

            return $aResult;
        }

        $iOffset = 0;
        $iLength =
            strlen($sLine);

        $bWriteOk =
            true;

        while ($iOffset < $iLength) {
            $iWritten =
                @fwrite(
                    $hLedger,
                    substr(
                        $sLine,
                        $iOffset
                    )
                );

            if (
                $iWritten === false
                || $iWritten <= 0
            ) {
                $bWriteOk =
                    false;
                break;
            }

            $iOffset +=
                $iWritten;
        }

        if ($bWriteOk)
            $bWriteOk =
                @fflush($hLedger);

        if (
            $bWriteOk
            && function_exists('fsync')
        ) {
            $bWriteOk =
                @fsync($hLedger);
        }

        if (!$bWriteOk) {
            $aResult['error'] =
                'Runtime merge ledger event could not be durably appended. Recovery is required.';

            return $aResult;
        }

        /*
         * Constant-size durable-byte verification: reread only
         * the line Doctor just appended, not the entire ledger.
         */
        if (
            @fseek(
                $hLedger,
                $iExpectedBytes,
                SEEK_SET
            ) !== 0
        ) {
            $aResult['error'] =
                'Runtime merge ledger event is durable but could not be reread for verification. Recovery is required.';

            return $aResult;
        }

        $sReadBack = '';

        while (
            strlen($sReadBack)
            < $iLength
        ) {
            $sChunk =
                @fread(
                    $hLedger,
                    $iLength
                    - strlen($sReadBack)
                );

            if (
                $sChunk === false
                || $sChunk === ''
            ) {
                break;
            }

            $sReadBack .=
                $sChunk;
        }

        if (
            strlen($sReadBack)
                !== $iLength
            || !hash_equals(
                $sLine,
                $sReadBack
            )
        ) {
            $aResult['error'] =
                'Runtime merge ledger event durable-byte verification failed. Recovery is required.';

            return $aResult;
        }

        $iNewBytes =
            $iExpectedBytes
            + $iLength;

        clearstatcache(
            true,
            $sLedgerPath
        );

        $aAfterPath =
            @lstat(
                $sLedgerPath
            );

        $aAfterHandle =
            @fstat(
                $hLedger
            );

        if (
            !is_array($aAfterPath)
            || !is_array($aAfterHandle)
            || (int)(
                $aAfterPath['dev']
                ?? -1
            ) !== $iExpectedLedgerDev
            || (int)(
                $aAfterPath['ino']
                ?? -1
            ) !== $iExpectedLedgerIno
            || (int)(
                $aAfterHandle['dev']
                ?? -2
            ) !== $iExpectedLedgerDev
            || (int)(
                $aAfterHandle['ino']
                ?? -2
            ) !== $iExpectedLedgerIno
            || (int)(
                $aAfterPath['nlink']
                ?? 0
            ) !== 1
            || (int)(
                $aAfterHandle['nlink']
                ?? 0
            ) !== 1
            || (int)(
                $aAfterPath['size']
                ?? -1
            ) !== $iNewBytes
            || (int)(
                $aAfterHandle['size']
                ?? -1
            ) !== $iNewBytes
        ) {
            $aResult['error'] =
                'Runtime merge ledger event is durable but the resulting ledger boundary is invalid. Recovery is required.';

            return $aResult;
        }

        /*
         * Re-read the journal after the durable append so an
         * unrelated concurrent journal mutation is never
         * overwritten by stale session JSON.
         */
        $aFreshJournal =
            $this->_oDb->getRow(
                "SELECT `status`, `result_json`
                 FROM `my_una_doctor_repairs`
                 WHERE `id` = ?
                 LIMIT 1",
                array($iJournalId)
            );

        $aFreshState =
            json_decode(
                (string)(
                    $aFreshJournal[
                        'result_json'
                    ]
                    ?? ''
                ),
                true
            );

        $aFreshMerge =
            is_array($aFreshState)
                ? (
                    $aFreshState['merge']
                    ?? array()
                )
                : array();

        if (
            empty($aFreshJournal)
            || (string)(
                $aFreshJournal['status']
                ?? ''
            ) !== 'runtime_merging'
            || !is_array($aFreshState)
            || (
                $aFreshState['phase']
                ?? ''
            ) !== 'runtime_merging'
            || (string)(
                $aFreshMerge['ledger_path']
                ?? ''
            ) !== $sLedgerPath
            || (string)(
                $aFreshMerge['ledger_id']
                ?? ''
            ) !== $sExpectedLedgerId
            || (string)(
                $aFreshMerge[
                    'execution_manifest_id'
                ]
                ?? ''
            ) !== $sExpectedExecutionId
            || (string)(
                $aFreshMerge[
                    'manifest_file_sha256'
                ]
                ?? ''
            ) !== $sExpectedManifestHash
            || (int)(
                $aFreshMerge[
                    'ledger_events'
                ]
                ?? -1
            ) !== $iPreviousSequence
        ) {
            $aResult['error'] =
                'Ledger event is durable but the merge journal changed before acknowledgement. Recovery is required.';

            return $aResult;
        }

        /*
         * Only now acknowledge the durable event in the DB.
         */
        $aFreshState['merge']['ledger_events'] =
            $iSequence;

        $sStateJson =
            json_encode(
                $aFreshState,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

        if ($sStateJson === false) {
            $aResult['error'] =
                'Ledger event is durable but merge journal state could not be encoded. Recovery is required.';

            return $aResult;
        }

        try {
            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' =>
                        'runtime_merging',

                    'result_json' =>
                        $sStateJson,

                    'error_text' =>
                        '',
                )
            );
        } catch (Throwable $e) {
            $aResult['error'] =
                'Ledger event is durable but journal advancement failed. Recovery is required: '
                . $e->getMessage();

            return $aResult;
        }

        /*
         * Independent DB verification without reparsing the
         * ledger or immutable manifest.
         */
        $aVerifyJournal =
            $this->_oDb->getRow(
                "SELECT `status`, `result_json`
                 FROM `my_una_doctor_repairs`
                 WHERE `id` = ?
                 LIMIT 1",
                array($iJournalId)
            );

        $aVerifyState =
            json_decode(
                (string)(
                    $aVerifyJournal[
                        'result_json'
                    ]
                    ?? ''
                ),
                true
            );

        $aVerifyMerge =
            is_array($aVerifyState)
                ? (
                    $aVerifyState['merge']
                    ?? array()
                )
                : array();

        if (
            empty($aVerifyJournal)
            || (string)(
                $aVerifyJournal['status']
                ?? ''
            ) !== 'runtime_merging'
            || !is_array($aVerifyState)
            || (
                $aVerifyState['phase']
                ?? ''
            ) !== 'runtime_merging'
            || (int)(
                $aVerifyMerge[
                    'ledger_events'
                ]
                ?? -1
            ) !== $iSequence
            || (string)(
                $aVerifyMerge['ledger_path']
                ?? ''
            ) !== $sLedgerPath
            || (string)(
                $aVerifyMerge['ledger_id']
                ?? ''
            ) !== $sExpectedLedgerId
            || (string)(
                $aVerifyMerge[
                    'execution_manifest_id'
                ]
                ?? ''
            ) !== $sExpectedExecutionId
        ) {
            $aResult['error'] =
                'Runtime merge ledger event journal verification failed.';

            return $aResult;
        }

        /*
         * Advance the in-memory locked session only after both
         * durable ledger and DB acknowledgement are proven.
         */
        $aSession['ledger_event_count'] =
            $iSequence;

        $aSession['ledger_file_bytes'] =
            $iNewBytes;

        $aSession[
            'entry_states'
        ][$iManifestIndex] =
            $sEvent;

        $aSession['state'] =
            $aVerifyState;

        $aSession['session_mutated'] =
            true;

        /*
         * Maintain useful in-memory state counts for the future
         * optimized progress writer.
         */
        if ($sEvent === 'intent') {
            $aSession[
                'intent_origins'
            ][$iManifestIndex] =
                $sPreviousState;

            if ($sPreviousState === '') {
                $aSession['untouched_count'] =
                    max(
                        0,
                        (int)(
                            $aSession[
                                'untouched_count'
                            ]
                            ?? 0
                        ) - 1
                    );
            } elseif (
                $sPreviousState
                    === 'rolled_back'
            ) {
                $aSession['rolled_back_count'] =
                    max(
                        0,
                        (int)(
                            $aSession[
                                'rolled_back_count'
                            ]
                            ?? 0
                        ) - 1
                    );
            }
        } elseif ($sEvent === 'installed') {
            $aSession['installed_count'] =
                (int)(
                    $aSession[
                        'installed_count'
                    ]
                    ?? 0
                ) + 1;

            $aSession['installed_bytes'] =
                (int)(
                    $aSession[
                        'installed_bytes'
                    ]
                    ?? 0
                )
                + (int)(
                    $aEntry['size']
                    ?? 0
                );

            unset(
                $aSession[
                    'intent_origins'
                ][$iManifestIndex]
            );
        } elseif (
            $sEvent === 'rolled_back'
        ) {
            if (
                $sPreviousState
                    === 'installed'
            ) {
                $aSession['installed_count'] =
                    max(
                        0,
                        (int)(
                            $aSession[
                                'installed_count'
                            ]
                            ?? 0
                        ) - 1
                    );

                $aSession['installed_bytes'] =
                    max(
                        0,
                        (int)(
                            $aSession[
                                'installed_bytes'
                            ]
                            ?? 0
                        )
                        - (int)(
                            $aEntry['size']
                            ?? 0
                        )
                    );
            }

            $aSession['rolled_back_count'] =
                (int)(
                    $aSession[
                        'rolled_back_count'
                    ]
                    ?? 0
                ) + 1;

            unset(
                $aSession[
                    'intent_origins'
                ][$iManifestIndex]
            );
        }

        $aResult['ok'] =
            true;

        $aResult['changes_applied'] =
            true;

        $aResult['sequence'] =
            $iSequence;

        $aResult['ledger_id'] =
            $sExpectedLedgerId;

        $aResult['ledger_file_bytes'] =
            $iNewBytes;

        $aResult['event_record'] =
            $aEvent;

        return $aResult;
    }


    protected function reconcileRuntimeMergeProgress(
        $iJournalId,
        $sPhase = 'copying'
    ) {
        $iJournalId = (int)$iJournalId;
        $sPhase = trim((string)$sPhase);

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'copied_files' => 0,
            'copied_bytes' => 0,
            'last_file' => '',
            'runtime_files_changed' => false,
            'error' => '',
        );

        if ($sPhase === '')
            $sPhase = 'copying';

        $aLedger =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (empty($aLedger['ok'])) {
            $aResult['error'] =
                'Runtime merge ledger could not be verified during progress reconciliation: '
                . (
                    $aLedger['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        if (
            (int)(
                $aLedger['unjournaled_events']
                ?? -1
            ) !== 0
        ) {
            $aResult['error'] =
                'Runtime merge progress cannot be reconciled while the ledger has an unjournaled durable tail.';

            return $aResult;
        }

        $aManifest =
            $aLedger['manifest']
            ?? array();

        $aEntryStates =
            $aLedger['entry_states']
            ?? array();

        $aEvents =
            $aLedger['events']
            ?? array();

        $iCopiedFiles = 0;
        $iCopiedBytes = 0;
        $sLastFile = '';

        foreach (
            $aEntryStates
            as $iIndex => $sState
        ) {
            if ($sState !== 'installed')
                continue;

            $iIndex = (int)$iIndex;

            if (
                !isset($aManifest[$iIndex])
                || !is_array(
                    $aManifest[$iIndex]
                )
            ) {
                $aResult['error'] =
                    'Installed ledger state references an invalid manifest entry.';

                return $aResult;
            }

            /*
             * Every currently installed entry must still be
             * exact before Doctor advertises it as copied.
             */
            $aDestination =
                $this->inspectRuntimeMergeDestinationEntry(
                    $iJournalId,
                    $iIndex
                );

            if (
                empty($aDestination['ok'])
                || (
                    $aDestination['state']
                    ?? ''
                ) !== 'exact'
            ) {
                $aResult['error'] =
                    'Installed runtime merge entry no longer matches its immutable manifest: '
                    . $iIndex;

                return $aResult;
            }

            $iCopiedFiles++;

            $iCopiedBytes +=
                (int)(
                    $aManifest[$iIndex]['size']
                    ?? 0
                );
        }

        /*
         * last_file is determined by the most recent installed
         * event whose entry is still currently installed.
         */
        for (
            $i = count($aEvents) - 1;
            $i >= 0;
            $i--
        ) {
            $aEvent =
                $aEvents[$i];

            if (
                ($aEvent['event'] ?? '')
                    !== 'installed'
            ) {
                continue;
            }

            $iIndex =
                (int)(
                    $aEvent['manifest_index']
                    ?? -1
                );

            if (
                (
                    $aEntryStates[$iIndex]
                    ?? ''
                ) === 'installed'
            ) {
                $sLastFile =
                    (string)(
                        $aEvent['file']
                        ?? ''
                    );

                break;
            }
        }

        $aState =
            $aLedger['state']
            ?? array();

        if (
            !is_array($aState)
            || ($aState['phase'] ?? '')
                !== 'runtime_merging'
        ) {
            $aResult['error'] =
                'Runtime merge state is incomplete during progress reconciliation.';

            return $aResult;
        }

        $aState['merge']['progress'] = array(
            'phase' =>
                $sPhase,

            'copied_files' =>
                $iCopiedFiles,

            'copied_bytes' =>
                $iCopiedBytes,

            'last_file' =>
                $sLastFile,

            'runtime_files_changed' =>
                $iCopiedFiles > 0,
        );

        $sJson =
            json_encode(
                $aState,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

        if ($sJson === false) {
            $aResult['error'] =
                'Runtime merge progress state could not be encoded.';

            return $aResult;
        }

        try {
            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' =>
                        'runtime_merging',

                    'result_json' =>
                        $sJson,

                    'error_text' =>
                        '',
                )
            );
        } catch (Throwable $e) {
            $aResult['error'] =
                'Runtime merge progress journal update failed: '
                . $e->getMessage();

            return $aResult;
        }

        /*
         * Independently verify the exact derived progress.
         */
        $aVerify =
            $this->_oDb->getRow(
                "SELECT `status`, `result_json`
                 FROM `my_una_doctor_repairs`
                 WHERE `id` = ?
                 LIMIT 1",
                array($iJournalId)
            );

        $aVerifyState =
            json_decode(
                (string)(
                    $aVerify['result_json']
                    ?? ''
                ),
                true
            );

        $aVerifyProgress =
            is_array($aVerifyState)
                ? (
                    $aVerifyState[
                        'merge'
                    ]['progress']
                    ?? array()
                )
                : array();

        if (
            empty($aVerify)
            || (string)(
                $aVerify['status']
                ?? ''
            ) !== 'runtime_merging'
            || !is_array($aVerifyState)
            || (
                $aVerifyState['phase']
                ?? ''
            ) !== 'runtime_merging'
            || (string)(
                $aVerifyProgress['phase']
                ?? ''
            ) !== $sPhase
            || (int)(
                $aVerifyProgress['copied_files']
                ?? -1
            ) !== $iCopiedFiles
            || (int)(
                $aVerifyProgress['copied_bytes']
                ?? -1
            ) !== $iCopiedBytes
            || (string)(
                $aVerifyProgress['last_file']
                ?? ''
            ) !== $sLastFile
            || (
                !empty(
                    $aVerifyProgress[
                        'runtime_files_changed'
                    ]
                )
                !== ($iCopiedFiles > 0)
            )
        ) {
            $aResult['error'] =
                'Runtime merge progress journal verification failed.';

            return $aResult;
        }

        $aResult['ok'] = true;
        $aResult['changes_applied'] = true;
        $aResult['copied_files'] =
            $iCopiedFiles;
        $aResult['copied_bytes'] =
            $iCopiedBytes;
        $aResult['last_file'] =
            $sLastFile;
        $aResult['runtime_files_changed'] =
            $iCopiedFiles > 0;

        return $aResult;
    }


    protected function writeRuntimeMergeProgressFromSession(
        array &$aSession,
        $sPhase,
        $sLastFile = ''
    ) {
        $sPhase =
            trim(
                (string)$sPhase
            );

        $sLastFile =
            (string)$sLastFile;

        $iJournalId =
            (int)(
                $aSession['journal_id']
                ?? 0
            );

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'phase' => $sPhase,
            'copied_files' => 0,
            'copied_bytes' => 0,
            'last_file' => '',
            'runtime_files_changed' => false,
            'error' => '',
        );

        if (
            empty($aSession['ok'])
            || $iJournalId <= 0
            || empty($aSession['session_id'])
        ) {
            $aResult['error'] =
                'Runtime merge batch session is invalid.';

            return $aResult;
        }

        if ($sPhase === '')
            $sPhase = 'copying';

        $iCopiedFiles =
            (int)(
                $aSession['installed_count']
                ?? 0
            );

        $iCopiedBytes =
            (int)(
                $aSession['installed_bytes']
                ?? 0
            );

        if (
            $iCopiedFiles < 0
            || $iCopiedBytes < 0
        ) {
            $aResult['error'] =
                'Runtime merge batch session progress counters are invalid.';

            return $aResult;
        }

        if ($iCopiedFiles === 0)
            $sLastFile = '';

        $aIdentity =
            $aSession['identity']
            ?? array();

        $sLedgerPath =
            (string)(
                $aSession['ledger_path']
                ?? ''
            );

        $sLedgerId =
            (string)(
                $aIdentity['ledger_id']
                ?? ''
            );

        $sExecutionId =
            (string)(
                $aIdentity[
                    'execution_manifest_id'
                ]
                ?? ''
            );

        $sManifestHash =
            (string)(
                $aIdentity[
                    'manifest_file_sha256'
                ]
                ?? ''
            );

        $iLedgerEvents =
            (int)(
                $aSession[
                    'ledger_event_count'
                ]
                ?? -1
            );

        if (
            $sLedgerPath === ''
            || $sLedgerId === ''
            || strlen($sExecutionId) !== 64
            || strlen($sManifestHash) !== 64
            || $iLedgerEvents < 0
        ) {
            $aResult['error'] =
                'Runtime merge batch session identity is incomplete.';

            return $aResult;
        }

        /*
         * Re-read the journal so progress never overwrites a
         * newer DB state with stale session JSON.
         */
        $aJournal =
            $this->_oDb->getRow(
                "SELECT `status`, `result_json`
                 FROM `my_una_doctor_repairs`
                 WHERE `id` = ?
                 LIMIT 1",
                array($iJournalId)
            );

        $aState =
            json_decode(
                (string)(
                    $aJournal['result_json']
                    ?? ''
                ),
                true
            );

        $aMerge =
            is_array($aState)
                ? (
                    $aState['merge']
                    ?? array()
                )
                : array();

        if (
            empty($aJournal)
            || (string)(
                $aJournal['status']
                ?? ''
            ) !== 'runtime_merging'
            || !is_array($aState)
            || (
                $aState['phase']
                ?? ''
            ) !== 'runtime_merging'
            || empty(
                $aState['runtime_moved']
            )
            || (string)(
                $aMerge['ledger_path']
                ?? ''
            ) !== $sLedgerPath
            || (string)(
                $aMerge['ledger_id']
                ?? ''
            ) !== $sLedgerId
            || (string)(
                $aMerge[
                    'execution_manifest_id'
                ]
                ?? ''
            ) !== $sExecutionId
            || (string)(
                $aMerge[
                    'manifest_file_sha256'
                ]
                ?? ''
            ) !== $sManifestHash
            || (int)(
                $aMerge['ledger_events']
                ?? -1
            ) !== $iLedgerEvents
        ) {
            $aResult['error'] =
                'Runtime merge journal no longer matches the batch session during progress update.';

            return $aResult;
        }

        $aState['merge']['progress'] =
            array(
                'phase' =>
                    $sPhase,

                'copied_files' =>
                    $iCopiedFiles,

                'copied_bytes' =>
                    $iCopiedBytes,

                'last_file' =>
                    $sLastFile,

                'runtime_files_changed' =>
                    $iCopiedFiles > 0,
            );

        $sJson =
            json_encode(
                $aState,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

        if ($sJson === false) {
            $aResult['error'] =
                'Runtime merge session progress state could not be encoded.';

            return $aResult;
        }

        try {
            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' =>
                        'runtime_merging',

                    'result_json' =>
                        $sJson,

                    'error_text' =>
                        '',
                )
            );
        } catch (Throwable $e) {
            $aResult['error'] =
                'Runtime merge session progress journal update failed: '
                . $e->getMessage();

            return $aResult;
        }

        /*
         * Independent DB verification only; no full ledger or
         * immutable manifest reparse is necessary here.
         */
        $aVerify =
            $this->_oDb->getRow(
                "SELECT `status`, `result_json`
                 FROM `my_una_doctor_repairs`
                 WHERE `id` = ?
                 LIMIT 1",
                array($iJournalId)
            );

        $aVerifyState =
            json_decode(
                (string)(
                    $aVerify['result_json']
                    ?? ''
                ),
                true
            );

        $aVerifyMerge =
            is_array($aVerifyState)
                ? (
                    $aVerifyState['merge']
                    ?? array()
                )
                : array();

        $aVerifyProgress =
            is_array($aVerifyMerge)
                ? (
                    $aVerifyMerge['progress']
                    ?? array()
                )
                : array();

        if (
            empty($aVerify)
            || (string)(
                $aVerify['status']
                ?? ''
            ) !== 'runtime_merging'
            || !is_array($aVerifyState)
            || (
                $aVerifyState['phase']
                ?? ''
            ) !== 'runtime_merging'
            || (int)(
                $aVerifyMerge['ledger_events']
                ?? -1
            ) !== $iLedgerEvents
            || (string)(
                $aVerifyMerge['ledger_id']
                ?? ''
            ) !== $sLedgerId
            || (string)(
                $aVerifyProgress['phase']
                ?? ''
            ) !== $sPhase
            || (int)(
                $aVerifyProgress['copied_files']
                ?? -1
            ) !== $iCopiedFiles
            || (int)(
                $aVerifyProgress['copied_bytes']
                ?? -1
            ) !== $iCopiedBytes
            || (string)(
                $aVerifyProgress['last_file']
                ?? ''
            ) !== $sLastFile
            || (
                !empty(
                    $aVerifyProgress[
                        'runtime_files_changed'
                    ]
                )
                !== ($iCopiedFiles > 0)
            )
        ) {
            $aResult['error'] =
                'Runtime merge session progress verification failed.';

            return $aResult;
        }

        $aSession['state'] =
            $aVerifyState;

        $aResult['ok'] =
            true;

        $aResult['changes_applied'] =
            true;

        $aResult['phase'] =
            $sPhase;

        $aResult['copied_files'] =
            $iCopiedFiles;

        $aResult['copied_bytes'] =
            $iCopiedBytes;

        $aResult['last_file'] =
            $sLastFile;

        $aResult['runtime_files_changed'] =
            $iCopiedFiles > 0;

        return $aResult;
    }


    protected function installRuntimeMergeManifestEntry(
        $iJournalId,
        $iManifestIndex
    ) {
        $iJournalId = (int)$iJournalId;
        $iManifestIndex = (int)$iManifestIndex;

        $aResult = array(
            'ok' => false,
            'installed' => false,
            'changes_applied' => false,
            'runtime_files_changed' => false,
            'journal_id' => $iJournalId,
            'manifest_index' => $iManifestIndex,
            'error' => '',
        );

        /*
         * Immutable manifest and durable ledger must both be
         * fully synchronized before installation begins.
         */
        $aLedger =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (
            empty($aLedger['ok'])
            || (int)(
                $aLedger['unjournaled_events']
                ?? -1
            ) !== 0
        ) {
            $aResult['error'] =
                !empty($aLedger['error'])
                    ? (string)$aLedger['error']
                    : 'Runtime merge ledger requires recovery before installation.';

            return $aResult;
        }

        $aManifest =
            $aLedger['manifest']
            ?? array();

        if (
            $iManifestIndex < 0
            || !isset($aManifest[$iManifestIndex])
            || !is_array(
                $aManifest[$iManifestIndex]
            )
        ) {
            $aResult['error'] =
                'Runtime merge manifest index is invalid.';

            return $aResult;
        }

        $sEntryState =
            (string)(
                $aLedger[
                    'entry_states'
                ][$iManifestIndex]
                ?? ''
            );

        if (
            $sEntryState !== ''
            && $sEntryState !== 'rolled_back'
        ) {
            $aResult['error'] =
                'Runtime merge entry is not in an installable ledger state.';

            return $aResult;
        }

        $aEntry =
            $aManifest[$iManifestIndex];

        $aState =
            $aLedger['state']
            ?? array();

        $aSafety =
            $aState['safety']
            ?? array();

        $aMerge =
            $aState['merge']
            ?? array();

        $sProtectedPath =
            (string)(
                $aSafety[
                    'protected_runtime_path'
                ]
                ?? ''
            );

        if (
            $sProtectedPath === ''
            || !is_dir($sProtectedPath)
            || is_link($sProtectedPath)
        ) {
            $aResult['error'] =
                'Protected runtime root is unavailable or unsafe.';

            return $aResult;
        }

        $sProtectedReal =
            realpath($sProtectedPath);

        if ($sProtectedReal === false) {
            $aResult['error'] =
                'Protected runtime root could not be resolved.';

            return $aResult;
        }

        $sRelative =
            (string)(
                $aEntry['relative_path']
                ?? ''
            );

        $sSource =
            $sProtectedReal
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $sRelative
            );

        if (
            !is_file($sSource)
            || is_link($sSource)
        ) {
            $aResult['error'] =
                'Protected runtime merge source is not a safe regular file.';

            return $aResult;
        }

        $sSourceReal =
            realpath($sSource);

        if (
            $sSourceReal === false
            || strpos(
                $sSourceReal,
                $sProtectedReal
                . DIRECTORY_SEPARATOR
            ) !== 0
        ) {
            $aResult['error'] =
                'Protected runtime merge source resolves outside the protected tree.';

            return $aResult;
        }

        /*
         * Re-prove source content and metadata immediately
         * before recording installation intent.
         */
        $aSourceBefore =
            @lstat($sSourceReal);

        if (
            !is_array($aSourceBefore)
            || (int)(
                $aSourceBefore['nlink']
                ?? 0
            ) !== 1
        ) {
            $aResult['error'] =
                'Protected runtime merge source metadata is unsafe.';

            return $aResult;
        }

        $sSourceHash =
            @hash_file(
                'sha256',
                $sSourceReal
            );

        clearstatcache(
            true,
            $sSourceReal
        );

        $aSourceAfter =
            @lstat($sSourceReal);

        if (
            $sSourceHash === false
            || !is_array($aSourceAfter)
        ) {
            $aResult['error'] =
                'Protected runtime merge source could not be verified.';

            return $aResult;
        }

        foreach (
            array(
                'dev',
                'ino',
                'size',
                'mtime',
                'ctime',
                'uid',
                'gid',
                'nlink',
                'mode',
            )
            as $sKey
        ) {
            if (
                (int)(
                    $aSourceBefore[$sKey]
                    ?? -1
                )
                !==
                (int)(
                    $aSourceAfter[$sKey]
                    ?? -1
                )
            ) {
                $aResult['error'] =
                    'Protected runtime merge source changed during verification.';

                return $aResult;
            }
        }

        $iExpectedMode =
            octdec(
                (string)(
                    $aEntry['mode']
                    ?? '0000'
                )
            );

        if (
            !hash_equals(
                strtolower(
                    (string)(
                        $aEntry['sha256']
                        ?? ''
                    )
                ),
                strtolower($sSourceHash)
            )
            || (int)$aSourceAfter['size']
                !== (int)(
                    $aEntry['size']
                    ?? -1
                )
            || (
                (int)$aSourceAfter['mode']
                & 07777
            ) !== $iExpectedMode
            || (int)$aSourceAfter['uid']
                !== (int)(
                    $aEntry['uid']
                    ?? -1
                )
            || (int)$aSourceAfter['gid']
                !== (int)(
                    $aEntry['gid']
                    ?? -1
                )
            || (int)$aSourceAfter['mtime']
                !== (int)(
                    $aEntry['mtime']
                    ?? -1
                )
            || (int)$aSourceAfter['ctime']
                !== (int)(
                    $aEntry['ctime']
                    ?? -1
                )
        ) {
            $aResult['error'] =
                'Protected runtime merge source no longer matches the immutable manifest.';

            return $aResult;
        }

        /*
         * Destination must still be proven absent.
         */
        $aDestination =
            $this->inspectRuntimeMergeDestinationEntry(
                $iJournalId,
                $iManifestIndex
            );

        if (
            empty($aDestination['ok'])
            || (
                $aDestination['state']
                ?? ''
            ) !== 'absent'
        ) {
            $aResult['error'] =
                'Runtime merge destination is no longer absent.';

            return $aResult;
        }

        $sDestination =
            (string)(
                $aDestination['destination']
                ?? ''
            );

        $sParent =
            dirname($sDestination);

        /*
         * First proof deliberately does not create directories.
         * Every required parent must already exist safely.
         */
        if (
            !is_dir($sParent)
            || is_link($sParent)
        ) {
            $aResult['error'] =
                'Runtime merge destination parent does not already exist safely.';

            return $aResult;
        }

        $sExecutionId =
            strtolower(
                (string)(
                    $aMerge[
                        'execution_manifest_id'
                    ]
                    ?? ''
                )
            );

        $sTemporary =
            $sParent
            . DIRECTORY_SEPARATOR
            . '.mud-merge-'
            . $iJournalId
            . '-'
            . $iManifestIndex
            . '-'
            . substr(
                $sExecutionId,
                0,
                12
            )
            . '.tmp';

        if (
            file_exists($sTemporary)
            || is_link($sTemporary)
        ) {
            $aResult['error'] =
                'A deterministic runtime merge temporary file already exists. Recovery is required.';

            return $aResult;
        }

        /*
         * Durable intent precedes every destination mutation.
         */
        $aIntent =
            $this->appendRuntimeMergeLedgerEvent(
                $iJournalId,
                'intent',
                $iManifestIndex
            );

        if (empty($aIntent['ok'])) {
            $aResult['error'] =
                'Runtime merge installation intent could not be journaled: '
                . (
                    $aIntent['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        $aResult['changes_applied'] = true;

        /*
         * Recheck both names after intent was durably recorded.
         */
        $aDestination =
            $this->inspectRuntimeMergeDestinationEntry(
                $iJournalId,
                $iManifestIndex
            );

        if (
            empty($aDestination['ok'])
            || (
                $aDestination['state']
                ?? ''
            ) !== 'absent'
            || file_exists($sTemporary)
            || is_link($sTemporary)
        ) {
            $aResult['error'] =
                'Runtime merge destination or temporary path changed after installation intent. Recovery is required.';

            return $aResult;
        }

        $hSource =
            @fopen(
                $sSourceReal,
                'rb'
            );

        $hTemporary =
            @fopen(
                $sTemporary,
                'xb'
            );

        if (
            $hSource === false
            || $hTemporary === false
        ) {
            if (is_resource($hSource))
                @fclose($hSource);

            if (is_resource($hTemporary))
                @fclose($hTemporary);

            if (
                is_file($sTemporary)
                && !is_link($sTemporary)
            ) {
                @unlink($sTemporary);
            }

            $aResult['error'] =
                'Runtime merge temporary copy could not be opened. Recovery is required.';

            return $aResult;
        }

        $iCopied =
            @stream_copy_to_stream(
                $hSource,
                $hTemporary
            );

        @fclose($hSource);

        $bFlushOk =
            $iCopied !== false
            && @fflush($hTemporary);

        if (
            $bFlushOk
            && function_exists('fsync')
        ) {
            $bFlushOk =
                @fsync($hTemporary);
        }

        @fclose($hTemporary);

        if (
            !$bFlushOk
            || (int)$iCopied
                !== (int)(
                    $aEntry['size']
                    ?? -1
                )
        ) {
            @unlink($sTemporary);

            $aResult['error'] =
                'Runtime merge temporary content copy failed. Recovery is required.';

            return $aResult;
        }

        $aMetadata =
            $this->applyRuntimeFilesystemMetadata(
                $sTemporary,
                array(
                    'mode' =>
                        $iExpectedMode,

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
                )
            );

        if (empty($aMetadata['ok'])) {
            @unlink($sTemporary);

            $aResult['error'] =
                'Runtime merge temporary metadata verification failed: '
                . (
                    $aMetadata['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        $aTempStat =
            @lstat($sTemporary);

        $sTempHash =
            @hash_file(
                'sha256',
                $sTemporary
            );

        if (
            !is_array($aTempStat)
            || (int)(
                $aTempStat['nlink']
                ?? 0
            ) !== 1
            || $sTempHash === false
            || !hash_equals(
                strtolower(
                    (string)(
                        $aEntry['sha256']
                        ?? ''
                    )
                ),
                strtolower($sTempHash)
            )
            || (int)$aTempStat['size']
                !== (int)(
                    $aEntry['size']
                    ?? -1
                )
            || (
                (int)$aTempStat['mode']
                & 07777
            ) !== $iExpectedMode
            || (int)$aTempStat['uid']
                !== (int)(
                    $aEntry['uid']
                    ?? -1
                )
            || (int)$aTempStat['gid']
                !== (int)(
                    $aEntry['gid']
                    ?? -1
                )
        ) {
            @unlink($sTemporary);

            $aResult['error'] =
                'Runtime merge temporary file failed immutable content or metadata verification. Recovery is required.';

            return $aResult;
        }

        /*
         * Final collision check immediately before atomic
         * create-without-overwrite.
         */
        $aDestination =
            $this->inspectRuntimeMergeDestinationEntry(
                $iJournalId,
                $iManifestIndex
            );

        if (
            empty($aDestination['ok'])
            || (
                $aDestination['state']
                ?? ''
            ) !== 'absent'
        ) {
            @unlink($sTemporary);

            $aResult['error'] =
                'Runtime merge destination appeared before atomic installation. Recovery is required.';

            return $aResult;
        }

        /*
         * link() atomically refuses an existing destination.
         * The temporary name is deterministic so a crash while
         * both hard links exist remains recoverable.
         */
        if (
            !@link(
                $sTemporary,
                $sDestination
            )
        ) {
            @unlink($sTemporary);

            $aResult['error'] =
                'Runtime merge destination could not be atomically created without overwrite. Recovery is required.';

            return $aResult;
        }

        if (!@unlink($sTemporary)) {
            $aResult['error'] =
                'Runtime merge destination was created but the temporary hard link remains. Recovery is required.';

            return $aResult;
        }

        clearstatcache(
            true,
            $sDestination
        );

        $aInstalled =
            $this->inspectRuntimeMergeDestinationEntry(
                $iJournalId,
                $iManifestIndex
            );

        if (
            empty($aInstalled['ok'])
            || (
                $aInstalled['state']
                ?? ''
            ) !== 'exact'
            || (int)(
                $aInstalled['actual_nlink']
                ?? -1
            ) !== 1
        ) {
            $aResult['error'] =
                'Installed runtime merge destination failed exact verification. Recovery is required.';

            return $aResult;
        }

        /*
         * Ownership identity is now captured durably.
         */
        $aInstalledEvent =
            $this->appendRuntimeMergeLedgerEvent(
                $iJournalId,
                'installed',
                $iManifestIndex
            );

        if (empty($aInstalledEvent['ok'])) {
            $aResult['error'] =
                'Runtime merge destination is installed but its installed event could not be journaled. Recovery is required: '
                . (
                    $aInstalledEvent['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        /*
         * Derive compact DB progress from the durable ledger.
         */
        $aProgress =
            $this->reconcileRuntimeMergeProgress(
                $iJournalId,
                'copying'
            );

        if (empty($aProgress['ok'])) {
            $aResult['error'] =
                'Runtime merge file is durably installed but progress reconciliation failed. Recovery is required: '
                . (
                    $aProgress['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        $aResult['ok'] = true;
        $aResult['installed'] = true;
        $aResult['changes_applied'] = true;
        $aResult['runtime_files_changed'] = true;
        $aResult['file'] =
            (string)(
                $aEntry['file']
                ?? ''
            );
        $aResult['destination'] =
            $sDestination;
        $aResult['sha256'] =
            (string)(
                $aEntry['sha256']
                ?? ''
            );
        $aResult['size'] =
            (int)(
                $aEntry['size']
                ?? 0
            );
        $aResult['installed_dev'] =
            (int)(
                $aInstalled['actual_dev']
                ?? -1
            );
        $aResult['installed_ino'] =
            (int)(
                $aInstalled['actual_ino']
                ?? -1
            );
        $aResult['copied_files'] =
            (int)(
                $aProgress['copied_files']
                ?? 0
            );
        $aResult['copied_bytes'] =
            (int)(
                $aProgress['copied_bytes']
                ?? 0
            );

        return $aResult;
    }


    protected function installRuntimeMergeManifestEntryFromSession(
        array &$aSession,
        $hLedger,
        $iManifestIndex
    ) {
        $iJournalId =
            (int)(
                $aSession['journal_id']
                ?? 0
            );

        $iManifestIndex =
            (int)$iManifestIndex;

        $aResult = array(
            'ok' => false,
            'installed' => false,
            'changes_applied' => false,
            'runtime_files_changed' => false,
            'journal_id' => $iJournalId,
            'manifest_index' => $iManifestIndex,
            'error' => '',
        );

        /*
         * The immutable manifest and append-only ledger were
         * fully verified by prepareRuntimeMergeBatchSession().
         * The locked appender will independently prove the
         * current ledger/DB byte boundary before each event.
         */
        if (
            empty($aSession['ok'])
            || $iJournalId <= 0
            || empty($aSession['session_id'])
            || !is_resource($hLedger)
        ) {
            $aResult['error'] =
                'Runtime merge batch session or locked ledger handle is invalid.';

            return $aResult;
        }

        $aManifest =
            $aSession['manifest']
            ?? array();

        if (
            $iManifestIndex < 0
            || !isset($aManifest[$iManifestIndex])
            || !is_array(
                $aManifest[$iManifestIndex]
            )
        ) {
            $aResult['error'] =
                'Runtime merge manifest index is invalid.';

            return $aResult;
        }

        $sEntryState =
            (string)(
                $aSession[
                    'entry_states'
                ][$iManifestIndex]
                ?? ''
            );

        if (
            $sEntryState !== ''
            && $sEntryState !== 'rolled_back'
        ) {
            $aResult['error'] =
                'Runtime merge entry is not in an installable session state.';

            return $aResult;
        }

        $aEntry =
            $aManifest[$iManifestIndex];

        $aState =
            $aSession['state']
            ?? array();

        $aSafety =
            $aState['safety']
            ?? array();

        $aMerge =
            $aState['merge']
            ?? array();

        $sProtectedPath =
            (string)(
                $aSafety[
                    'protected_runtime_path'
                ]
                ?? ''
            );

        if (
            $sProtectedPath === ''
            || !is_dir($sProtectedPath)
            || is_link($sProtectedPath)
        ) {
            $aResult['error'] =
                'Protected runtime root is unavailable or unsafe.';

            return $aResult;
        }

        $sProtectedReal =
            realpath($sProtectedPath);

        if ($sProtectedReal === false) {
            $aResult['error'] =
                'Protected runtime root could not be resolved.';

            return $aResult;
        }

        $sRelative =
            (string)(
                $aEntry['relative_path']
                ?? ''
            );

        $sSource =
            $sProtectedReal
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $sRelative
            );

        if (
            !is_file($sSource)
            || is_link($sSource)
        ) {
            $aResult['error'] =
                'Protected runtime merge source is not a safe regular file.';

            return $aResult;
        }

        $sSourceReal =
            realpath($sSource);

        if (
            $sSourceReal === false
            || strpos(
                $sSourceReal,
                $sProtectedReal
                . DIRECTORY_SEPARATOR
            ) !== 0
        ) {
            $aResult['error'] =
                'Protected runtime merge source resolves outside the protected tree.';

            return $aResult;
        }

        /*
         * Re-prove source content and metadata immediately
         * before recording installation intent.
         */
        $aSourceBefore =
            @lstat($sSourceReal);

        if (
            !is_array($aSourceBefore)
            || (int)(
                $aSourceBefore['nlink']
                ?? 0
            ) !== 1
        ) {
            $aResult['error'] =
                'Protected runtime merge source metadata is unsafe.';

            return $aResult;
        }

        $sSourceHash =
            @hash_file(
                'sha256',
                $sSourceReal
            );

        clearstatcache(
            true,
            $sSourceReal
        );

        $aSourceAfter =
            @lstat($sSourceReal);

        if (
            $sSourceHash === false
            || !is_array($aSourceAfter)
        ) {
            $aResult['error'] =
                'Protected runtime merge source could not be verified.';

            return $aResult;
        }

        foreach (
            array(
                'dev',
                'ino',
                'size',
                'mtime',
                'ctime',
                'uid',
                'gid',
                'nlink',
                'mode',
            )
            as $sKey
        ) {
            if (
                (int)(
                    $aSourceBefore[$sKey]
                    ?? -1
                )
                !==
                (int)(
                    $aSourceAfter[$sKey]
                    ?? -1
                )
            ) {
                $aResult['error'] =
                    'Protected runtime merge source changed during verification.';

                return $aResult;
            }
        }

        $iExpectedMode =
            octdec(
                (string)(
                    $aEntry['mode']
                    ?? '0000'
                )
            );

        if (
            !hash_equals(
                strtolower(
                    (string)(
                        $aEntry['sha256']
                        ?? ''
                    )
                ),
                strtolower($sSourceHash)
            )
            || (int)$aSourceAfter['size']
                !== (int)(
                    $aEntry['size']
                    ?? -1
                )
            || (
                (int)$aSourceAfter['mode']
                & 07777
            ) !== $iExpectedMode
            || (int)$aSourceAfter['uid']
                !== (int)(
                    $aEntry['uid']
                    ?? -1
                )
            || (int)$aSourceAfter['gid']
                !== (int)(
                    $aEntry['gid']
                    ?? -1
                )
            || (int)$aSourceAfter['mtime']
                !== (int)(
                    $aEntry['mtime']
                    ?? -1
                )
            || (int)$aSourceAfter['ctime']
                !== (int)(
                    $aEntry['ctime']
                    ?? -1
                )
        ) {
            $aResult['error'] =
                'Protected runtime merge source no longer matches the immutable manifest.';

            return $aResult;
        }

        /*
         * Destination must still be proven absent.
         */
        $aDestination =
            $this->inspectRuntimeMergeDestinationEntryFromSession(
                $aSession,
                $iManifestIndex
            );

        if (
            empty($aDestination['ok'])
            || (
                $aDestination['state']
                ?? ''
            ) !== 'absent'
        ) {
            $aResult['error'] =
                'Runtime merge destination is no longer absent.';

            return $aResult;
        }

        $sDestination =
            (string)(
                $aDestination['destination']
                ?? ''
            );

        $sParent =
            dirname($sDestination);

        /*
         * First proof deliberately does not create directories.
         * Every required parent must already exist safely.
         */
        if (
            !is_dir($sParent)
            || is_link($sParent)
        ) {
            $aResult['error'] =
                'Runtime merge destination parent does not already exist safely.';

            return $aResult;
        }

        $sExecutionId =
            strtolower(
                (string)(
                    $aMerge[
                        'execution_manifest_id'
                    ]
                    ?? ''
                )
            );

        $sTemporary =
            $sParent
            . DIRECTORY_SEPARATOR
            . '.mud-merge-'
            . $iJournalId
            . '-'
            . $iManifestIndex
            . '-'
            . substr(
                $sExecutionId,
                0,
                12
            )
            . '.tmp';

        if (
            file_exists($sTemporary)
            || is_link($sTemporary)
        ) {
            $aResult['error'] =
                'A deterministic runtime merge temporary file already exists. Recovery is required.';

            return $aResult;
        }

        /*
         * Durable intent precedes every destination mutation.
         */
        $aIntent =
            $this->appendRuntimeMergeLedgerEventFromSession(
                $aSession,
                $hLedger,
                'intent',
                $iManifestIndex
            );

        if (empty($aIntent['ok'])) {
            $aResult['error'] =
                'Runtime merge installation intent could not be journaled: '
                . (
                    $aIntent['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        $aResult['changes_applied'] = true;

        /*
         * Recheck both names after intent was durably recorded.
         */
        $aDestination =
            $this->inspectRuntimeMergeDestinationEntryFromSession(
                $aSession,
                $iManifestIndex
            );

        if (
            empty($aDestination['ok'])
            || (
                $aDestination['state']
                ?? ''
            ) !== 'absent'
            || file_exists($sTemporary)
            || is_link($sTemporary)
        ) {
            $aResult['error'] =
                'Runtime merge destination or temporary path changed after installation intent. Recovery is required.';

            return $aResult;
        }

        $hSource =
            @fopen(
                $sSourceReal,
                'rb'
            );

        $hTemporary =
            @fopen(
                $sTemporary,
                'xb'
            );

        if (
            $hSource === false
            || $hTemporary === false
        ) {
            if (is_resource($hSource))
                @fclose($hSource);

            if (is_resource($hTemporary))
                @fclose($hTemporary);

            if (
                is_file($sTemporary)
                && !is_link($sTemporary)
            ) {
                @unlink($sTemporary);
            }

            $aResult['error'] =
                'Runtime merge temporary copy could not be opened. Recovery is required.';

            return $aResult;
        }

        $iCopied =
            @stream_copy_to_stream(
                $hSource,
                $hTemporary
            );

        @fclose($hSource);

        $bFlushOk =
            $iCopied !== false
            && @fflush($hTemporary);

        if (
            $bFlushOk
            && function_exists('fsync')
        ) {
            $bFlushOk =
                @fsync($hTemporary);
        }

        @fclose($hTemporary);

        if (
            !$bFlushOk
            || (int)$iCopied
                !== (int)(
                    $aEntry['size']
                    ?? -1
                )
        ) {
            @unlink($sTemporary);

            $aResult['error'] =
                'Runtime merge temporary content copy failed. Recovery is required.';

            return $aResult;
        }

        $aMetadata =
            $this->applyRuntimeFilesystemMetadata(
                $sTemporary,
                array(
                    'mode' =>
                        $iExpectedMode,

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
                )
            );

        if (empty($aMetadata['ok'])) {
            @unlink($sTemporary);

            $aResult['error'] =
                'Runtime merge temporary metadata verification failed: '
                . (
                    $aMetadata['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        $aTempStat =
            @lstat($sTemporary);

        $sTempHash =
            @hash_file(
                'sha256',
                $sTemporary
            );

        if (
            !is_array($aTempStat)
            || (int)(
                $aTempStat['nlink']
                ?? 0
            ) !== 1
            || $sTempHash === false
            || !hash_equals(
                strtolower(
                    (string)(
                        $aEntry['sha256']
                        ?? ''
                    )
                ),
                strtolower($sTempHash)
            )
            || (int)$aTempStat['size']
                !== (int)(
                    $aEntry['size']
                    ?? -1
                )
            || (
                (int)$aTempStat['mode']
                & 07777
            ) !== $iExpectedMode
            || (int)$aTempStat['uid']
                !== (int)(
                    $aEntry['uid']
                    ?? -1
                )
            || (int)$aTempStat['gid']
                !== (int)(
                    $aEntry['gid']
                    ?? -1
                )
        ) {
            @unlink($sTemporary);

            $aResult['error'] =
                'Runtime merge temporary file failed immutable content or metadata verification. Recovery is required.';

            return $aResult;
        }

        /*
         * Final collision check immediately before atomic
         * create-without-overwrite.
         */
        $aDestination =
            $this->inspectRuntimeMergeDestinationEntryFromSession(
                $aSession,
                $iManifestIndex
            );

        if (
            empty($aDestination['ok'])
            || (
                $aDestination['state']
                ?? ''
            ) !== 'absent'
        ) {
            @unlink($sTemporary);

            $aResult['error'] =
                'Runtime merge destination appeared before atomic installation. Recovery is required.';

            return $aResult;
        }

        /*
         * link() atomically refuses an existing destination.
         * The temporary name is deterministic so a crash while
         * both hard links exist remains recoverable.
         */
        if (
            !@link(
                $sTemporary,
                $sDestination
            )
        ) {
            @unlink($sTemporary);

            $aResult['error'] =
                'Runtime merge destination could not be atomically created without overwrite. Recovery is required.';

            return $aResult;
        }

        if (!@unlink($sTemporary)) {
            $aResult['error'] =
                'Runtime merge destination was created but the temporary hard link remains. Recovery is required.';

            return $aResult;
        }

        clearstatcache(
            true,
            $sDestination
        );

        $aInstalled =
            $this->inspectRuntimeMergeDestinationEntryFromSession(
                $aSession,
                $iManifestIndex
            );

        if (
            empty($aInstalled['ok'])
            || (
                $aInstalled['state']
                ?? ''
            ) !== 'exact'
            || (int)(
                $aInstalled['actual_nlink']
                ?? -1
            ) !== 1
        ) {
            $aResult['error'] =
                'Installed runtime merge destination failed exact verification. Recovery is required.';

            return $aResult;
        }

        /*
         * Ownership identity is now captured durably.
         */
        $aInstalledEvent =
            $this->appendRuntimeMergeLedgerEventFromSession(
                $aSession,
                $hLedger,
                'installed',
                $iManifestIndex
            );

        if (empty($aInstalledEvent['ok'])) {
            $aResult['error'] =
                'Runtime merge destination is installed but its installed event could not be journaled. Recovery is required: '
                . (
                    $aInstalledEvent['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        /*
         * Derive compact DB progress from the durable ledger.
         */
        $aProgress =
            $this->writeRuntimeMergeProgressFromSession(
                $aSession,
                'copying',
                (string)(
                    $aEntry['file']
                    ?? ''
                )
            );

        if (empty($aProgress['ok'])) {
            $aResult['error'] =
                'Runtime merge file is durably installed but progress reconciliation failed. Recovery is required: '
                . (
                    $aProgress['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        $aResult['ok'] = true;
        $aResult['installed'] = true;
        $aResult['changes_applied'] = true;
        $aResult['runtime_files_changed'] = true;
        $aResult['file'] =
            (string)(
                $aEntry['file']
                ?? ''
            );
        $aResult['destination'] =
            $sDestination;
        $aResult['sha256'] =
            (string)(
                $aEntry['sha256']
                ?? ''
            );
        $aResult['size'] =
            (int)(
                $aEntry['size']
                ?? 0
            );
        $aResult['installed_dev'] =
            (int)(
                $aInstalled['actual_dev']
                ?? -1
            );
        $aResult['installed_ino'] =
            (int)(
                $aInstalled['actual_ino']
                ?? -1
            );
        $aResult['copied_files'] =
            (int)(
                $aProgress['copied_files']
                ?? 0
            );
        $aResult['copied_bytes'] =
            (int)(
                $aProgress['copied_bytes']
                ?? 0
            );

        return $aResult;
    }



    protected function rollbackRuntimeMergeManifestEntry(
        $iJournalId,
        $iManifestIndex,
        $bDeferProgressReconciliation = false
    ) {
        $iJournalId = (int)$iJournalId;
        $iManifestIndex = (int)$iManifestIndex;
        $bDeferProgressReconciliation =
            (bool)$bDeferProgressReconciliation;

        $aResult = array(
            'ok' => false,
            'rolled_back' => false,
            'changes_applied' => false,
            'runtime_files_changed' => false,
            'journal_id' => $iJournalId,
            'manifest_index' => $iManifestIndex,
            'error' => '',
        );

        $aLedger =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (
            empty($aLedger['ok'])
            || (int)(
                $aLedger['unjournaled_events']
                ?? -1
            ) !== 0
        ) {
            $aResult['error'] =
                !empty($aLedger['error'])
                    ? (string)$aLedger['error']
                    : 'Runtime merge ledger requires recovery before entry rollback.';

            return $aResult;
        }

        $aManifest =
            $aLedger['manifest']
            ?? array();

        if (
            $iManifestIndex < 0
            || !isset($aManifest[$iManifestIndex])
            || !is_array(
                $aManifest[$iManifestIndex]
            )
        ) {
            $aResult['error'] =
                'Runtime merge rollback manifest index is invalid.';

            return $aResult;
        }

        $aEntry =
            $aManifest[$iManifestIndex];

        $sEntryState =
            (string)(
                $aLedger[
                    'entry_states'
                ][$iManifestIndex]
                ?? ''
            );

        /*
         * Nothing has ever been installed for this entry.
         */
        if ($sEntryState === '') {
            $aDestination =
                $this->inspectRuntimeMergeDestinationEntry(
                    $iJournalId,
                    $iManifestIndex
                );

            if (
                empty($aDestination['ok'])
                || (
                    $aDestination['state']
                    ?? ''
                ) !== 'absent'
            ) {
                $aResult['error'] =
                    'Unjournaled runtime merge destination exists; rollback refused.';

                return $aResult;
            }

            $aResult['ok'] = true;
            $aResult['rolled_back'] = true;

            return $aResult;
        }

        /*
         * Already safely rolled back. Re-prove absence.
         */
        if ($sEntryState === 'rolled_back') {
            $aDestination =
                $this->inspectRuntimeMergeDestinationEntry(
                    $iJournalId,
                    $iManifestIndex
                );

            if (
                empty($aDestination['ok'])
                || (
                    $aDestination['state']
                    ?? ''
                ) !== 'absent'
            ) {
                $aResult['error'] =
                    'Previously rolled-back runtime merge destination is no longer absent.';

                return $aResult;
            }

            $aResult['ok'] = true;
            $aResult['rolled_back'] = true;

            return $aResult;
        }

        if (
            $sEntryState !== 'intent'
            && $sEntryState !== 'installed'
        ) {
            $aResult['error'] =
                'Runtime merge entry has an unsupported rollback state.';

            return $aResult;
        }

        $aState =
            $aLedger['state']
            ?? array();

        $aMerge =
            $aState['merge']
            ?? array();

        $sExecutionId =
            strtolower(
                (string)(
                    $aMerge[
                        'execution_manifest_id'
                    ]
                    ?? ''
                )
            );

        if (strlen($sExecutionId) !== 64) {
            $aResult['error'] =
                'Runtime merge execution identity is incomplete during rollback.';

            return $aResult;
        }

        $aDestination =
            $this->inspectRuntimeMergeDestinationEntry(
                $iJournalId,
                $iManifestIndex
            );

        if (empty($aDestination['ok'])) {
            $aResult['error'] =
                'Runtime merge destination could not be inspected during rollback: '
                . (
                    $aDestination['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        $sDestination =
            (string)(
                $aDestination['destination']
                ?? ''
            );

        if ($sDestination === '') {
            $aResult['error'] =
                'Runtime merge rollback destination is missing.';

            return $aResult;
        }

        $sParent =
            dirname($sDestination);

        $sTemporary =
            $sParent
            . DIRECTORY_SEPARATOR
            . '.mud-merge-'
            . $iJournalId
            . '-'
            . $iManifestIndex
            . '-'
            . substr(
                $sExecutionId,
                0,
                12
            )
            . '.tmp';

        /*
         * Read-only verifier for Doctor's deterministic temp.
         */
        $verifyTemporary =
            static function (
                $sPath,
                array $aExpected
            ) {
                $aVerify = array(
                    'ok' => false,
                    'exists' => false,
                    'error' => '',
                );

                if (
                    !file_exists($sPath)
                    && !is_link($sPath)
                ) {
                    $aVerify['ok'] = true;
                    return $aVerify;
                }

                $aVerify['exists'] = true;

                if (
                    !is_file($sPath)
                    || is_link($sPath)
                ) {
                    $aVerify['error'] =
                        'Runtime merge temporary path is not a safe regular file.';

                    return $aVerify;
                }

                $aBefore =
                    @lstat($sPath);

                if (!is_array($aBefore)) {
                    $aVerify['error'] =
                        'Runtime merge temporary metadata could not be read.';

                    return $aVerify;
                }

                $sHash =
                    @hash_file(
                        'sha256',
                        $sPath
                    );

                clearstatcache(
                    true,
                    $sPath
                );

                $aAfter =
                    @lstat($sPath);

                if (
                    $sHash === false
                    || !is_array($aAfter)
                ) {
                    $aVerify['error'] =
                        'Runtime merge temporary file could not be verified.';

                    return $aVerify;
                }

                foreach (
                    array(
                        'dev',
                        'ino',
                        'size',
                        'mtime',
                        'ctime',
                        'uid',
                        'gid',
                        'nlink',
                        'mode',
                    )
                    as $sKey
                ) {
                    if (
                        (int)(
                            $aBefore[$sKey]
                            ?? -1
                        )
                        !==
                        (int)(
                            $aAfter[$sKey]
                            ?? -1
                        )
                    ) {
                        $aVerify['error'] =
                            'Runtime merge temporary file changed during verification.';

                        return $aVerify;
                    }
                }

                $iExpectedMode =
                    octdec(
                        (string)(
                            $aExpected['mode']
                            ?? '0000'
                        )
                    );

                if (
                    !hash_equals(
                        strtolower(
                            (string)(
                                $aExpected['sha256']
                                ?? ''
                            )
                        ),
                        strtolower($sHash)
                    )
                    || (int)$aAfter['size']
                        !== (int)(
                            $aExpected['size']
                            ?? -1
                        )
                    || (
                        (int)$aAfter['mode']
                        & 07777
                    ) !== $iExpectedMode
                    || (int)$aAfter['uid']
                        !== (int)(
                            $aExpected['uid']
                            ?? -1
                        )
                    || (int)$aAfter['gid']
                        !== (int)(
                            $aExpected['gid']
                            ?? -1
                        )
                ) {
                    $aVerify['error'] =
                        'Runtime merge temporary file does not match the immutable manifest.';

                    return $aVerify;
                }

                $aVerify['ok'] = true;
                $aVerify['stat'] = $aAfter;
                $aVerify['sha256'] =
                    strtolower($sHash);

                return $aVerify;
            };

        $aTemporary =
            $verifyTemporary(
                $sTemporary,
                $aEntry
            );

        if (empty($aTemporary['ok'])) {
            $aResult['error'] =
                $aTemporary['error']
                ?? 'Runtime merge temporary verification failed.';

            return $aResult;
        }

        /*
         * INTENT can represent several crash locations.
         */
        if ($sEntryState === 'intent') {
            /*
             * No destination was created. Remove only a proven
             * Doctor temporary file, if present, then close the
             * abandoned intent.
             */
            if (
                (
                    $aDestination['state']
                    ?? ''
                ) === 'absent'
            ) {
                if (!empty($aTemporary['exists'])) {
                    if (
                        (int)(
                            $aTemporary['stat']['nlink']
                            ?? -1
                        ) !== 1
                    ) {
                        $aResult['error'] =
                            'Abandoned runtime merge temporary file has an unsafe hard-link count.';

                        return $aResult;
                    }

                    if (!@unlink($sTemporary)) {
                        $aResult['error'] =
                            'Could not remove abandoned runtime merge temporary file.';

                        return $aResult;
                    }

                    clearstatcache(
                        true,
                        $sTemporary
                    );

                    if (
                        file_exists($sTemporary)
                        || is_link($sTemporary)
                    ) {
                        $aResult['error'] =
                            'Abandoned runtime merge temporary file still exists after removal.';

                        return $aResult;
                    }

                    $aResult['changes_applied'] = true;
                }

                $aRolledBack =
                    $this->appendRuntimeMergeLedgerEvent(
                        $iJournalId,
                        'rolled_back',
                        $iManifestIndex
                    );

                if (empty($aRolledBack['ok'])) {
                    $aResult['error'] =
                        'Abandoned runtime merge intent could not be closed: '
                        . (
                            $aRolledBack['error']
                            ?? 'unknown error'
                        );

                    return $aResult;
                }

                $aProgress =
                    $this->reconcileRuntimeMergeProgress(
                        $iJournalId,
                        'rollback'
                    );

                if (empty($aProgress['ok'])) {
                    $aResult['error'] =
                        'Runtime merge intent was rolled back but progress reconciliation failed: '
                        . (
                            $aProgress['error']
                            ?? 'unknown error'
                        );

                    return $aResult;
                }

                $aResult['ok'] = true;
                $aResult['rolled_back'] = true;
                $aResult['changes_applied'] = true;
                $aResult['runtime_files_changed'] =
                    !empty(
                        $aProgress[
                            'runtime_files_changed'
                        ]
                    );

                return $aResult;
            }

            /*
             * Destination exists after intent. It must be an
             * exact manifest file; any mismatch is untouchable.
             */
            if (
                (
                    $aDestination['state']
                    ?? ''
                ) !== 'exact'
            ) {
                $aResult['error'] =
                    'Runtime merge intent has a non-exact destination; rollback refused.';

                return $aResult;
            }

            /*
             * Crash after link() but before removing the temp:
             * both names must be the same inode before Doctor
             * is allowed to remove the temporary name.
             */
            if (!empty($aTemporary['exists'])) {
                if (
                    (int)(
                        $aTemporary['stat']['dev']
                        ?? -1
                    ) !== (int)(
                        $aDestination[
                            'actual_dev'
                        ]
                        ?? -2
                    )
                    || (int)(
                        $aTemporary['stat']['ino']
                        ?? -1
                    ) !== (int)(
                        $aDestination[
                            'actual_ino'
                        ]
                        ?? -2
                    )
                    || (int)(
                        $aTemporary['stat']['nlink']
                        ?? -1
                    ) !== 2
                    || (int)(
                        $aDestination[
                            'actual_nlink'
                        ]
                        ?? -1
                    ) !== 2
                ) {
                    $aResult['error'] =
                        'Runtime merge intent destination and temporary path are not the expected two-link Doctor inode.';

                    return $aResult;
                }

                if (!@unlink($sTemporary)) {
                    $aResult['error'] =
                        'Could not remove recovered runtime merge temporary hard link.';

                    return $aResult;
                }

                clearstatcache(
                    true,
                    $sDestination
                );

                $aDestination =
                    $this->inspectRuntimeMergeDestinationEntry(
                        $iJournalId,
                        $iManifestIndex
                    );

                if (
                    empty($aDestination['ok'])
                    || (
                        $aDestination['state']
                        ?? ''
                    ) !== 'exact'
                    || (int)(
                        $aDestination[
                            'actual_nlink'
                        ]
                        ?? -1
                    ) !== 1
                ) {
                    $aResult['error'] =
                        'Recovered runtime merge destination failed verification after temporary-link removal.';

                    return $aResult;
                }

                $aResult['changes_applied'] = true;
            } elseif (
                (int)(
                    $aDestination[
                        'actual_nlink'
                    ]
                    ?? -1
                ) !== 1
            ) {
                $aResult['error'] =
                    'Runtime merge intent destination has an unsafe hard-link count.';

                return $aResult;
            }

            /*
             * Finalize durable ownership before deletion.
             */
            $aInstalled =
                $this->appendRuntimeMergeLedgerEvent(
                    $iJournalId,
                    'installed',
                    $iManifestIndex
                );

            if (empty($aInstalled['ok'])) {
                $aResult['error'] =
                    'Recovered runtime merge destination could not be journaled as installed: '
                    . (
                        $aInstalled['error']
                        ?? 'unknown error'
                    );

                return $aResult;
            }

            $aResult['changes_applied'] = true;

            /*
             * Refresh ledger so recorded dev/inode/ctime become
             * the authority for the actual removal below.
             */
            $aLedger =
                $this->readAndVerifyRuntimeMergeLedger(
                    $iJournalId
                );

            if (
                empty($aLedger['ok'])
                || (
                    $aLedger[
                        'entry_states'
                    ][$iManifestIndex]
                    ?? ''
                ) !== 'installed'
            ) {
                $aResult['error'] =
                    'Recovered runtime merge ownership could not be verified.';

                return $aResult;
            }

            $sEntryState = 'installed';
        }

        /*
         * INSTALLED rollback: locate the most recent installed
         * ownership event for this manifest entry.
         */
        $aInstalledEvent = null;

        $aEvents =
            $aLedger['events']
            ?? array();

        for (
            $i = count($aEvents) - 1;
            $i >= 0;
            $i--
        ) {
            $aEvent =
                $aEvents[$i];

            if (
                ($aEvent['event'] ?? '')
                    === 'installed'
                && (int)(
                    $aEvent[
                        'manifest_index'
                    ]
                    ?? -1
                ) === $iManifestIndex
            ) {
                $aInstalledEvent =
                    $aEvent;
                break;
            }
        }

        if (!is_array($aInstalledEvent)) {
            $aResult['error'] =
                'Runtime merge installed ownership event was not found.';

            return $aResult;
        }

        /*
         * No temp path may remain once an installed event is
         * authoritative.
         */
        if (
            file_exists($sTemporary)
            || is_link($sTemporary)
        ) {
            $aResult['error'] =
                'Runtime merge installed entry still has a temporary path; automatic deletion refused.';

            return $aResult;
        }

        $aDestination =
            $this->inspectRuntimeMergeDestinationEntry(
                $iJournalId,
                $iManifestIndex
            );

        if (
            empty($aDestination['ok'])
            || (
                $aDestination['state']
                ?? ''
            ) !== 'exact'
            || (int)(
                $aDestination[
                    'actual_nlink'
                ]
                ?? -1
            ) !== 1
        ) {
            $aResult['error'] =
                'Installed runtime merge destination is no longer exact; deletion refused.';

            return $aResult;
        }

        /*
         * Exact bytes are not sufficient ownership proof.
         * The filesystem object itself must still be the same
         * dev/inode/ctime recorded by Doctor.
         */
        if (
            (int)(
                $aDestination['actual_dev']
                ?? -1
            ) !== (int)(
                $aInstalledEvent[
                    'installed_dev'
                ]
                ?? -2
            )
            || (int)(
                $aDestination['actual_ino']
                ?? -1
            ) !== (int)(
                $aInstalledEvent[
                    'installed_ino'
                ]
                ?? -2
            )
            || (int)(
                $aDestination['actual_ctime']
                ?? -1
            ) !== (int)(
                $aInstalledEvent[
                    'installed_ctime'
                ]
                ?? -2
            )
        ) {
            $aResult['error'] =
                'Installed runtime merge destination filesystem identity changed; deletion refused.';

            return $aResult;
        }

        $sDestination =
            (string)(
                $aDestination['destination']
                ?? ''
            );

        if (
            $sDestination === ''
            || !@unlink($sDestination)
        ) {
            $aResult['error'] =
                'Verified runtime merge destination could not be removed.';

            return $aResult;
        }

        clearstatcache(
            true,
            $sDestination
        );

        $aAbsent =
            $this->inspectRuntimeMergeDestinationEntry(
                $iJournalId,
                $iManifestIndex
            );

        if (
            empty($aAbsent['ok'])
            || (
                $aAbsent['state']
                ?? ''
            ) !== 'absent'
        ) {
            $aResult['error'] =
                'Runtime merge destination removal could not be verified.';

            return $aResult;
        }

        $aResult['changes_applied'] = true;

        $aRolledBack =
            $this->appendRuntimeMergeLedgerEvent(
                $iJournalId,
                'rolled_back',
                $iManifestIndex
            );

        if (empty($aRolledBack['ok'])) {
            $aResult['error'] =
                'Runtime merge file was removed but rollback event could not be journaled. Recovery is required: '
                . (
                    $aRolledBack['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        /*
         * Bounded batch rollback may defer only the expensive
         * aggregate progress rebuild. The filesystem deletion has
         * already been identity-proved, verified absent and durably
         * recorded as rolled_back before this point.
         *
         * Single-entry recovery keeps the historical behavior and
         * reconciles immediately.
         */
        if ($bDeferProgressReconciliation) {
            $aResult['ok'] = true;
            $aResult['rolled_back'] = true;
            $aResult['changes_applied'] = true;
            $aResult['runtime_files_changed'] = true;

            return $aResult;
        }

        $aProgress =
            $this->reconcileRuntimeMergeProgress(
                $iJournalId,
                'rollback'
            );

        if (empty($aProgress['ok'])) {
            $aResult['error'] =
                'Runtime merge rollback was journaled but progress reconciliation failed: '
                . (
                    $aProgress['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        $aResult['ok'] = true;
        $aResult['rolled_back'] = true;
        $aResult['changes_applied'] = true;
        $aResult['runtime_files_changed'] =
            !empty(
                $aProgress[
                    'runtime_files_changed'
                ]
            );
        $aResult['copied_files'] =
            (int)(
                $aProgress['copied_files']
                ?? 0
            );
        $aResult['copied_bytes'] =
            (int)(
                $aProgress['copied_bytes']
                ?? 0
            );

        return $aResult;
    }


    protected function executeRuntimeMergeBatchFromSession(
        $iJournalId,
        $iStartIndex = 0,
        $iMaxFiles = 10,
        $iExpectedLedgerEvents = null
    ) {
        $iJournalId = (int)$iJournalId;
        $iStartIndex = (int)$iStartIndex;
        $iMaxFiles = (int)$iMaxFiles;

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'start_index' => $iStartIndex,
            'next_index' => $iStartIndex,
            'max_files' => $iMaxFiles,
            'installed_count' => 0,
            'installed_bytes' => 0,
            'installed_indexes' => array(),
            'skipped_installed' => array(),
            'error_index' => -1,
            'starting_session_id' => '',
            'starting_ledger_events' => 0,
            'final_ledger_events' => 0,
            'heartbeat_start_ok' => false,
            'heartbeat_final_ok' => false,
            'heartbeat_errors' => array(),
            'error' => '',
        );

        /*
         * Optimized executor remains deliberately bounded.
         * The proven performance ceiling is currently twenty-five
         * newly installed files per invocation.
         */
        if (
            $iStartIndex < 0
            || $iMaxFiles < 1
            || $iMaxFiles > 25
        ) {
            $aResult['error'] =
                'Fast runtime merge batch parameters are outside the guarded limits.';

            return $aResult;
        }

        $aSession =
            $this->prepareRuntimeMergeBatchSession(
                $iJournalId
            );

        if (empty($aSession['ok'])) {
            $aResult['error'] =
                'Fast runtime merge batch session could not be prepared: '
                . (
                    $aSession['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        /*
         * Optional optimistic-concurrency checkpoint.
         * A stale browser request may never advance a newer ledger.
         */
        if (
            $iExpectedLedgerEvents !== null
            && (int)($aSession['ledger_event_count'] ?? -1)
                !== (int)$iExpectedLedgerEvents
        ) {
            $aResult['error'] =
                'Runtime merge continuation checkpoint is stale. '
                . 'Reload the repair state before continuing.';

            return $aResult;
        }

        $aManifest =
            $aSession['manifest']
            ?? array();

        $iManifestCount =
            count($aManifest);

        $aResult['starting_session_id'] =
            (string)(
                $aSession['session_id']
                ?? ''
            );

        $aResult['starting_ledger_events'] =
            (int)(
                $aSession['ledger_event_count']
                ?? 0
            );

        if ($iStartIndex >= $iManifestCount) {
            $aResult['ok'] = true;
            $aResult['next_index'] =
                $iManifestCount;
            $aResult['complete'] =
                true;

            return $aResult;
        }

        /*
         * Public telemetry is intentionally outside the
         * ledger critical section. Failure is diagnostic
         * only and must never invalidate a durable merge.
         */
        $aHeartbeatStart =
            $this->writeRuntimeMergeMaintenanceHeartbeat(
                array(
                    'phase' => 'runtime_merging',
                )
            );

        $aResult['heartbeat_start_ok'] =
            !empty($aHeartbeatStart['ok']);

        if (empty($aHeartbeatStart['ok'])) {
            $aResult['heartbeat_errors'][] =
                (string)(
                    $aHeartbeatStart['error']
                    ?? 'Initial maintenance heartbeat failed.'
                );
        }

        $sLedgerPath =
            (string)(
                $aSession['ledger_path']
                ?? ''
            );

        $sManifestPath =
            (string)(
                $aSession['manifest_path']
                ?? ''
            );

        $aIdentity =
            $aSession['identity']
            ?? array();

        if (
            $sLedgerPath === ''
            || !is_file($sLedgerPath)
            || is_link($sLedgerPath)
            || $sManifestPath === ''
            || !is_file($sManifestPath)
            || is_link($sManifestPath)
        ) {
            $aResult['error'] =
                'Fast runtime merge batch sidecar paths are unavailable or unsafe.';

            return $aResult;
        }

        $hLedger =
            @fopen(
                $sLedgerPath,
                'c+b'
            );

        if ($hLedger === false) {
            $aResult['error'] =
                'Fast runtime merge batch ledger could not be opened.';

            return $aResult;
        }

        if (
            !@flock(
                $hLedger,
                LOCK_EX | LOCK_NB
            )
        ) {
            @fclose($hLedger);

            $aResult['error'] =
                'Fast runtime merge batch could not acquire the exclusive ledger lock.';

            return $aResult;
        }

        /*
         * Re-prove the exact cryptographic session boundary
         * after acquiring the exclusive ledger lock.
         */
        clearstatcache(
            true,
            $sLedgerPath
        );

        $aLedgerBefore =
            @lstat(
                $sLedgerPath
            );

        $aLedgerHandle =
            @fstat(
                $hLedger
            );

        if (
            !is_array($aLedgerBefore)
            || !is_array($aLedgerHandle)
        ) {
            @flock($hLedger, LOCK_UN);
            @fclose($hLedger);

            $aResult['error'] =
                'Fast runtime merge batch ledger metadata could not be verified under lock.';

            return $aResult;
        }

        $iExpectedDev =
            (int)(
                $aIdentity['ledger_dev']
                ?? -1
            );

        $iExpectedIno =
            (int)(
                $aIdentity['ledger_ino']
                ?? -1
            );

        $iExpectedBytes =
            (int)(
                $aSession['ledger_file_bytes']
                ?? -1
            );

        if (
            (int)(
                $aLedgerBefore['dev']
                ?? -1
            ) !== $iExpectedDev
            || (int)(
                $aLedgerHandle['dev']
                ?? -2
            ) !== $iExpectedDev
            || (int)(
                $aLedgerBefore['ino']
                ?? -1
            ) !== $iExpectedIno
            || (int)(
                $aLedgerHandle['ino']
                ?? -2
            ) !== $iExpectedIno
            || (int)(
                $aLedgerBefore['nlink']
                ?? 0
            ) !== 1
            || (int)(
                $aLedgerHandle['nlink']
                ?? 0
            ) !== 1
            || (
                (int)$aLedgerBefore['mode']
                & 07777
            ) !== 0600
            || (
                (int)$aLedgerHandle['mode']
                & 07777
            ) !== 0600
            || (int)(
                $aLedgerBefore['size']
                ?? -1
            ) !== $iExpectedBytes
            || (int)(
                $aLedgerHandle['size']
                ?? -1
            ) !== $iExpectedBytes
        ) {
            @flock($hLedger, LOCK_UN);
            @fclose($hLedger);

            $aResult['error'] =
                'Fast runtime merge batch locked ledger boundary does not match the prepared session.';

            return $aResult;
        }

        $sLedgerHash =
            @hash_file(
                'sha256',
                $sLedgerPath
            );

        clearstatcache(
            true,
            $sLedgerPath
        );

        $aLedgerAfter =
            @lstat(
                $sLedgerPath
            );

        if (
            $sLedgerHash === false
            || !is_array($aLedgerAfter)
            || (int)(
                $aLedgerAfter['dev']
                ?? -1
            ) !== $iExpectedDev
            || (int)(
                $aLedgerAfter['ino']
                ?? -1
            ) !== $iExpectedIno
            || (int)(
                $aLedgerAfter['size']
                ?? -1
            ) !== $iExpectedBytes
            || !hash_equals(
                strtolower(
                    (string)(
                        $aSession[
                            'ledger_file_sha256'
                        ]
                        ?? ''
                    )
                ),
                strtolower($sLedgerHash)
            )
        ) {
            @flock($hLedger, LOCK_UN);
            @fclose($hLedger);

            $aResult['error'] =
                'Fast runtime merge batch ledger changed between session preparation and locked execution.';

            return $aResult;
        }

        /*
         * Re-prove the immutable manifest sidecar once at the
         * locked batch boundary. Source content is still rehashed
         * individually by the single-entry installer.
         */
        clearstatcache(
            true,
            $sManifestPath
        );

        $aManifestBefore =
            @lstat(
                $sManifestPath
            );

        $sManifestHash =
            @hash_file(
                'sha256',
                $sManifestPath
            );

        clearstatcache(
            true,
            $sManifestPath
        );

        $aManifestAfter =
            @lstat(
                $sManifestPath
            );

        if (
            !is_array($aManifestBefore)
            || !is_array($aManifestAfter)
            || $sManifestHash === false
            || (int)(
                $aManifestBefore['dev']
                ?? -1
            ) !== (int)(
                $aManifestAfter['dev']
                ?? -2
            )
            || (int)(
                $aManifestBefore['ino']
                ?? -1
            ) !== (int)(
                $aManifestAfter['ino']
                ?? -2
            )
            || (int)(
                $aManifestBefore['size']
                ?? -1
            ) !== (int)(
                $aManifestAfter['size']
                ?? -2
            )
            || (int)(
                $aManifestBefore['mtime']
                ?? -1
            ) !== (int)(
                $aManifestAfter['mtime']
                ?? -2
            )
            || (int)(
                $aManifestBefore['ctime']
                ?? -1
            ) !== (int)(
                $aManifestAfter['ctime']
                ?? -2
            )
            || (int)(
                $aManifestAfter['nlink']
                ?? 0
            ) !== 1
            || (
                (int)$aManifestAfter['mode']
                & 07777
            ) !== 0600
            || !hash_equals(
                strtolower(
                    (string)(
                        $aIdentity[
                            'manifest_file_sha256'
                        ]
                        ?? ''
                    )
                ),
                strtolower($sManifestHash)
            )
        ) {
            @flock($hLedger, LOCK_UN);
            @fclose($hLedger);

            $aResult['error'] =
                'Fast runtime merge batch immutable manifest boundary changed before execution.';

            return $aResult;
        }

        /*
         * The compact DB identity must also still match this
         * exact session before the first install is attempted.
         */
        $aJournal =
            $this->_oDb->getRow(
                "SELECT `status`, `result_json`
                 FROM `my_una_doctor_repairs`
                 WHERE `id` = ?
                 LIMIT 1",
                array($iJournalId)
            );

        $aJournalState =
            json_decode(
                (string)(
                    $aJournal['result_json']
                    ?? ''
                ),
                true
            );

        $aJournalMerge =
            is_array($aJournalState)
                ? (
                    $aJournalState['merge']
                    ?? array()
                )
                : array();

        if (
            empty($aJournal)
            || (string)(
                $aJournal['status']
                ?? ''
            ) !== 'runtime_merging'
            || !is_array($aJournalState)
            || (
                $aJournalState['phase']
                ?? ''
            ) !== 'runtime_merging'
            || empty(
                $aJournalState[
                    'runtime_moved'
                ]
            )
            || (string)(
                $aJournalMerge['ledger_path']
                ?? ''
            ) !== $sLedgerPath
            || (string)(
                $aJournalMerge['ledger_id']
                ?? ''
            ) !== (string)(
                $aIdentity['ledger_id']
                ?? ''
            )
            || (string)(
                $aJournalMerge[
                    'execution_manifest_id'
                ]
                ?? ''
            ) !== (string)(
                $aIdentity[
                    'execution_manifest_id'
                ]
                ?? ''
            )
            || (string)(
                $aJournalMerge[
                    'manifest_file_sha256'
                ]
                ?? ''
            ) !== (string)(
                $aIdentity[
                    'manifest_file_sha256'
                ]
                ?? ''
            )
            || (int)(
                $aJournalMerge[
                    'ledger_events'
                ]
                ?? -1
            ) !== (int)(
                $aSession[
                    'ledger_event_count'
                ]
                ?? -2
            )
        ) {
            @flock($hLedger, LOCK_UN);
            @fclose($hLedger);

            $aResult['error'] =
                'Fast runtime merge batch journal boundary no longer matches the prepared session.';

            return $aResult;
        }

        $iInstalledThisBatch = 0;
        $iInstalledBytesThisBatch = 0;
        $iIndex = $iStartIndex;

        try {
            while (
                $iIndex < $iManifestCount
                && $iInstalledThisBatch
                    < $iMaxFiles
            ) {
                $sState =
                    (string)(
                        $aSession[
                            'entry_states'
                        ][$iIndex]
                        ?? ''
                    );

                if ($sState === 'installed') {
                    $aResult[
                        'skipped_installed'
                    ][] = $iIndex;

                    $iIndex++;
                    continue;
                }

                if (
                    $sState !== ''
                    && $sState
                        !== 'rolled_back'
                ) {
                    $aResult['error_index'] =
                        $iIndex;

                    $aResult['error'] =
                        'Fast runtime merge batch encountered manifest entry '
                        . $iIndex
                        . ' in recovery-required state '
                        . $sState
                        . '.';

                    break;
                }

                $aInstall =
                    $this->installRuntimeMergeManifestEntryFromSession(
                        $aSession,
                        $hLedger,
                        $iIndex
                    );

                if (empty($aInstall['ok'])) {
                    $aResult['error_index'] =
                        $iIndex;

                    $aResult['error'] =
                        'Fast runtime merge batch stopped at manifest entry '
                        . $iIndex
                        . ': '
                        . (
                            $aInstall['error']
                            ?? 'unknown installation error'
                        );

                    break;
                }

                $iInstalledThisBatch++;

                $iInstalledBytesThisBatch +=
                    (int)(
                        $aInstall['size']
                        ?? 0
                    );

                $aResult[
                    'installed_indexes'
                ][] = $iIndex;

                $aResult['changes_applied'] =
                    true;

                $iIndex++;
            }
        } catch (Throwable $e) {
            $aResult['error_index'] =
                $iIndex;

            $aResult['error'] =
                'Fast runtime merge batch raised an unexpected exception at manifest entry '
                . $iIndex
                . ': '
                . $e->getMessage();
        }

        $aResult['installed_count'] =
            $iInstalledThisBatch;

        $aResult['installed_bytes'] =
            $iInstalledBytesThisBatch;

        $aResult['next_index'] =
            $iIndex;

        $aResult['final_ledger_events'] =
            (int)(
                $aSession[
                    'ledger_event_count'
                ]
                ?? 0
            );

        /*
         * Never retain the batch lock while running the full
         * authoritative reference verifier.
         */
        @flock(
            $hLedger,
            LOCK_UN
        );

        @fclose(
            $hLedger
        );

        if ($aResult['error'] !== '') {
            return $aResult;
        }

        /*
         * One full authoritative verification per completed
         * bounded batch, rather than once per event/file.
         */
        $aFinal =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (
            empty($aFinal['ok'])
            || (int)(
                $aFinal[
                    'unjournaled_events'
                ]
                ?? -1
            ) !== 0
            || (int)(
                $aFinal['event_count']
                ?? -1
            ) !== (int)(
                $aSession[
                    'ledger_event_count'
                ]
                ?? -2
            )
            || (int)(
                $aFinal[
                    'journal_event_count'
                ]
                ?? -1
            ) !== (int)(
                $aSession[
                    'ledger_event_count'
                ]
                ?? -2
            )
        ) {
            $aResult['error'] =
                !empty($aFinal['error'])
                    ? (string)$aFinal['error']
                    : 'Fast runtime merge batch failed its final authoritative ledger verification.';

            return $aResult;
        }

        foreach (
            $aResult['installed_indexes']
            as $iInstalledIndex
        ) {
            if (
                (
                    $aFinal[
                        'entry_states'
                    ][$iInstalledIndex]
                    ?? ''
                ) !== 'installed'
            ) {
                $aResult['error_index'] =
                    $iInstalledIndex;

                $aResult['error'] =
                    'Fast runtime merge batch final reference state does not confirm an installed entry.';

                return $aResult;
            }
        }

        $aFinalState =
            $aFinal['state']
            ?? array();

        $aProgress =
            $aFinalState[
                'merge'
            ]['progress']
            ?? array();

        if (
            (int)(
                $aProgress[
                    'copied_files'
                ]
                ?? -1
            ) !== (int)(
                $aSession[
                    'installed_count'
                ]
                ?? -2
            )
            || (int)(
                $aProgress[
                    'copied_bytes'
                ]
                ?? -1
            ) !== (int)(
                $aSession[
                    'installed_bytes'
                ]
                ?? -2
            )
            || (
                !empty(
                    $aProgress[
                        'runtime_files_changed'
                    ]
                )
                !== (
                    (int)(
                        $aSession[
                            'installed_count'
                        ]
                        ?? 0
                    ) > 0
                )
            )
        ) {
            $aResult['error'] =
                'Fast runtime merge batch final compact progress does not match the authoritative ledger state.';

            return $aResult;
        }

        /*
         * Final telemetry is published only after the
         * authoritative ledger and compact progress state
         * have both been verified. It remains non-fatal.
         */
        $aHeartbeatFinal =
            $this->writeRuntimeMergeMaintenanceHeartbeat(
                array(
                    'phase' => 'runtime_merging',
                )
            );

        $aResult['heartbeat_final_ok'] =
            !empty($aHeartbeatFinal['ok']);

        if (empty($aHeartbeatFinal['ok'])) {
            $aResult['heartbeat_errors'][] =
                (string)(
                    $aHeartbeatFinal['error']
                    ?? 'Final maintenance heartbeat failed.'
                );
        }

        $aResult['ok'] =
            true;

        $aResult['manifest_count'] =
            $iManifestCount;

        $aResult['complete'] =
            $iIndex >= $iManifestCount;

        $aResult['total_installed_count'] =
            (int)(
                $aSession[
                    'installed_count'
                ]
                ?? 0
            );

        $aResult['total_installed_bytes'] =
            (int)(
                $aSession[
                    'installed_bytes'
                ]
                ?? 0
            );

        return $aResult;
    }


    protected function executeRuntimeMergeBatch(
        $iJournalId,
        $iStartIndex = 0,
        $iMaxFiles = 10
    ) {
        $iJournalId = (int)$iJournalId;
        $iStartIndex = (int)$iStartIndex;
        $iMaxFiles = (int)$iMaxFiles;

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'start_index' => $iStartIndex,
            'next_index' => $iStartIndex,
            'max_files' => $iMaxFiles,
            'installed_count' => 0,
            'installed_bytes' => 0,
            'installed_indexes' => array(),
            'skipped_installed' => array(),
            'error_index' => -1,
            'error' => '',
        );

        /*
         * Initial implementation is intentionally capped.
         * Scaling beyond ten files per invocation requires a
         * separate explicit code change after live testing.
         */
        if (
            $iStartIndex < 0
            || $iMaxFiles < 1
            || $iMaxFiles > 10
        ) {
            $aResult['error'] =
                'Runtime merge batch parameters are outside the guarded limits.';

            return $aResult;
        }

        $aLedger =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (empty($aLedger['ok'])) {
            $aResult['error'] =
                'Runtime merge ledger verification failed before batch execution: '
                . (
                    $aLedger['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        if (
            (int)(
                $aLedger['unjournaled_events']
                ?? -1
            ) !== 0
        ) {
            $aResult['error'] =
                'Runtime merge batch refused because the durable ledger is ahead of the journal.';

            return $aResult;
        }

        $aManifest =
            $aLedger['manifest']
            ?? array();

        $iManifestCount =
            count($aManifest);

        if ($iStartIndex >= $iManifestCount) {
            $aResult['ok'] = true;
            $aResult['next_index'] =
                $iManifestCount;

            return $aResult;
        }

        $iInstalled = 0;
        $iInstalledBytes = 0;
        $iIndex = $iStartIndex;

        while (
            $iIndex < $iManifestCount
            && $iInstalled < $iMaxFiles
        ) {
            /*
             * Re-read the durable ledger before every entry.
             * This is intentionally conservative for the first
             * bounded batch implementation.
             */
            $aCurrent =
                $this->readAndVerifyRuntimeMergeLedger(
                    $iJournalId
                );

            if (
                empty($aCurrent['ok'])
                || (int)(
                    $aCurrent[
                        'unjournaled_events'
                    ]
                    ?? -1
                ) !== 0
            ) {
                $aResult['error_index'] =
                    $iIndex;

                $aResult['error'] =
                    !empty($aCurrent['error'])
                        ? (string)$aCurrent['error']
                        : 'Runtime merge ledger lost synchronization during batch execution.';

                $aResult['next_index'] =
                    $iIndex;

                return $aResult;
            }

            $sState =
                (string)(
                    $aCurrent[
                        'entry_states'
                    ][$iIndex]
                    ?? ''
                );

            if ($sState === 'installed') {
                $aResult[
                    'skipped_installed'
                ][] = $iIndex;

                $iIndex++;
                continue;
            }

            if (
                $sState !== ''
                && $sState !== 'rolled_back'
            ) {
                $aResult['error_index'] =
                    $iIndex;

                $aResult['error'] =
                    'Runtime merge batch encountered entry '
                    . $iIndex
                    . ' in recovery-required state '
                    . $sState
                    . '.';

                $aResult['next_index'] =
                    $iIndex;

                return $aResult;
            }

            /*
             * The single-entry installer owns all source,
             * destination, temp, ledger, identity and progress
             * guarantees. Batch mode does not bypass them.
             */
            $aInstall =
                $this->installRuntimeMergeManifestEntry(
                    $iJournalId,
                    $iIndex
                );

            if (empty($aInstall['ok'])) {
                $aResult['error_index'] =
                    $iIndex;

                $aResult['error'] =
                    'Runtime merge batch stopped at manifest entry '
                    . $iIndex
                    . ': '
                    . (
                        $aInstall['error']
                        ?? 'unknown installation error'
                    );

                $aResult['next_index'] =
                    $iIndex;

                return $aResult;
            }

            $iInstalled++;
            $iInstalledBytes +=
                (int)(
                    $aInstall['size']
                    ?? 0
                );

            $aResult[
                'installed_indexes'
            ][] = $iIndex;

            $aResult['changes_applied'] =
                true;

            $iIndex++;
        }

        /*
         * One final synchronized ledger proof after the batch.
         */
        $aFinal =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (
            empty($aFinal['ok'])
            || (int)(
                $aFinal[
                    'unjournaled_events'
                ]
                ?? -1
            ) !== 0
        ) {
            $aResult['error_index'] =
                $iIndex;

            $aResult['error'] =
                !empty($aFinal['error'])
                    ? (string)$aFinal['error']
                    : 'Runtime merge ledger final batch verification failed.';

            $aResult['next_index'] =
                $iIndex;

            return $aResult;
        }

        foreach (
            $aResult['installed_indexes']
            as $iInstalledIndex
        ) {
            if (
                (
                    $aFinal[
                        'entry_states'
                    ][$iInstalledIndex]
                    ?? ''
                ) !== 'installed'
            ) {
                $aResult['error_index'] =
                    $iInstalledIndex;

                $aResult['error'] =
                    'Runtime merge batch final state does not show an installed entry as installed.';

                $aResult['next_index'] =
                    $iInstalledIndex;

                return $aResult;
            }
        }

        $aResult['ok'] = true;
        $aResult['installed_count'] =
            $iInstalled;
        $aResult['installed_bytes'] =
            $iInstalledBytes;
        $aResult['next_index'] =
            $iIndex;
        $aResult['manifest_count'] =
            $iManifestCount;
        $aResult['complete'] =
            $iIndex >= $iManifestCount;

        return $aResult;
    }


    protected function rollbackRuntimeMergeBatch(
        $iJournalId,
        $iStartIndex = 0,
        $iFileCount = 10
    ) {
        $iJournalId = (int)$iJournalId;
        $iStartIndex = (int)$iStartIndex;
        $iFileCount = (int)$iFileCount;

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'start_index' => $iStartIndex,
            'file_count' => $iFileCount,
            'rolled_back_count' => 0,
            'rolled_back_indexes' => array(),
            'skipped_indexes' => array(),
            'error_index' => -1,
            'error' => '',
        );

        /*
         * Initial bounded rollback is intentionally limited to
         * the same maximum range as the bounded installer.
         */
        if (
            $iStartIndex < 0
            || $iFileCount < 1
            || $iFileCount > 10
        ) {
            $aResult['error'] =
                'Runtime merge rollback batch parameters are outside the guarded limits.';

            return $aResult;
        }

        $aLedger =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (empty($aLedger['ok'])) {
            $aResult['error'] =
                'Runtime merge ledger verification failed before batch rollback: '
                . (
                    $aLedger['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        if (
            (int)(
                $aLedger['unjournaled_events']
                ?? -1
            ) !== 0
        ) {
            $aResult['error'] =
                'Runtime merge batch rollback refused because the durable ledger is ahead of the journal.';

            return $aResult;
        }

        $aManifest =
            $aLedger['manifest']
            ?? array();

        $iManifestCount =
            count($aManifest);

        if ($iStartIndex >= $iManifestCount) {
            $aResult['error'] =
                'Runtime merge rollback start index is outside the manifest.';

            return $aResult;
        }

        $iEndExclusive =
            min(
                $iStartIndex + $iFileCount,
                $iManifestCount
            );

        /*
         * Reverse order is deliberate: unwind the newest portion
         * of a bounded installation range first.
         */
        for (
            $iIndex = $iEndExclusive - 1;
            $iIndex >= $iStartIndex;
            $iIndex--
        ) {
            /*
             * The batch already established one authoritative ledger
             * snapshot before entering this loop. Use that immutable
             * starting state only to decide whether this unique entry
             * needs work.
             *
             * rollbackRuntimeMergeManifestEntry() independently
             * re-verifies the authoritative ledger immediately before
             * any mutation, so repeating a full ledger verification
             * here would duplicate the same safety gate for every file.
             */
            $sState =
                (string)(
                    $aLedger[
                        'entry_states'
                    ][$iIndex]
                    ?? ''
                );

            /*
             * Never-installed and already-rolled-back entries
             * require no mutation.
             */
            if (
                $sState === ''
                || $sState === 'rolled_back'
            ) {
                $aResult[
                    'skipped_indexes'
                ][] = $iIndex;

                continue;
            }

            /*
             * The single-entry rollback helper knows how to
             * recover either an unfinished intent or a proven
             * installed entry.
             */
            if (
                $sState !== 'intent'
                && $sState !== 'installed'
            ) {
                $aResult['error_index'] =
                    $iIndex;

                $aResult['error'] =
                    'Runtime merge batch rollback encountered entry '
                    . $iIndex
                    . ' in unsupported state '
                    . $sState
                    . '.';

                return $aResult;
            }

            $aRollback =
                $this->rollbackRuntimeMergeManifestEntry(
                    $iJournalId,
                    $iIndex,
                    true
                );

            if (empty($aRollback['ok'])) {
                $aResult['error_index'] =
                    $iIndex;

                $aResult['error'] =
                    'Runtime merge batch rollback stopped at manifest entry '
                    . $iIndex
                    . ': '
                    . (
                        $aRollback['error']
                        ?? 'unknown rollback error'
                    );

                return $aResult;
            }

            $aResult['changes_applied'] =
                true;

            $aResult[
                'rolled_back_indexes'
            ][] = $iIndex;

            $aResult['rolled_back_count']++;
        }

        /*
         * All per-file rolled_back events are now durable.
         * Rebuild aggregate runtime progress exactly once for
         * this bounded batch before the final ledger proof.
         */
        if ($aResult['rolled_back_count'] > 0) {
            $aProgress =
                $this->reconcileRuntimeMergeProgress(
                    $iJournalId,
                    'rollback'
                );

            if (empty($aProgress['ok'])) {
                $aResult['error'] =
                    'Runtime merge batch rollback was journaled but final progress reconciliation failed: '
                    . (
                        $aProgress['error']
                        ?? 'unknown reconciliation error'
                    );

                return $aResult;
            }
        }

        /*
         * Final proof: no entry in this requested range may
         * remain in intent or installed state.
         */
        $aFinal =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (
            empty($aFinal['ok'])
            || (int)(
                $aFinal[
                    'unjournaled_events'
                ]
                ?? -1
            ) !== 0
        ) {
            $aResult['error'] =
                !empty($aFinal['error'])
                    ? (string)$aFinal['error']
                    : 'Runtime merge ledger final rollback verification failed.';

            return $aResult;
        }

        for (
            $iIndex = $iStartIndex;
            $iIndex < $iEndExclusive;
            $iIndex++
        ) {
            $sState =
                (string)(
                    $aFinal[
                        'entry_states'
                    ][$iIndex]
                    ?? ''
                );

            if (
                $sState === 'intent'
                || $sState === 'installed'
            ) {
                $aResult['error_index'] =
                    $iIndex;

                $aResult['error'] =
                    'Runtime merge batch rollback left manifest entry '
                    . $iIndex
                    . ' in active state '
                    . $sState
                    . '.';

                return $aResult;
            }
        }

        $aResult['ok'] = true;
        $aResult['end_index_exclusive'] =
            $iEndExclusive;

        return $aResult;
    }


    protected function reconcileRuntimeMergeLedgerTail(
        $iJournalId
    ) {
        $iJournalId = (int)$iJournalId;

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'event_count' => 0,
            'journal_event_count' => 0,
            'reconciled_event' => '',
            'manifest_index' => -1,
            'progress_reconciled' => false,
            'error' => '',
        );

        $aLedger =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (empty($aLedger['ok'])) {
            $aResult['error'] =
                'Runtime merge ledger could not be verified for tail recovery: '
                . (
                    $aLedger['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        $iActual =
            (int)(
                $aLedger['event_count']
                ?? -1
            );

        $iJournal =
            (int)(
                $aLedger['journal_event_count']
                ?? -1
            );

        $iTail =
            (int)(
                $aLedger['unjournaled_events']
                ?? -1
            );

        $aResult['event_count'] =
            $iActual;

        $aResult['journal_event_count'] =
            $iJournal;

        /*
         * Already synchronized: safe no-op.
         */
        if ($iTail === 0) {
            $aResult['ok'] = true;
            return $aResult;
        }

        /*
         * Current writer stops immediately when an fsync'd
         * event cannot be reflected in the DB. Therefore more
         * than one durable unjournaled event is impossible under
         * the supported writer protocol and must fail closed.
         */
        if (
            $iTail !== 1
            || $iActual !== ($iJournal + 1)
        ) {
            $aResult['error'] =
                'Runtime merge ledger has an unsupported durable tail length.';

            return $aResult;
        }

        $aEvents =
            $aLedger['events']
            ?? array();

        if (
            empty($aEvents)
            || !is_array(
                $aEvents[
                    count($aEvents) - 1
                ]
            )
        ) {
            $aResult['error'] =
                'Runtime merge durable tail event could not be located.';

            return $aResult;
        }

        $aEvent =
            $aEvents[
                count($aEvents) - 1
            ];

        if (
            (int)(
                $aEvent['sequence']
                ?? -1
            ) !== $iActual
        ) {
            $aResult['error'] =
                'Runtime merge durable tail sequence is invalid.';

            return $aResult;
        }

        $sEvent =
            (string)(
                $aEvent['event']
                ?? ''
            );

        $iIndex =
            (int)(
                $aEvent['manifest_index']
                ?? -1
            );

        if (
            !in_array(
                $sEvent,
                array(
                    'intent',
                    'installed',
                    'rolled_back',
                ),
                true
            )
            || $iIndex < 0
        ) {
            $aResult['error'] =
                'Runtime merge durable tail event type or manifest index is invalid.';

            return $aResult;
        }

        $aResult['reconciled_event'] =
            $sEvent;

        $aResult['manifest_index'] =
            $iIndex;

        /*
         * Prove the live destination before allowing the DB to
         * acknowledge this durable event.
         */
        $aDestination =
            $this->inspectRuntimeMergeDestinationEntry(
                $iJournalId,
                $iIndex
            );

        if (empty($aDestination['ok'])) {
            $aResult['error'] =
                'Runtime merge durable tail destination could not be verified: '
                . (
                    $aDestination['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        $aState =
            $aLedger['state']
            ?? array();

        $sExecutionId =
            strtolower(
                (string)(
                    $aState[
                        'merge'
                    ]['execution_manifest_id']
                    ?? ''
                )
            );

        if (strlen($sExecutionId) !== 64) {
            $aResult['error'] =
                'Runtime merge execution identity is invalid during tail recovery.';

            return $aResult;
        }

        $sDestination =
            (string)(
                $aDestination['destination']
                ?? ''
            );

        if ($sDestination === '') {
            $aResult['error'] =
                'Runtime merge durable tail destination path is missing.';

            return $aResult;
        }

        $sTemporary =
            dirname($sDestination)
            . DIRECTORY_SEPARATOR
            . '.mud-merge-'
            . $iJournalId
            . '-'
            . $iIndex
            . '-'
            . substr(
                $sExecutionId,
                0,
                12
            )
            . '.tmp';

        /*
         * Under the current transaction ordering, DB ledger
         * acknowledgement occurs before installation proceeds
         * beyond intent, and installed/rolled_back events are
         * written only after temporary cleanup. An unjournaled
         * tail therefore must never coexist with a temp file.
         */
        if (
            file_exists($sTemporary)
            || is_link($sTemporary)
        ) {
            $aResult['error'] =
                'Runtime merge durable tail has an unexpected temporary path; automatic reconciliation refused.';

            return $aResult;
        }

        if ($sEvent === 'intent') {
            /*
             * Crash after intent fsync but before its DB update:
             * installRuntimeMergeManifestEntry() could not yet
             * have proceeded to temporary creation or linking.
             */
            if (
                (
                    $aDestination['state']
                    ?? ''
                ) !== 'absent'
            ) {
                $aResult['error'] =
                    'Unjournaled runtime merge intent no longer has an absent destination.';

                return $aResult;
            }
        } elseif ($sEvent === 'installed') {
            /*
             * Crash after installed-event fsync but before its
             * DB ledger counter update. The exact Doctor-owned
             * inode must still be present.
             */
            if (
                (
                    $aDestination['state']
                    ?? ''
                ) !== 'exact'
                || (int)(
                    $aDestination[
                        'actual_nlink'
                    ]
                    ?? -1
                ) !== 1
                || (int)(
                    $aDestination[
                        'actual_dev'
                    ]
                    ?? -1
                ) !== (int)(
                    $aEvent[
                        'installed_dev'
                    ]
                    ?? -2
                )
                || (int)(
                    $aDestination[
                        'actual_ino'
                    ]
                    ?? -1
                ) !== (int)(
                    $aEvent[
                        'installed_ino'
                    ]
                    ?? -2
                )
                || (int)(
                    $aDestination[
                        'actual_ctime'
                    ]
                    ?? -1
                ) !== (int)(
                    $aEvent[
                        'installed_ctime'
                    ]
                    ?? -2
                )
            ) {
                $aResult['error'] =
                    'Unjournaled installed event no longer matches the Doctor-owned destination identity.';

                return $aResult;
            }
        } elseif ($sEvent === 'rolled_back') {
            /*
             * Crash after rollback-event fsync but before its DB
             * ledger counter update: destination must remain gone.
             */
            if (
                (
                    $aDestination['state']
                    ?? ''
                ) !== 'absent'
            ) {
                $aResult['error'] =
                    'Unjournaled rollback event no longer has an absent destination.';

                return $aResult;
            }
        }

        /*
         * The append-only event is proven durable and its live
         * filesystem semantics are exactly what that event
         * promises. Advance only the compact DB ledger counter.
         */
        $aState['merge']['ledger_events'] =
            $iActual;

        $sJson =
            json_encode(
                $aState,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

        if ($sJson === false) {
            $aResult['error'] =
                'Runtime merge recovered journal state could not be encoded.';

            return $aResult;
        }

        try {
            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' =>
                        'runtime_merging',

                    'result_json' =>
                        $sJson,
                )
            );
        } catch (Throwable $e) {
            $aResult['error'] =
                'Runtime merge durable tail was proven but DB reconciliation failed: '
                . $e->getMessage();

            return $aResult;
        }

        /*
         * Independently prove the ledger and DB now agree.
         */
        $aVerify =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (
            empty($aVerify['ok'])
            || (int)(
                $aVerify['event_count']
                ?? -1
            ) !== $iActual
            || (int)(
                $aVerify['journal_event_count']
                ?? -1
            ) !== $iActual
            || (int)(
                $aVerify['unjournaled_events']
                ?? -1
            ) !== 0
            || (
                $aVerify[
                    'entry_states'
                ][$iIndex]
                ?? ''
            ) !== $sEvent
        ) {
            $aResult['error'] =
                'Runtime merge durable tail DB reconciliation verification failed.';

            return $aResult;
        }

        $aResult['changes_applied'] =
            true;

        /*
         * An installed or rolled-back event can make compact
         * copied-file progress stale if PHP died before the
         * normal progress reconciliation step.
         */
        if (
            $sEvent === 'installed'
            || $sEvent === 'rolled_back'
        ) {
            $aProgress =
                $this->reconcileRuntimeMergeProgress(
                    $iJournalId,
                    $sEvent === 'installed'
                        ? 'copying'
                        : 'rollback'
                );

            if (empty($aProgress['ok'])) {
                $aResult['error'] =
                    'Runtime merge ledger tail was reconciled but progress still requires recovery: '
                    . (
                        $aProgress['error']
                        ?? 'unknown error'
                    );

                return $aResult;
            }

            $aResult['progress_reconciled'] =
                true;

            $aResult['copied_files'] =
                (int)(
                    $aProgress[
                        'copied_files'
                    ]
                    ?? 0
                );

            $aResult['copied_bytes'] =
                (int)(
                    $aProgress[
                        'copied_bytes'
                    ]
                    ?? 0
                );
        }

        $aResult['ok'] = true;
        $aResult['event_count'] =
            $iActual;
        $aResult['journal_event_count'] =
            $iActual;

        return $aResult;
    }


    /**
     * Capture a read-only host-pressure snapshot for runtime merge work.
     *
     * This helper deliberately makes no throttling or batch-size decision.
     * It performs no database or filesystem mutation.
     */
    protected function getRuntimeMergeHostPressureSnapshot()
    {
        $iSampledAt = time();

        /*
         * Logical CPU count.
         */
        $iCpuCores = 0;

        if (is_readable('/proc/cpuinfo')) {
            $sCpuInfo = @file_get_contents(
                '/proc/cpuinfo'
            );

            if (is_string($sCpuInfo)) {
                $iMatches = preg_match_all(
                    '/^processor\s*:/m',
                    $sCpuInfo,
                    $aMatches
                );

                if ($iMatches !== false) {
                    $iCpuCores = (int)$iMatches;
                }
            }
        }

        /*
         * Never divide by zero. A missing CPU topology is reported
         * separately while one logical CPU is used as the safe divisor.
         */
        $bCpuTopologyAvailable =
            $iCpuCores > 0;

        if ($iCpuCores < 1) {
            $iCpuCores = 1;
        }

        /*
         * Unix load averages.
         */
        $aLoad =
            function_exists('sys_getloadavg')
                ? @sys_getloadavg()
                : false;

        $bLoadAvailable =
            is_array($aLoad)
            && count($aLoad) >= 3;

        $fLoad1 = $bLoadAvailable
            ? (float)$aLoad[0]
            : null;

        $fLoad5 = $bLoadAvailable
            ? (float)$aLoad[1]
            : null;

        $fLoad15 = $bLoadAvailable
            ? (float)$aLoad[2]
            : null;

        /*
         * Linux memory availability.
         */
        $iMemTotal = 0;
        $iMemAvailable = 0;

        if (is_readable('/proc/meminfo')) {
            $aMemInfo = @file(
                '/proc/meminfo',
                FILE_IGNORE_NEW_LINES
                | FILE_SKIP_EMPTY_LINES
            );

            if (is_array($aMemInfo)) {
                foreach ($aMemInfo as $sLine) {
                    if (
                        preg_match(
                            '/^MemTotal:\s+(\d+)\s+kB$/',
                            $sLine,
                            $aMatch
                        )
                    ) {
                        $iMemTotal =
                            (int)$aMatch[1] * 1024;
                    }

                    if (
                        preg_match(
                            '/^MemAvailable:\s+(\d+)\s+kB$/',
                            $sLine,
                            $aMatch
                        )
                    ) {
                        $iMemAvailable =
                            (int)$aMatch[1] * 1024;
                    }
                }
            }
        }

        $bMemoryAvailable =
            $iMemTotal > 0
            && $iMemAvailable >= 0;

        $fMemoryAvailablePercent =
            $bMemoryAvailable
                ? (
                    $iMemAvailable
                    / $iMemTotal
                ) * 100
                : null;

        $fMemoryUsedPercent =
            $fMemoryAvailablePercent !== null
                ? 100 - $fMemoryAvailablePercent
                : null;

        /*
         * Free space on the filesystem containing the UNA root.
         */
        $sDiskPath =
            defined('BX_DIRECTORY_PATH_ROOT')
                ? BX_DIRECTORY_PATH_ROOT
                : getcwd();

        $mDiskTotal =
            @disk_total_space($sDiskPath);

        $mDiskFree =
            @disk_free_space($sDiskPath);

        $bDiskAvailable =
            is_numeric($mDiskTotal)
            && is_numeric($mDiskFree)
            && (float)$mDiskTotal > 0;

        $iDiskTotal =
            $bDiskAvailable
                ? (int)$mDiskTotal
                : 0;

        $iDiskFree =
            $bDiskAvailable
                ? (int)$mDiskFree
                : 0;

        $fDiskFreePercent =
            $bDiskAvailable
                ? (
                    $iDiskFree
                    / $iDiskTotal
                ) * 100
                : null;

        /*
         * Linux Pressure Stall Information.
         *
         * Each resource can expose "some" and "full" pressure
         * averages over 10, 60 and 300 seconds.
         */
        $fReadPsi =
            static function ($sResource) {
                $sPath =
                    '/proc/pressure/'
                    . $sResource;

                if (!is_readable($sPath)) {
                    return array(
                        'available' => false,
                        'some' => array(),
                        'full' => array(),
                    );
                }

                $aLines = @file(
                    $sPath,
                    FILE_IGNORE_NEW_LINES
                    | FILE_SKIP_EMPTY_LINES
                );

                if (!is_array($aLines)) {
                    return array(
                        'available' => false,
                        'some' => array(),
                        'full' => array(),
                    );
                }

                $aResult = array(
                    'available' => true,
                    'some' => array(),
                    'full' => array(),
                );

                foreach ($aLines as $sLine) {
                    if (
                        !preg_match(
                            '/^(some|full)\s+(.*)$/',
                            $sLine,
                            $aHead
                        )
                    ) {
                        continue;
                    }

                    $sKind = $aHead[1];

                    preg_match_all(
                        '/(avg10|avg60|avg300|total)=([0-9.]+)/',
                        $aHead[2],
                        $aParts,
                        PREG_SET_ORDER
                    );

                    foreach ($aParts as $aPart) {
                        $aResult[
                            $sKind
                        ][
                            $aPart[1]
                        ] =
                            $aPart[1] === 'total'
                                ? (int)$aPart[2]
                                : (float)$aPart[2];
                    }
                }

                return $aResult;
            };

        return array(
            'sampled_at' =>
                $iSampledAt,

            'cpu' => array(
                'topology_available' =>
                    $bCpuTopologyAvailable,

                'cores' =>
                    $iCpuCores,

                'load_available' =>
                    $bLoadAvailable,

                'load_1m' =>
                    $fLoad1,

                'load_5m' =>
                    $fLoad5,

                'load_15m' =>
                    $fLoad15,

                'normalized_1m' =>
                    $fLoad1 !== null
                        ? $fLoad1 / $iCpuCores
                        : null,

                'normalized_5m' =>
                    $fLoad5 !== null
                        ? $fLoad5 / $iCpuCores
                        : null,

                'normalized_15m' =>
                    $fLoad15 !== null
                        ? $fLoad15 / $iCpuCores
                        : null,
            ),

            'memory' => array(
                'available' =>
                    $bMemoryAvailable,

                'total_bytes' =>
                    $iMemTotal,

                'available_bytes' =>
                    $iMemAvailable,

                'available_percent' =>
                    $fMemoryAvailablePercent,

                'used_percent' =>
                    $fMemoryUsedPercent,
            ),

            'disk' => array(
                'available' =>
                    $bDiskAvailable,

                'total_bytes' =>
                    $iDiskTotal,

                'free_bytes' =>
                    $iDiskFree,

                'free_percent' =>
                    $fDiskFreePercent,
            ),

            'psi' => array(
                'cpu' =>
                    $fReadPsi('cpu'),

                'io' =>
                    $fReadPsi('io'),

                'memory' =>
                    $fReadPsi('memory'),
            ),
        );
    }


    /**
     * Publish a sanitized maintenance heartbeat as a static JSON file.
     *
     * The payload intentionally contains no filesystem paths, hostnames,
     * account names, database identifiers or runtime manifest details.
     *
     * This helper does not alter runtime-merge state or make governor
     * decisions. Failure to publish telemetry must remain separate from
     * the durable repair transaction.
     */
    protected function writeRuntimeMergeMaintenanceHeartbeat(
        array $aExtra = array()
    ) {
        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'path' => '',
            'url' =>
                '/mud-doctor-maintenance-health.json',
            'bytes' => 0,
            'error' => '',
        );

        $aPressure =
            $this->getRuntimeMergeHostPressureSnapshot();

        $fNormalizedLoad =
            isset(
                $aPressure['cpu']['normalized_1m']
            )
            && $aPressure[
                'cpu'
            ][
                'normalized_1m'
            ] !== null
                ? (float)$aPressure[
                    'cpu'
                ][
                    'normalized_1m'
                ]
                : null;

        $fMemoryUsed =
            isset(
                $aPressure['memory']['used_percent']
            )
            && $aPressure[
                'memory'
            ][
                'used_percent'
            ] !== null
                ? (float)$aPressure[
                    'memory'
                ][
                    'used_percent'
                ]
                : null;

        $fMemoryAvailable =
            isset(
                $aPressure[
                    'memory'
                ][
                    'available_percent'
                ]
            )
            && $aPressure[
                'memory'
            ][
                'available_percent'
            ] !== null
                ? (float)$aPressure[
                    'memory'
                ][
                    'available_percent'
                ]
                : null;

        $fDiskFree =
            isset(
                $aPressure['disk']['free_percent']
            )
            && $aPressure[
                'disk'
            ][
                'free_percent'
            ] !== null
                ? (float)$aPressure[
                    'disk'
                ][
                    'free_percent'
                ]
                : null;

        $fPsiCpu =
            isset(
                $aPressure[
                    'psi'
                ][
                    'cpu'
                ][
                    'some'
                ][
                    'avg10'
                ]
            )
                ? (float)$aPressure[
                    'psi'
                ][
                    'cpu'
                ][
                    'some'
                ][
                    'avg10'
                ]
                : null;

        $fPsiIoSome =
            isset(
                $aPressure[
                    'psi'
                ][
                    'io'
                ][
                    'some'
                ][
                    'avg10'
                ]
            )
                ? (float)$aPressure[
                    'psi'
                ][
                    'io'
                ][
                    'some'
                ][
                    'avg10'
                ]
                : null;

        $fPsiIoFull =
            isset(
                $aPressure[
                    'psi'
                ][
                    'io'
                ][
                    'full'
                ][
                    'avg10'
                ]
            )
                ? (float)$aPressure[
                    'psi'
                ][
                    'io'
                ][
                    'full'
                ][
                    'avg10'
                ]
                : null;

        $fPsiMemory =
            isset(
                $aPressure[
                    'psi'
                ][
                    'memory'
                ][
                    'some'
                ][
                    'avg10'
                ]
            )
                ? (float)$aPressure[
                    'psi'
                ][
                    'memory'
                ][
                    'some'
                ][
                    'avg10'
                ]
                : null;

        $aPayload = array(
            'schema' =>
                'my_una_doctor_maintenance_health_v1',

            'updated_at' =>
                (int)(
                    $aPressure['sampled_at']
                    ?? time()
                ),

            'cpu_cores' =>
                (int)(
                    $aPressure['cpu']['cores']
                    ?? 1
                ),

            'load_1m' =>
                isset(
                    $aPressure['cpu']['load_1m']
                )
                && $aPressure[
                    'cpu'
                ][
                    'load_1m'
                ] !== null
                    ? round(
                        (float)$aPressure[
                            'cpu'
                        ][
                            'load_1m'
                        ],
                        2
                    )
                    : null,

            'normalized_load_percent' =>
                $fNormalizedLoad !== null
                    ? round(
                        $fNormalizedLoad * 100,
                        1
                    )
                    : null,

            'memory_used_percent' =>
                $fMemoryUsed !== null
                    ? round(
                        $fMemoryUsed,
                        1
                    )
                    : null,

            'memory_available_percent' =>
                $fMemoryAvailable !== null
                    ? round(
                        $fMemoryAvailable,
                        1
                    )
                    : null,

            'disk_free_percent' =>
                $fDiskFree !== null
                    ? round(
                        $fDiskFree,
                        1
                    )
                    : null,

            'pressure' => array(
                'cpu_some_avg10' =>
                    $fPsiCpu !== null
                        ? round(
                            $fPsiCpu,
                            2
                        )
                        : null,

                'io_some_avg10' =>
                    $fPsiIoSome !== null
                        ? round(
                            $fPsiIoSome,
                            2
                        )
                        : null,

                'io_full_avg10' =>
                    $fPsiIoFull !== null
                        ? round(
                            $fPsiIoFull,
                            2
                        )
                        : null,

                'memory_some_avg10' =>
                    $fPsiMemory !== null
                        ? round(
                            $fPsiMemory,
                            2
                        )
                        : null,
            ),

            /*
             * Measurement-only phase. Adaptive governor
             * classification comes later after live testing.
             */
            'doctor_mode' =>
                'monitoring',
        );

        /*
         * Optional public-safe context.
         */
        if (
            isset($aExtra['phase'])
            && is_string($aExtra['phase'])
        ) {
            $sPhase =
                preg_replace(
                    '/[^a-z0-9_-]/i',
                    '',
                    $aExtra['phase']
                );

            if ($sPhase !== '') {
                $aPayload['phase'] =
                    substr(
                        $sPhase,
                        0,
                        48
                    );
            }
        }

        $sJson =
            json_encode(
                $aPayload,
                JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

        if (!is_string($sJson) || $sJson === '') {
            $aResult['error'] =
                'Maintenance heartbeat JSON could not be encoded.';

            return $aResult;
        }

        $sRoot =
            defined('BX_DIRECTORY_PATH_ROOT')
                ? BX_DIRECTORY_PATH_ROOT
                : getcwd() . DIRECTORY_SEPARATOR;

        $sRoot =
            rtrim(
                $sRoot,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR;

        $sPath =
            $sRoot
            . 'mud-doctor-maintenance-health.json';

        $aResult['path'] =
            $sPath;

        /*
         * tempnam() keeps the temporary file on the same
         * filesystem so final rename is atomic.
         */
        $sTemp =
            @tempnam(
                $sRoot,
                'mud-health-'
            );

        if (
            $sTemp === false
            || $sTemp === ''
        ) {
            $aResult['error'] =
                'Maintenance heartbeat temporary file could not be created.';

            return $aResult;
        }

        $hFile =
            @fopen(
                $sTemp,
                'wb'
            );

        if ($hFile === false) {
            @unlink($sTemp);

            $aResult['error'] =
                'Maintenance heartbeat temporary file could not be opened.';

            return $aResult;
        }

        $iLength =
            strlen($sJson);

        $iWritten =
            @fwrite(
                $hFile,
                $sJson
            );

        if (
            $iWritten === false
            || $iWritten !== $iLength
            || !@fflush($hFile)
        ) {
            @fclose($hFile);
            @unlink($sTemp);

            $aResult['error'] =
                'Maintenance heartbeat temporary file could not be written completely.';

            return $aResult;
        }

        if (function_exists('fsync')) {
            @fsync($hFile);
        }

        @fclose($hFile);

        if (!@chmod($sTemp, 0644)) {
            @unlink($sTemp);

            $aResult['error'] =
                'Maintenance heartbeat permissions could not be applied.';

            return $aResult;
        }

        if (!@rename($sTemp, $sPath)) {
            @unlink($sTemp);

            $aResult['error'] =
                'Maintenance heartbeat could not be published atomically.';

            return $aResult;
        }

        clearstatcache(
            true,
            $sPath
        );

        $sVerify =
            @file_get_contents(
                $sPath
            );

        if (
            $sVerify === false
            || $sVerify !== $sJson
        ) {
            $aResult['error'] =
                'Published maintenance heartbeat could not be verified.';

            return $aResult;
        }

        $aResult['ok'] =
            true;

        $aResult['changes_applied'] =
            true;

        $aResult['bytes'] =
            strlen($sVerify);

        $aResult['payload'] =
            $aPayload;

        return $aResult;
    }


    protected function prepareRuntimeMergeBatchSession(
        $iJournalId
    ) {
        $iJournalId = (int)$iJournalId;

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'session_id' => '',
            'manifest_count' => 0,
            'manifest_bytes' => 0,
            'ledger_event_count' => 0,
            'untouched_count' => 0,
            'installed_count' => 0,
            'rolled_back_count' => 0,
            'installed_bytes' => 0,
            'error' => '',
        );

        /*
         * One authoritative full verification establishes the
         * immutable manifest and append-only ledger boundary for
         * a future optimized batch execution session.
         */
        $aLedger =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (empty($aLedger['ok'])) {
            $aResult['error'] =
                'Runtime merge ledger could not establish a batch session: '
                . (
                    $aLedger['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        if (
            (int)(
                $aLedger['unjournaled_events']
                ?? -1
            ) !== 0
        ) {
            $aResult['error'] =
                'Runtime merge batch session refused because the durable ledger is ahead of the journal.';

            return $aResult;
        }

        if (
            (int)(
                $aLedger['event_count']
                ?? -1
            ) !==
            (int)(
                $aLedger['journal_event_count']
                ?? -2
            )
        ) {
            $aResult['error'] =
                'Runtime merge batch session ledger counters are not synchronized.';

            return $aResult;
        }

        $aState =
            $aLedger['state']
            ?? array();

        $aMerge =
            $aState['merge']
            ?? array();

        if (
            !is_array($aState)
            || ($aState['phase'] ?? '')
                !== 'runtime_merging'
            || empty($aState['runtime_moved'])
        ) {
            $aResult['error'] =
                'Runtime merge journal is not in the protected merging phase.';

            return $aResult;
        }

        $aManifest =
            $aLedger['manifest']
            ?? array();

        if (empty($aManifest)) {
            $aResult['error'] =
                'Runtime merge batch session has no immutable manifest entries.';

            return $aResult;
        }

        $aEntryStates =
            $aLedger['entry_states']
            ?? array();

        /*
         * Any unfinished intent requires narrow recovery before
         * normal batch execution may begin.
         */
        foreach (
            $aEntryStates
            as $iIndex => $sState
        ) {
            if ($sState === 'intent') {
                $aResult['error'] =
                    'Runtime merge manifest entry '
                    . (int)$iIndex
                    . ' has an unfinished intent and requires recovery before batch execution.';

                return $aResult;
            }
        }

        $sLedgerPath =
            (string)(
                $aMerge['ledger_path']
                ?? ''
            );

        $sManifestPath =
            (string)(
                $aMerge['manifest_path']
                ?? ''
            );

        if (
            $sLedgerPath === ''
            || !is_file($sLedgerPath)
            || is_link($sLedgerPath)
            || $sManifestPath === ''
            || !is_file($sManifestPath)
            || is_link($sManifestPath)
        ) {
            $aResult['error'] =
                'Runtime merge batch session sidecar paths are unavailable or unsafe.';

            return $aResult;
        }

        clearstatcache(true, $sLedgerPath);
        clearstatcache(true, $sManifestPath);

        $aLedgerBefore =
            @lstat($sLedgerPath);

        $aManifestStat =
            @lstat($sManifestPath);

        if (
            !is_array($aLedgerBefore)
            || !is_array($aManifestStat)
            || (int)(
                $aLedgerBefore['nlink']
                ?? 0
            ) !== 1
            || (
                (int)$aLedgerBefore['mode']
                & 07777
            ) !== 0600
            || (int)(
                $aManifestStat['nlink']
                ?? 0
            ) !== 1
            || (
                (int)$aManifestStat['mode']
                & 07777
            ) !== 0600
        ) {
            $aResult['error'] =
                'Runtime merge batch session sidecar metadata is unsafe.';

            return $aResult;
        }

        /*
         * Hash the ledger once at session preparation. This is
         * the immutable starting boundary for optimized appends.
         */
        $sLedgerHash =
            @hash_file(
                'sha256',
                $sLedgerPath
            );

        clearstatcache(
            true,
            $sLedgerPath
        );

        $aLedgerAfter =
            @lstat($sLedgerPath);

        if (
            $sLedgerHash === false
            || !is_array($aLedgerAfter)
        ) {
            $aResult['error'] =
                'Runtime merge ledger boundary could not be hashed.';

            return $aResult;
        }

        foreach (
            array(
                'dev',
                'ino',
                'size',
                'mtime',
                'ctime',
                'uid',
                'gid',
                'nlink',
                'mode',
            )
            as $sKey
        ) {
            if (
                (int)(
                    $aLedgerBefore[$sKey]
                    ?? -1
                )
                !==
                (int)(
                    $aLedgerAfter[$sKey]
                    ?? -2
                )
            ) {
                $aResult['error'] =
                    'Runtime merge ledger changed while its batch boundary was being established.';

                return $aResult;
            }
        }

        $iUntouched = 0;
        $iInstalled = 0;
        $iRolledBack = 0;
        $iInstalledBytes = 0;

        foreach (
            $aManifest
            as $iIndex => $aEntry
        ) {
            $sState =
                (string)(
                    $aEntryStates[$iIndex]
                    ?? ''
                );

            if ($sState === '') {
                $iUntouched++;
                continue;
            }

            if ($sState === 'rolled_back') {
                $iRolledBack++;
                continue;
            }

            if ($sState === 'installed') {
                $iInstalled++;

                $iInstalledBytes +=
                    (int)(
                        $aEntry['size']
                        ?? 0
                    );

                continue;
            }

            $aResult['error'] =
                'Runtime merge batch session encountered unsupported entry state '
                . $sState
                . '.';

            return $aResult;
        }

        /*
         * Derive active installed-file ownership once from the
         * already fully verified append-only ledger.
         */
        $aLatestInstalledEvents =
            array();

        foreach (
            ($aLedger['events'] ?? array())
            as $aEvent
        ) {
            if (!is_array($aEvent)) {
                $aResult['error'] =
                    'Runtime merge batch session encountered a malformed verified ledger event.';

                return $aResult;
            }

            $sEvent =
                (string)(
                    $aEvent['event']
                    ?? ''
                );

            $iEventIndex =
                (int)(
                    $aEvent['manifest_index']
                    ?? -1
                );

            if (
                $iEventIndex < 0
                || !isset($aManifest[$iEventIndex])
            ) {
                $aResult['error'] =
                    'Runtime merge batch session encountered a ledger event with an invalid manifest index.';

                return $aResult;
            }

            if (
                $sEvent === 'intent'
                || $sEvent === 'rolled_back'
            ) {
                unset(
                    $aLatestInstalledEvents[
                        $iEventIndex
                    ]
                );

                continue;
            }

            if ($sEvent !== 'installed')
                continue;

            if (
                (int)(
                    $aEvent['sequence']
                    ?? 0
                ) <= 0
                || (int)(
                    $aEvent['installed_dev']
                    ?? -1
                ) < 0
                || (int)(
                    $aEvent['installed_ino']
                    ?? -1
                ) <= 0
                || (int)(
                    $aEvent['installed_ctime']
                    ?? -1
                ) <= 0
            ) {
                $aResult['error'] =
                    'Runtime merge batch session encountered an installed event with incomplete ownership identity.';

                return $aResult;
            }

            $aLatestInstalledEvents[
                $iEventIndex
            ] =
                $aEvent;
        }

        /*
         * Derived ownership must agree exactly with the final
         * verified ledger state.
         */
        foreach (
            $aEntryStates
            as $iIndex => $sState
        ) {
            $bHasOwnership =
                isset(
                    $aLatestInstalledEvents[
                        $iIndex
                    ]
                );

            if (
                $sState === 'installed'
                && !$bHasOwnership
            ) {
                $aResult['error'] =
                    'Runtime merge batch session could not derive installed ownership for manifest entry '
                    . (int)$iIndex
                    . '.';

                return $aResult;
            }

            if (
                $sState !== 'installed'
                && $bHasOwnership
            ) {
                $aResult['error'] =
                    'Runtime merge batch session derived stale installed ownership for manifest entry '
                    . (int)$iIndex
                    . '.';

                return $aResult;
            }
        }

        if (
            count($aLatestInstalledEvents)
            !== $iInstalled
        ) {
            $aResult['error'] =
                'Runtime merge batch session ownership count does not match installed entry count.';

            return $aResult;
        }

        $aIdentity = array(
            'schema' =>
                'my_una_doctor_runtime_merge_batch_session_v1',

            'journal_id' =>
                $iJournalId,

            'merge_plan_id' =>
                (string)(
                    $aMerge['merge_plan_id']
                    ?? ''
                ),

            'execution_manifest_id' =>
                (string)(
                    $aMerge[
                        'execution_manifest_id'
                    ]
                    ?? ''
                ),

            'manifest_file_sha256' =>
                (string)(
                    $aMerge[
                        'manifest_file_sha256'
                    ]
                    ?? ''
                ),

            'ledger_id' =>
                (string)(
                    $aMerge['ledger_id']
                    ?? ''
                ),

            'ledger_event_count' =>
                (int)(
                    $aLedger['event_count']
                    ?? 0
                ),

            'ledger_file_sha256' =>
                strtolower($sLedgerHash),

            'ledger_file_bytes' =>
                (int)(
                    $aLedgerAfter['size']
                    ?? 0
                ),

            'ledger_dev' =>
                (int)(
                    $aLedgerAfter['dev']
                    ?? -1
                ),

            'ledger_ino' =>
                (int)(
                    $aLedgerAfter['ino']
                    ?? -1
                ),

            'manifest_count' =>
                count($aManifest),

            'manifest_bytes' =>
                (int)(
                    $aMerge['manifest_bytes']
                    ?? 0
                ),
        );

        $sIdentityJson =
            json_encode(
                $aIdentity,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

        if ($sIdentityJson === false) {
            $aResult['error'] =
                'Runtime merge batch session identity could not be encoded.';

            return $aResult;
        }

        $aResult['ok'] = true;
        $aResult['session_id'] =
            hash(
                'sha256',
                $sIdentityJson
            );
        $aResult['identity'] =
            $aIdentity;
        $aResult['manifest'] =
            $aManifest;
        $aResult['entry_states'] =
            $aEntryStates;
        $aResult['latest_installed_events'] =
            $aLatestInstalledEvents;
        $aResult['state'] =
            $aState;
        $aResult['ledger_path'] =
            $sLedgerPath;
        $aResult['manifest_path'] =
            $sManifestPath;
        $aResult['manifest_count'] =
            count($aManifest);
        $aResult['manifest_bytes'] =
            (int)(
                $aMerge['manifest_bytes']
                ?? 0
            );
        $aResult['ledger_event_count'] =
            (int)(
                $aLedger['event_count']
                ?? 0
            );
        $aResult['ledger_file_bytes'] =
            (int)(
                $aLedgerAfter['size']
                ?? 0
            );
        $aResult['ledger_file_sha256'] =
            strtolower($sLedgerHash);
        $aResult['untouched_count'] =
            $iUntouched;
        $aResult['installed_count'] =
            $iInstalled;
        $aResult['rolled_back_count'] =
            $iRolledBack;
        $aResult['installed_bytes'] =
            $iInstalledBytes;

        return $aResult;
    }


    /**
     * Execute one guarded, bounded runtime-merge batch.
     *
     * Public mutation gateway for Studio. The transactional installer
     * remains protected and inaccessible directly from the UI.
     */
    /**
     * Roll back one proven Doctor-installed runtime-merge entry.
     *
     * Public mutation gateway for Studio. The filesystem rollback
     * implementation remains protected and inaccessible directly
     * from the UI.
     */
    /**
     * Read one runtime-merge manifest entry state.
     *
     * Read-only Studio helper. No ledger reconciliation, filesystem
     * mutation or journal mutation is permitted here.
     */
    public function getRuntimeMergeEntryState(
        $iJournalId,
        $iManifestIndex = 0
    ) {
        $iJournalId = (int)$iJournalId;
        $iManifestIndex = (int)$iManifestIndex;

        $aResult = array(
            'ok' => false,
            'journal_id' => $iJournalId,
            'manifest_index' => $iManifestIndex,
            'journal_status' => '',
            'phase' => '',
            'runtime_moved' => false,
            'entry_state' => '',
            'event_count' => 0,
            'journal_event_count' => 0,
            'unjournaled_events' => 0,
            'copied_files' => 0,
            'copied_bytes' => 0,
            'runtime_files_changed' => false,
            'error' => '',
        );

        if ($iJournalId <= 0 || $iManifestIndex < 0) {
            $aResult['error'] =
                'Runtime merge entry-state parameters are invalid.';

            return $aResult;
        }

        $aJournal =
            $this->_oDb->getRow(
                "SELECT `status`, `result_json`
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

        $aState =
            json_decode(
                (string)(
                    $aJournal['result_json']
                    ?? ''
                ),
                true
            );

        if (!is_array($aState)) {
            $aResult['error'] =
                'Repair journal state could not be decoded.';

            return $aResult;
        }

        $aResult['journal_status'] =
            (string)(
                $aJournal['status']
                ?? ''
            );

        $aResult['phase'] =
            (string)(
                $aState['phase']
                ?? ''
            );

        $aResult['runtime_moved'] =
            !empty(
                $aState['runtime_moved']
            );

        $aLedger =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (empty($aLedger['ok'])) {
            $aResult['error'] =
                'Runtime merge ledger verification failed: '
                . (
                    $aLedger['error']
                    ?? 'unknown ledger error'
                );

            return $aResult;
        }

        $aManifest =
            $aLedger['manifest']
            ?? array();

        if (
            !isset($aManifest[$iManifestIndex])
            || !is_array(
                $aManifest[$iManifestIndex]
            )
        ) {
            $aResult['error'] =
                'Runtime merge manifest entry was not found.';

            return $aResult;
        }

        $aResult['entry_state'] =
            (string)(
                $aLedger[
                    'entry_states'
                ][$iManifestIndex]
                ?? ''
            );

        $aResult['event_count'] =
            (int)(
                $aLedger['event_count']
                ?? 0
            );

        $aResult['journal_event_count'] =
            (int)(
                $aLedger['journal_event_count']
                ?? 0
            );

        $aResult['unjournaled_events'] =
            (int)(
                $aLedger['unjournaled_events']
                ?? 0
            );

        $aMerge =
            $aState['merge']
            ?? array();

        $aProgress =
            $aMerge['progress']
            ?? array();

        $aResult['copied_files'] =
            (int)(
                $aProgress['copied_files']
                ?? 0
            );

        $aResult['copied_bytes'] =
            (int)(
                $aProgress['copied_bytes']
                ?? 0
            );

        $aResult['runtime_files_changed'] =
            !empty(
                $aProgress['runtime_files_changed']
            );

        $aResult['ok'] = true;

        return $aResult;
    }


    /**
     * Read and prove one bounded runtime-merge manifest range.
     *
     * Read-only Studio helper. No ledger reconciliation, journal
     * mutation or filesystem mutation is permitted here.
     */
    public function getRuntimeMergeRangeState(
        $iJournalId,
        $iStartIndex = 0,
        $iFileCount = 5
    ) {
        $iJournalId = (int)$iJournalId;
        $iStartIndex = (int)$iStartIndex;
        $iFileCount = (int)$iFileCount;

        $aResult = array(
            'ok' => false,
            'journal_id' => $iJournalId,
            'start_index' => $iStartIndex,
            'file_count' => $iFileCount,
            'end_index_exclusive' => $iStartIndex,
            'journal_status' => '',
            'phase' => '',
            'runtime_moved' => false,
            'event_count' => 0,
            'journal_event_count' => 0,
            'unjournaled_events' => 0,
            'never_installed_count' => 0,
            'rolled_back_count' => 0,
            'installed_count' => 0,
            'intent_count' => 0,
            'passive_count' => 0,
            'installed_bytes' => 0,
            'all_passive' => false,
            'all_installed' => false,
            'install_ready' => false,
            'rollback_ready' => false,
            'recovery_required' => false,
            'entries' => array(),
            'error' => '',
        );

        /*
         * Read-only range may inspect at most the currently proven
         * optimized installer ceiling.
         */
        if (
            $iJournalId <= 0
            || $iStartIndex < 0
            || $iFileCount < 1
            || $iFileCount > 25
        ) {
            $aResult['error'] =
                'Runtime merge range-state parameters are outside the guarded limits.';

            return $aResult;
        }

        $aJournal =
            $this->_oDb->getRow(
                "SELECT `status`, `result_json`
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

        $aState =
            json_decode(
                (string)(
                    $aJournal['result_json']
                    ?? ''
                ),
                true
            );

        if (!is_array($aState)) {
            $aResult['error'] =
                'Repair journal state could not be decoded.';

            return $aResult;
        }

        $aResult['journal_status'] =
            (string)(
                $aJournal['status']
                ?? ''
            );

        $aResult['phase'] =
            (string)(
                $aState['phase']
                ?? ''
            );

        $aResult['runtime_moved'] =
            !empty(
                $aState['runtime_moved']
            );

        if (
            $aResult['journal_status'] !== 'runtime_merging'
            || $aResult['phase'] !== 'runtime_merging'
            || !$aResult['runtime_moved']
        ) {
            $aResult['error'] =
                'Runtime merge range-state inspection requires an exact runtime_merging journal with protected runtime still moved.';

            return $aResult;
        }

        /*
         * The durable ledger is authoritative.
         */
        $aLedger =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (empty($aLedger['ok'])) {
            $aResult['error'] =
                'Runtime merge range-state ledger verification failed: '
                . (
                    $aLedger['error']
                    ?? 'unknown ledger error'
                );

            return $aResult;
        }

        $aResult['event_count'] =
            (int)(
                $aLedger['event_count']
                ?? 0
            );

        $aResult['journal_event_count'] =
            (int)(
                $aLedger['journal_event_count']
                ?? 0
            );

        $aResult['unjournaled_events'] =
            (int)(
                $aLedger['unjournaled_events']
                ?? -1
            );

        if (
            $aResult['unjournaled_events'] !== 0
            || $aResult['event_count']
                !== $aResult['journal_event_count']
        ) {
            $aResult['error'] =
                'Runtime merge range-state inspection refused because the durable ledger and journal are not synchronized.';

            return $aResult;
        }

        $aManifest =
            $aLedger['manifest']
            ?? array();

        $iManifestCount =
            count($aManifest);

        if (
            $iStartIndex >= $iManifestCount
            || ($iStartIndex + $iFileCount) > $iManifestCount
        ) {
            $aResult['error'] =
                'Runtime merge range-state request is outside the execution manifest.';

            return $aResult;
        }

        $iEndExclusive =
            $iStartIndex + $iFileCount;

        $aResult['end_index_exclusive'] =
            $iEndExclusive;

        /*
         * Prove both the ledger state and the corresponding live
         * filesystem state for every requested entry.
         */
        for (
            $iIndex = $iStartIndex;
            $iIndex < $iEndExclusive;
            $iIndex++
        ) {
            $aEntry =
                $aManifest[$iIndex];

            $sEntryState =
                (string)(
                    $aLedger[
                        'entry_states'
                    ][$iIndex]
                    ?? ''
                );

            if (
                $sEntryState !== ''
                && $sEntryState !== 'rolled_back'
                && $sEntryState !== 'installed'
                && $sEntryState !== 'intent'
            ) {
                $aResult['error'] =
                    'Runtime merge range-state encountered unsupported ledger state '
                    . $sEntryState
                    . ' at manifest entry '
                    . $iIndex
                    . '.';

                return $aResult;
            }

            $aDestination =
                $this->inspectRuntimeMergeDestinationEntry(
                    $iJournalId,
                    $iIndex
                );

            if (empty($aDestination['ok'])) {
                $aResult['error'] =
                    'Runtime merge range-state could not inspect destination for manifest entry '
                    . $iIndex
                    . ': '
                    . (
                        $aDestination['error']
                        ?? 'unknown destination error'
                    );

                return $aResult;
            }

            $sDestinationState =
                (string)(
                    $aDestination['state']
                    ?? ''
                );

            /*
             * Passive ledger states require physical absence.
             */
            if (
                (
                    $sEntryState === ''
                    || $sEntryState === 'rolled_back'
                )
                && $sDestinationState !== 'absent'
            ) {
                $aResult['error'] =
                    'Passive runtime merge entry '
                    . $iIndex
                    . ' has a non-absent destination.';

                return $aResult;
            }

            /*
             * Installed state requires an exact manifest destination.
             */
            if (
                $sEntryState === 'installed'
                && (
                    $sDestinationState !== 'exact'
                    || empty(
                        $aDestination[
                            'matches_manifest'
                        ]
                    )
                )
            ) {
                $aResult['error'] =
                    'Installed runtime merge entry '
                    . $iIndex
                    . ' does not have an exact manifest destination.';

                return $aResult;
            }

            /*
             * INTENT may legitimately correspond to different crash
             * positions. Report it as recovery-only rather than
             * trying to reinterpret or repair it here.
             */
            if ($sEntryState === '') {
                $aResult['never_installed_count']++;
                $aResult['passive_count']++;
            } elseif ($sEntryState === 'rolled_back') {
                $aResult['rolled_back_count']++;
                $aResult['passive_count']++;
            } elseif ($sEntryState === 'installed') {
                $aResult['installed_count']++;

                $aResult['installed_bytes'] +=
                    (int)(
                        $aEntry['size']
                        ?? 0
                    );
            } elseif ($sEntryState === 'intent') {
                $aResult['intent_count']++;
                $aResult['recovery_required'] =
                    true;
            }

            $aResult['entries'][] =
                array(
                    'manifest_index' => $iIndex,
                    'file' =>
                        (string)(
                            $aEntry['file']
                            ?? ''
                        ),
                    'relative_path' =>
                        (string)(
                            $aEntry['relative_path']
                            ?? ''
                        ),
                    'size' =>
                        (int)(
                            $aEntry['size']
                            ?? 0
                        ),
                    'entry_state' =>
                        $sEntryState,
                    'destination_state' =>
                        $sDestinationState,
                    'matches_manifest' =>
                        !empty(
                            $aDestination[
                                'matches_manifest'
                            ]
                        ),
                );
        }

        $aResult['all_passive'] =
            $aResult['passive_count']
                === $iFileCount;

        $aResult['all_installed'] =
            $aResult['installed_count']
                === $iFileCount;

        /*
         * Install is offered only when every requested entry is
         * passive and physically absent.
         */
        $aResult['install_ready'] =
            $aResult['all_passive']
            && !$aResult['recovery_required'];

        /*
         * Bounded rollback may contain a mixture of installed and
         * passive entries, but no unresolved intent.
         */
        $aResult['rollback_ready'] =
            $aResult['installed_count'] > 0
            && !$aResult['recovery_required'];

        $aResult['ok'] = true;

        return $aResult;
    }


    public function rollbackRuntimeMergeEntry(
        $iJournalId,
        $iManifestIndex = 0
    ) {
        $iJournalId = (int)$iJournalId;
        $iManifestIndex = (int)$iManifestIndex;

        $aResult = array(
            'ok' => false,
            'rolled_back' => false,
            'changes_applied' => false,
            'runtime_files_changed' => false,
            'journal_id' => $iJournalId,
            'manifest_index' => $iManifestIndex,
            'guarded_gateway' => true,
            'operation' => 'rollback',
            'starting_ledger_events' => 0,
            'final_ledger_events' => 0,
            'copied_files' => 0,
            'copied_bytes' => 0,
            'error' => '',
        );

        /*
         * Match Doctor's existing mutation policy:
         * authenticated web POST only.
         */
        if (
            PHP_SAPI === 'cli'
            || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        ) {
            $aResult['error'] =
                'Runtime merge rollback is allowed only from an authenticated web POST.';

            return $aResult;
        }

        if (!isAdmin()) {
            $aResult['error'] =
                'Administrator authorization is required.';

            return $aResult;
        }

        if (
            $iJournalId <= 0
            || $iManifestIndex < 0
        ) {
            $aResult['error'] =
                'Runtime merge rollback parameters are outside the guarded limits.';

            return $aResult;
        }

        /*
         * Re-prove the authoritative repair state independently
         * before allowing the protected rollback helper to mutate.
         */
        $aJournal =
            $this->_oDb->getRow(
                "SELECT `status`, `result_json`, `error_text`
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

        $aState =
            json_decode(
                (string)(
                    $aJournal['result_json']
                    ?? ''
                ),
                true
            );

        if (
            (string)(
                $aJournal['status']
                ?? ''
            ) !== 'runtime_merging'
            || !is_array($aState)
            || (string)(
                $aState['phase']
                ?? ''
            ) !== 'runtime_merging'
            || empty(
                $aState['runtime_moved']
            )
        ) {
            $aResult['error'] =
                'Runtime merge rollback requires an exact runtime_merging journal with protected runtime still moved.';

            return $aResult;
        }

        $aLedger =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (empty($aLedger['ok'])) {
            $aResult['error'] =
                'Runtime merge rollback ledger verification failed: '
                . (
                    $aLedger['error']
                    ?? 'unknown ledger error'
                );

            return $aResult;
        }

        if (
            (int)(
                $aLedger['unjournaled_events']
                ?? -1
            ) !== 0
            || (int)(
                $aLedger['event_count']
                ?? -1
            ) !==
            (int)(
                $aLedger['journal_event_count']
                ?? -2
            )
        ) {
            $aResult['error'] =
                'Runtime merge rollback refused because the durable ledger and journal are not synchronized.';

            return $aResult;
        }

        /*
         * The controlled Studio rollback path does not perform
         * intent recovery. Any unresolved intent remains a
         * separate explicit recovery condition.
         */
        foreach (
            (
                $aLedger['entry_states']
                ?? array()
            )
            as $iIndex => $sState
        ) {
            if ((string)$sState === 'intent') {
                $aResult['error'] =
                    'Runtime merge rollback refused because manifest entry '
                    . (int)$iIndex
                    . ' has an unresolved durable intent.';

                return $aResult;
            }
        }

        $sTargetState =
            (string)(
                $aLedger[
                    'entry_states'
                ][$iManifestIndex]
                ?? ''
            );

        if ($sTargetState !== 'installed') {
            $aResult['error'] =
                'Controlled runtime merge rollback requires the requested manifest entry to be in installed state.';

            return $aResult;
        }

        $iStartingEvents =
            (int)(
                $aLedger['event_count']
                ?? 0
            );

        $aResult['starting_ledger_events'] =
            $iStartingEvents;

        /*
         * Delegate deletion only after every public gateway guard
         * has independently passed. The protected helper proves
         * exact manifest bytes plus Doctor-recorded filesystem
         * ownership before unlinking.
         */
        $aRollback =
            $this->rollbackRuntimeMergeManifestEntry(
                $iJournalId,
                $iManifestIndex
            );

        if (!is_array($aRollback)) {
            $aResult['error'] =
                'Runtime merge rollback executor returned an invalid result.';

            return $aResult;
        }

        $aResult['changes_applied'] =
            !empty(
                $aRollback['changes_applied']
            );

        $aResult['runtime_files_changed'] =
            !empty(
                $aRollback['runtime_files_changed']
            );

        $aResult['rolled_back'] =
            !empty(
                $aRollback['rolled_back']
            );

        $aResult['copied_files'] =
            (int)(
                $aRollback['copied_files']
                ?? 0
            );

        $aResult['copied_bytes'] =
            (int)(
                $aRollback['copied_bytes']
                ?? 0
            );

        if (empty($aRollback['ok'])) {
            $aResult['error'] =
                'Runtime merge rollback did not complete: '
                . (
                    $aRollback['error']
                    ?? 'unknown rollback error'
                );

            return $aResult;
        }

        /*
         * Independent post-mutation proof.
         */
        $aFinal =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (
            empty($aFinal['ok'])
            || (int)(
                $aFinal['unjournaled_events']
                ?? -1
            ) !== 0
            || (int)(
                $aFinal['event_count']
                ?? -1
            ) !==
            (int)(
                $aFinal['journal_event_count']
                ?? -2
            )
        ) {
            $aResult['error'] =
                'Runtime merge rollback completed but final ledger verification failed.';

            return $aResult;
        }

        $iFinalEvents =
            (int)(
                $aFinal['event_count']
                ?? -1
            );

        $aResult['final_ledger_events'] =
            $iFinalEvents;

        if (
            (
                $aFinal[
                    'entry_states'
                ][$iManifestIndex]
                ?? ''
            ) !== 'rolled_back'
            || $iFinalEvents !== ($iStartingEvents + 1)
        ) {
            $aResult['error'] =
                'Runtime merge rollback final state does not match the expected single durable rollback event.';

            return $aResult;
        }

        $aResult['ok'] = true;
        $aResult['rolled_back'] = true;

        return $aResult;
    }


    /**
     * Roll back one bounded range of proven runtime-merge entries.
     *
     * Public mutation gateway for Studio. The lower-level bounded
     * rollback implementation remains protected.
     */
    public function rollbackRuntimeMergeBatchEntries(
        $iJournalId,
        $iStartIndex = 0,
        $iFileCount = 10
    ) {
        $iJournalId = (int)$iJournalId;
        $iStartIndex = (int)$iStartIndex;
        $iFileCount = (int)$iFileCount;

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'runtime_files_changed' => false,
            'journal_id' => $iJournalId,
            'start_index' => $iStartIndex,
            'file_count' => $iFileCount,
            'end_index_exclusive' => $iStartIndex,
            'guarded_gateway' => true,
            'operation' => 'batch_rollback',
            'starting_ledger_events' => 0,
            'final_ledger_events' => 0,
            'rolled_back_count' => 0,
            'rolled_back_indexes' => array(),
            'skipped_indexes' => array(),
            'copied_files' => 0,
            'copied_bytes' => 0,
            'error_index' => -1,
            'error' => '',
        );

        /*
         * Match Doctor's mutation policy:
         * authenticated administrator web POST only.
         */
        if (
            PHP_SAPI === 'cli'
            || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        ) {
            $aResult['error'] =
                'Runtime merge batch rollback is allowed only from an authenticated web POST.';

            return $aResult;
        }

        if (!isAdmin()) {
            $aResult['error'] =
                'Administrator authorization is required.';

            return $aResult;
        }

        /*
         * Keep the public ceiling identical to the currently proven
         * protected rollback ceiling.
         */
        if (
            $iJournalId <= 0
            || $iStartIndex < 0
            || $iFileCount < 1
            || $iFileCount > 10
        ) {
            $aResult['error'] =
                'Runtime merge batch rollback parameters are outside the guarded limits.';

            return $aResult;
        }

        /*
         * Independently prove the authoritative journal state.
         */
        $aJournal =
            $this->_oDb->getRow(
                "SELECT `status`, `result_json`
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

        $aState =
            json_decode(
                (string)(
                    $aJournal['result_json']
                    ?? ''
                ),
                true
            );

        if (
            (string)(
                $aJournal['status']
                ?? ''
            ) !== 'runtime_merging'
            || !is_array($aState)
            || (string)(
                $aState['phase']
                ?? ''
            ) !== 'runtime_merging'
            || empty(
                $aState['runtime_moved']
            )
        ) {
            $aResult['error'] =
                'Runtime merge batch rollback requires an exact runtime_merging journal with protected runtime still moved.';

            return $aResult;
        }

        /*
         * Durable ledger must already be completely synchronized.
         * Recovery is deliberately separate.
         */
        $aLedger =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (empty($aLedger['ok'])) {
            $aResult['error'] =
                'Runtime merge batch rollback ledger verification failed: '
                . (
                    $aLedger['error']
                    ?? 'unknown ledger error'
                );

            return $aResult;
        }

        if (
            (int)(
                $aLedger['unjournaled_events']
                ?? -1
            ) !== 0
            || (int)(
                $aLedger['event_count']
                ?? -1
            ) !==
            (int)(
                $aLedger['journal_event_count']
                ?? -2
            )
        ) {
            $aResult['error'] =
                'Runtime merge batch rollback refused because the durable ledger and journal are not synchronized.';

            return $aResult;
        }

        /*
         * Normal bounded rollback does not consume unresolved intent.
         * Any intent anywhere in the manifest locks this gateway.
         */
        foreach (
            (
                $aLedger['entry_states']
                ?? array()
            )
            as $iIndex => $sState
        ) {
            if ((string)$sState === 'intent') {
                $aResult['error'] =
                    'Runtime merge batch rollback refused because manifest entry '
                    . (int)$iIndex
                    . ' has an unresolved durable intent.';

                return $aResult;
            }
        }

        $aManifest =
            $aLedger['manifest']
            ?? array();

        $iManifestCount =
            count($aManifest);

        if (
            $iStartIndex >= $iManifestCount
            || ($iStartIndex + $iFileCount) > $iManifestCount
        ) {
            $aResult['error'] =
                'Runtime merge batch rollback range is outside the execution manifest.';

            return $aResult;
        }

        $iEndExclusive =
            $iStartIndex + $iFileCount;

        $aResult['end_index_exclusive'] =
            $iEndExclusive;

        /*
         * Permit only known passive states plus installed.
         * At least one entry must actually require rollback so a
         * repeated submission cannot masquerade as a successful
         * mutation.
         */
        $iInstalledBefore = 0;

        for (
            $iIndex = $iStartIndex;
            $iIndex < $iEndExclusive;
            $iIndex++
        ) {
            $sEntryState =
                (string)(
                    $aLedger[
                        'entry_states'
                    ][$iIndex]
                    ?? ''
                );

            if (
                $sEntryState !== ''
                && $sEntryState !== 'rolled_back'
                && $sEntryState !== 'installed'
            ) {
                $aResult['error_index'] =
                    $iIndex;

                $aResult['error'] =
                    'Runtime merge batch rollback encountered unsupported state '
                    . $sEntryState
                    . ' at manifest entry '
                    . $iIndex
                    . '.';

                return $aResult;
            }

            if ($sEntryState === 'installed') {
                $iInstalledBefore++;
            }
        }

        if ($iInstalledBefore < 1) {
            $aResult['error'] =
                'Runtime merge batch rollback requires at least one installed entry in the requested range.';

            return $aResult;
        }

        $iStartingEvents =
            (int)(
                $aLedger['event_count']
                ?? 0
            );

        $aResult['starting_ledger_events'] =
            $iStartingEvents;

        /*
         * Delegate only after all public guards pass.
         *
         * The protected helper rolls the requested range backward,
         * re-verifies the ledger for each entry and delegates actual
         * deletion to the identity-proving single-entry rollback.
         */
        $aRollback =
            $this->rollbackRuntimeMergeBatch(
                $iJournalId,
                $iStartIndex,
                $iFileCount
            );

        if (!is_array($aRollback)) {
            $aResult['error'] =
                'Runtime merge batch rollback executor returned an invalid result.';

            return $aResult;
        }

        $aResult['changes_applied'] =
            !empty(
                $aRollback['changes_applied']
            );

        $aResult['rolled_back_count'] =
            (int)(
                $aRollback['rolled_back_count']
                ?? 0
            );

        $aResult['rolled_back_indexes'] =
            is_array(
                $aRollback['rolled_back_indexes']
                ?? null
            )
                ? $aRollback['rolled_back_indexes']
                : array();

        $aResult['skipped_indexes'] =
            is_array(
                $aRollback['skipped_indexes']
                ?? null
            )
                ? $aRollback['skipped_indexes']
                : array();

        $aResult['error_index'] =
            (int)(
                $aRollback['error_index']
                ?? -1
            );

        if (empty($aRollback['ok'])) {
            $aResult['error'] =
                'Runtime merge batch rollback did not complete: '
                . (
                    $aRollback['error']
                    ?? 'unknown rollback error'
                );

            return $aResult;
        }

        /*
         * Independent authoritative post-mutation proof.
         */
        $aFinal =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (
            empty($aFinal['ok'])
            || (int)(
                $aFinal['unjournaled_events']
                ?? -1
            ) !== 0
            || (int)(
                $aFinal['event_count']
                ?? -1
            ) !==
            (int)(
                $aFinal['journal_event_count']
                ?? -2
            )
        ) {
            $aResult['error'] =
                'Runtime merge batch rollback completed but final ledger verification failed.';

            return $aResult;
        }

        $iFinalEvents =
            (int)(
                $aFinal['event_count']
                ?? -1
            );

        $aResult['final_ledger_events'] =
            $iFinalEvents;

        /*
         * Every installed entry in the range must now be passive,
         * and each actual installed rollback contributes exactly one
         * durable rolled_back event.
         */
        for (
            $iIndex = $iStartIndex;
            $iIndex < $iEndExclusive;
            $iIndex++
        ) {
            $sFinalState =
                (string)(
                    $aFinal[
                        'entry_states'
                    ][$iIndex]
                    ?? ''
                );

            if (
                $sFinalState === 'intent'
                || $sFinalState === 'installed'
            ) {
                $aResult['error_index'] =
                    $iIndex;

                $aResult['error'] =
                    'Runtime merge batch rollback final verification left manifest entry '
                    . $iIndex
                    . ' in active state '
                    . $sFinalState
                    . '.';

                return $aResult;
            }
        }

        if (
            $aResult['rolled_back_count']
                !== $iInstalledBefore
            || $iFinalEvents
                !== (
                    $iStartingEvents
                    + $aResult['rolled_back_count']
                )
        ) {
            $aResult['error'] =
                'Runtime merge batch rollback durable event count does not match the proven installed-entry count.';

            return $aResult;
        }

        $aFinalState =
            $aFinal['state']
            ?? array();

        $aProgress =
            $aFinalState[
                'merge'
            ]['progress']
            ?? array();

        $aResult['copied_files'] =
            (int)(
                $aProgress['copied_files']
                ?? 0
            );

        $aResult['copied_bytes'] =
            (int)(
                $aProgress['copied_bytes']
                ?? 0
            );

        $aResult['runtime_files_changed'] =
            !empty(
                $aProgress[
                    'runtime_files_changed'
                ]
            );

        $aResult['ok'] = true;

        return $aResult;
    }


    public function rollbackRuntimeMergeWorkCycle(
        $iJournalId,
        $iStartIndex,
        $iFileCount = 250,
        $iExpectedLedgerEvents = null
    ) {
        $iJournalId = (int)$iJournalId;
        $iStartIndex = (int)$iStartIndex;
        $iFileCount = (int)$iFileCount;

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'start_index' => $iStartIndex,
            'file_count' => $iFileCount,
            'installed_before' => 0,
            'rolled_back_count' => 0,
            'chunks_completed' => 0,
            'starting_ledger_events' => 0,
            'final_ledger_events' => 0,
            'copied_files' => 0,
            'copied_bytes' => 0,
            'runtime_files_changed' => false,
            'error' => '',
        );

        if (
            PHP_SAPI === 'cli'
            || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        ) {
            $aResult['error'] =
                'Runtime merge rollback work cycle requires an authenticated web POST.';
            return $aResult;
        }

        if (!isAdmin()) {
            $aResult['error'] =
                'Administrator authorization is required.';
            return $aResult;
        }

        if (
            $iJournalId <= 0
            || $iStartIndex < 0
            || $iFileCount < 1
            || $iFileCount > 250
            || $iExpectedLedgerEvents === null
        ) {
            $aResult['error'] =
                'A valid journal, range and ledger checkpoint are required.';
            return $aResult;
        }

        $aInitial =
            $this->readAndVerifyRuntimeMergeLedger($iJournalId);

        if (
            empty($aInitial['ok'])
            || (int)($aInitial['unjournaled_events'] ?? -1) !== 0
            || (int)($aInitial['event_count'] ?? -1)
                !== (int)($aInitial['journal_event_count'] ?? -2)
        ) {
            $aResult['error'] =
                'Rollback work cycle could not establish a synchronized ledger.';
            return $aResult;
        }

        $iStartingEvents =
            (int)($aInitial['event_count'] ?? -1);

        if (
            $iStartingEvents
            !== (int)$iExpectedLedgerEvents
        ) {
            $aResult['error'] =
                'Rollback work-cycle checkpoint is stale. Reload the repair state.';
            return $aResult;
        }

        $aState = $aInitial['state'] ?? array();

        if (
            ($aState['phase'] ?? '') !== 'runtime_merging'
            || empty($aState['runtime_moved'])
        ) {
            $aResult['error'] =
                'Repair journal is not in protected runtime merging state.';
            return $aResult;
        }

        $iManifestCount =
            count($aInitial['manifest'] ?? array());
        $iEndExclusive =
            $iStartIndex + $iFileCount;

        if (
            $iStartIndex >= $iManifestCount
            || $iEndExclusive > $iManifestCount
        ) {
            $aResult['error'] =
                'Rollback work-cycle range exceeds the execution manifest.';
            return $aResult;
        }

        foreach (
            ($aInitial['entry_states'] ?? array())
            as $iIndex => $sEntryState
        ) {
            if ((string)$sEntryState === 'intent') {
                $aResult['error'] =
                    'Rollback work cycle refused because manifest entry '
                    . (int)$iIndex
                    . ' has unresolved intent.';
                return $aResult;
            }
        }

        $iInstalledBefore = 0;

        for (
            $i = $iStartIndex;
            $i < $iEndExclusive;
            $i++
        ) {
            $sEntryState =
                (string)(
                    $aInitial['entry_states'][$i]
                    ?? ''
                );

            if (
                $sEntryState !== ''
                && $sEntryState !== 'rolled_back'
                && $sEntryState !== 'installed'
            ) {
                $aResult['error'] =
                    'Unsupported state in rollback work-cycle range at entry '
                    . $i . '.';
                return $aResult;
            }

            if ($sEntryState === 'installed')
                $iInstalledBefore++;
        }

        $aResult['installed_before'] =
            $iInstalledBefore;
        $aResult['starting_ledger_events'] =
            $iStartingEvents;

        for (
            $iCursor = $iEndExclusive;
            $iCursor > $iStartIndex;
            $iCursor = $iChunkStart
        ) {
            $iChunkStart =
                max($iStartIndex, $iCursor - 10);
            $iChunkCount =
                $iCursor - $iChunkStart;

            $aCurrent =
                $this->readAndVerifyRuntimeMergeLedger(
                    $iJournalId
                );

            if (
                empty($aCurrent['ok'])
                || (int)(
                    $aCurrent['unjournaled_events']
                    ?? -1
                ) !== 0
                || (int)(
                    $aCurrent['event_count']
                    ?? -1
                ) !==
                (int)(
                    $aCurrent['journal_event_count']
                    ?? -2
                )
            ) {
                $aResult['error'] =
                    'Rollback work cycle lost ledger synchronization.';
                return $aResult;
            }

            $iChunkInstalled = 0;

            for (
                $i = $iChunkStart;
                $i < $iCursor;
                $i++
            ) {
                if (
                    (string)(
                        $aCurrent['entry_states'][$i]
                        ?? ''
                    ) === 'installed'
                ) {
                    $iChunkInstalled++;
                }
            }

            if ($iChunkInstalled > 0) {
                $aRollback =
                    $this->rollbackRuntimeMergeBatchEntries(
                        $iJournalId,
                        $iChunkStart,
                        $iChunkCount
                    );

                if (empty($aRollback['ok'])) {
                    $aResult['error'] =
                        'Rollback work cycle stopped at manifest entry '
                        . $iChunkStart
                        . ': '
                        . (
                            $aRollback['error']
                            ?? 'unknown rollback error'
                        );
                    return $aResult;
                }

                $aResult['rolled_back_count'] +=
                    (int)(
                        $aRollback['rolled_back_count']
                        ?? 0
                    );

                $aResult['changes_applied'] =
                    $aResult['rolled_back_count'] > 0;
            }

            $aResult['chunks_completed']++;
        }

        $aFinal =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (
            empty($aFinal['ok'])
            || (int)(
                $aFinal['unjournaled_events']
                ?? -1
            ) !== 0
            || (int)(
                $aFinal['event_count']
                ?? -1
            ) !==
            (int)(
                $aFinal['journal_event_count']
                ?? -2
            )
        ) {
            $aResult['error'] =
                'Rollback work cycle completed but final ledger verification failed.';
            return $aResult;
        }

        for (
            $i = $iStartIndex;
            $i < $iEndExclusive;
            $i++
        ) {
            $sFinalState =
                (string)(
                    $aFinal['entry_states'][$i]
                    ?? ''
                );

            if (
                $sFinalState === 'intent'
                || $sFinalState === 'installed'
            ) {
                $aResult['error'] =
                    'Rollback work-cycle final proof left entry '
                    . $i
                    . ' active.';
                return $aResult;
            }
        }

        $iFinalEvents =
            (int)($aFinal['event_count'] ?? -1);

        if (
            $aResult['rolled_back_count']
                !== $iInstalledBefore
            || $iFinalEvents
                !== (
                    $iStartingEvents
                    + $aResult['rolled_back_count']
                )
        ) {
            $aResult['error'] =
                'Rollback work-cycle durable event proof failed.';
            return $aResult;
        }

        $aProgress =
            ($aFinal['state']['merge']['progress']
                ?? array());

        $aResult['final_ledger_events'] =
            $iFinalEvents;
        $aResult['copied_files'] =
            (int)($aProgress['copied_files'] ?? 0);
        $aResult['copied_bytes'] =
            (int)($aProgress['copied_bytes'] ?? 0);
        $aResult['runtime_files_changed'] =
            !empty(
                $aProgress['runtime_files_changed']
            );
        $aResult['ok'] = true;

        return $aResult;
    }


    public function getRuntimeMergeNextBatchState($iJournalId)
    {
        $iJournalId = (int)$iJournalId;
        $iBatchCeiling = 25;

        $r = array(
            'ok' => false,
            'journal_id' => $iJournalId,
            'batch_ceiling' => $iBatchCeiling,
            'manifest_count' => 0,
            'installed_count' => 0,
            'passive_count' => 0,
            'next_start_index' => -1,
            'next_batch_files' => 0,
            'complete' => false,
            'event_count' => 0,
            'journal_event_count' => 0,
            'unjournaled_events' => -1,
            'error' => '',
        );

        if ($iJournalId <= 0) {
            $r['error'] = 'A valid repair journal is required.';
            return $r;
        }

        $aJournal = $this->_oDb->getRow(
            "SELECT `status`, `result_json`
             FROM `my_una_doctor_repairs`
             WHERE `id` = ?
             LIMIT 1",
            array($iJournalId)
        );

        $aState = json_decode(
            (string)($aJournal['result_json'] ?? ''),
            true
        );

        if (
            empty($aJournal)
            || ($aJournal['status'] ?? '') !== 'runtime_merging'
            || !is_array($aState)
            || ($aState['phase'] ?? '') !== 'runtime_merging'
            || empty($aState['runtime_moved'])
        ) {
            $r['error'] =
                'Automatic continuation requires an active runtime merge.';
            return $r;
        }

        $aLedger =
            $this->readAndVerifyRuntimeMergeLedger($iJournalId);

        if (empty($aLedger['ok'])) {
            $r['error'] =
                'Ledger verification failed: '
                . ($aLedger['error'] ?? 'unknown error');
            return $r;
        }

        $r['event_count'] =
            (int)($aLedger['event_count'] ?? 0);
        $r['journal_event_count'] =
            (int)($aLedger['journal_event_count'] ?? 0);
        $r['unjournaled_events'] =
            (int)($aLedger['unjournaled_events'] ?? -1);

        if (
            $r['unjournaled_events'] !== 0
            || $r['event_count'] !== $r['journal_event_count']
        ) {
            $r['error'] =
                'Ledger and repair journal are not synchronized.';
            return $r;
        }

        $aManifest = $aLedger['manifest'] ?? array();

        if (!is_array($aManifest) || !$aManifest) {
            $r['error'] = 'Runtime merge manifest is unavailable.';
            return $r;
        }

        $r['manifest_count'] = count($aManifest);

        foreach ($aManifest as $i => $aEntry) {
            $sState =
                (string)($aLedger['entry_states'][$i] ?? '');

            if ($sState === 'installed') {
                $r['installed_count']++;
                continue;
            }

            if ($sState === '' || $sState === 'rolled_back') {
                $r['passive_count']++;

                if ($r['next_start_index'] < 0) {
                    $r['next_start_index'] = (int)$i;
                }

                continue;
            }

            if ($sState === 'intent') {
                $r['error'] =
                    'Recovery is required at manifest entry '
                    . (int)$i . '.';
                return $r;
            }

            $r['error'] =
                'Unsupported runtime merge state at manifest entry '
                . (int)$i . '.';
            return $r;
        }

        if ($r['next_start_index'] < 0) {
            $r['complete'] = true;
            $r['next_start_index'] = $r['manifest_count'];
            $r['next_batch_files'] = 0;
        } else {
            $r['next_batch_files'] =
                min($iBatchCeiling, $r['passive_count']);
        }

        $r['ok'] = true;
        return $r;
    }


    /**
     * Continue the next automatically derived runtime-merge batch.
     *
     * Studio supplies no manifest index and no batch size.
     * The expected ledger count binds this request to the exact
     * durable state that the administrator approved.
     */
    public function continueRuntimeMergeNextBatch(
        $iJournalId,
        $iExpectedLedgerEvents
    ) {
        $iJournalId = (int)$iJournalId;
        $iExpectedLedgerEvents =
            (int)$iExpectedLedgerEvents;

        $r = array(
            'ok' => false,
            'changes_applied' => false,
            'guarded_gateway' => true,
            'operation' => 'continue_repair',
            'journal_id' => $iJournalId,
            'expected_ledger_events' =>
                $iExpectedLedgerEvents,
            'start_index' => -1,
            'max_files' => 0,
            'complete' => false,
            'error' => '',
        );

        if (
            PHP_SAPI === 'cli'
            || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        ) {
            $r['error'] =
                'Continue Repair is allowed only from an authenticated web POST.';
            return $r;
        }

        if (!isAdmin()) {
            $r['error'] =
                'Administrator authorization is required.';
            return $r;
        }

        if (
            $iJournalId <= 0
            || $iExpectedLedgerEvents < 0
        ) {
            $r['error'] =
                'Continue Repair received an invalid durable checkpoint.';
            return $r;
        }

        /*
         * Derive the next action entirely from authoritative state.
         */
        $aNext =
            $this->getRuntimeMergeNextBatchState(
                $iJournalId
            );

        if (empty($aNext['ok'])) {
            $r['error'] =
                'Continue Repair could not determine the next safe batch: '
                . (
                    $aNext['error']
                    ?? 'unknown state error'
                );

            return $r;
        }

        /*
         * Reject stale forms, duplicate submissions and old tabs
         * before any mutation is attempted.
         */
        if (
            (int)(
                $aNext['event_count']
                ?? -1
            ) !== $iExpectedLedgerEvents
            || (int)(
                $aNext['journal_event_count']
                ?? -2
            ) !== $iExpectedLedgerEvents
            || (int)(
                $aNext['unjournaled_events']
                ?? -1
            ) !== 0
        ) {
            $r['error'] =
                'Continue Repair checkpoint is stale. Reload the repair page before continuing.';

            return $r;
        }

        if (!empty($aNext['complete'])) {
            $r['ok'] = true;
            $r['complete'] = true;
            $r['start_index'] =
                (int)(
                    $aNext['next_start_index']
                    ?? -1
                );

            return $r;
        }

        $iStartIndex =
            (int)(
                $aNext['next_start_index']
                ?? -1
            );

        $iBatchFiles =
            (int)(
                $aNext['next_batch_files']
                ?? 0
            );

        if (
            $iStartIndex < 0
            || $iBatchFiles < 1
            || $iBatchFiles > 25
        ) {
            $r['error'] =
                'Continue Repair derived an invalid bounded batch.';
            return $r;
        }

        $r['start_index'] = $iStartIndex;
        $r['max_files'] = $iBatchFiles;

        /*
         * The optimized executor independently prepares a new
         * session and must observe the same ledger checkpoint.
         */
        $aBatch =
            $this->executeRuntimeMergeBatchFromSession(
                $iJournalId,
                $iStartIndex,
                $iBatchFiles,
                $iExpectedLedgerEvents
            );

        if (!is_array($aBatch)) {
            $r['error'] =
                'Continue Repair executor returned an invalid result.';
            return $r;
        }

        $aBatch['guarded_gateway'] = true;
        $aBatch['operation'] = 'continue_repair';
        $aBatch['expected_ledger_events'] =
            $iExpectedLedgerEvents;

        return $aBatch;
    }


    public function continueRuntimeMergeWorkCycle(
        $iJournalId,
        $iFileCount,
        $iExpectedLedgerEvents
    ) {
        $iJournalId = (int)$iJournalId;
        $iFileCount = (int)$iFileCount;
        $iExpectedLedgerEvents =
            (int)$iExpectedLedgerEvents;

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'requested_files' => $iFileCount,
            'start_index' => -1,
            'end_index_exclusive' => -1,
            'batches_completed' => 0,
            'installed_count' => 0,
            'installed_bytes' => 0,
            'starting_ledger_events' => 0,
            'final_ledger_events' => 0,
            'copied_files' => 0,
            'copied_bytes' => 0,
            'runtime_files_changed' => false,
            'error' => '',
        );

        /*
         * The underlying executor remains deliberately capped at
         * 25 files. A work cycle may coordinate multiple independently
         * verified 25-file batches, but never widens that mutation
         * boundary.
         */
        if (
            PHP_SAPI === 'cli'
            || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        ) {
            $aResult['error'] =
                'Runtime merge work cycle requires an authenticated web POST.';
            return $aResult;
        }

        if (!isAdmin()) {
            $aResult['error'] =
                'Administrator authorization is required.';
            return $aResult;
        }

        if (
            $iJournalId <= 0
            || $iFileCount < 25
            || $iFileCount > 250
            || ($iFileCount % 25) !== 0
            || $iExpectedLedgerEvents < 0
        ) {
            $aResult['error'] =
                'Runtime merge work cycle requires 25 to 250 files in 25-file increments and a valid ledger checkpoint.';
            return $aResult;
        }

        $aInitial =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (
            empty($aInitial['ok'])
            || (int)(
                $aInitial['unjournaled_events']
                ?? -1
            ) !== 0
            || (int)(
                $aInitial['event_count']
                ?? -1
            ) !==
            (int)(
                $aInitial['journal_event_count']
                ?? -2
            )
        ) {
            $aResult['error'] =
                'Runtime merge work cycle could not establish a synchronized ledger.';
            return $aResult;
        }

        $iStartingEvents =
            (int)(
                $aInitial['event_count']
                ?? -1
            );

        if (
            $iStartingEvents
            !== $iExpectedLedgerEvents
        ) {
            $aResult['error'] =
                'Runtime merge work-cycle checkpoint is stale. Reload the repair state.';
            return $aResult;
        }

        $aState =
            $aInitial['state']
            ?? array();

        if (
            ($aState['phase'] ?? '')
                !== 'runtime_merging'
            || empty($aState['runtime_moved'])
        ) {
            $aResult['error'] =
                'Repair journal is not in protected runtime merging state.';
            return $aResult;
        }

        /*
         * Any unresolved intent anywhere in the manifest blocks a
         * normal forward work cycle.
         */
        foreach (
            ($aInitial['entry_states'] ?? array())
            as $iIndex => $sEntryState
        ) {
            if ((string)$sEntryState === 'intent') {
                $aResult['error'] =
                    'Runtime merge work cycle refused because manifest entry '
                    . (int)$iIndex
                    . ' has unresolved intent.';
                return $aResult;
            }
        }

        $aNext =
            $this->getRuntimeMergeNextBatchState(
                $iJournalId
            );

        if (
            empty($aNext['ok'])
            || (int)(
                $aNext['event_count']
                ?? -1
            ) !== $iStartingEvents
            || (int)(
                $aNext['journal_event_count']
                ?? -2
            ) !== $iStartingEvents
            || (int)(
                $aNext['unjournaled_events']
                ?? -1
            ) !== 0
        ) {
            $aResult['error'] =
                'Runtime merge work cycle could not establish the next verified batch.';
            return $aResult;
        }

        if (!empty($aNext['complete'])) {
            $aResult['error'] =
                'Runtime merge is already complete.';
            return $aResult;
        }

        $iStartIndex =
            (int)(
                $aNext['next_start_index']
                ?? -1
            );

        $iManifestCount =
            count(
                $aInitial['manifest']
                ?? array()
            );

        $iEndExclusive =
            $iStartIndex + $iFileCount;

        if (
            $iStartIndex < 0
            || $iStartIndex >= $iManifestCount
            || $iEndExclusive > $iManifestCount
        ) {
            $aResult['error'] =
                'Runtime merge work-cycle range exceeds the execution manifest.';
            return $aResult;
        }

        /*
         * The complete requested range must be passive before any
         * mutation begins. This makes the 250-file proof contiguous
         * and deterministic.
         */
        $iExpectedBytes = 0;

        for (
            $iIndex = $iStartIndex;
            $iIndex < $iEndExclusive;
            $iIndex++
        ) {
            $sEntryState =
                (string)(
                    $aInitial[
                        'entry_states'
                    ][$iIndex]
                    ?? ''
                );

            if (
                $sEntryState !== ''
                && $sEntryState !== 'rolled_back'
            ) {
                $aResult['error'] =
                    'Runtime merge work-cycle target entry '
                    . $iIndex
                    . ' is not passive.';
                return $aResult;
            }

            $aEntry =
                $aInitial['manifest'][$iIndex]
                ?? array();

            $iExpectedBytes +=
                (int)(
                    $aEntry['size']
                    ?? 0
                );
        }

        $aInitialProgress =
            $aState['merge']['progress']
            ?? array();

        $iInitialCopiedFiles =
            (int)(
                $aInitialProgress['copied_files']
                ?? 0
            );

        $iInitialCopiedBytes =
            (int)(
                $aInitialProgress['copied_bytes']
                ?? 0
            );

        $aResult['start_index'] =
            $iStartIndex;
        $aResult['end_index_exclusive'] =
            $iEndExclusive;
        $aResult['starting_ledger_events'] =
            $iStartingEvents;
        $aResult['installed_bytes'] =
            $iExpectedBytes;

        $iCurrentEvents =
            $iStartingEvents;

        $iBatchCount =
            intdiv($iFileCount, 25);

        for (
            $iBatch = 0;
            $iBatch < $iBatchCount;
            $iBatch++
        ) {
            $iBatchStart =
                $iStartIndex
                + ($iBatch * 25);

            /*
             * Fresh authoritative state before every 25-file
             * mutation boundary.
             */
            $aBefore =
                $this->getRuntimeMergeNextBatchState(
                    $iJournalId
                );

            if (
                empty($aBefore['ok'])
                || (int)(
                    $aBefore['event_count']
                    ?? -1
                ) !== $iCurrentEvents
                || (int)(
                    $aBefore['journal_event_count']
                    ?? -2
                ) !== $iCurrentEvents
                || (int)(
                    $aBefore['unjournaled_events']
                    ?? -1
                ) !== 0
                || !empty($aBefore['complete'])
                || (int)(
                    $aBefore['next_start_index']
                    ?? -1
                ) !== $iBatchStart
                || (int)(
                    $aBefore['next_batch_files']
                    ?? -1
                ) !== 25
            ) {
                $aResult['error'] =
                    'Runtime merge work cycle stopped before batch '
                    . ($iBatch + 1)
                    . ' because the verified continuation state changed.';
                return $aResult;
            }

            $iInstalledBefore =
                (int)(
                    $aBefore['installed_count']
                    ?? -1
                );

            $iPassiveBefore =
                (int)(
                    $aBefore['passive_count']
                    ?? -1
                );

            $aBatchResult =
                $this->continueRuntimeMergeNextBatch(
                    $iJournalId,
                    $iCurrentEvents
                );

            if (
                !is_array($aBatchResult)
                || empty($aBatchResult['ok'])
            ) {
                $aResult['error'] =
                    'Runtime merge work cycle stopped during batch '
                    . ($iBatch + 1)
                    . ': '
                    . (
                        is_array($aBatchResult)
                            ? (
                                $aBatchResult['error']
                                ?? 'unknown continuation error'
                            )
                            : 'invalid continuation result'
                    );
                return $aResult;
            }

            /*
             * Verify the exact 25-file range, including live
             * destination identity, before allowing another batch.
             */
            $aRange =
                $this->getRuntimeMergeRangeState(
                    $iJournalId,
                    $iBatchStart,
                    25
                );

            if (
                empty($aRange['ok'])
                || (int)(
                    $aRange['installed_count']
                    ?? -1
                ) !== 25
                || (int)(
                    $aRange['intent_count']
                    ?? -1
                ) !== 0
                || (int)(
                    $aRange['passive_count']
                    ?? -1
                ) !== 0
            ) {
                $aResult['error'] =
                    'Runtime merge work cycle stopped because batch '
                    . ($iBatch + 1)
                    . ' did not pass its exact installed-range proof.';
                return $aResult;
            }

            $aAfter =
                $this->getRuntimeMergeNextBatchState(
                    $iJournalId
                );

            $iExpectedAfterEvents =
                $iCurrentEvents + 50;

            if (
                empty($aAfter['ok'])
                || (int)(
                    $aAfter['event_count']
                    ?? -1
                ) !== $iExpectedAfterEvents
                || (int)(
                    $aAfter['journal_event_count']
                    ?? -2
                ) !== $iExpectedAfterEvents
                || (int)(
                    $aAfter['unjournaled_events']
                    ?? -1
                ) !== 0
                || (int)(
                    $aAfter['installed_count']
                    ?? -1
                ) !== ($iInstalledBefore + 25)
                || (int)(
                    $aAfter['passive_count']
                    ?? -1
                ) !== ($iPassiveBefore - 25)
            ) {
                $aResult['error'] =
                    'Runtime merge work cycle stopped because batch '
                    . ($iBatch + 1)
                    . ' failed its ledger/progress proof.';
                return $aResult;
            }

            $iCurrentEvents =
                $iExpectedAfterEvents;

            $aResult['batches_completed']++;
            $aResult['installed_count'] += 25;
            $aResult['changes_applied'] = true;
        }

        /*
         * Final independent durable proof across the entire cycle.
         */
        $aFinal =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (
            empty($aFinal['ok'])
            || (int)(
                $aFinal['unjournaled_events']
                ?? -1
            ) !== 0
            || (int)(
                $aFinal['event_count']
                ?? -1
            ) !== $iCurrentEvents
            || (int)(
                $aFinal['journal_event_count']
                ?? -2
            ) !== $iCurrentEvents
        ) {
            $aResult['error'] =
                'Runtime merge work cycle completed but final ledger verification failed.';
            return $aResult;
        }

        for (
            $iIndex = $iStartIndex;
            $iIndex < $iEndExclusive;
            $iIndex++
        ) {
            if (
                (string)(
                    $aFinal[
                        'entry_states'
                    ][$iIndex]
                    ?? ''
                ) !== 'installed'
            ) {
                $aResult['error'] =
                    'Runtime merge work-cycle final proof did not find entry '
                    . $iIndex
                    . ' installed.';
                return $aResult;
            }
        }

        if (
            $iCurrentEvents
            !== (
                $iStartingEvents
                + ($iFileCount * 2)
            )
        ) {
            $aResult['error'] =
                'Runtime merge work-cycle durable event delta is incorrect.';
            return $aResult;
        }

        $aFinalState =
            $aFinal['state']
            ?? array();

        $aFinalProgress =
            $aFinalState['merge']['progress']
            ?? array();

        $iFinalCopiedFiles =
            (int)(
                $aFinalProgress['copied_files']
                ?? -1
            );

        $iFinalCopiedBytes =
            (int)(
                $aFinalProgress['copied_bytes']
                ?? -1
            );

        if (
            $iFinalCopiedFiles
            !== ($iInitialCopiedFiles + $iFileCount)
            || $iFinalCopiedBytes
            !== ($iInitialCopiedBytes + $iExpectedBytes)
            || empty(
                $aFinalProgress[
                    'runtime_files_changed'
                ]
            )
        ) {
            $aResult['error'] =
                'Runtime merge work-cycle final copied-file progress proof failed.';
            return $aResult;
        }

        $aResult['final_ledger_events'] =
            $iCurrentEvents;
        $aResult['copied_files'] =
            $iFinalCopiedFiles;
        $aResult['copied_bytes'] =
            $iFinalCopiedBytes;
        $aResult['runtime_files_changed'] =
            true;
        $aResult['ok'] = true;

        return $aResult;
    }



    /**
     * Adaptive protected runtime-merge coordinator.
     *
     * The proven continueRuntimeMergeWorkCycle() executor remains
     * unchanged and capped at 250 files. Large manifests may run
     * several independently verified work cycles in one POST.
     */
    public function continueRuntimeMergeAdaptiveWorkCycle(
        $iJournalId,
        $iExpectedLedgerEvents
    ) {
        $iJournalId = (int)$iJournalId;
        $iExpectedLedgerEvents =
            (int)$iExpectedLedgerEvents;

        $fStarted =
            microtime(true);

        /*
         * Leave headroom for the surrounding PHP/web request.
         * The limit is checked only after a fully verified work cycle.
         */
        $fTimeLimitSeconds = 18.0;

        $iNormalLimit = 250;
        $iLargeLimit = 250;
        $iLargeManifestThreshold = 2000;

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'requested_files' => 0,
            'start_index' => -1,
            'end_index_exclusive' => -1,
            'manifest_count' => 0,
            'installed_count' => 0,
            'installed_bytes' => 0,
            'starting_ledger_events' => 0,
            'final_ledger_events' => 0,
            'batches_completed' => 0,
            'work_cycles_completed' => 0,
            'partial_batches_completed' => 0,
            'large_module_mode' => false,
            'time_limit_seconds' => $fTimeLimitSeconds,
            'time_limit_reached' => false,
            'elapsed_seconds' => 0.0,
            'progress_completed' => 0,
            'progress_percent' => 0.0,
            'remaining_files' => 0,
            'estimated_clicks_remaining' => 0,
            'complete' => false,
            'error' => '',
        );

        if (
            PHP_SAPI === 'cli'
            || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        ) {
            $aResult['error'] =
                'Adaptive runtime merge requires an authenticated web POST.';
            return $aResult;
        }

        if (!isAdmin()) {
            $aResult['error'] =
                'Administrator authorization is required.';
            return $aResult;
        }

        if (
            $iJournalId <= 0
            || $iExpectedLedgerEvents < 0
        ) {
            $aResult['error'] =
                'Adaptive runtime merge requires a valid repair journal and ledger checkpoint.';
            return $aResult;
        }

        $aInitial =
            $this->getRuntimeMergeNextBatchState(
                $iJournalId
            );

        if (
            empty($aInitial['ok'])
            || (int)(
                $aInitial['event_count']
                ?? -1
            ) !== $iExpectedLedgerEvents
            || (int)(
                $aInitial['journal_event_count']
                ?? -2
            ) !== $iExpectedLedgerEvents
            || (int)(
                $aInitial['unjournaled_events']
                ?? -1
            ) !== 0
        ) {
            $aResult['error'] =
                'Adaptive runtime merge could not establish the verified starting checkpoint.';
            return $aResult;
        }

        $iInstalledStart =
            (int)(
                $aInitial['installed_count']
                ?? -1
            );

        $iPassiveStart =
            (int)(
                $aInitial['passive_count']
                ?? -1
            );

        if (
            $iInstalledStart < 0
            || $iPassiveStart < 0
        ) {
            $aResult['error'] =
                'Adaptive runtime merge starting progress is invalid.';
            return $aResult;
        }

        $iManifestCount =
            $iInstalledStart
            + $iPassiveStart;

        if ($iManifestCount <= 0) {
            $aResult['error'] =
                'Adaptive runtime merge manifest count is invalid.';
            return $aResult;
        }

        $bComplete =
            !empty($aInitial['complete']);

        $iStartIndex =
            $bComplete
                ? $iManifestCount
                : (int)(
                    $aInitial['next_start_index']
                    ?? -1
                );

        if (
            !$bComplete
            && (
                $iStartIndex < 0
                || $iStartIndex > $iManifestCount
            )
        ) {
            $aResult['error'] =
                'Adaptive runtime merge next manifest index is invalid.';
            return $aResult;
        }

        $bLargeModule =
            $iManifestCount
            >= $iLargeManifestThreshold;

        $iPerClickLimit =
            $bLargeModule
                ? $iLargeLimit
                : $iNormalLimit;

        $iRequestedFiles =
            min(
                $iPerClickLimit,
                $iPassiveStart
            );

        $aResult['requested_files'] =
            $iRequestedFiles;

        $aResult['start_index'] =
            $iStartIndex;

        $aResult['end_index_exclusive'] =
            $iStartIndex;

        $aResult['manifest_count'] =
            $iManifestCount;

        $aResult['starting_ledger_events'] =
            $iExpectedLedgerEvents;

        $aResult['final_ledger_events'] =
            $iExpectedLedgerEvents;

        $aResult['large_module_mode'] =
            $bLargeModule;

        if ($bComplete || $iPassiveStart === 0) {
            $aResult['progress_completed'] =
                $iManifestCount;

            $aResult['progress_percent'] =
                100.0;

            $aResult['remaining_files'] = 0;
            $aResult['estimated_clicks_remaining'] = 0;
            $aResult['complete'] = true;
            $aResult['elapsed_seconds'] =
                microtime(true) - $fStarted;
            $aResult['ok'] = true;

            return $aResult;
        }

        $iCurrentEvents =
            $iExpectedLedgerEvents;

        while (
            $aResult['installed_count']
            < $iRequestedFiles
        ) {
            /*
             * Never interrupt an active mutation. The timer is checked
             * only while Doctor is already sitting at a verified
             * checkpoint.
             */
            if (
                !empty($aResult['changes_applied'])
                && (
                    microtime(true)
                    - $fStarted
                ) >= $fTimeLimitSeconds
            ) {
                $aResult['time_limit_reached'] =
                    true;
                break;
            }

            $aBefore =
                $this->getRuntimeMergeNextBatchState(
                    $iJournalId
                );

            if (
                empty($aBefore['ok'])
                || (int)(
                    $aBefore['event_count']
                    ?? -1
                ) !== $iCurrentEvents
                || (int)(
                    $aBefore['journal_event_count']
                    ?? -2
                ) !== $iCurrentEvents
                || (int)(
                    $aBefore['unjournaled_events']
                    ?? -1
                ) !== 0
            ) {
                $aResult['error'] =
                    'Adaptive runtime merge stopped because the verified continuation checkpoint changed.';
                return $aResult;
            }

            if (!empty($aBefore['complete'])) {
                break;
            }

            $iPassiveNow =
                (int)(
                    $aBefore['passive_count']
                    ?? -1
                );

            $iRemainingTarget =
                $iRequestedFiles
                - (int)$aResult['installed_count'];

            if (
                $iPassiveNow <= 0
                || $iRemainingTarget <= 0
            ) {
                break;
            }

            $iNextBatchFiles =
                (int)(
                    $aBefore['next_batch_files']
                    ?? 0
                );

            /*
             * Use the existing proven 250-file work-cycle engine
             * whenever at least one complete 25-file group remains.
             */
            if (
                $iRemainingTarget >= 25
                && $iPassiveNow >= 25
                && $iNextBatchFiles === 25
            ) {
                $iChunkAvailable =
                    min(
                        $iRemainingTarget,
                        $iPassiveNow,
                        250
                    );

                $iChunkFiles =
                    intdiv(
                        $iChunkAvailable,
                        25
                    ) * 25;

                if ($iChunkFiles >= 25) {
                    $aCycle =
                        $this->continueRuntimeMergeWorkCycle(
                            $iJournalId,
                            $iChunkFiles,
                            $iCurrentEvents
                        );

                    if (
                        !is_array($aCycle)
                        || empty($aCycle['ok'])
                    ) {
                        $aResult['error'] =
                            'Adaptive runtime merge stopped during a verified work cycle: '
                            . (
                                is_array($aCycle)
                                    ? (
                                        $aCycle['error']
                                        ?? 'unknown work-cycle error'
                                    )
                                    : 'invalid work-cycle result'
                            );

                        return $aResult;
                    }

                    $iDone =
                        (int)(
                            $aCycle['installed_count']
                            ?? 0
                        );

                    if ($iDone !== $iChunkFiles) {
                        $aResult['error'] =
                            'Adaptive runtime merge work-cycle count did not match its verified request.';
                        return $aResult;
                    }

                    $iNewEvents =
                        (int)(
                            $aCycle['final_ledger_events']
                            ?? -1
                        );

                    if ($iNewEvents <= $iCurrentEvents) {
                        $aResult['error'] =
                            'Adaptive runtime merge work cycle returned an invalid ledger checkpoint.';
                        return $aResult;
                    }

                    $aResult['installed_count'] +=
                        $iDone;

                    $aResult['installed_bytes'] +=
                        (int)(
                            $aCycle['installed_bytes']
                            ?? 0
                        );

                    $aResult['batches_completed'] +=
                        (int)(
                            $aCycle['batches_completed']
                            ?? 0
                        );

                    $aResult['work_cycles_completed']++;

                    $aResult['changes_applied'] =
                        true;

                    $iCurrentEvents =
                        $iNewEvents;

                    continue;
                }
            }

            /*
             * Final manifest remainder smaller than 25 files.
             * The existing guarded next-batch executor derives the
             * exact final size from authoritative state.
             */
            $aBatch =
                $this->continueRuntimeMergeNextBatch(
                    $iJournalId,
                    $iCurrentEvents
                );

            if (
                !is_array($aBatch)
                || empty($aBatch['ok'])
            ) {
                $aResult['error'] =
                    'Adaptive runtime merge stopped during the final protected batch: '
                    . (
                        is_array($aBatch)
                            ? (
                                $aBatch['error']
                                ?? 'unknown final-batch error'
                            )
                            : 'invalid final-batch result'
                    );

                return $aResult;
            }

            $iDone =
                (int)(
                    $aBatch['installed_count']
                    ?? 0
                );

            if (
                $iDone <= 0
                || $iDone > $iRemainingTarget
                || $iDone > $iPassiveNow
            ) {
                $aResult['error'] =
                    'Adaptive runtime merge final protected batch returned an invalid installed count.';
                return $aResult;
            }

            $iNewEvents =
                (int)(
                    $aBatch['final_ledger_events']
                    ?? -1
                );

            if ($iNewEvents <= $iCurrentEvents) {
                $aResult['error'] =
                    'Adaptive runtime merge final protected batch returned an invalid ledger checkpoint.';
                return $aResult;
            }

            $aResult['installed_count'] +=
                $iDone;

            $aResult['installed_bytes'] +=
                (int)(
                    $aBatch['installed_bytes']
                    ?? 0
                );

            $aResult['batches_completed']++;
            $aResult['partial_batches_completed']++;

            $aResult['changes_applied'] =
                true;

            $iCurrentEvents =
                $iNewEvents;
        }

        /*
         * Independent final checkpoint for the complete adaptive
         * request, regardless of whether the time ceiling was reached.
         */
        $aFinal =
            $this->getRuntimeMergeNextBatchState(
                $iJournalId
            );

        if (
            empty($aFinal['ok'])
            || (int)(
                $aFinal['event_count']
                ?? -1
            ) !== $iCurrentEvents
            || (int)(
                $aFinal['journal_event_count']
                ?? -2
            ) !== $iCurrentEvents
            || (int)(
                $aFinal['unjournaled_events']
                ?? -1
            ) !== 0
        ) {
            $aResult['error'] =
                'Adaptive runtime merge final checkpoint verification failed.';
            return $aResult;
        }

        $iInstalledFinal =
            (int)(
                $aFinal['installed_count']
                ?? -1
            );

        $iPassiveFinal =
            (int)(
                $aFinal['passive_count']
                ?? -1
            );

        if (
            $iInstalledFinal
                !== (
                    $iInstalledStart
                    + $aResult['installed_count']
                )
            || $iPassiveFinal
                !== (
                    $iPassiveStart
                    - $aResult['installed_count']
                )
            || (
                $iInstalledFinal
                + $iPassiveFinal
            ) !== $iManifestCount
        ) {
            $aResult['error'] =
                'Adaptive runtime merge final manifest progress proof failed.';
            return $aResult;
        }

        $bComplete =
            !empty($aFinal['complete']);

        $iProgressCompleted =
            $iManifestCount
            - $iPassiveFinal;

        $iNextIndex =
            $bComplete
                ? $iManifestCount
                : (int)(
                    $aFinal['next_start_index']
                    ?? -1
                );

        if (
            $iNextIndex < 0
            || $iNextIndex !== $iProgressCompleted
        ) {
            $aResult['error'] =
                'Adaptive runtime merge final manifest cursor proof failed.';
            return $aResult;
        }

        $aResult['end_index_exclusive'] =
            $iNextIndex;

        $aResult['final_ledger_events'] =
            $iCurrentEvents;

        $aResult['progress_completed'] =
            $iProgressCompleted;

        $aResult['remaining_files'] =
            $iPassiveFinal;

        $aResult['progress_percent'] =
            $iManifestCount > 0
                ? (
                    (
                        $iProgressCompleted
                        / $iManifestCount
                    ) * 100
                )
                : 0.0;

        $aResult['complete'] =
            $bComplete;

        if ($bComplete) {
            $aResult['time_limit_reached'] =
                false;

            $aResult['estimated_clicks_remaining'] =
                0;
        } else {
            $iFutureLimit =
                $bLargeModule
                    ? $iLargeLimit
                    : $iNormalLimit;

            $aResult['estimated_clicks_remaining'] =
                (int)ceil(
                    $iPassiveFinal
                    / $iFutureLimit
                );
        }

        $aResult['elapsed_seconds'] =
            microtime(true)
            - $fStarted;

        $aResult['ok'] = true;

        return $aResult;
    }


    public function continueRuntimeMerge(
        $iJournalId,
        $iStartIndex = 0,
        $iMaxFiles = 10
    ) {
        $iJournalId = (int)$iJournalId;
        $iStartIndex = (int)$iStartIndex;
        $iMaxFiles = (int)$iMaxFiles;

        $aResult = array(
            'ok' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'start_index' => $iStartIndex,
            'max_files' => $iMaxFiles,
            'guarded_gateway' => true,
            'error' => '',
        );

        /*
         * Match Doctor's existing mutation policy:
         * authenticated web POST only.
         */
        if (
            PHP_SAPI === 'cli'
            || ($_SERVER['REQUEST_METHOD'] ?? '')
                !== 'POST'
        ) {
            $aResult['error'] =
                'Runtime merge execution is allowed only from an authenticated web POST.';

            return $aResult;
        }

        if (!isAdmin()) {
            $aResult['error'] =
                'Administrator authorization is required.';

            return $aResult;
        }

        if (
            $iJournalId <= 0
            || $iStartIndex < 0
            || $iMaxFiles < 1
            || $iMaxFiles > 25
        ) {
            $aResult['error'] =
                'Runtime merge execution parameters are outside the guarded limits.';

            return $aResult;
        }

        /*
         * Independent gateway state verification.
         */
        $aJournal =
            $this->_oDb->getRow(
                "SELECT `status`, `result_json`, `error_text`
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

        $aState =
            json_decode(
                (string)(
                    $aJournal['result_json']
                    ?? ''
                ),
                true
            );

        if (
            (string)(
                $aJournal['status']
                ?? ''
            ) !== 'runtime_merging'
            || !is_array($aState)
            || (string)(
                $aState['phase']
                ?? ''
            ) !== 'runtime_merging'
            || empty(
                $aState['runtime_moved']
            )
        ) {
            $aResult['error'] =
                'Runtime merge execution requires an exact runtime_merging journal with protected runtime still moved.';

            return $aResult;
        }

        /*
         * The durable ledger must already be fully synchronized.
         * Do not silently reconcile here; recovery remains an
         * explicit separate operation.
         */
        $aLedger =
            $this->readAndVerifyRuntimeMergeLedger(
                $iJournalId
            );

        if (empty($aLedger['ok'])) {
            $aResult['error'] =
                'Runtime merge execution ledger verification failed: '
                . (
                    $aLedger['error']
                    ?? 'unknown ledger error'
                );

            return $aResult;
        }

        if (
            (int)(
                $aLedger['unjournaled_events']
                ?? -1
            ) !== 0
            || (int)(
                $aLedger['event_count']
                ?? -1
            ) !==
            (int)(
                $aLedger['journal_event_count']
                ?? -2
            )
        ) {
            $aResult['error'] =
                'Runtime merge execution refused because the durable ledger and journal are not synchronized.';

            return $aResult;
        }

        /*
         * A durable intent means recovery must occur before any
         * additional generated file is installed.
         */
        foreach (
            (
                $aLedger['entry_states']
                ?? array()
            )
            as $iIndex => $sState
        ) {
            if ((string)$sState === 'intent') {
                $aResult['error'] =
                    'Runtime merge execution refused because manifest entry '
                    . (int)$iIndex
                    . ' has an unresolved durable intent.';

                return $aResult;
            }
        }

        /*
         * Delegate mutation only after every public gateway guard
         * has independently passed.
         */
        $aBatch =
            $this->executeRuntimeMergeBatchFromSession(
                $iJournalId,
                $iStartIndex,
                $iMaxFiles
            );

        if (!is_array($aBatch)) {
            $aResult['error'] =
                'Runtime merge executor returned an invalid result.';

            return $aResult;
        }

        $aBatch['guarded_gateway'] =
            true;

        return $aBatch;
    }


    public function beginRuntimeMerge(
        $iJournalId,
        $sApprovedMergePlanId
    ) {
        $iJournalId = (int)$iJournalId;
        $sApprovedMergePlanId =
            trim((string)$sApprovedMergePlanId);

        $aResult = array(
            'ok' => false,
            'checkpointed' => false,
            'changes_applied' => false,
            'runtime_files_changed' => false,
            'journal_id' => $iJournalId,
            'merge_plan_id' =>
                $sApprovedMergePlanId,
            'error' => '',
        );

        if (
            PHP_SAPI === 'cli'
            || ($_SERVER['REQUEST_METHOD'] ?? '')
                !== 'POST'
        ) {
            $aResult['error'] =
                'Runtime merge checkpoint is allowed only from an authenticated web POST.';

            return $aResult;
        }

        if (!isAdmin()) {
            $aResult['error'] =
                'Administrator authorization is required.';

            return $aResult;
        }

        if (
            $iJournalId <= 0
            || $sApprovedMergePlanId === ''
        ) {
            $aResult['error'] =
                'A valid journal ID and approved merge plan ID are required.';

            return $aResult;
        }

        /*
         * The authoritative journal must still be exactly
         * runtime_protected before merge preparation begins.
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

        if (
            (string)$aJournal['status']
            !== 'runtime_protected'
        ) {
            $aResult['error'] =
                'Runtime merge requires a runtime-protected repair journal.';

            return $aResult;
        }

        $aProtected = json_decode(
            (string)$aJournal['result_json'],
            true
        );

        if (
            !is_array($aProtected)
            || ($aProtected['phase'] ?? '')
                !== 'runtime_protected'
            || empty($aProtected['runtime_moved'])
        ) {
            $aResult['error'] =
                'Runtime-protected journal state is incomplete or inconsistent.';

            return $aResult;
        }

        /*
         * Build the complete content-hashed execution manifest
         * from a fresh merge plan. This also proves the browser
         * approval is still current.
         */
        $aExecution =
            $this->buildRuntimeMergeExecutionManifest(
                $iJournalId,
                $sApprovedMergePlanId
            );

        if (
            empty($aExecution['ok'])
            || empty($aExecution['ready'])
        ) {
            $aResult['error'] =
                'Runtime merge execution manifest is not ready: '
                . (
                    $aExecution['error']
                    ?? 'unknown error'
                );

            if (
                !empty(
                    $aExecution[
                        'fresh_merge_plan_id'
                    ]
                )
            ) {
                $aResult['fresh_merge_plan_id'] =
                    (string)$aExecution[
                        'fresh_merge_plan_id'
                    ];
            }

            return $aResult;
        }

        /*
         * Publish the complete SHA-256 execution contract
         * beside, never inside, the protected runtime tree.
         */
        $aPersist =
            $this->persistRuntimeMergeExecutionManifest(
                $iJournalId,
                $aExecution
            );

        if (empty($aPersist['ok'])) {
            $aResult['error'] =
                'Runtime merge execution manifest could not be persisted: '
                . (
                    $aPersist['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        /*
         * A sidecar may now exist. Before changing journal state,
         * perform one final independent read-only merge preflight.
         * No runtime destination may have appeared, no baseline
         * may have changed and the control barrier must still be
         * exact.
         */
        $aFinalPlan =
            $this->getPostUpdateRuntimeMergePlan(
                $iJournalId
            );

        if (
            empty($aFinalPlan['ok'])
            || empty($aFinalPlan['ready'])
        ) {
            $aResult['error'] =
                'Final runtime merge preflight is no longer ready: '
                . (
                    $aFinalPlan['error']
                    ?? 'unknown error'
                );

            return $aResult;
        }

        $sFinalMergePlanId =
            (string)(
                $aFinalPlan['merge_plan_id']
                ?? ''
            );

        if (
            $sFinalMergePlanId === ''
            || !hash_equals(
                $sApprovedMergePlanId,
                $sFinalMergePlanId
            )
        ) {
            $aResult['error'] =
                'Runtime merge plan changed after manifest creation. No runtime files were copied.';

            $aResult['fresh_merge_plan_id'] =
                $sFinalMergePlanId;

            return $aResult;
        }

        $encode = static function ($value) {
            $json = json_encode(
                $value,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

            if ($json === false)
                throw new RuntimeException(
                    'Runtime merge journal JSON encoding failed.'
                );

            return $json;
        };

        /*
         * Durable mutation checkpoint.
         *
         * IMPORTANT:
         * No generated runtime destination has been copied yet.
         * This state exists specifically so any later filesystem
         * mutation begins from a durable, content-hashed recovery
         * contract.
         */
        $aMergingState =
            $aProtected;

        $aMergingState['phase'] =
            'runtime_merging';

        $aMergingState['changes_applied'] =
            true;

        $aMergingState['runtime_moved'] =
            true;

        $aMergingState['merge'] = array(
            'merge_plan_id' =>
                $sFinalMergePlanId,

            'execution_manifest_id' =>
                (string)(
                    $aExecution[
                        'execution_manifest_id'
                    ]
                    ?? ''
                ),

            'manifest_path' =>
                (string)(
                    $aPersist[
                        'manifest_path'
                    ]
                    ?? ''
                ),

            'manifest_file_sha256' =>
                (string)(
                    $aPersist[
                        'manifest_file_sha256'
                    ]
                    ?? ''
                ),

            'manifest_file_bytes' =>
                (int)(
                    $aPersist[
                        'manifest_file_bytes'
                    ]
                    ?? 0
                ),

            'manifest_count' =>
                (int)(
                    $aExecution[
                        'manifest_count'
                    ]
                    ?? 0
                ),

            'manifest_bytes' =>
                (int)(
                    $aExecution[
                        'manifest_bytes'
                    ]
                    ?? 0
                ),

            'protected_inventory_id' =>
                (string)(
                    $aExecution[
                        'protected_inventory_id'
                    ]
                    ?? ''
                ),

            'module_version' =>
                (string)(
                    $aExecution['version']
                    ?? ''
                ),

            'started_at' =>
                time(),

            'progress' => array(
                'phase' =>
                    'checkpointed',

                'copied_files' =>
                    0,

                'copied_bytes' =>
                    0,

                'last_file' =>
                    '',

                'runtime_files_changed' =>
                    false,
            ),
        );

        try {
            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' =>
                        'runtime_merging',

                    'result_json' =>
                        $encode(
                            $aMergingState
                        ),

                    'error_text' =>
                        '',
                )
            );
        } catch (Throwable $e) {
            /*
             * The sidecar is intentionally retained. Because it
             * is immutable and journal-specific, a retry from
             * runtime_protected can verify/reuse the exact file.
             */
            $aResult['error'] =
                'Runtime merge checkpoint could not be journaled: '
                . $e->getMessage();

            return $aResult;
        }

        /*
         * Verify more than the status. The exact manifest
         * identities must survive the durable DB write.
         */
        $aVerifyJournal =
            $this->_oDb->getRow(
                "SELECT `status`, `result_json`, `error_text`
                 FROM `my_una_doctor_repairs`
                 WHERE `id` = ?
                 LIMIT 1",
                array($iJournalId)
            );

        $aVerifyState =
            json_decode(
                (string)(
                    $aVerifyJournal[
                        'result_json'
                    ]
                    ?? ''
                ),
                true
            );

        $aVerifyMerge =
            is_array($aVerifyState)
                ? (
                    $aVerifyState['merge']
                    ?? array()
                )
                : array();

        if (
            empty($aVerifyJournal)
            || (string)(
                $aVerifyJournal['status']
                ?? ''
            ) !== 'runtime_merging'
            || !is_array($aVerifyState)
            || ($aVerifyState['phase'] ?? '')
                !== 'runtime_merging'
            || empty($aVerifyState['runtime_moved'])
            || !hash_equals(
                $sFinalMergePlanId,
                (string)(
                    $aVerifyMerge[
                        'merge_plan_id'
                    ]
                    ?? ''
                )
            )
            || !hash_equals(
                (string)(
                    $aExecution[
                        'execution_manifest_id'
                    ]
                    ?? ''
                ),
                (string)(
                    $aVerifyMerge[
                        'execution_manifest_id'
                    ]
                    ?? ''
                )
            )
            || (
                (int)(
                    $aVerifyMerge[
                        'progress'
                    ]['copied_files']
                    ?? -1
                ) !== 0
            )
            || !empty(
                $aVerifyMerge[
                    'progress'
                ]['runtime_files_changed']
            )
        ) {
            $aResult['error'] =
                'Runtime merge checkpoint journal verification failed. No runtime files were copied.';

            return $aResult;
        }

        $aResult['ok'] = true;
        $aResult['checkpointed'] = true;
        $aResult['changes_applied'] = true;
        $aResult['runtime_files_changed'] =
            false;

        $aResult['fresh_merge_plan_id'] =
            $sFinalMergePlanId;

        $aResult['execution_manifest_id'] =
            (string)(
                $aExecution[
                    'execution_manifest_id'
                ]
                ?? ''
            );

        $aResult['manifest_path'] =
            (string)(
                $aPersist['manifest_path']
                ?? ''
            );

        $aResult['manifest_file_sha256'] =
            (string)(
                $aPersist[
                    'manifest_file_sha256'
                ]
                ?? ''
            );

        $aResult['manifest_count'] =
            (int)(
                $aExecution['manifest_count']
                ?? 0
            );

        $aResult['manifest_bytes'] =
            (int)(
                $aExecution['manifest_bytes']
                ?? 0
            );

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

        /*
         * Barrier 2: template-cache compression.
         *
         * Newer armed journals preserve the verified compression
         * value. Runtime protection must carry and re-verify it
         * rather than dropping the third control-barrier state.
         */
        $bHasCompressionState =
            array_key_exists(
                'template_cache_compress_value',
                $aOriginal
            );

        $sExpectedCompression = '';

        if ($bHasCompressionState) {
            if (
                !array_key_exists(
                    'compression_value',
                    $aArmed
                )
            ) {
                $aResult['error'] =
                    'Armed template-compression control-barrier state is missing.';

                return $aResult;
            }

            $sExpectedCompression =
                (string)$aArmed[
                    'compression_value'
                ];
        }

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

            'compression_value' =>
                $bHasCompressionState
                    ? $sExpectedCompression
                    : null,

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

            if ($bHasCompressionState) {
                $sLiveCompression =
                    (string)getParam(
                        'enable_mix_template_cache'
                    );

                if (
                    $sLiveCompression
                    !== $sExpectedCompression
                ) {
                    throw new RuntimeException(
                        'Template-cache compression state changed immediately before runtime protection.'
                    );
                }
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



    /**
     * Commit a fully verified runtime merge without restoring the
     * protected original runtime tree.
     *
     * The live merged runtime remains authoritative. The protected
     * original runtime is retained as recovery evidence.
     *
     * This method performs no maintenance/compression/generator
     * restoration. It only creates the verified armed state required
     * by restoreArmedRepair().
     */
    public function commitSuccessfulRuntimeMerge(
        $iJournalId,
        $iExpectedLedgerEvents
    ) {
        $iJournalId =
            (int)$iJournalId;

        $iExpectedLedgerEvents =
            (int)$iExpectedLedgerEvents;

        $aResult = array(
            'ok' => false,
            'committed' => false,
            'changes_applied' => false,
            'journal_id' => $iJournalId,
            'error' => '',
        );

        if (
            PHP_SAPI === 'cli'
            || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        ) {
            $aResult['error'] =
                'Successful runtime-merge commit is allowed only from an authenticated web POST.';

            return $aResult;
        }

        if (!isAdmin()) {
            $aResult['error'] =
                'Administrator authorization is required.';

            return $aResult;
        }

        if (
            $iJournalId <= 0
            || $iExpectedLedgerEvents < 0
        ) {
            $aResult['error'] =
                'Runtime-merge commit parameters are invalid.';

            return $aResult;
        }

        /*
         * Fresh authoritative whole-manifest proof.
         */
        $aState =
            $this->getRuntimeMergeNextBatchState(
                $iJournalId
            );

        if (empty($aState['ok'])) {
            $aResult['error'] =
                'Runtime-merge completion could not be independently verified: '
                . (
                    $aState['error']
                    ?? 'unknown verification failure'
                );

            return $aResult;
        }

        $iEventCount =
            (int)(
                $aState['event_count']
                ?? -1
            );

        $iJournalEventCount =
            (int)(
                $aState['journal_event_count']
                ?? -2
            );

        $iInstalled =
            (int)(
                $aState['installed_count']
                ?? -1
            );

        $iPassive =
            (int)(
                $aState['passive_count']
                ?? -1
            );

        $iManifest =
            (int)(
                $aState['manifest_count']
                ?? -1
            );

        $iNextIndex =
            (int)(
                $aState['next_start_index']
                ?? -1
            );

        if (
            empty($aState['complete'])
            || (int)(
                $aState['unjournaled_events']
                ?? -1
            ) !== 0
            || $iEventCount !== $iJournalEventCount
            || $iEventCount !== $iExpectedLedgerEvents
            || $iManifest <= 0
            || $iInstalled !== $iManifest
            || $iPassive !== 0
            || $iNextIndex !== $iManifest
        ) {
            $aResult['error'] =
                'Runtime-merge commit refused because the whole-manifest state is not an exact synchronized 100% completion checkpoint.';

            return $aResult;
        }

        $aJournal =
            $this->_oDb->getRow(
                "SELECT *
                 FROM `my_una_doctor_repairs`
                 WHERE `id` = ?
                 LIMIT 1",
                array(
                    $iJournalId
                )
            );

        if (empty($aJournal)) {
            $aResult['error'] =
                'Repair journal was not found.';

            return $aResult;
        }

        $aOriginal =
            json_decode(
                (string)(
                    $aJournal[
                        'original_state_json'
                    ]
                    ?? ''
                ),
                true
            );

        $aCurrent =
            json_decode(
                (string)(
                    $aJournal[
                        'result_json'
                    ]
                    ?? ''
                ),
                true
            );

        if (
            (string)(
                $aJournal['status']
                ?? ''
            ) !== 'runtime_merging'
            || !is_array($aOriginal)
            || !is_array($aCurrent)
            || (
                $aCurrent['phase']
                ?? ''
            ) !== 'runtime_merging'
            || empty(
                $aCurrent[
                    'runtime_moved'
                ]
            )
        ) {
            $aResult['error'] =
                'Runtime-merge journal is not at the exact protected merging state required for a successful commit.';

            return $aResult;
        }

        /*
         * Barrier values that restoreArmedRepair() requires.
         */
        $sMaintenance =
            (string)(
                $aCurrent[
                    'maintenance_value'
                ]
                ?? ''
            );

        $sGeneratorSchedule =
            (string)(
                $aCurrent[
                    'generator_schedule'
                ]
                ?? ''
            );

        $iGeneratorId =
            (int)(
                $aCurrent[
                    'generator_id'
                ]
                ?? 0
            );

        $aGenerator =
            $aOriginal[
                'generator'
            ]
            ?? array();

        $iOriginalGeneratorId =
            (int)(
                $aGenerator['id']
                ?? 0
            );

        if (
            $sMaintenance === ''
            || $sGeneratorSchedule === ''
            || $iGeneratorId <= 0
            || $iOriginalGeneratorId <= 0
            || $iGeneratorId !== $iOriginalGeneratorId
        ) {
            $aResult['error'] =
                'Runtime-merge completion is missing the protected armed-state barrier data required for final restoration.';

            return $aResult;
        }

        /*
         * Independently prove the live protection barrier has not
         * changed before committing the successful runtime.
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
            !== $sMaintenance
        ) {
            $aResult['error'] =
                'Soft-maintenance state changed before successful runtime commit. Commit refused.';

            return $aResult;
        }

        $bHasCompressionState =
            array_key_exists(
                'template_cache_compress_value',
                $aOriginal
            );

        $sCompression = '';

        if ($bHasCompressionState) {
            if (
                !array_key_exists(
                    'compression_value',
                    $aCurrent
                )
            ) {
                $aResult['error'] =
                    'Protected compression state is missing from the completed runtime-merge journal.';

                return $aResult;
            }

            $sCompression =
                (string)$aCurrent[
                    'compression_value'
                ];

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
                !== $sCompression
            ) {
                $aResult['error'] =
                    'Template-cache compression state changed before successful runtime commit. Commit refused.';

                return $aResult;
            }
        }

        $aLiveCron =
            $this->_oDb->getRow(
                "SELECT *
                 FROM `sys_cron_jobs`
                 WHERE `id` = ?
                 LIMIT 1",
                array(
                    $iGeneratorId
                )
            );

        $bCronExact =
            !empty($aLiveCron)
            && (string)(
                $aLiveCron['name']
                ?? ''
            ) === (string)(
                $aGenerator['name']
                ?? ''
            )
            && (string)(
                $aLiveCron['class']
                ?? ''
            ) === (string)(
                $aGenerator['class']
                ?? ''
            )
            && (string)(
                $aLiveCron['file']
                ?? ''
            ) === (string)(
                $aGenerator['file']
                ?? ''
            )
            && (string)(
                $aLiveCron['service_call']
                ?? ''
            ) === (string)(
                $aGenerator['service_call']
                ?? ''
            )
            && (string)(
                $aLiveCron['time']
                ?? ''
            ) === $sGeneratorSchedule;

        if (!$bCronExact) {
            $aResult['error'] =
                'Generator state changed before successful runtime commit. Commit refused.';

            return $aResult;
        }

        /*
         * Preserve all durable runtime-merge evidence.
         *
         * runtime_moved remains true here because the protected old
         * runtime is deliberately retained. restoreArmedRepair()
         * does not restore or remove that tree.
         */
        $aArmed =
            $aCurrent;

        $aArmed['phase'] =
            'armed';

        $aArmed['changes_applied'] =
            true;

        $aArmed['runtime_moved'] =
            true;

        $aArmed[
            'successful_runtime_merge'
        ] = array(
            'committed' =>
                true,

            'committed_at' =>
                time(),

            'manifest_count' =>
                $iManifest,

            'installed_count' =>
                $iInstalled,

            'ledger_events' =>
                $iEventCount,

            'protected_runtime_preserved' =>
                true,
        );

        $encode =
            static function ($value) {
                $json =
                    json_encode(
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
            $this->requireRepairJournalUpdate(
                $iJournalId,
                array(
                    'status' =>
                        'armed',

                    'result_json' =>
                        $encode(
                            $aArmed
                        ),

                    'error_text' =>
                        '',
                )
            );
        } catch (Throwable $e) {
            $aResult['error'] =
                'Successful runtime merge was verified but the journal could not be committed to armed state: '
                . $e->getMessage();

            return $aResult;
        }

        /*
         * Independent DB reread. Do not trust the preceding write.
         */
        $aVerify =
            $this->_oDb->getRow(
                "SELECT `status`, `result_json`
                 FROM `my_una_doctor_repairs`
                 WHERE `id` = ?
                 LIMIT 1",
                array(
                    $iJournalId
                )
            );

        $aVerifyState =
            json_decode(
                (string)(
                    $aVerify[
                        'result_json'
                    ]
                    ?? ''
                ),
                true
            );

        if (
            empty($aVerify)
            || (string)(
                $aVerify['status']
                ?? ''
            ) !== 'armed'
            || !is_array(
                $aVerifyState
            )
            || (
                $aVerifyState['phase']
                ?? ''
            ) !== 'armed'
            || empty(
                $aVerifyState[
                    'successful_runtime_merge'
                ]['committed']
            )
            || (int)(
                $aVerifyState[
                    'successful_runtime_merge'
                ]['manifest_count']
                ?? -1
            ) !== $iManifest
            || (int)(
                $aVerifyState[
                    'successful_runtime_merge'
                ]['ledger_events']
                ?? -1
            ) !== $iEventCount
        ) {
            $aResult['error'] =
                'Successful runtime-merge commit could not be independently verified after the journal transition.';

            return $aResult;
        }

        $aResult['ok'] =
            true;

        $aResult['committed'] =
            true;

        $aResult['changes_applied'] =
            true;

        $aResult['manifest_count'] =
            $iManifest;

        $aResult['ledger_events'] =
            $iEventCount;

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


        /*
         * Runtime restore compression barrier.
         *
         * New runtime-protection journals carry the verified
         * template-cache compression value. Preserve backward
         * compatibility with older protected journals that
         * predate this field.
         */
        $bHasProtectedCompression =
            array_key_exists(
                'compression_value',
                $aProtected
            );

        $sExpectedCompression =
            $bHasProtectedCompression
                ? (string)$aProtected[
                    'compression_value'
                ]
                : '';

        if ($bHasProtectedCompression) {
            $sLiveCompression =
                (string)getParam(
                    'enable_mix_template_cache'
                );

            if (
                $sLiveCompression
                !== $sExpectedCompression
            ) {
                $aResult['error'] =
                    'Template-cache compression control barrier no longer matches the protected journal.';

                return $aResult;
            }
        }

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

            if ($bHasProtectedCompression) {
                $aRecoveredArmed[
                    'compression_value'
                ] =
                    $sExpectedCompression;
            }

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

            if ($bHasProtectedCompression) {
                $aRecoveredArmed[
                    'compression_value'
                ] =
                    $sExpectedCompression;
            }

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

            if ($bHasProtectedCompression) {
                $aArmedResult[
                    'compression_value'
                ] =
                    $sExpectedCompression;
            }

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

        $aPlannedState = json_decode(
            (string)$aJournal['planned_state_json'],
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
                array_key_exists(
                    'compression_value',
                    $aArmedResult
                )
            ) {
                $sExpectedArmedCompression =
                    (string)$aArmedResult[
                        'compression_value'
                    ];
            } else {
                /*
                 * Legacy armed-journal compression recovery.
                 *
                 * Older runtime restore code could return an
                 * otherwise valid journal to armed without
                 * carrying compression_value forward.
                 *
                 * Never infer the missing value from the original
                 * setting. Require the durable planned target and
                 * independently verify that the live option still
                 * equals that target.
                 */
                if (
                    !is_array($aPlannedState)
                    || !array_key_exists(
                        'template_cache_compress_target',
                        $aPlannedState
                    )
                ) {
                    $aResult['error'] =
                        'Journaled Barrier 2 compression state is missing and no verified planned compression target is available.';

                    return $aResult;
                }

                $sPlannedCompression =
                    (string)$aPlannedState[
                        'template_cache_compress_target'
                    ];

                $sLiveLegacyCompression =
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
                    $sLiveLegacyCompression
                    !== $sPlannedCompression
                ) {
                    $aResult['error'] =
                        'Legacy armed journal is missing its compression state and the live compression value does not match the approved planned target. Restore refused.';

                    return $aResult;
                }

                $sExpectedArmedCompression =
                    $sPlannedCompression;
            }
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

            if (
                !empty(
                    $aArmedResult[
                        'successful_runtime_merge'
                    ]
                )
                && is_array(
                    $aArmedResult[
                        'successful_runtime_merge'
                    ]
                )
            ) {
                $aRestoredResult[
                    'successful_runtime_merge'
                ] =
                    $aArmedResult[
                        'successful_runtime_merge'
                    ];

                $aRestoredResult[
                    'successful_runtime_merge'
                ][
                    'site_state_restored'
                ] =
                    true;

                $aRestoredResult[
                    'successful_runtime_merge'
                ][
                    'site_state_restored_at'
                ] =
                    time();
            }


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
                   'runtime_merging',
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

        $iDoctorMaintenancePageOptions =
            (int)$this->_oDb->getOne(
                "SELECT COUNT(*)
                 FROM `sys_options`
                 WHERE `name` = ?",
                array(
                    'my_una_doctor_use_maintenance_page'
                )
            );

        $bUseDoctorMaintenancePage =
            $iDoctorMaintenancePageOptions === 0
            || (string)getParam(
                'my_una_doctor_use_maintenance_page'
            ) === 'on';

        $bDoctorMaintenanceLanding =
            $bUseDoctorMaintenancePage
            && $sOriginalMaintenance === '';

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
