#!/bin/bash

# printenv | grep -v "no_proxy" >> /etc/environment

# bash -c "/usr/bin/sqlite3 /var/www/status.db < /var/www/schema.sql"
# bash -c "composer install"
# bash -c "php -r \"require '/var/www/libs/proxies.php'; proxies();\""
# bash -c "cron && nohup php /var/www/receiver.php > /dev/null 2> /dev/null & echo $! && nohup php /var/www/receiver_sellers.php > /dev/null 2> /dev/null & echo $! && tail -f /var/log/pricechange.log"
bash -c "cron && tail -f /var/www/html/cron.log"
