<?php defined('BX_DOL') or die('hack attempt');

/**
 * My UNA Doctor
 *
 * Persistent Module Reference Library.
 *
 * This class is deliberately read-only in its first stage.
 * It verifies catalog records and immutable reference ZIPs,
 * but does not install, update, repair, delete, move, replace
 * or re-baseline any UNA module file.
 */
class MyUnaDoctorReferenceLibrary
{
    protected $oModule;
    protected $oDb;

    const TABLE =
        'my_una_doctor_reference_library';

    const STORAGE_PREFIX =
        'storage/my_una_doctor/reference-library/';

    const MANIFEST_SCHEMA =
        'my_una_doctor_reference_library_item_v1';


    public function __construct($oModule)
    {
        $this->oModule = $oModule;
        $this->oDb = BxDolDb::getInstance();
    }


    public function getAll()
    {
        if (!$this->tableExists())
            return array();

        $aRows = $this->oDb->getAll(
            "SELECT *
             FROM `" . self::TABLE . "`
             ORDER BY
                `module_title`,
                `module_name`,
                `reference_version`,
                `id`"
        );

        $aResult = array();

        foreach ((array)$aRows as $aRow)
            $aResult[] =
                $this->verifyRow($aRow);

        return $aResult;
    }


    public function getBySha256($sSha256)
    {
        $sSha256 =
            strtolower(
                trim((string)$sSha256)
            );

        if (
            strlen($sSha256) !== 64
            || !ctype_xdigit($sSha256)
            || !$this->tableExists()
        ) {
            return array();
        }

        $aRow = $this->oDb->getRow(
            "SELECT *
             FROM `" . self::TABLE . "`
             WHERE `reference_sha256` = ?
             LIMIT 1",
            array($sSha256)
        );

        if (!is_array($aRow) || empty($aRow))
            return array();

        return $this->verifyRow($aRow);
    }


    public function getSummary()
    {
        $aRows = $this->getAll();

        $aSummary = array(
            'total' => count($aRows),
            'verified' => 0,
            'failed' => 0,
            'modules' => array(),
        );

        foreach ($aRows as $aRow) {
            if (!empty($aRow['library_verified']))
                $aSummary['verified']++;
            else
                $aSummary['failed']++;

            $sModule =
                (string)(
                    $aRow['module_name']
                    ?? ''
                );

            if ($sModule !== '')
                $aSummary['modules'][$sModule] = true;
        }

        $aSummary['module_count'] =
            count($aSummary['modules']);

        $aSummary['modules'] =
            array_keys(
                $aSummary['modules']
            );

        sort($aSummary['modules']);

        return $aSummary;
    }



    /**
     * Record one successful re-comparison of an immutable
     * saved reference.
     *
     * This changes library metadata only. The durable ZIP and
     * immutable manifest are never rewritten.
     */

    /**
     * Promote a successful fresh reference comparison into
     * the persistent Module Reference Library.
     *
     * SHA-256 is the immutable identity. Existing references
     * are never overwritten; duplicate uploads append history.
     */
    public function promoteComparisonResult(
        array $aResult
    ) {
        try {
            return $this->doPromoteComparisonResult(
                $aResult
            );
        } catch (Throwable $e) {
            return array(
                'ok' => false,
                'saved' => false,
                'new_reference' => false,
                'duplicate_reference' => false,
                'history_recorded' => false,
                'error' => $e->getMessage(),
            );
        }
    }


