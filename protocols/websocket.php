<?php
Ant::import('protocols.a_protocol');

/**
 * websocket通信协议
 *
 * @author lucasho
 * @created 2017-02-19
 * @modified 2017-02-19
 * @version 1.0
 * @link http://github.com/cqlucasho
 */
class Websocket extends AProtocol {
    /**
     * 初始化
     *
     * @param string $receiveData 接收到的数据
     * @param object $conn 连接对象
     * @return bool
     */
    public function initial($receiveData, &$conn) {
        $this->conn = $conn;
        # 判断接收数据是否有效
        if (($receiveLength = strlen($receiveData)) < 2) {
            return false;
        }

        # 判断是否已经握手连接
        if (!$this->handshake) {
            if($this->connHandshake($receiveData)) {
                $this->conn->conn_status = AConn::STATUS_ESTABLISHED;
                return true;
            } else {
                return false;
            }
        }
        else {
            # 判断是否已建立连接
            if($this->conn->conn_status !== AConn::STATUS_ESTABLISHED) {
                $this->conn->destroy();
                return false;
            }

            # 解析数据
            $decodeData = $this->decode($receiveData);
            if($decodeData) {
                # 响应数据发送
                $this->conn->send();
            }
            else {
                return false;
            }
        }
    }

    /**
     * 建立websocket连接
     *
     * @param string $data 接收数据
     * @param object $conn 连接对象
     * @return bool
     */
    public function connHandshake($data) {
        # 获取请求内容数据
        $headerLength = mb_strpos($data, "\r\n\r\n") + 4;

        if(mb_strpos($data, 'GET') === 0) {
            if(preg_match("/Sec-WebSocket-Key: *(.*?)\r\n/i", $data, $matches)) {
                $secWebsocketKey = $matches[1];
            } else {
                $this->conn->send("HTTP/1.1 400 Bad Request\r\n\r\n<b>400 Bad Request</b><br>Sec-WebSocket-Key not found.<br>This is a WebSocket service and can not be accessed via HTTP.", true);
                $this->conn->destroy();
                return false;
            }

            if($secWebsocketKey) {
                $responseSecKey = base64_encode(sha1($secWebsocketKey . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));
                $response = "HTTP/1.1 101 Switching Protocols\r\n";
                $response .= "Upgrade: websocket\r\n";
                $response .= "Sec-WebSocket-Version: 13\r\n";
                $response .= "Connection: Upgrade\r\n";
                $response .= "Server: antrpc/" . Server::VERSION . "\r\n";
                $response .= "Sec-WebSocket-Accept: " . $responseSecKey . "\r\n\r\n";
                $this->conn->send_data = $response;
                # 更改握手状态
                $this->handshake = true;
                # 选择解析数据格式
                $this->binary_type = self::BINARY_TYPE_BLOB;
                $this->conn->setRecevicedData($headerLength);
                $this->conn->send($this->conn->send_data);

                # 主动发送握手成功后的信息, 状态更改为已连接
                $this->conn->conn_status = AConn::STATUS_CONNECTED;
                if($this->conn->send()) {
                    # 更改状态为已建立连接
                    $this->conn->conn_status = AConn::STATUS_ESTABLISHED;
                    return true;
                }

                return false;
            }
        }
        else {
            if(mb_strpos($data, '<polic') === 0) {
                $policy_xml = '<?xml version="1.0"?><cross-domain-policy><site-control permitted-cross-domain-policies="all"/><allow-access-from domain="*" to-ports="*"/></cross-domain-policy>' . "\0";
                return $this->conn->send($policy_xml);
            }
        }

        $this->conn->send("HTTP/1.1 400 Bad Request\r\n\r\n<b>400 Bad Request</b><br>Invalid handshake data for websocket.", true);
        $this->conn->destroy();
        return false;
    }

    /**
     * 解析数据
     */
    public function decode($receiveData) {
        $firstByte = ord($receiveData[0]);
        $secondByte = ord($receiveData[1]);
        $fin = $firstByte >> 7;
        $opcode = $firstByte & 15;
        $mask = $secondByte >> 7;
        $payloadLength = $secondByte & 127;

        # 判断数据是否已经传完, 并且判断是否有掩码, 没有掩码则直接断开连接
        if (!$fin && !$mask) return false;
        # 判断数据包类型
        if (!$this->_opcode($opcode)) return false;

        # 获取数据真实长度
        switch ($payloadLength) {
            case 126: {
                $maskString = substr($receiveData, 4, 4);
                $dataString = substr($receiveData, 8);
                break;
            }
            case 127: {
                $maskString = substr($receiveData, 10, 4);
                $dataString = substr($receiveData, 14);
                break;
            }
            default:
                $maskString = substr($receiveData, 2, 4);
                $dataString = substr($receiveData, 6);
                break;
        }

        # 使用掩码解码
        $decodeString = '';
        $len = strlen($dataString);
        for ($i = 0; $i < $len; $i++) {
            $decodeString .= $dataString[$i] ^ $maskString[$i % 4];
        }

        return $decodeString;
    }

    /**
     * 编码数据
     *
     * @param string $data
     * @return string
     * @throws
     */
    public function encode($data) {
        if (!is_scalar($data)) {
            throw new Exception("You can't send(" . gettype($data) . ") to client, you need to convert it to a string. ");
        }

        $len = strlen($data);
        if ($len <= 125) {
            $encodeBuffer = $this->binary_type . chr($len) . $data;
        } else {
            if ($len <= 65535) {
                $encodeBuffer = $this->binary_type . chr(126) . pack("n", $len) . $data;
            } else {
                $encodeBuffer = $this->binary_type . chr(127) . pack("xxxxN", $len) . $data;
            }
        }

        return $encodeBuffer;
    }

    /**
     * 判断数据包类型
     *
     * @return bool
     * @throws Exception
     */
    protected function _opcode($opcode) {
        switch ($opcode) {
            # Blob类型
            case 0x1:
                break;
            # binaryArray类型
            case 0x2:
                break;
            # 断开类型
            case 0x8:{
                if($this->conn->conn_status === AConn::STATUS_ESTABLISHED) {
                    $this->conn->conn_status = AConn::STATUS_CLOSING;
                    $this->conn->destroy();
                }

                return false;
            }
            # 心跳包响应
            case 0x9: {
                $this->conn->send_data = pack('H*', '8a00');
                $this->conn->send();
                break;
            }
            case 0xA:
                break;
            default:
                $this->conn->destroy();
                return false;
        }

        return true;
    }

    /**
     * 是否握手
     * @var bool $handshake
     */
    public $handshake = false;

    /**
     * 二进制格式类型
     * @var string $binary_type
     */
    public $binary_type = '';

    /**
     * 数据格式类型
     */
    const BINARY_TYPE_BLOB = "\x81";
    const BINARY_TYPE_ARRAY_BUFFER = "\x82";
}