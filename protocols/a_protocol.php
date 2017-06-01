<?php
/**
 * 连接协议抽象类
 *
 * @author lucasho
 * @created 2017-02-21
 * @modified 2017-02-21
 * @version 1.0
 * @link http://github.com/cqlucasho
 */
abstract class AProtocol {
    /**
     * 初始化数据
     *
     * @param string $receiveData 接收到的数据
     * @param object $conn 连接类型对象
     * @return mixed
     */
    abstract public function initial($receiveData, &$conn);

    /**
     * 解析数据
     *
     * @return mixed
     */
    abstract public function decode($data);

    /**
     * 编码数据
     *
     * @return mixed
     */
    abstract public function encode($data);

    /**
     * 连接对象
     * @var null|object $conn
     */
    public $conn = null;
}