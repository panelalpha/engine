<virtualHost>
  <name>{{ $domain }}</name>
  <vhRoot>/home/{{ $user }}{{ $relative_document_root }}</vhRoot>
  <configFile>$SERVER_ROOT/conf/vhosts/{{ $domain }}.xml</configFile>
  <allowSymbolLink>1</allowSymbolLink>
  <enableScript>1</enableScript>
  <restrained>1</restrained>
  <setUIDMode>2</setUIDMode>
  <chrootMode>0</chrootMode>
</virtualHost>
