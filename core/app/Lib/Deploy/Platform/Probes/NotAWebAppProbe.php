<?php

namespace App\Lib\Deploy\Platform\Probes;

use App\Lib\Deploy\Detect\EditorExtension;
use App\Lib\Deploy\Platform\PlatformProbe;
use App\Lib\Deploy\Platform\ProjectContext;

/**
 * A Node package that is an editor extension, not a server.
 *
 * VS Code / Cursor / Theia extensions are ordinary npm packages with an
 * ordinary package.json, so every file-based rule says "Node app". They
 * build cleanly and then crash on `require('vscode')`, which reads as a
 * broken deploy rather than as a project that was never a web app. The tell
 * is the `engines.vscode` / `contributes` shape inside package.json.
 */
final class NotAWebAppProbe implements PlatformProbe
{
    public function id(): string
    {
        return 'not-a-web-app';
    }

    public function evaluate(ProjectContext $context): bool|array
    {
        return EditorExtension::describes($context->package() ?? []);
    }
}
