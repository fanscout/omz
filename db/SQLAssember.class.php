<?php

/***************************************************************************
 *
 * Copyright (c) 2015 Meilishuo.com, Inc. All Rights Reserved
 *
 **************************************************************************/



/**
 * @file SQLAssember.class.php
 * @author CHEN Yijie(yijiechen@meilishuo.com)
 * @date 2015/09/01 16:16:03
 * @brief  错误类，主动打印日志 
 *
 **/


namespace Storehouse\Libs\Db;

use \Storehouse\Libs\Db\ISQL as ISQL;

class SQLAssember implements ISQL
{
    const LIST_COM = 0;
    const LIST_AND = 1;
    const LIST_SET = 2;

    private $sql = NULL;
    //private $db = NULL;

    //public function __construct(Bd_DB $db)
    //{
    //    $this->db = $db;
    //}

    /**
    * @brief 获取sql
    *
    * @return 
    */
    public function getSQL()
    {
        return $this->sql;
    }

    /**
    * @brief 获取select语句
    *
    * @param $tables 表名
    * @param $fields 字段名
    * @param $conds 条件
    * @param $options 选项
    * @param $appends 结尾操作
    *
    * @return 
    */
    public function getSelect($tables, $fields, $conds = NULL, $options = NULL, $appends = NULL)
    {
        $sql = 'SELECT ';

        // 1. options
        if($options !== NULL)
        {
            $options = $this->__makeList($options, self::LIST_COM, ' ');
            if(!strlen($options))
            {
                $this->sql = NULL;
                return NULL;
            }
            $sql .= "$options ";
        }

        // 2. fields
        $fields = $this->__makeList($fields, self::LIST_COM);
        if(!strlen($fields))
        {
            $this->sql = NULL;
            return NULL;
        }
        $sql .= "$fields FROM ";

        // 3. from
        $tables = $this->__makeList($tables, self::LIST_COM);
        if(!strlen($tables))
        {
            $this->sql = NULL;
            return NULL;
        }
        $sql .= $tables;

        // 4. conditions
        if(!empty($conds))
        {
            $conds = $this->__makeList($conds, self::LIST_AND);
            if(!strlen($conds))
            {
                $this->sql = NULL;
                return NULL;
            }
            $sql .= " WHERE $conds";
        }
        // 5. other append
        if($appends !== NULL)
        {
            $appends = $this->__makeList($appends, self::LIST_COM, ' ');
            if(!strlen($appends))
            {
                $this->sql = NULL;
                return NULL;
            }
            $sql .= " $appends";
        }
        
        $this->sql = $sql;
        return $sql;
    }

    /**
    * @brief 获取update语句
    *
    * @param $table 表名
    * @param $row 字段
    * @param $conds 条件
    * @param $options 选项
    * @param $appends 结尾操作
    *
    * @return 
    */
    public function getUpdate($table, $row, $conds = NULL, $options = NULL, $appends = NULL)
    {
        if(empty($row))
        {
            return NULL;
        }
        return $this->__makeUpdateOrDelete($table, $row, $conds, $options, $appends);
    }

    /**
    * @brief 获取delete语句
    *
    * @param $table
    * @param $conds
    * @param $options
    * @param $appends
    *
    * @return 
    */
    public function getDelete($table, $conds = NULL, $options = NULL, $appends = NULL)
    {
        return $this->__makeUpdateOrDelete($table, NULL, $conds, $options, $appends);
    }

