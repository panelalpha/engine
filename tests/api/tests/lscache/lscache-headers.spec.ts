import { expect, test } from '@/fixtures/test-options';
import {
  CACHE_EXPIRY_DELAY_MS,
  CACHE_POPULATE_DELAY_MS,
  CACHE_PROCESSING_DELAY_MS,
  LSCACHE_HEADER,
  LSCACHE_PLUGIN,
  ensureLscachePluginActive,
  getWithRetry,
  isLscachePluginActive,
  lscacheUnavailableReason,
  purgeAllLiteSpeedCache,
} from '@/helpers/lscache-helpers';
import { delay } from '@/helpers/retry';
import { wpPath } from '@/helpers/wpcli-helpers';

test.describe('LiteSpeed cache headers', () => {
  let wasActive: boolean;

  test.beforeEach(async ({ api, setupUser }) => {
    const reason = await lscacheUnavailableReason(api);
    test.skip(Boolean(reason), reason ?? '');

    wasActive = await isLscachePluginActive(api, setupUser.username, wpPath(setupUser));
  });

  test.afterEach(async ({ api, setupUser }) => {
    const path = wpPath(setupUser);
    await api
      .executeWpCliCommand(setupUser.username, [
        'plugin',
        wasActive ? 'activate' : 'deactivate',
        LSCACHE_PLUGIN,
        path,
      ])
      .catch(() => undefined);
  });

  test('a second request is served from cache when the plugin is active', async ({
    api,
    anonymousRequest,
    setupUser,
  }) => {
    const path = wpPath(setupUser);
    const homepage = `https://${setupUser.domain}`;

    await ensureLscachePluginActive(api, setupUser.username, path);
    await purgeAllLiteSpeedCache(api, setupUser.username, path);

    const first = await getWithRetry(anonymousRequest, homepage, {
      ignoreHTTPSErrors: true,
      maxRedirects: 5,
    });
    expect(first.status()).toBe(200);

    await delay(CACHE_POPULATE_DELAY_MS);

    const second = await getWithRetry(anonymousRequest, homepage, {
      ignoreHTTPSErrors: true,
      maxRedirects: 5,
    });
    expect(second.status()).toBe(200);
    expect(
      second.headers()[LSCACHE_HEADER]?.toLowerCase(),
      `the second request was not served from cache (${LSCACHE_HEADER}: ${
        second.headers()[LSCACHE_HEADER] ?? 'absent'
      })`
    ).toContain('hit');
  });

  /**
   * With the plugin off the site must still serve.
   *
   * The absence of the cache header is deliberately not asserted: on
   * OpenLiteSpeed the server-level cache sets it regardless of what WordPress is
   * doing, so asserting absence would fail for a reason unrelated to the plugin.
   * The observed value is annotated instead.
   */
  test('the site still serves with the plugin deactivated', async ({
    api,
    anonymousRequest,
    setupUser,
  }) => {
    const path = wpPath(setupUser);
    const homepage = `https://${setupUser.domain}`;

    if (wasActive) {
      await api.executeWpCliCommand(setupUser.username, [
        'plugin',
        'deactivate',
        LSCACHE_PLUGIN,
        path,
      ]);
    }

    await delay(CACHE_EXPIRY_DELAY_MS);

    await getWithRetry(anonymousRequest, homepage, {
      ignoreHTTPSErrors: true,
      maxRedirects: 5,
    });
    await delay(CACHE_PROCESSING_DELAY_MS);

    const response = await getWithRetry(anonymousRequest, homepage, {
      ignoreHTTPSErrors: true,
      maxRedirects: 5,
    });

    expect(response.status()).toBe(200);
    test.info().annotations.push({
      type: LSCACHE_HEADER,
      description: response.headers()[LSCACHE_HEADER] ?? 'absent',
    });
  });
});
