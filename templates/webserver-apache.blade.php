ServerRoot "/usr/local/apache2"
ServerName localhost

Listen 80
Listen 443

LoadModule mpm_event_module modules/mod_mpm_event.so
LoadModule authn_file_module modules/mod_authn_file.so
LoadModule authn_core_module modules/mod_authn_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule authz_groupfile_module modules/mod_authz_groupfile.so
LoadModule authz_user_module modules/mod_authz_user.so
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule access_compat_module modules/mod_access_compat.so
LoadModule auth_basic_module modules/mod_auth_basic.so
LoadModule reqtimeout_module modules/mod_reqtimeout.so
LoadModule filter_module modules/mod_filter.so
LoadModule mime_module modules/mod_mime.so
LoadModule log_config_module modules/mod_log_config.so
LoadModule env_module modules/mod_env.so
LoadModule headers_module modules/mod_headers.so
LoadModule setenvif_module modules/mod_setenvif.so
LoadModule version_module modules/mod_version.so
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_http_module modules/mod_proxy_http.so
LoadModule proxy_fcgi_module modules/mod_proxy_fcgi.so
LoadModule ssl_module modules/mod_ssl.so
LoadModule unixd_module modules/mod_unixd.so
LoadModule status_module modules/mod_status.so
LoadModule autoindex_module modules/mod_autoindex.so
LoadModule dir_module modules/mod_dir.so
LoadModule alias_module modules/mod_alias.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule remoteip_module modules/mod_remoteip.so

<IfModule unixd_module>
    User www-data
    Group www-data
</IfModule>

ServerAdmin you@example.com

<Directory />
    AllowOverride none
    Require all denied
</Directory>

DocumentRoot "/usr/local/apache2/htdocs"
<Directory "/usr/local/apache2/htdocs">
    Options Indexes FollowSymLinks
    AllowOverride None
    Require all granted
</Directory>

<IfModule dir_module>
    DirectoryIndex index.html
</IfModule>

<Files ".ht*">
    Require all denied
</Files>

ErrorLog /opt/panelalpha/shared-hosting/webserver-logs/apache/error.log
LogLevel warn

<IfModule log_config_module>
    LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\"" combined
    LogFormat "%h %l %u %t \"%r\" %>s %b" common

    <IfModule logio_module>
      LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\" %I %O" combinedio
    </IfModule>

    CustomLog /opt/panelalpha/shared-hosting/webserver-logs/apache/access.log combined
</IfModule>

<IfModule alias_module>
    ScriptAlias /cgi-bin/ "/usr/local/apache2/cgi-bin/"
</IfModule>

<Directory "/usr/local/apache2/cgi-bin">
    AllowOverride None
    Options None
    Require all granted
</Directory>

<IfModule headers_module>
    RequestHeader unset Proxy early
</IfModule>

<IfModule mime_module>
    TypesConfig conf/mime.types
    AddType application/x-compress .Z
    AddType application/x-gzip .gz .tgz
</IfModule>

<IfModule proxy_html_module>
    Include conf/extra/proxy-html.conf
</IfModule>

IncludeOptional /opt/panelalpha/shared-hosting/webserver-config/apache/cloudflare-realip.conf
IncludeOptional conf.d/*.conf

@if(empty($ips_v4))
<VirtualHost *:80>
@else
<VirtualHost @foreach($ips_v4 as $ip){{ $ip }}:80 @endforeach @foreach($ips_v6 as $ip)[{{ $ip }}]:80 @endforeach>
@endif
    ServerName default.invalid
    ServerAlias *
    DocumentRoot /opt/panelalpha/shared-hosting/webserver-config/document-root
    <Directory /opt/panelalpha/shared-hosting/webserver-config/document-root>
        Require all granted
        AllowOverride None
    </Directory>
    ErrorDocument 404 /404.html
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/404\.html$
    RewriteRule ^ /404.html [L]
</VirtualHost>

@if(empty($ips_v4))
<VirtualHost *:443>
@else
<VirtualHost @foreach($ips_v4 as $ip){{ $ip }}:443 @endforeach @foreach($ips_v6 as $ip)[{{ $ip }}]:443 @endforeach>
@endif
    ServerName default.invalid
    ServerAlias *
    DocumentRoot /opt/panelalpha/shared-hosting/webserver-config/document-root
    SSLEngine on
    SSLCertificateFile    {{ $ssl_cert_file }}
    SSLCertificateKeyFile {{ $ssl_cert_key_file }}
    <Directory /opt/panelalpha/shared-hosting/webserver-config/document-root>
        Require all granted
        AllowOverride None
    </Directory>
    ErrorDocument 404 /404.html
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/404\.html$
    RewriteRule ^ /404.html [L]
</VirtualHost>

<IfModule ssl_module>
    SSLRandomSeed startup builtin
    SSLRandomSeed connect builtin
</IfModule>

@if (!empty($modsecurity_enabled))
LoadModule security3_module /usr/lib/apache2/modules/mod_security3.so
modsecurity on
modsecurity_rules_file /opt/modsecurity/main.conf
@endif
