<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
date_default_timezone_set('Asia/Shanghai');

require_once "vendor/autoload.php";

define('ROOT', dirname(__FILE__));

spl_autoload_register(function (string $class) {
    if (strpos($class, 'api\\') === 0 || strpos($class, 'model\\') === 0 || strpos($class, 'task\\') === 0) {
        $file = ROOT . '/' . str_replace('\\', '/', $class) . '.php';
    } else {
        $file = ROOT . '/lib/' . str_replace('\\', '/', $class) . '.php';
    }
    if (is_file($file)) {
        require_once $file;
    }
});

config::init(ROOT . '/config/config.yaml', 'sw');

switch ($argv[1]) {
    case 'start':
        server::start();
        break;
    case 'stop':
        server::stop();
        break;
    case 'run':
        server::run($argv[2]);
        break;
    case 'cron':
        server::cron();
        break;
    case 'cronTest':
        server::cronRunTest($argv[2]);
        break;
    default:
        echo 'unknown command' . PHP_EOL;
        break;
}