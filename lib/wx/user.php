<?php

namespace wx;

use config;
use http;

class user
{
    public static function auth($code)
    {
        $url = http::build('https://api.weixin.qq.com/sns/oauth2/access_token', [
            'appid'      => config::get('wx.appid'),
            'secret'     => config::get('wx.secret'),
            'code'       => $code,
            'grant_type' => 'authorization_code',
        ]);
        $res = json_decode(http::get($url), true);
        if (!$res || $res['errcode']) {
            return null;
        }
        return $res;
    }

    public static function info($openid, $access_token)
    {
        $url = http::build('https://api.weixin.qq.com/sns/userinfo', [
            'openid'       => $openid,
            'access_token' => $access_token,
        ]);
        $res = json_decode(http::get($url), true);
        if (!$res || $res['errcode']) {
            return null;
        }
        return $res;
    }
}