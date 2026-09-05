<?php
/**
 * My UNA Doctor
 * Studio-only health diagnostics for UNA CMS.
 *
 * @defgroup    MyUnaDoctor My UNA Doctor
 * @ingroup     UnaModules
 *
 * @{
 */

$aConfig = array(
    'type' => BX_DOL_MODULE_TYPE_MODULE,
    'name' => 'my_una_doctor',
    'title' => 'My UNA Doctor',
    'note' => 'Studio-only UNA health diagnostics, integrity checks and scan history.',
    'version' => '0.5.0',
    'vendor' => 'NewtronJax',
    'help_url' => '',

    /*
     * UNA treats compatible_with as a minimum-version floor, not a wildcard
     * allowlist. This first test build targets UNA 15 beginning with 15.0.0-A1.
     */
    'compatible_with' => array(
        '15.0.0-A1',
    ),

    /* ZIP/module layout: modules/newton/my_una_doctor/ */
    'home_dir' => 'newton/my_una_doctor/',
    'home_uri' => 'my_una_doctor',

    'db_prefix' => 'my_una_doctor_',
    'class_prefix' => 'MyUnaDoctor',
    'language_category' => 'My UNA Doctor',

    'install' => array(
        'execute_sql' => 1,
        'update_languages' => 1,
        'clear_db_cache' => 1,
    ),
    'uninstall' => array(
        'execute_sql' => 1,
        'update_languages' => 1,
        'clear_db_cache' => 1,
    ),
    'enable' => array(
        'execute_sql' => 1,
        'clear_db_cache' => 1,
        'clear_template_cache' => 1,
    ),
    'disable' => array(
        'execute_sql' => 1,
        'clear_db_cache' => 1,
        'clear_template_cache' => 1,
    ),

    'dependencies' => array(),
);

/** @} */
