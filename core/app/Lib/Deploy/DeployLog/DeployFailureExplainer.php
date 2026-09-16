<?php

namespace App\Lib\Deploy\DeployLog;

/**
 * Turns a failed build's BuildKit output into one sentence naming the cause.
 * Rule slugs are identifiers telemetry reports, so renaming one splits that
 * failure's history on the receiving end. Null means nothing matched.
 */
class DeployFailureExplainer
{
    public static function explain(string $output): ?string
    {
        $match = self::match($output);

        return $match === null ? null : $match['message'];
    }

    /**
     * The matching rule and the sentence it produced.
     *
     * @return ?array{rule: string, message: string}
     */
    public static function match(string $output): ?array
    {
        if (trim($output) === '') {
            return null;
        }

        foreach (self::rules() as $rule => [$pattern, $build]) {
            if (preg_match($pattern, $output, $m) === 1) {
                $sentence = $build($m);
                if ($sentence !== null) {
                    return ['rule' => $rule, 'message' => $sentence];
                }
            }
        }

        return null;
    }

    /**
     * Every rule slug, in match order.
     *
     * @return list<string>
     */
    public static function ruleIds(): array
    {
        return array_keys(self::rules());
    }

    private static function duration(int $seconds): string
    {
        return $seconds >= 120 && $seconds % 60 === 0 ? intdiv($seconds, 60) . ' minutes' : "{$seconds} seconds";
    }

