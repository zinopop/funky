<?php
namespace cron;
use config;
use event;
use swoole_process;

class common
{

    public static function getEventData()
    {
        $result = \model\cron::list();
        foreach ($result as $k => $v){
            config::set('event.'.$v['event_name'],json_decode($v['model_name']));
        }
        return $result;
    }

    public static function cron_callback($event_name)
    {
        event::publish($event_name);
        \model\cron::setCronTime($event_name);
    }

    public static function getCronByEventName($event_name)
    {
        $row = \model\cron::getCronByEventName($event_name);
        return $row;
    }

    public static function createProcess($function)
    {
        $process = new swoole_process(function(swoole_process $worker)use($function){
            $function();
        });
        $process->start();
        swoole_event_add($process->pipe, function($pipe) use($process) {
            //删除sock pip异步事件
            swoole_event_del($pipe);
        });
    }
}