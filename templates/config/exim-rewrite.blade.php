@if (!empty($sender_domain))
*  ${local_part}_at_${domain}{{ '@' }}{{ $sender_domain }} Ffrs
@else
* ${address:$header_from:} F
@endif
