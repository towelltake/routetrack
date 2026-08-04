<?php
//$startTime = microtime(true);

/**
 * Put errors on ON for debugging this file
 */
ini_set('display_errors',0);
error_reporting(0);
// define these global path constants here
define( 'ROOT_PATH' , ( dirname( __FILE__ ) ) ) ;

define( 'ROOT_PATH_MAIN' , dirname( dirname( __FILE__ ) ) ) ;

define( 'LIB_PATH' , ROOT_PATH_MAIN . '/library' ) ; 
// define the path for config.ini
define( 'CONFIG_PATH' , ROOT_PATH . '/application/config' ) ;

// Define path to application directory
defined('APPLICATION_PATH') || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/application'));

// Define application environment
defined('APPLICATION_ENV') || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'development'));


// Define path to base directory
defined('BASE_PATH') || define('BASE_PATH', APPLICATION_PATH."/..");

// Define path to cache directory
defined('CACHE_PATH') || define('CACHE_PATH', BASE_PATH.'/data/cache');

/*
 * Defines the directory separator for windows or unix env
 */
define('DS', DIRECTORY_SEPARATOR);

/**
 * Define the absolute/relative paths to the library path, the app library path,
 * app path and the database configuration path 
 */
define('ZEND_LIBRARY_PATH', realpath(ROOT_PATH_MAIN . '/library'));
define('APP_LIBRARY_PATH', APPLICATION_PATH . '/library');




$paths = array(ZEND_LIBRARY_PATH,APP_LIBRARY_PATH,get_include_path());

/**
 * Set the include paths to point to the new defined paths
 */
set_include_path(implode(PATH_SEPARATOR, $paths));

define('BASE_URL', ROOT_PATH_MAIN);

/** Zend_Application */
require_once 'Zend/Application.php';

// Create application, bootstrap, and run
$application = new Zend_Application(APPLICATION_ENV,APPLICATION_PATH . DS .  'config' . DS . 'application.ini');

//Start
$application->bootstrap();
//echo 'Version >>>>>>>>>'.Zend_Version::VERSION;
$application->run();

$endTime = microtime(true);
//echo  number_format(($endTime - $startTime),2);