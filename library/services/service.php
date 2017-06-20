<?php
/**
 * 服务注册中心类, 主要用于管理已注册服务的信息.
 *
 * @author lucasho
 * @created 2017-01-18
 * @modified 2017-06-20
 * @version 1.1
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
        $shID = self::_open($sign);
        $shmData = shmop_read($shID, 0, shmop_size($shID));

        if($shmData) {
            # 判断服务是否可用, 故障转移调用另一台可用服务器
            $service = self::_check($shmData);
            if(!empty($service)) return $service;

            return false;
        }

        return false;
    }

    /**
     * @see IService::register()
     */
    public static function register(array $params) {
        $shID = self::_open($params['sign']);
        if($byteNum = shmop_write($shID, serialize($params['params']), 0)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @see IService::unregister()
     */
    public static function unregister(&$sign) {
        $shID = self::_open($sign);
        if($flag = shmop_delete($shID)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 检测服务是否可用
     *
     * @param int $shmData 配置信息
     * @return bool|array
     */
    protected static function _check(&$shmData) {
        $datas = unserialize($shmData);

        $service = array();
        foreach($datas as $address => $value) {
            if(!$value || (!$value['state'] && !$value['flag'])) continue;

            $service = array('address' => $address);
            break;
        }

        return $service;
    }

    /**
     * 打开
     *
     * @param string $shid 内存段id
     * @return int
     */
    protected static function _open(&$shid) {
        $shmID = shmop_open(self::_getSystemIpcID($shid), "c", 0755, 1024);

        return $shmID;
    }

    /**
     * system v ipc key
     *
     * @param string $key
     * @return int|string
     */
    protected static function _getSystemIpcID(&$key) {
        $key = crc32($key);

        return sprintf('%u', ($key & 0xffff) | (($key & 0xff) << 16));
    }
}