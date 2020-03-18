#!/usr/bin/env bash
apt-get update -y
apt-get install -y software-properties-common apt-transport-https lsb-release ca-certificates libzip-dev
wget -O /etc/apt/trusted.gpg.d/php.gpg https://mirror.xtom.com.hk/sury/php/apt.gpg
sh -c 'echo "deb https://mirror.xtom.com.hk/sury/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'
apt-get update -y
apt-get install -y --allow-unauthenticated git zip php7.3 php7.3-gd php7.3-dev php7.3-xml php7.3-mbstring php7.3-curl lame
systemctl stop apache2
git clone --branch v4.4.16 https://github.com/swoole/swoole-src/
cd swoole-src
phpize
./configure --enable-mysqlnd --enable-openssl
make
make install
echo -e "\nextension = swoole.so" >> /etc/php/7.3/cli/php.ini
cd ../
rm -rf swoole-src
pecl install inotify
pecl install zip
echo -e "\nextension = inotify.so" >>/etc/php/7.3/cli/php.ini
echo -e "\nextension = zip.so" >>/etc/php/7.3/cli/php.ini
curl -sS https://getcomposer.org/installer -o composer.php
php composer.php --install-dir=/usr/local/bin --filename=composer
composer install
rm composer.php