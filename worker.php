<?php
require_once('server.php');

try {
    $server = new Server('tcp://127.0.0.1:8088');
    $server->process_count = 4;

    # 连接成功处理
    $server->onConnected = function() {
        return 'connected';
    };

    # 处理接收到的数据
    $server->onMessage = function($data) {
        $data = json_decode($data);

        return $data->requestID;
    };

    # 发送数据处理
    $server->onSend = function($data) {
        return json_encode("hello, $data");
    };

    # 注册服务
    $server->registerServer('tcp://127.0.0.1:8089', array(
        'testData' => array('127.0.0.1:8090' => array('state' => true, 'flag' => true), '127.0.0.1:8091' => false),
        'testData1' => array('127.0.0.1:8090' => array('state' => true, 'flag' => true), '127.0.0.2:8091' => false)
    ));

    # 运行服务器
    $server->runAll();
}
catch (Exception $e) {
    echo $e;
}