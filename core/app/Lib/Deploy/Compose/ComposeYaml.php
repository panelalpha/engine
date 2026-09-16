<?php

namespace App\Lib\Deploy\Compose;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads a compose file the way Docker reads it, not the way Symfony does.
 *
 * The two disagree on one construct, and a project is entitled to the Docker
 * answer: its file works, and the engine refusing it is the engine's problem.
 * Lychee declares an anchor on a line of its own —
 *
 *     x-base-lychee-setup:
 *       # a comment
 *       &base-lychee-setup
 *       image: ghcr.io/lycheeorg/lychee:latest
 *
 * — which is legal YAML, which `docker compose` accepts, and which Symfony's
 * parser rejects with "Mapping values are not allowed in multi-line blocks".
 * That exception reached the API verbatim and took the account with it: the
 * deploy failed at the `running` stage, the account was rolled back, and the
 * deploy log was deleted before anyone could read why.
 *
 * So the anchor is moved onto the key above it, which is the same node in
 * canonical form, and only ever as a **fallback**: a file Symfony already
 * parses is never rewritten, so this cannot change the meaning of a file that
 * was working.
 */
final class ComposeYaml
{
    /**
     * An anchor alone on its line, e.g. `&base-lychee-setup` -- a trailing
     * comment included, because Baserow writes one there and an anchor with
     * something said about it is still an anchor alone on its line.
     */
    private const STANDALONE_ANCHOR = '/^&[A-Za-z0-9_][A-Za-z0-9_.\-]*(?:[ \t]+#.*)?$/';

    /**
     * The parsed document, or null when it cannot be read at all.
     *
     * @return array<mixed>|null
     */
    public static function parse(string $raw): ?array
    {
        try {
            $parsed = Yaml::parse($raw);

            return is_array($parsed) ? $parsed : null;
        } catch (ParseException) {
            // Fall through to the one construct worth rescuing.
        }

        $rescued = self::anchorsOntoTheirKeys($raw);
        if ($rescued === $raw) {
            return null;
        }

        try {
            $parsed = Yaml::parse($rescued);

            return is_array($parsed) ? $parsed : null;
        } catch (ParseException) {
            return null;
        }
    }

    /**
     * The same, for a caller holding a path rather than the contents.
     *
     * @return array<mixed>|null
     */
    public static function parseFile(string $path): ?array
    {
        $raw = @file_get_contents($path);

        return is_string($raw) ? self::parse($raw) : null;
    }

    /**
     * Move an anchor that sits alone on its line up onto the key it belongs
     * to, turning `key:\n  &a\n  x: 1` into `key: &a\n  x: 1`.
     *
     * Only a line that is nothing but an anchor is touched, and only when the
     * nearest line above it — skipping blanks and comments — ends in a colon,
     * which is the one shape where the move is unambiguous. Anything else is
     * left exactly as written rather than guessed at.
     */
    private static function anchorsOntoTheirKeys(string $raw): string
    {
        $lines = preg_split('/\R/', $raw);
        if ($lines === false) {
            return $raw;
        }

        $moved = false;
        foreach ($lines as $i => $line) {
            if (preg_match(self::STANDALONE_ANCHOR, trim($line)) !== 1) {
                continue;
            }

            for ($j = $i - 1; $j >= 0; $j--) {
                $above = $lines[$j];
                $trimmed = trim($above);
                if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                    continue;
                }
                if (str_ends_with($trimmed, ':')) {
                    $lines[$j] = rtrim($above) . ' ' . trim($line);
                    $lines[$i] = null;
                    $moved = true;
                }
                break;
            }
        }

        if (!$moved) {
            return $raw;
        }

        return implode("\n", array_filter($lines, static fn ($l): bool => $l !== null));
    }
}
