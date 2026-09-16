import type { EngineApi } from '@/clients/engine-api';
import type { SetupTestData } from '@/types/user.types';
import { waitForWpCliReady } from '@/helpers/wp-cli-helpers';

export interface WpCliResult {
  exit_code: number;
  stdout: string;
  stderr: string;
}

/** Every WP-CLI call needs the install path; this keeps it out of every call site. */
export function wpPath(setupUser: Pick<SetupTestData, 'wpPath'>): string {
  return `--path=${setupUser.wpPath}`;
}

/**
 * Why WP-CLI tests cannot run here, or `undefined` when they can.
 *
 * WP-CLI is disabled outright on some deployments (the endpoint 404s) and
 * forbidden on others (403, or a body whose output starts with "Forbidden"),
 * and both are reasons to skip rather than fail.
 */
export async function wpCliUnavailableReason(
  api: EngineApi,
  setupUser: SetupTestData
): Promise<string | undefined> {
  if (!setupUser.username || !setupUser.wpPath) {
    return 'The setup user has no WordPress installation.';
  }

  let probe: WpCliResult;
  try {
    probe = await api.executeWpCliCommand(setupUser.username, ['--info', wpPath(setupUser)]);
  } catch (error) {
    const message = String(error);
    if (/\b404\b/.test(message)) {
      return 'The WP-CLI endpoint is not available on this engine.';
    }
    if (/\b403\b/.test(message) || /forbidden/i.test(message)) {
      return 'WP-CLI is forbidden on this engine.';
    }
    return `The WP-CLI probe failed: ${message.slice(0, 200)}`;
  }

  if (isForbidden(probe)) {
    return 'WP-CLI is forbidden on this engine.';
  }
  if (probe.exit_code !== 0) {
    return `The WP-CLI probe exited ${probe.exit_code}: ${probe.stderr.slice(0, 200)}`;
  }

  await waitForWpCliReady(api, setupUser.username, setupUser.wpPath);
  return undefined;
}

function isForbidden(result: WpCliResult): boolean {
  return /forbidden/i.test(result.stderr) || /forbidden/i.test(result.stdout);
}

/**
 * Whether the stack is missing `mysqlcheck`, which `wp db check` and
 * `wp db optimize` shell out to. Its absence is an image packaging choice, not
 * a database problem, so those tests skip on it.
 */
export function isMysqlcheckMissing(result: WpCliResult): boolean {
  return result.exit_code === 127 || /mysqlcheck.+No such file|not found/i.test(result.stderr);
}
