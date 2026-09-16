<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Usage',
    properties: [
        new OA\Property(property: 'disk_used', type: 'integer', example: 512, description: 'Disk used in MB'),
        new OA\Property(property: 'disk_limit', type: 'integer', example: 10240, description: 'Disk limit in MB, -1 for unlimited'),
        new OA\Property(property: 'bandwidth_used', type: 'integer', example: 1024, description: 'Bandwidth used in MB', nullable: true),
        new OA\Property(property: 'bandwidth_limit', type: 'integer', example: 102400, description: 'Bandwidth limit in MB, -1 for unlimited', nullable: true),
        new OA\Property(property: 'inodes_used', type: 'integer', example: 25000, nullable: true),
        new OA\Property(property: 'inodes_limit', type: 'integer', example: 500000, nullable: true),
    ],
    type: 'object',
)]
class UsageSchema
{
}
