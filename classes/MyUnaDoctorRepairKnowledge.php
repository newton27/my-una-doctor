<?php defined('BX_DOL') or die('hack attempt');

class MyUnaDoctorRepairKnowledge
{
    const TABLE = 'my_una_doctor_repair_knowledge';

    protected $oModule;
    protected $oDb;

    public function __construct($oModule)
    {
        $this->oModule = $oModule;
        $this->oDb = BxDolDb::getInstance();
    }

    public function save(array $aData)
    {
        try {
            return $this->doSave($aData);
        } catch (Throwable $e) {
            return array(
                'ok' => false,
                'saved' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    public function findExact(
        $sModule,
        $sVersion,
        $sFile,
        $sReferenceMd5,
        $sLiveMd5
    ) {
        if (!$this->tableExists())
            return array();

        $sModule = trim((string)$sModule);
        $sVersion = trim((string)$sVersion);
        $sFile = $this->normalizeFile($sFile);

        $sReferenceMd5 =
            strtolower(trim((string)$sReferenceMd5));

        $sLiveMd5 =
            strtolower(trim((string)$sLiveMd5));

        if (
            $sModule === ''
            || $sVersion === ''
            || $sFile === ''
            || !$this->validMd5($sReferenceMd5)
            || !$this->validMd5($sLiveMd5)
        ) {
            return array();
        }

        $aRow = $this->oDb->getRow(
            "SELECT *
             FROM `" . self::TABLE . "`
             WHERE `module` = ?
               AND `module_version` = ?
               AND `file` = ?
               AND `reference_md5` = ?
               AND `approved_live_md5` = ?
               AND `enabled` = 1
             ORDER BY `updated` DESC, `id` DESC
             LIMIT 1",
            array(
                $sModule,
                $sVersion,
                $sFile,
                $sReferenceMd5,
                $sLiveMd5,
            )
        );

        if (!is_array($aRow) || empty($aRow))
            return array();

        return array(
            'verified' => true,
            'classification' =>
                'verified_local_repair_knowledge',
            'id' => (int)$aRow['id'],
            'knowledge_key' =>
                (string)$aRow['knowledge_key'],
            'module' =>
                (string)$aRow['module'],
            'module_version' =>
                (string)$aRow['module_version'],
            'file' =>
                (string)$aRow['file'],
            'reference_md5' =>
                (string)$aRow['reference_md5'],
            'approved_live_md5' =>
                (string)$aRow['approved_live_md5'],
            'repair_type' =>
                (string)$aRow['repair_type'],
            'reason' =>
                (string)$aRow['reason'],
            'reference_sha256' =>
                (string)$aRow['reference_sha256'],
            'reference_filename' =>
                (string)$aRow['reference_filename'],
            'created' =>
                (int)$aRow['created'],
            'updated' =>
                (int)$aRow['updated'],
        );
    }

    public function getAll($sModule = '')
    {
        if (!$this->tableExists())
            return array();

        $sModule = trim((string)$sModule);

        if ($sModule !== '') {
            return $this->oDb->getAll(
                "SELECT *
                 FROM `" . self::TABLE . "`
                 WHERE `module` = ?
                 ORDER BY `updated` DESC, `file` ASC",
                array($sModule)
            );
        }

        return $this->oDb->getAll(
            "SELECT *
             FROM `" . self::TABLE . "`
             ORDER BY `updated` DESC, `module` ASC, `file` ASC"
        );
    }

    public function getAllWithStatus($sModule = '')
    {
        $aRows =
            $this->getAll(
                (string)$sModule
            );

        foreach ($aRows as &$aRow) {
            $aRow =
                $this->inspectKnowledgeStatus(
                    $aRow
                );
        }

        unset($aRow);

        return $aRows;
    }


    public function setEnabled($iId, $bEnabled)
    {
        try {
            if (!$this->tableExists()) {
                throw new RuntimeException(
                    'Local Repair Knowledge table is missing.'
                );
            }

            $iId = (int)$iId;

            if ($iId <= 0) {
                throw new RuntimeException(
                    'Invalid Local Repair Knowledge record ID.'
                );
            }

            $aRow = $this->oDb->getRow(
                "SELECT `id`, `module`, `file`
                 FROM `" . self::TABLE . "`
                 WHERE `id` = ?
                 LIMIT 1",
                array($iId)
            );

            if (!is_array($aRow) || empty($aRow)) {
                throw new RuntimeException(
                    'The Local Repair Knowledge record was not found.'
                );
            }

            $iEnabled =
                $bEnabled ? 1 : 0;

            $this->oDb->query(
                "UPDATE `" . self::TABLE . "`
                 SET `enabled` = ?,
                     `updated` = ?
                 WHERE `id` = ?",
                array(
                    $iEnabled,
                    time(),
                    $iId,
                )
            );

            return array(
                'ok' => true,
                'knowledge_changed' => true,
                'id' => $iId,
                'enabled' => $iEnabled,
                'module' =>
                    (string)$aRow['module'],
                'file' =>
                    (string)$aRow['file'],
                'message' =>
                    $iEnabled
                        ? 'Local Repair Knowledge record enabled.'
                        : 'Local Repair Knowledge record disabled.',
            );

        } catch (Throwable $e) {
            return array(
                'ok' => false,
                'knowledge_changed' => false,
                'error' =>
                    $e->getMessage(),
            );
        }
    }


    public function deleteRecord($iId)
    {
        try {
            if (!$this->tableExists()) {
                throw new RuntimeException(
                    'Local Repair Knowledge table is missing.'
                );
            }

            $iId = (int)$iId;

            if ($iId <= 0) {
                throw new RuntimeException(
                    'Invalid Local Repair Knowledge record ID.'
                );
            }

            $aRow = $this->oDb->getRow(
                "SELECT `id`, `module`, `file`
                 FROM `" . self::TABLE . "`
                 WHERE `id` = ?
                 LIMIT 1",
                array($iId)
            );

            if (!is_array($aRow) || empty($aRow)) {
                throw new RuntimeException(
                    'The Local Repair Knowledge record was not found.'
                );
            }

            $this->oDb->query(
                "DELETE FROM `" . self::TABLE . "`
                 WHERE `id` = ?",
                array($iId)
            );

            return array(
                'ok' => true,
                'knowledge_changed' => true,
                'deleted' => true,
                'id' => $iId,
                'module' =>
                    (string)$aRow['module'],
                'file' =>
                    (string)$aRow['file'],
                'message' =>
                    'Local Repair Knowledge record deleted.',
            );

        } catch (Throwable $e) {
            return array(
                'ok' => false,
                'knowledge_changed' => false,
                'error' =>
                    $e->getMessage(),
            );
        }
    }


    protected function inspectKnowledgeStatus(
        array $aRow
    ) {
        $aRow['verification_status'] =
            'unverified';

        $aRow['verification_label'] =
            'UNVERIFIED';

        $aRow['current_live_md5'] =
            '';

        $aRow['comparison_session_id'] =
            '';

        $sEvidence =
            (string)(
                $aRow['evidence_json']
                ?? ''
            );

        if ($sEvidence !== '') {
            $aEvidence =
                json_decode(
                    $sEvidence,
                    true
                );

            if (is_array($aEvidence)) {
                $aRow['comparison_session_id'] =
                    (string)(
                        $aEvidence[
                            'comparison_session_id'
                        ] ?? ''
                    );
            }
        }

        if (
            empty($aRow['enabled'])
        ) {
            $aRow['verification_status'] =
                'disabled';

            $aRow['verification_label'] =
                'DISABLED';

            return $aRow;
        }

        $sModule =
            (string)(
                $aRow['module']
                ?? ''
            );

        $aModule =
            $this->oDb->getRow(
                "SELECT `name`, `version`, `path`
                 FROM `sys_modules`
                 WHERE `name` = ?
                 LIMIT 1",
                array($sModule)
            );

        if (!is_array($aModule) || empty($aModule)) {
            $aRow['verification_status'] =
                'module_missing';

            $aRow['verification_label'] =
                'MODULE MISSING';

            return $aRow;
        }

        if (
            (string)$aModule['version']
            !==
            (string)(
                $aRow['module_version']
                ?? ''
            )
        ) {
            $aRow['verification_status'] =
                'version_mismatch';

            $aRow['verification_label'] =
                'VERSION CHANGED';

            return $aRow;
        }

        $sFile =
            str_replace(
                '\\',
                '/',
                (string)(
                    $aRow['file']
                    ?? ''
                )
            );

        $sLivePath =
            rtrim(
                BX_DIRECTORY_PATH_MODULES,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR
            . trim(
                (string)$aModule['path'],
                '/\\'
            )
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $sFile
            );

        if (!is_file($sLivePath)) {
            $aRow['verification_status'] =
                'file_missing';

            $aRow['verification_label'] =
                'FILE MISSING';

            return $aRow;
        }

        $sCurrent =
            strtolower(
                (string)md5_file(
                    $sLivePath
                )
            );

        $aRow['current_live_md5'] =
            $sCurrent;

        $sApproved =
            strtolower(
                (string)(
                    $aRow[
                        'approved_live_md5'
                    ] ?? ''
                )
            );

        if (
            preg_match(
                '/^[a-f0-9]{32}$/',
                $sApproved
            )
            && preg_match(
                '/^[a-f0-9]{32}$/',
                $sCurrent
            )
            && hash_equals(
                $sApproved,
                $sCurrent
            )
        ) {
            $aRow['verification_status'] =
                'verified';

            $aRow['verification_label'] =
                'VERIFIED';

            return $aRow;
        }

        $aRow['verification_status'] =
            'stale';

        $aRow['verification_label'] =
            'STALE';

        return $aRow;
    }


    protected function doSave(array $aData)
    {
        if (!$this->tableExists())
            throw new RuntimeException(
                'Local Repair Knowledge table is missing.'
            );

        $sModule =
            trim((string)($aData['module'] ?? ''));

        $sVersion =
            trim((string)($aData['module_version'] ?? ''));

        $sFile =
            $this->normalizeFile(
                $aData['file'] ?? ''
            );

        $sReferenceMd5 =
            strtolower(
                trim(
                    (string)(
                        $aData['reference_md5']
                        ?? ''
                    )
                )
            );

        $sLiveMd5 =
            strtolower(
                trim(
                    (string)(
                        $aData['approved_live_md5']
                        ?? ''
                    )
                )
            );

        $sType =
            trim(
                (string)(
                    $aData['repair_type']
                    ?? 'local_fix'
                )
            );

        $sReason =
            trim(
                (string)(
                    $aData['reason']
                    ?? ''
                )
            );

        $sReferenceSha =
            strtolower(
                trim(
                    (string)(
                        $aData['reference_sha256']
                        ?? ''
                    )
                )
            );

        $sReferenceFilename =
            basename(
                (string)(
                    $aData['reference_filename']
                    ?? ''
                )
            );

        $sComparisonSession =
            trim(
                (string)(
                    $aData['comparison_session_id']
                    ?? ''
                )
            );

        if ($sModule === '')
            throw new RuntimeException(
                'Module name is required.'
            );

        if ($sVersion === '')
            throw new RuntimeException(
                'Module version is required.'
            );

        if ($sFile === '')
            throw new RuntimeException(
                'Module file is required.'
            );

        if (!$this->validMd5($sReferenceMd5))
            throw new RuntimeException(
                'Reference MD5 is invalid.'
            );

        if (!$this->validMd5($sLiveMd5))
            throw new RuntimeException(
                'Approved live MD5 is invalid.'
            );

        if (
            $sReferenceSha !== ''
            && !preg_match(
                '/^[a-f0-9]{64}$/',
                $sReferenceSha
            )
        ) {
            throw new RuntimeException(
                'Reference SHA256 is invalid.'
            );
        }

        if ($sReason === '')
            throw new RuntimeException(
                'A repair reason is required.'
            );

        if ($sComparisonSession === '')
            throw new RuntimeException(
                'The preserved comparison session is required before local repair knowledge can be saved.'
            );

        $aTypes = array(
            'local_fix',
            'una_compatibility',
            'php_compatibility',
            'namespace_compatibility',
            'vendor_hotfix',
            'other',
        );

        if (!in_array($sType, $aTypes, true))
            $sType = 'other';

        $aModule = $this->oDb->getRow(
            "SELECT `name`, `version`, `path`
             FROM `sys_modules`
             WHERE `name` = ?
             LIMIT 1",
            array($sModule)
        );

        if (!is_array($aModule) || empty($aModule))
            throw new RuntimeException(
                'The module is not currently registered in UNA.'
            );

        if (
            trim((string)$aModule['version'])
            !== $sVersion
        ) {
            throw new RuntimeException(
                'The supplied module version does not match the currently registered module version.'
            );
        }

        $sRoot =
            rtrim(
                BX_DIRECTORY_PATH_MODULES,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR
            . trim(
                (string)$aModule['path'],
                '/\\'
            )
            . DIRECTORY_SEPARATOR;

        $sLivePath =
            $sRoot
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $sFile
            );

        if (!is_file($sLivePath))
            throw new RuntimeException(
                'The live module file does not exist.'
            );

        $sCurrentMd5 =
            strtolower(
                (string)md5_file($sLivePath)
            );

        if (!hash_equals(
            $sLiveMd5,
            $sCurrentMd5
        )) {
            throw new RuntimeException(
                'The live file hash changed before the knowledge record could be saved.'
            );
        }

        $aComparisonEvidence =
            $this->verifyComparisonEvidence(
                $sComparisonSession,
                $sModule,
                $sVersion,
                $sFile,
                $sReferenceMd5,
                $sLiveMd5,
                $sReferenceSha,
                $sReferenceFilename
            );

        if (empty($aComparisonEvidence['verified'])) {
            throw new RuntimeException(
                'The proposed knowledge record does not match its preserved Reference Package comparison evidence.'
            );
        }

        $sKnowledgeKey = hash(
            'sha256',
            implode(
                "\n",
                array(
                    $sModule,
                    $sVersion,
                    $sFile,
                    $sReferenceMd5,
                    $sLiveMd5,
                )
            )
        );

        $iNow = time();

        $aEvidence = array(
            'verified_at_save' => true,
            'registered_module_version' =>
                (string)$aModule['version'],
            'live_file_md5' => $sCurrentMd5,
            'comparison_session_id' =>
                $sComparisonSession,
            'comparison_evidence' =>
                $aComparisonEvidence,
        );

        $sEvidence =
            json_encode(
                $aEvidence,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
            );

        if (!is_string($sEvidence))
            $sEvidence = '{}';

        $iExisting = (int)$this->oDb->getOne(
            "SELECT `id`
             FROM `" . self::TABLE . "`
             WHERE `knowledge_key` = ?
             LIMIT 1",
            array($sKnowledgeKey)
        );

        if ($iExisting > 0) {
            $this->oDb->query(
                "UPDATE `" . self::TABLE . "`
                 SET `repair_type` = ?,
                     `reason` = ?,
                     `reference_sha256` = ?,
                     `reference_filename` = ?,
                     `evidence_json` = ?,
                     `enabled` = 1,
                     `updated` = ?
                 WHERE `id` = ?",
                array(
                    $sType,
                    $sReason,
                    $sReferenceSha,
                    $sReferenceFilename,
                    $sEvidence,
                    $iNow,
                    $iExisting,
                )
            );

            $iId = $iExisting;
        } else {
            $this->oDb->query(
                "INSERT INTO `" . self::TABLE . "`
                (
                    `knowledge_key`,
                    `module`,
                    `module_version`,
                    `file`,
                    `reference_md5`,
                    `approved_live_md5`,
                    `repair_type`,
                    `reason`,
                    `reference_sha256`,
                    `reference_filename`,
                    `evidence_json`,
                    `enabled`,
                    `created`,
                    `updated`
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)",
                array(
                    $sKnowledgeKey,
                    $sModule,
                    $sVersion,
                    $sFile,
                    $sReferenceMd5,
                    $sLiveMd5,
                    $sType,
                    $sReason,
                    $sReferenceSha,
                    $sReferenceFilename,
                    $sEvidence,
                    $iNow,
                    $iNow,
                )
            );

            $iId = (int)$this->oDb->lastId();
        }

        return array(
            'ok' => true,
            'saved' => true,
            'changes_applied' => true,
            'id' => $iId,
            'knowledge_key' => $sKnowledgeKey,
            'module' => $sModule,
            'module_version' => $sVersion,
            'file' => $sFile,
            'reference_md5' => $sReferenceMd5,
            'approved_live_md5' => $sLiveMd5,
            'repair_type' => $sType,
            'reason' => $sReason,
        );
    }

    protected function verifyComparisonEvidence(
        $sSession,
        $sModule,
        $sVersion,
        $sFile,
        $sReferenceMd5,
        $sLiveMd5,
        $sReferenceSha256,
        $sReferenceFilename
    ) {
        if (
            !preg_match(
                '/^[0-9]{8}-[0-9]{6}-[a-f0-9]{12}$/',
                (string)$sSession
            )
        ) {
            return array();
        }

        $sRoot =
            rtrim(
                BX_DIRECTORY_PATH_ROOT,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR
            . 'tmp'
            . DIRECTORY_SEPARATOR
            . 'my-una-doctor-reference-packages'
            . DIRECTORY_SEPARATOR;

        $sReport =
            $sRoot
            . $sSession
            . DIRECTORY_SEPARATOR
            . 'comparison.json';

        if (!is_file($sReport))
            return array();

        $sJson =
            @file_get_contents($sReport);

        if (!is_string($sJson) || $sJson === '')
            return array();

        $aReport =
            json_decode($sJson, true);

        if (!is_array($aReport))
            return array();

        /*
         * Doctor comparison reports currently store the result
         * at the JSON root. Keep support for a nested result
         * wrapper as well.
         */
        $aResult =
            isset($aReport['result'])
            && is_array($aReport['result'])
                ? $aReport['result']
                : $aReport;

        $aInstalled =
            isset($aResult['installed'])
            && is_array($aResult['installed'])
                ? $aResult['installed']
                : array();

        $aPackage =
            isset($aResult['package'])
            && is_array($aResult['package'])
                ? $aResult['package']
                : array();

        if (
            (string)($aInstalled['name'] ?? '')
                !== (string)$sModule
            || (string)(
                $aInstalled['registered_version']
                ?? ''
            ) !== (string)$sVersion
        ) {
            return array();
        }

        $sReportSha =
            strtolower(
                (string)(
                    $aPackage['sha256']
                    ?? ''
                )
            );

        $sReportFilename =
            basename(
                (string)(
                    $aPackage['original_filename']
                    ?? ''
                )
            );

        if (
            $sReferenceSha256 !== ''
            && $sReportSha !==
                strtolower(
                    (string)$sReferenceSha256
                )
        ) {
            return array();
        }

        if (
            $sReferenceFilename !== ''
            && $sReportFilename !==
                basename(
                    (string)$sReferenceFilename
                )
        ) {
            return array();
        }

        $aComparison =
            isset($aReport['full_comparison'])
            && is_array(
                $aReport['full_comparison']
            )
                ? $aReport['full_comparison']
                : array();

        $aModified =
            isset($aComparison['modified'])
            && is_array($aComparison['modified'])
                ? $aComparison['modified']
                : array();

        foreach ($aModified as $aModifiedFile) {
            if (
                (string)(
                    $aModifiedFile['file']
                    ?? ''
                ) === (string)$sFile
                && strtolower(
                    (string)(
                        $aModifiedFile[
                            'reference_md5'
                        ] ?? ''
                    )
                ) === strtolower(
                    (string)$sReferenceMd5
                )
                && strtolower(
                    (string)(
                        $aModifiedFile[
                            'live_md5'
                        ] ?? ''
                    )
                ) === strtolower(
                    (string)$sLiveMd5
                )
            ) {
                return array(
                    'verified' => true,
                    'session_id' =>
                        (string)$sSession,
                    'reference_filename' =>
                        $sReportFilename,
                    'reference_sha256' =>
                        $sReportSha,
                    'comparison_report' =>
                        $sReport,
                    'modified_file_verified' =>
                        true,
                );
            }
        }

        return array();
    }


    protected function normalizeFile($sFile)
    {
        $sFile =
            str_replace(
                '\\',
                '/',
                trim((string)$sFile)
            );

        if (
            $sFile === ''
            || substr($sFile, 0, 1) === '/'
            || preg_match(
                '/^[A-Za-z]:\//',
                $sFile
            )
        ) {
            return '';
        }

        foreach (
            explode('/', $sFile)
            as $sPart
        ) {
            if ($sPart === '..')
                return '';
        }

        while (strpos($sFile, './') === 0)
            $sFile = substr($sFile, 2);

        return $sFile;
    }

    protected function validMd5($sHash)
    {
        return (bool)preg_match(
            '/^[a-f0-9]{32}$/',
            (string)$sHash
        );
    }

    protected function tableExists()
    {
        return (int)$this->oDb->getOne(
            "SELECT COUNT(*)
             FROM `INFORMATION_SCHEMA`.`TABLES`
             WHERE `TABLE_SCHEMA` = DATABASE()
               AND `TABLE_NAME` = ?",
            array(self::TABLE)
        ) === 1;
    }
}
