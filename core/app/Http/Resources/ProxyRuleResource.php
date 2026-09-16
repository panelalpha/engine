<?php

namespace App\Http\Resources;

use App\Models\ProxyRule;
use Illuminate\Http\Resources\Json\JsonResource;

class ProxyRuleResource extends JsonResource
{
    public function toArray($request)
    {
        $proxyRule = $this->resource;
        assert($proxyRule instanceof ProxyRule);

        return [
            'id' => $proxyRule->id,
            'owner_scope' => $proxyRule->owner_scope,
            'username' => $proxyRule->username,
            'enabled' => $proxyRule->enabled,
            'transport' => $proxyRule->transport,
            'listen_ip' => $proxyRule->listen_ip,
            'listen_port' => $proxyRule->listen_port,
            'server_name' => $proxyRule->server_name,
            'upstream_host' => $proxyRule->upstream_host,
            'upstream_port' => $proxyRule->upstream_port,
            'upstream_protocol' => $proxyRule->upstream_protocol,
            'is_generated' => $proxyRule->is_generated,
            'metadata' => $proxyRule->metadata,
            'created_at' => $proxyRule->created_at->toIso8601String(),
            'updated_at' => $proxyRule->updated_at->toIso8601String(),
        ];
    }
}
