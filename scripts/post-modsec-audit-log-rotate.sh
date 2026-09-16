#!/bin/bash

ENGINE_DIR=/opt/panelalpha/shared-hosting

CURRENT_WEBSERVER=$(docker compose -f $ENGINE_DIR/docker-compose.yml ps -a sites-http --format json | jq '.Labels' | tr ',' '\n'  | awk -F= '$1=="com.panelalpha.webserver"{print $2}')

case $CURRENT_WEBSERVER in
    nginx)
        docker compose -f $ENGINE_DIR/docker-compose.yml exec sites-http service nginx restart
        ;;
    nginx-proxy)
        docker compose -f $ENGINE_DIR/docker-compose.yml exec sites-http service nginx restart
        ;;
    apache)
        docker compose -f $ENGINE_DIR/docker-compose.yml exec sites-http apachectl restart
        ;;
esac
