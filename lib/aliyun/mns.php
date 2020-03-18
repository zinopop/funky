<?php
namespace aliyun;

class mns {

    public static function sign($method, $md5, $type, $date, $headers, $uri) {
        $mnsHeaders = '';
        ksort($headers);
        foreach ($headers as $key => $value) {
            $mnsHeaders .= $key . ':' . $value . "\n";
        }
        $re = 'MNS ' .\config::get('aliyun.mns.key') . ':' . base64_encode(hash_hmac('sha1', $method . "\n" . $md5 . "\n" . $type . "\n" . $date . "\n" . $mnsHeaders . $uri, 'y1v7vgt7zpJlsRJtSUlfedwZXQ6H2i', true));
        return $re;
    }


//    public static function subscription($name, $callback) {
//        $config = \config::get('aliyun.mns');
//        if (!$config['enabled']) {
//            return false;
//        }
//
//        $uri = '/topics/' . $config['topic'] . '/subscriptions/' . $name;
//        $url = $config['host'] . $uri;
//
/*        $content = '<?xml version="1.0" encoding="utf-8"?><Subscription xmlns="http://mns.aliyuncs.com/doc/v1/"><Endpoint>' . $callback . '</Endpoint><NotifyStrategy>EXPONENTIAL_DECAY_RETRY</NotifyStrategy><NotifyContentFormat>SIMPLIFIED</NotifyContentFormat><FilterTag>' . $name . '</FilterTag></Subscription>';*/
//
//        $date = gmdate('D, d M Y H:i:s T');
//
//        $res = Swlib\SaberGM::put($url, $content, [
//            'headers'  => [
//                'Authorization' => self::sign('PUT', '', 'text/xml', $date, [
//                    'x-mns-version' => '2015-06-06',
//                ], $uri),
//                'Content-Type'  => 'text/xml',
//                'Date'          => $date,
//                'x-mns-version' => '2015-06-06',
//            ],
//            'redirect' => 0,
//        ]);
//        if ($res->statusCode == 201 || $res->statusCode == 204) {
//            return true;
//        }
//        return false;
//    }

    public static function msg($name, $content) {
        $config = \config::get('aliyun.mns');
        if (!$config['enabled']) {
            return false;
        }
        $uri = '/topics/' . $config['topic'] . '/messages';
        $url = $config['host'] . $uri;
        $content = '<?xml version="1.0" encoding="utf-8"?><Message xmlns="http://mns.aliyuncs.com/doc/v1/"><MessageBody>' . $content . '</MessageBody><MessageTag>' . $name . '</MessageTag></Message>';
        $date = gmdate('D, d M Y H:i:s T');
        $res  = \http::send([
            'method' => 'post',
            'url'    => $url,
            'header' => [
                'Authorization' => self::sign('POST', '', 'text/xml', $date, [
                    'x-mns-version' => '2015-06-06',
                ], $uri),
                'Content-Type'  => 'text/xml',
                'Date'          => $date,
                'x-mns-version' => '2015-06-06',
            ],
            'data'   => $content,
        ]);
        return $res;
    }
}