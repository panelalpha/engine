#!/bin/bash
 
sysctl_file="/etc/sysctl.d/99-panelalpha.conf"

rm $sysctl_file || true
 
# Apply the new sysctl settings
sysctl --system

echo -e "sysctl config removed ($sysctl_file)"
