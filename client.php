<?php
/**
 * 客户端类, 主要用于客户端请求服务处理业务逻辑.
 *
 * @author lucasho
 * @created 2017-01-18
 * @modified 2017-05-30
 * @version 1.0
 * @link http://github.com/cqlucasho
 */
class Client {
    public function __construct() {
        $this->_loadClass();
    }

    /**
     * 调用服务
     *
     * @param string $name
     * @param array $arguments
     * @return string
     */
    public function __call($name, $arguments) {
        $this->func_name = $name;
        $this->func_arguments = $arguments;

        try {
            # 检测服务是否可用, 并作故障转移
            $this->_services = new Services($this->_services_address);
            $data = $this->_services->fetch($this->func_name);
            $result = json_decode($data);
            if($result->status === 200) {
                $this->_createClient($result->result->address);

                return $this->_connect();
            }

            return $data;
        }
        catch (Exception $e) {
            echo 'Exception: ',$e->getFile(),'(Line:',$e->getLine(),'): ',$e->getMessage(),"<br/>";
        }
    }

    /**
     * 设置注册服务中心所在的服务器地址
     *
     * @param string $address 地址
     */
    public function setServiceServer($address) {
        $this->_services_address = $address;
    }

    /**
     * 创建客户端socket句柄
     *
     * @param string $address 服务所在的服务器地址
     * @throws Exception
     */
    protected function _createClient($address) {
        $this->_socket = stream_socket_client($address, $errno, $errstr, 0);
        if(!$this->_socket) {
            throw new Exception("$errstr ($errno)");
        }
    }

    /**
     * 连接服务器并发送请求数据
     *
     * @return string
     * @throws Exception
     */
    protected function _connect() {
        # 上下文管理
        $context = array('requestID' => uniqid(), 'func' => $this->func_name, 'params' => $this->func_arguments);
        /*$this->_services->register($context['requestID'], $context, IService::SERVICE_TYPE_CONTEXT);*/

        # 序列化数据
        $data = json_encode($context);
        if(strlen($data) > self::MAX_SEND_DATA) {
            throw new Exception('数据超出最大发送长度');
        }

        # 发送数据
        fwrite($this->_socket, $data);
        while($receivedData = fread($this->_socket, self::RECEIVE_READ_SIZE)) {
            if(!empty($receivedData)) {
                fclose($this->_socket);
                return $receivedData;
            }
        }
    }

    /**
     * 加载其它相关类
     *
     * @throws Exception
     */
    protected function _loadClass() {
        require_once('library/ant.php');
        Ant::import('conns.tcp');
        Ant::import('library.services.services');
    }


    /**
     * 客户端socket
     * @var null|object $_socket
     */
    protected $_socket = null;
    /**
     * 服务管理对象
     * @var null|object $_services
     */
    protected $_services = null;
    /**
     * 服务管理地址列表
     * @var string $_services_address
     */
    protected $_services_address = '';

    /**
     * 方法调用名称
     * @var string $_call_name
     */
    public $func_name = '';
    /**
     * 方法调用参数
     * @var array $_call_arguments
     */
    public $func_arguments = null;

    /**
     * 微服务服务器请求地址
     * @var string $_address
     */
    public $_micro_service_address = '';
    /**
     * 微服务管理地址列表
     * @var array $_micro_service_server_list
     */
    protected $_micro_service_server_list = array();

    /**
     * 最大发送/接收数据大小
     */
    const MAX_SEND_DATA = 1048576;
    const RECEIVE_READ_SIZE = 65535;
}