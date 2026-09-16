<?php

namespace App\Lib\Deploy\DeployLog;

/**
 * Drop deploy-log noise before it hits the NDJSON stream: raw docker/build/
 * composer output drowns real errors in the panel.
 */
class DeployLogFilter
{
    /**
     * True when the line adds no signal for operators.
     */
    public static function shouldSkip(string $line): bool
    {
        $line = trim($line);
        if ($line === '') {
            return true;
        }

        if (preg_match('/^[a-f0-9]{12}$/', $line) === 1) {
            return true;
        }

        if (str_contains($line, 'could not lock config file') && str_contains($line, '.gitconfig')) {
            return true;
        }

        if (preg_match('/^#\d+ (\[internal\]|transferring|resolve|load build context)/i', $line) === 1) {
            return true;
        }

        if (preg_match('/^#\d+ DONE 0\.0s$/', $line) === 1) {
            return true;
        }

        // Per-asset bundler lines of the shape "<path><name>-<hash>.<ext> 12.3 kB", matched
        // on shape rather than on a framework's build directory.
        if (preg_match('/^\s*\S+\.(?:js|mjs|cjs|css|map)\s+[\d.]+\s*[kKmM]?[bB]/', $line) === 1) {
            return true;
        }

        if (preg_match('/^\s*-\s+(Downloading|Installing)\s+/i', $line) === 1) {
            return true;
        }

        if (preg_match('/^(Filesystem|ID\s+RECLAIMABLE|Reclaimable:|Total:|\d+\/\d+\s+\[)/', $line) === 1) {
            return true;
        }

        if (preg_match('/^Browserslist: browsers data .* is \d+ months old/', $line) === 1) {
            return true;
        }

        if (preg_match('/^npm notice/', $line) === 1) {
            return true;
        }

        return false;
    }
}
