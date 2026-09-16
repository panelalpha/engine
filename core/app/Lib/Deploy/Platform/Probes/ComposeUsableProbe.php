<?php

namespace App\Lib\Deploy\Platform\Probes;

use App\Lib\Deploy\Compose\ComposeFileInspector;
use App\Lib\Deploy\Platform\PlatformProbe;
use App\Lib\Deploy\Platform\ProjectContext;

/**
 * A compose file worth running as-is: not the engine's generated bootstrap, not
 * a workstation file, not sidecars-only. A missing Dockerfile reference is
 * accepted when the repo has a root Dockerfile. Yields `compose_path` or false.
 */
final class ComposeUsableProbe implements PlatformProbe
{
    public function id(): string
    {
        return 'compose-usable';
    }

    public function evaluate(ProjectContext $context): bool|array
    {
        foreach (ComposeFileInspector::COMPOSE_FILE_CANDIDATES as $candidate) {
            if (!$context->hasFile(strtolower($candidate)) || !$context->isFile($candidate)) {
                continue;
            }
            $path = $context->path($candidate);

            if (ComposeFileInspector::isGeneratedBootstrapCompose($path)
                || ComposeFileInspector::isLocalDevCompose($path)
                || ComposeFileInspector::isSidecarsOnlyCompose($path)
            ) {
                continue;
            }

            $missing = ComposeFileInspector::missingComposeDockerfileRefs($path, $context->projectDir);
            if ($missing === [] || $context->isFile('Dockerfile')) {
                return ['compose_path' => $path];
            }
        }

        return false;
    }
}
