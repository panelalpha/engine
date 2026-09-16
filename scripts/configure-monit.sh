#!/bin/bash

example_conf_file="/opt/panelalpha/shared-hosting/scripts/monit.conf.example"
conf_file="/opt/panelalpha/shared-hosting/scripts/monit.conf"
monit_conf_file="/etc/monit/conf.d/panelalpha.conf"

example_script_file="/opt/panelalpha/shared-hosting/scripts/monit-script.sh.example"
script_file="/opt/panelalpha/shared-hosting/scripts/monit-script.sh"

# install monit
apt-get update
apt-get install -y monit

# create script file from example, only if not exists
cp -n $example_script_file $script_file
chmod +x $script_file

# create conf file from example, only if not exists
cp -n $example_conf_file $conf_file

# add config file to monit's conf.d
cp $conf_file $monit_conf_file

# restart monit to apply the new configuration
systemctl restart monit

echo -e "monit config applied ($monit_conf_file)"
