<?php

namespace log;

class common
{
    private $moudle;
    private $moudle_name;
    private $user_id;
    private $type     = 'admin';
    private $request_data;
    private $response_data;
    private $addtime;
    private $ip;
    private $log_flag = 1;

    public function __construct($moudle_name, $request, $response_data = [], $type = 'admin')
    {
        $this->addtime       = \functions::date();
        $this->request_data  = $request->post() ? $request->post() : $request->get();
        $this->response_data = $response_data;
        $this->moudle_name   = $moudle_name;
        $this->user_id       = $request->session($type);
        $this->ip            = $request->ip();
        $this->moudle        = $request->server('path_info');
        $this->log_flag      = \config::get('server.log');
        $this->type          = $type;
    }

    public function record()
    {
        if ($this->log_flag) {
            $db = new \db();
            $db->insert('log', [
                'moudle'      => $this->moudle,
                'moudle_name' => $this->moudle_name,
                'addtime'     => $this->addtime,
                'user_id'     => $this->user_id,
                'type'        => $this->type,
                'request'     => json_encode($this->request_data, true),
                'response'    => json_encode($this->response_data, true),
                'ip'          => $this->ip,
            ]);
        }

    }

}