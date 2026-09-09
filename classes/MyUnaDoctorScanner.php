<?php defined('BX_DOL') or die('hack attempt');

class MyUnaDoctorScanner
{
    protected $oModule;
    protected $oDb;
    protected $aItems = array();
    protected $aTableExistsCache = array();
    protected $aTableFieldsCache = array();
    protected $aModuleBackupFiles = array();
    protected $aRuntimeContentCandidates = array();

    public function __construct($oModule)
    {
        $this->oModule = $oModule;
        $this->oDb = BxDolDb::getInstance();
    }

    public function run()
    {
        $fStart = microtime(true);
        $iStarted = time();

        $this->guarded('system', array($this, 'scanSystem'));
        $this->guarded(
            'compatibility',
            array(
                $this,
                'scanCompatibilityIntelligence'
            )
        );
        $this->guarded(
            'compatibility',
            array(
                $this,
                'scanUnaPhpExtensionsCompatibility'
            )
        );
        $this->guarded(
            'compatibility',
            array(
                $this,
                'scanInstalledModuleCompatibility'
            )
        );
        $this->guarded(
            'compatibility',
            array(
                $this,
                'scanUnaUpgradeTargetIntelligence'
            )
        );
        $this->guarded(
            'maintenance',
            array(
                $this,
                'scanMaintenanceUpgradeState'
            )
        );
        $this->guarded(
            'upgrade_readiness',
            array(
                $this,
                'scanUpgradeReadinessSummary'
            )
        );
        $this->guarded('filesystem', array($this, 'scanFilesystem'));
        $this->guarded('database', array($this, 'scanDatabase'));
        $this->guarded('storage', array($this, 'scanStorageMedia'));
        $this->guarded('modules', array($this, 'scanModules'));
        $this->guarded('file_integrity', array($this, 'scanFileIntegrity'));
        $this->guarded('repairs', array($this, 'scanRepairCandidates'));
        $this->guarded('notifications', array($this, 'scanNotifications'));
        $this->guarded('cron', array($this, 'scanCron'));
        $this->guarded('logs', array($this, 'scanLogs'));

        $aCounts = array('critical' => 0, 'warning' => 0, 'info' => 0, 'ok' => 0);
        foreach ($this->aItems as $aItem) {
            if (isset($aCounts[$aItem['severity']]))
                $aCounts[$aItem['severity']]++;
        }

        $iScore = 100 - ($aCounts['critical'] * 20) - ($aCounts['warning'] * 5);
        $iScore = max(0, min(100, $iScore));
        $iFinished = time();

        $sModuleVersion = (string)$this->oDb->getOne(
            "SELECT `version` FROM `sys_modules` WHERE `name` = 'my_una_doctor' LIMIT 1"
        );
        if ($sModuleVersion === '')
            $sModuleVersion = 'unknown';

        return array(
            'schema_version' => 1,
            'product' => 'My UNA Doctor',
            'module_version' => $sModuleVersion,
            'started' => $iStarted,
            'finished' => $iFinished,
            'duration_ms' => (int)round((microtime(true) - $fStart) * 1000),
            'summary' => array(
                'health_score' => $iScore,
                'critical' => $aCounts['critical'],
                'warning' => $aCounts['warning'],
                'info' => $aCounts['info'],
                'ok' => $aCounts['ok'],
                'checks' => count($this->aItems),
            ),
            'items' => $this->aItems,
        );
    }

    protected function guarded($sCategory, $aCallback)
    {
        try {
            call_user_func($aCallback);
        } catch (Throwable $e) {
            $this->add($sCategory, 'warning', 'Scanner could not complete this section', $e->getMessage());
        }
    }

    protected function add($sCategory, $sSeverity, $sTitle, $sDetail = '', array $aEvidence = array())
    {
        $this->aItems[] = array(
            'category' => (string)$sCategory,
            'severity' => (string)$sSeverity,
            'title' => (string)$sTitle,
            'detail' => (string)$sDetail,
            'evidence' => $aEvidence,
        );
    }

    protected function scanSystem()
    {
        $sFilesVersion = defined('BX_DOL_VERSION') ? (string)BX_DOL_VERSION : 'unknown';
        $sDbVersion = (string)$this->oDb->getOne("SELECT `version` FROM `sys_modules` WHERE `name` = 'system' LIMIT 1");

        if ($sFilesVersion !== 'unknown' && $sDbVersion !== '' && $sFilesVersion !== $sDbVersion) {
            $this->add('system', 'critical', 'UNA file and database versions do not match',
                'The UNA application files and the system module database record report different versions.',
                array('files_version' => $sFilesVersion, 'database_version' => $sDbVersion));
        } else {
            $this->add('system', 'ok', 'UNA file and database versions agree', '',
                array('files_version' => $sFilesVersion, 'database_version' => $sDbVersion));
        }

    }


    /**
     * Compare the PHP runtime executing Doctor with the minimum
     * PHP requirement declared by this installed UNA source.
     *
     * Diagnostic only. Meeting the minimum requirement is not
     * treated as certification of every third-party module or
     * of a future UNA upgrade target.
     */
    protected function scanCompatibilityIntelligence()
    {
        $sPhpVersion =
            (string)PHP_VERSION;

        $iPhpVersionId =
            defined('PHP_VERSION_ID')
                ? (int)PHP_VERSION_ID
                : 0;

        $sPhpSapi =
            (string)PHP_SAPI;

        $sUnaFilesVersion =
            defined('BX_DOL_VERSION')
                ? (string)BX_DOL_VERSION
                : '';

        $sUnaDatabaseVersion =
            (string)$this->oDb->getOne(
                "SELECT `version`
                 FROM `sys_modules`
                 WHERE `name` = 'system'
                 LIMIT 1"
            );

        $aRequirement =
            $this->detectUnaMinimumPhpRequirement();

        $sMinimumPhp =
            (string)(
                $aRequirement['version']
                ?? ''
            );

        $aEvidence = array(
            'scope' =>
                'current_installed_una_runtime',

            'php_version' =>
                $sPhpVersion,

            'php_version_id' =>
                $iPhpVersionId,

            'php_sapi' =>
                $sPhpSapi,

            'una_files_version' =>
                $sUnaFilesVersion,

            'una_database_version' =>
                $sUnaDatabaseVersion,

            'minimum_php_version' =>
                $sMinimumPhp,

            'minimum_php_source' =>
                (string)(
                    $aRequirement['source']
                    ?? 'unavailable'
                ),

            'minimum_php_source_path' =>
                (string)(
                    $aRequirement['source_path']
                    ?? ''
                ),

            'minimum_php_source_sha256' =>
                (string)(
                    $aRequirement['source_sha256']
                    ?? ''
                ),

            'requirement_confidence' =>
                (string)(
                    $aRequirement['confidence']
                    ?? 'unknown'
                ),

            'requirement_detection_reason' =>
                (string)(
                    $aRequirement['reason']
                    ?? ''
                ),

            'minimum_only' =>
                true,

            'future_upgrade_target_evaluated' =>
                false,

            'third_party_modules_certified' =>
                false,

            'changes_applied' =>
                false,
        );

        /*
         * If Doctor cannot establish the requirement from this
         * UNA installation, do not silently call compatibility
         * healthy. The old 8.1 baseline is retained only as an
         * explicitly identified fallback, not as claimed local
         * UNA evidence.
         */
        if ($sMinimumPhp === '') {
            $sFallbackMinimum =
                '8.1.0';

            $aEvidence['minimum_php_version'] =
                $sFallbackMinimum;

            $aEvidence['minimum_php_source'] =
                'doctor_fallback';

            $aEvidence['requirement_confidence'] =
                'fallback';

            $aEvidence['meets_minimum'] =
                version_compare(
                    $sPhpVersion,
                    $sFallbackMinimum,
                    '>='
                );

            $this->add(
                'compatibility',
                'warning',
                'UNA local PHP requirement could not be verified',
                'Doctor could not establish the minimum PHP requirement from this installed UNA source. PHP was compared with the conservative Doctor fallback of 8.1.0, but the result is not being treated as verified UNA compatibility.',
                $aEvidence
            );

            return;
        }

        $bMeetsMinimum =
            version_compare(
                $sPhpVersion,
                $sMinimumPhp,
                '>='
            );

        $aEvidence['meets_minimum'] =
            $bMeetsMinimum;

        if (!$bMeetsMinimum) {
            $this->add(
                'compatibility',
                'critical',
                'PHP runtime is below this UNA installation minimum',
                'The PHP runtime executing My UNA Doctor is older than the minimum PHP version declared by the installed UNA Studio audit. Upgrade PHP only after confirming the hosting environment, UNA build and installed modules are compatible.',
                $aEvidence
            );

            return;
        }

        $this->add(
            'compatibility',
            'ok',
            'PHP runtime meets this UNA installation minimum',
            'Doctor verified the current PHP runtime against the minimum PHP version declared by this installed UNA source. This confirms the UNA minimum-version requirement only; it does not certify every third-party module or a future UNA upgrade target.',
            $aEvidence
        );
    }


    /**
     * Verify PHP modules required by this installed UNA source.
     *
     * Diagnostic only. Doctor derives the requirement from
     * UNA's local Studio audit instead of maintaining its own
     * fixed extension list.
     */
    protected function scanUnaPhpExtensionsCompatibility()
    {
        $aRequirement =
            $this->detectUnaRequiredPhpExtensions();

        $aRequired =
            isset($aRequirement['extensions'])
            && is_array($aRequirement['extensions'])
                ? $aRequirement['extensions']
                : array();

        $aLoaded = array();
        $aMissing = array();

        foreach ($aRequired as $sExtension) {
            if (extension_loaded($sExtension))
                $aLoaded[] = $sExtension;
            else
                $aMissing[] = $sExtension;
        }

        $aEvidence = array(
            'scope' =>
                'current_installed_una_php_extensions',

            'required_extensions' =>
                $aRequired,

            'required_extension_count' =>
                count($aRequired),

            'loaded_required_extensions' =>
                $aLoaded,

            'loaded_required_count' =>
                count($aLoaded),

            'missing_required_extensions' =>
                $aMissing,

            'missing_required_count' =>
                count($aMissing),

            'requirement_source' =>
                (string)(
                    $aRequirement['source']
                    ?? 'unavailable'
                ),

            'requirement_source_path' =>
                (string)(
                    $aRequirement['source_path']
                    ?? ''
                ),

            'requirement_source_sha256' =>
                (string)(
                    $aRequirement['source_sha256']
                    ?? ''
                ),

            'requirement_confidence' =>
                (string)(
                    $aRequirement['confidence']
                    ?? 'unknown'
                ),

            'requirement_detection_reason' =>
                (string)(
                    $aRequirement['reason']
                    ?? ''
                ),

            'php_version' =>
                (string)PHP_VERSION,

            'php_sapi' =>
                (string)PHP_SAPI,

            'changes_applied' =>
                false,
        );

        /*
         * Do not silently substitute Doctor's former hardcoded
         * list when the installed UNA source cannot be verified.
         */
        if (empty($aRequired)) {
            $this->add(
                'compatibility',
                'warning',
                'UNA PHP extension requirements could not be verified',
                'Doctor could not extract the required PHP-module list from this installed UNA Studio audit. No Doctor-owned extension list was substituted.',
                $aEvidence
            );

            return;
        }

        if (!empty($aMissing)) {
            $this->add(
                'compatibility',
                'critical',
                'Required PHP extensions for this UNA installation are missing',
                'One or more PHP extensions declared by this installed UNA Studio audit are not loaded in the PHP runtime currently serving Doctor.',
                $aEvidence
            );

            return;
        }

        $this->add(
            'compatibility',
            'ok',
            'Required PHP extensions for this UNA installation are available',
            'Doctor verified the PHP modules declared by this installed UNA Studio audit against the PHP runtime currently executing the scan.',
            $aEvidence
        );
    }


    /**
     * Evaluate registered module package compatibility using
     * UNA's own current compatibility/dependency semantics.
     *
     * Diagnostic only.
     */
    protected function scanInstalledModuleCompatibility()
    {
        $sUnaVersion =
            function_exists('bx_get_ver')
                ? (string)bx_get_ver()
                : (
                    defined('BX_DOL_VERSION')
                        ? (string)BX_DOL_VERSION
                        : ''
                );

        $aModules =
            $this->oDb->getAll(
                "SELECT
                    `name`,
                    `title`,
                    `version`,
                    `path`,
                    `uri`,
                    `enabled`
                 FROM `sys_modules`
                 WHERE `name` <> 'system'
                 ORDER BY `name`"
            );

        $iModulesChecked = 0;
        $iCompatibleDeclarations = 0;
        $iCompatible = 0;
        $iDependencyModules = 0;
        $iDependencyDeclarations = 0;
        $iDependencyEnforcementModules = 0;

        $aIncompatible = array();
        $aUnverified = array();
        $aDependencies = array();
        $aUnsatisfiedDependencies = array();
        $aDependencyEnforcementMissing = array();

        foreach ($aModules as $aModule) {
            $iModulesChecked++;

            $sModuleName =
                (string)($aModule['name'] ?? '');

            $sModuleTitle =
                (string)($aModule['title'] ?? '');

            $sModuleVersion =
                (string)($aModule['version'] ?? '');

            $sModulePath =
                trim(
                    str_replace(
                        array('\\', '..'),
                        array('/', ''),
                        (string)($aModule['path'] ?? '')
                    ),
                    '/'
                );

            $bModuleEnabled =
                !empty($aModule['enabled']);

            $sRelativeConfig =
                'modules/'
                . $sModulePath
                . '/install/config.php';

            $sConfigPath =
                rtrim(
                    BX_DIRECTORY_PATH_ROOT,
                    DIRECTORY_SEPARATOR
                )
                . DIRECTORY_SEPARATOR
                . str_replace(
                    '/',
                    DIRECTORY_SEPARATOR,
                    $sRelativeConfig
                );

            if (
                $sModulePath === ''
                || !is_file($sConfigPath)
                || !is_readable($sConfigPath)
            ) {
                $aUnverified[] = array(
                    'module' => $sModuleName,
                    'title' => $sModuleTitle,
                    'version' => $sModuleVersion,
                    'enabled' => $bModuleEnabled,
                    'config' => $sRelativeConfig,
                    'reason' =>
                        'package_config_missing_or_unreadable',
                );

                continue;
            }

            $iSize =
                (int)@filesize($sConfigPath);

            if (
                $iSize <= 0
                || $iSize > 1048576
            ) {
                $aUnverified[] = array(
                    'module' => $sModuleName,
                    'title' => $sModuleTitle,
                    'version' => $sModuleVersion,
                    'enabled' => $bModuleEnabled,
                    'config' => $sRelativeConfig,
                    'reason' =>
                        'package_config_size_unexpected',
                );

                continue;
            }

            $sSource =
                @file_get_contents($sConfigPath);

            if ($sSource === false) {
                $aUnverified[] = array(
                    'module' => $sModuleName,
                    'title' => $sModuleTitle,
                    'version' => $sModuleVersion,
                    'enabled' => $bModuleEnabled,
                    'config' => $sRelativeConfig,
                    'reason' =>
                        'package_config_read_failed',
                );

                continue;
            }

            /*
             * compatible_with
             *
             * Mirror UNA core _isCompatibleWith():
             * current UNA version >= ANY declared entry.
             */
            $sCompatibleBody =
                $this->extractSimpleConfigArrayBody(
                    $sSource,
                    'compatible_with'
                );

            $aCompatibleWith =
                $sCompatibleBody === null
                    ? array()
                    : $this->parseQuotedStringList(
                        $sCompatibleBody
                    );

            if (empty($aCompatibleWith)) {
                $aUnverified[] = array(
                    'module' => $sModuleName,
                    'title' => $sModuleTitle,
                    'version' => $sModuleVersion,
                    'enabled' => $bModuleEnabled,
                    'config' => $sRelativeConfig,
                    'reason' =>
                        'compatible_with_missing_or_unparsed',
                );
            } else {
                $iCompatibleDeclarations++;

                $bCompatible = false;

                foreach (
                    $aCompatibleWith
                    as $sRequiredVersion
                ) {
                    if (
                        version_compare(
                            strtolower($sUnaVersion),
                            strtolower($sRequiredVersion),
                            '>='
                        ) == 1
                    ) {
                        $bCompatible = true;
                        break;
                    }
                }

                if ($bCompatible) {
                    $iCompatible++;
                } else {
                    $aIncompatible[] = array(
                        'module' => $sModuleName,
                        'title' => $sModuleTitle,
                        'module_version' => $sModuleVersion,
                        'enabled' => $bModuleEnabled,
                        'compatible_with' =>
                            $aCompatibleWith,
                        'una_version' =>
                            $sUnaVersion,
                        'comparison' =>
                            'UNA core: current >= any compatible_with entry',
                    );
                }
            }

            /*
             * dependencies
             *
             * UNA uses the array KEY as the dependency identifier.
             * The array VALUE is not version-compared.
             */
            $sDependenciesBody =
                $this->extractSimpleConfigArrayBody(
                    $sSource,
                    'dependencies'
                );

            $aDeclaredDependencies =
                $sDependenciesBody === null
                    ? array()
                    : $this->parseDependencyPairs(
                        $sDependenciesBody
                    );

            if (empty($aDeclaredDependencies))
                continue;

            $iDependencyModules++;

            $bInstallChecks =
                $this->configOperationChecksDependencies(
                    $sSource,
                    'install'
                );

            $bEnableChecks =
                $this->configOperationChecksDependencies(
                    $sSource,
                    'enable'
                );

            $bUpdateChecks =
                $this->configOperationChecksDependencies(
                    $sSource,
                    'update'
                );

            if (
                $bInstallChecks
                || $bEnableChecks
                || $bUpdateChecks
            ) {
                $iDependencyEnforcementModules++;
            } else {
                $aDependencyEnforcementMissing[] = array(
                    'module' => $sModuleName,
                    'title' => $sModuleTitle,
                    'enabled' => $bModuleEnabled,
                    'declared_dependencies' =>
                        array_keys($aDeclaredDependencies),
                    'install_check_dependencies' => false,
                    'enable_check_dependencies' => false,
                    'update_check_dependencies' => false,
                );
            }

            foreach (
                $aDeclaredDependencies
                as $sDependency => $sDeclaredValue
            ) {
                $iDependencyDeclarations++;

                $aTarget =
                    $this->oDb->getRow(
                        "SELECT
                            `name`,
                            `title`,
                            `version`,
                            `uri`,
                            `enabled`
                         FROM `sys_modules`
                         WHERE `uri` = ?
                            OR `name` = ?
                         ORDER BY `enabled` DESC
                         LIMIT 1",
                        array(
                            $sDependency,
                            $sDependency,
                        )
                    );

                $bFound =
                    is_array($aTarget)
                    && !empty($aTarget);

                $bEnabled =
                    $bFound
                    && !empty($aTarget['enabled']);

                $sStatus =
                    !$bFound
                        ? 'missing'
                        : (
                            !$bEnabled
                                ? 'disabled'
                                : 'satisfied'
                        );

                $aDependency = array(
                    'module' => $sModuleName,
                    'module_title' => $sModuleTitle,
                    'module_enabled' => $bModuleEnabled,

                    'dependency' => $sDependency,

                    'declared_value' =>
                        $sDeclaredValue,

                    'declared_value_semantics' =>
                        'display_label_in_una_core_dependency_check_not_version_constraint',

                    'status' => $sStatus,

                    'target_name' =>
                        $bFound
                            ? (string)(
                                $aTarget['name']
                                ?? ''
                            )
                            : '',

                    'target_uri' =>
                        $bFound
                            ? (string)(
                                $aTarget['uri']
                                ?? ''
                            )
                            : '',

                    'target_version' =>
                        $bFound
                            ? (string)(
                                $aTarget['version']
                                ?? ''
                            )
                            : '',

                    'target_enabled' =>
                        $bEnabled,

                    'install_check_dependencies' =>
                        $bInstallChecks,

                    'enable_check_dependencies' =>
                        $bEnableChecks,

                    'update_check_dependencies' =>
                        $bUpdateChecks,
                );

                $aDependencies[] =
                    $aDependency;

                if ($sStatus !== 'satisfied') {
                    $aUnsatisfiedDependencies[] =
                        $aDependency;
                }
            }
        }

        $aEvidence = array(
            'scope' =>
                'registered_module_package_compatibility',

            'una_version' =>
                $sUnaVersion,

            'modules_checked' =>
                $iModulesChecked,

            'compatible_with_declared_count' =>
                $iCompatibleDeclarations,

            'una_core_compatible_count' =>
                $iCompatible,

            'una_core_incompatible_count' =>
                count($aIncompatible),

            'unverified_count' =>
                count($aUnverified),

            'incompatible_modules' =>
                $aIncompatible,

            'unverified_modules' =>
                $aUnverified,

            'dependency_module_count' =>
                $iDependencyModules,

            'dependency_declaration_count' =>
                $iDependencyDeclarations,

            'dependency_enforcement_module_count' =>
                $iDependencyEnforcementModules,

            'dependency_enforcement_missing' =>
                $aDependencyEnforcementMissing,

            'dependencies' =>
                $aDependencies,

            'unsatisfied_dependency_count' =>
                count($aUnsatisfiedDependencies),

            'unsatisfied_dependencies' =>
                $aUnsatisfiedDependencies,

            'compatible_with_semantics' =>
                'Mirrors UNA core _isCompatibleWith(): current UNA version >= any declared compatible_with entry.',

            'dependency_semantics' =>
                'Mirrors UNA actionCheckDependencies(): dependency identifier must resolve to an installed and enabled module; the array value is not version-compared.',

            'unregistered_packages_evaluated' =>
                false,

            'changes_applied' =>
                false,
        );

        /*
         * Compatibility metadata is an installation/package
         * signal. Do not call it a current fatal runtime failure
         * without stronger evidence.
         */
        if (
            !empty($aIncompatible)
            || !empty($aUnsatisfiedDependencies)
            || !empty($aUnverified)
        ) {
            $this->add(
                'compatibility',
                'warning',
                'Installed module compatibility requires review',
                'Doctor found an incompatible or unverifiable registered module package declaration, or a declared dependency that is missing or disabled. No repair or package change was made.',
                $aEvidence
            );

            return;
        }

        /*
         * A package can declare dependencies without wiring
         * check_dependencies into its install/enable/update
         * operations. Keep this visible without reducing health
         * when the dependency itself is currently satisfied.
         */
        if (!empty($aDependencyEnforcementMissing)) {
            $this->add(
                'compatibility',
                'info',
                'Module dependencies are satisfied but installer enforcement is not configured',
                'Doctor found module dependency declarations whose package operation arrays do not enable UNA check_dependencies. Current declared dependencies are satisfied, but UNA installer enforcement is not configured for those packages.',
                $aEvidence
            );

            return;
        }

        $this->add(
            'compatibility',
            'ok',
            'Installed module compatibility declarations are satisfied',
            'Doctor evaluated registered module packages against the current UNA version using UNA core compatible_with semantics and checked declared dependencies using UNA installed-and-enabled semantics.',
            $aEvidence
        );
    }


