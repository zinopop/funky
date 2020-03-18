<?php

namespace wb;

use http;

class user
{
    public static function auth($access_token)
    {
        $res = json_decode(http::post('https://api.weibo.com/oauth2/get_token_info',
            ['access_token' => $access_token]), true);
        if (!$res || !$res['uid']) {
            return null;
        }
        return $res;
    }

    public static function info($access_token, $uid)
    {
        $url = http::build('https://api.weibo.com/2/users/show.json', [
            'access_token' => $access_token,
            'uid'          => $uid,
        ]);
        $res = json_decode(http::get($url), true);
        if (!$res || !$res['id']) {
            return null;
        }
        return $res;
    }
}