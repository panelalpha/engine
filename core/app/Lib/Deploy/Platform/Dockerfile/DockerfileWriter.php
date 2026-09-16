<?php

namespace App\Lib\Deploy\Platform\Dockerfile;

/**
 * One shape of generated Dockerfile: what a stub under
 * `resources/deploy/templates/dockerfile/` needs to be filled in with.
 */
interface DockerfileWriter
{
    public function render(): string;
}
