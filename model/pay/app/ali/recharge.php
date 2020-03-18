<?php

namespace model\pay\app\ali;

use config;
use Yurun\PaySDK\AlipayApp\App\Params\Pay\Request;
use Yurun\PaySDK\AlipayApp\Params\PublicParams;
use Yurun\PaySDK\AlipayApp\SDK;
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
        $params                                     = new PublicParams;
        $params->appID                              = config::get('pay.ali.0.appId');
        $params->appPrivateKey                      = config::get('pay.ali.0.rsaPrivateKey');
        $pay                                        = new SDK($params);
        $request_y                                  = new Request;
        $request_y->notify_url                      = config::get('server.hostNameApp') . 'alipay/notify';
        $request_y->businessParams->out_trade_no    = $data['out_trade_no'];
        $request_y->businessParams->total_amount    = $data['total_amount'];
        $request_y->businessParams->subject         = $data['subject'];
        $request_y->businessParams->passback_params = $data['passback_params'];
        $pay->prepareExecute($request_y, $url, $data);
        if (!$url) {
            return ['系统错误:返回url为空', false];
        }
        if (!$data) {
            return ['系统错误:参数为空', false];
        }
        foreach ($data as $k => $v) {
            if (!$v) {
                return ['系统错误:参数' . $k . '为空', false];
            }
        }
        $str  = substr($url, strripos($url, "?") + 1);
        $data = [
            'order_no' => $request_y->businessParams->out_trade_no,
            'sign'     => $str,
        ];
        return [$data, true];
    }

    public function notify($request)
    {
        $params                = new PublicParams;
        $params->appPublicKey  = config::get('pay.ali.0.aliPayrsaPublic');
        $params->appPrivateKey = config::get('pay.ali.0.rsaPrivateKey');
        $pay                   = new SDK($params);
        if ($pay->verifyCallback($request->arg())) {
            return [$request->arg(), true];
        }
        return ['验签失败', false];
    }

    public function return_note($request)
    {
        $params                = new PublicParams;
        $params->appPublicKey  = config::get('pay.ali.0.aliPayrsaPublic');
        $params->appPrivateKey = config::get('pay.ali.0.rsaPrivateKey');
        $pay                   = new SDK($params);
        if ($pay->verifyCallback($request->arg())) {
            return [$request->arg(), true];
        }
        return ['验签失败', false];
    }

    public function query($order_no, $trade_no = null)
    {
        $params                = new PublicParams;
        $params->appID         = config::get('pay.ali.0.appId');
        $params->appPublicKey  = config::get('pay.ali.0.aliPayrsaPublic');
        $params->appPrivateKey = config::get('pay.ali.0.rsaPrivateKey');
        $pay                   = new SDK($params);
        $request               = new \Yurun\PaySDK\AlipayApp\Params\Query\Request;

        $request->businessParams->out_trade_no = $order_no; // 订单支付时传入的商户订单号,和支付宝交易号不能同时为空。
        if ($trade_no) {
            $request->businessParams->trade_no = $trade_no;
        } // 支付宝交易号，和商户订单号不能同时为空
        $result = $pay->execute($request);
        if (!$pay->checkResult()) {
            return [$pay->getError(), false];
        }
        if ($result['alipay_trade_query_response']['trade_status'] != "TRADE_SUCCESS") {
            return ['订单支付失败', false];
        }
        return [$result['alipay_trade_query_response'], true];
    }
}