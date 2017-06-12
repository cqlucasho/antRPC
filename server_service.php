<?php
require_once('library/ant.php');
Ant::import('server');
Ant::import('library.services.i_service');
Ant::import('library.services.service');
Ant::import('library.services.context');

/**
 * 服务类, 主要用于存储rpc需要的相关服务数据.
 *
 * @author lucasho
 * @created 2017-02-23
 * @modified 2017-02-23
 * @version 1.0
 * @link http://github.com/cqlucasho
 */
class ServerService extends Server {
    public static function e() {
        echo "service ok\r\n";
    }
}

try {
    $servicesServer = new ServerService('tcp://127.0.0.1:8089');
    $servicesServer->process_count = 4;

    $servicesServer->onMessage = function($data) {
        $receDatas = explode(';', $data);

        return $receDatas;
    };

    $servicesServer->onSend = function($datas) {
        if(is_array($datas)) {
            $result = false;
            $datas = array_filter($datas);

            foreach($datas as $value) {
                $data = json_decode($value, true);
                echo "------------------------------\ndata:\n";
                print_r($data);
                echo "\n";

                switch($data['type']) {
                    case IService::SERVICE_TYPE_SERVICE: {
                        $resultData = Service::operation($data);
                        echo "resultData:\n";
                        print_r($resultData);
                        echo "\n";
                        if(!empty($resultData) || $resultData === true) {
                            $result = $resultData;
                        }

                        break;
                    }
                    default:
                        $result = Context::operation($data);
                }
            }

            if(!empty($result) || $result === true) {
                return json_encode(array('status' => 200, 'result' => $result));
            } else {
                return json_encode(array('status' => 404, 'result' => false));
            }
        }

        return false;
    };
    
    $servicesServer->runAll();
}
catch (Exception $e) {
    echo $e;
}