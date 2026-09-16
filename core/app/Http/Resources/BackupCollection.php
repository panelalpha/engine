<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BackupCollection extends ResourceCollection
{
    public $collects = BackupResource::class;
}
