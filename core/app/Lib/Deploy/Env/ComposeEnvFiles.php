<?php

namespace App\Lib\Deploy\Env;

use Symfony\Component\Yaml\Yaml;

/**
 * The `env_file:` paths a compose file expects to exist.
 *
 * Compose V2 refuses to start a stack whose `env_file` is missing, so these
 * have to be created before the deploy rather than discovered by it. Only
 * paths inside the project count: an absolute path, a `..` segment or an
 * unresolved `${VAR}` is not something the engine can create on the
 * repository's behalf.
 */
final class ComposeEnvFiles
{
    /**
     * @return list<string> project-relative paths, deduplicated
     */
    public static function declaredIn(string $raw): array
    {
        $parsed = self::parse($raw);

        return $parsed === null ? [] : self::pathsIn($parsed);
    }

    /**
     * @param array<string, mixed> $parsed a decoded compose file
     * @return list<string>
     */
    public static function pathsIn(array $parsed): array
    {
        $services = $parsed['services'] ?? null;
        if (!is_array($services)) {
            return [];
        }

        $paths = [];
        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }
            foreach (self::entriesOf($service['env_file'] ?? null) as $entry) {
                $relative = self::projectRelative($entry);
                if ($relative !== null) {
                    $paths[$relative] = true;
                }
            }
        }

        return array_keys($paths);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function parse(string $raw): ?array
    {
        if ($raw === '') {
            return null;
        }
        try {
            $parsed = Yaml::parse($raw);
        } catch (\Throwable) {
            return null;
        }

        return is_array($parsed) ? $parsed : null;
    }

    /**
     * `env_file` is a string, a list of strings, or a list of `{path: …}`.
     *
     * @param mixed $envFile
     * @return list<string>
     */
    private static function entriesOf($envFile): array
    {
        if (is_string($envFile)) {
            return [$envFile];
        }
        if (!is_array($envFile)) {
            return [];
        }

        $paths = [];
        foreach ($envFile as $entry) {
            $path = is_array($entry) ? ($entry['path'] ?? null) : $entry;
            if (is_string($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    private static function projectRelative(string $raw): ?string
    {
        $path = str_replace('\\', '/', trim($raw));

        // `env_file: ${ENV_FILE:-.env}` -- the default is what compose will use
        // when nothing sets the variable, so it is the file that has to exist.
        // Refusing every interpolated path left ideon with no `.env` at all and
        // compose failing on `env file ... not found`, while the literal
        // spelling of the same thing already gets one. Substituted before the
        // guard below, so a default that escapes or names an absolute path is
        // still refused.
        if (str_contains($path, '${')) {
            $path = self::substituteDefaults($path);
            if ($path === null) {
                return null;
            }
        }

        if ($path === '' || str_starts_with($path, '/')) {
            return null;
        }
        if (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }
        if ($path === '' || in_array('..', explode('/', $path), true)) {
            return null;
        }

        return $path;
    }

    /**
     * `${VAR:-default}` and `${VAR-default}` collapse to `default`; anything
     * whose default is itself interpolated or absent cannot be guessed at.
     *
     * Null means "no single answer": several candidates (`${A:-x}${B:-y}`), a
     * nested reference, or a bare `${VAR}` whose value is the caller's to
     * choose. Guessing one of those would create a file compose may not read
     * and leave the real one missing, which is the bug this avoids rather than
     * trades for.
     */
    private static function substituteDefaults(string $path): ?string
    {
        $count = 0;
        // Only the `:-`/`-` forms have a default to take. `\$\{[^}]*\}` would
        // swallow a nested brace, so the body is matched without one.
        $substituted = preg_replace_callback(
            '/\$\{([A-Za-z_][A-Za-z0-9_]*)(?::-|-)([^}]*)\}/',
            static function (array $m) use (&$count): string {
                $count++;

                return $m[2];
            },
            $path
        );

        if ($substituted === null || $count !== 1) {
            return null;
        }

        // A second pass is how a nested reference shows up: whatever remains is
        // an interpolation with no default, and that is not a path to invent.
        return str_contains($substituted, '${') ? null : $substituted;
    }
}
