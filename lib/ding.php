<?php

class ding
{

    public static function send($content)
    {
        if (!is_string($content)) {
            $content = json_encode($content);
        }
        $content = '[' . date('m-d H:i') . '] ' . config::get('server.name') . PHP_EOL . $content;
        http::json(config::get('ding'), [
            'msgtype' => 'text',
            'text'    => [
                'content' => $content,
            ],
        ]);
    }

    public static function sendMarkDown($content)
    {
        if (!is_array($content)) {
            $content = json_decode($content,true);
        }
        http::json(config::get('ding'), $content);
    }

}