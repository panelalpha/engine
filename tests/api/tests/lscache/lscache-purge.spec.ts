import { expect, test } from '@/fixtures/test-options';
import {
  CACHE_POPULATE_DELAY_MS,
  LSCACHE_HEADER,
  LSCACHE_PLUGIN,
  MAX_PURGE_WAIT_MS,
  PURGE_RETRY_DELAY_MS,
  ensureLscachePluginActive,
  getWithRetry,
  isLscachePluginActive,
  lscacheUnavailableReason,
  purgeAllLiteSpeedCache,
} from '@/helpers/lscache-helpers';
import { delay, waitForCondition } from '@/helpers/retry';
import { parseWpCliId } from '@/helpers/permalinks-http-helpers';
import { wpPath } from '@/helpers/wpcli-helpers';

/**
 * A cache that never invalidates is worse than no cache: the site owner edits a
 * page and visitors keep seeing the old one. These publish real content and read
 * it back over HTTPS, which is the only way to tell a purge actually happened.
 */
test.describe('LiteSpeed cache purging', () => {
  let wasActive: boolean;

  test.beforeEach(async ({ api, setupUser }) => {
    const reason = await lscacheUnavailableReason(api);
    test.skip(Boolean(reason), reason ?? '');

    wasActive = await isLscachePluginActive(api, setupUser.username, wpPath(setupUser));
  });

  test.afterEach(async ({ api, setupUser }) => {
    await api
      .executeWpCliCommand(setupUser.username, [
        'plugin',
        wasActive ? 'activate' : 'deactivate',
        LSCACHE_PLUGIN,
        wpPath(setupUser),
      ])
      .catch(() => undefined);
  });

  test('a purge makes the updated page visible', async ({ api, anonymousRequest, setupUser }) => {
    const path = wpPath(setupUser);
    const run = (...args: string[]) => api.executeWpCliCommand(setupUser.username, [...args, path]);

    const stamp = Date.now();
    const cachedMarker = `LSCache Cached ${stamp}`;
    const updatedMarker = `LSCache Updated ${stamp}`;

    await ensureLscachePluginActive(api, setupUser.username, path, { forceInstall: true });

    const created = await run(
      'post',
      'create',
      '--post_type=post',
      `--post_title=${cachedMarker}`,
      `--post_content=${cachedMarker}`,
      '--post_status=publish',
      '--porcelain'
    );
    const postId = parseWpCliId(created.stdout);
    expect(postId, `WP-CLI returned no post id: ${created.stdout}`).toMatch(/^\d+$/);

    try {
      const permalink = (await run('eval', `echo get_permalink(${postId!});`)).stdout.trim();
      expect(permalink).toBeTruthy();

      const fetchPage = () =>
        getWithRetry(anonymousRequest, permalink, {
          ignoreHTTPSErrors: true,
          maxRedirects: 5,
          headers: { Cookie: '' },
        });

      await purgeAllLiteSpeedCache(api, setupUser.username, path);

      await test.step('prime the cache', async () => {
        expect((await fetchPage()).status()).toBe(200);
        await delay(CACHE_POPULATE_DELAY_MS);

        const cached = await fetchPage();
        expect(cached.status()).toBe(200);
        expect(await cached.text()).toContain(cachedMarker);

        test.info().annotations.push({
          type: LSCACHE_HEADER,
          description: cached.headers()[LSCACHE_HEADER] ?? 'absent',
        });
      });

      await run(
        'post',
        'update',
        postId!,
        `--post_title=${updatedMarker}`,
        `--post_content=${updatedMarker}`
      );
      await purgeAllLiteSpeedCache(api, setupUser.username, path);

      await test.step('the purge lets the update through', async () => {
        await waitForCondition(
          async () => (await (await fetchPage()).text()).includes(updatedMarker),
          {
            timeout: MAX_PURGE_WAIT_MS,
            interval: PURGE_RETRY_DELAY_MS,
            message: `${permalink} still served the pre-update content after a full purge`,
          }
        );
      });
    } finally {
      await run('post', 'delete', postId!, '--force').catch(() => undefined);
      await purgeAllLiteSpeedCache(api, setupUser.username, path).catch(() => undefined);
    }
  });

  test('an option change is visible on the site after a purge', async ({
    api,
    anonymousRequest,
    setupUser,
  }) => {
    const path = wpPath(setupUser);
    const run = (...args: string[]) => api.executeWpCliCommand(setupUser.username, [...args, path]);

    const updatedTitle = `TestSite Updated ${Date.now()}`;
    const originalTitle = (await run('option', 'get', 'blogname')).stdout.trim();

    await ensureLscachePluginActive(api, setupUser.username, path, { forceInstall: true });

    try {
      const updated = await run('option', 'update', 'blogname', updatedTitle);
      expect(updated.exit_code, updated.stderr).toBe(0);

      await purgeAllLiteSpeedCache(api, setupUser.username, path);

      await waitForCondition(
        async () => {
          const response = await getWithRetry(anonymousRequest, `https://${setupUser.domain}/`, {
            ignoreHTTPSErrors: true,
            maxRedirects: 5,
            headers: { Cookie: '' },
          });
          return (await response.text()).includes(updatedTitle);
        },
        {
          timeout: MAX_PURGE_WAIT_MS,
          interval: PURGE_RETRY_DELAY_MS,
          message: 'the homepage still showed the old site title after a full purge',
        }
      );
    } finally {
      await run('option', 'update', 'blogname', originalTitle).catch(() => undefined);
      await purgeAllLiteSpeedCache(api, setupUser.username, path).catch(() => undefined);
    }
  });
});
