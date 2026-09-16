<?php

namespace App\Lib\Deploy\Platform\Dockerfile;

use App\Lib\Deploy\Template\TemplateLoader;

/**
 * The `.dockerignore` the engine writes when a project has none; a project's
 * own is left as the customer wrote it.
 *
 * `.git` must not enter the build context: two clones of one repository give an
 * identical worktree and different `.git` bytes, so `COPY . .` hashed
 * differently on every deploy and rebuilt every layer below it.
 */
final class DockerIgnore
{
    public const FILENAME = '.dockerignore';

    public static function contents(): string
    {
        return TemplateLoader::asset('dockerignore');
    }
}
