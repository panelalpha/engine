<?php

namespace App\Lib\Deploy\Platform\Probes;

use App\Lib\Deploy\Detect\PhpSources;
use App\Lib\Deploy\Platform\PlatformProbe;
use App\Lib\Deploy\Platform\ProjectContext;

/**
 * PHP the project never declared.
 *
 * Every other PHP manifest recognises a file by name -- composer.json,
 * artisan, phpBB's config.php. A hand-written site declares nothing, so the
 * only evidence it is PHP at all is that it contains PHP, which is a walk
 * rather than a predicate {@see PhpSources}.
 */
final class PhpSourcesProbe implements PlatformProbe
{
    public function id(): string
    {
        return 'php-sources';
    }

    public function evaluate(ProjectContext $context): bool|array
    {
        return PhpSources::present($context->projectDir);
    }
}
