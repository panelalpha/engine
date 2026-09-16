#!/bin/bash

monit_conf_file="/etc/monit/conf.d/panelalpha.conf"

rm $monit_conf_file || true

# restart monit to apply the new configuration
systemctl restart monit

echo -e "monit config removed ($monit_conf_file)"
