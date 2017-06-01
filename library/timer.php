<?php
/**
 * 定时器
 *
 * @author lucasho
 * @created 2017-02-17
 * @modified 2017-02-17
 * @version 1.0
 * @link http://github.com/cqlucasho
 */
class Timer {
    /**
     * 安装时钟信号
     */
    public static function install() {
        pcntl_signal(SIGALRM, array('Timer', 'signalHandler'), false);
    }

    /**
     * 信号处理
     */
    public static function signalHandler() {
        if(!empty(self::$tasks)) {
            pcntl_alarm(self::$time);
            self::task();
        }
    }

    /**
     * 执行任务
     *
     * @throws Exception
     */
    public static function task() {
        if(empty(self::$tasks)) {
            pcntl_alarm(0);
            return;
        }

        foreach(self::$tasks as $time => $tasks) {
            if(time() >= $time) {
                foreach($tasks as $key => $task) {
                    call_user_func_array($task['func'], $task['args']);

                    if($task['persistent']) {
                        self::add($task['persistent'], $task['func'], $task['args']);
                    }
                }

                self::$tasks[$time] = array();
                unset(self::$tasks[$time]);
            }
        }
    }

    /**
     * 添加定时任务
     *
     * @param string $func 方法名称
     * @param array $argument 方法参数
     * @return bool
     * @throws
     */
    public static function add($interval = 0, $funcName, $arguments = array(), $persistent = false) {
        if(!empty($funcName)) {
            if (!is_callable($funcName)) throw new Exception("funcName is not callable");
            if (empty(self::$tasks)) pcntl_alarm(1);

            $time = time() + $interval;
            if(!isset(self::$tasks[$time])) self::$tasks[$time] = array();
            array_push(self::$tasks[$time], array('func' => $funcName, 'args' => $arguments, 'interval' => $interval, 'persistent' => $persistent));

            return true;
        }

        throw new Exception('funcName is empty!');
    }

    /**
     * 清除所有任务
     */
    public static function clear() {
        self::$tasks = array();
        pcntl_alarm(0);
    }

    /**
     * 定时任务
     * @var array $tasks
     */
    public static $tasks = array();
    /**
     * 定时时间
     * @var int $time
     */
    public static $time = 2;
}