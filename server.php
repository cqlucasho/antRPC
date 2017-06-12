<?php
require_once('library/ant.php');
Ant::import('library.a_server');
Ant::import('library.timer');

/**
 * 服务器类
 *
 * @author lucasho
 * @created 2017-02-09
 * @modified 2017-02-09
 * @version 1.0
 * @link http://github.com/cqlucasho
 */
class Server extends AServer {
    public function __construct($listenAddress) {
        if(empty($listenAddress)) throw new Exception('the server listen address cann\'t empty!');

        $this->_address     = $listenAddress;
        $this->_server_id   = spl_object_hash($this);
        $this->_name        = get_class($this);
        self::$_worker_groups[$this->_server_id] = $this;
        self::$_pid_groups[$this->_server_id] = array();

        # 生成相关文件信息
        $this->_generateFile();

        # 初始化相关回调函数
        $this->onConnected  = function() {};
        $this->onMessage    = function() {};
        $this->onSend       = function() {};
    }

    /**
     * 执行服务器操作
     */
    public function runAll() {
        $this->_parseCommand();
        $this->_saveMasterPid();
        $this->_initialWorkers();
        $this->installSignal();
        $this->_forkProcess();
        $this->_printInfo();
        $this->_daemonSIO();
        $this->monitor();
    }

    /**
     * 运行
     */
    public function run() {
        self::$_server_status = self::SERVER_RUNNING;

        # 清除时钟并重新安装时钟信号
        Timer::clear();
        Timer::install();

        $this->_loadConnType();
        $this->reinstallSignal();

        self::$event->loop();
    }

    /**
     * 接收客户端请求
     *
     * @param resource $socket 服务器socket文件描述符
     * @throws
     */
    public function accept($socket) {
        if(!is_resource($socket)) throw new Exception('the function accept of tcp.php argument is invalid resource!');

        $socketAccept = @stream_socket_accept($socket, 5);
        if(!$socketAccept) return;

        # 更新记数
        self::$conn_counter++;
        # 生成新tcp连接, 并运行读取
        $conn = new Tcp($socketAccept, $this);
        $this->conn_quence[self::$conn_counter] = $conn;
        $conn->run();
    }

    /**
     * 父进程监控子进程状态
     */
    public function monitor() {
        self::$_server_status = self::SERVER_RUNNING;

        while(1) {
            pcntl_signal_dispatch();
            $status = 0;
            $pid = pcntl_wait($status, WUNTRACED);
            pcntl_signal_dispatch();

            if($pid > 0) {
                foreach(self::$_pid_groups as $serverID => $group) {
                    if(isset($group[$pid])) {
                        if($status !== 0) {
                            self::log('['.date('Y-m-d h:i:s', time()).']: ended status is '.$status.".\r\n");
                        }

                        unset(self::$_pid_groups[$serverID][$pid]);
                        self::$_worker_groups_map[$serverID][$pid] = 0;
                        break;
                    }
                }

                # 如果还是运行状态, 则开启新进程
                if (self::$_server_status !== self::SERVER_SHUTDOWN) {
                    $this->_forkProcess();
                } else {
                    $this->clearAndExit();
                }
            }
            else {
                if(self::$_server_status === self::SERVER_SHUTDOWN) {
                    $this->clearAndExit();
                }
            }
        }
    }

    /**
     * 注册服务
     *
     * @param string $address 地址
     * @param array $services 服务所在服务器及状态
     */
    public function registerServer($address, $services = array()) {
        Ant::import('library.services.services');
        $servicesObj = new Services($address);

        foreach($services as $name => $value) {
            $servicesObj->register($name, $value);
        }

        $servicesObj->close();
    }

    /**
     * 退出当前进程
     */
    protected function clearAndExit() {
        foreach (self::$_worker_groups as $worker) {
            $address = $worker->_address;

            if ($worker->_protocol_name === 'unix' && $address) {
                list(, $addr) = explode(':', $address, 2);
                @unlink($addr);
            }
        }

        exit(0);
    }

}