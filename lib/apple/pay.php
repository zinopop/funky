<?php
namespace apple;
use http;
class pay
{
    public static function validate($receipt_data,$is_produce = false){
        $re = self::acurl($receipt_data,$is_produce);
        return $re;
    }


    private static function acurl($receipt_data, $is_produce=false){
        $str = "{\"receipt-data\":\"{$receipt_data}\"}";
        $url_buy     = "https://buy.itunes.apple.com/verifyReceipt";
        $url_sandbox = "https://sandbox.itunes.apple.com/verifyReceipt";
        $url = !$is_produce ? $url_sandbox : $url_buy;
        $result = http::post($url,$str,["Content-type: application/json;charset='utf-8'"]);
        $data = json_decode($result,true);
        if($data['status'] == '21007') {
            $result = self::acurl($receipt_data,false);
            return json_decode($result,true);
        }else if ($data['status'] == '21008'){
            $result = self::acurl($receipt_data,true);
            return json_decode($result,true);
        }
        return $data;
    }
}