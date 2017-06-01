<?php
/**
 * libevent事件驱动封闭类
 *
 * @author lucasho
 * @created 2017-02-13
 * @modified 2017-02-13
 * @version 1.0
 * @link http://github.com/cqlucasho
 */
class LibEvent {
    public function __construct() {
        $this->event_base = event_base_new();
    }

    public function fetchEvents() {
        return $this->event_list;
    }

    public function add($fd, $flag, $callback, $arg = array()) {
        switch($flag) {
            case EV_SIGNAL: {
                $newEvent = event_new();
                event_set($newEvent, $fd, $flag, $callback, $arg);
                event_base_set($newEvent, $this->event_base);
                event_add($newEvent);

                $this->event_signal[$fd] = $newEvent;
                break;
            }
            default:
                $fdKey = (int)$fd;
                $realFlag = ($flag === EV_READ) ? EV_READ | EV_PERSIST : EV_WRITE | EV_PERSIST;

                $newEvent = event_new();
                event_set($newEvent, $fd, $realFlag, $callback, $arg);
                event_base_set($newEvent, $this->event_base);
                event_add($newEvent);

                $this->event_list[$fdKey][$flag] = $newEvent;
        }
    }

    public function delete($fd, $flag) {
        switch($flag) {
            case EV_READ:
            case EV_WRITE: {
                $fdKey = (int)$fd;
                if(isset($this->event_list[$fdKey][$flag])) {
                    event_del($this->event_list[$fdKey][$flag]);
                    unset($this->event_list[$fdKey][$flag]);
                }

                break;
            }
            case EV_SIGNAL: {
                if(isset($this->event_signal[$fd])) {
                    event_del($this->event_signal[$fd]);
                    unset($this->event_signal[$fd]);
                }

                break;
            }
        }
    }

    public function loop() {
        event_base_loop($this->event_base);
    }


    /**
     * 事件库
     * @var null $event
     */
    public $event_base = null;
    /**
     * 已注册事件信号
     * @var array $event_signal
     */
    public $event_signal = array();
    /**
     * 已注册事件列表
     * @var array $event_list
     */
    public $event_list = array();
}