import type { APIRequestContext, APIResponse } from '@playwright/test';
import type { EngineApi } from '@/clients/engine-api';
import { delay } from '@/helpers/retry';
import { getWebserverInfo } from '@/helpers/webserver-helpers';

export const LSCACHE_HEADER = 'x-litespeed-cache';
export const LSCACHE_PLUGIN = 'litespeed-cache';

export const PURGE_RETRY_DELAY_MS = 3_000;
export const MAX_PURGE_WAIT_MS = 60_000;
/** Query LiteSpeed honours as "do not serve this from cache". */
export const CACHE_BYPASS_QUERY = 'LSCWP_CTRL=before_optm';
/** Server-level (OpenLiteSpeed) cache needs this long to expire after a plugin change. */
export const CACHE_EXPIRY_DELAY_MS = 6_000;
export const CACHE_PROCESSING_DELAY_MS = 1_000;
export const CACHE_POPULATE_DELAY_MS = 500;

const TRANSIENT_HTTP_ERROR = /socket hang up|ECONNRESET|ETIMEDOUT|EPIPE|ECONNABORTED/i;

/**
 * Why LSCache tests cannot run here, or `undefined` when they can.
 *
 * Derived from the webserver the engine is actually running, not from TEST_ENV:
 * the env var describes the profile the operator intended, which may not match
 * the stack after a webserver change.
 */
export async function lscacheUnavailableReason(api: EngineApi): Promise<string | undefined> {
  const { slug } = await getWebserverInfo(api);

  if (!slug.includes('litespeed')) {
    return `LiteSpeed Cache needs LiteSpeed or OpenLiteSpeed; this engine runs ${slug}.`;
  }

  // Enterprise without a serial runs in trial mode, whose worker and cache
  // limits produce failures that say nothing about the engine.
  const isEnterprise = slug === 'litespeed';
  if (isEnterprise && !process.env.LITESPEED_SERIAL_NUMBER?.trim()) {
    return (
      'LiteSpeed Enterprise is unlicensed (set LITESPEED_SERIAL_NUMBER) — trial mode has ' +
      'cache and worker limits that make these tests unreliable.'
    );
  }

  return undefined;
}

/** GET that retries through the connection resets a cache purge tends to cause. */
export async function getWithRetry(
  http: APIRequestContext,
  url: string,
  options?: Parameters<APIRequestContext['get']>[1],
  attempts = 6,
  retryDelayMs = PURGE_RETRY_DELAY_MS
): Promise<APIResponse> {
  let lastError: unknown;

  for (let attempt = 0; attempt < attempts; attempt++) {
    try {
      return await http.get(url, options);
    } catch (error) {
      lastError = error;
      if (!TRANSIENT_HTTP_ERROR.test(String(error)) || attempt === attempts - 1) {
        throw error;
      }
      await delay(retryDelayMs);
    }
  }

  throw lastError;
}

export async function ensureLscachePluginActive(
  api: EngineApi,
  username: string,
  wpPath: string,
  options: { forceInstall?: boolean } = {}
): Promise<void> {
  const install = ['plugin', 'install', LSCACHE_PLUGIN, wpPath];
  if (options.forceInstall) {
    install.splice(3, 0, '--force');
  }

  await api.executeWpCliCommand(username, install);
  await api.executeWpCliCommand(username, ['plugin', 'activate', LSCACHE_PLUGIN, wpPath]);
}

/**
 * Empties every cache layer.
 *
 * `litespeed-purge all` talks to the server over HTTPS and fails on a self-signed
 * certificate, so `cache flush` is the fallback.
 */
export async function purgeAllLiteSpeedCache(
  api: EngineApi,
  username: string,
  wpPath: string
): Promise<void> {
  const purge = await api.executeWpCliCommand(username, ['litespeed-purge', 'all', wpPath]);
  if (purge.exit_code === 0) {
    return;
  }

  await api.executeWpCliCommand(username, ['cache', 'flush', wpPath]);
}

/** Whether the plugin is currently active for this user. */
export async function isLscachePluginActive(
  api: EngineApi,
  username: string,
  wpPath: string
): Promise<boolean> {
  const active = await api.executeWpCliCommand(username, [
    'plugin',
    'list',
    '--status=active',
    '--field=name',
    wpPath,
  ]);
  return active.stdout.includes(LSCACHE_PLUGIN);
}
