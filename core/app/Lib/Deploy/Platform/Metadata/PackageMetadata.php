<?php

namespace App\Lib\Deploy\Platform\Metadata;

use App\Lib\Deploy\Platform\ProjectContext;

/**
 * One ecosystem's answer to "which application is this?" — never execute the
 * project (`setup.py`, a `.gemspec` and `build.gradle` are programs), and report
 * script names, not script bodies. Null means the ecosystem is absent.
 */
interface PackageMetadata
{
    /**
     * The ecosystem id, matching the runtime id where one exists: `php`, `node`,
     * `python`, `rust`, `java`, `go`.
     */
    public function id(): string;

    /**
     * What this project's files say it is, or null when this ecosystem is
     * absent from the directory.
     */
    public function read(ProjectContext $context): ?AppPackage;
}
