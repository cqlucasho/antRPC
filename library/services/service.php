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
            case self::OPERATION_FETCH:
                return self::fetch($params['sign']);
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
     * @see IService::fetch()
     */
    public static function fetch(&$sign) {
        $md5Name = md5($sign);
        echo "------------md5Name-------------\n";
        print_r($md5Name);
        echo "#######";
        print_r(self::$_service_map[$md5Name]);
        echo "\n------------md5Name-------------\n";
        if(isset(self::$_service_map[$md5Name])) {
            $services = self::$_service_map[$md5Name];

            # 判断服务是否可用, 并作故障转移调用另一台可用服务器
            $service = self::_check($services);
            if(!empty($service)) return $service;

            return false;
        }

        return false;
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
     * 检测服务是否可用
     *
     * @param array $services 服务列表
     * @return bool|array
     */
    protected static function _check(array $services) {
        if(empty($services)) return false;

        $service = array();
        foreach($services as $address => $value) {
            if(!$value || (!$value['state'] && !$value['flag'])) continue;

            $service = array('address' => $address);
            break;
        }

        return $service;
    }

    /**
     * 服务集合
     * @var array $_service_map
     */
    public static $_service_map = array();
}