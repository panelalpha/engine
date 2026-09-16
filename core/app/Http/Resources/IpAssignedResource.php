<?php

namespace App\Http\Resources;

use App\Models\IpAssigned;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IpAssignedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ip = $this->resource;
        assert($ip instanceof IpAssigned);

        return [
            'id' => $ip->id,
            'user_id' => $ip->user_id,
            'username' => $ip->user->username,
            'domain_names' => $ip->user->listAllDomainNames(),
            'ip_subnet_id' => $ip->ip_subnet_id,
            'ip_address' => $ip->ip_address,
            'created_at' => $ip->created_at,
            'updated_at' => $ip->updated_at,
        ];
    }
}
