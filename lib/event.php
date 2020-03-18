<?php

class event
{

    public static function publish($name)
    {
        $hooks = config::get('event.' . $name);
        if (!$hooks) {
            return;
        }
        $args = func_get_args();
        unset($args[0]);
        foreach ($hooks as $hook) {
            go(function () use ($hook, $args) {
                try {
                    call_user_func_array('model\\' . $hook, $args);
                } catch (Exception|Error $e) {
                    ding::send($hook . PHP_EOL . functions::formatException(null, $e));
                }
            });
        }

    }

}