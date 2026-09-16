<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CsfRule',
    properties: [
        new OA\Property(property: 'line', type: 'string', example: '192.168.1.100 # Blocked by admin'),
        new OA\Property(property: 'line_md5', type: 'string', example: 'd41d8cd98f00b204e9800998ecf8427e'),
        new OA\Property(property: 'type', type: 'string', example: 'deny', enum: ['deny', 'allow']),
    ],
    type: 'object',
)]
class CsfRuleSchema
{
}
