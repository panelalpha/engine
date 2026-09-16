import type { EngineApi } from '@/clients/engine-api';
import { skipUnless } from './test-helpers';

/**
 * The address FTP and SFTP listen on.
 *
 * A project's public hostname is often `*.panelalpha.online`, which resolves
 * to the WithoutDNS proxy. That proxy does not speak FTP or SFTP — connecting
 * there is ECONNREFUSED on :21 / :2222 even when the daemons on the engine
 * are up. Always use the engine's own IPv4.
 */
export async function resolveEngineIpv4(api: EngineApi): Promise<string | null> {
  try {
    const data = (await api.getSystemInfo()).data as { default_ipv4?: string };
    const ip = data?.default_ipv4?.trim();
    return ip && /^\d{1,3}(\.\d{1,3}){3}$/.test(ip) ? ip : null;
  } catch {
    return null;
  }
}

/** Engine IPv4, or skip the current test when system/info has none. */
export async function requireEngineConnectHost(api: EngineApi): Promise<string> {
  const ip = await resolveEngineIpv4(api);
  skipUnless(
    ip,
    'system/info reports no default IPv4; FTP/SFTP listen on the engine, not the tunnel proxy.'
  );
  return ip;
}
