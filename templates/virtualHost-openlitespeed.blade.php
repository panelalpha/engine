virtualhost {{ $domain }} {
  vhRoot                  /home/{{ $user }}{{ $relative_document_root }}
  configFile              $SERVER_ROOT/conf/vhosts/{{ $domain }}.conf
  allowSymbolLink         1
  enableScript            1
  restrained              1
  setUIDMode              0
}
