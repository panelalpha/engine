<?php

namespace App\Lib\Deploy\Compose;

/**
 * Workstation-only services with no place in a deployment: mail catchers,
 * asset dev-servers, browser drivers, tunnels — matched by name or by the
 * dev-server command, since many appear under a project-specific name.
 */
final class DevServices
{
    /** @var list<string> */
    private const NAMES = [
        'vite',
        'webpack',
        'webpack-dev-server',
        'webpacker',
        'js-host',
        'css-host',
        'mailhog',
        'mailpit',
        'mailcatcher',
        'selenium',
        'chrome',
        'chromium',
        'playwright',
        'cypress',
        'storybook',
        'ngrok',
        // Administration and observability consoles: attached to a stack, never
        // the thing the stack is for, and several hand out the database they point at.
        'adminer',
        'phpmyadmin',
        'pgadmin',
        'pgadmin4',
        'keycloak',
        'grafana',
        'loki',
        'tempo',
        'jaeger',
        'zipkin',
        'prometheus',
        'kibana',
        'swagger-ui',
    ];

    private const DEV_COMMAND_PATTERN = '/\b(vite|webpack-dev-server|storybook)\s+(dev|serve)\b/';

    /**
     * @param array<string, mixed> $service
     */
    public static function isDevSidecar(string $name, array $service): bool
    {
        if (in_array(strtolower($name), self::NAMES, true)) {
            return true;
        }

        $command = ComposeCommand::asString($service['command'] ?? null);

        return $command !== '' && preg_match(self::DEV_COMMAND_PATTERN, $command) === 1;
    }
}
