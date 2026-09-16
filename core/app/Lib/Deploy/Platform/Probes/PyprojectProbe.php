<?php

namespace App\Lib\Deploy\Platform\Probes;

use App\Lib\Deploy\Platform\PlatformProbe;
use App\Lib\Deploy\Platform\ProjectContext;
use App\Lib\Deploy\Platform\Runtime\PythonRuntime;

/**
 * Python declared by a `pyproject.toml`: a `[project]` (PEP 621) or
 * `[tool.poetry]` table. The name alone is not evidence — it is TOML's generic
 * config filename that any tool borrows. Yields the python platform.
 */
final class PyprojectProbe implements PlatformProbe
{
    public function id(): string
    {
        return 'pyproject';
    }

    public function evaluate(ProjectContext $context): bool
    {
        return PythonRuntime::declaresProject($context->contents('pyproject.toml'));
    }
}