    private function __makeUpdateOrDelete($table, $row, $conds, $options, $appends)
    {
        // 1. options
        if($options !== NULL)
        {
            if(is_array($options))
            {
                $options = implode(' ', $options);
            }
            $sql = $options;
        }

        // 2. fields
        // delete
        if(empty($row))
        {
            $sql = "DELETE $options FROM $table ";
        }
        // update
        else
        {
            $sql = "UPDATE $options $table SET ";
            $row = $this->__makeList($row, self::LIST_SET);
            if(!strlen($row))
            {
                $this->sql = NULL;
                return NULL;
            }
            $sql .= "$row ";
        }

        // 3. conditions
        if($conds !== NULL)
        {
            $conds = $this->__makeList($conds, self::LIST_AND);
            if(!strlen($conds))
            {
                $this->sql = NULL;
                return NULL;
            }
            $sql .= "WHERE $conds ";
        }

        // 4. other append
        if($appends !== NULL)
        {
            $appends = $this->__makeList($appends, self::LIST_COM, ' ');
            if(!strlen($appends))
            {
                $this->sql = NULL;
                return NULL;
            }
            $sql .= $appends;
        }

        $this->sql = $sql;
        return $sql;
    }

    /**
    * @brief 获取insert语句
    *
    * @param $table 表名
    * @param $row 字段
    * @param $options 选项
    * @param $onDup 键冲突时的字段值列表
    *
    * @return 
    */
    public function getInsert($table, $row, $options = NULL, $onDup = NULL)
    {
        $sql = 'INSERT INTO ';

        // 1. options
        if($options !== NULL)
        {
            if(is_array($options))
            {
                $options = implode(' ', $options);
            }
            $sql .= "$options ";
        }

        // 2. table
        $sql .= "$table SET ";

        // 3. clumns and values
        $row = $this->__makeList($row, self::LIST_SET);
        if(!strlen($row))
        {
            $this->sql = NULL;
            return NULL;
        }
        $sql .= $row;

        if(!empty($onDup))
        {
            $sql .= ' ON DUPLICATE KEY UPDATE ';
            $onDup = $this->__makeList($onDup, self::LIST_SET);
            if(!strlen($onDup))
            {
                $this->sql = NULL;
                return NULL;
            }
            $sql .= $onDup;
        }
        $this->sql = $sql;
        return $sql;
    }

    private function __makeList($arrList, $type = self::LIST_SET, $cut = ', ')
    {
        if(is_string($arrList))
        {
            return $arrList;
        }

        $sql = '';

        // for set in insert and update
        if($type == self::LIST_SET)
        {
            foreach($arrList as $name => $value)
            {
                if(is_int($name))
                {
                    $sql .= "$value, ";
                }
                else
                {
                    if(!is_int($value))
                    {
                        if($value === NULL)
                        {
                            $value = 'NULL';
                        }
                        else
                        {
                            //$value = '\''.$this->db->escapeString($value).'\'';
                            $value = str_replace(array('\''), array('\\\''), $value);
                            $value = '\''.$value.'\'';
                        }
                    }
                    $type = substr($name, 0, 1);               //addition
                    if($type == "+" || $type == "-") {         //addition
                        $name = substr($name, 1);              //addition
                        $sql .= "$name=$name $type $value, ";  //addition
                    } else {                                   //addition
                        $sql .= "$name=$value, ";
                    }                                          //addition
                }
            }
            $sql = substr($sql, 0, strlen($sql) - 2);
        }
        // for where conds
        else if($type == self::LIST_AND)
        {
            foreach($arrList as $name => $value)
            {
                if(is_int($name))
                {
                    $sql .= "($value) AND ";
                }
                else
                {
                    if(!is_int($value))
                    {
                        if($value === NULL)
                        {
                            $value = 'NULL';
                        }
                        else if(!is_array($value))
                        {
                            //$value = '\''.$this->db->escapeString($value).'\'';
                            $value = str_replace(array('\''), array('\\\''), $value);
                            $value = '\''.$value.'\'';
                        }
                        else
                        {
                            $value = "('" . implode("', '",$value) . "')";
                        }
                    }
                    $sql .= "($name $value) AND ";
                }
            }
            $sql = substr($sql, 0, strlen($sql) - 5);
        }
        else
        {
            $sql = implode($cut, $arrList);
        }

        return $sql;
    }
}
/* vim: set expandtab ts=4 sw=4 sts=4 tw=100: */
