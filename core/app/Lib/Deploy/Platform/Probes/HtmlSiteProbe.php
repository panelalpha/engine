<?php

namespace App\Lib\Deploy\Platform\Probes;

use App\Lib\Deploy\Detect\HtmlSite;
use App\Lib\Deploy\Platform\PlatformProbe;
use App\Lib\Deploy\Platform\ProjectContext;

/**
 * A site made only of documents, whose front page is not called `index`.
 *
 * `static-entry` claims the sites that name themselves. This claims the rest:
 * a tree of HTML and the assets it references, where the entrance has to be
 * worked out rather than read off a filename {@see HtmlSite}.
 *
 * Contributes the document the nginx root is pointed at, which for this
 * platform is the whole recipe -- there is nothing to install and nothing to
 * build, only a question of which file answers `/`.
 */
final class HtmlSiteProbe implements PlatformProbe
{
    public function id(): string
    {
        return 'html-site';
    }

    public function evaluate(ProjectContext $context): bool|array
    {
        $entry = HtmlSite::entry($context->projectDir);

        return $entry === null ? false : ['static_index' => $entry];
    }
}
