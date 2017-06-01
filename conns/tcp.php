<?php
Ant::import('conns.a_conn');

/**
 * tcp协议类
 *
 * @author lucasho
 * @created 2017-01-19
 * @modified 2017-02-14
 * @version 1.1
 * @link http://github.com/cqlucasho
 */
class Tcp extends AConn {
    public function __construct($accept, $worker) {
        $this->_id          = Server::$conn_counter;
        $this->_protocol    = Server::$protocol;
        $this->_worker      = $worker;
        $this->_socket      = $accept;

        stream_set_blocking($this->_socket, 0);
        if (function_exists('stream_set_read_buffer')) {
            stream_set_read_buffer($this->_socket, 0);
        }
    }

    /**
     * 运行
     */
    public function run() {
        try {
            call_user_func($this->_worker->onConnected, null);
            $this->conn_status = AConn::STATUS_CONNECTED;
        } catch (Exception $ex) {
            Server::log($ex->getMessage());
            exit(1);
        }

        Server::$event->add($this->_socket, EV_READ, array($this, 'read'));
    }

    /**
     * 读取客户端信息
     */
    public function read($socket) {
        $this->_received_data = fread($socket, self::RECEIVE_READ_SIZE);
        if($this->_received_data === '' || $this->_received_data === false || !is_resource($this->_socket)) {
            $this->destroy();
            return;
        }

        # 调用onMessage方法
        try {
            $this->_received_data = call_user_func($this->_worker->onMessage, $this->_received_data);
        } catch (Exception $ex) {
            Server::log($ex->getMessage());
            exit(1);
        }

        # 是否使用其它协议
        if($this->_useProtocol()) {
            return;
        } else {
            Server::$event->add($socket, EV_WRITE, array($this, 'write'));
        }
    }

    /**
     * 发送数据
     */
    public function write() {
        try {
            $this->conn_status = AConn::STATUS_ESTABLISHED;
            $this->send_data = call_user_func($this->_worker->onSend, $this->_received_data);
        } catch (Exception $e) {
            Server::log($e);
            exit(1);
        }

        $len = fwrite($this->_socket, $this->send_data, strlen($this->send_data));
        switch($len) {
            case strlen($this->send_data):
            case $len > 0:
                Server::$event->delete($this->_socket, EV_WRITE);
                /*if ($this->conn_status === AConn::STATUS_CLOSING) {
                    $this->destroy();
                }
                break;*/
            default: {
                $this->conn_status = AConn::STATUS_CLOSING;
                $this->destroy();
            }
        }
    }

    /**
     * 发送数据, 主要用于其它基于tcp的连接协议的数据发送.
     */
    public function send() {
        switch ($this->conn_status) {
            case self::STATUS_HASKSHAKE:
                break;
            case self::STATUS_CONNECTED: {
                try {
                    $this->send_data = call_user_func($this->_worker->onConnected, null);
                } catch (Exception $e) {
                    Server::log($e);
                    exit(1);
                }

                break;
            }
            case self::STATUS_ESTABLISHED: {
                try {
                    $this->send_data = call_user_func($this->_worker->onSend, $this->send_data);
                } catch (Exception $e) {
                    Server::log($e);
                    exit(1);
                }

                break;
            }
            case self::STATUS_CLOSING:
            case self::STATUS_CLOSED:
                return false;
            default:
                break;
        }

        if(!empty($this->send_data)) {
            $len = stream_socket_sendto($this->_socket, $this->_protocol->encode($this->send_data));
            if($len > 0) {
                $this->send_data = '';
                return true;
            }
        }

        $this->conn_status = AConn::STATUS_CLOSING;
        $this->destroy();
        return false;
    }

    /**
     * 重新设置接收数据
     */
    public function setRecevicedData($headerLength) {
        $this->_received_data = mb_substr($this->_received_data, $headerLength);
    }

    /**
     * 释放相关信息
     */
    public function destroy() {
        if($this->conn_status !== AConn::STATUS_CLOSING) return false;

        # 删除事件监听
        Server::$event->delete($this->_socket, EV_READ);
        Server::$event->delete($this->_socket, EV_WRITE);

        # 更新记数
        Server::$conn_counter--;
        unset($this->_worker->conn_quence[$this->_id]);

        # 更新状态
        $this->conn_status = AConn::STATUS_CLOSED;

        //$this->_worker->onConnected = $this->_worker->onSend = null;
        $this->send_data = $this->_received_data = '';
        @fclose($this->_socket);
    }

    /**
     * 判断是否使用通信协议
     */
    protected function _useProtocol() {
        if($this->_protocol) {
            if($this->_received_data !== '') {
                $this->_protocol->initial($this->_received_data, $this);
            }

            # 清空数据
            $this->_received_data = '';
            $this->send_data = '';
            return true;
        }

        return false;
    }

}