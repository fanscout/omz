<?php

/***************************************************************************
 *
 * Copyright (c) 2015 Meilishuo.com, Inc. All Rights Reserved
 *
 **************************************************************************/



/**
 * @file   DB.class.php
 * @author CHEN Yijie(yijiechen@meilishuo.com)
 * @date   2015/09/01 16:16:03
 * @brief  DB操作基础类 提供 query insert update 等方法 
 *
 **/

namespace Odp;

use Odp\Db\DBConnManager;
use Odp\Db\SQLAssember;

use Odp\Mlog\MLog;

class DB {
    const T_NUM = 'n';
    const T_NUM2 = 'd';
    const T_STR = 's';
    const T_RAW = 'S';
    const T_RAW2 = 'r';
    const V_ESC = '%';

    // query result types
    const FETCH_RAW = 0;    // return raw mysqli_result
    const FETCH_ROW = 1;    // return numeric array
    const FETCH_ASSOC = 2;  // return associate array
    const FETCH_OBJ = 3;    // return Bd_DBResult object

    const LOG_SQL_LENGTH = 30;

    private $mysql = NULL;
    private $dbConf = NULL;
    private $isConnected = false;
    private $lastSQL = NULL;

    private $enableProfiling = false;
    private $arrCost = NULL;
    private $lastCost = 0;
    private $totalCost = 0;

    private $hkBeforeQ = array();
    private $hkAfterQ = array();
    private $onfail = NULL;

    private $sqlAssember = NULL;
	private $_error = NULL;

    public function __construct($dbname)
    {
        $this->mysql = DBConnManager::getConn($dbname);
    }


	/**
	* @brief 设置mysql连接选项
	*
	* @param $optName 选项名字
	* @param $value   选项值
	*
	* @return
	*/
    public function setOption($optName, $value)
    {
        return $this->mysql->options($optName, $value);
    }

	/**
	* @brief 设置连接超时
	*
	* @param $seconds 超时时间
	*
	* @return
	*/
    public function setConnectTimeOut($seconds)
    {
        if($seconds <= 0)
        {
            return false;
        }
        return $this->setOption(MYSQLI_OPT_CONNECT_TIMEOUT, $seconds);
    }

    public function __get($name)
    {
        switch($name)
        {
            case 'error':
                return $this->mysql->error;
            case 'errno':
                return $this->mysql->errno;
            case 'insertID':
                return $this->mysql->insert_id;
            case 'affectedRows':
                return $this->mysql->affected_rows;
            case 'lastSQL':
                return $this->lastSQL;
            case 'lastCost':
                return $this->lastCost;
            case 'totalCost':
                return $this->totalCost;
            case 'isConnected':
                return $this->isConnected;
            case 'db':
                return $this->mysql;
            default:
                return NULL;
        }
    }

