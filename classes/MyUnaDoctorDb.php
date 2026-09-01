<?php defined('BX_DOL') or die('hack attempt');

class MyUnaDoctorDb extends BxDolModuleDb
{
    public function __construct(&$oConfig)
    {
        parent::__construct($oConfig);
    }

    public function addScan(array $aScan)
    {
        $sJson = json_encode($aScan, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($sJson === false)
            $sJson = '{}';

        return $this->query(
            "INSERT INTO `my_una_doctor_scans`
             (`started`, `finished`, `duration_ms`, `health_score`, `critical_count`, `warning_count`, `info_count`, `ok_count`, `result_json`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            array(
                (int)$aScan['started'],
                (int)$aScan['finished'],
                (int)$aScan['duration_ms'],
                (int)$aScan['summary']['health_score'],
                (int)$aScan['summary']['critical'],
                (int)$aScan['summary']['warning'],
                (int)$aScan['summary']['info'],
                (int)$aScan['summary']['ok'],
                $sJson,
            )
        );
    }

    public function getLatestScan()
    {
        $aRow = $this->getRow("SELECT * FROM `my_una_doctor_scans` ORDER BY `id` DESC LIMIT 1");
        return $this->decodeScan($aRow);
    }

    public function getScans($iLimit = 20)
    {
        $iLimit = max(1, min(100, (int)$iLimit));
        return $this->getAll("SELECT `id`, `started`, `finished`, `duration_ms`, `health_score`, `critical_count`, `warning_count`, `info_count`, `ok_count` FROM `my_una_doctor_scans` ORDER BY `id` DESC LIMIT " . $iLimit);
    }


    public function addRepairJournal(array $aData)
    {
        $encode = static function ($value) {
            $json = json_encode(
                $value,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
            );

            return $json === false ? '{}' : $json;
        };

        $iNow = time();

        $bResult = $this->query(
            "INSERT INTO `my_una_doctor_repairs`
             (`repair_key`, `rule`, `module`, `status`,
              `created`, `updated`, `preflight_id`, `arm_id`,
              `original_state_json`, `planned_state_json`,
              `result_json`, `error_text`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            array(
                (string)($aData['repair_key'] ?? ''),
                (string)($aData['rule'] ?? ''),
                (string)($aData['module'] ?? ''),
                (string)($aData['status'] ?? 'planned'),
                $iNow,
                $iNow,
                (string)($aData['preflight_id'] ?? ''),
                (string)($aData['arm_id'] ?? ''),
                $encode($aData['original_state'] ?? array()),
                $encode($aData['planned_state'] ?? array()),
                $encode($aData['result'] ?? array()),
                (string)($aData['error_text'] ?? ''),
            )
        );

        if ($bResult === false)
            return 0;

        return (int)$this->lastId();
    }

    public function updateRepairJournal($iId, array $aFields)
    {
        $iId = (int)$iId;

        if ($iId <= 0)
            return false;

        $aAllowed = array(
            'status',
            'preflight_id',
            'arm_id',
            'original_state_json',
            'planned_state_json',
            'result_json',
            'error_text',
        );

        $aSet = array();
        $aValues = array();

        foreach ($aFields as $sField => $mixedValue) {
            if (!in_array($sField, $aAllowed, true))
                continue;

            $aSet[] = "`{$sField}` = ?";
            $aValues[] = (string)$mixedValue;
        }

        if (empty($aSet))
            return false;

        $aSet[] = "`updated` = ?";
        $aValues[] = time();

        $aValues[] = $iId;

        return $this->query(
            "UPDATE `my_una_doctor_repairs`
             SET " . implode(', ', $aSet) . "
             WHERE `id` = ?",
            $aValues
        ) !== false;
    }

    public function getRepairJournalByKey($sRepairKey)
    {
        return $this->getRow(
            "SELECT *
             FROM `my_una_doctor_repairs`
             WHERE `repair_key` = ?
             LIMIT 1",
            array((string)$sRepairKey)
        );
    }


    protected function decodeScan($aRow)
    {
        if (empty($aRow) || !is_array($aRow))
            return array();

        $aScan = json_decode($aRow['result_json'], true);
        if (!is_array($aScan))
            return array();

        $aScan['id'] = isset($aRow['id']) ? (int)$aRow['id'] : 0;
        return $aScan;
    }
}
