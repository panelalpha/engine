[program:cloudflared]
command=bash -c 'exec cloudflared --no-autoupdate tunnel run --token "$TUNNEL_TOKEN"'
directory=/
autostart={{ !empty($autostart) ? 'true' : 'false' }}
autorestart=true
priority=30
startsecs=3
startretries=10
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
@if (!empty($autostart) && !empty($tunnelToken))
environment=TUNNEL_TOKEN="{{ str_replace(['\\', '"'], ['\\\\', '\\"'], $tunnelToken) }}"
@endif
