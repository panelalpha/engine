<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'MysqlUser',
    properties: [
        new OA\Property(property: 'username', type: 'string', example: 'johndoe_user'),
        new OA\Property(property: 'host', type: 'string', example: 'localhost'),
    ],
    type: 'object',
)]
class MysqlUserSchema
{
}
