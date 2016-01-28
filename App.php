<?php

namespace Odp;

final class App {

    private static $instance = null;

    private $name = null;
    private $confPath = null;
    private $dataPath = null;
    private $logPath = null;
    private $tplPath = null;
    private $env = array();

    public static function initInstance($name) {
        if (self::$instance != null) {
		return null;
	}
        self::$instance = new self($name);
        return self::$instance;
    }

    public static function getInstance() {
        (self::$instance == null) && self::$instance = self::initInstance(ODP_APP);
        return self::$instance;
    }

    protected function __construct($name) {
        $this->name = $name;
        $this->confPath = CONF_PATH.DIRECTORY_SEPARATOR."$name";
        $this->dataPath = DATA_PATH.DIRECTORY_SEPARATOR."$name";
        $this->logPath = LOG_PAT.DIRECTORY_SEPARATOR."$name";
        $this->appPath = APP_PATH.DIRECTORY_SEPARATOR."$name";

        $tpl_path = TPL_PATH.DIRECTORY_SEPARATOR."$name";
        $this->tplPath = is_dir($tpl_path) ? $tpl_path : TPL_PATH;
    }

    public function __get($property) {
        if (isset($this->$property)) {
		return $this->$property;
}
        
        return $this->env[$property];
    }

    public function __set($property, $value) {
        if (isset($this->$property)) return;
    
        $this->env[$property] = $value;
    }

}

?>
