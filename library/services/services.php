<?php
Ant::import('library.services.i_service');
abstract class AServices {
    /**
     * 返回结果
     *
     * @param string $receData 接收到的数据
     * @param string $data 需要发送的原数据
     * @param string $error_info 错误描述信息
     * @param string $error_status 错误状态码
     * @return string
     */
    protected static function result($receData, $data, $error_info = 'Not Found', $error_status = '404') {
        if(!empty($receData)) {
            $result = json_decode($receData);
            if(is_object($result) && $result->status === 200) {
                return $receData;
            }
        }

        return json_encode(array('status' => $error_status, 'type' => $data['type'], 'op' => $data['option'], 'error' => $error_info));
    }
}
/**
 * 服务管理类, 主要用于服务管理操作.
 *
 * @author lucasho
 * @created 2017-03-09
 * @modified 2017-03-09
 * @version 1.0
 * @link http://github.com/cqlucasho
 */
class Services extends AServices {
    /**
     * 初始化socket连接
     *
     * @param string $url
     * @throws Exception
     */
    public function __construct($url) {
        $this->_socket = stream_socket_client($url, $errno, $errstr, 5);
        if(!$this->_socket) {
            throw new Exception("$errstr ($errno)");
        }
    }

    /**
     * 判断服务是否可用
     *
     * @param string $sign 唯一标识符
     * @param array $params 参数信息
     * @param string $type 操作类型
     * @return string
     * @throws Exception
     */
    public function check($sign, $params = array(), $type = IService::SERVICE_TYPE_SERVICE) {
        $data = array('type' => $type, 'option' => IService::OPERATION_CHECK, 'sign' => $sign, 'params' => $params);

        return $this->_read($data);
    }

    /**
     * 获取服务
     *
     * @param string $sign 唯一标识符
     * @param string $type 操作类型
     * @return string
     * @throws Exception
     */
    public function fetch($sign, $type = IService::SERVICE_TYPE_SERVICE) {
        $data = array('type' => $type, 'option' => IService::OPERATION_FETCH, 'sign' => $sign);

        return $this->_read($data);
    }

    /**
     * 注册服务
     *
     * @param string $sign 唯一标识符
     * @param array $params 参数信息
     * @param string $type 操作类型
     * @return string
     * @throws Exception
     */
    public function register($sign, $params = array(), $type = IService::SERVICE_TYPE_SERVICE) {
        $data = json_encode(array('type' => $type, 'option' => IService::OPERATION_ADD, 'sign' => $sign, 'params' => $params)).';';

        return $this->_write($data);
    }

    /**
     * 注销服务
     *
     * @param string $sign 唯一标识符
     * @param array $params 参数信息
     * @param string $type 操作类型
     * @return string
     * @throws Exception
     */
    public function unregister($sign, $params = array(), $type = IService::SERVICE_TYPE_SERVICE) {
        $data = json_encode(array('type' => $type, 'option' => IService::OPERATION_DELETE, 'sign' => $sign, 'params' => $params));

        return $this->_write($data);
    }

    /**
     * 获取socket句柄
     *
     * @return bool
     */
    public function getSocket() {
        if(!is_null($this->_socket)) return $this->_socket; else return false;
    }

    /**
     * 关闭socket连接
     *
     * @return bool
     */
    public function close() {
        if(!empty($this->_socket)) return fclose($this->_socket); else return false;
    }

    /**
     * 读取数据
     *
     * @param string $data 请求数据
     * @return mixed|string
     * @throws Exception
     */
    protected function _read($data) {
        $encodeData = json_encode($data);
        $len = fwrite($this->_socket, $encodeData);
        if($len) {
            while($this->_received_data = fread($this->_socket, AConn::RECEIVE_READ_SIZE)) {
                $this->close();
                return self::result($this->_received_data, $data);
            }

            $this->close();
        }

        throw new Exception('接收服务请求结果失败');
    }

    /**
     * 写入数据, 只返回成功或者失败.
     *
     * @param string $data 请求数据
     * @return bool
     */
    protected function _write($data) {
        $len = fwrite($this->_socket, $data);
        switch ($len) {
            case strlen($data):
            case $len > 0:
                return true;
            default:
                return false;
        }
    }

    /**
     * 微服务服务器请求地址
     * @var string $_address
     */
    public $address = '';

    /**
     * 接收返回数据
     * @var string $_received_data
     */
    protected $_received_data = '';
    
    /**
     * socket句柄
     * @var null|resource $socket
     */
    protected $_socket = null;
}