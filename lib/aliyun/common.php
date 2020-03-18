<?php

namespace aliyun;

use functions;
use http;

class common
{
    public static function post($host, $data, $id, $secret)
    {
        $data['Format'] = 'json';
        if (!$data['RegionId']) {
            $data['RegionId'] = 'cn-hangzhou';
        }
        $data['SignatureMethod']  = 'HMAC-SHA1';
        $data['Timestamp']        = date('Y-m-d', time() - 8 * 3600) . 'T' . date('H:i:s', time() - 8 * 3600) . 'Z';
        $data['SignatureVersion'] = '1.0';
        $data['SignatureNonce']   = functions::uuid();
        $data['AccessKeyId']      = $id;
        ksort($data);
        $query = [];
        foreach ($data as $k => $v) {
            $v       = urlencode($v);
            $v       = str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], $v);
            $query[] = $k . '=' . $v;
        }
        $query             = 'GET&' . urlencode('/') . '&' . urlencode(implode('&', $query));
        $sign              = hash_hmac('sha1', $query, $secret . '&', true);
        $sign              = base64_encode($sign);
        $data['Signature'] = $sign;
        $url               = http::build($host, $data);
        $res               = http::get($url);
        return json_decode($res, true);
    }
}