    protected function doPromoteComparisonResult(
        array $aResult
    ) {
        if (!$this->tableExists())
            throw new RuntimeException(
                'The Module Reference Library catalog is unavailable.'
            );

        if (empty($aResult['ok']))
            throw new RuntimeException(
                'Only successful comparisons can be promoted.'
            );

        $aPackage =
            isset($aResult['package'])
            && is_array($aResult['package'])
                ? $aResult['package']
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

        $sSha256 =
            strtolower(
                trim(
                    (string)(
                        $aPackage['sha256']
                        ?? ''
                    )
                )
            );

        if (
            strlen($sSha256) !== 64
            || !ctype_xdigit($sSha256)
        ) {
            throw new RuntimeException(
                'The compared package has an invalid SHA-256 identity.'
            );
        }

        $sSessionId =
            trim(
                (string)(
                    $aEvidence['session_id']
                    ?? ''
                )
            );

        if (
            !preg_match(
                '/^[0-9]{8}-[0-9]{6}-[a-f0-9]{12}$/',
                $sSessionId
            )
        ) {
            throw new RuntimeException(
                'The comparison evidence session identity is invalid.'
            );
        }

        $sSourceZip =
            (string)(
                $aEvidence['reference_zip']
                ?? ''
            );

        $sReport =
            (string)(
                $aEvidence['comparison_report']
                ?? ''
            );

        if (
            !is_file($sSourceZip)
            || is_link($sSourceZip)
        ) {
            throw new RuntimeException(
                'The preserved comparison ZIP is missing or unsafe.'
            );
        }

        if (
            !is_file($sReport)
            || is_link($sReport)
        ) {
            throw new RuntimeException(
                'The preserved comparison report is missing or unsafe.'
            );
        }

        $sEvidenceRoot =
            rtrim(
                BX_DIRECTORY_PATH_ROOT,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR
            . 'tmp'
            . DIRECTORY_SEPARATOR
            . 'my-una-doctor-reference-packages';

        $sEvidenceRootReal =
            realpath($sEvidenceRoot);

        $sZipReal =
            realpath($sSourceZip);

        $sReportReal =
            realpath($sReport);

        if (
            $sEvidenceRootReal === false
            || $sZipReal === false
            || $sReportReal === false
        ) {
            throw new RuntimeException(
                'Doctor could not resolve the comparison evidence.'
            );
        }

        $sSessionRoot =
            $sEvidenceRootReal
            . DIRECTORY_SEPARATOR
            . $sSessionId
            . DIRECTORY_SEPARATOR;

        if (
            strpos(
                $sZipReal,
                $sSessionRoot
            ) !== 0
            || strpos(
                $sReportReal,
                $sSessionRoot
            ) !== 0
        ) {
            throw new RuntimeException(
                'The comparison evidence resolves outside its recorded session.'
            );
        }

        $sActualSha256 =
            strtolower(
                (string)hash_file(
                    'sha256',
                    $sZipReal
                )
            );

        if (
            strlen($sActualSha256) !== 64
            || !hash_equals(
                $sSha256,
                $sActualSha256
            )
        ) {
            throw new RuntimeException(
                'The preserved comparison ZIP failed SHA-256 verification.'
            );
        }

        $sBaselineAuthenticity =
            trim(
                (string)(
                    $aBaseline['authenticity']
                    ?? ''
                )
            );

        /*
         * Existing immutable identity:
         * verify it, then append history only.
         */
        $aExisting =
            $this->oDb->getRow(
                "SELECT *
                 FROM `" . self::TABLE . "`
                 WHERE `reference_sha256` = ?
                 LIMIT 1",
                array($sSha256)
            );

        if (
            is_array($aExisting)
            && !empty($aExisting)
        ) {
            $aVerified =
                $this->getBySha256(
                    $sSha256
                );

            if (
                empty($aVerified)
                || empty(
                    $aVerified['library_verified']
                )
            ) {
                throw new RuntimeException(
                    'This reference already exists, but its immutable evidence no longer verifies. Doctor will not overwrite it.'
                );
            }

            $bRecorded =
                $this->recordComparison(
                    $sSha256,
                    $sSessionId,
                    $sBaselineAuthenticity
                );

            if (!$bRecorded)
                throw new RuntimeException(
                    'Doctor could not append comparison history for the existing reference.'
                );

            return array(
                'ok' => true,
                'saved' => true,
                'new_reference' => false,
                'duplicate_reference' => true,
                'history_recorded' => true,
                'reference_sha256' =>
                    $sSha256,
                'session_id' =>
                    $sSessionId,
            );
        }

        /*
         * New immutable identity.
         */
        $sLibraryRoot =
            rtrim(
                BX_DIRECTORY_PATH_ROOT,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                rtrim(
                    self::STORAGE_PREFIX,
                    '/'
                )
            );

        if (
            !is_dir($sLibraryRoot)
            && !@mkdir(
                $sLibraryRoot,
                0700,
                true
            )
        ) {
            throw new RuntimeException(
                'Doctor could not create protected Reference Library storage.'
            );
        }

        if (
            !is_dir($sLibraryRoot)
            || is_link($sLibraryRoot)
        ) {
            throw new RuntimeException(
                'The Reference Library storage root is unsafe.'
            );
        }

        @chmod(
            $sLibraryRoot,
            0700
        );

        $sItemDir =
            $sLibraryRoot
            . DIRECTORY_SEPARATOR
            . $sSha256;

        if (file_exists($sItemDir))
            throw new RuntimeException(
                'An unindexed Reference Library directory already exists for this SHA-256. Manual review is required.'
            );

        if (!@mkdir(
            $sItemDir,
            0700
        )) {
            throw new RuntimeException(
                'Doctor could not create the Reference Library item directory.'
            );
        }

        @chmod(
            $sItemDir,
            0700
        );

        $sTargetZip =
            $sItemDir
            . DIRECTORY_SEPARATOR
            . 'reference.zip';

        $sManifest =
            $sItemDir
            . DIRECTORY_SEPARATOR
            . 'manifest.json';

        try {
            $sTempZip =
                $sItemDir
                . DIRECTORY_SEPARATOR
                . '.reference-'
                . bin2hex(random_bytes(6))
                . '.tmp';

            if (!@copy(
                $sZipReal,
                $sTempZip
            )) {
                throw new RuntimeException(
                    'Doctor could not copy the verified package into persistent Reference Library storage.'
                );
            }

            @chmod(
                $sTempZip,
                0600
            );

            $sCopiedSha256 =
                strtolower(
                    (string)hash_file(
                        'sha256',
                        $sTempZip
                    )
                );

            if (
                strlen($sCopiedSha256) !== 64
                || !hash_equals(
                    $sSha256,
                    $sCopiedSha256
                )
            ) {
                throw new RuntimeException(
                    'The durable Reference Library copy failed SHA-256 verification.'
                );
            }

            if (!@rename(
                $sTempZip,
                $sTargetZip
            )) {
                throw new RuntimeException(
                    'Doctor could not commit the persistent reference ZIP.'
                );
            }

            @chmod(
                $sTargetZip,
                0600
            );

            $sCreatedUtc =
                gmdate('Y-m-d H:i:s');

            $sReportJson =
                @file_get_contents(
                    $sReportReal
                );

            if (is_string($sReportJson)) {
                $aReport =
                    json_decode(
                        $sReportJson,
                        true
                    );

                $sReportedTime =
                    is_array($aReport)
                        ? trim(
                            (string)(
                                $aReport[
                                    'created_utc'
                                ]
                                ?? ''
                            )
                        )
                        : '';

                if (
                    preg_match(
                        '/^\d{4}-\d{2}-\d{2} '
                        . '\d{2}:\d{2}:\d{2}$/',
                        $sReportedTime
                    )
                ) {
                    $sCreatedUtc =
                        $sReportedTime;
                }
            }

            $aManifest = array(
                'schema' =>
                    self::MANIFEST_SCHEMA,

                'reference_sha256' =>
                    $sSha256,

                'module_name' =>
                    (string)(
                        $aPackage['name']
                        ?? ''
                    ),

                'module_title' =>
                    (string)(
                        $aPackage['title']
                        ?? ''
                    ),

                'vendor' =>
                    (string)(
                        $aPackage['vendor']
                        ?? ''
                    ),

                'reference_version' =>
                    (string)(
                        $aPackage['version']
                        ?? ''
                    ),

                'original_filename' =>
                    basename(
                        (string)(
                            $aPackage[
                                'original_filename'
                            ]
                            ?? 'reference.zip'
                        )
                    ),

                'archive_files' =>
                    (int)(
                        $aPackage[
                            'archive_files'
                        ]
                        ?? 0
                    ),

                'archive_bytes' =>
                    (int)(
                        $aPackage[
                            'archive_uncompressed_bytes'
                        ]
                        ?? 0
                    ),

                'reference_files' =>
                    (int)(
                        $aComparison[
                            'reference_files'
                        ]
                        ?? 0
                    ),

                'first_session_id' =>
                    $sSessionId,

                'first_seen_utc' =>
                    $sCreatedUtc,
            );

            $sManifestJson =
                json_encode(
                    $aManifest,
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES
                );

            if (!is_string($sManifestJson))
                throw new RuntimeException(
                    'Doctor could not encode the immutable Reference Library manifest.'
                );

            $sTempManifest =
                $sItemDir
                . DIRECTORY_SEPARATOR
                . '.manifest-'
                . bin2hex(random_bytes(6))
                . '.tmp';

            if (
                @file_put_contents(
                    $sTempManifest,
                    $sManifestJson . "\n",
                    LOCK_EX
                ) === false
            ) {
                throw new RuntimeException(
                    'Doctor could not write the immutable Reference Library manifest.'
                );
            }

            @chmod(
                $sTempManifest,
                0600
            );

            if (!@rename(
                $sTempManifest,
                $sManifest
            )) {
                throw new RuntimeException(
                    'Doctor could not commit the immutable Reference Library manifest.'
                );
            }

            @chmod(
                $sManifest,
                0600
            );

            $sRelPath =
                self::STORAGE_PREFIX
                . $sSha256
                . '/reference.zip';

            $iNow = time();

            $mixedInsert =
                $this->oDb->query(
                    "INSERT INTO `" . self::TABLE . "`
                    (
                        `module_name`,
                        `module_title`,
                        `vendor`,
                        `reference_version`,
                        `original_filename`,
                        `reference_sha256`,
                        `storage_relpath`,
                        `archive_files`,
                        `archive_bytes`,
                        `reference_files`,
                        `first_session_id`,
                        `latest_session_id`,
                        `comparison_count`,
                        `first_seen_utc`,
                        `last_seen_utc`,
                        `baseline_authenticity`,
                        `integrity_status`,
                        `created_at`,
                        `updated_at`
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, 1,
                        ?, ?, ?, 'verified',
                        ?, ?
                    )",
                    array(
                        (string)(
                            $aPackage['name']
                            ?? ''
                        ),

                        (string)(
                            $aPackage['title']
                            ?? ''
                        ),

                        (string)(
                            $aPackage['vendor']
                            ?? ''
                        ),

                        (string)(
                            $aPackage['version']
                            ?? ''
                        ),

                        basename(
                            (string)(
                                $aPackage[
                                    'original_filename'
                                ]
                                ?? 'reference.zip'
                            )
                        ),

                        $sSha256,
                        $sRelPath,

                        (int)(
                            $aPackage[
                                'archive_files'
                            ]
                            ?? 0
                        ),

                        (int)(
                            $aPackage[
                                'archive_uncompressed_bytes'
                            ]
                            ?? 0
                        ),

                        (int)(
                            $aComparison[
                                'reference_files'
                            ]
                            ?? 0
                        ),

                        $sSessionId,
                        $sSessionId,
                        $sCreatedUtc,
                        $sCreatedUtc,
                        $sBaselineAuthenticity,
                        $iNow,
                        $iNow,
                    )
                );

            if ($mixedInsert === false)
                throw new RuntimeException(
                    'Doctor could not create the Reference Library catalog record.'
                );

            $aVerified =
                $this->getBySha256(
                    $sSha256
                );

            if (
                empty($aVerified)
                || empty(
                    $aVerified['library_verified']
                )
            ) {
                $this->oDb->query(
                    "DELETE FROM `" . self::TABLE . "`
                     WHERE `reference_sha256` = ?
                     LIMIT 1",
                    array($sSha256)
                );

                throw new RuntimeException(
                    'The new reference failed final immutable Library verification.'
                );
            }

            return array(
                'ok' => true,
                'saved' => true,
                'new_reference' => true,
                'duplicate_reference' => false,
                'history_recorded' => true,
                'reference_sha256' =>
                    $sSha256,
                'session_id' =>
                    $sSessionId,
            );

        } catch (Throwable $e) {
            @unlink($sManifest);
            @unlink($sTargetZip);

            foreach (
                glob(
                    $sItemDir
                    . DIRECTORY_SEPARATOR
                    . '.*.tmp'
                ) ?: array()
                as $sTemp
            ) {
                @unlink($sTemp);
            }

            @rmdir($sItemDir);

            throw $e;
        }
    }


    public function recordComparison(
        $sSha256,
        $sSessionId,
        $sBaselineAuthenticity = ''
    ) {
        $sSha256 =
            strtolower(
                trim((string)$sSha256)
            );

        $sSessionId =
            trim((string)$sSessionId);

        if (
            strlen($sSha256) !== 64
            || !ctype_xdigit($sSha256)
        ) {
            return false;
        }

        if (
            !preg_match(
                '/^[0-9]{8}-[0-9]{6}-[a-f0-9]{12}$/',
                $sSessionId
            )
        ) {
            return false;
        }

        /*
         * Re-verify the immutable library item immediately
         * before modifying its catalog history.
         */
        $aItem =
            $this->getBySha256(
                $sSha256
            );

        if (
            empty($aItem)
            || empty(
                $aItem['library_verified']
            )
        ) {
            return false;
        }

        $sBaselineAuthenticity =
            trim(
                (string)$sBaselineAuthenticity
            );

        $iNow = time();

        $mixedResult =
            $this->oDb->query(
                "UPDATE `" . self::TABLE . "`
                 SET
                    `latest_session_id` = ?,
                    `comparison_count` =
                        `comparison_count` + 1,
                    `last_seen_utc` = UTC_TIMESTAMP(),
                    `baseline_authenticity` =
                        CASE
                            WHEN ? <> ''
                            THEN ?
                            ELSE `baseline_authenticity`
                        END,
                    `integrity_status` = 'verified',
                    `updated_at` = ?
                 WHERE `reference_sha256` = ?
                 LIMIT 1",
                array(
                    $sSessionId,
                    $sBaselineAuthenticity,
                    $sBaselineAuthenticity,
                    $iNow,
                    $sSha256,
                )
            );

        return $mixedResult !== false;
    }


    protected function verifyRow(array $aRow)
    {
        $aRow['library_verified'] = false;
        $aRow['library_errors'] = array();
        $aRow['actual_sha256'] = '';
        $aRow['manifest_verified'] = false;
        $aRow['zip_verified'] = false;
        $aRow['zip_bytes_actual'] = 0;

        $sExpectedSha =
            strtolower(
                trim(
                    (string)(
                        $aRow[
                            'reference_sha256'
                        ]
                        ?? ''
                    )
                )
            );

        if (
            strlen($sExpectedSha) !== 64
            || !ctype_xdigit($sExpectedSha)
        ) {
            $aRow['library_errors'][] =
                'The catalog contains an invalid reference SHA-256.';

            return $aRow;
        }

        $sRelPath =
            str_replace(
                '\\',
                '/',
                trim(
                    (string)(
                        $aRow[
                            'storage_relpath'
                        ]
                        ?? ''
                    )
                )
            );

        if (
            $sRelPath === ''
            || strpos(
                $sRelPath,
                self::STORAGE_PREFIX
            ) !== 0
            || strpos($sRelPath, '../') !== false
            || strpos($sRelPath, '/..') !== false
        ) {
            $aRow['library_errors'][] =
                'The catalog storage path is outside Doctor’s protected Reference Library.';

            return $aRow;
        }

        $sLibraryRoot =
            rtrim(
                BX_DIRECTORY_PATH_ROOT,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                rtrim(
                    self::STORAGE_PREFIX,
                    '/'
                )
            );

        $sLibraryRootReal =
            realpath($sLibraryRoot);

        if (
            $sLibraryRootReal === false
            || !is_dir($sLibraryRootReal)
        ) {
            $aRow['library_errors'][] =
                'The protected Reference Library storage root is unavailable.';

            return $aRow;
        }

        $sZip =
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

        if (
            !is_file($sZip)
            || is_link($sZip)
        ) {
            $aRow['library_errors'][] =
                'The preserved reference ZIP is missing or unsafe.';

            return $aRow;
        }

        $sZipReal = realpath($sZip);

        if ($sZipReal === false) {
            $aRow['library_errors'][] =
                'Doctor could not resolve the preserved reference ZIP.';

            return $aRow;
        }

        $sPrefix =
            rtrim(
                $sLibraryRootReal,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR;

        if (
            strpos(
                $sZipReal,
                $sPrefix
            ) !== 0
        ) {
            $aRow['library_errors'][] =
                'The preserved reference ZIP resolves outside Doctor’s protected library root.';

            return $aRow;
        }

        $sActualSha =
            strtolower(
                (string)@hash_file(
                    'sha256',
                    $sZipReal
                )
            );

        $aRow['actual_sha256'] =
            $sActualSha;

        $aRow['zip_bytes_actual'] =
            (int)@filesize($sZipReal);

        if (
            strlen($sActualSha) !== 64
            || !hash_equals(
                $sExpectedSha,
                $sActualSha
            )
        ) {
            $aRow['library_errors'][] =
                'The preserved reference ZIP no longer matches its catalog SHA-256.';

            return $aRow;
        }

        $aRow['zip_verified'] = true;

        $sItemDir = dirname($sZipReal);

        $sExpectedDir =
            basename($sItemDir);

        if (
            !hash_equals(
                $sExpectedSha,
                strtolower($sExpectedDir)
            )
        ) {
            $aRow['library_errors'][] =
                'The reference library directory identity does not match the ZIP SHA-256.';

            return $aRow;
        }

        $sManifest =
            $sItemDir
            . DIRECTORY_SEPARATOR
            . 'manifest.json';

        if (
            !is_file($sManifest)
            || is_link($sManifest)
        ) {
            $aRow['library_errors'][] =
                'The immutable reference manifest is missing or unsafe.';

            return $aRow;
        }

        $sManifestJson =
            @file_get_contents($sManifest);

        if ($sManifestJson === false) {
            $aRow['library_errors'][] =
                'Doctor could not read the immutable reference manifest.';

            return $aRow;
        }

        $aManifest =
            json_decode(
                $sManifestJson,
                true
            );

        if (!is_array($aManifest)) {
            $aRow['library_errors'][] =
                'The immutable reference manifest is not valid JSON.';

            return $aRow;
        }

        if (
            (string)(
                $aManifest['schema']
                ?? ''
            )
            !== self::MANIFEST_SCHEMA
        ) {
            $aRow['library_errors'][] =
                'The immutable reference manifest has an unsupported schema.';

            return $aRow;
        }

        if (
            strtolower(
                (string)(
                    $aManifest[
                        'reference_sha256'
                    ]
                    ?? ''
                )
            )
            !== $sExpectedSha
        ) {
            $aRow['library_errors'][] =
                'The immutable manifest SHA-256 identity does not match the catalog.';
        }

        if (
            (string)(
                $aManifest['module_name']
                ?? ''
            )
            !==
            (string)(
                $aRow['module_name']
                ?? ''
            )
        ) {
            $aRow['library_errors'][] =
                'The immutable manifest module identity does not match the catalog.';
        }

        if (
            (string)(
                $aManifest[
                    'reference_version'
                ]
                ?? ''
            )
            !==
            (string)(
                $aRow[
                    'reference_version'
                ]
                ?? ''
            )
        ) {
            $aRow['library_errors'][] =
                'The immutable manifest version does not match the catalog.';
        }

        if (
            (string)(
                $aManifest[
                    'first_session_id'
                ]
                ?? ''
            )
            !==
            (string)(
                $aRow[
                    'first_session_id'
                ]
                ?? ''
            )
        ) {
            $aRow['library_errors'][] =
                'The immutable manifest origin session does not match the catalog.';
        }

        if (empty($aRow['library_errors']))
            $aRow['manifest_verified'] = true;

        $aRow['library_verified'] =
            $aRow['zip_verified']
            && $aRow['manifest_verified']
            && empty(
                $aRow['library_errors']
            );

        return $aRow;
    }


    protected function tableExists()
    {
        return (int)$this->oDb->getOne(
            "SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?",
            array(self::TABLE)
        ) === 1;
    }
}
