import type { APIRequestContext } from '@playwright/test';
import type { EngineApi } from '@/clients/engine-api';
import type { ModSecurityMode } from '@/types';
import { delay } from './retry';
import { isHostingUnavailableStatus, waitForSiteHttpReady } from './webserver-helpers';

export interface ModsecHostingProbeResult {
  compatible: boolean;
  skipReason: string;
}

let cachedProbe: ModsecHostingProbeResult | undefined;

async function trySetModsecModeOff(api: EngineApi): Promise<void> {
  try {
    await api.setModSecurityConfig({ mode: 'off' });
  } catch {
    // Best-effort restore after probe failure.
  }
}

/**
 * Verifies nginx can serve HTTP with ModSecurity mode "on" (blocking).
 * Caches the result for the worker process — enabling modsec reloads nginx and
 * fails when the ngx_http_modsecurity_module.so build does not match nginx.
 * WAF scenarios enable mode "on", so detection_only is not a sufficient probe.
 */
export async function probeModsecurityHostingCompatible(
  api: EngineApi,
  httpClient: APIRequestContext,
  probeUrl: string
): Promise<ModsecHostingProbeResult> {
  if (cachedProbe && !cachedProbe.compatible) {
    return cachedProbe;
  }

  let initialMode: ModSecurityMode = 'off';
  try {
    initialMode = (await api.getModSecurityConfig()).data.mode;
  } catch {
    cachedProbe = {
      compatible: false,
      skipReason: 'ModSecurity probe failed: could not read current mode',
    };
    return cachedProbe;
  }

  try {
    await api.setModSecurityConfig({ mode: 'on' });
    await delay(3_000);

    try {
      await waitForSiteHttpReady(httpClient, probeUrl, {
        timeout: 20_000,
        interval: 2_000,
      });
    } catch {
      await trySetModsecModeOff(api);
      await delay(2_000);
      cachedProbe = {
        compatible: false,
        skipReason:
          '[skip] nginx webserver unavailable after enabling ModSecurity (connector/nginx version mismatch on host)',
      };
      return cachedProbe;
    }

    await api.setModSecurityConfig({ mode: initialMode });
    await delay(2_000);

    // Confirm hosting still works after restoring the initial mode.
    const check = await httpClient.get(probeUrl, {
      ignoreHTTPSErrors: true,
      timeout: 10_000,
    });
    if (isHostingUnavailableStatus(check.status())) {
      await trySetModsecModeOff(api);
      cachedProbe = {
        compatible: false,
        skipReason:
          '[skip] nginx webserver unavailable after ModSecurity probe restore (left mode=off)',
      };
      return cachedProbe;
    }

    cachedProbe = { compatible: true, skipReason: '' };
    return cachedProbe;
  } catch (error) {
    await trySetModsecModeOff(api);
    cachedProbe = {
      compatible: false,
      skipReason: `[skip] ModSecurity hosting probe failed: ${error instanceof Error ? error.message : String(error)}`,
    };
    return cachedProbe;
  }
}

/** Clears cached probe (for targeted re-runs in the same worker). */
export function resetModsecurityHostingProbeCache(): void {
  cachedProbe = undefined;
}
