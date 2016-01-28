<?php

namespace Odp;

class Conf {

    protected static $_isInit = false;
    protected static $_confCache = null;
    protected static $_confPath = null;

    public static function init($path) {
        
        if (self::$_isInit) return false;

        self::$_confPath = $path;
        return true;
    }

    public static function getConf($item) {
        
        if (isset(self::$_confCache[$item])) return self::$_confCache[$item];

        $path = self::$_confPath . '/' . $item . '.conf';
        if (file_exists($path)) {
            $data = file_get_contents($path);
            $data = json_decode($data, true);
            self::$_confCache[$item] = $data;
            return $data;
        } else {
            return false;    
        }
    }
    
}

