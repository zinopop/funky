<?php

use Swoole\Http\Request as swRequest;
use Swoole\Http\Response as swResponse;

class request
{

    private $swRequest;
    private $swResponse;
    private $session_id;

    public function __construct(swRequest $swRequest, swResponse $swResponse)
    {
        $this->swRequest  = $swRequest;
        $this->swResponse = $swResponse;
    }

    public function arg($key = null, $filter = [])
    {
        $value = $this->server('request_method') == 'GET' ? $this->get($key) : $this->post($key);
        if (!$key) {
            return $value;
        }
        $fmt = $value;
        if ($filter['default'] && !$fmt) {
            $fmt = $filter['default'];
        }

        $name = $filter['name'] ?? $key;
        switch ($filter['type']) {
            case 'int':
                $fmt = intval($fmt);
                if ($filter['min'] && $fmt < $filter['min']) {
                    $this->error(['key' => $key, 'value' => $value, 'min' => $filter['min']],
                        "{$name}不能小于{$filter['min']}", 402);
                }
                if ($filter['max'] && $fmt > $filter['max']) {
                    $this->error(['key' => $key, 'value' => $value, 'max' => $filter['max']],
                        "{$name}不能大于{$filter['max']}", 402);
                }
                break;
            case 'array':
                break;
            case 'img':
                if (strpos($fmt, 'http') !== false) {
                    $fmt_array = explode('/', $fmt);
                    $idx       = count($fmt_array);
                    $img       = $fmt_array[$idx - 1];
                    $uuid      = substr($img, 0, strpos($img, '.'));
                    $fmt       = $uuid;
                }
                break;
            case 'file':
                $fmt = upload::getId($fmt);
                break;
            case 'json':
                $fmt = json_decode($fmt, $filter['array'] !== false);
                break;
            case 'url':
                if ($fmt && strpos($fmt, 'http://') !== 0) {
                    $fmt = 'http://' . $fmt;
                }
                break;
            case 'time':
                $fmt = functions::date(null, strtotime($fmt));
                break;
            default:
                if ($filter['type'] == 'mobile') {
                    $filter['regex'] = '/^1[0-9]{10}$/';
                } else {
                    if ($filter['type'] == 'email') {
                        $filter['regex'] = '/^[A-Za-z\d]+([-_.][A-Za-z\d]+)*@([A-Za-z\d]+[-.])+[A-Za-z\d]{2,4}$/';
                    } else {
                        if ($filter['type'] == 'ip') {
                            $filter['regex'] = '/^(2(5[0-5]{1}|[0-4]\d{1})|[0-1]?\d{1,2})(\.(2(5[0-5]{1}|[0-4]\d{1})|[0-1]?\d{1,2})){3}$/';
                        }
                    }
                }

                if ($filter['type'] == 'wxcode') {
                    $filter['regex'] = '/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{1,50}$/';
                }

                $fmt = (string)$fmt;
                if ($filter['enum'] && !in_array($fmt, $filter['enum'])) {
                    $this->error(['key' => $key, 'value' => $value, 'enum' => $filter['enum']], "{$name}非法", 402);
                }
                if ($filter['regex'] && $fmt) {
                    preg_match($filter['regex'], $fmt, $match);
                    if (!$match) {
                        $this->error(['key' => $key, 'value' => $value, 'regex' => $filter['regex']], "{$name}格式错误",
                            402);
                    }
                }
                if ($filter['min'] && mb_strlen($fmt, 'utf-8') < $filter['min']) {
                    $this->error(['key' => $key, 'value' => $value, 'min' => $filter['min']],
                        "{$name}长度不能小于{$filter['min']}", 402);
                }
                if ($filter['max'] && mb_strlen($fmt, 'utf-8') > $filter['max']) {
                    $this->error(['key' => $key, 'value' => $value, 'max' => $filter['max']],
                        "{$name}长度不能大于{$filter['max']}", 402);
                }
                break;
        }

        if ($filter['required'] && !$fmt) {
            $this->error(['key' => $key, 'value' => $value], "{$name}不能为空", 402);
        }
        return $fmt;
    }

