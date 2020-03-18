<?php
namespace juhe;
use config;
use http;

class common
{
    public static function getIpInfo($ip, $detail = false) {
        $params = array(
            "ip" => $ip,
            "key" => config::get('juhe.ipInfo.key'),
            "dtype" => "json",
        );
        $result = http::send([
            'method' => 'post',
            'url'    => config::get('juhe.ipInfo.url'),
            'data'   => $params
        ]);

        if($result['status'] == "200"){
            $result_son = json_decode($result['content'],true);
            if($result_son['error_code'] === 0){
                if(!$detail){
                    return $result_son['result']['City'];
                }
                return $result_son['result'];
            }
        }
        return null;

    }

    public static function getWeatherData($city)
    {
        $params = array(
            "cityname" => $city,
            "key" => config::get('juhe.weather.key'),
            "dtype" => "json",
        );
        $result = http::send([
            'method' => 'post',
            'url'    => config::get('juhe.weather.url'),
            'data'   => $params
        ]);
        if($result['status'] == "200"){
            $result_son = json_decode($result['content'],true);
            if($result_son['error_code'] === 0){
                return $result_son['result'];
            }
        }
        return null;
    }

    public static function getCityList()
    {
        $params = array(
            "key" => '475adc3c58b3eb99efefa060f5a3c5c7',
        );
        $result = http::send([
            'method' => 'post',
            'url'    => 'http://apis.juhe.cn/simpleWeather/cityList',
            'data'   => $params
        ]);
        if($result['status'] == "200"){
            $result_son = json_decode($result['content'],true);
            if($result_son['error_code'] === 0){
                return $result_son['result'];
            }
        }
        return null;
    }
}