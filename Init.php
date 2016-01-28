<?php

namespace Odp;

class Init {

    private static $isInit = false;

    public static function init($app_name = null) {
        if (self::$isInit) {
            return false;
        }

        self::$isInit = true;

        self::initContext();
        self::initApp($app_name);
        
        // todo: provide this feature any more? 
        // self::doAppPrepend();
        
        return \Yaf_Application::app();
    }


    private static function initContext() {
    
        // set timezone
        date_default_timezone_set('PRC');

        // page start time，use $_SERVER['REQUEST_TIME'] instead above PHP 5.4+
        define('REQUEST_TIME_US', intval(microtime(true)*1000000));

        // ODP pre-defined
        define('IS_ODP', true);

        define('ROOT_PATH', realpath(dirname(__FILE__) . '/../../../'));
        define('CONF_PATH', ROOT_PATH.'/conf');
        define('DATA_PATH', ROOT_PATH.'/data');
        define('BIN_PATH', ROOT_PATH.'/php/bin');
        define('LOG_PATH', ROOT_PATH.'/log');
        define('APP_PATH', ROOT_PATH.'/app');
        define('TPL_PATH', ROOT_PATH.'/template');
        define('LIB_PATH', ROOT_PATH.'/php/phplib');
        define('WEB_ROOT', ROOT_PATH.'/webroot');
        define('PHP_EXEC', BIN_PATH.'/php');

        $app_name == null && ($app_name = self::getAppName()) == null && $app_name = 'unknown-app';
 
        define('ODP_APP', $app_name);
        define('APP', ODP_APP);

        $loader = \Yaf_Loader::getInstance(null, LIB_PATH);
	//var_dump($loader);

        define('CLIENT_IP', \Odp\Ip::getClientIp());
        define('USER_IP', \Odp\Ip::getUserIp());
	define('FRONTEND_IP', \Odp\Ip::getFrontendIp());

        // init autoloader 
        // todo PSR-4 autoloader

        return true;
    }

    private static function initApp($app_name) {
        $app = \Odp\App::getInstance();

        \Odp\Conf::init($app->confPath);
        \Odp\Log::init($app->logPath);

        $ap_conf = \Odp\Conf::getConf('yaf');
	    $ap_conf['directory'] = $app->appPath.DIRECTORY_SEPARATOR;
        $app = new \Yaf_Application(array('yaf' => $ap_conf));
 
        return true;
    }

    // 执行产品线的auto_prepend
    private static function doAppPrepend() {
        if (file_exists(APP_PATH."/auto_prepend.php")) {
            include_once APP_PATH."/auto_prepend.php";
        }
    }

    private static function getAppName() {
        $app_name = null;
        
        if (PHP_SAPI != 'cli') {
            // sapi: cgi
            // /xxx/index.php
            //$script = explode('/', $_SERVER['SCRIPT_NAME']);
            //某些重写规则会导致"/xxx/index.php/"这样的SCRIPT_NAME
            $script = explode('/', rtrim($_SERVER['SCRIPT_NAME'], '/'));
            
            // ODP app
            if(count($script) == 3 && $script[2] == 'index.php') {
                $app_name = $script[1];
            }
        } else {
            // sapi: cli
            $file = $_SERVER['argv'][0];
            if ($file{0} != '/') {
                $cwd = getcwd();
                $full_path = realpath($file);
            } else {
                $full_path = $file;
            }
            
            if (strpos($full_path, APP_PATH.'/') === 0) {
                $s = substr($full_path, strlen(APP_PATH)+1);
                if (($pos = strpos($s, '/')) > 0) {
                    $app_name = substr($s, 0, $pos);
                }
            }
        }
        
        return $app_name;
    }
    
}
