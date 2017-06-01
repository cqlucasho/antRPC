<?php
/**
 * 服务注册中心类, 主要用于管理已注册服务的信息.
 *
 * @author lucasho
 * @created 2017-01-18
 * @modified 2017-01-18
 * @version 1.0
 * @link http://github.com/cqlucasho
 */
class Service implements IService {
    /**
     * 执行操作
     *
     * @param array $params 参数
     * @return bool|mixed
     */
    public static function operation($params) {
        switch ($params['option']) {
            case self::OPERATION_CHECK:
                return self::checkService($params['sign']);
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
     * 检测服务是否可用
     *
     * @param string $sign 服务名称
     * @return bool
     */
    public static function checkService(&$sign) {
        return self::fetchService($sign) ? self::fetchService($sign)['flag'] : false;
    }

    /**
     * @see IService::fetchService()
     */
    public static function fetchService(&$sign, $default = false) {
        $md5Name = md5($sign);
        return isset(self::$_service_map[$md5Name]) ? self::$_service_map[$md5Name] : $default;
    }

    /**
     * @see IService::register()
     */
    public static function register(array $params) {
        $md5Name = md5($params['sign']);

        if(!isset(self::$_service_map[$md5Name])) {
            self::$_service_map[$md5Name] = $params['params'];
            return true;
        }

        return false;
    }

    /**
     * @see IService::unregister()
     */
    public static function unregister(&$sign) {
        $md5Name = md5($sign);

        if(isset(self::$_service_map[$md5Name])) {
            self::$_service_map[$md5Name] = null;
            unset(self::$_service_map[$md5Name]);

            return true;
        }

        return false;
    }

    /**
     * 服务集合
     * @var array $_service_map
     */
    public static $_service_map = array();
}