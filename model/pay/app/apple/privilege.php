<?php
namespace model\pay\app\apple;

use apple\pay;

class privilege
{
    private static $instance;

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function execute($data,$is_produce = false)
    {
        $result = pay::validate($data,$is_produce);
        if($result['status'] === null){
            return ['苹果服务器未返回任何信息', false];
        }
        if($result['status'] !== 0){
            return [$result['status'], false];
        }
        //$result['receipt']['in_app'][0];
        return [array_pop($result['receipt']['in_app']), true];
    }
}