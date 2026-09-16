<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BackupContainerCollection extends ResourceCollection
{
    public $collects = BackupContainerResource::class;
}
