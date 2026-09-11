<?php
// Candidate only: native version/hash checks remain mandatory.
$aConfig = array(
    'title' => 'My UNA Doctor',
    'version_from' => '0.6.2',
    'version_to' => '0.6.4',
    'vendor' => 'My UNA World',
    'compatible_with' => array('15.0.0-A1'),
    'home_dir' => 'newton/my_una_doctor/updates/update_0.6.2_0.6.4/',
    'home_uri' => 'my_una_doctor_update_062_064',
    'module_dir' => 'newton/my_una_doctor/',
    'module_uri' => 'my_una_doctor',
    'db_prefix' => 'my_una_doctor_',
    'class_prefix' => 'MyUnaDoctor',
    'install' => array('update_files' => 1, 'clear_db_cache' => 1, 'clear_template_cache' => 1),
    'language_category' => 'My UNA Doctor',
    'delete_files' => array(),
);