    /**
     * @return array<string, array{0: string, 1: callable(array<int|string, string>): ?string}>
     */
    private static function rules(): array
    {
        return [
            // StepWatchdog killed a silent step. First: it quotes a last output line in
            // its marker, which another rule could otherwise match.
            'build-stalled' => [
                '/' . preg_quote(StepWatchdog::MARKER, '/') . ': "(.*?)" printed nothing for (\d+)s\. Last output: ([^\n]*)/',
                static fn (array $m): string =>
                    "The build step \"{$m[1]}\" printed nothing for " . self::duration((int) $m[2])
                        . " and was stopped. Last output: {$m[3]}. It was most likely stuck on a download "
                        . 'or network call; deploy again, and if it stalls at the same point, check that step.',
            ],

            // Language toolchain too old for what the project declares.
            'go-toolchain-too-old' => [
                '/go\.mod requires go >= ([0-9.]+).*?running go ([0-9.]+)/is',
                static fn (array $m): string =>
                    "This project needs Go {$m[1]}, but it was built with Go {$m[2]}.",
            ],

            // The Java image carries one JDK. `javac`'s own line is matched, not the plugin
            // goal that reported it. Measured: thingsboard, tigase, graphhopper, druid and
            // openrouteservice all declare 25 against the image's 21.
            'java-release-too-new' => [
                '/error: release version ([0-9]+) not supported/i',
                static fn (array $m): string =>
                    "This project has to be compiled for Java {$m[1]}, and the build image ships an "
                        . 'older JDK, whose compiler refuses it. The full build output is in the deploy log.',
            ],

            // Maven could not put the reactor together: a module or parent POM it needs is
            // missing. Distinct from a compile error and from a dependency resolution failure.
            'java-project-build-failed' => [
                '/(?:\[ERROR\] Failed to execute goal[^\n]*(?:ProjectBuildingException|UnresolvableModelException)'
                    . '|Child module ([^\s]+) of [^\s]+ does not exist'
                    . '|Non-resolvable parent POM for ([^\s:]+))[^\n]*/i',
                static fn (array $m): string =>
                    'Maven could not assemble this project: a module or parent POM it declares is '
                        . 'absent' . (($m[1] ?? '') !== '' ? " ({$m[1]})" : (($m[2] ?? '') !== '' ? " ({$m[2]})" : '')) . '. '
                        . 'The full output is in the deploy log.',
            ],

            // A crate's build script needs pkg-config or the headers it queries: `The
            // pkg-config command could not be found`, `Unable to find libclang`.
            'native-library-headers-missing' => [
                '/(The pkg-config command could not be found|Unable to find libclang'
                    . '|Package \\S+ was not found in the pkg-config search path'
                    . '|Could not find \\S+ using pkg-config)/i',
                static fn (): string =>
                    'A dependency has to be compiled and needs development headers that the build '
                        . 'image does not carry (pkg-config, or a library it queries). The full '
                        . 'output is in the deploy log, naming the dependency.',
            ],

            'php-version-mismatch' => [
                '/requires php ([^\s,]+).*?your php version \(([^)]+)\)/is',
                static fn (array $m): string =>
                    "This project needs PHP {$m[1]}, but it was built with PHP {$m[2]}.",
            ],

            // Composer resolves against the runtime image, so the extension really is absent
            // from the image the app runs on; the fix is to bake it, not to retry.
            'php-extension-missing' => [
                // Composer 2 says "requires PHP extension ext-zstd", older versions say
                // "requires ext-zstd"; both are in the wild.
                '/requires (?:PHP extension )?ext-([a-z0-9_]+).*?it is missing from your system/is',
                static fn (array $m): string =>
                    "This project needs the PHP extension {$m[1]}, which is not in the PHP image it runs on.",
            ],

            // The project's own constraints cannot be satisfied together; nothing the
            // platform can do about it.
            'composer-unresolvable' => [
                '/Your requirements could not be resolved to an installable set of packages/i',
                static fn (): string =>
                    'This project\'s Composer dependencies cannot all be installed together.'
                    . ' The full resolver output is in the deploy log.',
            ],

            // Only npm's *error* form, never `npm warn EBADENGINE` — a warning reports what
            // some transitive dependency asked for, not what this project needs (measured:
            // nextcloud/server declares ^24.0.0 and was reported as ^22.0.0 off a warning).
            // The tempered `(?!npm warn)` stops the scan at the next warning line.
            'node-engine-mismatch' => [
                '/npm (?:error|ERR!)[^\n]*Unsupported engine(?:(?!npm warn)[\s\S])*?'
                    . 'required:\s*\{?\s*node:\s*\'?([^\'",}]+)/i',
                static fn (array $m): string =>
                    'This project needs Node ' . trim($m[1]) . ', which does not match the version used to build it.',
            ],

            // Resources.
            'disk-full' => [
                '/(no space left on device|ENOSPC|errno=28)/i',
                static fn (): string =>
                    'The build ran out of disk space. Free some space in the account or move to a larger plan.',
            ],

            // Java refusing to allocate inside the heap it was given (the fix is how the
            // engine sizes the heap), not the kernel killing a cgroup (`out-of-memory`).
            // Ranked above `out-of-memory` because one Maven log can carry both, and a
            // Maven OOM names no container of its own. Above `build-step-failed` too:
            // Maven prints a successful reactor summary before the failure, so the last
            // line is unrelated noise (measured: an Alfresco deploy reported a `/root`
            // mkdir permission error where the truth was 512 MB of heap against a 2 GB
            // container).
            'java-heap-space' => [
                '/(OutOfMemoryError:\s*Java heap space|java\.lang\.OutOfMemoryError'
                    . '|Java heap space\s*->|There is insufficient memory for the Java Runtime'
                    . '|Could not reserve enough space for \d+KB object heap)/i',
                static fn (): string =>
                    'The Java build ran out of heap: the compiler was given less memory than this '
                        . 'project needs. The build container had more to give — the heap is sized '
                        . 'from it — so this is an engine limit, not the plan. The full build output '
                        . 'is in the deploy log.',
            ],

            // The same for V8, and the same reason it outranks `out-of-memory`: Node
            // refusing to allocate inside its heap, not the kernel killing the cgroup.
            // `Reached heap limit` is the capped form, `Ineffective mark-compacts` the
            // other.
            'node-heap-space' => [
                '/FATAL ERROR:\s*(?:Ineffective mark-compacts near heap limit'
                    . '|Reached heap limit|CALL_AND_RETRY_LAST Allocation failed)'
                    . '|JavaScript heap out of memory/',
                static fn (): string =>
                    'The Node build ran out of heap: the build was given less memory than this '
                        . 'project needs. The build container had more to give — the heap is sized '
                        . 'from it — so this is an engine limit, not the plan. The full build output '
                        . 'is in the deploy log.',
            ],

            // The kernel's own forms only. npm prints `killed: false,` and `signal: null` on
            // any plain failure, and a bare `\bKilled\b` matched the false value (measured:
            // espocrm reported out of memory after dying in phantomjs-prebuilt).
            //
            // A line that is *only* `Killed`, anchored with /m, is what a host build produces:
            // those run `docker run --entrypoint sh -e -c <script>` with no BuildKit, so no
            // `exit code: 137` is printed. `out of memory` stays bare — the kernel writes it
            // that way in `Memory cgroup out of memory: Killed process ...`.
            'out-of-memory' => [
                '/(exit code: 137|signal:\s*killed|OOMKilled'
                    . '|out of memory'
                    . '|(?:task|process)\s+"?[\w\/.-]+"?\s+killed'
                    . '|^[ \t]*Killed[ \t]*$'
                    . '|oom-kill)/im',
                static fn (): string =>
                    'The build ran out of memory. This project needs more RAM than the plan allows.',
            ],

            // Below `out-of-memory`: a Java build that spawned a Node frontend usually died
            // because the frontend ran the JVM out of memory. Reached only when Maven reports
            // a goal failure (keycloak, openmeetings do this).
            'java-plugin-goal-failed' => [
                '/\[ERROR\] Failed to execute goal (com\.github\.eirslett|com\.diffplug\.spotless):[^\n]*/i',
                static fn (array $m): string =>
                    'The Java build ran a frontend step that reported failures'
                        . ' (the ' . $m[1] . ' plugin). '
                        . 'The full output is in the deploy log.',
            ],
            'java-license-check-failed' => [
                '/\[ERROR\] Failed to execute goal org\.apache\.rat:[^\n]*/i',
                static fn (): string =>
                    'The Java build refused to continue over a missing license header (the Apache RAT '
                        . 'plugin). The full output is in the deploy log.',
            ],

            // A Rust `-sys` crate with no C++ compiler in the image says so about itself
            // (`CXX_... = None`), which reads as a crate fault when it is the image's.
            // `native-library-headers-missing` above covers the pkg-config and libclang cases.
            'native-build-interrupted' => [
                '/^(?:CXX?_[A-Za-z0-9_-]+ = None|CC_FORCE_DISABLE = None)$/m',
                static fn (): string =>
                    'A dependency has to be compiled from source and the build container had no C or '
                        . 'C++ compiler to do it. The full output is in the deploy log.',
            ],

            // Cargo's own report, naming the crate. The two specific reasons above win where
            // they apply; this catches the long tail that says only "a build script failed".
            'rust-build-script-failed' => [
                '/error: failed to run custom build command for `([^`]+)`/',
                static fn (array $m): string =>
                    "The Rust dependency `{$m[1]}` could not be built (it compiles or links a C "
                        . 'library). The full output is in the deploy log.',
            ],

            'rust-compile-failed' => [
                '/error: could not compile `([^`]+)`/',
                static fn (array $m): string =>
                    "The project's Rust code does not compile: `{$m[1]}`. The full output is in the "
                        . 'deploy log.',
            ],

            // Registry.
            'registry-rate-limited' => [
                '/(toomanyrequests|429 Too Many Requests)/i',
                static fn (): string =>
                    'Docker Hub temporarily refused further downloads because of its rate limit. Try again in a few minutes.',
            ],

            // BuildKit's wording when it cannot reach the registry at all: a pruned patch tag
            // answers `not found` on its own, and a Dockerfile built for someone else's CI
            // names a registry that is not there.
            'base-image-unavailable' => [
                '/(manifest unknown|manifest for \S+ not found|pull access denied'
                    . '|failed to resolve source metadata|failed to resolve reference|failed to do request)/i',
                static fn (array $m): string =>
                    'A base image this project asks for could not be downloaded — it may not exist, '
                        . 'may be private, or its registry may be unreachable from here.',
            ],

            // node-gyp needs a Python interpreter and a C toolchain the slim Node images do
            // not carry. pnpm 10+ runs install scripts by default, so the first dependency
            // with a native addon ends the build with gyp output and no diagnosis.
            'native-build-toolchain-missing' => [
                '/(gyp ERR!|Could not find any Python installation to use|node-gyp rebuild)/i',
                static fn (): string =>
                    'A dependency has to be compiled during install, and this build image has no '
                        . 'Python or C toolchain for it. Name an image that does in a panelalpha.yaml, '
                        . 'or use a release of this dependency that ships a prebuilt binary.',
            ],

            // Assets a compiled binary embeds at build time (go:embed and the like). Above the
            // marker rule below: with the frontend missing the build never reaches it.
            'missing-embedded-assets' => [
                '/pattern [^\s:]*:?\S*: no matching files found/i',
                static fn (): string =>
                    'This project embeds files that have to be produced by an earlier build step, '
                        . 'and that step is not part of the automatic recipe. It needs a PanelAlpha page to describe its build.',
            ],

            // Anchored to a BuildKit *output* line (#<step> <seconds>): the same words appear
            // in the RUN instruction BuildKit echoes when a build fails.
            'go-entrypoint-not-found' => [
                '/^#\d+\s+[\d.]+\s+PANELALPHA: no runnable Go program found/m',
                static fn (): string =>
                    'No runnable program was found in this Go repository — the entrypoint could not be located automatically.',
            ],

            // The app started but rejected its own configuration — almost always a secret the
            // project expects the operator to fill in.
            'env-validation-failed' => [
                '/(environment variables? (has|have) failed the following validations|should not be one of the following values|must be longer than or equal to \d+ characters)/i',
                static fn (): string =>
                    'The application refused to start because one of its settings is missing or still has a placeholder value. '
                        . 'Set it under the application\'s environment variables and deploy again.',
            ],

            // A datastore created by an earlier deploy keeps the password it was initialised
            // with; the env var is only read at first start.
            'database-auth-failed' => [
                '/(password authentication failed for user|Access denied for user .{0,40}using password|authentication failed.{0,40}MongoServerError)/i',
                static fn (): string =>
                    'The application could not sign in to its database. The database was created by an earlier deploy '
                        . 'and still expects the old password — delete the application\'s database volume, or recreate the '
                        . 'application, and deploy again.',
            ],

            // Project setup.
            'missing-build-script' => [
                '/(Missing script: ["\']?build|npm ERR! missing script: build)/i',
                static fn (): string =>
                    'The project has no "build" script in package.json, so there is nothing to compile.',
            ],

            'dependency-conflict' => [
                '/(ERESOLVE|unable to resolve dependency tree|conflicting peer dependency)/i',
                static fn (): string =>
                    'The project\'s dependencies conflict with each other and could not be installed.',
            ],

            'git-binary-missing' => [
                '/Executable not found in \$PATH:\s*["\']git["\']/i',
                static fn (): string =>
                    'The build needs the git binary (often content-collections or similar calling `git log`). '
                        . 'Hosting images now install git during the framework build — try deploying again.',
            ],

            'prepare-script-failed' => [
                '/prepare script from .+ exited with 1/i',
                static fn (): string =>
                    'A package.json "prepare" script failed during install. '
                        . 'Git hook installers (husky/lefthook) cannot run in the build image.',
            ],

            'git-exec-failed' => [
                '/Error: exec: ["\']git["\']/i',
                static fn (): string =>
                    'A build step needed the git binary (often lefthook/husky install). '
                        . 'Those hooks are skipped in hosting builds — try deploying again after an engine update.',
            ],

            'bun-lockfile-outdated' => [
                '/error parsing lockfile:\s*Outdated lockfile version/i',
                static fn (): string =>
                    'The Bun lockfile was written by a newer Bun than the one used to install dependencies. '
                        . 'The hosting image should use a current Bun — try deploying again after an engine update.',
            ],

            'bun-lockfile-frozen' => [
                '/lockfile had changes, but lockfile is frozen/i',
                static fn (): string =>
                    'Dependency install refused to change the lockfile (frozen install). '
                        . 'The lockfile may not match package.json, or was produced by a different Bun version.',
            ],

            // BuildKit often omits bun's note and shows only the RUN plus exit code.
            'bun-frozen-install-failed' => [
                '/RUN bun install --frozen-lockfile[\s\S]{0,400}?did not complete successfully: exit code: 1/i',
                static fn (): string =>
                    'Bun could not install dependencies with a frozen lockfile (usually a Bun version mismatch). '
                        . 'Try deploying again after an engine update that installs without --frozen-lockfile.',
            ],

            'missing-package-at-runtime' => [
                '/error: Cannot find package ["\']([^"\']+)["\']/i',
                static fn (array $m): string =>
                    "A required package ({$m[1]}) was missing when the app started. The install step may have been incomplete.",
            ],

            'dependency-not-found' => [
                '/could not find a version that satisfies the requirement/i',
                static fn (): string =>
                    'One of the project\'s dependencies could not be found in the package registry.',
            ],

            // A Poetry application, not a package: `pip install .` asks poetry-core to build
            // a wheel and its refusal is the only line saying so. The engine installs these
            // with `poetry install --no-root`, so this rule is telemetry.
            'python-non-package-poetry' => [
                '/Building a package is not possible in non-package mode/i',
                static fn (): string =>
                    'This project is a Poetry application, not a Python package, so its own code '
                        . 'cannot be installed as a library. It needs to be run from its source '
                        . 'with its dependencies installed — name a runnable entry point, or use a '
                        . 'recipe that installs with `poetry install --no-root`.',
            ],

            // Prefer the shell's own error line over BuildKit's generic exit code.
            'install-error-line' => [
                '/^#\d+\s+\d+\.\d+\s+(error:[^\n]+)/im',
                static fn (array $m): string =>
                    'Install or build failed: ' . trim($m[1]),
            ],

            'repo-auth-failed' => [
                '/(fatal: could not read Username|Authentication failed|remote: Invalid username or password)/i',
                static fn (): string =>
                    'The repository could not be read. Check that it is public, or that the access token is valid.',
            ],

            'repo-not-found' => [
                '/(fatal: repository .* not found|ERROR: Repository not found)/i',
                static fn (): string =>
                    'The repository was not found. Check the address and whether it is private.',
            ],

            // Generic build failure — last resort, still better than the dump.
            'build-step-failed' => [
                '/did not complete successfully: exit code: (\d+)/i',
                static fn (array $m): string =>
                    "A build step failed (exit code {$m[1]}). The full output is in the deploy log.",
            ],
        ];
    }
}
