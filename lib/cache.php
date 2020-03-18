<?php

use pool\connectionPool;
use pool\coroutineRedisConnector;

class cache
{

    private static $pool = [];
    private        $key;
    private        $prefix;

    public function __construct($prefix = '', $key = 'default')
    {
        $this->prefix = $prefix ? $prefix . '_' : '';
        $this->key    = $key;
    }

    public static function init()
    {
        $config = config::get('cache');
        foreach ($config as $key => $row) {
            self::$pool[$key] = new connectionPool([
                'minActive' => $row['min'],
                'maxActive' => $row['max'],
            ], new coroutineRedisConnector(), [
                'host'     => $row['host'],
                'port'     => 6379,
                'password' => $row['password'],
                'database' => $row['database'],
            ]);
            self::$pool[$key]->init();
        }
    }

    public static function close()
    {
        foreach (self::$pool as $pool) {
            $pool->close();
        }
    }

    public function clear()
    {
        $conn = self::$pool[$this->key]->borrow();
        $conn->flushdb();
        self::$pool['user']->return($conn);
    }

    public function set($key, $value, $option = 3600)
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }
        if ($value === null) {
            $option = 1;
        }
        $conn = cache::$pool[$this->key]->borrow();
        $conn->set($this->prefix . $key, $value, $option);
        cache::$pool[$this->key]->return($conn);
    }

    public function get($key)
    {
        $conn  = cache::$pool[$this->key]->borrow();
        $value = $conn->get($this->prefix . $key);
        cache::$pool[$this->key]->return($conn);
        $json = json_decode($value, true);
        return $json ?? $value;
    }

    public function incr($key)
    {
        $conn = cache::$pool[$this->key]->borrow();
        $conn->incr($this->prefix . $key);
        $result = $conn->get($this->prefix . $key);
        cache::$pool[$this->key]->return($conn);
        return $result;
    }

    public function delete($key)
    {
        $conn   = cache::$pool[$this->key]->borrow();
        $result = $conn->delete($this->prefix . $key);
        cache::$pool[$this->key]->return($conn);
        return $result;
    }

}