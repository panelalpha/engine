import type { EngineApi } from '@/clients/engine-api';

/** Time CSF needs to reload its rules after a restart. */
export const CSF_RESTART_DELAY_MS = 30_000;
/** Disabling flushes iptables and takes noticeably longer than a restart. */
export const CSF_DISABLE_DELAY_MS = 60_000;
/** Time for an added or removed rule to show up in the rules listing. */
export const CSF_PROPAGATION_DELAY_MS = 2_000;

export interface CsfState {
  enabled: boolean;
  version: string;
}

/**
 * Reads CSF's configuration, or `undefined` when the module is not installed.
 *
 * CSF is optional, so specs skip on `undefined` rather than failing — but they
 * must skip explicitly, so an actual CSF failure is never mistaken for absence.
 */
export async function readCsfState(api: EngineApi): Promise<CsfState | undefined> {
  try {
    return (await api.getCsfConfig()).data;
  } catch {
    return undefined;
  }
}

/**
 * Toggling CSF flushes iptables, which drops the connection carrying the very
 * request that asked for it. A reset here means the command was applied.
 */
export function isConnectionDroppedByFirewall(error: unknown): boolean {
  const message = error instanceof Error ? error.message : String(error);
  return message.includes('ECONNRESET') || message.includes('socket hang up');
}

/** Runs a CSF toggle, tolerating the connection drop it causes. */
export async function applyCsfToggle(toggle: () => Promise<unknown>): Promise<void> {
  try {
    await toggle();
  } catch (error) {
    if (!isConnectionDroppedByFirewall(error)) {
      throw error;
    }
  }
}
