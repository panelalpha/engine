<?php

namespace App\Lib\Deploy\Platform\Probes;

use App\Lib\Deploy\Platform\PlatformProbe;
use App\Lib\Deploy\Platform\ProjectContext;

/**
 * Where an Angular build actually writes.
 *
 * Angular states its own output path inside angular.json, under a project
 * name nobody can predict, and modern versions split it into `base` plus a
 * `browser` subdirectory. Serving the wrong one serves an empty directory,
 * which looks exactly like a successful deploy.
 *
 * Contributes `output_directory`; always matches when angular.json is there,
 * so the manifest can use it as its output resolver and as part of detection.
 */
final class AngularOutputProbe implements PlatformProbe
{
    public function id(): string
    {
        return 'angular-output';
    }

    public function evaluate(ProjectContext $context): bool|array
    {
        return ['output_directory' => self::outputDir($context->projectDir)];
    }

    public static function outputDir(string $projectDir): string
    {
        $path = rtrim($projectDir, '/') . '/angular.json';
        if (!is_file($path)) {
            return 'dist';
        }
        $json = json_decode((string) @file_get_contents($path), true);
        if (!is_array($json) || empty($json['projects']) || !is_array($json['projects'])) {
            return 'dist';
        }
        foreach ($json['projects'] as $project) {
            if (!is_array($project)) {
                continue;
            }
            $output = $project['architect']['build']['options']['outputPath']
                ?? $project['targets']['build']['options']['outputPath']
                ?? null;
            if (is_string($output) && $output !== '') {
                return $output;
            }
            if (is_array($output)) {
                $base = is_string($output['base'] ?? null) ? $output['base'] : 'dist';
                $browser = is_string($output['browser'] ?? null) ? $output['browser'] : 'browser';

                return rtrim($base, '/') . '/' . ltrim($browser, '/');
            }
        }

        return 'dist';
    }
}
