<?php

namespace aliyun;

use config;

class push
{
    public static function device(
        $device_id,
        $title,
        $body,
        $pushType = 'MESSAGE',
        $device_type = 'android',
        $all = false
    ) {
        $pushData = [];
        if ($device_type == "ios") {
            $pushData = [
                'Action'           => 'Push',
                'Target'           => $all ? 'ALL' : 'DEVICE',
                'TargetValue'      => $all ? 'ALL' : $device_id,
                'DeviceType'       => 'iOS',
                'PushType'         => $pushType,
                'Title'            => $title,
                'Body'             => $title,
                'iOSExtParameters' => json_encode([
                    'datas' => is_string($body) ? $body : json_encode($body)
                ]),
                'iOSApnsEnv'       => 'DEV',
            ];
        } else {
            if ($device_type == "android") {
                $pushData = [
                    'Action'               => 'Push',
                    'Target'               => $all ? 'ALL' : 'DEVICE',
                    'TargetValue'          => $all ? 'ALL' : $device_id,
                    'DeviceType'           => 'ANDROID',
                    'PushType'             => $pushType,
                    'Title'                => $title,
                    'Body'                 => $title,
                    'AndroidExtParameters' => json_encode([
                        'datas' => is_string($body) ? $body : json_encode($body)
                    ]),
                    'StoreOffline'         => "true",
                    'AndroidRemind'        => "true",
                    'AndroidPopupTitle'    => $title,
                    'AndroidPopupBody'     => is_string($body) ? $body : json_encode($body)
                ];
            }
        }
        $result = self::post($pushData, $device_type);
        if (!$result['MessageId']) {
            return [$result['Message'], false];
        }
        return [$result['MessageId'], true];
    }

    private static function post($data, $device_type = 'android')
    {
        $data['AppKey']  = config::get('aliyun.push.key.' . $device_type);
        $data['Version'] = '2016-08-01';
        $result          = common::post('http://cloudpush.aliyuncs.com/', $data, config::get('aliyun.push.id'),
            config::get('aliyun.push.secret'));
        return $result;
    }

    public static function cronPush(
        $device_id,
        $title,
        $body,
        $pushTime,
        $pushType = 'NOTICE',
        $device_type = 'android'
    ) {
        $pushData = [];
        switch ($device_type) {
            case 'ios':
                $pushData = [
                    'Action'           => 'Push',
                    'Target'           => 'DEVICE',
                    'TargetValue'      => $device_id,
                    'DeviceType'       => 'ALL',
                    'PushType'         => $pushType,
                    'Title'            => $title,
                    'Body'             => $title,
                    //'iOSMusic'    => 'Library/Sounds',
                    'iOSExtParameters' => json_encode([
                        'datas' => is_string($body) ? $body : json_encode($body)
                    ]),
                    'iOSApnsEnv'       => 'DEV',
                    'PushTime'         => $pushTime ? $pushTime : gmdate('Y-m-d\TH:i:s\Z', strtotime('+15 second'))
                ];
                break;
            case 'android':
                $pushData = [
                    'Action'               => 'Push',
                    'Target'               => 'DEVICE',
                    'TargetValue'          => $device_id,
                    'DeviceType'           => 'ALL',
                    'PushType'             => $pushType,
                    'Title'                => $title,
                    'Body'                 => $title,
                    'AndroidExtParameters' => json_encode([
                        'datas' => is_string($body) ? $body : json_encode($body)
                    ]),
                    'StoreOffline'         => "true",
                    'AndroidRemind'        => "true",
                    'AndroidPopupTitle'    => $title,
                    'AndroidPopupBody'     => is_string($body) ? $body : json_encode($body),
                    'PushTime'             => $pushTime ? $pushTime : gmdate('Y-m-d\TH:i:s\Z', strtotime('+15 second'))
                ];
                break;
        }
        $result = self::post($pushData, $device_type);
        if (!$result['MessageId']) {
            return [$result['Message'], false];
        }
        return [$result['MessageId'], true];
    }

    public static function pushCancel($messageId)
    {
        $result = self::post([
            'Action'    => 'CancelPush',
            'MessageId' => $messageId
        ]);
        if ($result['Message']) {
            return [$result['Message'], false];
        }
        return [$result['RequestId'], true];
    }

    public static function alias($name, $title, $body, $type = 'MESSAGE', $device_type = 'android')
    {
        self::post([
            'Action'      => 'Push',
            'Target'      => 'ALIAS',
            'TargetValue' => $name,
            'DeviceType'  => 'ALL',
            'PushType'    => $type,
            'Title'       => $title,
            'Body'        => is_string($body) ? $body : json_encode($body),
            'iOSApnsEnv'  => 'DEV',
        ], $device_type);
    }

    public static function bindAlias($name, $id, $device_type = 'android')
    {
        self::post([
            'Action'    => 'BindAlias',
            'DeviceId'  => $id,
            'AliasName' => $name,
        ], $device_type);
    }

    public static function unbindAlias($name, $id, $device_type = 'android')
    {
        self::post([
            'Action'    => 'UnbindAlias',
            'DeviceId'  => $id,
            'AliasName' => $name,
        ], $device_type);
    }

    public static function queryDevicesByAlias($alias, $device_type = 'android')
    {
        return self::post([
            'Action' => 'QueryDevicesByAlias',
            'Alias'  => $alias
        ], $device_type);
    }

    public static function massPush($pushTasks, $device_type = 'android')
    {
        $requstData = [
            'Action' => 'MassPush'
        ];
        if(count($pushTasks) > 100){
            return ['任务不能超过100个',false];
        }
        if ($pushTasks) {
            $i = 1;
            foreach ($pushTasks as $k => &$v) {
                foreach ($v as $ks => &$vs) {
                    $requstData['PushTask.' . $i . '.' . $ks] = $vs;
                }
                $i++;
            }
        }
        $result = self::post($requstData, $device_type);
        if (!$result['MessageIds']) {
            return [$result['Message'], false];
        }
        return [$result['MessageIds']['MessageId'], true];
    }

    public static function handlePushData($device_id, $title, $body, $pushType, $device_type, $pushTime = null)
    {
        $result = [];
        switch ($device_type) {
            case 'ios':
                $result = [
                    'Target'           => 'DEVICE',
                    'TargetValue'      => $device_id,
                    'DeviceType'       => 'iOS',
                    'PushType'         => $pushType,
                    'Title'            => $title,
                    'Body'             => $title,
                    'iOSExtParameters' => json_encode([
                        'datas' => is_string($body) ? $body : json_encode($body)
                    ]),
                    'iOSApnsEnv'       => 'DEV'
                ];
                if ($pushTime) {
                    $result['PushTime'] = $pushTime;
                }
                break;
            case 'android':
                $result = [
                    'Target'               => 'DEVICE',
                    'TargetValue'          => $device_id,
                    'DeviceType'           => 'ANDROID',
                    'PushType'             => $pushType,
                    'Title'                => $title,
                    'Body'                 => $title,
                    'AndroidExtParameters' => json_encode([
                        'datas' => is_string($body) ? $body : json_encode($body)
                    ]),
                    'StoreOffline'         => "true",
                    'AndroidRemind'        => "true",
                    'AndroidPopupTitle'    => $title,
                    'AndroidPopupBody'     => is_string($body) ? $body : json_encode($body)
                ];
                if ($pushTime) {
                    $result['PushTime'] = $pushTime;
                }
                break;
        }
        return $result;
    }
}