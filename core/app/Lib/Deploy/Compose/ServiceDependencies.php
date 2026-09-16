<?php

namespace App\Lib\Deploy\Compose;

/**
 * References to services that have been dropped: Compose refuses to start a
 * stack naming a service the file does not define. `depends_on` is the obvious
 * key, `links` the older spelling Compose enforces just as strictly.
 */
final class ServiceDependencies
{
    /**
     * The keys that name another service and must be pruned with it.
     *
     * @var list<string>
     */
    private const REFERENCE_KEYS = ['depends_on', 'links'];

    /**
     * @param array<string, mixed> $service
     * @param array<string, true> $dropped lowercase service names
     * @return array<string, mixed>
     */
    public static function withoutDropped(array $service, array $dropped): array
    {
        if ($dropped === []) {
            return $service;
        }

        foreach (self::REFERENCE_KEYS as $key) {
            $service = self::withoutDroppedIn($service, $key, $dropped);
        }

        return $service;
    }

    /**
     * Points every reference to $from at $to, keeping a `links` alias.
     *
     * @param array<string, mixed> $service
     * @return array<string, mixed>
     */
    public static function renamed(array $service, string $from, string $to): array
    {
        $matches = static fn ($ref): bool => is_string($ref) && strcasecmp(self::serviceIn($ref), $from) === 0;
        foreach (self::REFERENCE_KEYS as $key) {
            $refs = $service[$key] ?? null;
            if (!is_array($refs)) {
                continue;
            }
            if (self::isStringList($refs)) {
                $service[$key] = array_map(
                    static fn ($ref) => $matches($ref) ? $to . substr($ref, strlen(self::serviceIn($ref))) : $ref,
                    $refs
                );
                continue;
            }
            $renamed = [];
            foreach ($refs as $name => $condition) {
                $renamed[$matches((string) $name) ? $to : $name] = $condition;
            }
            $service[$key] = $renamed;
        }

        return $service;
    }

    /**
     * `links` entries may be `name` or `name:alias`; the alias goes with the
     * dropped service, since nothing can reach it any more.
     *
     * @param array<string, mixed> $service
     * @param array<string, true> $dropped
     * @return array<string, mixed>
     */
    private static function withoutDroppedIn(array $service, string $key, array $dropped): array
    {
        if (!isset($service[$key]) || !is_array($service[$key])) {
            return $service;
        }

        $kept = self::isStringList($service[$key])
            ? self::keepListed($service[$key], $dropped)
            : self::keepKeyed($service[$key], $dropped);

        if ($kept === []) {
            unset($service[$key]);

            return $service;
        }
        $service[$key] = $kept;

        return $service;
    }

    /**
     * @param list<mixed> $dependencies
     * @param array<string, true> $dropped
     * @return list<string>
     */
    private static function keepListed(array $dependencies, array $dropped): array
    {
        return array_values(array_filter(
            $dependencies,
            static fn ($name): bool => is_string($name)
                && !isset($dropped[strtolower(self::serviceIn($name))])
        ));
    }

    /**
     * @param array<string, mixed> $dependencies
     * @param array<string, true> $dropped
     * @return array<string, mixed>
     */
    private static function keepKeyed(array $dependencies, array $dropped): array
    {
        foreach (array_keys($dependencies) as $name) {
            if (isset($dropped[strtolower((string) $name)])) {
                unset($dependencies[$name]);
            }
        }

        return $dependencies;
    }

    /** `name` or `name:alias` — the service is the part before the colon. */
    private static function serviceIn(string $reference): string
    {
        $colon = strpos($reference, ':');

        return $colon === false ? $reference : substr($reference, 0, $colon);
    }

    /**
     * @param array<mixed> $items
     */
    private static function isStringList(array $items): bool
    {
        return $items === [] || (isset($items[0]) && is_string($items[0]));
    }
}
