<?php defined('BX_DOL') or die('hack attempt');

class MyUnaDoctorReferencePackage
{
    protected $oModule;
    protected $oDb;

    const MAX_ARCHIVE_FILES = 50000;
    const MAX_ARCHIVE_BYTES = 2147483648;
    const MAX_SINGLE_FILE_BYTES = 268435456;

    public function __construct($oModule)
    {
        $this->oModule = $oModule;
        $this->oDb = BxDolDb::getInstance();
    }

    public function compareUpload(array $aUpload)
    {
        try {
            return $this->doCompareUpload($aUpload);
        } catch (Throwable $e) {
            return array(
                'ok' => false,
                'changes_applied' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    protected function doCompareUpload(array $aUpload)
    {
        if (!class_exists('ZipArchive'))
            throw new RuntimeException(
                'PHP ZIP support is not available.'
            );

        $iError = isset($aUpload['error'])
            ? (int)$aUpload['error']
            : UPLOAD_ERR_NO_FILE;

        if ($iError !== UPLOAD_ERR_OK)
            throw new RuntimeException(
                'Reference ZIP upload did not complete successfully. Upload error code: '
                . $iError
            );

        $sTmp = isset($aUpload['tmp_name'])
            ? (string)$aUpload['tmp_name']
            : '';

        /*
         * Fresh uploads retain the original HTTP-upload gate.
         * Saved Reference Library files use a separate method.
         */
        if (
            $sTmp === ''
            || !is_file($sTmp)
            || !is_uploaded_file($sTmp)
        ) {
            throw new RuntimeException(
                'The uploaded reference package could not be verified as an HTTP upload.'
            );
        }

        $sOriginal = isset($aUpload['name'])
            ? basename((string)$aUpload['name'])
            : 'reference.zip';

        $iUploadSize = isset($aUpload['size'])
            ? (int)$aUpload['size']
            : (int)filesize($sTmp);

        return $this->doCompareFile(
            $sTmp,
            $sOriginal,
            $iUploadSize,
            'upload',
            ''
        );
    }


    /**
     * Compare a previously verified persistent Reference
     * Library ZIP.
     *
     * This does NOT weaken compareUpload(). Saved files must
     * remain inside Doctor's protected library and must match
     * their immutable SHA-256 identity.
     */
    public function compareStoredReferenceFile(
        $sPath,
        $sOriginal,
        $sExpectedSha256
    ) {
        try {
            return $this->doCompareStoredReferenceFile(
                $sPath,
                $sOriginal,
                $sExpectedSha256
            );
        } catch (Throwable $e) {
            return array(
                'ok' => false,
                'changes_applied' => false,
                'error' => $e->getMessage(),
            );
        }
    }


    protected function doCompareStoredReferenceFile(
        $sPath,
        $sOriginal,
        $sExpectedSha256
    ) {
        if (!class_exists('ZipArchive'))
            throw new RuntimeException(
                'PHP ZIP support is not available.'
            );

        $sExpectedSha256 =
            strtolower(
                trim((string)$sExpectedSha256)
            );

        if (
            strlen($sExpectedSha256) !== 64
            || !ctype_xdigit($sExpectedSha256)
        ) {
            throw new RuntimeException(
                'The saved reference has an invalid SHA-256 identity.'
            );
        }

        $sPath = (string)$sPath;

        if (
            $sPath === ''
            || !is_file($sPath)
            || is_link($sPath)
        ) {
            throw new RuntimeException(
                'The saved Reference Library ZIP is missing or unsafe.'
            );
        }

        $sLibraryRoot =
            rtrim(
                BX_DIRECTORY_PATH_ROOT,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'my_una_doctor'
            . DIRECTORY_SEPARATOR
            . 'reference-library';

        $sRootReal = realpath($sLibraryRoot);
        $sFileReal = realpath($sPath);

        if (
            $sRootReal === false
            || $sFileReal === false
        ) {
            throw new RuntimeException(
                'Doctor could not resolve the saved Reference Library path.'
            );
        }

        $sPrefix =
            rtrim(
                $sRootReal,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR;

        if (strpos($sFileReal, $sPrefix) !== 0)
            throw new RuntimeException(
                'The saved reference resolves outside Doctor’s protected Reference Library.'
            );

        $sActualSha256 =
            strtolower(
                (string)hash_file(
                    'sha256',
                    $sFileReal
                )
            );

        if (
            strlen($sActualSha256) !== 64
            || !hash_equals(
                $sExpectedSha256,
                $sActualSha256
            )
        ) {
            throw new RuntimeException(
                'The saved reference ZIP failed its SHA-256 integrity check.'
            );
        }

        $sOriginal =
            basename(
                trim((string)$sOriginal)
            );

        if ($sOriginal === '')
            $sOriginal = 'reference.zip';

        $iSize = (int)filesize($sFileReal);

        return $this->doCompareFile(
            $sFileReal,
            $sOriginal,
            $iSize,
            'library',
            $sExpectedSha256
        );
    }


    /**
     * Shared comparison engine after the source itself has
     * already passed the appropriate trust gate.
     */
    protected function doCompareFile(
        $sTmp,
        $sOriginal,
        $iUploadSize,
        $sSource = 'upload',
        $sExpectedSha256 = ''
    ) {
        if (
            strtolower(
                pathinfo(
                    $sOriginal,
                    PATHINFO_EXTENSION
                )
            ) !== 'zip'
        ) {
            throw new RuntimeException(
                'Reference packages must be ZIP files.'
            );
        }

        $iUploadSize = (int)$iUploadSize;

        if ($iUploadSize <= 0)
            throw new RuntimeException(
                'The reference ZIP is empty.'
            );

        if ($iUploadSize > self::MAX_ARCHIVE_BYTES)
            throw new RuntimeException(
                'The reference ZIP exceeds Doctor’s 2 GiB safety ceiling.'
            );

        $oZip = new ZipArchive();

        if ($oZip->open($sTmp) !== true)
            throw new RuntimeException(
                'Doctor could not open the reference ZIP.'
            );

        try {
            $aArchive =
                $this->inspectArchive($oZip);

            $aIdentity =
                $this->parsePackageIdentity(
                    $oZip,
                    $aArchive['config_path']
                );

            $aInstalled =
                $this->findInstalledModule(
                    $aIdentity
                );

            if (empty($aInstalled))
                throw new RuntimeException(
                    'Doctor identified the reference package, but could not match it to an installed UNA module.'
                );

            $aReferenceHashes =
                $this->hashReferenceTree(
                    $oZip,
                    $aArchive['root_prefix']
                );

            $sLiveRoot =
                rtrim(
                    BX_DIRECTORY_PATH_MODULES,
                    DIRECTORY_SEPARATOR
                )
                . DIRECTORY_SEPARATOR
                . trim(
                    (string)$aInstalled['path'],
                    '/\\'
                )
                . DIRECTORY_SEPARATOR;

            if (!is_dir($sLiveRoot))
                throw new RuntimeException(
                    'The installed module directory is missing.'
                );

            $aLiveHashes =
                $this->hashLiveTree(
                    $sLiveRoot
                );

            $aComparison =
                $this->compareHashes(
                    $aReferenceHashes,
                    $aLiveHashes
                );

            $aBaseline =
                $this->compareReferenceToUnaBaseline(
                    (int)$aInstalled['id'],
                    $aReferenceHashes
                );

            $aComparison =
                $this->classifyReferenceModifications(
                    (string)$aInstalled['name'],
                    (string)$aInstalled['version'],
                    $aComparison,
                    $aBaseline
                );

            $aComparison =
                $this->classifyLiveOnlyFiles(
                    $aComparison,
                    $aBaseline
                );

            $aLiveIdentity =
                $this->parseLivePackageIdentity(
                    $sLiveRoot
                    . 'install/config.php'
                );

            /*
             * For persistent references, prove the source did
             * not change between the library gate and the end
             * of comparison preparation.
             */
            $sCurrentSha256 =
                strtolower(
                    (string)hash_file(
                        'sha256',
                        $sTmp
                    )
                );

            if (
                $sExpectedSha256 !== ''
                && !hash_equals(
                    strtolower($sExpectedSha256),
                    $sCurrentSha256
                )
            ) {
                throw new RuntimeException(
                    'The saved reference changed while Doctor was comparing it.'
                );
            }

            $aResult = array(
                'ok' => true,
                'changes_applied' => false,

                'package' => array(
                    'original_filename' =>
                        $sOriginal,

                    'uploaded_bytes' =>
                        $iUploadSize,

                    'sha256' =>
                        $sCurrentSha256,

                    'source' =>
                        $sSource,

                    'archive_root' =>
                        $aArchive['root_prefix'],

                    'archive_files' =>
                        $aArchive['file_count'],

                    'archive_uncompressed_bytes' =>
                        $aArchive['uncompressed_bytes'],

                    'name' =>
                        $aIdentity['name'],

                    'title' =>
                        $aIdentity['title'],

                    'version' =>
                        $aIdentity['version'],

                    'vendor' =>
                        $aIdentity['vendor'],

                    'home_dir' =>
                        $aIdentity['home_dir'],
                ),

                'installed' => array(
                    'id' =>
                        (int)$aInstalled['id'],

                    'name' =>
                        (string)$aInstalled['name'],

                    'title' =>
                        (string)$aInstalled['title'],

                    'registered_version' =>
                        (string)$aInstalled['version'],

                    'live_package_version' =>
                        isset(
                            $aLiveIdentity['version']
                        )
                            ? $aLiveIdentity['version']
                            : '',

                    'path' =>
                        (string)$aInstalled['path'],

                    'enabled' =>
                        (int)$aInstalled['enabled'],
                ),

                'comparison' =>
                    $this->summarizeComparison(
                        $aComparison,
                        count($aReferenceHashes),
                        count($aLiveHashes)
                    ),

                'baseline' =>
                    $this->summarizeBaseline(
                        $aBaseline
                    ),

                'safety' => array(
                    'live_module_modified' => false,
                    'archive_extracted' => false,
                    'comparison_only' => true,
                    'reference_source' =>
                        $sSource,
                ),
            );

            $aStored =
                $this->persistComparison(
                    $sTmp,
                    $aResult,
                    $aComparison,
                    $aBaseline
                );

            $aResult['evidence'] =
                $aStored;

            return $aResult;

        } finally {
            $oZip->close();
        }
    }


    protected function inspectArchive(ZipArchive $oZip)
    {
        $iFiles = 0;
        $iBytes = 0;
        $aConfigs = array();

        for ($i = 0; $i < $oZip->numFiles; $i++) {
            $aStat = $oZip->statIndex($i);

            if (!is_array($aStat))
                throw new RuntimeException(
                    'Doctor could not inspect one of the ZIP entries.'
                );

            $sName = isset($aStat['name'])
                ? (string)$aStat['name']
                : '';

            if ($this->isIgnoredArchiveEntry($sName))
                continue;

            $sName = $this->normalizeArchivePath(
                $sName
            );

            if ($sName === '')
                continue;

            if (substr($sName, -1) === '/')
                continue;

            $iFiles++;

            if ($iFiles > self::MAX_ARCHIVE_FILES)
                throw new RuntimeException(
                    'The ZIP contains more than 50,000 files and was rejected by the safety gate.'
                );

            $iSize = isset($aStat['size'])
                ? (int)$aStat['size']
                : 0;

            if ($iSize > self::MAX_SINGLE_FILE_BYTES)
                throw new RuntimeException(
                    'The ZIP contains an individual file larger than Doctor’s 256 MiB safety ceiling: '
                    . $sName
                );

            $iBytes += $iSize;

            if ($iBytes > self::MAX_ARCHIVE_BYTES)
                throw new RuntimeException(
                    'The ZIP expands beyond Doctor’s 2 GiB safety ceiling.'
                );

            if (
                preg_match(
                    '#(^|/)install/config\.php$#i',
                    $sName
                )
            ) {
                $aConfigs[] = $sName;
            }
        }

        $aConfigs = array_values(
            array_unique($aConfigs)
        );

        if (count($aConfigs) !== 1)
            throw new RuntimeException(
                'Doctor requires exactly one module install/config.php in the reference ZIP. Found: '
                . count($aConfigs)
            );

        $sConfig = $aConfigs[0];

        $sSuffix = 'install/config.php';

        $sRoot = substr(
            $sConfig,
            0,
            strlen($sConfig) - strlen($sSuffix)
        );

        return array(
            'config_path' => $sConfig,
            'root_prefix' => $sRoot,
            'file_count' => $iFiles,
            'uncompressed_bytes' => $iBytes,
        );
    }

    protected function normalizeArchivePath($sPath)
    {
        if (strpos($sPath, "\0") !== false)
            throw new RuntimeException(
                'The ZIP contains an invalid null-byte path.'
            );

        $sPath = str_replace('\\', '/', $sPath);

        if (
            substr($sPath, 0, 1) === '/'
            || preg_match('/^[A-Za-z]:\//', $sPath)
        ) {
            throw new RuntimeException(
                'The ZIP contains an absolute path and was rejected: '
                . $sPath
            );
        }

        $aParts = explode('/', $sPath);

        foreach ($aParts as $sPart) {
            if ($sPart === '..')
                throw new RuntimeException(
                    'The ZIP contains path traversal and was rejected: '
                    . $sPath
                );
        }

        while (strpos($sPath, './') === 0)
            $sPath = substr($sPath, 2);

        return $sPath;
    }

    protected function isIgnoredArchiveEntry($sName)
    {
        $s = str_replace('\\', '/', $sName);

        if (
            strpos($s, '__MACOSX/') === 0
            || substr($s, -9) === '/.DS_Store'
            || $s === '.DS_Store'
            || substr($s, -10) === '/Thumbs.db'
            || $s === 'Thumbs.db'
        ) {
            return true;
        }

        return false;
    }

    protected function parsePackageIdentity(
        ZipArchive $oZip,
        $sConfigPath
    ) {
        $sConfig = $oZip->getFromName(
            $sConfigPath
        );

        if (!is_string($sConfig))
            throw new RuntimeException(
                'Doctor could not read install/config.php from the ZIP.'
            );

        $aIdentity =
            $this->parseConfigText($sConfig);

        if (
            $aIdentity['name'] === ''
            && $aIdentity['home_dir'] === ''
        ) {
            throw new RuntimeException(
                'Doctor could not identify the module name or home_dir from install/config.php.'
            );
        }

        return $aIdentity;
    }

    protected function parseLivePackageIdentity(
        $sConfigPath
    ) {
        if (!is_file($sConfigPath))
            return $this->emptyIdentity();

        $sText = @file_get_contents(
            $sConfigPath
        );

        if (!is_string($sText))
            return $this->emptyIdentity();

        return $this->parseConfigText($sText);
    }

    protected function parseConfigText($sText)
    {
        $sText = $this->stripPhpComments(
            $sText
        );

        $aResult = $this->emptyIdentity();

        foreach (
            array(
                'name',
                'title',
                'version',
                'vendor',
                'home_dir',
            )
            as $sKey
        ) {
            $aPatterns = array(
                '/[\'"]'
                    . preg_quote($sKey, '/')
                    . '[\'"]\s*=>\s*[\'"]([^\'"]*)[\'"]/i',
                '/\$aConfig\s*\[\s*[\'"]'
                    . preg_quote($sKey, '/')
                    . '[\'"]\s*\]\s*=\s*[\'"]([^\'"]*)[\'"]/i',
            );

            foreach ($aPatterns as $sPattern) {
                if (
                    preg_match(
                        $sPattern,
                        $sText,
                        $aMatch
                    )
                ) {
                    $aResult[$sKey] =
                        trim((string)$aMatch[1]);
                    break;
                }
            }
        }

        return $aResult;
    }

    protected function stripPhpComments($sText)
    {
        $aTokens = token_get_all($sText);
        $sOut = '';

        foreach ($aTokens as $mixedToken) {
            if (is_array($mixedToken)) {
                if (
                    $mixedToken[0] === T_COMMENT
                    || $mixedToken[0] === T_DOC_COMMENT
                ) {
                    continue;
                }

                $sOut .= $mixedToken[1];
            } else {
                $sOut .= $mixedToken;
            }
        }

        return $sOut;
    }

    protected function emptyIdentity()
    {
        return array(
            'name' => '',
            'title' => '',
            'version' => '',
            'vendor' => '',
            'home_dir' => '',
        );
    }

    protected function findInstalledModule(
        array $aIdentity
    ) {
        $aModule = array();

        if ($aIdentity['name'] !== '') {
            $aModule = $this->oDb->getRow(
                "SELECT `id`, `name`, `title`, `version`,
                        `path`, `enabled`
                 FROM `sys_modules`
                 WHERE `name` = ?
                 LIMIT 1",
                array($aIdentity['name'])
            );
        }

        if (
            empty($aModule)
            && $aIdentity['home_dir'] !== ''
        ) {
            $sHome = trim(
                $aIdentity['home_dir'],
                '/\\'
            ) . '/';

            $aModule = $this->oDb->getRow(
                "SELECT `id`, `name`, `title`, `version`,
                        `path`, `enabled`
                 FROM `sys_modules`
                 WHERE `path` = ?
                 LIMIT 1",
                array($sHome)
            );
        }

        return is_array($aModule)
            ? $aModule
            : array();
    }

    protected function hashReferenceTree(
        ZipArchive $oZip,
        $sRoot
    ) {
        $aHashes = array();

        for ($i = 0; $i < $oZip->numFiles; $i++) {
            $aStat = $oZip->statIndex($i);

            if (!is_array($aStat))
                continue;

            $sName = isset($aStat['name'])
                ? (string)$aStat['name']
                : '';

            if ($this->isIgnoredArchiveEntry($sName))
                continue;

            $sName = $this->normalizeArchivePath(
                $sName
            );

            if (
                $sName === ''
                || substr($sName, -1) === '/'
            ) {
                continue;
            }

            if (
                $sRoot !== ''
                && strpos($sName, $sRoot) !== 0
            ) {
                continue;
            }

            $sRelative = $sRoot !== ''
                ? substr($sName, strlen($sRoot))
                : $sName;

            if ($sRelative === '')
                continue;

            $rStream = $oZip->getStream(
                $sName
            );

            if (!is_resource($rStream))
                throw new RuntimeException(
                    'Doctor could not read ZIP entry: '
                    . $sName
                );

            $oHash = hash_init('md5');
            hash_update_stream($oHash, $rStream);
            fclose($rStream);

            $aHashes[$sRelative] =
                hash_final($oHash);
        }

        ksort($aHashes);

        return $aHashes;
    }

    protected function hashLiveTree($sRoot)
    {
        $aHashes = array();

        $oIterator =
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $sRoot,
                    FilesystemIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

        foreach ($oIterator as $oFile) {
            if (
                !$oFile->isFile()
                || $oFile->isLink()
            ) {
                continue;
            }

            $sFull = $oFile->getPathname();

            $sRelative = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                substr($sFull, strlen($sRoot))
            );

            $aHashes[$sRelative] =
                md5_file($sFull);

            if (
                count($aHashes)
                > self::MAX_ARCHIVE_FILES
            ) {
                throw new RuntimeException(
                    'The live module contains more than 50,000 files; comparison stopped at the safety ceiling.'
                );
            }
        }

        ksort($aHashes);

        return $aHashes;
    }

    protected function compareHashes(
        array $aReference,
        array $aLive
    ) {
        $aModified = array();
        $aMissing = array();
        $aLiveOnly = array();
        $iMatching = 0;

        foreach (
            $aReference
            as $sFile => $sReferenceHash
        ) {
            if (!array_key_exists($sFile, $aLive)) {
                $aMissing[] = $sFile;
                continue;
            }

            if (
                hash_equals(
                    $sReferenceHash,
                    (string)$aLive[$sFile]
                )
            ) {
                $iMatching++;
            } else {
                $aModified[] = array(
                    'file' => $sFile,
                    'reference_md5' =>
                        $sReferenceHash,
                    'live_md5' =>
                        (string)$aLive[$sFile],
                );
            }
        }

        foreach ($aLive as $sFile => $sHash) {
            if (!array_key_exists(
                $sFile,
                $aReference
            )) {
                $aLiveOnly[] = $sFile;
            }
        }

        sort($aMissing);
        sort($aLiveOnly);

        return array(
            'matching_count' => $iMatching,
            'modified' => $aModified,
            'missing' => $aMissing,
            'live_only' => $aLiveOnly,
        );
    }

    protected function classifyReferenceModifications(
        $sModule,
        $sVersion,
        array $aComparison,
        array $aBaseline = array()
    ) {
        $aBaselineBacked = array();
        $aApproved = array();
        $aUnexplained = array();

        /*
         * Index reference files which differ from the UNA
         * checksum baseline.
         */
        $aBaselineDifferent = array();

        if (
            isset($aBaseline['different'])
            && is_array($aBaseline['different'])
        ) {
            foreach (
                $aBaseline['different']
                as $aDifference
            ) {
                if (!is_array($aDifference))
                    continue;

                $sFile =
                    (string)(
                        $aDifference['file']
                        ?? ''
                    );

                if ($sFile === '')
                    continue;

                $aBaselineDifferent[$sFile] =
                    $aDifference;
            }
        }

        $aModified =
            isset($aComparison['modified'])
            && is_array($aComparison['modified'])
                ? $aComparison['modified']
                : array();

        foreach ($aModified as $aFile) {
            if (!is_array($aFile))
                continue;

            $sFile =
                (string)(
                    $aFile['file']
                    ?? ''
                );

            $sReference =
                strtolower(
                    (string)(
                        $aFile['reference_md5']
                        ?? ''
                    )
                );

            $sLive =
                strtolower(
                    (string)(
                        $aFile['live_md5']
                        ?? ''
                    )
                );

            /*
             * Highest available evidence:
             *
             * The reference ZIP differs from UNA's stored hash
             * while the live file exactly matches UNA's stored
             * hash.
             *
             * This means the live file is baseline-backed and
             * the uploaded ZIP is the divergent build.
             */
            if (
                isset(
                    $aBaselineDifferent[$sFile]
                )
            ) {
                $aDifference =
                    $aBaselineDifferent[$sFile];

                $sBaseline =
                    strtolower(
                        (string)(
                            $aDifference[
                                'baseline_md5'
                            ] ?? ''
                        )
                    );

                $sBaselineReference =
                    strtolower(
                        (string)(
                            $aDifference[
                                'reference_md5'
                            ] ?? ''
                        )
                    );

                if (
                    $sBaseline !== ''
                    && $sReference !== ''
                    && $sLive !== ''
                    && hash_equals(
                        $sBaseline,
                        $sLive
                    )
                    && hash_equals(
                        $sBaselineReference,
                        $sReference
                    )
                ) {
                    $aFile['baseline_evidence'] =
                        array(
                            'verified' => true,
                            'classification' =>
                                'live_matches_una_baseline',
                            'baseline_md5' =>
                                $sBaseline,
                            'reference_md5' =>
                                $sReference,
                            'live_md5' =>
                                $sLive,
                            'reason' =>
                                'The live file exactly matches UNA\'s recorded checksum baseline while the uploaded reference package differs.',
                        );

                    $aBaselineBacked[] =
                        $aFile;

                    continue;
                }
            }

            /*
             * No baseline explanation exists.
             * Now consult Doctor compatibility rules and
             * persistent Local Repair Knowledge.
             */
            $aApproval =
                $this->inspectApprovedReferenceModification(
                    $sModule,
                    $sVersion,
                    $aFile
                );

            if (!empty($aApproval['verified'])) {
                $aFile['approval'] =
                    $aApproval;

                $aApproved[] =
                    $aFile;
            } else {
                $aUnexplained[] =
                    $aFile;
            }
        }

        $aComparison['baseline_backed_modified'] =
            $aBaselineBacked;

        $aComparison['approved_modified'] =
            $aApproved;

        $aComparison['unexplained_modified'] =
            $aUnexplained;

        return $aComparison;
    }


    protected function inspectApprovedReferenceModification(
        $sModule,
        $sVersion,
        array $aFile
    ) {
        $sFile =
            isset($aFile['file'])
                ? (string)$aFile['file']
                : '';

        $sReference =
            isset($aFile['reference_md5'])
                ? strtolower(
                    (string)$aFile['reference_md5']
                )
                : '';

        $sLive =
            isset($aFile['live_md5'])
                ? strtolower(
                    (string)$aFile['live_md5']
                )
                : '';

        $aKnowledge =
            $this->oModule
                ->findLocalRepairKnowledge(
                    (string)$sModule,
                    (string)$sVersion,
                    $sFile,
                    $sReference,
                    $sLive
                );

        if (!empty($aKnowledge['verified'])) {
            return array(
                'verified' => true,
                'classification' =>
                    'verified_local_repair_knowledge',
                'reason' =>
                    (string)$aKnowledge['reason'],
                'repair_type' =>
                    (string)$aKnowledge['repair_type'],
                'knowledge_id' =>
                    (int)$aKnowledge['id'],
                'knowledge_key' =>
                    (string)$aKnowledge['knowledge_key'],
                'reference_md5' =>
                    $sReference,
                'approved_live_md5' =>
                    $sLive,
            );
        }

        /*
         * Built-in AQB Points namespace knowledge remains as
         * a product-level compatibility rule.
         */
        if ($sModule !== 'aqb_points')
            return array();

        $aApproved = array(
            'classes/AqbPointsConfig.php' => array(
                'reference_md5' =>
                    '5dad898d9adcc4876cc41c7e387dceb1',
                'live_md5' =>
                    '3c3970a78a094016c7e031762ac2ed9c',
            ),
            'install/sql/enable.sql' => array(
                'reference_md5' =>
                    '331f622c0d4ccc2841562105843bd6e7',
                'live_md5' =>
                    'f8e81c87461d6efe0a5c606f53ce3f93',
            ),
        );

        if (!isset($aApproved[$sFile]))
            return array();

        if (
            $sReference
                !== $aApproved[$sFile]['reference_md5']
            || $sLive
                !== $aApproved[$sFile]['live_md5']
        ) {
            return array();
        }

        $aNamespace =
            $this->inspectAqbPointsNamespaceCompatibility();

        if (empty($aNamespace['verified']))
            return array();

        return array(
            'verified' => true,
            'classification' =>
                'approved_compatibility_modification',
            'reason' =>
                'UNA Reputation owns points-info; AQB Points is intentionally namespaced to aqb-points-info.',
            'reference_md5' => $sReference,
            'approved_live_md5' => $sLive,
            'namespace' => $aNamespace,
        );
    }

    protected function inspectAqbPointsNamespaceCompatibility()
    {
        $iTable = (int)$this->oDb->getOne(
            "SELECT COUNT(*)
             FROM `INFORMATION_SCHEMA`.`TABLES`
             WHERE `TABLE_SCHEMA` = DATABASE()
               AND `TABLE_NAME` = 'sys_objects_page'"
        );

        if ($iTable !== 1)
            return array();

        $iReputation = (int)$this->oDb->getOne(
            "SELECT COUNT(*)
             FROM `sys_objects_page`
             WHERE `object`='bx_reputation_info'
               AND `module`='bx_reputation'
               AND `uri`='points-info'
               AND `url`='page.php?i=points-info'"
        );

        $iPoints = (int)$this->oDb->getOne(
            "SELECT COUNT(*)
             FROM `sys_objects_page`
             WHERE `object`='aqb_points_info'
               AND `module`='aqb_points'
               AND `uri`='aqb-points-info'
               AND `url`='page.php?i=aqb-points-info'"
        );

        $iUnexpected = (int)$this->oDb->getOne(
            "SELECT COUNT(*)
             FROM `sys_objects_page`
             WHERE `uri`='points-info'
               AND NOT (
                   `object`='bx_reputation_info'
                   AND `module`='bx_reputation'
               )"
        );

        if (
            $iReputation !== 1
            || $iPoints !== 1
            || $iUnexpected !== 0
        ) {
            return array();
        }

        return array(
            'verified' => true,
            'reputation_uri' => 'points-info',
            'aqb_points_uri' => 'aqb-points-info',
            'unexpected_points_info_owners' => 0,
        );
    }


    protected function compareReferenceToUnaBaseline(
        $iModuleId,
        array $aReference
    ) {
        $iExists = (int)$this->oDb->getOne(
            "SELECT COUNT(*)
             FROM `INFORMATION_SCHEMA`.`TABLES`
             WHERE `TABLE_SCHEMA` = DATABASE()
               AND `TABLE_NAME` = 'sys_modules_file_tracks'"
        );

        if ($iExists !== 1) {
            return array(
                'available' => false,
                'tracked_count' => 0,
                'matching_count' => 0,
                'different' => array(),
                'reference_untracked' => array(),
                'baseline_missing_from_reference' => array(),
            );
        }

        $aRows = $this->oDb->getAll(
            "SELECT `file`, `hash`
             FROM `sys_modules_file_tracks`
             WHERE `module_id` = ?",
            array($iModuleId)
        );

        $aBaseline = array();

        foreach ($aRows as $aRow) {
            $aBaseline[
                (string)$aRow['file']
            ] = strtolower(
                (string)$aRow['hash']
            );
        }

        $iMatching = 0;
        $aDifferent = array();
        $aUntracked = array();
        $aMissing = array();

        foreach (
            $aReference
            as $sFile => $sReferenceHash
        ) {
            if (!isset($aBaseline[$sFile])) {
                $aUntracked[] = $sFile;
                continue;
            }

            if (
                hash_equals(
                    $aBaseline[$sFile],
                    strtolower($sReferenceHash)
                )
            ) {
                $iMatching++;
            } else {
                $aDifferent[] = array(
                    'file' => $sFile,
                    'baseline_md5' =>
                        $aBaseline[$sFile],
                    'reference_md5' =>
                        strtolower($sReferenceHash),
                );
            }
        }

        foreach ($aBaseline as $sFile => $sHash) {
            if (!isset($aReference[$sFile]))
                $aMissing[] = $sFile;
        }

        sort($aUntracked);
        sort($aMissing);

        return array(
            'available' => true,
            'tracked_count' =>
                count($aBaseline),
            'matching_count' =>
                $iMatching,
            'different' =>
                $aDifferent,
            'reference_untracked' =>
                $aUntracked,
            'baseline_missing_from_reference' =>
                $aMissing,
        );
    }

    protected function classifyLiveOnlyFiles(
        array $aComparison,
        array $aBaseline
    ) {
        $aLiveOnly =
            isset($aComparison['live_only'])
            && is_array($aComparison['live_only'])
                ? $aComparison['live_only']
                : array();

        $aBaselineMissing =
            isset(
                $aBaseline[
                    'baseline_missing_from_reference'
                ]
            )
            && is_array(
                $aBaseline[
                    'baseline_missing_from_reference'
                ]
            )
                ? $aBaseline[
                    'baseline_missing_from_reference'
                ]
                : array();

        $aBaselineIndex = array();

        foreach ($aBaselineMissing as $sFile) {
            $aBaselineIndex[
                (string)$sFile
            ] = true;
        }

        $aBaselineBacked = array();
        $aRemaining = array();

        /*
         * First remove files which UNA itself tracks but the
         * uploaded reference package omitted.
         */
        foreach ($aLiveOnly as $sFile) {
            $sFile = (string)$sFile;

            if (isset($aBaselineIndex[$sFile])) {
                $aBaselineBacked[] = $sFile;
            } else {
                $aRemaining[] = $sFile;
            }
        }

        $aRuntime = array();
        $aUnexplained = $aRemaining;
        $aRuntimeEvidence = array();

        $iTotal = count($aRemaining);

        /*
         * Same high-confidence runtime-content rule used by
         * Doctor's scanner:
         *
         * - at least 100 files
         * - >= 90% in one known runtime directory
         */
        if ($iTotal >= 100) {
            $aGroups = array();

            foreach ($aRemaining as $sFile) {
                $sNormalized =
                    str_replace(
                        '\\',
                        '/',
                        ltrim(
                            (string)$sFile,
                            '/\\'
                        )
                    );

                if ($sNormalized === '')
                    continue;

                $aParts =
                    explode(
                        '/',
                        $sNormalized
                    );

                $sTop =
                    strtolower(
                        trim(
                            (string)$aParts[0]
                        )
                    );

                if ($sTop === '')
                    $sTop = '(root)';

                if (!isset($aGroups[$sTop]))
                    $aGroups[$sTop] = 0;

                $aGroups[$sTop]++;
            }

            if (!empty($aGroups)) {
                arsort($aGroups);

                $sDominant =
                    (string)key($aGroups);

                $iDominant =
                    (int)current($aGroups);

                $fDominant =
                    $iTotal > 0
                        ? ($iDominant / $iTotal) * 100
                        : 0;

                $aRuntimeDirectories = array(
                    'media',
                    'upload',
                    'uploads',
                    'storage',
                    'cache',
                    'tmp',
                    'runtime',
                    'generated',
                );

                if (
                    in_array(
                        $sDominant,
                        $aRuntimeDirectories,
                        true
                    )
                    && $fDominant >= 90
                ) {
                    $aRuntime = array();
                    $aUnexplained = array();

                    foreach ($aRemaining as $sFile) {
                        $sNormalized =
                            str_replace(
                                '\\',
                                '/',
                                ltrim(
                                    (string)$sFile,
                                    '/\\'
                                )
                            );

                        $aParts =
                            explode(
                                '/',
                                $sNormalized
                            );

                        $sTop =
                            strtolower(
                                trim(
                                    (string)(
                                        $aParts[0]
                                        ?? ''
                                    )
                                )
                            );

                        if ($sTop === $sDominant) {
                            $aRuntime[] =
                                (string)$sFile;
                        } else {
                            $aUnexplained[] =
                                (string)$sFile;
                        }
                    }

                    $aRuntimeEvidence = array(
                        'classification' =>
                            'runtime_generated_module_content',
                        'confidence' =>
                            'high',
                        'dominant_directory' =>
                            $sDominant . '/',
                        'dominant_file_count' =>
                            count($aRuntime),
                        'dominant_percent' =>
                            round(
                                $fDominant,
                                2
                            ),
                        'candidate_live_only_count' =>
                            $iTotal,
                        'rule' =>
                            'At least 100 live-only files with at least 90% concentrated in a known runtime-content directory.',
                    );
                }
            }
        }

        sort($aBaselineBacked);
        sort($aRuntime);
        sort($aUnexplained);

        /*
         * Preserve raw live_only unchanged. These are additional
         * diagnostic classifications only.
         */
        $aComparison[
            'baseline_backed_live_only'
        ] = $aBaselineBacked;

        $aComparison[
            'runtime_generated_live_only'
        ] = $aRuntime;

        $aComparison[
            'unexplained_live_only'
        ] = $aUnexplained;

        $aComparison[
            'runtime_generated_live_only_evidence'
        ] = $aRuntimeEvidence;

        return $aComparison;
    }


    protected function summarizeComparison(
        array $aComparison,
        $iReferenceFiles,
        $iLiveFiles
    ) {
        $aBaselineBacked =
            isset(
                $aComparison[
                    'baseline_backed_modified'
                ]
            )
            && is_array(
                $aComparison[
                    'baseline_backed_modified'
                ]
            )
                ? $aComparison[
                    'baseline_backed_modified'
                ]
                : array();

        $aApproved =
            isset($aComparison['approved_modified'])
            && is_array(
                $aComparison['approved_modified']
            )
                ? $aComparison['approved_modified']
                : array();

        $aUnexplained =
            isset($aComparison['unexplained_modified'])
            && is_array(
                $aComparison['unexplained_modified']
            )
                ? $aComparison['unexplained_modified']
                : array();

        $aBaselineBackedLiveOnly =
            isset(
                $aComparison[
                    'baseline_backed_live_only'
                ]
            )
            && is_array(
                $aComparison[
                    'baseline_backed_live_only'
                ]
            )
                ? $aComparison[
                    'baseline_backed_live_only'
                ]
                : array();

        $aRuntimeLiveOnly =
            isset(
                $aComparison[
                    'runtime_generated_live_only'
                ]
            )
            && is_array(
                $aComparison[
                    'runtime_generated_live_only'
                ]
            )
                ? $aComparison[
                    'runtime_generated_live_only'
                ]
                : array();

        $aUnexplainedLiveOnly =
            isset(
                $aComparison[
                    'unexplained_live_only'
                ]
            )
            && is_array(
                $aComparison[
                    'unexplained_live_only'
                ]
            )
                ? $aComparison[
                    'unexplained_live_only'
                ]
                : array();

        return array(
            'reference_files' =>
                (int)$iReferenceFiles,
            'live_files' =>
                (int)$iLiveFiles,
            'matching_count' =>
                (int)$aComparison['matching_count'],

            'modified_count' =>
                count($aComparison['modified']),

            'baseline_backed_modified_count' =>
                count($aBaselineBacked),

            'approved_modified_count' =>
                count($aApproved),

            'unexplained_modified_count' =>
                count($aUnexplained),

            'missing_count' =>
                count($aComparison['missing']),

            'live_only_count' =>
                count($aComparison['live_only']),

            'runtime_generated_live_only_count' =>
                count($aRuntimeLiveOnly),

            'baseline_backed_live_only_count' =>
                count($aBaselineBackedLiveOnly),

            'unexplained_live_only_count' =>
                count($aUnexplainedLiveOnly),

            'modified_files' =>
                array_slice(
                    $aComparison['modified'],
                    0,
                    100
                ),

            'baseline_backed_modified_files' =>
                array_slice(
                    $aBaselineBacked,
                    0,
                    100
                ),

            'approved_modified_files' =>
                array_slice(
                    $aApproved,
                    0,
                    100
                ),

            'unexplained_modified_files' =>
                array_slice(
                    $aUnexplained,
                    0,
                    100
                ),

            'missing_files' =>
                array_slice(
                    $aComparison['missing'],
                    0,
                    100
                ),

            'live_only_files' =>
                array_slice(
                    $aComparison['live_only'],
                    0,
                    100
                ),

            'runtime_generated_live_only_files' =>
                array_slice(
                    $aRuntimeLiveOnly,
                    0,
                    20
                ),

            'baseline_backed_live_only_files' =>
                array_slice(
                    $aBaselineBackedLiveOnly,
                    0,
                    100
                ),

            'unexplained_live_only_files' =>
                array_slice(
                    $aUnexplainedLiveOnly,
                    0,
                    100
                ),

            'runtime_generated_live_only_evidence' =>
                $aComparison[
                    'runtime_generated_live_only_evidence'
                ] ?? array(),
        );
    }


    protected function summarizeBaseline(
        array $aBaseline
    ) {
        if (empty($aBaseline['available']))
            return array(
                'available' => false,
            );

        $bAuthentic =
            (int)$aBaseline['tracked_count'] > 0
            && (int)$aBaseline['matching_count']
                === (int)$aBaseline['tracked_count']
            && empty($aBaseline['different'])
            && empty(
                $aBaseline[
                    'baseline_missing_from_reference'
                ]
            );

        return array(
            'available' => true,
            'authenticity' =>
                $bAuthentic
                    ? 'verified'
                    : 'not_verified',
            'authenticity_reason' =>
                $bAuthentic
                    ? 'Every UNA-tracked file in the reference package exactly matches the recorded checksum baseline.'
                    : 'One or more UNA-tracked files do not exactly match the recorded checksum baseline.',
            'tracked_count' =>
                (int)$aBaseline['tracked_count'],
            'matching_count' =>
                (int)$aBaseline['matching_count'],
            'different_count' =>
                count($aBaseline['different']),
            'reference_untracked_count' =>
                count(
                    $aBaseline['reference_untracked']
                ),
            'baseline_missing_from_reference_count' =>
                count(
                    $aBaseline[
                        'baseline_missing_from_reference'
                    ]
                ),
            'different_files' =>
                array_slice(
                    $aBaseline['different'],
                    0,
                    100
                ),
            'reference_untracked_files' =>
                array_slice(
                    $aBaseline[
                        'reference_untracked'
                    ],
                    0,
                    100
                ),
            'baseline_missing_from_reference' =>
                array_slice(
                    $aBaseline[
                        'baseline_missing_from_reference'
                    ],
                    0,
                    100
                ),
        );
    }

    protected function persistComparison(
        $sUpload,
        array $aResult,
        array $aComparison,
        array $aBaseline
    ) {
        $sRoot =
            rtrim(
                BX_DIRECTORY_PATH_ROOT,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR
            . 'tmp'
            . DIRECTORY_SEPARATOR
            . 'my-una-doctor-reference-packages';

        if (
            !is_dir($sRoot)
            && !@mkdir($sRoot, 0700, true)
        ) {
            throw new RuntimeException(
                'Doctor could not create its protected reference-package evidence directory.'
            );
        }

        @chmod($sRoot, 0700);

        $sSession =
            date('Ymd-His')
            . '-'
            . bin2hex(random_bytes(6));

        $sDir =
            $sRoot
            . DIRECTORY_SEPARATOR
            . $sSession;

        if (!@mkdir($sDir, 0700, true))
            throw new RuntimeException(
                'Doctor could not create the reference comparison session directory.'
            );

        @chmod($sDir, 0700);

        $sZip =
            $sDir
            . DIRECTORY_SEPARATOR
            . 'reference.zip';

        if (!@copy($sUpload, $sZip))
            throw new RuntimeException(
                'Doctor could not preserve the verified reference ZIP.'
            );

        @chmod($sZip, 0600);

        $aReport = array(
            'created_utc' =>
                gmdate('Y-m-d H:i:s'),
            'result' => $aResult,
            'full_comparison' =>
                $aComparison,
            'full_baseline_comparison' =>
                $aBaseline,
        );

        $sJson =
            $sDir
            . DIRECTORY_SEPARATOR
            . 'comparison.json';

        $sEncoded = json_encode(
            $aReport,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
        );

        if (
            !is_string($sEncoded)
            || @file_put_contents(
                $sJson,
                $sEncoded,
                LOCK_EX
            ) === false
        ) {
            throw new RuntimeException(
                'Doctor could not preserve the comparison evidence.'
            );
        }

        @chmod($sJson, 0600);

        return array(
            'session_id' => $sSession,
            'reference_zip' => $sZip,
            'comparison_report' => $sJson,
            'reference_preserved' => true,
        );
    }
}
