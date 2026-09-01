SET @sName = 'my_una_doctor';
SET @sPath = 'newton/my_una_doctor/';

-- MY UNA DOCTOR DATA

CREATE TABLE IF NOT EXISTS `my_una_doctor_scans` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `started` int(11) unsigned NOT NULL DEFAULT 0,
    `finished` int(11) unsigned NOT NULL DEFAULT 0,
    `duration_ms` int(10) unsigned NOT NULL DEFAULT 0,
    `health_score` tinyint(3) unsigned NOT NULL DEFAULT 0,
    `critical_count` int(10) unsigned NOT NULL DEFAULT 0,
    `warning_count` int(10) unsigned NOT NULL DEFAULT 0,
    `info_count` int(10) unsigned NOT NULL DEFAULT 0,
    `ok_count` int(10) unsigned NOT NULL DEFAULT 0,
    `result_json` mediumtext NOT NULL,
    PRIMARY KEY (`id`),
    KEY `finished` (`finished`),
    KEY `health_score` (`health_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS `my_una_doctor_repairs` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `repair_key` varchar(64) NOT NULL,
    `rule` varchar(128) NOT NULL,
    `module` varchar(128) NOT NULL DEFAULT '',
    `status` varchar(32) NOT NULL DEFAULT 'planned',
    `created` int(11) unsigned NOT NULL DEFAULT 0,
    `updated` int(11) unsigned NOT NULL DEFAULT 0,
    `preflight_id` varchar(64) NOT NULL DEFAULT '',
    `arm_id` varchar(64) NOT NULL DEFAULT '',
    `original_state_json` mediumtext NOT NULL,
    `planned_state_json` mediumtext NOT NULL,
    `result_json` mediumtext NOT NULL,
    `error_text` text NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `repair_key` (`repair_key`),
    KEY `status` (`status`),
    KEY `module` (`module`),
    KEY `updated` (`updated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- STUDIO PAGE & WIDGET

INSERT INTO `sys_std_pages`(`index`, `name`, `header`, `caption`, `icon`) VALUES
(3, @sName, '_my_una_doctor', '_my_una_doctor', CONCAT(@sName, '@modules/', @sPath, '|std-icon.svg'));
SET @iPageId = LAST_INSERT_ID();

SET @iParentPageId = (SELECT `id` FROM `sys_std_pages` WHERE `name` = 'home');
SET @iParentPageOrder = (SELECT MAX(`order`) FROM `sys_std_pages_widgets` WHERE `page_id` = @iParentPageId);
INSERT INTO `sys_std_widgets` (`page_id`, `module`, `type`, `url`, `click`, `icon`, `caption`, `cnt_notices`, `cnt_actions`) VALUES
(@iPageId, @sName, 'manage', CONCAT('{url_studio}module.php?name=', @sName), '', CONCAT(@sName, '@modules/', @sPath, '|std-icon.svg'), '_my_una_doctor', '', 'a:4:{s:6:"module";s:6:"system";s:6:"method";s:11:"get_actions";s:6:"params";a:0:{}s:5:"class";s:18:"TemplStudioModules";}');
INSERT INTO `sys_std_pages_widgets` (`page_id`, `widget_id`, `order`) VALUES
(@iParentPageId, LAST_INSERT_ID(), IF(ISNULL(@iParentPageOrder), 1, @iParentPageOrder + 1));
