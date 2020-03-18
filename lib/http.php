<?php

//https://github.com/swlib/saber

class http
{

    public static function init()
    {
        Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_CURL);
    }

    public static function build($url, $args)
    {
        if (!$args || count($args) == 0) {
            return $url;
        }
        return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args);
    }

    public static function get($url, $options = [])
    {
        !is_array($options) && $options = [];
        $options['method'] = 'get';
        $options['url']    = $url;
        $options['format'] = false;

        $res = self::send($options);
        return $res['content'];
    }

    public static function send($options)
    {
        if (!is_array($options)) {
            $options = [
                'method' => 'get',
                'url'    => $options,
            ];
        }

        //get或post
        $method = $options['method'];

        //url自动加http://
        $url = $options['url'];
        if (strpos($url, 'http://') === false
            && strpos($url, 'https://') === false) {
            $url = 'http://' . $url;
        }

        //post参数
        $data = $method == 'post' ? $options['data'] : null;

        //header兼容key => value和key: value
        $header = $options['header'] ?? [];
        foreach ($header as $k => $v) {
            if (!is_numeric($k)) {
                unset($header[$k]);
                $header[] = $k . ': ' . $v;
            }
        }
        //curl对post请求先发出100-continue，这里屏蔽掉，兼容一些不支持100的服务器
        $header[] = 'Expect: ';

        //user-agent
        $ua = $options['ua'] ?? 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_2) AppleWebKit/604.4.7 (KHTML, like Gecko) Version/11.0.2 Safari/604.4.7';

        //referer默认同url
        $referer = $options['referer'] ?? $url;

        //cookie为key => value格式
        $cookie = $options['cookie'];
        if ($cookie) {
            $cookie_arr = [];
            foreach ($cookie as $k => $v) {
                $cookie_arr[] = $k . '=' . urlencode($v);
            }
            $cookie = implode('; ', $cookie_arr);
        }

        //超时时间
        $timeout = $options['timeout'] ?? 10;

        //重试次数
        $try = max(1, intval($options['try']));

        //是否解析结果
        $parse = $options['parse'] ?? true;

        //结果格式化
        $format = $options['format'] ?? true;

        //跟随location
        $follow = $options['follow'] ?? true;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        if ($header) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        if ($cookie) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if ($referer) {
            curl_setopt($ch, CURLOPT_REFERER, $referer);
        }
        if ($method == 'post') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $content = curl_exec($ch);
        $errno   = curl_errno($ch);
        $err     = curl_error($ch);
        curl_close($ch);

        if (!$content || $errno) {
            return $err;
        }

        if (!$parse) {
            return $content;
        }

        //解析结果
        $res            = [];
        $content_arr    = explode("\r\n\r\n", $content);
        $res_header     = array_shift($content_arr);
        $res['content'] = implode("\r\n\r\n", $content_arr);
        $res_header_arr = explode("\r\n", $res_header);
        $res_first_line = array_shift($res_header_arr);
        list(, $res['status']) = explode(' ', $res_first_line);
        $res['header'] = [];
        $res['cookie'] = [];
        foreach ($res_header_arr as $row) {
            [$k, $v] = explode(': ', $row);
            if ($k == 'Set-Cookie') {
                [$c] = explode('; ', $v);
                [$ck, $cv] = explode('=', $c);
                $res['cookie'][$ck] = urldecode($cv);
            } else {
                $res['header'][$k] = $v;
            }
        }

        if (strpos($res['status'], '30') === 0 && $follow) {
            $follow_options            = $options;
            $follow_options['url']     = $res['header']['Location'];
            $follow_options['method']  = 'get';
            $follow_options['referer'] = $url;
            $follow_options['cookie']  = $res['cookie'];
            return self::send($follow_options);
        }

        //自动格式化
        if ($format) {
            if (strpos(strtolower($res['header']['Content-Type']), 'application/json') !== false) {
                $res['content'] = json_decode($res['content'], true);
            }
        }
        return $res;
    }

    public static function json($url, $data = [], $options = [])
    {
        $data                              = json_encode($data);
        $options['header']['Content-Type'] = 'application/json';
        return self::post($url, $data, $options);
    }

    public static function post($url, $data = [], $options = [])
    {
        !is_array($options) && $options = [];
        $options['method'] = 'post';
        $options['url']    = $url;
        if ($data && is_array($data)) {
            foreach ($data as $name => &$value) {
                if (strpos($value, '@') === 0 && is_file(substr($value, 1))) {
                    $value = new CURLFile(substr($value, 1));
                }
            }
        }
        $options['data']   = $data;
        $options['format'] = false;

        $res = self::send($options);
        if ($res['status'] != 200) {
            return '';
        }
        return $res['content'];
    }

}