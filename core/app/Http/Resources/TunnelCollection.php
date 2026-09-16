<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TunnelCollection extends ResourceCollection
{
    public $collects = TunnelResource::class;
}
