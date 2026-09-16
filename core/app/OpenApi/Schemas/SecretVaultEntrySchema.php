<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SecretVaultEntry',
    properties: [
        new OA\Property(property: 'ref', type: 'string', nullable: true, description: 'The `vault:<id>` reference — only on create, never afterwards (only its hash is stored).', example: 'vault:7f3a9c2b5d1e4f6a8b0c2d4e6f8a0b2c4d5e6f7a8b9c'),
        new OA\Property(property: 'type', type: 'string', description: 'The request field the reference will be passed in (free-form snake_case).', example: 'git_token'),
        new OA\Property(property: 'url', type: 'string', nullable: true, description: 'The form the customer pastes the secret into (create only).', example: 'https://engine.example.com/vault/7f3a9c2b5d1e4f6a8b0c2d4e6f8a0b2c4d5e6f7a8b9c'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'filled', 'expired'], example: 'filled'),
        new OA\Property(property: 'expires_in', type: 'integer', nullable: true, description: 'Seconds the entry lives (create only).', example: 3600),
        new OA\Property(property: 'used_count', type: 'integer', example: 1),
        new OA\Property(property: 'last_used_at', type: 'string', nullable: true, example: '2026-09-11T10:00:00+00:00'),
        new OA\Property(property: 'created_at', type: 'string', nullable: true, example: '2026-09-11T09:00:00+00:00'),
        new OA\Property(property: 'expires_at', type: 'string', example: '2026-09-11T10:00:00+00:00'),
    ],
    type: 'object',)]
class SecretVaultEntrySchema
{
}