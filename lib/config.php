<?php

use Symfony\Component\Yaml\Yaml as Yaml;

class config
{

    private static $data = [];

    public static function init($file, $env = '')
    {
        self::$data = Yaml::parseFile($file);
        if ($env) {
            $env     .= '_';
            $envData = getenv();
            foreach ($envData as $k => $v) {
                if (strpos($k, $env) === 0) {
                    $k = substr($k, strlen($env));
                    $k = str_replace('_', '.', $k);
                    self::set($k, $v);
                }
            }
        }
    }

    public static function set($key, $value)
    {
        switch ($value) {
            case 'true':
                $value = true;
                break;
            case 'false':
                $value = false;
                break;
            default:
                break;
        }
        $p  = &self::$data;
        $ks = explode('.', $key);
        foreach ($ks as $i => $k) {
            if ($i == count($ks) - 1) {
                $p[$k] = $value;
            } else {
                if (!$p[$k]) {
                    $p[$k] = [];
                }
                $p = &$p[$k];
            }
        }
    }

    public static function get($key = null)
    {
        if (!$key) {
            return self::$data;
        }
        $p  = &self::$data;
        $ks = explode('.', $key);
        foreach ($ks as $k) {
            $p = &$p[$k];
        }

        return $p;
    }

}