<?php

namespace api\app;
use model\app;
use request;

class user
{
    public function __construct($method, request $request)
    {
        app::auth($request);
    }
}