<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'MysqlPrivileges',
    properties: [
        new OA\Property(property: 'dbuser', type: 'string', example: 'johndoe_user'),
        new OA\Property(property: 'dbname', type: 'string', example: 'johndoe_wp'),
        new OA\Property(
            property: 'privileges',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['SELECT', 'INSERT', 'UPDATE', 'DELETE'],
        ),
    ],
    type: 'object',
)]
class MysqlPrivilegesSchema
{
}
