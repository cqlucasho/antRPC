<?php
/**
 * 服务注册中心接口
 *
 * @author lucasho
 * @created 2017-01-18
 * @modified 2017-01-18
 * @version 1.0
 * @link http://github.com/cqlucasho
 */
interface IService {
    /**
     * 注册服务
     *
     * @param array $params 相关描述信息
     * @return mixed
     */
    static function register(array $params);

    /**
     * 注销已注册服务
     *
     * @param string $sign 唯一标识符
     * @return mixed
     */
    static function unregister(&$sign);

    /**
     * 获取注册服务
     *
     * @param string $sign 唯一标识符
     * @param bool $default 默认值
     * @return mixed
     */
    static function fetchService(&$sign, $default = false);

    /**
     * 服务类型
     */
    const SERVICE_TYPE_SERVICE = 'service';
    const SERVICE_TYPE_CONTEXT = 'context';

    /**
     * 操作
     */
    const OPERATION_CHECK   = 'check';
    const OPERATION_FETCH   = 'fetch';
    const OPERATION_ADD     = 'add';
    const OPERATION_DELETE  = 'delete';
}
