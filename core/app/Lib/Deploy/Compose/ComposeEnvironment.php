<?php

namespace App\Lib\Deploy\Compose;

/**
 * The environment a generated service runs with, in the order its layers
 * outrank one another.
 *
 * Compose gives `environment:` precedence over `env_file:`, so every variable
 * a strategy names here is one the account cannot correct in the `.env` it was
 * given — `env_vars` wrote `APP_ENV=prod` into the file and the container kept
 * running with the `APP_ENV=production` the PHP platform ships, which is
 * Laravel's value and the one Symfony refuses to boot on.
 *
 * Everything a strategy generates is therefore a default. The project's
 * app config may restate it, and what the account set outranks both — which is
 * what `env_vars` has claimed to do since it was added, and what the copy in
 * `.env` already did.
 *
 * Two groups stay the engine's own, because the engine reads them back and a
 * mismatch fails quietly. `PA_*` are the generated entrypoint's own
 * variables: `PA_DOCROOT` is the Apache document root, `PA_DEPLOY_PHASE`
 * decides install from upgrade. `HOST`, `HOSTNAME` and `PORT` name the port
 * compose publishes and the health check probes — an account that moved those
 * would get a container nothing can reach and a log that never says why.
 *
 * No Laravel dependencies — unit-testable.
 */
final class ComposeEnvironment
{
    /** The generated entrypoint's own variables. */
    public const RESERVED_PREFIX = 'PA_';

    /**
     * Where the application is published and probed.
     *
     * @var list<string>
     */
    public const RESERVED = ['HOST', 'HOSTNAME', 'PORT'];

    /**
     * Fold what the app config and the account declared onto a decision's `env`.
     *
     * @param array<string, mixed> $decision
     * @param array<string, string> $appConfig the project's `panelalpha.yaml`
     * @param array<string, string> $account the account's own `env_vars`
     * @return array<string, mixed>
     */
    public static function layer(array $decision, array $appConfig, array $account): array
    {
        $overrides = array_merge(
            self::overridable(ComposeValues::stringMap($appConfig)),
            // An empty field means "keep what the project shipped", not "set
            // this to nothing" — the rule ProjectEnvironment already applies
            // to the same values on their way into .env.
            self::overridable(array_filter(
                ComposeValues::stringMap($account),
                static fn (string $value): bool => $value !== ''
            ))
        );

        if ($overrides === []) {
            return $decision;
        }

        $decision['env'] = array_merge(
            ComposeValues::stringMap($decision['env'] ?? null),
            $overrides
        );

        return $decision;
    }

    public static function isReserved(string $key): bool
    {
        return str_starts_with($key, self::RESERVED_PREFIX)
            || in_array($key, self::RESERVED, true);
    }

    /**
     * @param array<string, string> $env
     * @return array<string, string>
     */
    private static function overridable(array $env): array
    {
        return array_filter(
            $env,
            static fn (string $key): bool => !self::isReserved($key),
            ARRAY_FILTER_USE_KEY
        );
    }
}
