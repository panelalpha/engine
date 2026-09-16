listener {{ $ip  }} {
  address                 {{ $listen_address }}:80
  secure                  0
@foreach($domains as $domain => $aliases)
  map {{ $domain }} {{ $domain }}@foreach($aliases as $alias), {{ $alias }}@endforeach 
@endforeach
@foreach($other_listener_maps as $vhost => $vhostDomains)
  map {{ $vhost }} {{ implode(',', $vhostDomains) }}
@endforeach
  map fallback *
}

listener {{ $ip }}-SSL {
  address                 {{ $listen_address }}:443
  secure                  1
  keyFile                 /usr/local/lsws/admin/conf/webadmin.key
  certFile                /usr/local/lsws/admin/conf/webadmin.crt
@foreach($domains as $domain => $aliases)
  map {{ $domain }} {{ $domain }}@foreach($aliases as $alias), {{ $alias }}@endforeach 
@endforeach
@foreach($other_listener_maps as $vhost => $vhostDomains)
  map {{ $vhost }} {{ implode(',', $vhostDomains) }}
@endforeach
  map fallback *
}
