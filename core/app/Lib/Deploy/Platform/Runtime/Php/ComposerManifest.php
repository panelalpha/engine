<?php

namespace App\Lib\Deploy\Platform\Runtime\Php;

/**
 * composer.json and composer.lock, decoded once.
 *
 * The lockfile is what makes an extension list complete: a package the app
 * only depends on transitively still needs its `ext-*` compiled in, and only
 * the lock knows about it.
 */
final class ComposerManifest
{
    /** @var array<string, mixed> */
    private readonly array $json;

    /** @var array<string, mixed> */
    private readonly array $lock;

    public function __construct(private readonly ?string $composerJson, private readonly ?string $composerLock)
    {
        $this->json = self::decode($composerJson);
        $this->lock = self::decode($composerLock);
    }

    public function hasLockfile(): bool
    {
        return $this->composerLock !== null && $this->composerLock !== '';
    }

    public function json(): ?string
    {
        return $this->composerJson;
    }

    public function lock(): ?string
    {
        return $this->composerLock;
    }

    /**
     * The root `require` plus every locked package's — the whole dependency
     * graph's demands, flattened.
     *
     * @return list<array<string, mixed>>
     */
    public function requireMaps(): array
    {
        return $this->maps('require');
    }

    /**
     * Whether the project's **own** composer.json requires a package.
     *
     * Deliberately not {@see requireMaps()}, which flattens every locked
     * package's requirements too: half of Packagist depends on a symfony
     * component, and "depends on symfony/console" is not "is a Symfony
     * application".
     */
    public function rootRequires(string $package): bool
    {
        $require = $this->json['require'] ?? null;

        return is_array($require) && array_key_exists($package, $require);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function suggestMaps(): array
    {
        return $this->maps('suggest');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function maps(string $key): array
    {
        $maps = [];
        if (is_array($this->json[$key] ?? null)) {
            $maps[] = $this->json[$key];
        }
        foreach ($this->lockedPackages() as $package) {
            if (is_array($package[$key] ?? null)) {
                $maps[] = $package[$key];
            }
        }

        return $maps;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lockedPackages(): array
    {
        $packages = $this->lock['packages'] ?? null;

        return is_array($packages) ? array_filter($packages, 'is_array') : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(?string $raw): array
    {
        $decoded = $raw === null || $raw === '' ? null : json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
