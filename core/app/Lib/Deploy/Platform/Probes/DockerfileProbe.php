<?php

namespace App\Lib\Deploy\Platform\Probes;

use App\Lib\Deploy\Detect\DockerfileFinder;
use App\Lib\Deploy\Platform\PlatformProbe;
use App\Lib\Deploy\Platform\ProjectContext;

/**
 * A Dockerfile the repository actually intends to be built.
 *
 * Not expressible as `{"file": "Dockerfile"}`, for three reasons the JSON
 * has no way to say: the name may be `Dockerfile.prod` or live under
 * `docker/`, the casing has to be preserved for the build context, and a
 * Dockerfile that maps the host's uid/gid through build args is a
 * workstation file rather than a deployable one.
 *
 * Contributes the Dockerfile's path and its EXPOSEd port.
 */
final class DockerfileProbe implements PlatformProbe
{
    public function id(): string
    {
        return 'dockerfile';
    }

    public function evaluate(ProjectContext $context): bool|array
    {
        $dockerfile = DockerfileFinder::find($context->projectDir, $context->files);
        if ($dockerfile === null) {
            return false;
        }

        $port = DockerfileFinder::exposedPort($context->path($dockerfile));

        return array_filter(
            ['dockerfile' => $dockerfile, 'port_hint' => $port],
            static fn ($value): bool => $value !== null
        );
    }
}
