<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'username', type: 'string', example: 'johndoe'),
        new OA\Property(property: 'domain', type: 'string', example: 'johndoe.example.com'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com', nullable: true),
        new OA\Property(property: 'status', type: 'string', example: 'active', enum: ['active', 'suspended']),
        new OA\Property(property: 'config', type: 'object', nullable: true),
        new OA\Property(property: 'details', type: 'object', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
class UserSchema
{
}
