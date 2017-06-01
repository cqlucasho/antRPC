<?php
/**
 * 上下文管理
 *
 * @author lucasho
 * @created 2017-02-23
 * @modified 2017-02-23
 * @version 1.0
 * @link http://github.com/cqlucasho
 */
class Context implements IService {
    /**
     * 执行操作
     *
     * @param array $params 参数
     * @return bool|mixed
     */
    public static function operation($params) {
        switch ($params['option']) {
            case self::OPERATION_FETCH:
                return self::fetchService($params['sign']);
            case self::OPERATION_ADD:
                return self::register($params);
            case self::OPERATION_DELETE:
                return self::unregister($params['sign']);
            default:
                break;
        }

        return false;
    }

    /**
     * @see IService::fetchService()
     */
    public static function fetchService(&$sign, $default = false) {
        $md5Name = md5($sign);
        print_r(self::$_list[$md5Name]);
        return isset(self::$_list[$md5Name]) ? self::$_list[$md5Name] : $default;
    }

    /**
     * @see IService::register()
     */
    public static function register(array $params) {
        $md5Name = md5($params['sign']);

        if(!isset(self::$_list[$md5Name])) {
            self::$_list[$md5Name] = $params['params'];
            return true;
        }

        return false;
    }

    /**
     * @see IService::unregister()
     */
    public static function unregister(&$sign) {
        $md5Name = md5($sign);

        if(isset(self::$_list[$md5Name])) {
            self::$_list[$md5Name] = null;
            unset(self::$_list[$md5Name]);

            return true;
        }

        return false;
    }

    /**
     * 上下文管理表
     * @var array $_list
     */
    protected static $_list = array();
}