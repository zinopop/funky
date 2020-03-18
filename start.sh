#!/usr/bin/env bash
php /root/service/index.php start -d
/bin/sh -c "while true; do sleep 10; done"