    /**
     * Extract a simple PHP array body from install/config.php
     * without executing the module's PHP file.
     */
    protected function extractSimpleConfigArrayBody(
        $sSource,
        $sKey
    ) {
        $sPattern =
            "~['\"]"
            . preg_quote($sKey, '~')
            . "['\"]\\s*=>\\s*(?:array\\s*\\((.*?)\\)|\\[(.*?)\\])~s";

        $aMatch = array();

        if (
            preg_match(
                $sPattern,
                $sSource,
                $aMatch
            ) !== 1
        ) {
            return null;
        }

        if (
            array_key_exists(1, $aMatch)
            && $aMatch[1] !== ''
        ) {
            return (string)$aMatch[1];
        }

        if (array_key_exists(2, $aMatch))
            return (string)$aMatch[2];

        return '';
    }


    protected function parseQuotedStringList($sBody)
    {
        $aValues = array();
        $aMatches = array();

        $iMatched =
            preg_match_all(
                "~['\"]([^'\"]+)['\"]~",
                (string)$sBody,
                $aMatches
            );

        if (
            $iMatched === false
            || $iMatched < 1
        ) {
            return $aValues;
        }

        foreach (
            ($aMatches[1] ?? array())
            as $sValue
        ) {
            $sValue =
                trim((string)$sValue);

            if (
                $sValue !== ''
                && !in_array(
                    $sValue,
                    $aValues,
                    true
                )
            ) {
                $aValues[] =
                    $sValue;
            }
        }

        return $aValues;
    }


    protected function parseDependencyPairs($sBody)
    {
        $aPairs = array();
        $aMatches = array();

        $iMatched =
            preg_match_all(
                "~['\"]([^'\"]+)['\"]\\s*=>\\s*['\"]([^'\"]*)['\"]~",
                (string)$sBody,
                $aMatches,
                PREG_SET_ORDER
            );

        if (
            $iMatched === false
            || $iMatched < 1
        ) {
            return $aPairs;
        }

        foreach ($aMatches as $aMatch) {
            $sIdentifier =
                trim(
                    (string)(
                        $aMatch[1]
                        ?? ''
                    )
                );

            $sDeclaredValue =
                trim(
                    (string)(
                        $aMatch[2]
                        ?? ''
                    )
                );

            if ($sIdentifier === '')
                continue;

            $aPairs[$sIdentifier] =
                $sDeclaredValue;
        }

        return $aPairs;
    }


    protected function configOperationChecksDependencies(
        $sSource,
        $sOperation
    ) {
        $sBody =
            $this->extractSimpleConfigArrayBody(
                $sSource,
                $sOperation
            );

        if ($sBody === null)
            return false;

        return preg_match(
            "~['\"]check_dependencies['\"]\\s*=>\\s*1\\b~",
            $sBody
        ) === 1;
    }


    /**
     * Ask UNA's own version service whether a newer system
     * target is currently advertised.
     *
     * This check does not download, unpack, execute or schedule
     * an upgrade patch.
     */
    protected function scanUnaUpgradeTargetIntelligence()
    {
        $sCurrentVersion =
            function_exists('bx_get_ver')
                ? (string)bx_get_ver()
                : (
                    defined('BX_DOL_VERSION')
                        ? (string)BX_DOL_VERSION
                        : ''
                );

        $sConfiguredChannel =
            (string)getParam(
                'sys_upgrade_channel'
            );

        $sEffectiveChannel =
            strtolower($sConfiguredChannel) === 'beta'
                ? 'beta'
                : 'stable';

        $aEvidence = array(
            'scope' =>
                'official_una_upgrade_target',

            'current_una_version' =>
                $sCurrentVersion,

            'configured_upgrade_channel' =>
                $sConfiguredChannel,

            'effective_upgrade_feed' =>
                $sEffectiveChannel,

            'latest_advertised_version' =>
                '',

            'newer_target_available' =>
                false,

            'patch_metadata_available' =>
                false,

            'patch_version' =>
                '',

            'patch_md5_present' =>
                false,

            'patch_url_present' =>
                false,

            'metadata_source' =>
                'una_core_BxDolUpgrader_getVersionUpdateInfo',

            'target_requirements_evaluated' =>
                false,

            'target_patch_downloaded' =>
                false,

            'target_patch_check_executed' =>
                false,

            'staged_patch_detected' =>
                false,

            'staged_patch_filename' =>
                '',

            'staged_patch_bytes' =>
                0,

            'staged_patch_md5_verified' =>
                false,

            'staged_patch_trusted_for_static_inspection' =>
                false,

            'staged_patch_trust_reason' =>
                '',

            'staged_patch_structure_inspected' =>
                false,

            'staged_patch_structure_valid' =>
                false,

            'staged_patch_check_file_present' =>
                false,

            'staged_patch_version_file_present' =>
                false,

            'staged_patch_upgrade_cron_present' =>
                false,

            'staged_target_version_read' =>
                false,

            'staged_target_version' =>
                '',

            'staged_target_version_matches_patch' =>
                false,

            'staged_target_version_source' =>
                '',

            'staged_check_bytes' =>
                0,

            'staged_check_sha256' =>
                '',

            'staged_check_static_only' =>
                true,

            'staged_static_evidence_found' =>
                false,

            'staged_static_php_thresholds' =>
                array(),

            'staged_static_highest_php_threshold' =>
                '',

            'staged_static_php_threshold_met' =>
                null,

            'staged_static_extension_checks' =>
                array(),

            'staged_static_missing_extensions' =>
                array(),

            'staged_static_observed_checks_met' =>
                null,

            'staged_static_preflight_scope' =>
                'literal_patterns_only',

            'staged_full_target_preflight_executed' =>
                false,

            'staged_full_target_preflight_certified' =>
                false,

            'changes_applied' =>
                false,
        );

        if (!class_exists('BxDolUpgrader')) {
            $aEvidence['reason'] =
                'una_upgrader_class_unavailable';

            $this->add(
                'compatibility',
                'info',
                'UNA upgrade target status could not be verified',
                'Doctor could not access UNA core upgrade metadata. No target version was assumed.',
                $aEvidence
            );

            return;
        }

        try {
            $oUpgrader =
                new BxDolUpgrader();

            $aInfo =
                $oUpgrader
                    ->getVersionUpdateInfo();

        } catch (Throwable $e) {
            $aEvidence['reason'] =
                'official_version_metadata_request_failed';

            $aEvidence['error'] =
                $e->getMessage();

            $this->add(
                'compatibility',
                'info',
                'UNA upgrade target status could not be verified',
                'UNA official version metadata could not be retrieved. Doctor made no upgrade-readiness assumption.',
                $aEvidence
            );

            return;
        }

        if (
            !is_array($aInfo)
            || empty($aInfo['latest_version'])
        ) {
            $aEvidence['reason'] =
                'latest_version_not_supplied';

            $this->add(
                'compatibility',
                'info',
                'UNA upgrade target status could not be verified',
                'UNA did not return a usable latest-version value. Doctor made no future-version compatibility claim.',
                $aEvidence
            );

            return;
        }

        $sLatestVersion =
            (string)$aInfo['latest_version'];

        $bNewer =
            $sCurrentVersion !== ''
            && version_compare(
                strtolower($sLatestVersion),
                strtolower($sCurrentVersion),
                '>'
            );

        $aPatch =
            isset($aInfo['patch'])
            && is_array($aInfo['patch'])
                ? $aInfo['patch']
                : array();

        $aEvidence['latest_advertised_version'] =
            $sLatestVersion;

        $aEvidence['newer_target_available'] =
            (bool)$bNewer;

        $aEvidence['patch_metadata_available'] =
            !empty($aPatch);

        $aEvidence['patch_version'] =
            (string)(
                $aPatch['ver']
                ?? ''
            );

        $aEvidence['patch_md5_present'] =
            !empty($aPatch['md5']);

        $aEvidence['patch_url_present'] =
            !empty($aPatch['url']);

        /*
         * A patch may already exist in UNA tmp after an
         * administrator or UNA core downloaded it.
         *
         * Doctor never downloads the patch here. If a matching
         * local ZIP exists, Doctor inspects its archive directory
         * only. No archive content is extracted or executed.
         */
        if (
            $bNewer
            && !empty($aPatch)
        ) {
            $aStaged =
                $this->inspectStagedUnaUpgradePatch(
                    $sCurrentVersion,
                    $aPatch
                );

            foreach (
                $aStaged
                as $sKey => $mixedValue
            ) {
                $aEvidence[$sKey] =
                    $mixedValue;
            }
        }

        if (!$bNewer) {
            $aEvidence['reason'] =
                'no_newer_target_advertised';

            $this->add(
                'compatibility',
                'ok',
                'No newer UNA upgrade target is currently advertised',
                'Doctor checked UNA official version metadata for the configured upgrade channel. The advertised version is not newer than the currently installed UNA version. This does not certify compatibility with any future release.',
                $aEvidence
            );

            return;
        }

        if (!empty($aPatch)) {
            $aEvidence['reason'] =
                'newer_target_and_patch_metadata_available';

            $this->add(
                'compatibility',
                'info',
                'A newer UNA upgrade target is advertised',
                'UNA official metadata reports a newer version and provides patch metadata. Doctor has not downloaded or executed the patch, so target-specific PHP and environment requirements have not yet been evaluated.',
                $aEvidence
            );

            return;
        }

        $aEvidence['reason'] =
            'newer_target_without_patch_metadata';

        $this->add(
            'compatibility',
            'info',
            'A newer UNA version is advertised without upgrade patch metadata',
            'UNA official metadata reports a newer version, but no upgrade patch metadata was supplied. Doctor cannot evaluate the target package from this response.',
            $aEvidence
        );
    }


