<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ServerMetrics',
    properties: [
        new OA\Property(property: 'cpu', type: 'number', format: 'float', example: 12.5, description: 'CPU usage in percent'),
        new OA\Property(property: 'memory', type: 'number', format: 'float', example: 45.2, description: 'Memory usage in percent'),
        new OA\Property(property: 'disk', type: 'number', format: 'float', example: 68.0, description: 'Disk usage in percent'),
        new OA\Property(property: 'load_avg', type: 'number', format: 'float', example: 0.85, nullable: true),
        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
class ServerMetricsSchema
{
}
