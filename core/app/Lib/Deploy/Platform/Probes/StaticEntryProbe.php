<?php

namespace App\Lib\Deploy\Platform\Probes;

use App\Lib\Deploy\Detect\StaticEntry;
use App\Lib\Deploy\Platform\PlatformProbe;
use App\Lib\Deploy\Platform\ProjectContext;

/**
 * An HTML entry point this project can actually be served from.
 *
 * The Static platform's manifest already says "and none of the manifests
 * that would make this an application" in JSON. What it cannot say is where
 * the entry document is: `index.html` may sit at the root, or under
 * `public/`, `dist/`, `build/` or `site/`, and picking the wrong one serves
 * a build artefact directory as if it were the site.
 *
 * Contributes the entry path the nginx root is pointed at.
 */
final class StaticEntryProbe implements PlatformProbe
{
    public function id(): string
    {
        return 'static-entry';
    }

    public function evaluate(ProjectContext $context): bool|array
    {
        $entry = StaticEntry::find($context->projectDir, $context->files);

        return $entry === null ? false : ['static_index' => $entry];
    }
}
