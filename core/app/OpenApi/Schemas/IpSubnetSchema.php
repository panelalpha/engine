<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'IpSubnet',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'subnet', type: 'string', example: '192.168.1.0/24'),
        new OA\Property(property: 'gateway', type: 'string', example: '192.168.1.1', nullable: true),
        new OA\Property(property: 'version', type: 'integer', example: 4, enum: [4, 6]),
    ],
    type: 'object',
)]
class IpSubnetSchema
{
}
