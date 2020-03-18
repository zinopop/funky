<?php

namespace aliyun;

class asr
{
    public static function getToken()
    {
        return common::post('http://nls-meta.cn-shanghai.aliyuncs.com/', [
            'Action'   => 'CreateToken',
            'Version'  => '2019-02-28',
            'RegionId' => 'cn-shanghai',
        ], \config::get('aliyun.asr.id'),
            \config::get('aliyun.asr.secret'));
    }
}