	/**
	* @brief 查询接口
	*
	* @param $sql 查询sql
	* @param $fetchType 结果集抽取类型 
	* @param $bolUseResult 是否使用MYSQLI_USE_RESULT
	*
	* @return 结果数组：成功；false：失败
	*/
    public function query($sql, $read=true, $master=false)
    {
		$logPara = array( 
					'db_host' => $this->dbConf['host'], 
					'db_port' => $this->dbConf['port'], 
					'default_db'=> $this->dbConf['dbname'],
					);

        if(!is_string($sql))
        {
            // get sql text
            if (!($sql = $sql->getSQL()))
            {
				$this->_error['errno'] = ErrorCodes::INVALID_SQL;
				$this->_error['error'] = 'Input SQL is not valid,please use string or ISQL instance';
				return false;
            }
        }
        $this->lastSQL = $sql;
        if ($read) {
            try {
                $res = $this->mysql->read($sql,array(),$master);
            } catch( \Exception $e ) {
                $this->_error['errno'] = ErrorCodes::DB_ERROR;
                $this->_error['error'] = $e->getMessage();
                $lastLogApp = MLog::getLastLogger();
                MLog::setLogApp('mysql');
                $errMsg = 'DB Error Occured. Error Message is ' . $e->getMessage() . ' Error Tracing message is ' . $e->getTraceAsString();
                $errMsg .= ' Error Code is ' . $e->getCode();
                MLog::warning($errMsg, ErrorCodes::DB_ERROR, array('LastSQL'=>$this->lastSQL));
                throw new \Exception('DB Error, Please retry later.' , ErrorCodes::DB_ERROR);
                MLog::setLogApp($lastLogApp);
            }
        } else {
            try {
                $res = $this->mysql->write($sql);
            } catch ( \Exception $e ) {
                $this->_error['errno'] = ErrorCodes::DB_ERROR;
                $this->_error['error'] = $e->getMessage();
                $lastLogApp = MLog::getLastLogger();
                MLog::setLogApp('mysql');
                $errMsg = 'DB Error Occured. Error Message is ' . $e->getMessage() . ' Error Tracing message is ' . $e->getTraceAsString();
                $errMsg .= ' Error Code is ' . $e->getCode();
                MLog::warning($errMsg, ErrorCodes::DB_ERROR,array('LastSQL'=>$this->lastSQL));
                throw new \Exception('DB Error, Please retry later.' , ErrorCodes::DB_ERROR);
                MLog::setLogApp($lastLogApp);
            }
        }
        
        //$res = DBConnManager::getConn('coupon')->write($sql);
        // record cost
        $this->lastCost = intval(microtime(true)*1000000) - $beg;
        $this->totalCost += $this->lastCost;

        $ret = false;

		$pos = strpos($sql,"\n");
		if($pos){
			$logPara['sql'] = strstr($sql, array("\n", ' '));
		}else{
			$logPara['sql'] = $sql;
		}
	
        // res is NULL if mysql is disconnected
        if(is_bool($res) || $res === NULL)
        {
			$arrInfo = array(
				'ns'        => $this->dbConf['dbname'],
				'query'     => $logPara['sql'],
				'retry'     => 1,
				'local_ip'  => $_SERVER['SERVER_ADDR'],
				'remote_ip' => $this->dbConf['host'].':'.$this->dbConf['port'],
				'res_len'   => 0,
				'errno'     => ErrorCodes::QUERY_ERROR,
			);
            $ret = ($res == true);
        }
        // we have result
        else
        {
            $logPara['time_ms'] = $this->lastCost/1000;
            $logPara['affected_rows'] = $this->mysql->affected_rows;
			$arrInfo = array(
				'ns'        => $this->dbConf['dbname'],
				'query'     => $logPara['sql'],
				'retry'     => 1,
				'local_ip'  => $_SERVER['SERVER_ADDR'],
				'remote_ip' => $this->dbConf['host'].':'.$this->dbConf['port'],
				'res_len'   => $logPara['affected_rows'],
			);
            //MLog::trace("Query successfully",0,$logPara);
            //switch($fetchType)
            //{
            //    case self::FETCH_OBJ:
            //        $ret = new self_DBResult($res);
            //        break;

            //    case self::FETCH_ASSOC:
            //        $ret = array();
            //        while($row = $res->fetch_assoc())
            //        {
            //            $ret[] = $row;
            //        }
            //        $res->free();
            //        break;

            //    case self::FETCH_ROW:
            //        $ret = array();
            //        while($row = $res->fetch_row())
            //        {
            //            $ret[] = $row;
            //        }
            //        $res->free();
            //        break;

            //    default:
            //        $ret = $res;
            //        break;
            //}
        }
        $ret = $res;
        return $ret;
    }

    private function __getSQLAssember()
    {
        if($this->sqlAssember == NULL)
        {
            $this->sqlAssember = new SQLAssember();
        }
        return $this->sqlAssember;
    }

	/**
	* @brief select接口
	*
	* @param $tables 表名
	* @param $fields 字段名
	* @param $conds 条件
	* @param $options 选项
	* @param $appends 结尾操作
	* @param $fetchType 获取类型
	* @param $bolUseResult 是否使用MYSQL_USE_RESULT
	*
	* @return 
	*/
    public function select($tables, $fields, $conds = NULL, $options = NULL, $appends = NULL, $master = false)
    {
        $this->__getSQLAssember();
        $sql = $this->sqlAssember->getSelect($tables, $fields, $conds, $options, $appends);
        if(!$sql)
        {
            return false;
        }
        return $this->query($sql, true, $master);
    }

	/**
	* @brief select count(*)接口
	*
	* @param $tables 表名
	* @param $conds 条件
	* @param $options 选项
	* @param $appends 结尾操作
	qlAssember-
	* @return 
	*/
    public function selectCount($tables, $conds = NULL, $options = NULL, $appends = NULL)
    {
        $this->__getSQLAssember();
        $fields = 'COUNT(*)';
        $sql = $this->sqlAssember->getSelect($tables, $fields, $conds, $options, $appends);
        if(!$sql)
        {
            return false;
        }
        $res = $this->query($sql, self::FETCH_ROW);
        if($res === false)
        {
            return false;
        }
        return intval($res[0][0]);
    }

