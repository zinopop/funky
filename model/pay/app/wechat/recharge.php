<?php

namespace model\pay\app\wechat;

use config;
use Yurun\PaySDK\Weixin\APP\Params\Client\Request;
use Yurun\PaySDK\Weixin\Params\PublicParams;
use Yurun\PaySDK\Weixin\SDK;
use Yurun\Util\YurunHttp;

class recharge
{

    private static $instance;

    private function __construct()
    {
        YurunHttp::setDefaultHandler('Yurun\Util\YurunHttp\Handler\Swoole');
    }

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function execute($data)
    {
        $params                      = new PublicParams;
        $params->appID               = config::get('pay.wechat.0.appId');
        $params->mch_id              = config::get('pay.wechat.0.mch_id');
        $params->key                 = config::get('pay.wechat.0.key');
        $pay                         = new SDK($params);
        $request_w                   = new \Yurun\PaySDK\Weixin\APP\Params\Pay\Request;
        $request_w->body             = $data['body'];                                          // 商品描述
        $request_w->out_trade_no     = $data['out_trade_no'];                                  // 订单号11
        $request_w->total_fee        = $data['total_fee'];                                     // 订单总金额，单位为：分
        $request_w->spbill_create_ip = $data['spbill_create_ip'];                              // 客户端ip，必须传正确的用户ip，否则会报错
        $request_w->notify_url       = config::get('server.hostNameApp') . 'wechatpay/notify'; // 异步通知地址
        // 调用接口
        $result = $pay->execute($request_w);
        if ($pay->checkResult()) {
            $clientRequest           = new Request;
            $clientRequest->prepayid = $result['prepay_id'];
            $pay->prepareExecute($clientRequest, $url, $data);

            foreach ($data as &$v) {
                $v = (string)$v;
            }
            unset($data['appid']);
            unset($data['sub_appid']);
            unset($data['sub_mch_id']);
            $return_data = [
                'order_no' => $request_w->out_trade_no,
                'sign'     => $data,
            ];
            return [$return_data, true];
        } else {
            return [$pay->getError(), false];
        }
    }

    public function notify($request)
    {
        $json_xml  = json_encode(simplexml_load_string($request->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA));
        $xml_array = json_decode($json_xml, true);
        if ($xml_array['return_code'] == "SUCCESS" && $xml_array['result_code'] == "SUCCESS") {
            foreach ($xml_array as $k => $v) {
                if ($k == 'sign') {
                    $xmlSign = $xml_array[$k];
                    unset($xml_array[$k]);
                };
            }
            $sign = http_build_query($xml_array);
            $sign = md5($sign . '&key=' . config::get('pay.wechat.0.key'));
            $sign = strtoupper($sign);
            if ($sign === $xmlSign) {
                return [$xml_array, true];

            }
            return ['验签失败', false];

        }
        return ['系统内部错误', false];
    }

    public function query($order_no)
    {
        $params                = new PublicParams;
        $params->appID         = config::get('pay.wechat.0.appId');
        $params->mch_id        = config::get('pay.wechat.0.mch_id');
        $params->key           = config::get('pay.wechat.0.key');
        $sdk                   = new SDK($params);
        $request               = new \Yurun\PaySDK\Weixin\OrderQuery\Request;
        $request->out_trade_no = $order_no;
        $result                = $sdk->execute($request);
        if (!$sdk->checkResult()) {
            return [$sdk->getError(), false];
        }
        if ($result['trade_state'] != "SUCCESS") {
            return ['订单支付失败', false];
        }
        return [$result, true];
    }
}

