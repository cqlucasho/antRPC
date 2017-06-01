<?php
/**
 * 服务器抽象类
 *
 * @author lucasho
 * @created 2017-02-09
 * @modified 2017-02-09
 * @version 1.0
 * @link http://github.com/cqlucasho
 */
abstract class AServer {
    /**
     * 安装父进程信号
     */
    public function installSignal() {
        pcntl_signal(SIGINT, array($this, 'signalHandler'), false);
        pcntl_signal(SIGPIPE, SIG_IGN, false);

        # 安装时钟信号
        Timer::install();
    }

    /**
     * 忽略子进程信号
     */
    public function reinstallSignal() {
        # 忽略control+c
        pcntl_signal(SIGINT, SIG_IGN, false);

        self::$event->add(SIGINT, EV_SIGNAL, array($this, 'signalHandler'));
    }

    /**
     * 判断信号类型
     *
     * @param int $signo 信号
     */
    public function signalHandler($signo) {
        switch($signo) {
            case SIGINT:
                $this->closeServer();
                break;
            default:
                break;
        }
    }

    /**
     * 关闭服务器
     */
    public function closeServer() {
        if(self::$_server_status === self::SERVER_RUNNING) self::$_server_status = self::SERVER_SHUTDOWN;

        if($this->_master_pid == posix_getpid()) {
            # 打印服务器关闭信息
            $this->_printClosedInfo();

            $pids = self::$_pid_groups[$this->_server_id];
            foreach($pids as $pid) {
                Timer::add(Timer::$time, 'posix_kill', array($pid, SIGKILL), false);

                posix_kill($pid, SIGINT);
                posix_kill($pid, SIGTERM);
            }
        }
        else {
            foreach (self::$_worker_groups as $worker) {
                self::$event->delete($worker->socket, EV_READ);
                @fclose($worker->socket);
            }

            exit(0);
        }
    }

    /**
     * 记录日志
     */
    public static function log($message) {
        @file_put_contents(self::$_run_file, $message, FILE_APPEND);
    }

    /**
     * 解析命令行
     *
     * @throws Exception
     */
    protected function _parseCommand() {
        global $argv;
        if(!isset($argv[1]) || $argv[1] == '-h') $this->_help();

        switch($argv[1]) {
            case 'start': {
                if(isset($argv[2]) && $argv[2] == '-d') {
                    $this->_is_daemon = true;
                    $this->_deamon();
                }

                break;
            }
            case 'stop':
                break;
            case 'restart':
                break;
            default:
                $this->_help();
                exit(0);
        }
    }

    /**
     * 初始化工作组
     */
    protected function _initialWorkers() {
        # 初始化工作组映射表
        foreach(self::$_worker_groups as $id => $worker) {
            for($i = 0; $i < $worker->process_count; $i++) {
                if(!isset(self::$_worker_groups_map[$id])) self::$_worker_groups_map[$id] = array();

                self::$_worker_groups_map[$id][$i] = 0;
            }

            if(!$worker->reuse_port) {
                $worker->_initialServer();
            }
        }
    }

    /**
     * 初始化服务器
     */
    protected function _initialServer() {
        # 判断每个worker是否已经创建socket
        if (!empty($this->socket)) return;

        $this->_loadProtocol();
        $this->_loadIOEvent();
        $this->_createServer();
    }

    /**
     * 创建服务器连接
     *
     * @throws Exception
     */
    protected function _createServer() {
        $this->_socket_context = stream_context_create();
        @stream_context_set_option($this->_socket_context, 'socket', 'backlog', self::DEFAULT_BACKLOG);

        if($this->reuse_port) {
            @stream_context_set_option($this->_socket_context, 'socket', 'so_reuseport', 1);
        }

        $flag = ($this->_protocol_name === 'tcp') ? STREAM_SERVER_LISTEN | STREAM_SERVER_BIND : STREAM_SERVER_BIND;
        $this->socket = stream_socket_server($this->_address, $errno, $errstr, $flag, $this->_socket_context);
        if (!$this->socket) {
            throw new Exception($errstr);
        }

        # 记录状态
        self::$_server_status = self::SERVER_START;
        if (function_exists('socket_import_stream') && $this->_protocol_name === 'tcp') {
            $socket = socket_import_stream($this->socket);
            @socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
            @socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
        }

        stream_set_blocking($this->socket, 0);

        # 加入event表
        if(self::$event) {
            #TODO: 判断是tcp还是udp
            self::$event->add($this->socket, EV_READ, array($this, 'accept'));
        }

        self::log('['.date('Y-m-d h:i:s', time()).']: the server is start.'."\r\n");
    }