    /**
     * Inspect an UNA system upgrade ZIP already present in tmp.
     *
     * Read-only:
     * - does not download
     * - does not extract
     * - does not include check.php
     * - does not schedule an upgrade
     */
    protected function inspectStagedUnaUpgradePatch(
        $sCurrentVersion,
        array $aPatch
    ) {
        $aResult = array(
            'staged_patch_detected' => false,
            'staged_patch_filename' => '',
            'staged_patch_bytes' => 0,
            'staged_patch_md5_verified' => false,
            'staged_patch_trusted_for_static_inspection' => false,
            'staged_patch_trust_reason' => '',
            'staged_patch_structure_inspected' => false,
            'staged_patch_structure_valid' => false,
            'staged_patch_check_file_present' => false,
            'staged_patch_version_file_present' => false,
            'staged_patch_upgrade_cron_present' => false,
            'staged_target_version_read' => false,
            'staged_target_version' => '',
            'staged_target_version_matches_patch' => false,
            'staged_target_version_source' => '',
            'staged_check_bytes' => 0,
            'staged_check_sha256' => '',
            'staged_check_static_only' => true,
            'staged_static_evidence_found' => false,
            'staged_static_php_thresholds' => array(),
            'staged_static_highest_php_threshold' => '',
            'staged_static_php_threshold_met' => null,
            'staged_static_extension_checks' => array(),
            'staged_static_missing_extensions' => array(),
            'staged_static_observed_checks_met' => null,
            'staged_static_preflight_scope' => 'literal_patterns_only',
            'staged_full_target_preflight_executed' => false,
            'staged_full_target_preflight_certified' => false,
        );

        $sPatchVersion =
            trim(
                (string)(
                    $aPatch['ver']
                    ?? ''
                )
            );

        if (
            $sCurrentVersion === ''
            || $sPatchVersion === ''
        ) {
            return $aResult;
        }

        /*
         * Remote version metadata must never become an arbitrary
         * filesystem path.
         */
        if (
            preg_match(
                '/^[A-Za-z0-9._-]+$/',
                $sCurrentVersion
            ) !== 1
            || preg_match(
                '/^[A-Za-z0-9._-]+$/',
                $sPatchVersion
            ) !== 1
        ) {
            return $aResult;
        }

        $sFilename =
            'patch_'
            . $sCurrentVersion
            . '_'
            . $sPatchVersion
            . '.zip';

        $sPath =
            rtrim(
                BX_DIRECTORY_PATH_TMP,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR
            . $sFilename;

        $aResult['staged_patch_filename'] =
            $sFilename;

        if (!is_file($sPath))
            return $aResult;

        $aResult['staged_patch_detected'] =
            true;

        $aResult['staged_patch_bytes'] =
            (int)@filesize($sPath);

        $sExpectedMd5 =
            strtolower(
                trim(
                    (string)(
                        $aPatch['md5']
                        ?? ''
                    )
                )
            );

        if (
            preg_match(
                '/^[a-f0-9]{32}$/',
                $sExpectedMd5
            ) !== 1
        ) {
            $aResult['staged_patch_trust_reason'] =
                'official_patch_md5_unavailable_or_invalid';

            return $aResult;
        }

        $sActualMd5 =
            strtolower(
                (string)@md5_file(
                    $sPath
                )
            );

        $aResult['staged_patch_md5_verified'] =
            $sActualMd5 !== ''
            && hash_equals(
                $sExpectedMd5,
                $sActualMd5
            );

        if (!$aResult['staged_patch_md5_verified']) {
            $aResult['staged_patch_trust_reason'] =
                'staged_patch_md5_mismatch';

            return $aResult;
        }

        if (!class_exists('ZipArchive')) {
            $aResult['staged_patch_trust_reason'] =
                'ziparchive_unavailable';

            return $aResult;
        }

        $oZip =
            new ZipArchive();

        if (
            $oZip->open($sPath)
            !== true
        ) {
            return $aResult;
        }

        $aResult['staged_patch_structure_inspected'] =
            true;

        $sCurrentNormalized =
            str_replace(
                array('-', '_', ' '),
                '.',
                $sCurrentVersion
            );

        $sPatchNormalized =
            str_replace(
                array('-', '_', ' '),
                '.',
                $sPatchVersion
            );

        $sPrefix =
            'files/'
            . $sCurrentNormalized
            . '-'
            . $sPatchNormalized
            . '/';

        $sCheck =
            $sPrefix
            . 'check.php';

        $sVersion =
            $sPrefix
            . 'files/inc/version.inc.php';

        $aResult['staged_patch_check_file_present'] =
            $oZip->locateName(
                $sCheck
            ) !== false;

        $aResult['staged_patch_version_file_present'] =
            $oZip->locateName(
                $sVersion
            ) !== false;

        $aResult['staged_patch_upgrade_cron_present'] =
            $oZip->locateName(
                'BxDolUpgradeCron.php'
            ) !== false;

        $aResult['staged_patch_structure_valid'] =
            $aResult['staged_patch_check_file_present']
            && $aResult['staged_patch_version_file_present']
            && $aResult['staged_patch_upgrade_cron_present'];

        if (!$aResult['staged_patch_structure_valid']) {
            $aResult['staged_patch_trust_reason'] =
                'invalid_patch_structure';

            $oZip->close();

            return $aResult;
        }

        /*
         * Read target version metadata as inert text only.
         */
        $aResult['staged_target_version_source'] =
            $sVersion;

        $aVersionStat =
            $oZip->statName($sVersion);

        if (
            is_array($aVersionStat)
            && isset($aVersionStat['size'])
            && (int)$aVersionStat['size'] > 0
            && (int)$aVersionStat['size'] <= 65536
        ) {
            $sVersionSource =
                $oZip->getFromName($sVersion);

            if (
                is_string($sVersionSource)
                && preg_match(
                    '/define\s*\(\s*[\'"]BX_DOL_VERSION[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/i',
                    $sVersionSource,
                    $aMatch
                ) === 1
            ) {
                $aResult['staged_target_version_read'] =
                    true;

                $aResult['staged_target_version'] =
                    trim($aMatch[1]);

                $aResult['staged_target_version_matches_patch'] =
                    strcasecmp(
                        trim($aMatch[1]),
                        $sPatchVersion
                    ) === 0;
            }
        }

        if (!$aResult['staged_target_version_read']) {
            $aResult['staged_patch_trust_reason'] =
                'target_version_unreadable';

            $oZip->close();

            return $aResult;
        }

        if (!$aResult['staged_target_version_matches_patch']) {
            $aResult['staged_patch_trust_reason'] =
                'target_version_mismatch';

            $oZip->close();

            return $aResult;
        }

        $aResult['staged_patch_trusted_for_static_inspection'] =
            true;

        $aResult['staged_patch_trust_reason'] =
            'md5_structure_and_target_identity_verified';

        /*
         * Fingerprint check.php without executing it.
         * Doctor reads at most 512 KiB.
         */
        $aCheckStat =
            $oZip->statName($sCheck);

        if (
            is_array($aCheckStat)
            && isset($aCheckStat['size'])
        ) {
            $aResult['staged_check_bytes'] =
                (int)$aCheckStat['size'];

            if (
                (int)$aCheckStat['size'] >= 0
                && (int)$aCheckStat['size'] <= 524288
            ) {
                $sCheckSource =
                    $oZip->getFromName($sCheck);

                if (is_string($sCheckSource)) {
                    $aResult['staged_check_sha256'] =
                        hash(
                            'sha256',
                            $sCheckSource
                        );

                    $aStatic =
                        $this->extractStaticUnaTargetEvidence(
                            $sCheckSource
                        );

                    foreach (
                        $aStatic
                        as $sKey => $mixedValue
                    ) {
                        $aResult[$sKey] =
                            $mixedValue;
                    }
                }
            }
        }

        $oZip->close();

        return $aResult;
    }


    /**
     * Extract only literal, statically provable runtime checks
     * from a target check.php source string.
     *
     * This does not execute the target file and does not claim
     * that observed checks represent the complete upgrade logic.
     */
    protected function extractStaticUnaTargetEvidence($sSource)
    {
        $aResult = array(
            'staged_static_evidence_found' => false,
            'staged_static_php_thresholds' => array(),
            'staged_static_highest_php_threshold' => '',
            'staged_static_php_threshold_met' => null,
            'staged_static_extension_checks' => array(),
            'staged_static_missing_extensions' => array(),
            'staged_static_observed_checks_met' => null,
            'staged_static_preflight_scope' => 'literal_patterns_only',
            'staged_full_target_preflight_executed' => false,
            'staged_full_target_preflight_certified' => false,
        );

        if (
            !is_string($sSource)
            || strlen($sSource) > 524288
        ) {
            return $aResult;
        }

        $aPhp = array();

        if (
            preg_match_all(
                '/version_compare\s*\(\s*(?:PHP_VERSION|phpversion\s*\(\s*\))\s*,\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]<[\'"]\s*\)/i',
                $sSource,
                $aMatches
            )
        ) {
            foreach ($aMatches[1] as $sVersion) {
                $sVersion = trim($sVersion);

                if (
                    preg_match(
                        '/^[0-9]+(?:\.[0-9A-Za-z-]+)+$/',
                        $sVersion
                    )
                ) {
                    $aPhp[] = $sVersion;
                }
            }
        }

        if (
            preg_match_all(
                '/PHP_VERSION_ID\s*<\s*([0-9]{5,6})/',
                $sSource,
                $aIdMatches
            )
        ) {
            foreach ($aIdMatches[1] as $sId) {
                $iId = (int)$sId;

                $aPhp[] =
                    intdiv($iId, 10000)
                    . '.'
                    . intdiv($iId % 10000, 100)
                    . '.'
                    . ($iId % 100);
            }
        }

        $aPhp =
            array_values(
                array_unique($aPhp)
            );

        usort(
            $aPhp,
            function ($a, $b) {
                return version_compare($a, $b);
            }
        );

        $aExtensions = array();

        if (
            preg_match_all(
                '/extension_loaded\s*\(\s*[\'"]([A-Za-z0-9_]+)[\'"]\s*\)/i',
                $sSource,
                $aExtMatches
            )
        ) {
            foreach ($aExtMatches[1] as $sExtension) {
                $aExtensions[] =
                    strtolower($sExtension);
            }
        }

        $aExtensions =
            array_values(
                array_unique($aExtensions)
            );

        sort($aExtensions);

        $aMissing = array();

        foreach ($aExtensions as $sExtension) {
            if (!extension_loaded($sExtension))
                $aMissing[] = $sExtension;
        }

        $aResult['staged_static_php_thresholds'] =
            $aPhp;

        $aResult['staged_static_extension_checks'] =
            $aExtensions;

        $aResult['staged_static_missing_extensions'] =
            $aMissing;

        if (!empty($aPhp)) {
            $sHighest =
                end($aPhp);

            $aResult['staged_static_highest_php_threshold'] =
                $sHighest;

            $aResult['staged_static_php_threshold_met'] =
                version_compare(
                    PHP_VERSION,
                    $sHighest,
                    '>='
                );
        }

        if (
            !empty($aPhp)
            || !empty($aExtensions)
        ) {
            $aResult['staged_static_evidence_found'] =
                true;

            $bPhpOkay =
                $aResult['staged_static_php_threshold_met']
                !== false;

            $bExtensionsOkay =
                empty($aMissing);

            $aResult['staged_static_observed_checks_met'] =
                $bPhpOkay
                && $bExtensionsOkay;
        }

        return $aResult;
    }


    /**
     * Extract required PHP modules from UNA's local Studio
     * Tools Audit source.
     *
     * Parsing is deliberately narrow and read-only.
     */
    protected function detectUnaRequiredPhpExtensions()
    {
        $sRelativePath =
            'studio/classes/BxDolStudioToolsAudit.php';

        $sPath =
            rtrim(
                BX_DIRECTORY_PATH_ROOT,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $sRelativePath
            );

        $aResult = array(
            'extensions' => array(),
            'source' => 'unavailable',
            'source_path' => $sRelativePath,
            'source_sha256' => '',
            'confidence' => 'unknown',
            'reason' => '',
        );

        if (!is_file($sPath)) {
            $aResult['reason'] =
                'studio_audit_source_missing';

            return $aResult;
        }

        if (!is_readable($sPath)) {
            $aResult['reason'] =
                'studio_audit_source_not_readable';

            return $aResult;
        }

        $iSize =
            (int)@filesize($sPath);

        if (
            $iSize <= 0
            || $iSize > 1048576
        ) {
            $aResult['reason'] =
                'studio_audit_source_size_unexpected';

            return $aResult;
        }

        $sSource =
            @file_get_contents($sPath);

        if ($sSource === false) {
            $aResult['reason'] =
                'studio_audit_source_read_failed';

            return $aResult;
        }

        /*
         * Match only UNA audit entries explicitly using:
         *
         * 'op' => 'module'
         * 'val' => '<extension>'
         */
        $sPattern =
            '/[\'"]php module:\s*[^\'"]+[\'"]'
            . '\s*=>\s*array\s*\('
            . '\s*[\'"]op[\'"]\s*=>\s*[\'"]module[\'"]'
            . '\s*,\s*[\'"]val[\'"]\s*=>\s*[\'"]([A-Za-z0-9_]+)[\'"]'
            . '/i';

        $aMatches = array();

        $iMatched =
            preg_match_all(
                $sPattern,
                $sSource,
                $aMatches
            );

        if (
            $iMatched === false
            || $iMatched < 1
        ) {
            $aResult['source_sha256'] =
                (string)@hash_file(
                    'sha256',
                    $sPath
                );

            $aResult['reason'] =
                'required_php_modules_not_found';

            return $aResult;
        }

        $aExtensions =
            array_values(
                array_unique(
                    $aMatches[1]
                    ?? array()
                )
            );

        if (empty($aExtensions)) {
            $aResult['source_sha256'] =
                (string)@hash_file(
                    'sha256',
                    $sPath
                );

            $aResult['reason'] =
                'required_php_modules_empty';

            return $aResult;
        }

        $aResult['extensions'] =
            $aExtensions;

        $aResult['source'] =
            'una_studio_tools_audit';

        $aResult['source_sha256'] =
            (string)@hash_file(
                'sha256',
                $sPath
            );

        $aResult['confidence'] =
            'local_source';

        $aResult['reason'] =
            'required_php_modules_extracted_from_local_una_audit';

        return $aResult;
    }


    /**
     * Read the minimum PHP version from UNA's local Studio
     * Tools Audit source.
     *
     * Source parsing is deliberately narrow and read-only.
     */
    protected function detectUnaMinimumPhpRequirement()
    {
        $sRelativePath =
            'studio/classes/BxDolStudioToolsAudit.php';

        $sPath =
            rtrim(
                BX_DIRECTORY_PATH_ROOT,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $sRelativePath
            );

        $aResult = array(
            'version' => '',
            'source' => 'unavailable',
            'source_path' => $sRelativePath,
            'source_sha256' => '',
            'confidence' => 'unknown',
            'reason' => '',
        );

        if (!is_file($sPath)) {
            $aResult['reason'] =
                'studio_audit_source_missing';

            return $aResult;
        }

        if (!is_readable($sPath)) {
            $aResult['reason'] =
                'studio_audit_source_not_readable';

            return $aResult;
        }

        $iSize =
            (int)@filesize($sPath);

        /*
         * The normal UNA audit class is small. Refuse unusually
         * large input rather than reading an unexpected file.
         */
        if (
            $iSize <= 0
            || $iSize > 1048576
        ) {
            $aResult['reason'] =
                'studio_audit_source_size_unexpected';

            return $aResult;
        }

        $sSource =
            @file_get_contents($sPath);

        if ($sSource === false) {
            $aResult['reason'] =
                'studio_audit_source_read_failed';

            return $aResult;
        }

        $aPatterns = array(
            '/\$this->sMinPhpVer\s*=\s*[\'"]([0-9]+(?:\.[0-9]+){1,2})[\'"]\s*;/',
            '/protected\s+\$sMinPhpVer\s*=\s*[\'"]([0-9]+(?:\.[0-9]+){1,2})[\'"]\s*;/',
        );

        foreach ($aPatterns as $sPattern) {
            $aMatch = array();

            if (
                preg_match(
                    $sPattern,
                    $sSource,
                    $aMatch
                ) === 1
            ) {
                $sVersion =
                    (string)(
                        $aMatch[1]
                        ?? ''
                    );

                if (
                    $sVersion !== ''
                    && preg_match(
                        '/^[0-9]+(?:\.[0-9]+){1,2}$/',
                        $sVersion
                    ) === 1
                ) {
                    $aResult['version'] =
                        $sVersion;

                    $aResult['source'] =
                        'una_studio_tools_audit';

                    $aResult['source_sha256'] =
                        (string)@hash_file(
                            'sha256',
                            $sPath
                        );

                    $aResult['confidence'] =
                        'local_source';

                    $aResult['reason'] =
                        'minimum_php_extracted_from_local_una_audit';

                    return $aResult;
                }
            }
        }

        $aResult['source_sha256'] =
            (string)@hash_file(
                'sha256',
                $sPath
            );

        $aResult['reason'] =
            'minimum_php_assignment_not_found';

        return $aResult;
    }


    /**
     * Inspect UNA maintenance and upgrade state.
     *
     * Diagnostic only. This method never changes maintenance
     * options, the hard-maintenance file, repair journals,
     * cron schedules, module files or checksum baselines.
     */
    protected function scanMaintenanceUpgradeState()
    {
        $sRoot =
            rtrim(
                BX_DIRECTORY_PATH_ROOT,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR;

        $sHardFileName =
            defined('BX_MAINTENANCE_FILE')
                ? (string)BX_MAINTENANCE_FILE
                : '.bx_maintenance';

        $sHardFile =
            $sRoot
            . $sHardFileName;

        $bHardMaintenance =
            is_file($sHardFile);

        $iHardMtime = 0;
        $iHardAge = 0;

        if ($bHardMaintenance) {
            $iHardMtime =
                (int)@filemtime(
                    $sHardFile
                );

            if ($iHardMtime > 0) {
                $iHardAge =
                    max(
                        0,
                        time() - $iHardMtime
                    );
            }
        }

        /*
         * Six hours is deliberately conservative.
         *
         * Doctor does not remove a lock based on age. Age only
         * changes the diagnostic classification and prompts
         * further investigation.
         */
        $iStaleThreshold =
            6 * 60 * 60;

        $bHardStale =
            $bHardMaintenance
            && $iHardAge >= $iStaleThreshold;

        $sSoftValue =
            (string)getParam(
                'sys_maintenance_mode'
            );

        $bSoftMaintenance =
            !empty($sSoftValue);

        $sSoftHtml =
            (string)getParam(
                'sys_maintenance_mode_html'
            );

        $sFileVersion =
            defined('BX_DOL_VERSION')
                ? (string)BX_DOL_VERSION
                : '';

        $sDatabaseVersion =
            (string)$this->oDb->getOne(
                "SELECT `version`
                 FROM `sys_modules`
                 WHERE `name` = 'system'
                 LIMIT 1"
            );

        $bVersionMismatch =
            $sFileVersion !== ''
            && $sDatabaseVersion !== ''
            && $sFileVersion !==
                $sDatabaseVersion;

        /*
         * Upgrade-related log entries are supporting evidence,
         * never sufficient by themselves to declare a current
         * failure.
         */
        $aUpgradeLogSignals =
            $this->collectMaintenanceUpgradeLogSignals();

        $iUpgradeLogSignalCount =
            count($aUpgradeLogSignals);

        /*
         * Doctor's own durable repair journal is authoritative
         * evidence that maintenance may be intentional.
         */
        $aJournal = array();

        if (
            is_object($this->oModule)
            && method_exists(
                $this->oModule,
                'getLatestActiveRepairJournal'
            )
        ) {
            try {
                $aJournal =
                    $this->oModule
                        ->getLatestActiveRepairJournal();

                if (!is_array($aJournal))
                    $aJournal = array();

            } catch (Throwable $e) {
                $aJournal = array();
            }
        }

        $iJournalId =
            (int)(
                $aJournal['id']
                ?? 0
            );

        $sJournalStatus =
            (string)(
                $aJournal['status']
                ?? ''
            );

        $sJournalRule =
            (string)(
                $aJournal['rule']
                ?? ''
            );

        $sJournalModule =
            (string)(
                $aJournal['module']
                ?? ''
            );

        $aMaintenanceRepairStates = array(
            'maintenance_enabled',
            'generator_paused',
            'armed',
            'runtime_protecting',
            'runtime_protected',
            'runtime_merging',
            'runtime_restoring',
            'runtime_rollback_failed',
            'restoring',
            'rollback_failed',
        );

        $bDoctorMaintenanceExpected =
            $iJournalId > 0
            && in_array(
                $sJournalStatus,
                $aMaintenanceRepairStates,
                true
            );

        $aEvidence = array(
            'state' => 'NORMAL',

            'soft_maintenance_enabled' =>
                $bSoftMaintenance,

            'soft_maintenance_value' =>
                $sSoftValue,

            'soft_maintenance_html_bytes' =>
                strlen($sSoftHtml),

            'hard_maintenance_file' =>
                $bHardMaintenance,

            'hard_maintenance_path' =>
                $sHardFile,

            'hard_maintenance_mtime' =>
                $iHardMtime,

            'hard_maintenance_age_seconds' =>
                $iHardAge,

            'hard_lock_stale_threshold_seconds' =>
                $iStaleThreshold,

            'una_files_version' =>
                $sFileVersion,

            'una_database_version' =>
                $sDatabaseVersion,

            'una_version_mismatch' =>
                $bVersionMismatch,

            'upgrade_log_signal_count' =>
                $iUpgradeLogSignalCount,

            'upgrade_log_signals' =>
                $aUpgradeLogSignals,

            'doctor_repair_journal_id' =>
                $iJournalId,

            'doctor_repair_status' =>
                $sJournalStatus,

            'doctor_repair_rule' =>
                $sJournalRule,

            'doctor_repair_module' =>
                $sJournalModule,

            'changes_applied' => false,
        );

        /*
         * Strongest signal:
         * hard maintenance + application/DB version mismatch.
         *
         * Doctor does not assert that an upgrade definitely
         * failed. It identifies the combination as requiring
         * investigation.
         */
        if (
            $bHardMaintenance
            && $bVersionMismatch
        ) {
            $aEvidence['state'] =
                'POSSIBLE_INTERRUPTED_UPGRADE';

            $aEvidence['correlation_reason'] =
                'hard_maintenance_and_version_mismatch';

            $this->add(
                'maintenance',
                'critical',
                'Possible interrupted UNA upgrade',
                'UNA hard maintenance is active while the UNA application files and database report different versions. This is strong evidence of an incomplete or interrupted upgrade state. Doctor will not remove the maintenance lock automatically.',
                $aEvidence
            );

            return;
        }

        /*
         * An unexpected hard lock during Doctor's own protected
         * repair is not treated as normal Doctor maintenance.
         */
        if (
            $bHardMaintenance
            && $bDoctorMaintenanceExpected
        ) {
            $aEvidence['state'] =
                'HARD_MAINTENANCE';

            $this->add(
                'maintenance',
                'warning',
                'Hard UNA maintenance appeared during a Doctor protected repair',
                'Doctor has an active repair journal, but a hard .bx_maintenance lock is also present. Doctor protected repairs use controlled soft maintenance and do not assume this hard lock is safe to remove.',
                $aEvidence
            );

            return;
        }

        /*
         * Soft maintenance alone is not an upgrade failure.
         * However, unexplained soft maintenance together with
         * UNA version drift and upgrade-log evidence is a much
         * stronger interrupted-upgrade signal.
         *
         * Doctor-owned protected repair maintenance is excluded.
         */
        if (
            $bSoftMaintenance
            && !$bDoctorMaintenanceExpected
            && $bVersionMismatch
            && $iUpgradeLogSignalCount > 0
        ) {
            $aEvidence['state'] =
                'POSSIBLE_INTERRUPTED_UPGRADE';

            $aEvidence['correlation_reason'] =
                'soft_maintenance_version_mismatch_and_upgrade_logs';

            $this->add(
                'maintenance',
                'critical',
                'Possible interrupted UNA upgrade',
                'UNA soft maintenance is active, the UNA application and database versions do not agree, and recent module-upgrade log signals were found. Doctor detected the correlated condition but made no changes.',
                $aEvidence
            );

            return;
        }

        if ($bHardStale) {
            $aEvidence['state'] =
                'STALE_HARD_LOCK';

            if ($iUpgradeLogSignalCount > 0) {
                $aEvidence['correlation_reason'] =
                    'stale_hard_maintenance_with_upgrade_log_evidence';

                $sDetail =
                    'The .bx_maintenance file has remained present for at least six hours and Doctor also found upgrade-related module log evidence. The log evidence may be historical, so Doctor is treating this as a warning rather than proof of an interrupted upgrade. Confirm that no upgrade or administrator maintenance operation is still running before removing the lock.';
            } else {
                $aEvidence['correlation_reason'] =
                    'stale_hard_maintenance_only';

                $sDetail =
                    'The .bx_maintenance file has remained present for at least six hours. Doctor detected the condition but made no change. Confirm that no upgrade or administrator maintenance operation is still running before removing it.';
            }

            $this->add(
                'maintenance',
                'warning',
                'UNA hard maintenance lock may be stale',
                $sDetail,
                $aEvidence
            );

            return;
        }

        if ($bHardMaintenance) {
            $aEvidence['state'] =
                'HARD_MAINTENANCE';

            $this->add(
                'maintenance',
                'warning',
                'UNA hard maintenance mode is active',
                'The root .bx_maintenance file is present. Doctor will not remove or modify the lock automatically.',
                $aEvidence
            );

            return;
        }

        /*
         * Doctor-controlled repair maintenance must be
         * separated from an unexplained maintenance condition.
         */
        if ($bDoctorMaintenanceExpected) {

            if ($bSoftMaintenance) {
                $aEvidence['state'] =
                    'DOCTOR_PROTECTED_REPAIR';

                $this->add(
                    'maintenance',
                    'info',
                    'Doctor protected repair maintenance is active',
                    'The active Doctor repair journal and UNA soft-maintenance state agree. This is an intentional protected repair condition, not an abandoned upgrade lock.',
                    $aEvidence
                );

                return;
            }

            $aEvidence['state'] =
                'DOCTOR_PROTECTED_REPAIR';

            $this->add(
                'maintenance',
                'warning',
                'Doctor repair journal and maintenance state do not agree',
                'Doctor has an active repair journal whose current phase normally requires protected soft maintenance, but UNA soft maintenance is not enabled. Review the repair journal before continuing the repair.',
                $aEvidence
            );

            return;
        }

        if ($bSoftMaintenance) {
            $aEvidence['state'] =
                'SOFT_MAINTENANCE';

            $this->add(
                'maintenance',
                'info',
                'UNA soft maintenance mode is active',
                'Public visitors are intentionally being held by UNA soft maintenance. Studio operators and permitted upgrade activity can remain accessible. Doctor made no change.',
                $aEvidence
            );

            return;
        }

        /*
         * A maintenance page may remain configured while the
         * switch is off. That is configuration, not an active
         * outage.
         */
        if ($sSoftHtml !== '') {
            $aEvidence['state'] =
                'NORMAL';

            $this->add(
                'maintenance',
                'ok',
                'UNA maintenance mode is off',
                'No soft or hard maintenance mode is active. A maintenance HTML template is configured but is not currently being shown.',
                $aEvidence
            );

            return;
        }

        $this->add(
            'maintenance',
            'ok',
            'UNA maintenance and upgrade state is normal',
            'No UNA soft maintenance, hard maintenance lock or Doctor protected repair state is active.',
            $aEvidence
        );
    }


    /**
     * Return a small bounded set of recent upgrade-related
     * sys_modules.log signals.
     *
     * The log is supporting evidence only. A matching line does
     * not independently create an upgrade-failure diagnosis.
     */
    /**
     * Summarize maintenance/upgrade-state readiness.
     *
     * This is deliberately separate from module checksum/update
     * eligibility. It changes no files, options, journals or locks.
     */
    protected function scanUpgradeReadinessSummary()
    {
        $sHardFile =
            rtrim(
                BX_DIRECTORY_PATH_ROOT,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR
            . (
                defined('BX_MAINTENANCE_FILE')
                    ? BX_MAINTENANCE_FILE
                    : '.bx_maintenance'
            );

        $bHard =
            is_file($sHardFile);

        $bSoft =
            !empty(
                (string)getParam(
                    'sys_maintenance_mode'
                )
            );

        $sFilesVersion =
            defined('BX_DOL_VERSION')
                ? (string)BX_DOL_VERSION
                : '';

        $sDatabaseVersion =
            (string)$this->oDb->getOne(
                "SELECT `version`
                 FROM `sys_modules`
                 WHERE `name` = 'system'
                 LIMIT 1"
            );

        $bVersionMismatch =
            $sFilesVersion !== ''
            && $sDatabaseVersion !== ''
            && $sFilesVersion !== $sDatabaseVersion;

        $aJournal = array();

        if (
            is_object($this->oModule)
            && method_exists(
                $this->oModule,
                'getLatestActiveRepairJournal'
            )
        ) {
            try {
                $aJournal =
                    $this->oModule
                        ->getLatestActiveRepairJournal();

                if (!is_array($aJournal))
                    $aJournal = array();

            } catch (Throwable $e) {
                $aJournal = array();
            }
        }

        $iJournalId =
            (int)(
                $aJournal['id']
                ?? 0
            );

        $aUpgradeSignals =
            $this->collectMaintenanceUpgradeLogSignals();

        $aBlockers = array();

        if ($bHard)
            $aBlockers[] =
                'UNA hard maintenance is active';

        if ($bSoft)
            $aBlockers[] =
                'UNA soft maintenance is active';

        if ($bVersionMismatch)
            $aBlockers[] =
                'UNA file and database versions do not agree';

        if ($iJournalId > 0)
            $aBlockers[] =
                'A My UNA Doctor repair journal is still active';

        $bReady =
            empty($aBlockers);

        $aEvidence = array(
            'scope' =>
                'maintenance_and_upgrade_state',

            'ready' =>
                $bReady,

            'blockers' =>
                $aBlockers,

            'hard_maintenance' =>
                $bHard,

            'soft_maintenance' =>
                $bSoft,

            'una_files_version' =>
                $sFilesVersion,

            'una_database_version' =>
                $sDatabaseVersion,

            'version_mismatch' =>
                $bVersionMismatch,

            'doctor_repair_journal_id' =>
                $iJournalId,

            'doctor_repair_status' =>
                (string)(
                    $aJournal['status']
                    ?? ''
                ),

            'upgrade_log_signal_count' =>
                count($aUpgradeSignals),

            'upgrade_log_signals' =>
                $aUpgradeSignals,

            'changes_applied' =>
                false,
        );

        if ($bReady) {
            $this->add(
                'upgrade_readiness',
                'ok',
                'Upgrade readiness: maintenance state clear',
                'No active UNA maintenance barrier, UNA version mismatch or Doctor repair journal is present. Upgrade-related log entries, if found, are advisory evidence only. Module checksum/update eligibility is evaluated separately.',
                $aEvidence
            );

            return;
        }

        $sDetail =
            'Doctor found maintenance or upgrade-state conditions that should be reviewed before starting another UNA upgrade: '
            . implode(
                '; ',
                $aBlockers
            )
            . '. Module checksum/update eligibility is evaluated separately.';

        $sSeverity =
            (
                $bVersionMismatch
                && (
                    $bHard
                    || $bSoft
                )
            )
                ? 'critical'
                : 'warning';

        $this->add(
            'upgrade_readiness',
            $sSeverity,
            'Upgrade readiness: maintenance state requires review',
            $sDetail,
            $aEvidence
        );
    }


    protected function collectMaintenanceUpgradeLogSignals()
    {
        $sLog =
            rtrim(
                BX_DIRECTORY_PATH_ROOT,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR
            . 'logs'
            . DIRECTORY_SEPARATOR
            . 'sys_modules.log';

        if (
            !is_file($sLog)
            || !is_readable($sLog)
        ) {
            return array();
        }

        /*
         * Ignore a module log that has not itself changed within
         * the last 14 days.
         */
        $iMtime =
            (int)@filemtime($sLog);

        if (
            $iMtime <= 0
            || $iMtime < time() - (14 * 86400)
        ) {
            return array();
        }

        $aLines =
            @file(
                $sLog,
                FILE_IGNORE_NEW_LINES
                | FILE_SKIP_EMPTY_LINES
            );

        if (!is_array($aLines))
            return array();

        /*
         * Bound inspection to the most recent 500 physical
         * lines even if a historical log has grown very large.
         */
        if (count($aLines) > 500) {
            $aLines =
                array_slice(
                    $aLines,
                    -500
                );
        }

        $aSignals = array();

        foreach ($aLines as $sLine) {

            $sLine =
                trim((string)$sLine);

            if ($sLine === '')
                continue;

            if (
                !preg_match(
                    '/upgrade|module update|update failed|cannot get a list of modules|apply upgrade/i',
                    $sLine
                )
            ) {
                continue;
            }

            /*
             * Keep technical evidence bounded.
             */
            if (strlen($sLine) > 500) {
                $sLine =
                    substr(
                        $sLine,
                        0,
                        500
                    )
                    . '…';
            }

            $aSignals[] = $sLine;
        }

        /*
         * Store only the newest eight matching lines.
         */
        if (count($aSignals) > 8) {
            $aSignals =
                array_slice(
                    $aSignals,
                    -8
                );
        }

        return $aSignals;
    }


    protected function scanFilesystem()
    {
        $sRoot = rtrim(BX_DIRECTORY_PATH_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $aDirs = array('cache', 'cache_public', 'tmp', 'logs', 'storage');

        foreach ($aDirs as $sDir) {
            $sPath = $sRoot . $sDir;
            if (!is_dir($sPath)) {
                $this->add('filesystem', 'warning', 'Expected UNA directory is missing', $sDir, array('path' => $sPath));
                continue;
            }
            if (!is_writable($sPath)) {
                $this->add('filesystem', 'critical', 'UNA directory is not writable', $sDir, array('path' => $sPath));
                continue;
            }
            $this->add('filesystem', 'ok', 'UNA directory is writable', $sDir);
        }

        $iFree = @disk_free_space($sRoot);
        $iTotal = @disk_total_space($sRoot);
        if ($iFree !== false && $iTotal !== false && $iTotal > 0) {
            $fPct = ($iFree / $iTotal) * 100;
            $aEvidence = array(
                'free_bytes' => (int)$iFree,
                'total_bytes' => (int)$iTotal,
                'free_percent' => round($fPct, 1),
            );
            if ($fPct < 5)
                $this->add('filesystem', 'critical', 'Disk free space is critically low', 'Less than 5% of the filesystem is free.', $aEvidence);
            elseif ($fPct < 10)
                $this->add('filesystem', 'warning', 'Disk free space is low', 'Less than 10% of the filesystem is free.', $aEvidence);
            else
                $this->add('filesystem', 'ok', 'Disk free space is within the initial safety threshold', '', $aEvidence);
        }
    }

    protected function scanDatabase()
    {
        $sDbName = (string)$this->oDb->getOne('SELECT DATABASE()');
        $iTables = (int)$this->oDb->getOne('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        $this->add('database', 'ok', 'Database connection is working', '', array('database' => $sDbName, 'table_count' => $iTables));

        if ($this->tableExists('sys_alerts') && $this->tableExists('sys_alerts_handlers')) {
            $iOrphans = (int)$this->oDb->getOne(
                'SELECT COUNT(*) FROM `sys_alerts` a LEFT JOIN `sys_alerts_handlers` h ON h.`id` = a.`handler_id` WHERE h.`id` IS NULL'
            );
            if ($iOrphans > 0)
                $this->add('database', 'warning', 'Orphan UNA alert registrations were found',
                    $iOrphans . ' sys_alerts record(s) reference a handler that no longer exists.');
            else
                $this->add('database', 'ok', 'No orphan UNA alert registrations were found');

            $aDup = $this->oDb->getAll(
                'SELECT `unit`, `action`, `handler_id`, COUNT(*) AS `cnt` FROM `sys_alerts` GROUP BY `unit`, `action`, `handler_id` HAVING COUNT(*) > 1 LIMIT 25'
            );
            if (!empty($aDup))
                $this->add('database', 'warning', 'Duplicate UNA alert registrations were found',
                    count($aDup) . ' duplicate alert signature(s) were detected.', array('duplicates' => $aDup));
            else
                $this->add('database', 'ok', 'No duplicate UNA alert registrations were found');
        }
    }

    protected function scanStorageMedia()
    {
        $bSupported = false;

        if ($this->tableExists('bx_albums_files') || $this->tableExists('bx_albums_files2albums') || $this->tableExists('bx_albums_albums')) {
            $bSupported = true;
            $this->scanAlbumsMediaIntegrity();
        }

        if ($this->tableExists('bx_timeline_events') || $this->tableExists('bx_timeline_photos2events') || $this->tableExists('bx_timeline_videos2events') || $this->tableExists('bx_timeline_files2events')) {
            $bSupported = true;
            $this->scanTimelineMediaIntegrity();
        }

        if (!$bSupported) {
            $this->add('storage', 'info', 'No supported media relationship tables were found',
                'The first Storage & Media Inspector currently understands UNA Albums and Timeline relationship tables. No supported tables were present, so no media relationship checks were run.',
                $this->humanEvidence(
                    'No supported Albums or Timeline relationship tables were detected.',
                    array(),
                    'None',
                    'No'
                ));
        }
    }

    protected function scanAlbumsMediaIntegrity()
    {
        $aRequired = array(
            'bx_albums_files' => array('id'),
            'bx_albums_files2albums' => array('id', 'content_id', 'file_id'),
            'bx_albums_albums' => array('id', 'thumb', 'title'),
        );

        $aMissing = $this->missingTableFields($aRequired);
        if (!empty($aMissing)) {
            $this->add('storage', 'warning', 'Albums media relationship schema could not be verified',
                'Doctor found Albums tables but one or more fields required for a safe relationship check are missing.',
                $this->humanEvidence(
                    'Doctor could not safely complete the Albums relationship inspection because the live schema differs from the fields this diagnostic expects.',
                    array(array('title' => 'Missing table/field requirements', 'items' => $aMissing)),
                    'Unknown until the schema difference is reviewed',
                    'Review required; Doctor made no changes'
                ) + array('missing_requirements' => $aMissing));
            return;
        }

        $iMediaLinks = (int)$this->oDb->getOne('SELECT COUNT(*) FROM `bx_albums_files2albums`');
        $iMissingSources = (int)$this->oDb->getOne(
            'SELECT COUNT(*) FROM `bx_albums_files2albums` l LEFT JOIN `bx_albums_files` f ON f.`id` = l.`file_id` WHERE f.`id` IS NULL'
        );
        $aMissingSources = $this->oDb->getAll(
            'SELECT l.`id` AS `link_id`, l.`content_id` AS `album_id`, l.`file_id`, l.`title` AS `media_title`, a.`title` AS `album_title` '
            . 'FROM `bx_albums_files2albums` l LEFT JOIN `bx_albums_files` f ON f.`id` = l.`file_id` '
            . 'LEFT JOIN `bx_albums_albums` a ON a.`id` = l.`content_id` WHERE f.`id` IS NULL ORDER BY l.`id` LIMIT 25'
        );

        if ($iMissingSources > 0) {
            $aLines = array();
            foreach ($aMissingSources as $aRow) {
                $aLines[] = 'File ID ' . (int)$aRow['file_id'] . ' is still linked to album #' . (int)$aRow['album_id']
                    . ($aRow['album_title'] !== null && $aRow['album_title'] !== '' ? ' (' . (string)$aRow['album_title'] . ')' : '') . '.';
            }
            $this->add('storage', 'warning', 'Albums media links reference missing source files',
                $iMissingSources . ' Albums media relationship(s) point to a source file row that no longer exists.',
                $this->humanEvidence(
                    'Doctor found live Albums media relationships whose source-storage row is missing.',
                    array(array('title' => 'Affected relationships (up to 25)', 'items' => $aLines)),
                    'Potential broken album media',
                    'Review required before any repair'
                ) + array('relationship_count' => $iMediaLinks, 'missing_source_count' => $iMissingSources, 'samples' => $aMissingSources));
        } else {
            $this->add('storage', 'ok', 'Albums media links point to existing source files',
                '',
                $this->humanEvidence(
                    'Doctor checked ' . $iMediaLinks . ' Albums media relationship(s); none reference a missing source file.',
                    array(),
                    'None',
                    'No'
                ) + array('relationship_count' => $iMediaLinks, 'missing_source_count' => 0));
        }

        $iMissingAlbums = (int)$this->oDb->getOne(
            'SELECT COUNT(*) FROM `bx_albums_files2albums` l LEFT JOIN `bx_albums_albums` a ON a.`id` = l.`content_id` WHERE a.`id` IS NULL'
        );
        $aMissingAlbums = $this->oDb->getAll(
            'SELECT l.`id` AS `link_id`, l.`content_id` AS `album_id`, l.`file_id`, l.`title` AS `media_title` '
            . 'FROM `bx_albums_files2albums` l LEFT JOIN `bx_albums_albums` a ON a.`id` = l.`content_id` '
            . 'WHERE a.`id` IS NULL ORDER BY l.`id` LIMIT 25'
        );

        if ($iMissingAlbums > 0) {
            $aLines = array();
            foreach ($aMissingAlbums as $aRow)
                $aLines[] = 'Media link #' . (int)$aRow['link_id'] . ' references missing album #' . (int)$aRow['album_id'] . ' (file ID ' . (int)$aRow['file_id'] . ').';
            $this->add('storage', 'warning', 'Albums media links reference missing album records',
                $iMissingAlbums . ' Albums media relationship(s) point to an album record that no longer exists.',
                $this->humanEvidence(
                    'Doctor found Albums media-link rows whose parent album record is missing.',
                    array(array('title' => 'Affected relationships (up to 25)', 'items' => $aLines)),
                    'Potential orphaned Albums media relationships',
                    'Review required before any repair'
                ) + array('missing_album_count' => $iMissingAlbums, 'samples' => $aMissingAlbums));
        } else {
            $this->add('storage', 'ok', 'Albums media links point to existing album records', '',
                array('relationship_count' => $iMediaLinks, 'missing_album_count' => 0));
        }

        $iThumbs = (int)$this->oDb->getOne('SELECT COUNT(*) FROM `bx_albums_albums` WHERE `thumb` > 0');
        $iMissingThumbs = (int)$this->oDb->getOne(
            'SELECT COUNT(*) FROM `bx_albums_albums` a LEFT JOIN `bx_albums_files` f ON f.`id` = a.`thumb` WHERE a.`thumb` > 0 AND f.`id` IS NULL'
        );
        $aMissingThumbs = $this->oDb->getAll(
            'SELECT a.`id` AS `album_id`, a.`title`, a.`thumb` AS `file_id` FROM `bx_albums_albums` a '
            . 'LEFT JOIN `bx_albums_files` f ON f.`id` = a.`thumb` WHERE a.`thumb` > 0 AND f.`id` IS NULL ORDER BY a.`id` LIMIT 25'
        );

        if ($iMissingThumbs > 0) {
            $aLines = array();
            foreach ($aMissingThumbs as $aRow)
                $aLines[] = 'Album #' . (int)$aRow['album_id'] . ' (' . (string)$aRow['title'] . ') uses missing thumbnail source file ID ' . (int)$aRow['file_id'] . '.';
            $this->add('storage', 'warning', 'Album thumbnails reference missing source files',
                $iMissingThumbs . ' album thumbnail reference(s) point to source file rows that no longer exist.',
                $this->humanEvidence(
                    'Doctor found album thumbnail fields that still point at deleted source-storage rows.',
                    array(array('title' => 'Affected albums (up to 25)', 'items' => $aLines)),
                    'Album covers/thumbnails may fail to render',
                    'Review required before any repair'
                ) + array('thumbnail_reference_count' => $iThumbs, 'missing_thumbnail_source_count' => $iMissingThumbs, 'samples' => $aMissingThumbs));
        } else {
            $this->add('storage', 'ok', 'Album thumbnails point to existing source files', '',
                array('thumbnail_reference_count' => $iThumbs, 'missing_thumbnail_source_count' => 0));
        }
    }

    protected function scanTimelineMediaIntegrity()
    {
        if (!$this->tableExists('bx_timeline_events')) {
            $this->add('storage', 'warning', 'Timeline media relationships cannot be fully verified',
                'Timeline media relationship tables are present but bx_timeline_events is missing.',
                $this->humanEvidence(
                    'Doctor found Timeline media tables without the Timeline events table required to validate their parent relationships.',
                    array(),
                    'Potential Timeline schema/integrity problem',
                    'Review required; Doctor made no changes'
                ));
            return;
        }

        $aSpecs = array(
            'photos' => array('source' => 'bx_timeline_photos', 'links' => 'bx_timeline_photos2events'),
            'videos' => array('source' => 'bx_timeline_videos', 'links' => 'bx_timeline_videos2events'),
            'files' => array('source' => 'bx_timeline_files', 'links' => 'bx_timeline_files2events'),
        );

        $iRelationshipCount = 0;
        $iMissingSourceCount = 0;
        $iMissingEventCount = 0;
        $aMissingSourceSamples = array();
        $aMissingEventSamples = array();
        $aCheckedTypes = array();

        foreach ($aSpecs as $sType => $aSpec) {
            if (!$this->tableExists($aSpec['links']))
                continue;

            $aFields = $this->tableFields($aSpec['links']);
            if (!in_array('id', $aFields, true) || !in_array('event_id', $aFields, true) || !in_array('media_id', $aFields, true)) {
                $this->add('storage', 'warning', 'Timeline ' . $sType . ' relationship schema could not be verified',
                    $aSpec['links'] . ' does not expose the expected id, event_id and media_id fields.',
                    array('table' => $aSpec['links'], 'fields' => $aFields));
                continue;
            }

            $aCheckedTypes[] = $sType;
            $iRelationshipCount += (int)$this->oDb->getOne('SELECT COUNT(*) FROM `' . $aSpec['links'] . '`');

            if ($this->tableExists($aSpec['source']) && in_array('id', $this->tableFields($aSpec['source']), true)) {
                $iTypeMissing = (int)$this->oDb->getOne(
                    'SELECT COUNT(*) FROM `' . $aSpec['links'] . '` l LEFT JOIN `' . $aSpec['source'] . '` s ON s.`id` = l.`media_id` WHERE s.`id` IS NULL'
                );
                $iMissingSourceCount += $iTypeMissing;
                if ($iTypeMissing > 0) {
                    $aRows = $this->oDb->getAll(
                        'SELECT l.`id` AS `link_id`, l.`event_id`, l.`media_id` FROM `' . $aSpec['links'] . '` l '
                        . 'LEFT JOIN `' . $aSpec['source'] . '` s ON s.`id` = l.`media_id` WHERE s.`id` IS NULL ORDER BY l.`id` LIMIT 10'
                    );
                    foreach ($aRows as $aRow) {
                        if (count($aMissingSourceSamples) >= 25)
                            break;
                        $aMissingSourceSamples[] = array('type' => $sType) + $aRow;
                    }
                }
            } else {
                $this->add('storage', 'warning', 'Timeline ' . $sType . ' source storage cannot be verified',
                    'The relationship table exists but its source storage table is missing or does not expose an id field.',
                    array('source_table' => $aSpec['source'], 'relationship_table' => $aSpec['links']));
            }

            $iTypeMissingEvents = (int)$this->oDb->getOne(
                'SELECT COUNT(*) FROM `' . $aSpec['links'] . '` l LEFT JOIN `bx_timeline_events` e ON e.`id` = l.`event_id` WHERE e.`id` IS NULL'
            );
            $iMissingEventCount += $iTypeMissingEvents;
            if ($iTypeMissingEvents > 0) {
                $aRows = $this->oDb->getAll(
                    'SELECT l.`id` AS `link_id`, l.`event_id`, l.`media_id` FROM `' . $aSpec['links'] . '` l '
                    . 'LEFT JOIN `bx_timeline_events` e ON e.`id` = l.`event_id` WHERE e.`id` IS NULL ORDER BY l.`id` LIMIT 10'
                );
                foreach ($aRows as $aRow) {
                    if (count($aMissingEventSamples) >= 25)
                        break;
                    $aMissingEventSamples[] = array('type' => $sType) + $aRow;
                }
            }
        }

        if (empty($aCheckedTypes)) {
            $this->add('storage', 'info', 'No supported Timeline media relationships were present',
                'Timeline is present, but Doctor found no photos/videos/files relationship tables to inspect.');
            return;
        }

        if ($iMissingSourceCount > 0) {
            $aLines = array();
            foreach ($aMissingSourceSamples as $aRow)
                $aLines[] = ucfirst((string)$aRow['type']) . ' media ID ' . (int)$aRow['media_id'] . ' is still linked to Timeline event #' . (int)$aRow['event_id'] . '.';
            $this->add('storage', 'warning', 'Timeline media links reference missing source files',
                $iMissingSourceCount . ' Timeline media relationship(s) point to source rows that no longer exist.',
                $this->humanEvidence(
                    'Doctor found Timeline media relationships whose source-storage row is missing.',
                    array(array('title' => 'Affected relationships (up to 25)', 'items' => $aLines)),
                    'Potential broken Timeline media',
                    'Review required before any repair'
                ) + array('checked_types' => $aCheckedTypes, 'relationship_count' => $iRelationshipCount, 'missing_source_count' => $iMissingSourceCount, 'samples' => $aMissingSourceSamples));
        } else {
            $this->add('storage', 'ok', 'Timeline media links point to existing source files', '',
                $this->humanEvidence(
                    'Doctor checked ' . $iRelationshipCount . ' Timeline media relationship(s) across ' . implode(', ', $aCheckedTypes) . '; none reference a missing source row.',
                    array(),
                    'None',
                    'No'
                ) + array('checked_types' => $aCheckedTypes, 'relationship_count' => $iRelationshipCount, 'missing_source_count' => 0));
        }

        if ($iMissingEventCount > 0) {
            $aLines = array();
            foreach ($aMissingEventSamples as $aRow)
                $aLines[] = ucfirst((string)$aRow['type']) . ' relationship #' . (int)$aRow['link_id'] . ' points to missing Timeline event #' . (int)$aRow['event_id'] . '.';
            $this->add('storage', 'warning', 'Timeline media links reference missing Timeline events',
                $iMissingEventCount . ' Timeline media relationship(s) point to event rows that no longer exist.',
                $this->humanEvidence(
                    'Doctor found Timeline media-link rows whose parent Timeline event is missing.',
                    array(array('title' => 'Affected relationships (up to 25)', 'items' => $aLines)),
                    'Potential orphaned Timeline media relationships',
                    'Review required before any repair'
                ) + array('checked_types' => $aCheckedTypes, 'missing_event_count' => $iMissingEventCount, 'samples' => $aMissingEventSamples));
        } else {
            $this->add('storage', 'ok', 'Timeline media links point to existing Timeline events', '',
                array('checked_types' => $aCheckedTypes, 'relationship_count' => $iRelationshipCount, 'missing_event_count' => 0));
        }
    }

    protected function missingTableFields(array $aRequired)
    {
        $aMissing = array();
        foreach ($aRequired as $sTable => $aFields) {
            if (!$this->tableExists($sTable)) {
                $aMissing[] = $sTable . ' (table missing)';
                continue;
            }
            $aPresent = $this->tableFields($sTable);
            foreach ($aFields as $sField) {
                if (!in_array($sField, $aPresent, true))
                    $aMissing[] = $sTable . '.' . $sField;
            }
        }
        return $aMissing;
    }

    protected function humanEvidence($sSummary, array $aSections = array(), $sHealthImpact = '', $sRepairRequired = '')
    {
        return array('human' => array(
            'summary' => (string)$sSummary,
            'sections' => $aSections,
            'health_impact' => (string)$sHealthImpact,
            'repair_required' => (string)$sRepairRequired,
        ));
    }

    protected function extractModulePackageVersion($sConfigPath)
    {
        /*
         * Read package identity without executing another module's
         * install/config.php.
         *
         * php_strip_whitespace() removes PHP comments while preserving
         * quoted values, which prevents a commented historical version
         * from being mistaken for the live package version.
         */
        if (!is_file($sConfigPath)) {
            return array(
                'status' => 'missing',
                'version' => '',
            );
        }

        $sText = @php_strip_whitespace($sConfigPath);

        if (!is_string($sText) || trim($sText) === '') {
            return array(
                'status' => 'unverified',
                'version' => '',
            );
        }

        $aPatterns = array(
            '/[\'"]version[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/i',
            '/\$aConfig\s*\[\s*[\'"]version[\'"]\s*\]\s*=\s*[\'"]([^\'"]+)[\'"]/i',
        );

        foreach ($aPatterns as $sPattern) {
            if (
                preg_match($sPattern, $sText, $aMatch)
                && isset($aMatch[1])
                && trim((string)$aMatch[1]) !== ''
            ) {
                return array(
                    'status' => 'parsed',
                    'version' => trim((string)$aMatch[1]),
                );
            }
        }

        return array(
            'status' => 'unverified',
            'version' => '',
        );
    }


    protected function scanModules()
    {
        if (!$this->tableExists('sys_modules')) {
            $this->add(
                'modules',
                'critical',
                'sys_modules table is missing'
            );
            return;
        }

        $aModules = $this->oDb->getAll(
            'SELECT `name`, `title`, `version`, `path`, `class_prefix`, `enabled`
             FROM `sys_modules`
             ORDER BY `title`'
        );

        $iMissing = 0;
        $iMissingInstall = 0;
        $iVersionChecked = 0;

        $aVersionMismatches = array();
        $aVersionUnverified = array();

        $sModulesRoot =
            rtrim(
                BX_DIRECTORY_PATH_MODULES,
                DIRECTORY_SEPARATOR
            )
            . DIRECTORY_SEPARATOR;

        foreach ($aModules as $aModule) {
            $sPath = trim((string)$aModule['path']);

            if ($sPath === '')
                continue;

            $sFull =
                $sModulesRoot
                . trim($sPath, '/\\')
                . DIRECTORY_SEPARATOR;

            if (!is_dir($sFull)) {
                $iMissing++;

                $this->add(
                    'modules',
                    ((int)$aModule['enabled'] === 1
                        ? 'critical'
                        : 'warning'),
                    'Installed module directory is missing',
                    $aModule['title']
                        . ' ('
                        . $aModule['name']
                        . ')',
                    array(
                        'module' => $aModule['name'],
                        'version' => $aModule['version'],
                        'path' => $sPath,
                        'enabled' => (int)$aModule['enabled'],
                    )
                );

                continue;
            }

            $sConfig =
                $sFull
                . 'install/config.php';

            if (!is_file($sConfig)) {
                $iMissingInstall++;

                $this->add(
                    'modules',
                    'warning',
                    'Module install metadata is missing',
                    $aModule['title']
                        . ' does not have install/config.php at its registered module path.',
                    array(
                        'module' => $aModule['name'],
                        'path' => $sPath,
                    )
                );

                continue;
            }

            /*
             * Package/source provenance:
             * registered database version versus the version declared
             * by the live package source.
             */
            $aPackage =
                $this->extractModulePackageVersion(
                    $sConfig
                );

            if (
                isset($aPackage['status'])
                && $aPackage['status'] === 'parsed'
            ) {
                $iVersionChecked++;

                $sRegisteredVersion =
                    trim((string)$aModule['version']);

                $sPackageVersion =
                    trim((string)$aPackage['version']);

                if (
                    $sRegisteredVersion !== ''
                    && $sPackageVersion !== ''
                    && $sRegisteredVersion !== $sPackageVersion
                ) {
                    $aVersionMismatches[] = array(
                        'module' =>
                            (string)$aModule['name'],
                        'title' =>
                            (string)$aModule['title'],
                        'registered_version' =>
                            $sRegisteredVersion,
                        'package_version' =>
                            $sPackageVersion,
                        'path' =>
                            $sPath,
                        'enabled' =>
                            (int)$aModule['enabled'],
                        'config' =>
                            trim($sPath, '/\\')
                            . '/install/config.php',
                    );
                }

            } else {
                $aVersionUnverified[] = array(
                    'module' =>
                        (string)$aModule['name'],
                    'title' =>
                        (string)$aModule['title'],
                    'registered_version' =>
                        (string)$aModule['version'],
                    'path' =>
                        $sPath,
                    'enabled' =>
                        (int)$aModule['enabled'],
                    'status' =>
                        isset($aPackage['status'])
                            ? (string)$aPackage['status']
                            : 'unverified',
                );
            }
        }


        if ($iMissing === 0) {
            $this->add(
                'modules',
                'ok',
                'All registered module directories were found',
                '',
                array(
                    'module_count' => count($aModules),
                )
            );
        }


        if ($iMissingInstall === 0) {
            $this->add(
                'modules',
                'ok',
                'Registered modules retain their install metadata'
            );
        }


        /*
         * One aggregate provenance finding prevents a large number of
         * per-module findings from artificially inflating health scoring.
         */
        if (!empty($aVersionMismatches)) {

            $aEvidence = $this->humanEvidence(
                'Doctor found '
                    . count($aVersionMismatches)
                    . ' module(s) whose registered database version differs from the version declared by the live package source.',
                array(),
                'Package/source provenance is inconsistent. The registered module identity and live source package do not agree.',
                'Establish which package is authoritative before updating, repairing, or re-baselining the affected module.'
            );

            $aEvidence['modules_checked'] =
                $iVersionChecked;

            $aEvidence['mismatch_count'] =
                count($aVersionMismatches);

            $aEvidence['mismatches'] =
                array_slice(
                    $aVersionMismatches,
                    0,
                    50
                );

            $aEvidence['unverified_count'] =
                count($aVersionUnverified);

            if (!empty($aVersionUnverified)) {
                $aEvidence['unverified_modules'] =
                    array_slice(
                        $aVersionUnverified,
                        0,
                        50
                    );
            }

            $this->add(
                'modules',
                'warning',
                'Registered module versions differ from live package metadata',
                '',
                $aEvidence
            );

        } elseif (!empty($aVersionUnverified)) {

            $aEvidence = $this->humanEvidence(
                'Doctor confirmed no package/source version mismatch in the modules it could verify, but '
                    . count($aVersionUnverified)
                    . ' module package version(s) could not be read safely.',
                array(),
                'No mismatch was confirmed. Package provenance is incomplete for the listed modules.',
                'Review only the unverified package metadata. Do not guess or rewrite module versions.'
            );

            $aEvidence['modules_checked'] =
                $iVersionChecked;

            $aEvidence['mismatch_count'] = 0;

            $aEvidence['unverified_count'] =
                count($aVersionUnverified);

            $aEvidence['unverified_modules'] =
                array_slice(
                    $aVersionUnverified,
                    0,
                    50
                );

            $this->add(
                'modules',
                'info',
                'Some live module package versions could not be verified',
                '',
                $aEvidence
            );

        } else {

            $aEvidence = $this->humanEvidence(
                'Doctor compared '
                    . $iVersionChecked
                    . ' registered module version(s) with their live install/config.php package metadata; all versions agree.',
                array(),
                'None',
                'No'
            );

            $aEvidence['modules_checked'] =
                $iVersionChecked;

            $aEvidence['mismatch_count'] = 0;
            $aEvidence['unverified_count'] = 0;

            $this->add(
                'modules',
                'ok',
                'Registered module versions match live package metadata',
                '',
                $aEvidence
            );
        }


        $aDupNames = $this->oDb->getAll(
            'SELECT `name`, COUNT(*) AS `cnt`
             FROM `sys_modules`
             GROUP BY `name`
             HAVING COUNT(*) > 1'
        );

        if (!empty($aDupNames)) {
            $this->add(
                'modules',
                'critical',
                'Duplicate module names are registered',
                '',
                array(
                    'duplicates' => $aDupNames,
                )
            );
        } else {
            $this->add(
                'modules',
                'ok',
                'Module registration names are unique'
            );
        }
    }



    protected function scanFileIntegrity()
    {
        if (!$this->tableExists('sys_modules_file_tracks')) {
            $this->add(
                'file_integrity',
                'warning',
                'UNA module checksum tracking table is missing',
                'sys_modules_file_tracks is required for module file-integrity diagnostics.'
            );
            return;
        }

        $aModules = $this->oDb->getAll(
            "SELECT `id`, `name`, `title`, `version`, `path`, `enabled`
             FROM `sys_modules`
             WHERE `id` > 0
               AND `path` <> ''
             ORDER BY `title`, `name`"
        );

        $aRows = $this->oDb->getAll(
            "SELECT `module_id`, `file`, `hash`
             FROM `sys_modules_file_tracks`
             WHERE `module_id` > 0
             ORDER BY `module_id`, `file`"
        );

        $aTracks = array();

        foreach ($aRows as $aRow) {
            $iId = (int)$aRow['module_id'];

            if (!isset($aTracks[$iId]))
                $aTracks[$iId] = array();

            $aTracks[$iId][(string)$aRow['file']] =
                strtolower(trim((string)$aRow['hash']));
        }

        $aNoBaseline = array();
        $aModified = array();
        $aMissing = array();
        $aBlocked = array();
        $aAllowedChanges = array();
        $aPreviewErrors = array();

        $this->aModuleBackupFiles = array();
        $this->aRuntimeContentCandidates = array();

        $iWithBaseline = 0;
        $iTracked = 0;
        $iPreviewed = 0;

        $bForce = getParam(
            'sys_autoupdate_force_modified_files'
        ) === 'on';

        $fLimit = $this->getUnaModifiedFileLimit();

        $sModulesRoot =
            rtrim(BX_DIRECTORY_PATH_MODULES, '/\\')
            . DIRECTORY_SEPARATOR;

        foreach ($aModules as $aModule) {
            $iId = (int)$aModule['id'];

            if (empty($aTracks[$iId])) {
                $aNoBaseline[] = array(
                    'module' => (string)$aModule['name'],
                    'title' => (string)$aModule['title'],
                    'version' => (string)$aModule['version'],
                    'path' => (string)$aModule['path'],
                );
                continue;
            }

            $iWithBaseline++;

            $aExpected = $aTracks[$iId];
            $iTracked += count($aExpected);

            $sRoot = $sModulesRoot
                . trim((string)$aModule['path'], '/\\')
                . DIRECTORY_SEPARATOR;

            if (!is_dir($sRoot)) {
                $aPreviewErrors[] = array(
                    'module' => (string)$aModule['name'],
                    'title' => (string)$aModule['title'],
                    'reason' => 'Registered module directory is missing.',
                );
                continue;
            }

            try {
                /*
                 * ONE UNA hashFiles() pass for this module.
                 */
                $aCurrentRows =
                    $this->collectCurrentModuleHashes($sRoot);

                $iPreviewed++;

                $aCurrent = array();

                foreach ($aCurrentRows as $aFile) {
                    $sFile = (string)$aFile['file'];

                    $aCurrent[$sFile] =
                        strtolower((string)$aFile['hash']);

                    if ($this->isBackupLikeModuleFile($sFile)) {
                        $this->aModuleBackupFiles[] = array(
                            'module' => (string)$aModule['name'],
                            'title' => (string)$aModule['title'],
                            'file' => $sFile,
                        );
                    }
                }

                /*
                 * Doctor integrity:
                 * expected baseline -> current filesystem.
                 */
                $iModuleModified = 0;
                $iModuleMissing = 0;

                foreach ($aExpected as $sFile => $sExpected) {
                    if (!array_key_exists($sFile, $aCurrent)) {
                        $iModuleMissing++;
                        $aMissing[] = array(
                            'module' => (string)$aModule['name'],
                            'title' => (string)$aModule['title'],
                            'file' => $sFile,
                            'expected_md5' => $sExpected,
                        );
                        continue;
                    }

                    if (!hash_equals(
                        $sExpected,
                        $aCurrent[$sFile]
                    )) {
                        $iModuleModified++;
                        $aModified[] = array(
                            'module' => (string)$aModule['name'],
                            'title' => (string)$aModule['title'],
                            'file' => $sFile,
                            'expected_md5' => $sExpected,
                            'current_md5' => $aCurrent[$sFile],
                        );
                    }
                }

                /*
                 * Mirror UNA Studio hashCheck():
                 * current file is changed when it is new OR its
                 * checksum differs from the recorded baseline.
                 *
                 * Missing baseline files are intentionally not
                 * counted by Studio's percentage calculation.
                 */
                $aStudioChanged = array();
                $aNewFiles = array();

                foreach ($aCurrent as $sFile => $sHash) {
                    if (!isset($aExpected[$sFile])) {
                        $aStudioChanged[] = $sFile;
                        $aNewFiles[] = $sFile;
                        continue;
                    }

                    if ($aExpected[$sFile] !== $sHash)
                        $aStudioChanged[] = $sFile;
                }

                /*
                 * Distinguish tracked source drift from large
                 * concentrations of untracked runtime/generated
                 * content inside the module tree.
                 *
                 * This DOES NOT exclude those files from the UNA
                 * Studio calculation. It only explains the cause.
                 */
                $aRuntimeClassification =
                    $this->classifyRuntimeModuleContent(
                        $aNewFiles,
                        $iModuleModified,
                        $iModuleMissing
                    );

                if (!empty($aRuntimeClassification)) {
                    $aRuntimeRow = array_merge(
                        array(
                            'module' => (string)$aModule['name'],
                            'title' => (string)$aModule['title'],
                            'version' => (string)$aModule['version'],
                            'path' => (string)$aModule['path'],
                            'tracked_source_files' =>
                                count($aExpected),
                            'modified_tracked_files' =>
                                $iModuleModified,
                            'missing_tracked_files' =>
                                $iModuleMissing,
                        ),
                        $aRuntimeClassification
                    );

                    $aRuntimeRow['generator_discovery'] =
                        $this->discoverModuleCronCandidates(
                            (string)$aModule['path']
                        );

                    $this->aRuntimeContentCandidates[] =
                        $aRuntimeRow;
                }

                $fPercent = count($aExpected) > 0
                    ? count($aStudioChanged) / count($aExpected)
                    : 0;

                $bBlocked = false;

                if (!empty($aStudioChanged) && !$bForce)
                    $bBlocked = true;
                elseif ($bForce && $fPercent > $fLimit)
                    $bBlocked = true;

                $aGateRow = array(
                    'module' => (string)$aModule['name'],
                    'title' => (string)$aModule['title'],
                    'changed_count' => count($aStudioChanged),
                    'changed_percent' => round(
                        $fPercent * 100,
                        2
                    ),
                    'changed_files' => array_slice(
                        $aStudioChanged,
                        0,
                        50
                    ),
                    'source_modified_count' => $iModuleModified,
                    'source_missing_count' => $iModuleMissing,
                    'source_integrity' => ($iModuleModified === 0 && $iModuleMissing === 0 ? 'clean' : 'drifted'),
                    'content_classification' => (!empty($aRuntimeClassification) ? $aRuntimeClassification : array()),
                    'new_untracked_count' => count($aNewFiles),
                    'new_untracked_files' => array_slice(
                        $aNewFiles,
                        0,
                        50
                    ),
                );

                if ($bBlocked)
                    $aBlocked[] = $aGateRow;
                elseif (!empty($aStudioChanged))
                    $aAllowedChanges[] = $aGateRow;

            } catch (Throwable $e) {
                $aPreviewErrors[] = array(
                    'module' => (string)$aModule['name'],
                    'title' => (string)$aModule['title'],
                    'reason' => $e->getMessage(),
                );
            }
        }

        /*
         * Check 26 — checksum baseline availability.
         */
        if (empty($aNoBaseline)) {
            $aEvidence = $this->humanEvidence(
                'Doctor found a recorded UNA checksum baseline for every installed module in the current module-integrity scope.',
                array(),
                'None',
                'No'
            );

            $aEvidence['modules_checked'] = count($aModules);
            $aEvidence['modules_with_baseline'] = $iWithBaseline;

            $this->add(
                'file_integrity',
                'ok',
                'Installed modules have checksum baselines',
                '',
                $aEvidence
            );
        } else {
            $aEvidence = $this->humanEvidence(
                count($aNoBaseline)
                    . ' installed module(s) have no recorded checksum baseline.',
                array(),
                'None by itself; source integrity cannot be verified for those modules.',
                'No automatic repair. Establish a baseline only through a trusted installation or update workflow.'
            );

            $aEvidence['modules_without_baseline_count'] =
                count($aNoBaseline);

            $aEvidence['modules_without_baseline'] =
                array_slice($aNoBaseline, 0, 50);

            $this->add(
                'file_integrity',
                'info',
                'Some installed modules have no checksum baseline',
                '',
                $aEvidence
            );
        }

        /*
         * Check 27 — strict Doctor integrity.
         *
         * IMPORTANT:
         * A module which could not be hashed is UNKNOWN, not OK.
         */
        /*
         * Separate evidence-backed compatibility overrides from
         * unexplained source drift.
         *
         * UNA's authentic checksum baseline remains unchanged.
         */
        $aApprovedOverrides = array();
        $aUnapprovedModified = array();

        foreach ($aModified as $aModifiedFile) {
            $aApproval =
                $this->inspectApprovedCompatibilityOverride(
                    isset($aModifiedFile['module'])
                        ? (string)$aModifiedFile['module']
                        : '',
                    isset($aModifiedFile['file'])
                        ? (string)$aModifiedFile['file']
                        : '',
                    isset($aModifiedFile['expected_md5'])
                        ? (string)$aModifiedFile['expected_md5']
                        : '',
                    isset($aModifiedFile['current_md5'])
                        ? (string)$aModifiedFile['current_md5']
                        : ''
                );

            if (!empty($aApproval['verified'])) {
                $aModifiedFile['approval'] = $aApproval;
                $aApprovedOverrides[] = $aModifiedFile;
                continue;
            }

            $aUnapprovedModified[] = $aModifiedFile;
        }

        $aModified = $aUnapprovedModified;

        if (
            empty($aModified)
            && empty($aMissing)
            && empty($aPreviewErrors)
        ) {
            $aEvidence = $this->humanEvidence(
                !empty($aApprovedOverrides)
                    ? 'Doctor found '
                        . count($aApprovedOverrides)
                        . ' approved compatibility override(s) and no unexplained tracked-file checksum drift.'
                    : 'Doctor checked '
                        . $iTracked
                        . ' tracked module file(s); all baseline files retain their recorded MD5 checksums.',
                array(),
                'None',
                'No'
            );

            $aEvidence['tracked_files_checked'] = $iTracked;
            $aEvidence['approved_override_count'] =
                count($aApprovedOverrides);

            if (!empty($aApprovedOverrides)) {
                $aEvidence['approved_compatibility_overrides'] =
                    array_slice($aApprovedOverrides, 0, 50);
            }
            $aEvidence['modified_count'] = 0;
            $aEvidence['missing_count'] = 0;
            $aEvidence['unverified_module_count'] = 0;

            $this->add(
                'file_integrity',
                !empty($aApprovedOverrides)
                    ? 'info'
                    : 'ok',
                !empty($aApprovedOverrides)
                    ? 'Only approved compatibility overrides differ from recorded checksums'
                    : 'Tracked module files retain their recorded checksums',
                '',
                $aEvidence
            );

        } elseif (
            empty($aModified)
            && empty($aMissing)
            && !empty($aPreviewErrors)
        ) {
            $aEvidence = $this->humanEvidence(
                'Doctor could not complete file-integrity verification for '
                    . count($aPreviewErrors)
                    . ' module(s). No checksum differences were confirmed in the modules that were successfully inspected.',
                array(),
                'Integrity status is incomplete because one or more module trees could not be hashed.',
                'Review the hashing errors. An unverified module must never be treated as checksum-clean.'
            );

            $aEvidence['tracked_files_checked'] = $iTracked;
            $aEvidence['approved_override_count'] =
                count($aApprovedOverrides);

            if (!empty($aApprovedOverrides)) {
                $aEvidence['approved_compatibility_overrides'] =
                    array_slice($aApprovedOverrides, 0, 50);
            }
            $aEvidence['modified_count'] = 0;
            $aEvidence['missing_count'] = 0;
            $aEvidence['unverified_module_count'] =
                count($aPreviewErrors);

            $aEvidence['unverified_modules'] =
                array_slice($aPreviewErrors, 0, 50);

            $this->add(
                'file_integrity',
                'warning',
                'Module file integrity could not be verified completely',
                '',
                $aEvidence
            );

        } else {
            $aEvidence = $this->humanEvidence(
                'Doctor found '
                    . count($aModified)
                    . ' modified tracked file(s) and '
                    . count($aMissing)
                    . ' missing tracked file(s).'
                    . (
                        !empty($aPreviewErrors)
                            ? ' In addition, '
                                . count($aPreviewErrors)
                                . ' module(s) could not be fully inspected.'
                            : ''
                    ),
                array(),
                'Potential module source drift. Affected source should be treated as unverified.',
                'Review required. Do not automatically re-baseline affected modules.'
            );

            $aEvidence['tracked_files_checked'] = $iTracked;
            $aEvidence['approved_override_count'] =
                count($aApprovedOverrides);

            if (!empty($aApprovedOverrides)) {
                $aEvidence['approved_compatibility_overrides'] =
                    array_slice($aApprovedOverrides, 0, 50);
            }
            $aEvidence['modified_count'] = count($aModified);
            $aEvidence['missing_count'] = count($aMissing);

            $aEvidence['modified_files'] =
                array_slice($aModified, 0, 100);

            $aEvidence['missing_files'] =
                array_slice($aMissing, 0, 100);

            $aEvidence['unverified_module_count'] =
                count($aPreviewErrors);

            if (!empty($aPreviewErrors)) {
                $aEvidence['unverified_modules'] =
                    array_slice($aPreviewErrors, 0, 50);
            }

            $this->add(
                'file_integrity',
                'warning',
                'Tracked module source files differ from UNA checksum records',
                '',
                $aEvidence
            );
        }

        /*
         * Check 28 — Studio update readiness.
         */
        $aEvidence = $this->humanEvidence(
            'Doctor previewed UNA Studio checksum/update readiness with Force modified files '
                . ($bForce ? 'ON' : 'OFF')
                . ' and a '
                . round($fLimit * 100, 2)
                . '% modified-file ceiling.',
            array(),
            !empty($aBlocked)
                ? 'None to current runtime health. The condition affects module update eligibility.'
                : 'None',
            !empty($aBlocked)
                ? 'No automatic repair. Verify affected module source before updating.'
                : 'No'
        );

        $aEvidence['force_modified_files'] =
            $bForce ? 'on' : 'off';

        $aEvidence['maximum_changed_percent'] =
            round($fLimit * 100, 2);

        $aEvidence['modules_previewed'] = $iPreviewed;

        $aEvidence['blocked_modules'] =
            array_slice($aBlocked, 0, 50);

        $aEvidence['allowed_modules_with_changes'] =
            array_slice($aAllowedChanges, 0, 50);

        $aEvidence['preview_errors'] =
            array_slice($aPreviewErrors, 0, 50);

        if (!empty($aBlocked) || !empty($aPreviewErrors)) {
            $this->add(
                'file_integrity',
                'info',
                !empty($aBlocked)
                    ? 'UNA Studio checksum gate would block one or more module updates'
                    : 'Update readiness could not be previewed for some modules',
                '',
                $aEvidence
            );
        } else {
            $this->add(
                'file_integrity',
                'ok',
                'UNA Studio checksum gate currently permits tracked module updates',
                '',
                $aEvidence
            );
        }

        /*
         * Check 30 — runtime content inside module source trees.
         *
         * This is informational. These files may be completely
         * legitimate at runtime while still blocking Studio's
         * checksum gate.
         */
        if (empty($this->aRuntimeContentCandidates)) {
            $aEvidence = $this->humanEvidence(
                'Doctor found no high-confidence concentrations of runtime/generated content affecting module update readiness.',
                array(),
                'None',
                'No'
            );

            $this->add(
                'file_integrity',
                'ok',
                'No runtime-generated module content is affecting update readiness',
                '',
                $aEvidence
            );

        } else {
            $aEvidence = $this->humanEvidence(
                'Doctor identified '
                    . count($this->aRuntimeContentCandidates)
                    . ' module(s) whose tracked source is clean but whose module tree contains a large concentration of untracked runtime/generated content.',
                array(),
                'No current source-integrity impact was confirmed for these modules. UNA Studio may still block updates because the generated files live inside the module directory.',
                'Do not re-baseline generated content. Review an update-preparation workflow that temporarily protects runtime data outside the module tree.'
            );

            $aEvidence['runtime_content_modules'] =
                array_slice(
                    $this->aRuntimeContentCandidates,
                    0,
                    50
                );

            $this->add(
                'file_integrity',
                'info',
                'Runtime-generated module content is affecting Studio update readiness',
                '',
                $aEvidence
            );
        }
    }


    protected function classifyRuntimeModuleContent(
        array $aNewFiles,
        $iModified,
        $iMissing
    ) {
        $iModified = (int)$iModified;
        $iMissing = (int)$iMissing;

        /*
         * Runtime-content classification is only appropriate
         * when the tracked source baseline itself is clean.
         */
        if ($iModified !== 0 || $iMissing !== 0)
            return array();

        $iTotal = count($aNewFiles);

        /*
         * Avoid classifying a handful of harmless untracked files.
         */
        if ($iTotal < 100)
            return array();

        $aGroups = array();

        foreach ($aNewFiles as $sFile) {
            $sFile = str_replace(
                '\\',
                '/',
                ltrim((string)$sFile, '/\\')
            );

            if ($sFile === '')
                continue;

            $aParts = explode('/', $sFile);
            $sTop = strtolower(trim($aParts[0]));

            if ($sTop === '')
                $sTop = '(root)';

            if (!isset($aGroups[$sTop]))
                $aGroups[$sTop] = 0;

            $aGroups[$sTop]++;
        }

        if (empty($aGroups))
            return array();

        arsort($aGroups);

        $sDominant = (string)key($aGroups);
        $iDominant = (int)current($aGroups);

        $fDominant =
            $iTotal > 0
                ? ($iDominant / $iTotal) * 100
                : 0;

        /*
         * High-confidence runtime directories.
         *
         * Doctor does NOT exclude these from Studio hashing.
         * This list is used only for explanatory classification.
         */
        $aRuntimeDirectories = array(
            'media',
            'upload',
            'uploads',
            'storage',
            'cache',
            'tmp',
            'runtime',
            'generated'
        );

        if (
            !in_array(
                $sDominant,
                $aRuntimeDirectories,
                true
            )
            || $fDominant < 90
        ) {
            return array();
        }

        return array(
            'classification' =>
                'runtime_generated_module_content',

            'confidence' => 'high',

            'source_integrity' => 'clean',

            'dominant_directory' =>
                $sDominant . '/',

            'dominant_file_count' =>
                $iDominant,

            'dominant_percent' =>
                round($fDominant, 2),

            'new_untracked_count' =>
                $iTotal,

            'studio_effect' =>
                'These files remain part of UNA Studio checksum/update readiness because they live inside the module tree.',

            'recommended_action' =>
                'Do not re-baseline generated content. Before a module update, protect the runtime directory outside the module tree, verify update readiness, perform the update, restore runtime content, verify the module, then resume its generator/import process.'
        );
    }


    public function getModuleIntegritySnapshot($sModule)
    {
        $sModule = trim((string)$sModule);

        $aResult = array(
            'ok' => false,
            'module' => $sModule,
            'error' => '',
        );

        if ($sModule === '') {
            $aResult['error'] =
                'A module name is required.';

            return $aResult;
        }

        if (!$this->tableExists('sys_modules_file_tracks')) {
            $aResult['error'] =
                'UNA module checksum tracking table is missing.';

            return $aResult;
        }

        $aModule = $this->oDb->getRow(
            "SELECT `id`, `name`, `title`, `version`, `path`, `enabled`
             FROM `sys_modules`
             WHERE `name` = ?
               AND `id` > 0
               AND `path` <> ''
             LIMIT 1",
            array($sModule)
        );

        if (empty($aModule)) {
            $aResult['error'] =
                'The requested module is not registered.';

            return $aResult;
        }

        $iModuleId =
            (int)$aModule['id'];

        $aTrackRows = $this->oDb->getAll(
            "SELECT `file`, `hash`
             FROM `sys_modules_file_tracks`
             WHERE `module_id` = ?
             ORDER BY `file`",
            array($iModuleId)
        );

        if (empty($aTrackRows)) {
            $aResult['error'] =
                'The requested module has no UNA checksum baseline.';

            return $aResult;
        }

        $aExpected = array();

        foreach ($aTrackRows as $aRow) {
            $aExpected[
                (string)$aRow['file']
            ] = strtolower(
                trim(
                    (string)$aRow['hash']
                )
            );
        }

        $sModuleRoot =
            rtrim(
                BX_DIRECTORY_PATH_MODULES,
                '/\\'
            )
            . DIRECTORY_SEPARATOR
            . trim(
                (string)$aModule['path'],
                '/\\'
            )
            . DIRECTORY_SEPARATOR;

        if (!is_dir($sModuleRoot)) {
            $aResult['error'] =
                'The registered module directory is missing.';

            return $aResult;
        }

        try {
            $aCurrentRows =
                $this->collectCurrentModuleHashes(
                    $sModuleRoot
                );
        } catch (Throwable $e) {
            $aResult['error'] =
                'UNA module hashing failed: '
                . $e->getMessage();

            return $aResult;
        }

        $aCurrent = array();

        foreach ($aCurrentRows as $aFile) {
            $sFile =
                (string)($aFile['file'] ?? '');

            if ($sFile === '')
                continue;

            $aCurrent[$sFile] =
                strtolower(
                    (string)(
                        $aFile['hash']
                        ?? ''
                    )
                );
        }

        ksort($aCurrent);

        $aModified = array();
        $aMissing = array();
        $aNew = array();
        $aStudioChanged = array();

        foreach (
            $aExpected
            as $sFile => $sExpectedHash
        ) {
            if (!array_key_exists($sFile, $aCurrent)) {
                $aMissing[] = array(
                    'file' =>
                        $sFile,
                    'expected_md5' =>
                        $sExpectedHash,
                );

                continue;
            }

            if (
                !hash_equals(
                    $sExpectedHash,
                    $aCurrent[$sFile]
                )
            ) {
                $aModified[] = array(
                    'file' =>
                        $sFile,
                    'expected_md5' =>
                        $sExpectedHash,
                    'current_md5' =>
                        $aCurrent[$sFile],
                );
            }
        }

        foreach (
            $aCurrent
            as $sFile => $sCurrentHash
        ) {
            if (!isset($aExpected[$sFile])) {
                $aNew[] = array(
                    'file' =>
                        $sFile,
                    'current_md5' =>
                        $sCurrentHash,
                );

                $aStudioChanged[] =
                    $sFile;

                continue;
            }

            if (
                !hash_equals(
                    $aExpected[$sFile],
                    $sCurrentHash
                )
            ) {
                $aStudioChanged[] =
                    $sFile;
            }
        }

        $aResult = array(
            'ok' =>
                true,

            'module' =>
                (string)$aModule['name'],

            'title' =>
                (string)$aModule['title'],

            'version' =>
                (string)$aModule['version'],

            'path' =>
                (string)$aModule['path'],

            'enabled' =>
                (int)$aModule['enabled'],

            'module_id' =>
                $iModuleId,

            'tracked_count' =>
                count($aExpected),

            'current_count' =>
                count($aCurrent),

            'modified_tracked_count' =>
                count($aModified),

            'missing_tracked_count' =>
                count($aMissing),

            'new_untracked_count' =>
                count($aNew),

            'studio_changed_count' =>
                count($aStudioChanged),

            'source_integrity' =>
                (
                    empty($aModified)
                    && empty($aMissing)
                )
                    ? 'clean'
                    : 'drifted',

            /*
             * Full arrays are intentional. The health-scan UI
             * may truncate evidence, but repair planning must
             * reason about every file.
             */
            'tracked' =>
                $aExpected,

            'current_hashes' =>
                $aCurrent,

            'modified_tracked' =>
                $aModified,

            'missing_tracked' =>
                $aMissing,

            'new_untracked' =>
                $aNew,

            'studio_changed' =>
                $aStudioChanged,

            'error' =>
                '',
        );

        return $aResult;
    }


    protected function collectCurrentModuleHashes($sModuleRoot)
    {
        $sInstaller = BX_DIRECTORY_PATH_ROOT
            . 'studio/classes/BxDolStudioInstaller.php';

        if (!class_exists('BxDolStudioInstaller', false)) {
            if (!is_file($sInstaller))
                throw new RuntimeException(
                    'BxDolStudioInstaller.php was not found.'
                );

            require_once $sInstaller;
        }

        $sModulesRoot =
            rtrim(BX_DIRECTORY_PATH_MODULES, '/\\')
            . DIRECTORY_SEPARATOR;

        $sRoot =
            rtrim((string)$sModuleRoot, '/\\')
            . DIRECTORY_SEPARATOR;

        if (strpos($sRoot, $sModulesRoot) !== 0) {
            throw new RuntimeException(
                'Module hash root is outside BX_DIRECTORY_PATH_MODULES.'
            );
        }

        /*
         * Use UNA's real hashFiles() implementation without
         * invoking BxDolStudioInstaller::__construct().
         *
         * The full Studio constructor initializes translated
         * UI action labels through _t(), which is not guaranteed
         * to exist in Doctor's CLI/background scan context.
         *
         * The only installer state hashFiles() requires here is
         * UNA's own non-hashable module paths:
         *
         *     install
         *     updates
         */
        $oHasher = new class($sRoot)
            extends BxDolStudioInstaller
        {
            protected $mudRoot;

            public function __construct($sRoot)
            {
                $this->mudRoot =
                    rtrim((string)$sRoot, '/\\')
                    . DIRECTORY_SEPARATOR;

                $this->_aNonHashableFiles = array(
                    'install',
                    'updates'
                );
            }

            protected function filePathWithoutBase($sPath)
            {
                return bx_ltrim_str(
                    $sPath,
                    $this->mudRoot
                );
            }
        };

        $aFiles = array();

        $oHasher->hashFiles(
            $sRoot,
            $aFiles
        );

        return $aFiles;
    }

    protected function isBackupLikeModuleFile($sFile)
    {
        $sName = basename((string)$sFile);

        /*
         * Match backup COPIES, not legitimate source classes
         * whose semantic name happens to contain "Backup".
         *
         * Matches examples such as:
         *
         *   SomeFile.php.bak
         *   SomeFile.php.bak_20260830
         *   SomeFile.php.old
         *   SomeFile.php.orig
         *   SomeFile.php.save
         *   SomeFile.php~
         *   SomeFile_backup_20260830.php
         *   backup_SomeFile.php
         *
         * Does NOT match:
         *
         *   MyUnaWorldBackup.php
         */
        return preg_match(
            '/(?:'
                . '\.bak(?:[._-].*)?$'
                . '|\.(?:old|orig|save)$'
                . '|~$'
                . '|(?:^|[._-])backup(?:[._-]|$)'
            . ')/i',
            $sName
        ) === 1;
    }

    protected function getRepairBackupRoot()
    {
        return BX_DIRECTORY_PATH_ROOT
            . 'tmp/my-una-doctor-repairs/';
    }

    protected function getUnaModifiedFileLimit()
    {
        if (!defined('BX_FORCE_AUTOUPDATE_MAX_CHANGED_FILES_PERCENT')) {
            $sUtils = BX_DIRECTORY_PATH_ROOT
                . 'inc/classes/BxDolInstallerUtils.php';

            if (is_file($sUtils))
                require_once $sUtils;
        }

        if (defined('BX_FORCE_AUTOUPDATE_MAX_CHANGED_FILES_PERCENT'))
            return (float)BX_FORCE_AUTOUPDATE_MAX_CHANGED_FILES_PERCENT;

        /*
         * Safe fallback for an unusual UNA build where the installer
         * utility cannot be loaded. The live UNA 15 build currently
         * defines the limit as 0.05.
         */
        return 0.05;
    }


    protected function discoverModuleCronCandidates($sModulePath)
    {
        $aResult = array(
            'method' =>
                'module_file_path_ownership',

            'module_path_prefix' => '',

            'candidate_count' => 0,

            'candidates' => array(),
        );

        if (!$this->tableExists('sys_cron_jobs'))
            return $aResult;

        $sModulePath = trim(
            str_replace(
                '\\',
                '/',
                (string)$sModulePath
            ),
            '/'
        );

        if ($sModulePath === '')
            return $aResult;

        $sPrefix =
            'modules/'
            . $sModulePath
            . '/';

        $aResult['module_path_prefix'] =
            $sPrefix;

        $aRows = $this->oDb->getAll(
            "SELECT `id`, `name`, `time`, `class`,
                    `file`, `service_call`, `ts`, `timing`
             FROM `sys_cron_jobs`
             WHERE `file` <> ''
             ORDER BY `id`"
        );

        foreach ($aRows as $aRow) {
            $sFile = str_replace(
                '\\',
                '/',
                (string)$aRow['file']
            );

            if (strpos($sFile, $sPrefix) !== 0)
                continue;

            $aResult['candidates'][] =
                $aRow;
        }

        $aResult['candidate_count'] =
            count($aResult['candidates']);

        return $aResult;
    }


    protected function scanRepairCandidates()
    {
        $aActive = array();
        $aHistorical = array();

        /*
         * Runtime-generated module content is an update-readiness
         * advisory, not evidence of damaged module source.
         *
         * File Integrity already records these modules in
         * aRuntimeContentCandidates and emits the dedicated INFO
         * finding. Do not place them into $aActive repairs.
         *
         * The Studio UI may offer an administrator-initiated
         * "Prepare for Update" workflow from that INFO evidence.
         */
        /*
         * Backup policy candidates collected during the same
         * module hash traversal. No second filesystem scan.
         */
        if (!empty($this->aModuleBackupFiles)) {
            $aActive[] = array(
                'rule' => 'repair_backup_inside_module_tree',
                'confidence' => 'high',
                'status' => 'review',
                'summary' => 'Backup-like files exist inside a tracked UNA module tree.',
                'recommended_action' =>
                    'Move repair backups outside the module tree to '
                    . $this->getRepairBackupRoot(),
                'files' => array_slice(
                    $this->aModuleBackupFiles,
                    0,
                    50
                ),
            );
        }

        /*
         * Verified Module Reference drift.
         *
         * Only cryptographically verified persistent references
         * matching the currently installed module version are
         * considered.
         *
         * Comparison is explicitly read-only:
         * - no module files are modified
         * - no comparison evidence session is created
         * - no Reference Library history is updated
         * - automatic repair remains disabled
         *
         * Explained differences are NOT repair candidates:
         * - UNA-baseline-backed modifications
         * - approved Local Repair Knowledge
         * - baseline-backed live-only files
         * - recognized runtime-generated live-only files
         *
         * A review candidate is emitted only when Doctor proves:
         * - unexplained modified files, or
         * - files missing from live relative to the reference, or
         * - unexplained live-only files.
         */
        $aReferences =
            $this->oModule
                ->getModuleReferenceLibrary();

        if (is_array($aReferences)) {
            foreach ($aReferences as $aReference) {
                if (
                    !is_array($aReference)
                    || empty(
                        $aReference[
                            'library_verified'
                        ]
                    )
                ) {
                    continue;
                }

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

                $sReferenceSha256 =
                    strtolower(
                        trim(
                            (string)(
                                $aReference[
                                    'reference_sha256'
                                ]
                                ?? ''
                            )
                        )
                    );

                if (
                    $sReferenceModule === ''
                    || $sReferenceVersion === ''
                    || strlen($sReferenceSha256) !== 64
                    || !ctype_xdigit($sReferenceSha256)
                ) {
                    continue;
                }

                /*
                 * Do not compare a reference for some other
                 * installed version. Version drift is not proof
                 * of source corruption.
                 */
                $aInstalledReferenceModule =
                    $this->oDb->getRow(
                        "SELECT `name`, `title`, `version`
                         FROM `sys_modules`
                         WHERE `name` = ?
                         LIMIT 1",
                        array(
                            $sReferenceModule
                        )
                    );

                if (
                    !is_array(
                        $aInstalledReferenceModule
                    )
                    || empty(
                        $aInstalledReferenceModule
                    )
                    || trim(
                        (string)(
                            $aInstalledReferenceModule[
                                'version'
                            ]
                            ?? ''
                        )
                    ) !== $sReferenceVersion
                ) {
                    continue;
                }

                $aReferenceComparison =
                    $this->oModule
                        ->compareSavedModuleReferenceReadOnly(
                            $sReferenceSha256
                        );

                if (
                    !is_array(
                        $aReferenceComparison
                    )
                    || empty(
                        $aReferenceComparison['ok']
                    )
                ) {
                    /*
                     * A failed comparison is not itself proof
                     * that the module needs repair.
                     */
                    continue;
                }

                $aReferenceSummary =
                    isset(
                        $aReferenceComparison[
                            'comparison'
                        ]
                    )
                    && is_array(
                        $aReferenceComparison[
                            'comparison'
                        ]
                    )
                        ? $aReferenceComparison[
                            'comparison'
                        ]
                        : array();

                $iUnexplainedModified =
                    (int)(
                        $aReferenceSummary[
                            'unexplained_modified_count'
                        ]
                        ?? 0
                    );

                $iMissing =
                    (int)(
                        $aReferenceSummary[
                            'missing_count'
                        ]
                        ?? 0
                    );

                $iUnexplainedLiveOnly =
                    (int)(
                        $aReferenceSummary[
                            'unexplained_live_only_count'
                        ]
                        ?? 0
                    );

                if (
                    $iUnexplainedModified <= 0
                    && $iMissing <= 0
                    && $iUnexplainedLiveOnly <= 0
                ) {
                    continue;
                }

                $iDrift =
                    $iUnexplainedModified
                    + $iMissing
                    + $iUnexplainedLiveOnly;

                $sInstalledTitle =
                    trim(
                        (string)(
                            $aInstalledReferenceModule[
                                'title'
                            ]
                            ?? ''
                        )
                    );

                if ($sInstalledTitle === '')
                    $sInstalledTitle =
                        $sReferenceModule;

                $aActive[] = array(
                    'rule' =>
                        'verified_module_reference_drift',

                    'confidence' =>
                        'high',

                    'status' =>
                        'review',

                    'automatic_repair' =>
                        false,

                    'module' =>
                        $sReferenceModule,

                    'title' =>
                        $sInstalledTitle,

                    'module_version' =>
                        $sReferenceVersion,

                    'reference_sha256' =>
                        $sReferenceSha256,

                    'reference_filename' =>
                        (string)(
                            $aReference[
                                'original_filename'
                            ]
                            ?? ''
                        ),

                    'summary' =>
                        'Verified Module Reference drift detected for '
                        . $sInstalledTitle
                        . '.',

                    'recommended_action' =>
                        'Review the verified reference differences before approving any repair. Doctor has not changed any module files.',

                    'drift_count' =>
                        $iDrift,

                    'unexplained_modified_count' =>
                        $iUnexplainedModified,

                    'missing_count' =>
                        $iMissing,

                    'unexplained_live_only_count' =>
                        $iUnexplainedLiveOnly,

                    /*
                     * summarizeComparison() already bounds file
                     * evidence arrays, so preserving the summary
                     * here does not expose the unbounded raw tree.
                     */
                    'reference_comparison' =>
                        $aReferenceSummary,

                    'comparison_safety' =>
                        isset(
                            $aReferenceComparison[
                                'safety'
                            ]
                        )
                        && is_array(
                            $aReferenceComparison[
                                'safety'
                            ]
                        )
                            ? $aReferenceComparison[
                                'safety'
                            ]
                            : array(),
                );
            }
        }


        /*
         * Scan only a small recent tail of UNA/PHP logs for
         * known high-value repair signatures.
         */
        $aFiles = array();

        $sLogs = BX_DIRECTORY_PATH_ROOT . 'logs/';

        if (is_dir($sLogs)) {
            foreach (glob($sLogs . '*') as $sFile) {
                if (is_file($sFile))
                    $aFiles[] = $sFile;
            }
        }

        $sRootError = BX_DIRECTORY_PATH_ROOT . 'error_log';

        if (is_file($sRootError))
            $aFiles[] = $sRootError;

        usort($aFiles, function($a, $b) {
            return @filemtime($b) <=> @filemtime($a);
        });

        $aFiles = array_slice($aFiles, 0, 8);

        $aSeen = array();

        foreach ($aFiles as $sLog) {
            $sText = $this->tailFile($sLog, 262144);

            if ($sText === '')
                continue;

            $aLines = preg_split('/\R/', $sText);

            foreach ($aLines as $sLine) {
                /*
                 * PHP inheritance signature fatal.
                 */
                if (preg_match(
                    '~Declaration of\s+([A-Za-z0-9_\\\\]+)::([A-Za-z0-9_]+)\((.*?)\)\s+must be compatible with\s+([A-Za-z0-9_\\\\]+)::([A-Za-z0-9_]+)\((.*?)\)\s+in\s+(.+?)\s+on line\s+(\d+)~i',
                    $sLine,
                    $m
                )) {
                    $sKey = 'signature|' . $m[1] . '|'
                        . $m[2] . '|' . $m[7];

                    if (isset($aSeen[$sKey]))
                        continue;

                    $aSeen[$sKey] = true;

                    $aCandidate = array(
                        'rule' => 'php_method_signature_compatibility',
                        'confidence' => 'high',
                        'class' => $m[1],
                        'method' => $m[2],
                        'child_signature' => trim($m[3]),
                        'parent_class' => $m[4],
                        'parent_method' => $m[5],
                        'parent_signature' => trim($m[6]),
                        'file' => $m[7],
                        'line' => (int)$m[8],
                        'validation' =>
                            'Inspect method body, patch declaration only when legacy parameters are not semantically required, then lint and class-load validate.',
                    );

                    /*
                     * If current source no longer contains the
                     * logged legacy signature, retain as history
                     * rather than an active repair.
                     */
                    if (is_file($m[7])) {
                        $sSource = (string)@file_get_contents($m[7]);

                        if (
                            $sSource !== '' &&
                            strpos(
                                $sSource,
                                $m[2] . '(' . $m[3] . ')'
                            ) === false
                        ) {
                            $aCandidate['status'] =
                                'historical_or_resolved';

                            $aHistorical[] = $aCandidate;
                            continue;
                        }
                    }

                    $aCandidate['status'] = 'review';
                    $aActive[] = $aCandidate;
                    continue;
                }

                /*
                 * PHP 8.2/8.3 dynamic property deprecation.
                 */
                if (preg_match(
                    '~Creation of dynamic property\s+([A-Za-z0-9_\\\\]+)::\$([A-Za-z0-9_]+)\s+is deprecated in\s+(.+?)\s+on line\s+(\d+)~i',
                    $sLine,
                    $m
                )) {
                    $sKey = 'dynamic|' . $m[1] . '|'
                        . $m[2] . '|' . $m[3];

                    if (isset($aSeen[$sKey]))
                        continue;

                    $aSeen[$sKey] = true;

                    $aCandidate = array(
                        'rule' => 'php_dynamic_property',
                        'confidence' => 'high',
                        'class' => $m[1],
                        'property' => $m[2],
                        'file' => $m[3],
                        'line' => (int)$m[4],
                        'validation' =>
                            'Confirm the property is not declared in the class hierarchy before proposing an explicit declaration.',
                    );

                    if (is_file($m[3])) {
                        $sSource = (string)@file_get_contents($m[3]);

                        if (
                            preg_match(
                                '/(?:public|protected|private)\s+\$'
                                    . preg_quote($m[2], '/')
                                    . '\b/',
                                $sSource
                            )
                        ) {
                            $aCandidate['status'] =
                                'historical_or_resolved';

                            $aHistorical[] = $aCandidate;
                            continue;
                        }
                    }

                    $aCandidate['status'] = 'review';
                    $aActive[] = $aCandidate;
                    continue;
                }

                /*
                 * Database unique-key collision.
                 */
                if (preg_match(
                    "~Duplicate entry '([^']+)' for key '([^']+)'~i",
                    $sLine,
                    $m
                )) {
                    $sKey = 'duplicate|' . $m[1] . '|'
                        . $m[2];

                    if (isset($aSeen[$sKey]))
                        continue;

                    $aSeen[$sKey] = true;

                    $aCandidate = array(
                        'rule' => 'database_unique_key_collision',
                        'confidence' => 'medium',
                        'value' => $m[1],
                        'key' => $m[2],
                        'status' => 'historical_unverified',
                        'validation' =>
                            'Establish ownership before deleting or renaming any existing UNA object.',
                    );

                    if (
                        strtolower($m[2]) === 'uri' &&
                        $this->tableExists('sys_objects_page')
                    ) {
                        $aOwner = $this->oDb->getRow(
                            "SELECT `id`, `object`, `module`, `uri`, `url`
                             FROM `sys_objects_page`
                             WHERE `uri` = ?
                             LIMIT 1",
                            array($m[1])
                        );

                        if (!empty($aOwner)) {
                            $aCandidate['confidence'] = 'high';
                            $aCandidate['existing_owner'] = $aOwner;
                        }
                    }

                    $aHistorical[] = $aCandidate;
                }
            }
        }

        if (empty($aActive)) {
            $bHistorical = !empty($aHistorical);

            $aEvidence = $this->humanEvidence(
                $bHistorical
                    ? 'Doctor found previous repair-related activity, but no new repair problem is currently confirmed.'
                    : 'Doctor found no new repair problem that currently needs attention.',
                array(),
                $bHistorical
                    ? 'No new repair is being recommended from this scan.'
                    : 'No repair action is needed from this scan.',
                'If a repair is already in progress, continue it from the active repair session. Otherwise, no action is needed.'
            );

            $aEvidence['repair_backup_root'] =
                $this->getRepairBackupRoot();

            $aEvidence['historical_or_unverified_patterns'] =
                array_slice($aHistorical, 0, 50);

            $this->add(
                'repairs',
                $bHistorical ? 'info' : 'ok',
                $bHistorical
                    ? 'Previous repair evidence found'
                    : 'No new repair needed',
                '',
                $aEvidence
            );

            return;
        }

        $iActiveRepairs = count($aActive);

        $aEvidence = $this->humanEvidence(
            'Doctor found '
                . $iActiveRepairs
                . (
                    $iActiveRepairs === 1
                        ? ' recommended repair ready for review.'
                        : ' recommended repairs ready for review.'
                ),
            array(),
            'Reviewing this recommendation does not make any changes to your site.',
            'Review the recommendation and approve it when you are ready. Doctor will protect the site and verify each step before making changes.'
        );

        $aEvidence['repair_backup_root'] =
            $this->getRepairBackupRoot();

        $aEvidence['active_candidates'] =
            array_slice($aActive, 0, 50);

        $aEvidence['historical_or_unverified_patterns'] =
            array_slice($aHistorical, 0, 50);

        $this->add(
            'repairs',
            'info',
            'Recommended repair ready for review',
            '',
            $aEvidence
        );
    }

    protected function scanNotifications()
    {
        if (!$this->tableExists('bx_notifications_handlers')) {
            $this->add('notifications', 'info', 'Notifications handler table is not installed', 'The Notifications module may not be installed on this UNA site.');
            return;
        }

        $aFields = $this->tableFields('bx_notifications_handlers');
        $aNeeded = array('group', 'type', 'alert_unit', 'alert_action');
        if (count(array_intersect($aNeeded, $aFields)) === count($aNeeded)) {
            $aDup = $this->oDb->getAll(
                'SELECT `group`, `type`, `alert_unit`, `alert_action`, COUNT(*) AS `cnt` FROM `bx_notifications_handlers` GROUP BY `group`, `type`, `alert_unit`, `alert_action` HAVING COUNT(*) > 1 LIMIT 25'
            );
            if (!empty($aDup))
                $this->add('notifications', 'warning', 'Duplicate notification handlers were found',
                    count($aDup) . ' duplicate handler signature(s) were detected.', array('duplicates' => $aDup));
            else
                $this->add('notifications', 'ok', 'No duplicate notification handler signatures were found');
        }

        if ($this->tableExists('bx_notifications_settings') && in_array('handler_id', $this->tableFields('bx_notifications_settings'), true)) {
            $iOrphans = (int)$this->oDb->getOne(
                'SELECT COUNT(*) FROM `bx_notifications_settings` s LEFT JOIN `bx_notifications_handlers` h ON h.`id` = s.`handler_id` WHERE h.`id` IS NULL'
            );
            if ($iOrphans > 0)
                $this->add('notifications', 'warning', 'Orphan notification settings were found', $iOrphans . ' setting record(s) reference missing handlers.');
            else
                $this->add('notifications', 'ok', 'No orphan notification settings were found');
        }
    }

    protected function scanCron()
    {
        if (!$this->tableExists('sys_cron_jobs')) {
            $this->add('cron', 'warning', 'sys_cron_jobs table is missing');
            return;
        }

        $aJobs = $this->oDb->getAll('SELECT * FROM `sys_cron_jobs` ORDER BY `id`');
        $aFields = $this->tableFields('sys_cron_jobs');
        $iBroken = 0;
        $iDisabledBroken = 0;

        if (in_array('file', $aFields, true)) {
            foreach ($aJobs as $aJob) {
                $sFile = trim(isset($aJob['file']) ? (string)$aJob['file'] : '');
                if ($sFile === '')
                    continue;

                $sPath = $sFile;
                if (!preg_match('~^(?:/|[A-Za-z]:[\\/])~', $sPath))
                    $sPath = rtrim(BX_DIRECTORY_PATH_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($sPath, '/\\');

                if (is_file($sPath))
                    continue;

                $bEnabled = true;
                if (array_key_exists('active', $aJob))
                    $bEnabled = (bool)$aJob['active'];
                elseif (array_key_exists('enabled', $aJob))
                    $bEnabled = (bool)$aJob['enabled'];

                $aEvidence = array(
                    'job_id' => isset($aJob['id']) ? (int)$aJob['id'] : 0,
                    'job_name' => isset($aJob['name']) ? (string)$aJob['name'] : '',
                    'registered_file' => $sFile,
                    'resolved_path' => $sPath,
                    'enabled' => $bEnabled,
                );

                foreach (array('time', 'service_call', 'service', 'class', 'method') as $sField) {
                    if (array_key_exists($sField, $aJob) && $aJob[$sField] !== '' && $aJob[$sField] !== null)
                        $aEvidence[$sField] = $aJob[$sField];
                }

                if ($bEnabled) {
                    $iBroken++;
                    $this->add('cron', 'warning', 'Enabled cron job references a missing PHP file',
                        isset($aJob['name']) && $aJob['name'] !== '' ? (string)$aJob['name'] : ('Cron job #' . (int)$aEvidence['job_id']),
                        $aEvidence);
                } else {
                    $iDisabledBroken++;
                    $this->add('cron', 'info', 'Disabled cron job retains a missing PHP file reference',
                        isset($aJob['name']) && $aJob['name'] !== '' ? (string)$aJob['name'] : ('Cron job #' . (int)$aEvidence['job_id']),
                        $aEvidence);
                }
            }
        }

        if ($iBroken === 0 && $iDisabledBroken === 0)
            $this->add('cron', 'ok', 'Registered cron job file references look valid', '', array('job_count' => count($aJobs)));

        $sCron = rtrim(BX_DIRECTORY_PATH_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'periodic' . DIRECTORY_SEPARATOR . 'cron.php';
        if (!is_file($sCron))
            $this->add('cron', 'critical', 'UNA periodic/cron.php is missing', '', array('path' => $sCron));
        else
            $this->add('cron', 'ok', 'UNA periodic cron entry point exists', '', array('path' => $sCron));
    }

    protected function scanLogs()
    {
        $sLogDir = rtrim(BX_DIRECTORY_PATH_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($sLogDir)) {
            $this->add('logs', 'warning', 'UNA logs directory is missing');
            return;
        }

        $aFiles = glob($sLogDir . DIRECTORY_SEPARATOR . '*');
        if ($aFiles === false)
            $aFiles = array();

        $aFiles = array_values(array_filter($aFiles, function ($sFile) {
            if (!is_file($sFile))
                return false;
            $sName = basename($sFile);
            if ($sName === '' || $sName[0] === '.')
                return false;
            return !preg_match('/(?:\.gz$|\.zip$|\.bak(?:[._-].*)?$|\.old(?:[._-].*)?$|\.orig(?:[._-].*)?$|~$)/i', $sName);
        }));

        usort($aFiles, function ($a, $b) {
            return @filemtime($b) <=> @filemtime($a);
        });
        $aFiles = array_slice($aFiles, 0, 15);

        $iNow = time();
        $iCutoff24h = $iNow - 86400;
        $iCutoff7d = $iNow - (7 * 86400);

        $iActiveFatal = 0;
        $iActiveError = 0;
        $iActiveInfo = 0;
        $iActiveStaleMedia = 0;
        $iActiveAdmin = 0;
        $iRecentFatal = 0;
        $iRecentError = 0;
        $iRecentInfo = 0;
        $iRecentStaleMedia = 0;
        $iRecentAdmin = 0;
        $iHistorical = 0;
        $iUndated = 0;
        $iFilesScanned = 0;

        $aEvidence = array();
        $aActiveSignatures = array();
        $aActiveInfoSignatures = array();
        $aRecentSignatures = array();
        $aRecentInfoSignatures = array();

        foreach ($aFiles as $sFile) {
            $sTail = $this->tailFile($sFile, 262144);
            if ($sTail === '')
                continue;

            $iFilesScanned++;
            $aEntries = $this->parseLogEntries($sTail, $iNow);
            if (empty($aEntries))
                continue;

            $aFile = array(
                'file' => basename($sFile),
                'modified' => date('Y-m-d H:i:s', (int)@filemtime($sFile)),
                'active_24h' => 0,
                'health_impacting_24h' => 0,
                'informational_24h' => 0,
                'recent_1_to_7_days' => 0,
                'historical' => 0,
                'undated' => 0,
                'latest_actionable' => '',
                'samples' => array(),
                '_active_samples' => array(),
                '_recent_samples' => array(),
            );

            foreach ($aEntries as $aEntry) {
                $sText = isset($aEntry['text']) ? (string)$aEntry['text'] : '';
                if ($sText === '')
                    continue;

                $bFatal = (bool)preg_match('/(?:PHP\s+Fatal\s+error|Fatal\s+error|PHP\s+Parse\s+error|Uncaught\s+(?:Error|Exception|TypeError|ValueError|RuntimeException|PDOException))/i', $sText);
                $bError = !$bFatal && (bool)preg_match('/(?:\[(?:ERROR|CRITICAL)\]|PHP\s+Warning\b|SQLSTATE\[|DB\s+query\s+error|Database\s+error)/i', $sText);

                if (!$bFatal && !$bError)
                    continue;

                $iTs = isset($aEntry['timestamp']) ? (int)$aEntry['timestamp'] : 0;
                $sSafe = $this->sanitizeLogEntry($sText);
                $sSignature = $this->normalizeLogSignature($sSafe);
                $aClass = $this->classifyLogEntry($sText, $bFatal, $bError);
                $bHealthImpact = !empty($aClass['health_impact']);
                $sClassification = isset($aClass['classification']) ? (string)$aClass['classification'] : 'active_error';

                if ($iTs <= 0) {
                    $iUndated++;
                    $aFile['undated']++;
                    continue;
                }

                if ($aFile['latest_actionable'] === '' || $iTs > strtotime($aFile['latest_actionable']))
                    $aFile['latest_actionable'] = date('Y-m-d H:i:s', $iTs);

                $aSample = array(
                    'classification' => $sClassification,
                    'health_impact' => $bHealthImpact,
                    'entry' => $sSafe,
                );
                if (!empty($aClass['evidence']))
                    $aSample['verification'] = $aClass['evidence'];

                if ($iTs >= $iCutoff24h) {
                    $aFile['active_24h']++;
                    $aSample['age'] = 'last_24_hours';

                    if ($bHealthImpact) {
                        $aFile['health_impacting_24h']++;
                        if ($bFatal)
                            $iActiveFatal++;
                        else
                            $iActiveError++;
                        if ($sSignature !== '')
                            $aActiveSignatures[$sSignature] = isset($aActiveSignatures[$sSignature]) ? $aActiveSignatures[$sSignature] + 1 : 1;
                    } else {
                        $aFile['informational_24h']++;
                        $iActiveInfo++;
                        if ($sClassification === 'stale_media_request')
                            $iActiveStaleMedia++;
                        elseif ($sClassification === 'administrative_operation')
                            $iActiveAdmin++;
                        if ($sSignature !== '')
                            $aActiveInfoSignatures[$sSignature] = isset($aActiveInfoSignatures[$sSignature]) ? $aActiveInfoSignatures[$sSignature] + 1 : 1;
                    }

                    if (count($aFile['_active_samples']) < 6)
                        $aFile['_active_samples'][] = $aSample;
                } elseif ($iTs >= $iCutoff7d) {
                    $aFile['recent_1_to_7_days']++;
                    $aSample['age'] = '1_to_7_days';

                    if ($bHealthImpact) {
                        if ($bFatal)
                            $iRecentFatal++;
                        else
                            $iRecentError++;
                        if ($sSignature !== '')
                            $aRecentSignatures[$sSignature] = isset($aRecentSignatures[$sSignature]) ? $aRecentSignatures[$sSignature] + 1 : 1;
                    } else {
                        $iRecentInfo++;
                        if ($sClassification === 'stale_media_request')
                            $iRecentStaleMedia++;
                        elseif ($sClassification === 'administrative_operation')
                            $iRecentAdmin++;
                        if ($sSignature !== '')
                            $aRecentInfoSignatures[$sSignature] = isset($aRecentInfoSignatures[$sSignature]) ? $aRecentInfoSignatures[$sSignature] + 1 : 1;
                    }

                    if (count($aFile['_recent_samples']) < 4)
                        $aFile['_recent_samples'][] = $aSample;
                } else {
                    $aFile['historical']++;
                    $iHistorical++;
                }
            }

            $aFile['samples'] = array_slice(array_merge($aFile['_active_samples'], $aFile['_recent_samples']), 0, 6);
            unset($aFile['_active_samples'], $aFile['_recent_samples']);

            if ($aFile['active_24h'] > 0 || $aFile['recent_1_to_7_days'] > 0 || $aFile['historical'] > 0 || $aFile['undated'] > 0)
                $aEvidence[] = $aFile;
        }

        arsort($aActiveSignatures);
        arsort($aActiveInfoSignatures);
        arsort($aRecentSignatures);
        arsort($aRecentInfoSignatures);

        $aTopActive = $this->signatureCountsToRows($aActiveSignatures);
        $aTopActiveInfo = $this->signatureCountsToRows($aActiveInfoSignatures);
        $aTopRecent = $this->signatureCountsToRows($aRecentSignatures);
        $aTopRecentInfo = $this->signatureCountsToRows($aRecentInfoSignatures);

        $aCommonEvidence = array(
            'files_scanned' => $iFilesScanned,
            'files_with_actionable_history' => count($aEvidence),
            'last_24_hours' => array(
                'fatal' => $iActiveFatal,
                'error_warning' => $iActiveError,
                'informational' => $iActiveInfo,
                'stale_media_requests' => $iActiveStaleMedia,
                'administrative_operations' => $iActiveAdmin,
                'top_health_impacting_signatures' => $aTopActive,
                'top_informational_signatures' => $aTopActiveInfo,
            ),
            'one_to_seven_days' => array(
                'fatal' => $iRecentFatal,
                'error_warning' => $iRecentError,
                'informational' => $iRecentInfo,
                'stale_media_requests' => $iRecentStaleMedia,
                'administrative_operations' => $iRecentAdmin,
                'top_health_impacting_signatures' => $aTopRecent,
                'top_informational_signatures' => $aTopRecentInfo,
            ),
            'historical_entries_in_scanned_tails' => $iHistorical,
            'undated_actionable_entries' => $iUndated,
            'files' => $aEvidence,
            'scope' => 'Tail 256 KiB of up to 15 non-backup log files. Health severity is based on each log entry timestamp and classification. Verified stale Albums/Timeline transcoder requests and Studio module-operation failures remain visible as information but do not reduce the health score.',
        );

        $aHumanSections = array();
        $aStaleLines = array();
        $aAdminLines = array();
        $aHealthLines = array();

        foreach ($aEvidence as $aFileEvidence) {
            if (empty($aFileEvidence['samples']) || !is_array($aFileEvidence['samples']))
                continue;

            foreach ($aFileEvidence['samples'] as $aSample) {
                if (!isset($aSample['age']) || $aSample['age'] !== 'last_24_hours')
                    continue;

                $sClass = isset($aSample['classification']) ? (string)$aSample['classification'] : '';
                if ($sClass === 'stale_media_request' && !empty($aSample['verification'])) {
                    $aVerify = $aSample['verification'];
                    $sStorage = isset($aVerify['source_storage']) ? (string)$aVerify['source_storage'] : 'media storage';
                    $iId = isset($aVerify['file_id']) ? (int)$aVerify['file_id'] : 0;
                    $iRefs = isset($aVerify['live_reference_count']) ? (int)$aVerify['live_reference_count'] : 0;
                    $iCache = isset($aVerify['cache_record_count']) ? (int)$aVerify['cache_record_count'] : 0;
                    $aStaleLines[] = $sStorage . ' file ID ' . $iId . ' — source absent, ' . $iRefs . ' live reference(s)'
                        . ($iCache > 0 ? ', ' . $iCache . ' cache record(s)' : '') . '; no content repair required.';
                } elseif ($sClass === 'administrative_operation') {
                    $aAdminLines[] = $this->describeAdministrativeLogEntry(isset($aSample['entry']) ? $aSample['entry'] : '');
                } elseif (!empty($aSample['health_impact'])) {
                    $aHealthLines[] = $this->shortLogDescription(isset($aSample['entry']) ? $aSample['entry'] : '');
                }
            }
        }

        $aStaleLines = array_values(array_unique(array_filter($aStaleLines)));
        $aAdminLines = array_values(array_unique(array_filter($aAdminLines)));
        $aHealthLines = array_values(array_unique(array_filter($aHealthLines)));

        if (!empty($aHealthLines))
            $aHumanSections[] = array('title' => 'Current health-impacting log activity', 'items' => array_slice($aHealthLines, 0, 10));
        if (!empty($aStaleLines))
            $aHumanSections[] = array('title' => 'Verified stale media requests', 'items' => array_slice($aStaleLines, 0, 10));
        if (!empty($aAdminLines))
            $aHumanSections[] = array('title' => 'Studio administrative history', 'items' => array_slice($aAdminLines, 0, 10));

        $aCommonEvidence['human'] = array(
            'summary' => ($iActiveFatal + $iActiveError) > 0
                ? 'Doctor found recent health-impacting log activity. Informational stale-media and Studio-operation entries are separated below.'
                : 'Doctor reviewed recent error-level log activity and found no current health-impacting entries.',
            'sections' => $aHumanSections,
            'health_impact' => ($iActiveFatal + $iActiveError) > 0 ? 'Yes — review the current health-impacting entries' : 'None from the classified entries shown',
            'repair_required' => ($iActiveFatal + $iActiveError) > 0 ? 'Diagnosis required before any repair' : 'No',
        );

        if ($iActiveFatal > 0) {
            $this->add('logs', 'critical', 'UNA logs contain health-impacting fatal or uncaught errors from the last 24 hours',
                $iActiveFatal . ' fatal/uncaught entry or entries and ' . $iActiveError . ' other health-impacting error/warning entry or entries occurred during the last 24 hours.', $aCommonEvidence);
        } elseif ($iActiveError > 0) {
            $this->add('logs', 'warning', 'UNA logs contain health-impacting errors or PHP warnings from the last 24 hours',
                $iActiveError . ' health-impacting non-fatal entry or entries occurred during the last 24 hours. Verified stale media requests and administrative module-operation history are shown separately and are not scored.', $aCommonEvidence);
        } elseif ($iActiveInfo > 0) {
            $this->add('logs', 'info', 'Recent log activity was reviewed with no current health-impacting errors found',
                $iActiveInfo . ' recent log entry or entries were classified as informational. ' . $iActiveStaleMedia . ' verified stale media request(s) and ' . $iActiveAdmin . ' Studio module-operation event(s) do not reduce the health score.', $aCommonEvidence);
        } elseif (($iRecentFatal + $iRecentError + $iRecentInfo) > 0) {
            $this->add('logs', 'info', 'UNA logs contain recent history but no health-impacting entries from the last 24 hours',
                ($iRecentFatal + $iRecentError + $iRecentInfo) . ' classified entry or entries occurred 1-7 days ago. They are retained for review but are not scored as a current warning.', $aCommonEvidence);
        } elseif ($iHistorical > 0 || $iUndated > 0) {
            $this->add('logs', 'info', 'Only historical or undated actionable log entries were found',
                'No timestamped health-impacting fatal/error/warning entries were found in the last 7 days. Historical entries remain available as diagnostic context.', $aCommonEvidence);
        } else {
            $this->add('logs', 'ok', 'No obvious fatal/error signatures were found in the scanned log tails',
                'My UNA Doctor found no actionable timestamped error signatures in the scanned log data.',
                array('files_scanned' => $iFilesScanned, 'scope' => 'Tail 256 KiB of up to 15 non-backup log files.'));
        }
    }

    protected function signatureCountsToRows(array $aCounts)
    {
        $aRows = array();
        foreach (array_slice($aCounts, 0, 10, true) as $sSignature => $iCount)
            $aRows[] = array('count' => (int)$iCount, 'signature' => $sSignature);
        return $aRows;
    }

    protected function inspectApprovedCompatibilityOverride(
        $sModule,
        $sFile,
        $sExpectedMd5,
        $sCurrentMd5
    ) {
        /*
         * AQB Points v3 compatibility override.
         *
         * Never approve by filename alone.
         */
        if (
            $sModule !== 'aqb_points'
            || $sFile !== 'classes/AqbPointsConfig.php'
            || strtolower((string)$sExpectedMd5) !==
                '5dad898d9adcc4876cc41c7e387dceb1'
            || strtolower((string)$sCurrentMd5) !==
                '3c3970a78a094016c7e031762ac2ed9c'
        ) {
            return array();
        }

        if (!$this->tableExists('sys_objects_page'))
            return array();

        $oDb = BxDolDb::getInstance();

        $iReputationOwner = (int)$oDb->getOne(
            "SELECT COUNT(*)
             FROM `sys_objects_page`
             WHERE `object`='bx_reputation_info'
               AND `module`='bx_reputation'
               AND `uri`='points-info'
               AND `url`='page.php?i=points-info'"
        );

        $iPointsOwner = (int)$oDb->getOne(
            "SELECT COUNT(*)
             FROM `sys_objects_page`
             WHERE `object`='aqb_points_info'
               AND `module`='aqb_points'
               AND `uri`='aqb-points-info'
               AND `url`='page.php?i=aqb-points-info'"
        );

        $iUnexpectedOwner = (int)$oDb->getOne(
            "SELECT COUNT(*)
             FROM `sys_objects_page`
             WHERE `uri`='points-info'
               AND NOT (
                   `object`='bx_reputation_info'
                   AND `module`='bx_reputation'
               )"
        );

        if (
            $iReputationOwner !== 1
            || $iPointsOwner !== 1
            || $iUnexpectedOwner !== 0
        ) {
            return array();
        }

        return array(
            'verified' => true,
            'classification' =>
                'approved_compatibility_override',
            'reason' =>
                'AQB Points v3 uses aqb-points-info because UNA Reputation owns points-info.',
            'vendor_md5' =>
                strtolower((string)$sExpectedMd5),
            'approved_md5' =>
                strtolower((string)$sCurrentMd5),
            'reputation_uri' => 'points-info',
            'points_uri' => 'aqb-points-info',
        );
    }

    protected function inspectResolvedHistoricalLogError($sText)
    {
        /*
         * Preserve the old AQB collation incident in history,
         * but stop scoring it when current database evidence
         * proves the condition was repaired.
         */
        if (
            stripos($sText, 'Illegal mix of collations') === false
            || stripos($sText, 'aqb-points-info') === false
            || stripos($sText, 'aqb_points_actions') === false
        ) {
            return array();
        }

        if (
            !$this->tableExists('aqb_points_actions')
            || !$this->tableExists('sys_alerts')
            || !$this->tableExists('sys_objects_page')
        ) {
            return array();
        }

        $oDb = BxDolDb::getInstance();

        $sPointsCollation = (string)$oDb->getOne(
            "SELECT `TABLE_COLLATION`
             FROM `INFORMATION_SCHEMA`.`TABLES`
             WHERE `TABLE_SCHEMA`=DATABASE()
               AND `TABLE_NAME`='aqb_points_actions'"
        );

        $sAlertsCollation = (string)$oDb->getOne(
            "SELECT `TABLE_COLLATION`
             FROM `INFORMATION_SCHEMA`.`TABLES`
             WHERE `TABLE_SCHEMA`=DATABASE()
               AND `TABLE_NAME`='sys_alerts'"
        );

        $iBadColumns = (int)$oDb->getOne(
            "SELECT COUNT(*)
             FROM `INFORMATION_SCHEMA`.`COLUMNS`
             WHERE `TABLE_SCHEMA`=DATABASE()
               AND `TABLE_NAME`='aqb_points_actions'
               AND `CHARACTER_SET_NAME` IS NOT NULL
               AND `COLLATION_NAME` <>
                   'utf8mb4_unicode_ci'"
        );

        $iInfoPage = (int)$oDb->getOne(
            "SELECT COUNT(*)
             FROM `sys_objects_page`
             WHERE `object`='aqb_points_info'
               AND `module`='aqb_points'
               AND `uri`='aqb-points-info'"
        );

        if (
            $sPointsCollation !== 'utf8mb4_unicode_ci'
            || $sAlertsCollation !== 'utf8mb4_unicode_ci'
            || $iBadColumns !== 0
            || $iInfoPage !== 1
        ) {
            return array();
        }

        return array(
            'verified' => true,
            'reason' =>
                'The historical AQB Points collation error is resolved in the current database state.',
            'aqb_points_actions_collation' =>
                $sPointsCollation,
            'sys_alerts_collation' =>
                $sAlertsCollation,
            'nonconforming_points_columns' =>
                $iBadColumns,
            'page_uri' => 'aqb-points-info',
        );
    }

    protected function classifyLogEntry($sText, $bFatal, $bError)
    {
        $aResult = array(
            'health_impact' => true,
            'classification' => $bFatal ? 'fatal_error' : 'active_error',
            'evidence' => array(),
        );

        if ($bFatal)
            return $aResult;

        if (!$bError) {
            $aResult['health_impact'] = false;
            $aResult['classification'] = 'informational';
            return $aResult;
        }

        $aMedia = $this->inspectMissingTranscoderSource($sText);
        if (!empty($aMedia)) {
            $aResult['evidence'] = $aMedia;

            if (!empty($aMedia['verified']) && empty($aMedia['source_exists']) && empty($aMedia['live_reference_count'])) {
                $aResult['health_impact'] = false;
                $aResult['classification'] = 'stale_media_request';
                return $aResult;
            }

            if (!empty($aMedia['verified']) && empty($aMedia['source_exists']) && !empty($aMedia['live_reference_count'])) {
                $aResult['health_impact'] = true;
                $aResult['classification'] = 'media_integrity_reference';
                return $aResult;
            }

            $aResult['classification'] = 'transcoder_error';
            return $aResult;
        }

        $aResolved =
            $this->inspectResolvedHistoricalLogError($sText);

        if (!empty($aResolved['verified'])) {
            $aResult['health_impact'] = false;
            $aResult['classification'] =
                'resolved_historical_error';
            $aResult['evidence'] = $aResolved;

            return $aResult;
        }

        if ($this->isStudioAdministrativeFailure($sText)) {
            $aResult['health_impact'] = false;
            $aResult['classification'] = 'administrative_operation';
            $aResult['evidence'] = array(
                'reason' => 'The entry records an explicit Studio install/update/enable/disable/uninstall action failure. Doctor keeps it visible for review, while current structural checks determine whether the site remains unhealthy.',
            );
            return $aResult;
        }

        return $aResult;
    }

    protected function describeAdministrativeLogEntry($sText)
    {
        $sText = (string)$sText;
        $sOperation = 'module operation';
        if (preg_match('/\[(install|uninstall|update|enable|disable)\]\s+Operation failed/i', $sText, $m))
            $sOperation = strtolower((string)$m[1]);

        $sModule = '';
        if (preg_match('/mod_value=([A-Za-z0-9_\-]+)/i', $sText, $m))
            $sModule = (string)$m[1];
        elseif (preg_match('/Operation failed:\s*([^:\r\n]+)\s*:/i', $sText, $m))
            $sModule = trim((string)$m[1]);

        $sWhen = '';
        if (preg_match('/^([A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2})/m', $sText, $m))
            $sWhen = (string)$m[1] . ' — ';

        return $sWhen . ($sModule !== '' ? $sModule . ' — ' : '') . ucfirst($sOperation) . ' failure recorded by Studio; retained as administrative history.';
    }

    protected function shortLogDescription($sText)
    {
        $s = preg_replace('/\s+/', ' ', trim((string)$sText));
        if ($s === '')
            return 'Health-impacting error entry.';
        if (strlen($s) > 220)
            $s = substr($s, 0, 217) . '...';
        return $s;
    }

    protected function isStudioAdministrativeFailure($sText)
    {
        $sText = (string)$sText;
        if (!preg_match('~/studio/(?:module|store)\.php\b~i', $sText))
            return false;

        return (bool)preg_match('/\[(?:install|uninstall|update|enable|disable)\]\s+Operation failed\s*:/i', $sText);
    }

    protected function inspectMissingTranscoderSource($sText)
    {
        $sText = (string)$sText;
        if (!preg_match('/storeFileLocally_Storage failed,\s*file\((\d+)\)\s+wasn\'t found in source storage\s*\(([A-Za-z0-9_]+)\)/i', $sText, $m))
            return array();

        $iFileId = (int)$m[1];
        $sStorage = (string)$m[2];

        $aResult = array(
            'verified' => false,
            'file_id' => $iFileId,
            'source_storage' => $sStorage,
            'source_exists' => null,
            'live_reference_count' => null,
            'cache_record_count' => null,
            'references' => array(),
            'cache_records' => array(),
        );

        if ($iFileId <= 0)
            return $aResult;

        $aSpecs = array(
            'bx_albums_files' => array(
                'source' => 'bx_albums_files',
                'references' => array(
                    array('table' => 'bx_albums_files2albums', 'field' => 'file_id', 'label' => 'media_links'),
                    array('table' => 'bx_albums_albums', 'field' => 'thumb', 'label' => 'album_thumbnails'),
                ),
                'cache' => array(
                    array('table' => 'bx_albums_photos_resized', 'field' => 'remote_id', 'label' => 'resized_records'),
                ),
            ),
            'bx_timeline_photos' => array(
                'source' => 'bx_timeline_photos',
                'references' => array(
                    array('table' => 'bx_timeline_photos2events', 'field' => 'media_id', 'label' => 'timeline_event_links'),
                ),
                'cache' => array(
                    array('table' => 'bx_timeline_photos_processed', 'field' => 'remote_id', 'label' => 'processed_records'),
                ),
            ),
            'bx_timeline_videos' => array(
                'source' => 'bx_timeline_videos',
                'references' => array(
                    array('table' => 'bx_timeline_videos2events', 'field' => 'media_id', 'label' => 'timeline_event_links'),
                ),
                'cache' => array(
                    array('table' => 'bx_timeline_videos_processed', 'field' => 'remote_id', 'label' => 'processed_records'),
                ),
            ),
            'bx_timeline_files' => array(
                'source' => 'bx_timeline_files',
                'references' => array(
                    array('table' => 'bx_timeline_files2events', 'field' => 'media_id', 'label' => 'timeline_event_links'),
                ),
                'cache' => array(),
            ),
        );

        if (!isset($aSpecs[$sStorage]))
            return $aResult;

        $aSpec = $aSpecs[$sStorage];
        if (!$this->tableExists($aSpec['source']) || !in_array('id', $this->tableFields($aSpec['source']), true))
            return $aResult;

        $iSource = (int)$this->oDb->getOne(
            'SELECT COUNT(*) FROM `' . $aSpec['source'] . '` WHERE `id` = ?',
            array($iFileId)
        );

        $aRefs = array();
        foreach ($aSpec['references'] as $aRef) {
            if (!$this->tableExists($aRef['table']))
                continue;
            $aFields = $this->tableFields($aRef['table']);
            if (!in_array($aRef['field'], $aFields, true))
                continue;
            $aRefs[$aRef['label']] = (int)$this->oDb->getOne(
                'SELECT COUNT(*) FROM `' . $aRef['table'] . '` WHERE `' . $aRef['field'] . '` = ?',
                array($iFileId)
            );
        }

        $aCache = array();
        foreach ($aSpec['cache'] as $aRef) {
            if (!$this->tableExists($aRef['table']))
                continue;
            $aFields = $this->tableFields($aRef['table']);
            if (!in_array($aRef['field'], $aFields, true))
                continue;
            $aCache[$aRef['label']] = (int)$this->oDb->getOne(
                'SELECT COUNT(*) FROM `' . $aRef['table'] . '` WHERE `' . $aRef['field'] . '` = ?',
                array((string)$iFileId)
            );
        }

        $iRefCount = 0;
        foreach ($aRefs as $iCount)
            $iRefCount += (int)$iCount;

        $iCacheCount = 0;
        foreach ($aCache as $iCount)
            $iCacheCount += (int)$iCount;

        $aResult['verified'] = true;
        $aResult['source_exists'] = $iSource > 0;
        $aResult['live_reference_count'] = $iRefCount;
        $aResult['cache_record_count'] = $iCacheCount;
        $aResult['references'] = $aRefs;
        $aResult['cache_records'] = $aCache;

        return $aResult;
    }

    protected function parseLogEntries($sText, $iNow)
    {
        $aLines = preg_split('/\R/u', (string)$sText);
        if (!is_array($aLines))
            $aLines = array((string)$sText);

        $aEntries = array();
        $aCurrent = null;

        foreach ($aLines as $sLine) {
            if (!is_string($sLine))
                continue;

            $iTs = $this->parseLogTimestamp($sLine, $iNow);
            if ($iTs > 0) {
                if (is_array($aCurrent))
                    $aEntries[] = $aCurrent;
                $aCurrent = array('timestamp' => $iTs, 'text' => $sLine);
                continue;
            }

            if (is_array($aCurrent)) {
                if (trim($sLine) !== '')
                    $aCurrent['text'] .= "\n" . $sLine;
            } elseif (trim($sLine) !== '') {
                // Keep an undated standalone line so non-UNA/PHP log formats are
                // visible as evidence without pretending we know their age.
                $aEntries[] = array('timestamp' => 0, 'text' => $sLine);
            }
        }

        if (is_array($aCurrent))
            $aEntries[] = $aCurrent;

        return $aEntries;
    }

    protected function parseLogTimestamp($sLine, $iNow)
    {
        $sLine = (string)$sLine;
        $iNow = (int)$iNow;

        if (preg_match('/^([A-Z][a-z]{2})\s+(\d{1,2})\s+(\d{2}:\d{2}:\d{2})\b/', $sLine, $m)) {
            $iYear = (int)date('Y', $iNow);
            $iTs = strtotime($m[1] . ' ' . $m[2] . ' ' . $iYear . ' ' . $m[3]);
            if ($iTs !== false && $iTs > ($iNow + 86400))
                $iTs = strtotime($m[1] . ' ' . $m[2] . ' ' . ($iYear - 1) . ' ' . $m[3]);
            return $iTs === false ? 0 : (int)$iTs;
        }

        if (preg_match('/^\[(\d{1,2})-([A-Z][a-z]{2})-(\d{4})\s+(\d{2}:\d{2}:\d{2})/', $sLine, $m)) {
            $iTs = strtotime($m[2] . ' ' . $m[1] . ' ' . $m[3] . ' ' . $m[4]);
            return $iTs === false ? 0 : (int)$iTs;
        }

        if (preg_match('/^\[?(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2}:\d{2})/', $sLine, $m)) {
            $iTs = strtotime($m[1] . '-' . $m[2] . '-' . $m[3] . ' ' . $m[4]);
            return $iTs === false ? 0 : (int)$iTs;
        }

        return 0;
    }

    protected function sanitizeLogEntry($sText)
    {
        $aLines = preg_split('/\R/u', (string)$sText);
        if (!is_array($aLines))
            $aLines = array((string)$sText);

        $aSafe = array();
        foreach ($aLines as $sLine) {
            if (trim((string)$sLine) === '')
                continue;
            $aSafe[] = $this->sanitizeLogLine($sLine);
            if (count($aSafe) >= 6)
                break;
        }

        $sSafe = implode("\n", $aSafe);
        if (strlen($sSafe) > 1400)
            $sSafe = substr($sSafe, 0, 1397) . '...';
        return $sSafe;
    }

    protected function sanitizeLogLine($sLine)
    {
        $sLine = trim((string)$sLine);
        $sLine = preg_replace('/\bsk-(?:proj-)?[A-Za-z0-9_-]{12,}\b/', 'sk-***REDACTED***', $sLine);
        $sLine = preg_replace('/(Authorization:\s*Bearer\s+)[^\s]+/i', '$1***REDACTED***', $sLine);
        $sLine = preg_replace('/((?:password|passwd|api[_-]?key|token)\s*[=:]\s*)[^\s,;]+/i', '$1***REDACTED***', $sLine);
        if (strlen($sLine) > 500)
            $sLine = substr($sLine, 0, 497) . '...';
        return $sLine;
    }

    protected function normalizeLogSignature($sLine)
    {
        $sLine = str_replace(array("\r", "\n", "\t"), ' ', (string)$sLine);
        $sLine = preg_replace('/\s+/', ' ', $sLine);

        // UNA's normal log prefix: "Aug 30 09:09:06 [ERROR] [3]".
        $sLine = preg_replace('/^[A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2}(?:\.\d+)?\s+/', '', $sLine);
        // Common bracketed PHP/log prefixes.
        $sLine = preg_replace('/^\[[^\]]{1,100}\]\s*/', '', $sLine);
        $sLine = preg_replace('/^\[(?:ERROR|CRITICAL|WARNING|INFO|DEBUG)\]\s+\[\d+\]\s*/i', '', $sLine);
        $sLine = preg_replace('/^\[\d+\]\s*/', '', $sLine);

        $sLine = preg_replace('~/home/[^/\s]+/public_html/~', '{site-root}/', $sLine);
        $sLine = preg_replace('/\bon line\s+\d+\b/i', 'on line #', $sLine);
        $sLine = preg_replace('/\.php:\d+\b/', '.php:#', $sLine);

        // Remove volatile request values while retaining the operation/object.
        $sLine = preg_replace('/([?&])_t=\d+&?/', '$1', $sLine);
        $sLine = preg_replace('/([?&])mod_widget_id=\d+&?/', '$1', $sLine);
        $sLine = preg_replace('/([?&])mod_confirmed=\d+&?/', '$1', $sLine);
        $sLine = preg_replace('/([?&])h=\d+/', '$1h=#', $sLine);
        $sLine = preg_replace('/([?&])t=\d+/', '$1t=#', $sLine);
        $sLine = preg_replace('/([?&])dpx=\d+/', '$1dpx=#', $sLine);
        $sLine = preg_replace('/file\(\d+\)/i', 'file(#)', $sLine);

        $sLine = trim($sLine, " \t\n\r\0\x0B?&");
        if (strlen($sLine) > 360)
            $sLine = substr($sLine, 0, 357) . '...';
        return $sLine;
    }


    protected function tableExists($sTable)
    {
        $sTable = (string)$sTable;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $sTable))
            return false;

        if (array_key_exists($sTable, $this->aTableExistsCache))
            return (bool)$this->aTableExistsCache[$sTable];

        $bExists = (int)$this->oDb->getOne(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            array($sTable)
        ) > 0;
        $this->aTableExistsCache[$sTable] = $bExists;
        return $bExists;
    }

    protected function tableFields($sTable)
    {
        $sTable = (string)$sTable;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $sTable))
            return array();

        if (isset($this->aTableFieldsCache[$sTable]))
            return $this->aTableFieldsCache[$sTable];

        if (!$this->tableExists($sTable)) {
            $this->aTableFieldsCache[$sTable] = array();
            return array();
        }

        $aRows = $this->oDb->getAll('SHOW COLUMNS FROM `' . $sTable . '`');
        $aFields = array();
        foreach ($aRows as $aRow) {
            if (isset($aRow['Field']))
                $aFields[] = (string)$aRow['Field'];
        }
        $this->aTableFieldsCache[$sTable] = $aFields;
        return $aFields;
    }

    protected function tailFile($sFile, $iBytes)
    {
        $h = @fopen($sFile, 'rb');
        if (!$h)
            return '';
        $iSize = @filesize($sFile);
        if ($iSize === false)
            $iSize = 0;
        $iOffset = max(0, $iSize - $iBytes);
        @fseek($h, $iOffset);
        $s = stream_get_contents($h);
        fclose($h);
        return is_string($s) ? $s : '';
    }
}
