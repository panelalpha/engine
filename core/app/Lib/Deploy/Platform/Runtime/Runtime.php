<?php

namespace App\Lib\Deploy\Platform\Runtime;

use App\Lib\Deploy\Platform\ProjectContext;

/**
 * One toolchain the engine can put in an image: PHP, Node, Python, Ruby, Go,
 * Rust, Java. A manifest names it under `requires` and states no version; the
 * runtime reads the project (go.mod's `go` line, `requires-python`,
 * `engines.node`, composer's `require.php`) and answers with a `Requirement`.
 *
 * Distinct from a *tool* (npm, composer, bundler): a tool constrains the
 * runtime hosting it and provides no image of its own.
 */
interface Runtime
{
    /** Name a manifest references under `requires`, e.g. "php". */
    public function id(): string;

    /**
     * What this project needs of this toolchain, or null when it does not
     * use it at all.
     */
    public function resolve(ProjectContext $context): ?Requirement;

    /**
     * This toolchain at its default version, for callers with no project to
     * read — the prewarmer knows the platform but not the account.
     */
    public function defaultRequirement(): Requirement;

    /**
     * The versions worth having on the host before anyone asks. What the
     * prewarmer reads; anything outside this set is acquired on demand. Not a
     * limit on what `resolve()` may answer — Go honours a go.mod newer than
     * anything listed here.
     *
     * @return list<string> versions in the spelling of `Requirement::$version`
     */
    public function supportedVersions(): array;

    /**
     * The image providing this requirement on its own, for a requirement this
     * runtime produced. Combining runtimes into one image is `ImageResolver`'s
     * decision, not a runtime's.
     */
    public function image(Requirement $requirement): string;
}
