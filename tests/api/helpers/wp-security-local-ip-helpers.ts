import type { EngineApi } from '@/clients/engine-api';

/** Must-use plugin filename — removed after each test. */
export const LOCAL_IP_BLOCK_MU_PLUGIN = 'panelalpha-test-block-local-ips.php';

/** One-off probe script run via `wp eval-file` (removed after tests). */
export const WP_HTTP_PROBE_SCRIPT = 'panelalpha-wp-http-probe.php';

/**
 * Simulates WP firewall plugins that deny RFC1918 / loopback REMOTE_ADDR values.
 * Engine reverse-proxy and in-container HTTP often appear in these ranges.
 */
export const LOCAL_IP_BLOCK_MU_PLUGIN_PHP = `<?php
/**
 * PanelAlpha Engine API test stub — simulates WP security plugins blocking local/private IPs.
 * @see features/users/wp-cli/wp-cli-security-local-ip.feature
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', static function (): void {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($remote !== '' && panelalpha_test_is_private_or_local_ip($remote)) {
        status_header(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden: private/local IP blocked (PanelAlpha test mu-plugin)';
        exit;
    }
}, 0);

function panelalpha_test_is_private_or_local_ip(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }
        $ranges = [
            ['10.0.0.0', '10.255.255.255'],
            ['172.16.0.0', '172.31.255.255'],
            ['192.168.0.0', '192.168.255.255'],
            ['127.0.0.0', '127.255.255.255'],
        ];
        foreach ($ranges as [$start, $end]) {
            $startLong = ip2long($start);
            $endLong = ip2long($end);
            if ($startLong !== false && $endLong !== false && $long >= $startLong && $long <= $endLong) {
                return true;
            }
        }
        return false;
    }

    $lower = strtolower($ip);
    return $lower === '::1'
        || str_starts_with($lower, 'fc')
        || str_starts_with($lower, 'fd')
        || str_starts_with($lower, 'fe80:');
}
`;

export const WP_HTTP_PROBE_SCRIPT_PHP = `<?php
/**
 * Prints HTTP status code for a URL via wp_remote_get (in-container / WP-cron style).
 * Usage: wp eval-file panelalpha-wp-http-probe.php -- 'https://example.test/'
 */
$url = $argv[1] ?? home_url('/');
$response = wp_remote_get($url, ['timeout' => 30, 'sslverify' => false]);
if (is_wp_error($response)) {
    fwrite(STDERR, $response->get_error_message());
    exit(2);
}
echo (string) wp_remote_retrieve_response_code($response);
`;

export function muPluginPath(wpPath: string): string {
  return `${wpPath}/wp-content/mu-plugins/${LOCAL_IP_BLOCK_MU_PLUGIN}`;
}

export function wpHttpProbePath(wpPath: string): string {
  return `${wpPath}/${WP_HTTP_PROBE_SCRIPT}`;
}

export async function installLocalIpBlockMuPlugin(
  api: EngineApi,
  username: string,
  wpPath: string
): Promise<void> {
  const muDir = `${wpPath}/wp-content/mu-plugins`;
  try {
    const exists = await api.fileExists(username, muDir);
    if (!exists.exists) {
      await api.createDirectory(username, muDir, true);
    }
  } catch {
    await api.createDirectory(username, muDir, true);
  }

  await api.putFileContents(username, muPluginPath(wpPath), LOCAL_IP_BLOCK_MU_PLUGIN_PHP);
}

export async function removeLocalIpBlockMuPlugin(
  api: EngineApi,
  username: string,
  wpPath: string
): Promise<void> {
  const path = muPluginPath(wpPath);
  try {
    const exists = await api.fileExists(username, path);
    if (exists.exists) {
      await api.removeFile(username, path, false);
    }
  } catch {
    // Best-effort cleanup.
  }
}

export async function installWpHttpProbeScript(
  api: EngineApi,
  username: string,
  wpPath: string
): Promise<void> {
  await api.putFileContents(username, wpHttpProbePath(wpPath), WP_HTTP_PROBE_SCRIPT_PHP);
}

export async function removeWpHttpProbeScript(
  api: EngineApi,
  username: string,
  wpPath: string
): Promise<void> {
  const path = wpHttpProbePath(wpPath);
  try {
    const exists = await api.fileExists(username, path);
    if (exists.exists) {
      await api.removeFile(username, path, false);
    }
  } catch {
    // Best-effort cleanup.
  }
}

/**
 * Fetches an HTTPS URL from inside the WordPress install (wp_remote_get), as Engine/cron would.
 */
export async function wpRemoteGetStatusCode(
  api: EngineApi,
  username: string,
  wpPath: string,
  targetUrl: string
): Promise<{ exit_code: number; status: number | null; stderr: string }> {
  const probePath = wpHttpProbePath(wpPath);
  const response = await api.executeWpCliCommand(username, [
    'eval-file',
    probePath,
    `--path=${wpPath}`,
    '--',
    targetUrl,
  ]);

  if (response.exit_code !== 0) {
    return { exit_code: response.exit_code, status: null, stderr: response.stderr };
  }

  const parsed = Number.parseInt(response.stdout.trim(), 10);
  return {
    exit_code: 0,
    status: Number.isFinite(parsed) ? parsed : null,
    stderr: response.stderr,
  };
}

export { resolveEngineIpv4 } from './engine-host';
