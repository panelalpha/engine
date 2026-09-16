<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SftpAccount',
    properties: [
        new OA\Property(property: 'username', type: 'string', example: 'johndoe_sftp'),
        new OA\Property(property: 'home_dir', type: 'string', example: '/home/johndoe/public_html'),
    ],
    type: 'object',
)]
class SftpAccountSchema
{
}
