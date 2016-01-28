<?php
/**
 * @file Filter.php
 * @author orp(zhaopengwei@baidu.com)
 * @date
 * @version $Revision$
 * @brief
 *  参数校验
 **/
namespace Storehouse\Libs\Db;

use \Storehouse\Libs\Error;
use \Storehouse\Libs\ErrorCodes;

class Filter
{
    const PARAM_TYPE = 'type';
    const PARAM_ARRTYPE = 'arrType';
    const PARAM_INT_MIN = 'min';
    const PARAM_INT_MAX = 'max';
    const PARAM_INT_DEFAULT = 'intDefault';
    const PARAM_STR_DEFAULT = 'strDefault';
    const PARAM_ARR_DEFAULT = 'arrDefault';
    const PARAM_DEFAULT = 'default';
    const PARAM_STR_ACTION = 'strAction';
    const PARAM_STR_TRIM = 'trim';
    const PARAM_REG = 'pattern';

    const TYPE_INT = 'int';
    const TYPE_STRING = 'string';
    const TYPE_ARR = 'array';
    const TYPE_DOUBLE = 'double';
    const TYPE_REG = 'reg';

    const TBL_KEY = 'key';
    const OPTIONAL = 'optional';

    const LOG_LEVEL = Error::TRACE;

    public static $FUNC = array(
        self::TYPE_INT => '_filterInteger',
        self::TYPE_STRING => '_filterString',
        self::TYPE_DOUBLE => '_filterDouble',
        self::TYPE_ARR => '_filterArray',
        self::TYPE_REG => '_filterReg',
    );

    public static function check($arrParamField, $arrFuncField, $arrMap = null)
    {
        if (is_null($arrParamField)) //为null用于查全表
        {
            return  $arrParamField;
        }

        if(is_null($arrFuncField))
        {
            throw new Error(ErrorCodes::PARAM_ERROR, $arrParamField);
        }

        foreach($arrParamField as $strParamName => $value)
        {
            //输入非法参数
            if(!array_key_exists($strParamName, $arrFuncField))
            {
                throw new Error(ErrorCodes::PARAM_ERROR, 
                	array('key' => $strParamName, 'conf' => json_encode($arrFuncField)));
            }

            $arrFuncConfig = $arrFuncField[$strParamName];

            switch($arrFuncConfig[self::PARAM_TYPE])
            {
            case self::TYPE_INT:
                self::_filterInteger($value, $arrFuncConfig);
                break;
            case self::TYPE_STRING:
                self::_filterString($value, $arrFuncConfig);
                break;
            case self::TYPE_REG:
                self::_filterReg($value, $arrFuncConfig);
                break;
            default :
                break;
            }
            if(isset($arrFuncConfig[self::TBL_KEY])) {
                $key = strval($arrFuncConfig[self::TBL_KEY]);
                $arrParamField[$key] = $arrParamField[$strParamName];
                if($key !== $strParamName){
                    unset($arrParamField[$strParamName]);
                }
            }elseif($arrMap !== NULL && isset($arrMap[$strParamName])){
                $key = strval($arrMap[$strParamName]);
                $arrParamField[$key] = $arrParamField[$strParamName];
                if($key !== $strParamName){
                    unset($arrParamField[$strParamName]);
                }
            }
        }
        return $arrParamField;
    }

    public static function map($arrParams, $arrFuncField)
    {
        if(is_null($arrParams))
        {
            throw new Error(ErrorCodes::PARAM_ERROR);
        }
        if(is_null($arrFuncField) || empty($arrFuncField)){
            return $arrParams;
        }
        $arrParamField = array();
        foreach($arrParams as $strParamName){
            if(isset($arrFuncField[$strParamName])){
                $arrParamField[] = $arrFuncField[$strParamName]. " as $strParamName";
            }
            else{
                $arrParamField[] = $strParamName;
            }
        }
        return $arrParamField;
    }

    public static function filter(&$arrParams, $arrFilterConfig) {
        if(is_null($arrParams) || is_null($arrFilterConfig)) {
            throw new Error(ErrorCodes::PARAM_ERROR);
        }

        foreach($arrFilterConfig as $strFilterName => $arrFilterConf) {
            if(!array_key_exists($strFilterName, $arrParams)) {
                $paramValue = self::_filterNullParam($arrFilterConf, $strFilterName);
            } else {
                $paramValue = $arrParams[$strFilterName];
            }

            if ($paramValue !== false) {
                if (isset(self::$FUNC[$arrFilterConf[self::PARAM_TYPE]])) {
                    $func_name =
                        self::$FUNC[$arrFilterConf[self::PARAM_TYPE]];
                    $paramValue =
                        self::$func_name($paramValue, $arrFilterConf);
                }

                $arrParams[$strFilterName] = $paramValue;
            }
        }

        foreach($arrParams as $strParamName => $objParamValue) {
            if(!array_key_exists($strParamName,$arrFilterConfig)) {
                unset($arrParams[$strParamName]);
            }
        }
    }

