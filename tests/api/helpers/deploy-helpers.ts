import type { EngineApi } from '@/clients/engine-api';
import type { DeployLogSnapshot } from '@/types';
import { Timeouts } from '@/config/timeouts';
import { waitForCondition } from './retry';

/** Small public repo used as the live-engine git-deploy fixture. */
export const DEFAULT_DEPLOY_GIT_REPO = 'https://github.com/octocat/Spoon-Knife.git';

/** `GIT_REPO` when set, otherwise the public Spoon-Knife fixture. */
export function configuredGitRepo(): string {
  const fromEnv = process.env.GIT_REPO?.trim();
  return fromEnv !== undefined && fromEnv.length > 0 ? fromEnv : DEFAULT_DEPLOY_GIT_REPO;
}

export const TERMINAL_DEPLOY_STATUSES = ['success', 'partial', 'failed', 'cancelled'] as const;

export type TerminalDeployStatus = (typeof TERMINAL_DEPLOY_STATUSES)[number];

export function isTerminalDeployStatus(status: string): status is TerminalDeployStatus {
  return (TERMINAL_DEPLOY_STATUSES as readonly string[]).includes(status);
}

export async function waitForDeploy(
  api: EngineApi,
  username: string,
  options: { timeout?: number; interval?: number } = {}
): Promise<DeployLogSnapshot> {
  const timeout = options.timeout ?? Timeouts.deploy;
  const interval = options.interval ?? 3_000;
  let latest: DeployLogSnapshot | undefined;

  await waitForCondition(
    async () => {
      const response = await api.getDeployLogRaw(username);
      if (response.status !== 200) {
        return false;
      }
      const body = response.body as { data?: DeployLogSnapshot };
      latest = body.data;
      return latest !== undefined && isTerminalDeployStatus(latest.status);
    },
    {
      timeout,
      interval,
      message: `Deploy for ${username} did not reach a terminal status`,
      describeLast: () =>
        latest ? `status=${latest.status} stage=${latest.stage ?? 'none'}` : 'no deploy log yet',
    }
  );

  if (!latest) {
    throw new Error(`Deploy for ${username} produced no log`);
  }
  return latest;
}

export function containerServiceName(container: unknown): string | undefined {
  if (container === null || typeof container !== 'object') {
    return undefined;
  }
  const record = container as { Service?: unknown; service?: unknown; Name?: unknown };
  if (typeof record.Service === 'string' && record.Service.length > 0) {
    return record.Service;
  }
  if (typeof record.service === 'string' && record.service.length > 0) {
    return record.service;
  }
  return undefined;
}

/** Compose `ps --format json` State, e.g. `running` / `exited`. */
export function containerState(container: unknown): string | undefined {
  if (container === null || typeof container !== 'object') {
    return undefined;
  }
  const record = container as { State?: unknown; state?: unknown; Status?: unknown };
  if (typeof record.State === 'string' && record.State.length > 0) {
    return record.State.toLowerCase();
  }
  if (typeof record.state === 'string' && record.state.length > 0) {
    return record.state.toLowerCase();
  }
  if (typeof record.Status === 'string' && record.Status.length > 0) {
    return record.Status.toLowerCase();
  }
  return undefined;
}

export function containerIsRunning(container: unknown): boolean {
  const state = containerState(container);
  return state === 'running' || Boolean(state?.startsWith('up'));
}
