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

        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $this->add('system', 'critical', 'PHP version is below the supported baseline',
                'My UNA Doctor expects PHP 8.1 or newer.',
                array('php_version' => PHP_VERSION));
        } else {
            $this->add('system', 'ok', 'PHP version meets the baseline', '', array('php_version' => PHP_VERSION));
        }

        $aRequired = array('json', 'mbstring', 'curl', 'openssl', 'zip', 'gd');
        $aMissing = array();
        foreach ($aRequired as $sExt) {
            if (!extension_loaded($sExt))
                $aMissing[] = $sExt;
        }
        if (!empty($aMissing))
            $this->add('system', 'warning', 'Common UNA PHP extensions are missing', implode(', ', $aMissing));
        else
            $this->add('system', 'ok', 'Common UNA PHP extensions are available');
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

    protected function scanModules()
    {
        if (!$this->tableExists('sys_modules')) {
            $this->add('modules', 'critical', 'sys_modules table is missing');
            return;
        }

        $aModules = $this->oDb->getAll('SELECT `name`, `title`, `version`, `path`, `class_prefix`, `enabled` FROM `sys_modules` ORDER BY `title`');
        $iMissing = 0;
        $iMissingInstall = 0;
        $sModulesRoot = rtrim(BX_DIRECTORY_PATH_MODULES, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        foreach ($aModules as $aModule) {
            $sPath = trim((string)$aModule['path']);
            if ($sPath === '')
                continue;

            $sFull = $sModulesRoot . trim($sPath, '/\\') . DIRECTORY_SEPARATOR;
            if (!is_dir($sFull)) {
                $iMissing++;
                $this->add('modules', ((int)$aModule['enabled'] === 1 ? 'critical' : 'warning'),
                    'Installed module directory is missing',
                    $aModule['title'] . ' (' . $aModule['name'] . ')',
                    array('module' => $aModule['name'], 'version' => $aModule['version'], 'path' => $sPath, 'enabled' => (int)$aModule['enabled']));
                continue;
            }

            if (!is_file($sFull . 'install/config.php')) {
                $iMissingInstall++;
                $this->add('modules', 'warning', 'Module install metadata is missing',
                    $aModule['title'] . ' does not have install/config.php at its registered module path.',
                    array('module' => $aModule['name'], 'path' => $sPath));
            }
        }

        if ($iMissing === 0)
            $this->add('modules', 'ok', 'All registered module directories were found', '', array('module_count' => count($aModules)));
        if ($iMissingInstall === 0)
            $this->add('modules', 'ok', 'Registered modules retain their install metadata');

        $aDupNames = $this->oDb->getAll('SELECT `name`, COUNT(*) AS `cnt` FROM `sys_modules` GROUP BY `name` HAVING COUNT(*) > 1');
        if (!empty($aDupNames))
            $this->add('modules', 'critical', 'Duplicate module names are registered', '', array('duplicates' => $aDupNames));
        else
            $this->add('modules', 'ok', 'Module registration names are unique');
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
        if (
            empty($aModified)
            && empty($aMissing)
            && empty($aPreviewErrors)
        ) {
            $aEvidence = $this->humanEvidence(
                'Doctor checked '
                    . $iTracked
                    . ' tracked module file(s); all baseline files retain their recorded MD5 checksums.',
                array(),
                'None',
                'No'
            );

            $aEvidence['tracked_files_checked'] = $iTracked;
            $aEvidence['modified_count'] = 0;
            $aEvidence['missing_count'] = 0;
            $aEvidence['unverified_module_count'] = 0;

            $this->add(
                'file_integrity',
                'ok',
                'Tracked module files retain their recorded checksums',
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
         * High-confidence update-preparation candidates discovered
         * during File Integrity. Still review-only in 0.2.2.
         */
        foreach (
            $this->aRuntimeContentCandidates
            as $aRuntime
        ) {
            $aActive[] = array(
                'rule' =>
                    'runtime_content_update_preparation',

                'confidence' =>
                    isset($aRuntime['confidence'])
                        ? $aRuntime['confidence']
                        : 'high',

                'status' => 'review',

                'module' =>
                    $aRuntime['module'],

                'title' =>
                    $aRuntime['title'],

                'directory' =>
                    $aRuntime['dominant_directory'],

                'runtime_file_count' =>
                    $aRuntime['new_untracked_count'],

                'source_integrity' =>
                    $aRuntime['source_integrity'],

                'generator_discovery' =>
                    $aRuntime['generator_discovery']
                    ?? array(),

                'studio_effect' =>
                    'UNA Studio update readiness is affected by runtime/generated files inside the module directory.',

                'recommended_workflow' => array(
                    'Pause the module process that generates or imports runtime content.',
                    'Create a safety snapshot before moving runtime data.',
                    'Move the identified runtime directory outside the module tree without deleting it.',
                    'Run Doctor again and verify Studio update readiness.',
                    'Perform the approved module update.',
                    'Restore the runtime directory.',
                    'Verify module operation and runtime data.',
                    'Resume the module generator/import process.'
                ),

                'automatic_repair' => false,
            );
        }


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
            $aEvidence = $this->humanEvidence(
                !empty($aHistorical)
                    ? 'Doctor found known repair signatures in recent logs, but none are currently verified as active from the evidence available.'
                    : 'Doctor found no currently verified high-confidence repair candidates.',
                array(),
                'None',
                'No'
            );

            $aEvidence['repair_backup_root'] =
                $this->getRepairBackupRoot();

            $aEvidence['historical_or_unverified_patterns'] =
                array_slice($aHistorical, 0, 50);

            $this->add(
                'repairs',
                !empty($aHistorical) ? 'info' : 'ok',
                !empty($aHistorical)
                    ? 'Recent repair patterns were classified as historical or unverified'
                    : 'No active high-confidence repair candidates were detected',
                '',
                $aEvidence
            );

            return;
        }

        $aEvidence = $this->humanEvidence(
            'Doctor identified '
                . count($aActive)
                . ' repair candidate(s) that merit administrator review.',
            array(),
            'Detection does not change site health by itself. Individual underlying failures may affect runtime or Studio operation.',
            'Administrator approval is required. Doctor can arm supported repairs and explicitly protect verified runtime content. Module update execution remains a separate guarded stage.'
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
            'High-confidence repair candidates are available for review',
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
