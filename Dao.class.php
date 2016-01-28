<?php

/***************************************************************************
 *
 * Copyright (c) 2015 Meilishuo.com, Inc. All Rights Reserved
 *
 **************************************************************************/



/**
 * @file   Dao.class.php
 * @author CHEN Yijie(yijiechen@meilishuo.com)
 * @date   2015/09/01 16:16:03
 * @brief  单表数据操作类 
 *
 **/

namespace Storehouse\Libs;

use \Storehouse\Libs\DB;
use \Storehouse\Libs\Db\Filter;

class Dao {
	
    protected  $_db;
    
    protected  $_tableName;

    protected  $_dbName = 'storehouse';
    
    protected  $_checkMapping = array();

    protected  $_filterMapping = array();

    public function __construct() {
        $this->_db = new DB($this->_dbName);
    }

    public function startTransaction() {
        $this->_db->__get(db)->beginTransaction ();
    }

    public function rollBack() {
        $this->_db->__get(db)->rollback ();
    }

    public function commit() {
        $this->_db->__get(db)->commit ();
    }

    public function getByConds($fields, $conds, $appends = NULL, $master = false) {
        $conds = Filter::check($conds, $this->_checkMapping, $this->_filterMapping);
        $conds = $this->_db->getConds($conds);
        $fields = Filter::map($fields, $this->_filterMapping);
        $ret = $this->_db->select($this->_tableName, $fields, $conds, null, $appends, $master);
        return $ret;
    }

    public function insert($fields) {
        $fields = Filter::check($fields, $this->_checkMapping, $this->_filterMapping);
        $ret = $this->_db->insert($this->_tableName, $fields);
        return $ret;
    }

    public function updateByConds($fields, $conds) {
        $conds = Filter::check($conds,  $this->_checkMapping, $this->_filterMapping);
        $conds = $this->_db->getConds($conds);
        $ret = $this->_db->update($this->_tableName, $fields, $conds);
        return $ret;
    }

 	public function delByConds($conds) {
        $conds = Filter::check($conds, $this->_checkMapping, $this->_filterMapping);
        $conds = $this->_db->getConds($conds);
        $ret = $this->_db->delete($this->_tableName, $conds);
        return $ret;
    }
    
	public function getListTotalByConds($conds, $master = false) {
        $conds = Filter::check($conds, $this->_checkMapping, $this->_filterMapping);
        $conds = $this->_db->getConds($conds);

        $fields = array(
            'count(*) as cnt'
        );
        $res = $this->_db->select($this->_tableName, $fields, $conds, null, null, $master);
        if (empty($res)) {
            return 0;
        }

        return $res[0]['cnt'];
    }

    public function getLastSQL() {
        return $this->_db->getLastSQL();
    }

    public function getInsertID() {
        return $this->_db->getInsertID();
    }
    
    public function queryBySql($sql, $read = true) {
        $ret = $this->_db->query($sql, $read);
        return $ret;
    }
}
