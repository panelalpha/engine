import type { EngineApi } from '@/clients/engine-api';

/** Where each supported webserver keeps its per-domain vhost file. */
const VHOST_DIRS = [
  '/etc/nginx/conf.d',
  '/etc/nginx/sites-enabled',
  '/etc/apache2/sites-enabled',
  '/usr/local/lswd/conf/vhosts',
] as const;

/**
 * Reads the vhost file for `domain` from whichever webserver directory holds it.
 *
 * Returns `undefined` when no directory has one — which is both "the stack does
 * not use this path" and "the config was removed", so callers assert on presence
 * rather than distinguishing the two.
 */
export async function readVhostConfig(
  api: EngineApi,
  username: string,
  domain: string
): Promise<string | undefined> {
  for (const dir of VHOST_DIRS) {
    try {
      const content = await api.getFileContent(username, `${dir}/${domain}.conf`);
      if (content && content.length > 0) {
        return content;
      }
    } catch {
      // Not this stack's directory, or the file is gone — try the next one.
    }
  }
  return undefined;
}
