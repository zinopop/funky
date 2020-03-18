<?php

use cron\common;
use Swoole\Coroutine\System;
use Swoole\Http\Request as swRequest;
use Swoole\Http\Response as swResponse;
use Swoole\Http\Server as swServer;
use Swoole\Process as swProcess;
use Swoole\Runtime as swRuntime;
use Swoole\Timer as swTimer;

class server
{

    private static $server;
    private static $watcher;
    private static $pid_file  = '/run/service.pid';
    private static $worker_id = 0;
    private static $mode;
    private static $server_id = 0;
    private static $start     = false;

    public static function start()
    {
        if (is_file(self::$pid_file)) {
            echo 'service is already running' . PHP_EOL;
            return;
        }

        self::$server = new swServer('0.0.0.0', 80);
        self::$server->set([
            'dispatch_mode'         => 1,
            'daemonize'             => false,
            'package_max_length'    => 1024 * 1024 * 100,
            'buffer_output_size'    => 1024 * 1024 * 100,
            'open_cpu_affinity'     => true,
            'open_tcp_nodelay'      => true,
            'enable_reuse_port'     => true,
            'reload_async'          => true,
            'tcp_fastopen'          => true,
            'enable_coroutine'      => true,
            'max_coroutine'         => 10000,
            'pid_file'              => null,
            'max_wait_time'         => 60,
            'http_compression'      => false,
            'enable_static_handler' => true,
            'document_root'         => ROOT . '/static',
            'hook_flags'            => SWOOLE_HOOK_ALL,
        ]);

        self::$mode = (int)config::get('server.mode');
        if (config::get('server.keeper') && self::$mode != 0) {
            self::$server_id = (int)file_get_contents('http://' . config::get('server.keeper') . '?version=' . config::get('server.name') . '-' . config::get('server.version'));
        } else {
            self::$server_id = 0;
        }
        self::$server->on('start', function () {
            self::watch();
        });
        self::$server->on('workerStart', function (Swoole\Server $server) {
            config::init(ROOT . '/config/config.yaml', 'sw');
            self::$worker_id = $server->worker_id;
            http::init();
            db::init();
            cache::init();
            if (self::$server_id == 0 && self::$worker_id == 0) {
                model\user::init();
                $db_log = syncdb::run();
                if (self::$mode > 0) {
                    $log = '服务启动成功(v' . config::get('server.version') . ')';
                    if ($db_log) {
                        $log .= PHP_EOL . $db_log;
                    }
                    ding::send($log);
                }
            }
            self::$start = true;
        });

        self::$server->on('workerError', function () {
            sleep(30);
        });
        self::$server->on('workerStop', function () {
            db::close();
            cache::close();
        });
        self::$server->on('request', function (swRequest $swRequest, swResponse $swResponse) {
            $request = new request($swRequest, $swResponse);
            if (!self::$start) {
                $request->error(null, 'system busy', 500, false);
                return;
            }
            try {
                self::route($request);
            } catch (Exception | Error $e) {
                if ($e->getCode() == -1) {
                    return;
                }
                $info = functions::formatException($request, $e);
                echo '[' . functions::date() . '] Exception' . PHP_EOL . $info . PHP_EOL . PHP_EOL;
                if (self::$mode == 0) {
                    $request->error($e->getTrace(), $e->getMessage(), 500, false);
                } else {
                    ding::send($info);
                    $request->error(null, 'system busy', 500, false);
                }
            }

        });

        //swRuntime::enableCoroutine(true);
        self::$server->start();
    }

    private static function watch()
    {
        self::$watcher = new watcher();
        self::$watcher->addAll(['api', 'data', 'lib', 'model', 'config.yaml']);
        self::$watcher->onUpdate(function () {
            self::$server->reload();
        }, 200);
        self::$watcher->start();
    }

    private static function route(request $request)
    {
        if ($request->server('request_method') == 'OPTIONS') {
            $request->options();
            $request->send('');
        }
        $uri = substr($request->server('path_info'), 1);
        if (!$uri) {
            $request->send('');
        }
        if (substr($uri, -1) == '/') {
            $uri = substr($uri, 0, -1);
        }
        if ($uri == 'favicon.ico') {
            $request->send('');
        }
        $uri_arr = explode('/', $uri);
        if (count($uri_arr) == 1) {
            $uri_arr[1] = 'index';
        }
        $class  = 'api';
        $method = '';
        foreach ($uri_arr as $k => $v) {
            if (strpos($v, '_') === 0) {
                $request->error(null, 'forbidden', 403);
            }
            if ($k == count($uri_arr) - 1) {
                $method = $v;
            } else {
                $class .= '\\' . $v;
            }
        }
        if (!class_exists($class)) {
            $request->error(null, 'module not found', 404);
        }
        if($request->arg("version")){
            $version_flag = (int) substr($request->arg("version") , 0 , 1);
            $reflectionClass = new reflectionClass($class);
            $re = function ($reflectionClass,$method,$version_flag){
                $flag = null;
                foreach ($reflectionClass->getMethods() as &$v){
                    if($method."_v".$version_flag == $v->getName()){
                        $flag = $v->getName();
                        break;
                    }
                }
                return $flag;
            };
            for ($i=$version_flag;$i>=1;$i--){
                $method_flag = $re($reflectionClass,$method,$i);
                if($method_flag) {
                    $method = $method_flag;
                    break;
                }
            }
        }
        $object = new $class($method,$request);
        if(!method_exists($object, $method)){
            $request->error(null, 'method not found', 404);
        }
        call_user_func_array([$object, $method], [$request]);
        $request->success();
    }

