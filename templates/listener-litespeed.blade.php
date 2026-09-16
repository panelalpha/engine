<listener>
  <name>{{ $ip  }}</name>
  <address>{{ $listen_address }}:80</address>
  <secure>0</secure>
  <vhostMapList>
@foreach($domains as $domain => $aliases)
    <vhostMap>
      <vhost>{{ $domain }}</vhost>
      <domain>{{ $domain }}@foreach($aliases as $alias), {{ $alias }}@endforeach</domain>
    </vhostMap>
@endforeach
@foreach($other_listener_maps as $vhost => $vhostDomains)
    <vhostMap>
      <vhost>{{ $vhost }}</vhost>
      <domain>{{ implode(',', $vhostDomains) }}</domain>
    </vhostMap>
@endforeach
    <vhostMap>
      <vhost>fallback</vhost>
      <domain>*</domain>
    </vhostMap>
  </vhostMapList>
</listener>

<listener>
  <name>{{ $ip  }}-SSL</name>
  <address>{{ $listen_address }}:443</address>
  <reusePort>1</reusePort>
  <secure>1</secure>
  <keyFile>/usr/local/lsws/admin/conf/webadmin.key</keyFile>
  <certFile>/usr/local/lsws/admin/conf/webadmin.crt</certFile>
  <vhostMapList>
@foreach($domains as $domain => $aliases)
    <vhostMap>
      <vhost>{{ $domain }}</vhost>
      <domain>{{ $domain }}@foreach($aliases as $alias), {{ $alias }}@endforeach</domain>
    </vhostMap>
@endforeach
@foreach($other_listener_maps as $vhost => $vhostDomains)
    <vhostMap>
      <vhost>{{ $vhost }}</vhost>
      <domain>{{ implode(',', $vhostDomains) }}</domain>
    </vhostMap>
@endforeach
    <vhostMap>
      <vhost>fallback</vhost>
      <domain>*</domain>
    </vhostMap>
  </vhostMapList>
</listener>
