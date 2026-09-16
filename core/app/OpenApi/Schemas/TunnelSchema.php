<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Tunnel',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'hostname', type: 'string', example: 'shop.panelalpha.online'),
        new OA\Property(property: 'provider', type: 'string', enum: ['cloudflare', 'panelalpha'], example: 'panelalpha'),
        new OA\Property(property: 'domain', type: 'string', nullable: true, example: 'shop.example.com'),
        new OA\Property(property: 'url', type: 'string', example: 'https://shop.panelalpha.online/'),
        new OA\Property(property: 'details', type: 'object'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
class TunnelSchema
{
}
