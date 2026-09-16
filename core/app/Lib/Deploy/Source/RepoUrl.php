<?php

namespace App\Lib\Deploy\Source;

/**
 * A repository URL broken into the three things the engine keys anything by:
 * host, owner, repo.
 *
 * Two trees are addressed this way — the paemd pages under
 * `/opt/panelalpha/shared-hosting/paemd-pages` and the source recipes under
 * `resources/sources/` — and a project reaches them by the URL it was cloned
 * from, in whichever spelling the operator typed. `git@github.com:o/r.git`,
 * `https://GitHub.com/O/R/` and `https://github.com/o/r` are one repository,
 * so they have to resolve to one path.
 *
 * Lives here rather than beside either consumer because "what repository is
 * this URL" is not a question about app configs or about recipes.
 *
 * No Laravel dependencies — unit-testable.
 */
final class RepoUrl
{
    /**
     * Host, owner and repo, or null when the URL names no repository.
     *
     * Case is left as typed; {@see segments()} is what lowercases, because a
     * caller comparing two URLs and a caller building a path want different
     * things from the same parse.
     *
     * @return ?array{host: string, owner: string, repo: string}
     */
    public static function parse(string $gitUrl): ?array
    {
        // Normalise: strip trailing slashes and optional .git suffix
        $gitUrl = rtrim(trim($gitUrl), '/');
        if (str_ends_with($gitUrl, '.git')) {
            $gitUrl = substr($gitUrl, 0, -4);
        }
        $gitUrl = rtrim($gitUrl, '/');

        // SCP-style git URLs: git@host:owner/repo
        if (preg_match('/^[^@\s\/]+@([^:\s]+):([^\/\s]+)\/([^\/\s]+)$/', $gitUrl, $m)) {
            return ['host' => $m[1], 'owner' => $m[2], 'repo' => $m[3]];
        }

        $parts = parse_url($gitUrl);
        if (empty($parts['host']) || empty($parts['path'])) {
            return null;
        }
        $segments = array_values(array_filter(explode('/', trim($parts['path'], '/'))));
        if (count($segments) < 2) {
            return null;
        }

        return ['host' => $parts['host'], 'owner' => $segments[0], 'repo' => $segments[1]];
    }

    /**
     * The URL as path segments — lowercased, and rejected if any of them
     * could climb out of the tree they are about to be joined to.
     *
     * A repository URL is operator input that becomes a filesystem path, so
     * this is the only place that conversion happens: `..` and separators in
     * an owner or repo name are how a crafted remote would read a YAML file
     * from somewhere else on the host.
     *
     * @return ?array{0: string, 1: string, 2: string} host, owner, repo
     */
    public static function segments(string $gitUrl): ?array
    {
        $parsed = self::parse($gitUrl);
        if ($parsed === null) {
            return null;
        }

        $segments = [
            strtolower($parsed['host']),
            strtolower($parsed['owner']),
            strtolower($parsed['repo']),
        ];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
            if (str_contains($segment, '/') || str_contains($segment, '\\') || str_contains($segment, "\0")) {
                return null;
            }
        }

        return $segments;
    }

    /** `<host>/<owner>/<repo>`, the form a source recipe directory is named. */
    public static function slug(string $gitUrl): ?string
    {
        $segments = self::segments($gitUrl);

        return $segments === null ? null : implode('/', $segments);
    }
}
