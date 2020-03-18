<?php

namespace wb;

use http;

class service
{
    public static function updateSubscribe($appkey, $data)
    {
        if (!$data) {
            return false;
        }
        $params = ['source' => $appkey];
        foreach ($data as $k => $v) {
            if ($v) {
                $params[$k] = $v;
            }
        }
        $url = http::build('https://c.api.weibo.com/subscribe/update_subscribe.json', $params);
        $res = json_decode(http::get($url), true);
        return $res;
    }

    public static function listSubscribe($appkey, $subid)
    {
        $url = http::build('https://c.api.weibo.com/subscribe/update_subscribe.json', [
            'source' => $appkey,
            'subid'  => $subid,
        ]);
        $res = json_decode(http::get($url), true);
        return $res;
    }
}