<?php

use Swoole\Event as swEvent;
use Swoole\Timer as swTimer;

class watcher
{

    private $files    = [];
    private $delay    = 0;
    private $callback = null;
    private $inotify  = null;
    private $timer    = 0;
    private $handle   = [];

    public function add($file)
    {
        $this->files[] = $file;
    }

    public function addAll($files)
    {
        $this->files = $files;
    }

    public function onUpdate($callback, $delay = 0)
    {
        $this->callback = $callback;
        $this->delay    = $delay;
    }

    public function start()
    {
        if (count($this->files) == 0 || !$this->callback) {
            return;
        }
        $this->inotify = inotify_init();
        foreach ($this->files as $file) {
            $this->watch(ROOT . '/' . $file);
        }
        swEvent::add($this->inotify, function ($inotify) {
            $event = inotify_read($inotify);
            foreach ($event as $e) {
                $file = $this->handle[$e['wd']] . '/' . $e['name'];
                if (is_dir($file)) {
                    $this->watch($file);
                }
            }
            swTimer::clear($this->timer);
            $this->timer = swTimer::after($this->delay, $this->callback);
        });
    }

    private function watch($file)
    {
        if (in_array($file, $this->handle)) {
            return;
        }
        $this->handle[inotify_add_watch($this->inotify, $file,
            IN_MODIFY | IN_CREATE | IN_DELETE | IN_MOVE | IN_ISDIR)] = $file;
        if (is_dir($file)) {
            $sub = scandir($file);
            foreach ($sub as $s) {
                if (in_array($s, ['.', '..'])) {
                    continue;
                }
                $sub_file = $file . '/' . $s;
                if (is_dir($sub_file)) {
                    $this->watch($sub_file);
                }
            }
        }
    }

}