    private static function _filterInteger($intParamValue, $arrConf)
    {
        //if (!preg_match('|^[0-9]+$|i', $intParamValue)) {
        //        throw new Error(ErrorCodes::PARAM_ERROR, $intParamValue);
        //}
        $intParamValue = intval($intParamValue);

        if(array_key_exists(self::PARAM_INT_MIN, $arrConf))
        {
            if($intParamValue < $arrConf[self::PARAM_INT_MIN])
            {
                throw new Error(ErrorCodes::PARAM_ERROR,
                    $arrConf, self::LOG_LEVEL);
            }
        }

        if(array_key_exists(self::PARAM_INT_MAX, $arrConf))
        {
            if($intParamValue > $arrConf[self::PARAM_INT_MAX])
            {
                throw new Error(ErrorCodes::PARAM_ERROR,
                    $arrConf, self::LOG_LEVEL);
            }
        }

        return $intParamValue;
    }

    private static function _filterReg($regParamValue, $arrConf) {
        if (isset($arrConf[self::PARAM_REG])) {
            if (preg_match($arrConf[self::PARAM_REG], $regParamValue)) {
                return $regParamValue;
            } else {
                throw new Error(ErrorCodes::PARAM_ERROR,
                    $arrConf, self::LOG_LEVEL);
            }
        }

        return $regParamValue;
    }

    private static function _filterString($strParamValue, $arrConf) {
        if(array_key_exists(self::PARAM_STR_ACTION,$arrConf)) {
            foreach($arrConf[self::PARAM_STR_ACTION] as $key => $func) {
                $strParamValue = call_user_func($func, $strParamValue);
            }
        } else {
            if (isset($arrConf[self::PARAM_STR_TRIM])
                && intval($arrConf[self::PARAM_STR_TRIM]) === 1) {
                    $strParamValue = trim($strParamValue);
                }

            if (isset($arrConf[self::PARAM_INT_MIN])) {
                if (strlen($strParamValue) < $arrConf[self::PARAM_INT_MIN]) {
                    throw new Error(ErrorCodes::PARAM_ERROR,
                        $arrConf, self::LOG_LEVEL);
                }
            }

            if (isset($arrConf[self::PARAM_INT_MAX])) {
                if (strlen($strParamValue) > $arrConf[self::PARAM_INT_MAX]) {
                    throw new Error(ErrorCodes::PARAM_ERROR,
                        $arrConf, self::LOG_LEVEL);
                }
            }
        }

        return $strParamValue;
    }

    private static function _filterDouble($strParamValue, $arrConf) {
        $strParamValue = floatval($strParamValue);
        if (isset($arrConf[self::PARAM_INT_MIN])) {
            if ($strParamValue < $arrConf[self::PARAM_INT_MIN]) {
                throw new Error(ErrorCodes::PARAM_ERROR,
                    $arrConf, self::LOG_LEVEL);
            }
        }

        if (isset($arrConf[self::PARAM_INT_MAX])) {
            if ($strParamValue > $arrConf[self::PARAM_INT_MAX]) {
                throw new Error(ErrorCodes::PARAM_ERROR,
                    $arrConf, self::LOG_LEVEL);
            }
        }
        return $strParamValue;
    }

    private static function _filterArray($arrParamValue, $arrConf)
    {
        if(array_key_exists(self::PARAM_ARRTYPE, $arrConf))
        {
            foreach($arrParamValue as $key => $value)
            {
                switch($arrConf[self::PARAM_ARRTYPE][self::PARAM_TYPE])
                {
                case self::TYPE_INT :
                    $intParamValue = self::_filterInteger($value, $arrConf[self::PARAM_ARRTYPE]);
                    $arrParamValue[$key] = $intParamValue;
                    break;
                case self::TYPE_STRING :
                    $strParamValue = self::_filterString($value, $arrConf[self::PARAM_ARRTYPE]);
                    $arrParamValue[$key] = $strParamValue;
                    break;
                default :
                    break;
                }   
            }
        }

        return $arrParamValue;
    }

    private static function _filterNullParam($arrFilterConf, $filterName)
    {
        $objParamDefalut = '';

		$arrFilterConf['key'] = $filterName;

        switch($arrFilterConf[self::PARAM_TYPE])
        {
        case self::TYPE_INT :
            $objParamDefault = self::PARAM_INT_DEFAULT;
            break;
        case self::TYPE_STRING :
            $objParamDefault = self::PARAM_STR_DEFAULT;
            break;
        case self::TYPE_ARR : 
            $objParamDefault = self::PARAM_ARR_DEFAULT;
            break;
        case self::TYPE_REG:
        case self::TYPE_DOUBLE:
            $objParamDefault = self::PARAM_DEFAULT;
            break;
        default :
            break;
        }

        if (isset($arrFilterConf[self::OPTIONAL])
            && intval($arrFilterConf[self::OPTIONAL]) === 1) {
                if (!isset($objParamDefault, $arrFilterConf)) {
                    return false;
                }
            }

        //没有设置默认值
        if(!array_key_exists($objParamDefault, $arrFilterConf))
        {
            throw new Error(ErrorCodes::PARAM_ERROR,
                $arrFilterConf, self::LOG_LEVEL);
        }
        elseif(is_null($arrFilterConf[$objParamDefault]))
        {
            throw new Error(ErrorCodes::PARAM_ERROR,
                $arrFilterConf, self::LOG_LEVEL);
        }
        else
        {
            return $objParamValue = $arrFilterConf[$objParamDefault];
        }
    }
}
