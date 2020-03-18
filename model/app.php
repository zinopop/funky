<?php

namespace model;

use cache;
use config;
use db;
use request;

class app
{

    public static function auth(request $request)
    {
        if (!config::get('server.mode')) {
            return;
        }

        $timestamp = $request->arg('timestamp', ['required' => true, 'type' => 'int']);
        $nonce     = $request->arg('nonce', ['required' => true, 'min' => 32]);
        $sign      = $request->arg('sign', ['required' => true]);
        $user_id   = $request->arg('user_id');
        $device_id = $request->arg('device_id');
        $now       = time();

        if (abs($timestamp - $now) > 60) {
            $request->error(null, 'request timeout', 403);
        }

        $cache       = new cache('app_nonce');
        $nonce_exist = $cache->get($nonce);
        if ($nonce_exist) {
            $request->error(null, 'request duplicate', 403);
        }
        $cache->set($nonce, true, 60);

        $args = $request->arg();
        unset($args['sign']);
        ksort($args);
        if ($user_id) {
            $user = user::info($user_id);
            if (!$user) {
                $request->error(null, 'user not exist', 401);
            }
            if (!$user['is_enable']) {
                $request->error(null, 'user disabled', 401);
            }
            if (!$user['device'][$device_id]) {
                $request->error(null, 'device not exist', 401);
            }
            if ($sign != hash_hmac('sha256', http_build_query($args),
                    config::get('app.key') . $user['device'][$device_id]['key'])) {
                $request->error(null, 'request reject', 401);
            }
        } else {
            if ($sign != hash_hmac('sha256', http_build_query($args), config::get('app.key'))) {
                $request->error(null, 'request reject', 401);
            }
        }
    }

    public static function encrypt($data, $compress = false)
    {
        if ($compress) {
            $data = gzencode($data, 9);
        }
        $key = config::get('app.key');
        $iv  = substr(config::get('app.key'), 0, 16);
        return openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    }

    public static function decrypt($data, $compress = false)
    {
        $key  = config::get('app.key');
        $iv   = substr(config::get('app.key'), 0, 16);
        $data = openssl_decrypt($data, 'AES-256-CBC', $key, 0, $iv);
        if ($compress) {
            $data = gzdecode($data);
        }
        return $data;
    }

    public static function setConfig($name,$val,$type,$describe)
    {
        $db = new \db();
        $row = $db->row('select id from app_config where `name` = ?',[$name]);
        if($row){
            $state = $db->update('app_config',[
                'val' => $val,
                'type' => $type,
                'describe' => $describe
            ],'`name` = ?',[$name]);
            if(!$state){
                return ['设置失败',false];
            }
        }else{
            $row = $db->row('select id from app_config where `name` = ?',[$name]);
            if($row) {
                return ['记录已存在,不可重复添加',false];
            }
            $id = $db->insert('app_config',[
                'name' => $name,
                'val' => $val,
                'type'=>$type,
                'describe'=>$describe
            ]);
            if(!$id){
                return ['插入失败',false];
            }
        }
        self::getConfig(null,true);
        return ['设置成功',true];
    }

    public static function getConfig($key = null,$force = false)
    {
        $cache = new cache('app', 'default');
        $data  = $cache->get('config');
        if ($data && !$force) {
            if($key){
                foreach ($data as $k => $v){
                    if($v['name'] == $key){
                        return ['val'=>$v['val'],'type'=>$v['type']];
                        break;
                    }
                }
                return null;
            }
            return $data;
        }
        $db   = new db();
        $data = $db->query('select * from app_config');
        if (!$data) {
            return null;
        }
        $cache->set('config', $data, 7200);
        if($key){
            foreach ($data as $k => $v){
                if($v['name'] == $key){
                    return ['val'=>$v['val'],'type'=>$v['type']];
                    break;
                }
            }
            return null;
        }
        return $data;
    }


    public static function delConfig($name)
    {
        $db = new db();
        $state = $db->delete('app_config','name = ?',[$name]);
        if(!$state){
            return ['失败',false];
        }
        return [self::getConfig(null,true),true];
    }

    public static function initCache()
    {
//        $cache = new cache('app', 'default');
//        $cache->delete('config');
    }
}