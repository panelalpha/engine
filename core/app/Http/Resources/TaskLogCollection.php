<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TaskLogCollection extends ResourceCollection
{
    public $collects = TaskLogResource::class;

    public function __construct($resource, private readonly string $taskStatus, private readonly int $nextAfterId)
    {
        parent::__construct($resource);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function with($request): array
    {
        return [
            'meta' => [
                'next_after_id' => $this->nextAfterId,
                'task_status' => $this->taskStatus,
            ],
        ];
    }
}
