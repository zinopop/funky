<?php

namespace qq;

use config;
use http;

class user
{
    public static function info($openid, $access_token, $device)
    {
        $url = http::build('https://graph.qq.com/user/get_user_info', [
            'oauth_consumer_key' => config::get('qq.' . $device),
            'openid'             => $openid,
            'access_token'       => $access_token,
        ]);
        $res = json_decode(http::get($url), true);
        if (!$res || $res['ret'] !== 0) {
            return null;
        }
        return $res;
    }
}