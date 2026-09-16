# Turn the engine on globally
SecRuleEngine {{ $SecRuleEngine }}

# Request body settings
SecRequestBodyAccess On
SecRequestBodyLimit 13107200

# Response body settings (disable unless you really need it)
SecResponseBodyAccess Off

# Audit logging
SecAuditEngine RelevantOnly
SecAuditLog /opt/panelalpha/shared-hosting/logs/modsecurity/audit.log
SecAuditLogFormat JSON
SecAuditLogParts ABFHZ

# Debug logging (optional, noisy, enable only for troubleshooting)
# SecDebugLog /opt/panelalpha/shared-hosting/logs/modsecurity/debug.log
# SecDebugLogLevel 3

@foreach($includeFiles as $includeFile)
Include {{ $includeFile }}
@endforeach
SecRule REQUEST_URI "@beginsWith /phpmyadmin" "id:1000001,phase:1,pass,nolog,ctl:ruleRemoveById=949110"