	/**
	* @brief Insert接口
	*
	* @param $table 表名
	* @param $row 字段
	* @param $options 选项
	* @param $onDup 键冲突时的字段值列表
	*
	* @return 
	*/
    public function insert($table, $row, $options = NULL, $onDup = NULL)
    {
        $this->__getSQLAssember();
        $sql = $this->sqlAssember->getInsert($table, $row, $options, $onDup);
        $this->mysql->affected_rows = $this->query($sql, false);
        if(!$sql || !$this->mysql->affected_rows)
        {
            return false;
        }
        return $this->mysql->affected_rows;
    }
    
    
    
    public function getConds($arrConds, $arrMap = null){

        if (is_null($arrConds))
        {
            return $arrConds;
        }
        $arrCondsRes = array();
        foreach($arrConds as $key => $value){
            if($arrMap !== NULL && isset($arrMap[$key]))
            {
                $key = $arrMap[$key];
            }
            if(is_array($value)){
                if(count($value) == 2){
                    $arrCondsRes["$key".$value[1]] = $value[0];
                }elseif(count($value) == 4){
                    $arrCondsRes["$key".$value[1]] = $value[0];
                    $arrCondsRes["$key".$value[3]] = $value[2];
                }elseif(count($value) == 1){
                    $arrCond = $value[0];
                    //$strCond = is_int($arrCond[0]) ? "(".implode(", ", $arrCond).")" : "('".implode("', '",$arrCond)."')";
                    $arrCondsRes["$key IN "] = $arrCond;
                }
                else{
                    throw new Error(ErrorCodes::PARAM_ERROR);
                }
            }else{
                $arrCondsRes["$key ="] = $value;
            }
        }
        return $arrCondsRes;
    }

	/**
	* @brief Update接口
	*
	* @param $table 表名
	* @param $row 字段
	* @param $conds 条件
	* @param $options 选项
	* @param $appends 结尾操作
	*
	* @return 
	*/
    public function update($table, $row, $conds = NULL, $options = NULL, $appends = NULL)
    {
        $this->__getSQLAssember();
        $sql = $this->sqlAssember->getUpdate($table, $row, $conds, $options, $appends);
        file_put_contents('/home/work/storehouse/1', '$sql-------  '.date("Y-m-d H:i:s",time()).'  :' . var_export($sql, TRUE) . "\n", FILE_APPEND);
        $this->mysql->affected_rows = $this->query($sql, false);
        if(!$sql || !$this->mysql->affected_rows)
        {
            return false;
        }
        return $this->mysql->affected_rows;
    }

	/**
	* @brief delete接口
	*
	* @param $table 表名
	* @param $conds 条件
	* @param $options 选项
	* @param $appends 结尾操作
	*
	* @return 
	*/
    public function delete($table, $conds = NULL, $options = NULL, $appends = NULL)
    {
        $this->__getSQLAssember();
        $sql = $this->sqlAssember->getDelete($table, $conds, $options, $appends);
        $this->mysql->affected_rows = $this->query($sql, false);
        if(!$sql || !$this->mysql->affected_rows)
        {
            return false;
        }
        return $this->mysql->affected_rows;
    }

	/**
	* @brief prepare查询接口
	*
	* @param $query 查询语句
	* @param $getRaw 是否返回原始的mysqli_stmt对象
	*
	* @return 
	*/
    public function prepare($query, $getRaw = false)
    {
        $stmt = $this->mysql->prepare($query);
		$arrInfo = array(
			'ns'        => $this->dbConf['dbname'],
			'query'     => $query,
			'retry'     => 1,
			'local_ip'  => $_SERVER['SERVER_ADDR'],
			'remote_ip' => $this->dbConf['host'].':'.$this->dbConf['port'],
			'res_len'   => $stmt,
		);

        if($stmt === false)
        {
            return false;
        }
        if($getRaw)
        {
            return $stmt;
        }
        else
        {
            return new self_DBStmt($stmt);
        }
    }

	/**
	* @brief 获取上一次SQL语句
	*
	* @return 
	*/
    public function getLastSQL()
    {
        return $this->lastSQL;
    }

	/**
	* @brief 获取Insert_id
	*
	* @return 
	*/
    public function getInsertID()
    {
        return $this->mysql->getInsertId();
    }

	/**
	* @brief 获取受影响的行数
	*
	* @return 
	*/
    public function getAffectedRows()
    {
        return $this->mysql->affected_rows;
    }

    //////////////////////////// profiling ////////////////////////////

	/**
	* @brief 获取上一次耗时
	*
	* @return 
	*/
    public function getLastCost()
    {
        return $this->lastCost;
    }

	/**
	* @brief 获取本对象至今的总耗时
	*
	* @return 
	*/
    public function getTotalCost()
    {
        return $this->totalCost;
    }

	/**
	* @brief 获取profiling数据
	*
	* @return 
	*/
    public function getProfilingData()
    {
        return $this->arrCost;
    }

