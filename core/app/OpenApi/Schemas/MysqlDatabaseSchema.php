<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'MysqlDatabase',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'johndoe_wp'),
        new OA\Property(property: 'charset', type: 'string', example: 'utf8mb4'),
        new OA\Property(property: 'collation', type: 'string', example: 'utf8mb4_unicode_ci'),
    ],
    type: 'object',
)]
class MysqlDatabaseSchema
{
}
