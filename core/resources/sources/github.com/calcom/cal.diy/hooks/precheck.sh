#!/bin/bash
# Require 10GB of free disk space
REQUIRED_SPACE=$((10 * 1024 * 1024))
AVAILABLE_SPACE=$(df -k . | awk 'NR==2 {print $4}')
if [ "$AVAILABLE_SPACE" -lt "$REQUIRED_SPACE" ]; then
    echo "Error: Less than 10GB of disk space available."
    exit 1
fi
