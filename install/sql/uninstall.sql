SET @sName = 'my_una_doctor';

-- STUDIO PAGE & WIDGET

DELETE FROM `tp`, `tw`, `tpw`
USING `sys_std_pages` AS `tp`, `sys_std_widgets` AS `tw`, `sys_std_pages_widgets` AS `tpw`
WHERE `tp`.`id` = `tw`.`page_id`
  AND `tw`.`id` = `tpw`.`widget_id`
  AND `tp`.`name` = @sName;


-- MY UNA DOCTOR SETTINGS

SET @iDoctorOptionsTypeId = (
    SELECT `id`
    FROM `sys_options_types`
    WHERE `name` = @sName
    LIMIT 1
);

SET @iDoctorOptionsCategoryId = (
    SELECT `id`
    FROM `sys_options_categories`
    WHERE `type_id` = @iDoctorOptionsTypeId
      AND `name` = @sName
    LIMIT 1
);

DELETE FROM `sys_options`
WHERE `category_id` = @iDoctorOptionsCategoryId
  AND `name` IN (
      'my_una_doctor_show_activity',
      'my_una_doctor_expand_technical_details',
      'my_una_doctor_work_cycle_size',
      'my_una_doctor_maintenance_show_header',
      'my_una_doctor_maintenance_show_footer',
      'my_una_doctor_maintenance_heading',
      'my_una_doctor_maintenance_message',
      'my_una_doctor_maintenance_patience',
      'my_una_doctor_maintenance_return_message',
      'my_una_doctor_maintenance_footer_message',
      'my_una_doctor_use_maintenance_page'
  );

DELETE FROM `sys_options_categories`
WHERE `id` = @iDoctorOptionsCategoryId
  AND `name` = @sName;

DELETE FROM `sys_options_types`
WHERE `id` = @iDoctorOptionsTypeId
  AND `name` = @sName
  AND `group` = 'modules';


-- MY UNA DOCTOR DATA

DROP TABLE IF EXISTS `my_una_doctor_repairs`;
DROP TABLE IF EXISTS `my_una_doctor_scans`;

-- Remove My UNA Doctor's Module Reference Library catalog.
DROP TABLE IF EXISTS `my_una_doctor_reference_library`;

