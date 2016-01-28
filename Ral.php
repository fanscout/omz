<?php

namespace Odp;

/*
Usage:
 \Odp\Conf::getConf('/a/b');
 
 */

class Ral {

    protected static $_isInit = false;

    public static function init($path) {
        
        (!self::$_isInit) && return false;

        return true;
    }

    public static function call($service, $method, $input, $extra = null) {
        
    }

    public static function getErrno() {}
    public static function getErrmsg() {}
    public static function getService() {}

}

