<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AppUser',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
        new OA\Property(property: 'role', type: 'string', example: 'administrator', nullable: true),
        new OA\Property(property: 'display_name', type: 'string', example: 'John Doe', nullable: true),
    ],
    type: 'object',
)]
class AppUserSchema
{
}
