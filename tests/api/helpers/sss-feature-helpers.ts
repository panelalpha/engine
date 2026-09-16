import type { EngineApi } from '@/clients/engine-api';

/** Whether proxy-rule management answers at all on this engine. */
export async function isProxyRulesAvailable(api: EngineApi): Promise<boolean> {
  const response = await api.listProxyRulesRaw();
  return response.status === 200;
}

/**
 * Whether container/deploy endpoints accept this user as a DinD project.
 *
 * A shared WordPress user answers 403; a missing user answers 404. Either way
 * the DinD-specific specs should skip rather than fail.
 */
export async function isDindUser(api: EngineApi, username: string): Promise<boolean> {
  const response = await api.listContainersRaw(username);
  return response.status === 200;
}
