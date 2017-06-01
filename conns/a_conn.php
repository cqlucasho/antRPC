<?php
/**
 * 连接抽象类
 *
 * @author lucasho
 * @created 2017-01-19
 * @modified 2017-01-19
 * @version 1.0
 * @link http://github.com/cqlucasho
 */
abstract class AConn {
    /**
     * 运行
     */
    abstract protected function run();

    /**
     * 读取
     */
    abstract protected function read($socket);

    /**
     * 写回
     */
    abstract protected function write();

    /**
     * 释放
     */
    abstract protected function destroy();


    /**
     * 当前连接id
     * @var int $id
     */
    protected $_id = 0;

    /**
     * worker对象
     * @var null|resource $_worker
     */
    protected $_worker = null;

    /**
     * accept socket
     * @var null|resource $_socket
     */
    protected $_socket = null;

    /**
     * 协议对象
     * @var null|resource $_protocol
     */
    protected $_protocol = null;

    /**
     * io多路复用对象
     * @var null|resource $io_event
     */
    protected $_event = null;

    /**
     * 接收到的数据
     * @var string $_received_data
     */
    protected $_received_data = '';
    /**
     * 发送的数据
     * @var string $send_data
     */
    public $send_data = '';

    /**
     * 当前包长度
     * @var string $_current_package_length
     */
    protected $_current_package_length = '';

    /**
     * 是否暂停接收数据
     * @var bool $_is_paused
     */
    protected $_is_paused = false;

    /**
     * 读取接收数据大小
     */
    const RECEIVE_READ_SIZE = 65535;

    /**
     * 连接状态
     */
    const STATUS_HASKSHAKE      = 1;
    const STATUS_CONNECTED      = 2;
    const STATUS_ESTABLISHED    = 3;
    const STATUS_CLOSING        = 4;
    const STATUS_CLOSED         = 5;
    /**
     * 当前连接状态
     * @var int $status
     */
    public $conn_status = self::STATUS_HASKSHAKE;
}