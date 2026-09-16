<?php

namespace App\System\Project;

abstract class AbstractApplication
{
    abstract public function id(): string;

    abstract public function rootPath(): string;

    /**
     * @return list<\App\Models\Domain>
     */
    abstract public function domains(): array;
}