	/**
	* @brief 清除profiling数据
	*
	* @return 
	*/
    public function cleanProfilingData()
    {
        $this->arrCost = NULL;
    }


    //////////////////////////// transaction ////////////////////////////

	/**
	* @brief 设置或查询当前自动提交状态
	*
	* @param $bolAuto NULL返回当前状态，其他设置当前状态
	*
	* @return 
	*/
    public function autoCommit($bolAuto = NULL)
    {
        if($bolAuto === NULL)
        {
            $sql = 'SELECT @@autocommit';
            $res = $this->query($sql);
            if($res === false)
            {
                return NULL;
            }
            return $res[0]['@@autocommit'] == '1';
        }

        return $this->mysql->autocommit($bolAuto);
    }

	/**
	* @brief 开始事务
	*
	* @return 
	*/
    public function startTransaction()
    {
        $sql = 'START TRANSACTION';
        return $this->query($sql,false,true);
    }

	/**
	* @brief 提交事务
	*
	* @return 
	*/
    public function commit()
    {
        $ret = $this->mysql->commit();
		$arrInfo = array(
			'ns'        => $this->dbConf['dbname'],
			'query'     => $query,
			'retry'     => 1,
			'local_ip'  => $_SERVER['SERVER_ADDR'],
			'remote_ip' => $this->dbConf['host'].':'.$this->dbConf['port'],
			'res_len'   => $ret,
		);
		return $ret;
    }

	/**
	* @brief 回滚
	*
	* @return 
	*/
    public function rollback()
	{
        $ret = $this->mysql->rollback();
		$arrInfo = array(
			'ns'        => $this->dbConf['dbname'],
			'query'     => $query,
			'retry'     => 1,
			'local_ip'  => $_SERVER['SERVER_ADDR'],
			'remote_ip' => $this->dbConf['host'].':'.$this->dbConf['port'],
			'res_len'   => $ret,
		);
		return $ret;
    }

    //////////////////////////// util ////////////////////////////

	/**
	* @brief 选择db
	*
	* @param $dbname 数据库名
	*
	* @return 
	*/
    public function selectDB($dbname)
    {
        if($this->mysql->select_db($dbname))
        {
            $this->dbConf['dbname'] = $dbname;
            return true;
        }
        return false;
    }

	/**
	* @brief 获取当前db中存在的表
	*
	* @param $pattern 表名Pattern
	* @param $dbname 数据库
	*
	* @return 
	*/
    public function getTables($pattern = NULL, $dbname = NULL)
    {
        $sql = 'SHOW TABLES';
        if($dbname !== NULL)
        {
            $sql .= ' FROM '.$this->escapeString($dbname);
        }
        if($pattern !== NULL)
        {
            $sql .= ' LIKE \''.$this->escapeString($pattern).'\'';
        }

        if(!($res = $this->query($sql, false)))
        {
            return false;
        }

        $ret = array();
        while($row = $res->fetch_row())
        {
            $ret[] = $row[0];
        }
        $res->free();
        return $ret;
    }

	/**
	* @brief 检查数据表是否存在
	*
	* @param $name 表名
	* @param $dbname 数据库名
	*
	* @return 
	*/
    public function isTableExists($name, $dbname = NULL)
    {
        $tables = $this->getTables($name, $dbname);
        if($tables === false)
        {
            return NULL;
        }
        return count($tables) > 0;
    }

	/**
	* @brief 设置和查询当前连接的字符集
	*
	* @param $name NULL表示查询，字符串表示设置
	*
	* @return 
	*/
    public function charset($name = NULL)
    {
        if($name === NULL)
        {
            return $this->mysql->character_set_name();
        }
        return $this->mysql->set_charset($name);
    }

	/**
	* @brief 获取连接参数
	*
	* @return 
	*/
    public function getConnConf()
    {
        if($this->dbConf == NULL)
        {
            return NULL;
        }

        return array(
            'host' => $this->dbConf['host'],
            'port' => $this->dbConf['port'],
            'uname' => $this->dbConf['uname'],
            'dbname' => $this->dbConf['dbname']
            );
    }

    //////////////////////////// error ////////////////////////////

	/**
	* @brief 获取当前mysqli错误码
	*
	* @return 
	*/
    public function errno()
    {
        return $this->mysql->errno;
    }

	/**
	* @brief 获取当前mysqli错误描述
	*
	* @return 
	*/
    public function error()
    {
        return $this->mysql->error;
    }
	/**
	* @brief 获取db库错误码
	*
	* @return 
	*/
	public function getErrno()
	{
		return $this->_error['errno'];
	}
	/**
	* @brief 获取db库错误描述
	*
	* @return 
	*/
	public function getError()
	{
		return $this->_error['error'];
	}
}
