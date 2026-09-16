@if ($client_area)
@if ($ca_port)
Listen {{ $ca_port }}
@endif
<VirtualHost @forelse ($ips_v4 as $ip) @if ($ca_port) {{ $ip }}:{{ $ca_port }} @else {{ $ip }}:80 {{ $ip }}:443 @endif @empty @if ($ca_port) *:{{ $ca_port }} @else *:80 *:443 @endif @endforelse @foreach ($ips_v6 as $ip) @if ($ca_port) [{{ $ip }}]:{{ $ca_port }} @else [{{ $ip }}]:80 [{{ $ip }}]:443 @endif @endforeach>
    Protocols h2 http/1.1
    ServerName {{ $ca_server_name }}

    SSLEngine on
    SSLCertificateFile {{ $ssl_cert_file }}
    SSLCertificateKeyFile {{ $ssl_cert_key_file }}

    CustomLog /opt/panelalpha/shared-hosting/webserver-logs/apache/app-lite/access.log combined
    ErrorLog /opt/panelalpha/shared-hosting/webserver-logs/apache/app-lite/error.log

    RewriteEngine On
    ProxyPreserveHost On
    ProxyRequests Off
    RequestHeader set X-Real-IP %{REMOTE_ADDR}s
    RequestHeader set X-Forwarded-For %{REMOTE_ADDR}s
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteRule /(.*) ws://{{ $ca_proxy_host }}:{{ $ca_proxy_port }}/$1 [P,L]

    # Normal HTTP proxy
    ProxyPass / http://{{ $ca_proxy_host }}:{{ $ca_proxy_port }}/
    ProxyPassReverse / http://{{ $ca_proxy_host }}:{{ $ca_proxy_port }}/
</VirtualHost>
@endif

@if ($admin_area)
@if ($aa_port)
Listen {{ $aa_port }}
@endif
<VirtualHost @forelse ($ips_v4 as $ip) @if ($aa_port) {{ $ip }}:{{ $aa_port }} @else {{ $ip }}:80 {{ $ip }}:443 @endif @empty @if ($aa_port) *:{{ $aa_port }} @else *:80 *:443 @endif @endforelse @foreach ($ips_v6 as $ip) @if ($aa_port) [{{ $ip }}]:{{ $aa_port }} @else [{{ $ip }}]:80 [{{ $ip }}]:443 @endif @endforeach>
    Protocols h2 http/1.1
    ServerName {{ $aa_server_name }}

    SSLEngine on
    SSLCertificateFile {{ $ssl_cert_file }}
    SSLCertificateKeyFile {{ $ssl_cert_key_file }}

    CustomLog /opt/panelalpha/shared-hosting/webserver-logs/apache/app-lite/access.log combined
    ErrorLog /opt/panelalpha/shared-hosting/webserver-logs/apache/app-lite/error.log

    RewriteEngine On
    ProxyPreserveHost On
    ProxyRequests Off
    RequestHeader set X-Real-IP %{REMOTE_ADDR}s
    RequestHeader set X-Forwarded-For %{REMOTE_ADDR}s
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteRule /(.*) ws://{{ $aa_proxy_host }}:{{ $aa_proxy_port }}/$1 [P,L]
    ProxyPass / http://{{ $aa_proxy_host }}:{{ $aa_proxy_port }}/
    ProxyPassReverse / http://{{ $aa_proxy_host }}:{{ $aa_proxy_port }}/
</VirtualHost>
@endif
