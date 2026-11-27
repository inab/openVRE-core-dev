#!/bin/bash

# SGE master for the docker_SGE launcher; images built without SGE skip this.
if [ -d /var/lib/gridengine/default/common ]; then
    echo "sgecore" > /var/lib/gridengine/default/common/act_qmaster
fi
rm -rf /var/www/html/openVRE/public/assets/global/plugins/*
mkdir -p /var/www/html/openVRE/public/assets/global/plugins
cp -r /var/www/html/openVRE/plugins-to-copy/. /var/www/html/openVRE/public/assets/global/plugins
