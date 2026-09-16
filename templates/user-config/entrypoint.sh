#!/bin/bash
set -e
service redis-server start
service cron start
echo "$(hostname -i) $(hostname) $(hostname).localhost" >> /etc/hosts
bash /entrypoint-runner.sh init --all
bash /entrypoint-runner.sh start --all
tail -f /dev/null
