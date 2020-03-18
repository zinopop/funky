<?php

namespace aliyun;

use config;

class sms
{
    public static function send($number, $tpl, $param)
    {
        $data = [
            'PhoneNumbers'  => $number,
            'SignName'      => config::get('aliyun.sms.sign'),
            'TemplateCode'  => $tpl,
            'Action'        => 'SendSms',
            'TemplateParam' => json_encode($param),
            'Version'       => '2017-05-25',
        ];
        $res  = common::post('http://dysmsapi.aliyuncs.com', $data, config::get('aliyun.sms.id'),
            config::get('aliyun.sms.secret'));
        return $res['Code'] == 'OK';
    }
}