    public static function stop()
    {
        $pid = file_get_contents(self::$pid_file);
        if (!$pid) {
            echo 'service is not running' . PHP_EOL;
            return;
        }
        swProcess::kill($pid, 15);
        swTimer::tick(200, function () use ($pid) {
            if (is_file(self::$pid_file)) {
                echo '.';
            } else {
                swProcess::kill($pid, 9);
                exit();
            }
        });
    }

    public static function getServerId()
    {
        return self::$server_id;
    }

    public static function getWorkerId()
    {
        return self::$worker_id;
    }

    public static function cron()
    {
        $http = new swServer('0.0.0.0', 8001);
        $http->on('request', function ($request, $response) {
            $response->end("hello_world");
        });

        $http->on('start', function (Swoole\Server $server) {
            config::set("db.default.min", 1);
            config::set("cache.default.min", 1);
            db::init();
            cache::init();
            http::init();
            go(function () {
                try {
                    $cron = common::getEventData();
                    if (!$cron) {
                        var_dump("没有可运行的计划任务." . date('Y-m-d H:i:s'));
                        ding::send("没有可运行的计划任务." . date('Y-m-d H:i:s'));
                        exit();
                    }
                    $cron_msg = "计划任务进程开启:\n";
                    foreach ($cron as $k => $v) {
                        $cron_msg .= '任务:' . $v['event_name'] . ',执行方法:' . $v['model_name'] . ";\n";
                    }
                    $cron_msg .= '当前容器时间:' . date('Y-m-d H:i:s');
                    if(config::get('server.mode')) {
                        ding::send($cron_msg);
                    }
                    foreach ($cron as $k => $v) {
                        go(function () use ($v, $cron_msg) {
                            while (true) {
                                try {
                                    $cron_row = common::getCronByEventName($v['event_name']);
                                    if ($cron_row['is_enable'] === 1) {
                                        switch ($cron_row['type']) {
                                            case 'clock':
                                                if ($cron_row['execute_clock'] == date("H:i") && (time() - strtotime($cron_row['executetime'])) > 60 * 60 * 24) {
                                                    common::cron_callback($cron_row['event_name']);
                                                }
                                                break;
                                            case 'offset':
                                                if ((time() - strtotime($cron_row['executetime'])) > (int)$cron_row['execute_offset_time']) {
                                                    common::cron_callback($cron_row['event_name']);
                                                }
                                                break;
                                        }
                                    }
                                } catch (Exception $e) {
                                    db::close();
                                    cache::close();
                                    var_dump("任务." . $v['event_name'] . "异常:" . $e->getMessage() . date('Y-m-d H:i:s'));
                                    ding::send("任务." . $v['event_name'] . "异常:" . $e->getMessage() . date('Y-m-d H:i:s'));
                                    System::sleep(10);
                                    exit();
                                }
                                System::sleep(1);
                            }
                        });
                    }
                } catch (Exception $e) {
                    db::close();
                    cache::close();
                    var_dump("计划任务启动进程异常:." . $e->getMessage() . date('Y-m-d H:i:s'));
                    ding::send("计划任务启动进程异常:." . $e->getMessage() . date('Y-m-d H:i:s'));
                    exit();
                }
            });
        });

        $http->on('workerError', function () {
            db::close();
            cache::close();
            ding::send("8001端口异常:" . date('Y-m-d H:i:s'));
        });
        $http->on('workerStop', function () {
            db::close();
            cache::close();
            ding::send("8001已停止工作:" . date('Y-m-d H:i:s'));
        });
        swRuntime::enableCoroutine(true);
        $http->start();
    }

    public static function run($task)
    {
        go("task\\{$task}::run");
    }

    public static function cronRunTest($event_name)
    {
        db::init();
        cache::init();
        http::init();
        go(function () use ($event_name) {
            common::getEventData();
            common::cron_callback($event_name);
        });
        db::close();
        cache::close();
    }

}