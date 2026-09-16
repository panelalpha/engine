<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AssignedIp',
    properties: [
        new OA\Property(property: 'ip', type: 'string', example: '192.168.1.10'),
        new OA\Property(property: 'username', type: 'string', example: 'johndoe'),
        new OA\Property(property: 'version', type: 'integer', example: 4, enum: [4, 6]),
    ],
    type: 'object',
)]
class AssignedIpSchema
{
}