    public function server($key = null)
    {
        return $key ? $this->swRequest->server[$key] : $this->swRequest->server;
    }

    public function get($key = null)
    {
        return $key ? $this->swRequest->get[$key] : $this->swRequest->get;
    }

    public function post($key = null)
    {
        return $key ? $this->swRequest->post[$key] : $this->swRequest->post;
    }

    public function error($data = null, $msg = 'error', $code = 400, $break = true)
    {
        $this->json([
            'status'   => $code,
            'msg'      => $msg,
            'data'     => $data,
            'duration' => $this->duration(),
            'version'  => config::get('server.version'),
        ], $break);
    }

    public function json($data, $break = true)
    {
        $this->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->options();
        $this->send(json_encode($data), $break);
    }

    public function setHeader($key, $value)
    {
        $this->swResponse->header($key, $value);
    }

    public function options()
    {
        $this->setHeader('Access-Control-Allow-Credentials', 'true');
        $this->setHeader('Access-Control-Allow-Origin', $this->header('origin'));
        $this->setHeader('Access-Control-Allow-Methods', 'get,post,options');
        $this->setHeader('Access-Control-Allow-Headers', 'x-requested-with,content-type');
    }

    public function header($key = null)
    {
        return $key ? $this->swRequest->header[$key] : $this->swRequest->header;
    }

    public function send($content, $break = true)
    {
        $this->swResponse->end($content);
        if ($break) {
            $this->break();
        }
    }

    private function break()
    {
        throw new Exception('', -1);
    }

    public function duration()
    {
        $duration = 1000 * (microtime(true) - $this->server('request_time_float'));
        return round($duration, 2);
    }

    public function getContent()
    {
        return $this->swRequest->rawContent();
    }

    public function file()
    {
        return array_values($this->swRequest->files)[0];
    }

    public function content()
    {
        return $this->swRequest->rawContent();
    }

    public function ip()
    {
        return $this->header('x-forwarded-for') ?? $this->server('remote_addr');
    }

    public function setHeaders($headers)
    {
        foreach ($headers as $key => $value) {
            $this->setHeader($key, $value);
        }
    }

    public function setStatus($code)
    {
        $this->swResponse->status($code);
    }

    public function redirect($url, $code = 302)
    {
        $this->swResponse->redirect($url, $code);
        $this->break();
    }

    public function success($data = null, $msg = 'success', $break = true)
    {
        $this->json([
            'status'   => 200,
            'msg'      => $msg,
            'data'     => $data,
            'duration' => $this->duration(),
            'version'  => config::get('server.version'),
        ], $break);
    }

    public function initSession()
    {
        if ($this->session_id) {
            return;
        }
        $name             = config::get('session.name');
        $this->session_id = $this->cookie($name);
        if (!$this->session_id) {
            $this->session_id = functions::uuid() . functions::randomString(16);
            $this->setCookie($name, $this->session_id);
        }
    }

    public function cookie($key)
    {
        return $this->swRequest->cookie[$key];
    }

    public function setCookie($key, $value, $expire = 0)
    {
        $this->swResponse->cookie($key, $value, $expire, '/');
    }

    public function session($key)
    {
        if (!$this->session_id) {
            return null;
        }
        $cache = new cache('session');
        $data  = $cache->get($this->session_id);
        return $data[$key];
    }

    public function setSession($key, $value)
    {
        if (!$this->session_id) {
            return;
        }
        $cache = new cache('session');
        $data  = $cache->get($this->session_id);
        if (!$data) {
            $data = [];
        }
        if ($value === null) {
            unset($data[$key]);
        } else {
            $data[$key] = $value;
        }
        $cache->set($this->session_id, count($data) ? $data : null, config::get('session.alive') * 3600);
    }

}