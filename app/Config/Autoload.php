<?php

namespace Config;

use CodeIgniter\Config\AutoloadConfig;

/**
 * -------------------------------------------------------------------
 * AUTOLOADER CONFIGURATION
 * -------------------------------------------------------------------
 *
 * This file defines the namespaces and class maps so the Autoloader
 * can find the files as needed.
 *
 * NOTE: If you use an identical key in $psr4 or $classmap, then
 *       the values in this file will overwrite the framework's values.
 *
 * NOTE: This class is required prior to Autoloader instantiation,
 *       and does not extend BaseConfig.
 */
class Autoload extends AutoloadConfig
{
    /**
     * -------------------------------------------------------------------
     * Namespaces
     * -------------------------------------------------------------------
     * This maps the locations of any namespaces in your application to
     * their location on the file system. These are used by the autoloader
     * to locate files the first time they have been instantiated.
     *
     * The 'Config' (APPPATH . 'Config') and 'CodeIgniter' (SYSTEMPATH) are
     * already mapped for you.
     *
     * You may change the name of the 'App' namespace if you wish,
     * but this should be done prior to creating any namespaced classes,
     * else you will need to modify all of those classes for this to work.
     *
     * @var array<string, list<string>|string>
     */
    public $psr4 = [
        APP_NAMESPACE => APPPATH,
    ];

    /**
     * -------------------------------------------------------------------
     * Class Map
     * -------------------------------------------------------------------
     * Composer's PSR-4 loader needs the FILENAME to match the classname
     * exactly (e.g. `User.php` for `class User`). Our project uses
     * lowercase filenames (`user.php`, `app.php`, `user_model.php`,
     * `app_model.php`) — pview style — which Composer rejects with the
     * "does not comply with psr-4 autoloading standard" warning.
     *
     * To keep the lowercase filenames AND still autoload these classes
     * reliably (especially with `composer install --optimize-autoloader`
     * on production), we register each one explicitly here. CI4's
     * autoloader checks the classmap before falling back to PSR-4.
     *
     * @var array<string, string>
     */
    public $classmap = [
        'App\\Controllers\\App'  => APPPATH . 'Controllers/app.php',
        'App\\Controllers\\User' => APPPATH . 'Controllers/user.php',
        'App\\Models\\App_model'  => APPPATH . 'Models/app_model.php',
        'App\\Models\\User_model' => APPPATH . 'Models/user_model.php',
        'App\\Models\\Helper_model' => APPPATH . 'Models/helper_model.php',
    ];

    /**
     * -------------------------------------------------------------------
     * Files
     * -------------------------------------------------------------------
     * The files array provides a list of paths to __non-class__ files
     * that will be autoloaded. This can be useful for bootstrap operations
     * or for loading functions.
     *
     * Prototype:
     *   $files = [
     *       '/path/to/my/file.php',
     *   ];
     *
     * @var list<string>
     */
    public $files = [];

    /**
     * -------------------------------------------------------------------
     * Helpers
     * -------------------------------------------------------------------
     * Prototype:
     *   $helpers = [
     *       'form',
     *   ];
     *
     * @var list<string>
     */
    public $helpers = [];
}