    /**
     * 加载io多路复用类
     */
    protected function _loadIOEvent() {
        if(!self::$event) {
            switch($this->event_mode) {
                case 'libevent': {
                    Ant::import('library.libevent');
                    self::$event = new LibEvent();
                    break;
                }
                default:
                    break;
            }
        }
    }

    /**
     * 加载协议类
     */
    protected function _loadProtocol() {
        list($scheme, $address) = explode(':', $this->_address, 2);

        if(!isset($this->_protocal_groups[$scheme])) {
            if(!class_exists($scheme)) {
                Ant::import('protocols.'.$scheme);
                self::$protocol = new $scheme();
            }

            $this->_address = $this->_protocol_name.':'.$address;
        }
        else {
            $this->_protocol_name = $this->_protocal_groups[$scheme];
        }
    }

    /**
     * 根据连接类型生成连接对象
     *
     * @param string $mode
     */
    protected function _loadConnType() {
        switch($this->_conn_type) {
            case 'tcp': {
                if(!class_exists('Tcp')) {
                    Ant::import('conns.tcp');
                }
                break;
            }
        }
    }

    /**
     * 设置启动为守护进程
     */
    protected function _deamon() {
        if($this->_is_daemon) {
            umask(0);
            $pid = pcntl_fork();
            if($pid === -1) {
                die('fork fail.');
            } else if($pid > 0) {
                exit(0);
            }

            if (posix_setsid() === -1) throw new Exception("posix_setsid is failed!");

            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new Exception("fork fail");
            } elseif ($pid > 0) {
                exit(0);
            }
        }
    }

    /**
     * 保存当前主进程id
     *
     * @throws Exception
     */
    protected function _saveMasterPid() {
        $pid = posix_getpid();
        if(!file_put_contents(self::$_pid_file, $pid)) {
            throw new Exception('the pid.log file to write fail!');
        }

        $this->_master_pid = $pid;
    }

    /**
     * 如果为守护进程, 更改输入输出源.
     *
     * @throws Exception
     */
    protected function _daemonSIO() {
        if(!$this->_is_daemon) return;

        global $STDOUT, $STDERR;
        $handle = fopen('/dev/null', "a");
        if ($handle) {
            unset($handle);
            @fclose(STDOUT);
            @fclose(STDERR);
            $STDOUT = fopen('/dev/null', "a");
            $STDERR = fopen('/dev/null', "a");
        }
        else {
            throw new Exception('/dev/null invalid!');
        }
    }

    /**
     * 生成相关文件
     */
    protected function _generateFile() {
        $path = dirname(__DIR__).DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR;
        self::$_pid_file = $path.'process.pid';
        self::$_run_file = $path.'run.log';

        # 文件保存主进程id
        if(!file_exists(self::$_pid_file)) {
            touch(self::$_pid_file);
            chmod(self::$_pid_file, 777);
        }

        # 生成日志文件
        if(!file_exists(self::$_run_file)) {
            touch(self::$_run_file);
            chmod(self::$_run_file, 777);
        }
    }

    /**
     * 生成进程
     *
     * @throws Exception
     */
    protected function _forkProcess() {
        foreach(self::$_worker_groups as $worker) {
            if(self::$_server_status == self::SERVER_START) {
                $worker->process_count = ($worker->process_count <= 0) ? 1 : $worker->process_count;

                while(($pidGroupsNum = count(self::$_pid_groups[$worker->_server_id])) < $worker->process_count) {
                    $this->_forkWorker($worker, $pidGroupsNum);
                }
            }
        }
    }

    /**
     * 为每个worker生成相应进程
     *
     * @param $worker
     * @param $pidGroupsNum
     * @throws Exception
     */
    protected function _forkWorker($worker, $pidGroupsNum) {
        $pid = pcntl_fork();

        if ($pid > 0) {
            self::$_pid_groups[$worker->_server_id][$pid] = $pid;
            self::$_worker_groups_map[$worker->_server_id][$pidGroupsNum] = $pid;
        }
        else if ($pid === 0) {
            if($worker->reuse_port) $worker->_initialServer();
            if (self::$_server_status === self::SERVER_START) $worker->_daemonSIO();

            self::$_pid_groups = array();
            self::$_worker_groups = array($worker->_server_id => $worker);
            $worker->run();
            exit(1);
        }
        else {
            throw new Exception("fork fail");
        }
    }

    /**
     * 打印结束
     */
    protected function _printClosedInfo() {
        self::log('['.date('Y-m-d h:i:s', time()).']: the server is stopping...'."\r\n");
        self::log('['.date('Y-m-d h:i:s', time()).']: the server is stopped!'."\r\n");
        echo "the server is stopping...\t\n";
        echo "the server is stopped!\t\n";
    }

    /**
     * 打印基本系统信息
     */
    protected function _printInfo() {
        echo "\033[0;33m---------------------Server Info-------------------------\r\n";
        echo "Server Address: {$this->_address}\r\n";
        echo "PHP Version: ".PHP_VERSION."\r\n";
        echo "----------------------------------------------------------\033[0m\r\n";
    }

    /**
     * 帮助信息
     */
    protected function _help() {
        echo "\033[0;33m---------------------Basic Useages-------------------\r\n";
        echo "example: php server.php 127.0.0.1:8081 start|stop|restart\033[0m\r\n";
        exit();
    }

    /**
     * 运行
     *
     * @return mixed
     */
    abstract public function run();


    /**
     * 自定义连接成功方法
     * @var method $onConnected
     */
    public $onConnected;
    /**
     * 自定义接收到来自客户端消息后, 将json格式的数据解析成数组后并返回
     * @var method $onMessage
     */
    public $onMessage;
    /**
     * 自定义发送数据处理方法
     * @var method $onSend
     */
    public $onSend;

    /**
     * 工作组
     * @var array $worker_groups
     */
    protected static $_worker_groups = array();
    /**
     * 工作组映射表
     * @var array $worker_groups_map
     */
    protected static $_worker_groups_map = array();

    /**
     * 处理进程数, 默认为4
     * @var null $id
     */
    public $process_count = 4;
    /**
     * 单个进程的连接记数器
     * @var int $conn_counter
     */
    public static $conn_counter = 0;
    /**
     * 单个进程的连接队列管理
     * @var int $conn_quence
     */
    public $conn_quence = array();
    /**
     * 主父进程pid
     * @var int $_master_pid
     */
    protected $_master_pid = 0;
    /**
     * 进程组
     * @var array $pid_groups
     */
    protected static $_pid_groups = array();

    /**
     * 事件对象
     * @var null $event
     */
    public static $event = null;
    /**
     * 默认使用的evnet模式
     * @var string $event_mode
     */
    public $event_mode = 'libevent';

    /**
     * 协议对象
     * @var null|object $protocol
     */
    public static $protocol = null;
    /**
     * 目前支持的协议
     * @var array $protocal_groups
     */
    protected $_protocal_groups = array(
        'udp'       => 'udp',
        'tcp'       => 'tcp',
        'ssl'       => 'tcp'
    );
    /**
     * 协议名称
     * @var string $_protocol_name
     */
    protected $_protocol_name = 'tcp';

    /**
     * 使用的连接类型
     * @var string $_conn_type
     */
    protected $_conn_type = 'tcp';

    /**
     * 复用同一端口
     * @var int $reuse_port
     */
    public $reuse_port = 0;
    /**
     * 服务器socket文件描述符
     * @var null|resource $socket
     */
    public $socket = null;
    /**
     * 当前服务器对象id
     * @var string $_server_id
     */
    protected $_server_id = '';
    /**
     * 服务器状态
     * @var int $_server_status
     */
    protected static $_server_status = '';
    /**
     * stream_socket上下文信息
     * @var null|resource $socket_context
     */
    protected $_socket_context = null;
    /**
     * 监听地址
     * @var string $_address
     */
    protected $_address = '';
    /**
     * 通信协议
     * @var string $_scheme
     */
    protected $_scheme = '';
    /**
     * 当前脚本名称
     * @var string $_name
     */
    protected $_name = '';

    /**
     * 是否设置为守护进程
     * @var bool $_is_deamon
     */
    protected $_is_daemon = false;

    /**
     * 文件路径
     * @var string
     */
    protected static $_pid_file = '';
    protected static $_run_file = '';

    /**
     * 默认backlog大小
     * @var int
     */
    const DEFAULT_BACKLOG = 102400;

    /**
     * 服务器状态
     */
    const SERVER_START      = 1;
    const SERVER_RUNNING    = 2;
    const SERVER_SHUTDOWN   = 3;
    const SERVER_CLOSED     = 4;

    /**
     * 版本
     */
    const VERSION = 1.0;
}