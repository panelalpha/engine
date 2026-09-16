import fs from 'fs';
import path from 'path';
import type { EngineApi } from '@/clients/engine-api';
import { isTestUsername } from '@/helpers/random';
import type { UserFactory } from '@/test-data/factories/user.factory';
import type { SetupTestData, SystemChangeStatus } from '@/types';

export interface WebserverChangeDebugContext {
  setupUsername?: string;
  setupDomain?: string;
  setupUrl?: string;
  stuckUsername?: string;
  logsPath?: string;
}

export interface CleanupStaleTestUsersOptions {
  keepUsernames?: string[];
}

export function parseStuckUsernameFromChangeTail(
  change: SystemChangeStatus | null | undefined
): string | undefined {
  const haystack = `${change?.tail_stdout ?? ''}\n${change?.tail_stderr ?? ''}`;
  const match = /\/(?:projects|users)\/([a-z0-9_-]+)\//i.exec(haystack);
  return match?.[1];
}

export function buildWebserverChangeDebugContext(
  setupData: SetupTestData | undefined,
  change: SystemChangeStatus | null | undefined
): WebserverChangeDebugContext {
  return {
    setupUsername: setupData?.username,
    setupDomain: setupData?.domain,
    setupUrl: setupData?.url,
    stuckUsername: parseStuckUsernameFromChangeTail(change),
    logsPath: change?.logs_path ?? '/opt/panelalpha/log/change-webserver/latest',
  };
}

export function formatWebserverChangeDebugMessage(debug: WebserverChangeDebugContext): string {
  const lines = ['Debug — setup user (left on server):'];
  if (debug.setupUsername) {
    lines.push(`  username: ${debug.setupUsername}`);
  }
  if (debug.setupDomain) {
    lines.push(`  domain:   ${debug.setupDomain}`);
  }
  if (debug.setupUrl) {
    lines.push(`  url:      ${debug.setupUrl}`);
  }
  if (debug.logsPath) {
    lines.push(`Change log: ${debug.logsPath}`);
  }
  if (debug.stuckUsername) {
    lines.push(`Stuck on user (from rebuild tail): ${debug.stuckUsername}`);
  }
  return lines.join('\n');
}

export function writeSetupDebugEnv(debug: WebserverChangeDebugContext): void {
  const dir = path.resolve(process.cwd(), '.playwright');
  fs.mkdirSync(dir, { recursive: true });
  const lines = [
    `SETUP_USERNAME=${debug.setupUsername ?? ''}`,
    `SETUP_DOMAIN=${debug.setupDomain ?? ''}`,
    `SETUP_URL=${debug.setupUrl ?? ''}`,
    `STUCK_USERNAME=${debug.stuckUsername ?? ''}`,
    `CHANGE_LOG_PATH=${debug.logsPath ?? ''}`,
  ];
  fs.writeFileSync(path.join(dir, 'setup-debug.env'), `${lines.join('\n')}\n`, 'utf-8');
}

/**
 * Persist the full latest_webserver_change snapshot to .playwright/change-snapshot.json.
 * Survives engine reinstalls (which wipe /opt/panelalpha/log/change-webserver/*) so the
 * change stdout/stderr tail remains available as an orchestrator run artifact.
 */
export function writeChangeSnapshot(change: SystemChangeStatus | null | undefined): void {
  const dir = path.resolve(process.cwd(), '.playwright');
  fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(
    path.join(dir, 'change-snapshot.json'),
    JSON.stringify(change ?? {}, null, 2),
    'utf-8'
  );
}

export async function cleanupStaleTestUsers(
  api: EngineApi,
  userFactory: UserFactory,
  options: CleanupStaleTestUsersOptions = {}
): Promise<string[]> {
  const keep = new Set(
    (options.keepUsernames ?? []).map((username) => username.trim().toLowerCase()).filter(Boolean)
  );
  const removed: string[] = [];

  let users: { username: string; domain: string }[] = [];
  try {
    const response = await api.listAllUsers(true);
    users = (response.data ?? []).map((user) => ({
      username: user.username,
      domain: user.domain,
    }));
  } catch (error) {
    console.warn(
      '[cleanup] Failed to list users for stale cleanup:',
      error instanceof Error ? error.message : error
    );
    return removed;
  }

  for (const user of users) {
    const username = user.username.trim();
    if (!username || keep.has(username.toLowerCase())) {
      continue;
    }
    if (!isTestUsername(username)) {
      continue;
    }
    try {
      await userFactory.deleteUser(username);
      removed.push(username);
      console.log(`[cleanup] Removed stale test user: ${username} (${user.domain})`);
    } catch (error) {
      console.warn(
        `[cleanup] Failed to remove stale user ${username}:`,
        error instanceof Error ? error.message : error
      );
    }
  }

  return removed;
}
