<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'FtpAccount',
    properties: [
        new OA\Property(property: 'username', type: 'string', example: 'johndoe_ftp'),
        new OA\Property(property: 'home_dir', type: 'string', example: '/home/johndoe/public_html'),
        new OA\Property(property: 'quota', type: 'integer', example: 1024, description: 'Quota in MB, -1 for unlimited', nullable: true),
    ],
    type: 'object',
)]
class FtpAccountSchema
{
}
