<?php

namespace App\Lib\Deploy\Port;

/**
 * One entry of a Compose `ports:` or `expose:` list, as the host port it
 * publishes and the container port behind it.
 *
 * Compose accepts every one of these:
 *
 *     8080                       "8080:8080"        "8080:80/tcp"
 *     "127.0.0.1:8080:8080"      "0.0.0.0:8080:80"  "${APP_PORT:-8090}:8000"
 *
 * A binding to loopback publishes nothing anyone outside can reach, so it is
 * not a mapping at all.
 */
final class PortMapping
{
    /** @var list<string> */
    private const LOOPBACK_HOSTS = ['127.0.0.1', '::1', 'localhost'];

    private function __construct(public readonly int $hostPort, public readonly ?int $containerPort)
    {
    }

    /**
     * @param mixed $mapping one entry as Compose wrote it
     */
    public static function parse($mapping): ?self
    {
        if (is_int($mapping)) {
            return $mapping > 0 ? new self($mapping, null) : null;
        }

        return is_string($mapping) ? self::fromString($mapping) : null;
    }

    private static function fromString(string $mapping): ?self
    {
        // Env-var defaults are resolved before the split, so
        // "${APP_PORT:-8090}:8000" becomes "8090:8000" rather than nonsense.
        $parts = explode(':', trim(EnvVarDefault::resolve($mapping)));
        if (self::isLoopbackBinding($parts)) {
            return null;
        }

        $containerPort = self::portOf((string) end($parts));
        $parts[count($parts) - 1] = self::withoutProtocol((string) end($parts));
        if (count($parts) === 3) {
            array_shift($parts);
        }
        $hostPort = self::portOf((string) array_shift($parts));

        return $hostPort > 0 ? new self($hostPort, $containerPort ?: null) : null;
    }

    /**
     * @param list<string> $parts
     */
    private static function isLoopbackBinding(array $parts): bool
    {
        return count($parts) >= 2 && in_array($parts[0], self::LOOPBACK_HOSTS, true);
    }

    private static function portOf(string $value): int
    {
        return (int) trim(self::withoutProtocol($value));
    }

    private static function withoutProtocol(string $value): string
    {
        return explode('/', $value)[0];
    }
}
