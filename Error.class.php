<?php

/***************************************************************************
 *
 * Copyright (c) 2015 Meilishuo.com, Inc. All Rights Reserved
 *
 **************************************************************************/



/**
 * @file   Error.class.php
 * @author CHEN Yijie(yijiechen@meilishuo.com)
 * @date   2015/09/01 16:16:03
 * @brief  错误类，主动打印日志 
 *
 **/


namespace Storehouse\Libs;

use Storehouse\Libs\MLog;

class Error extends \Exception {
    const WARNING = 'warning';
    const DEBUG   = 'debug';
    const TRACE   = 'trace';
    const FATAL   = 'fatal';
    const NOTICE  = 'notice';
    
    private $err_no;
    private $err_str;
    //额外的中文错误提示
    private $err_cstr;
    private $err_arg;

    public function __construct($err_no, $arg = null, $level = self::WARNING, $msg = '') {
        $this->err_no = $err_no;
        $errstr = strval(@ErrorCodes::$codes[$err_no]);
        $this->err_str = $errstr;
        $this->err_arg = $arg;
        $this->err_cstr = $msg;

        $stack_trace = $this->getTrace();
        $class = @$stack_trace[0]['class'];
        $type = @$stack_trace[0]['type'];
        $function = $stack_trace[0]['function'];
        $file = $this->file;
        $line = $this->line;
        if ($class != null) {
            $function = "{$class}{$type}{$function}";
        }
        MLog::$level("$errstr at [$function at $file:$line] ".$msg, $err_no, $arg);
        parent::__construct($errstr, $err_no);
    }

    public function getErrNo() {
        return $this->err_no;
    }
    public function getErrStr() {
        return $this->err_str;
    }
    public function getErrCStr() {
        return $this->err_cstr;
    }

    public function  getErrArg() {
        return $this->err_arg;
    }
}
