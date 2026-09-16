import { expect, test } from '@/fixtures/test-options';
import { expectNotOneOf } from '@/helpers/expect-one-of';
import {
  AJAX_ACTION,
  AJAX_PLUGIN_CONTENTS,
  AJAX_PLUGIN_FILENAME,
  MODSEC_PROPAGATION_DELAY_MS,
  isAuthorEnumerationMitigated,
  isBlockedBody,
  isBlockedStatus,
  modsecUnavailableReason,
  restoreModSec,
  snapshotModSec,
  type ModSecSnapshot,
} from '@/helpers/modsec-helpers';
import { delay } from '@/helpers/retry';
import {
  getWebserverInfo,
  getWebserverPropagationDelay,
  waitForSiteHttpReady,
} from '@/helpers/webserver-helpers';

/**
 * What the WAF must block, and — just as importantly — what it must not.
 *
 * Every test here runs with ModSecurity on and every ruleset enabled, the
 * strictest configuration a customer can end up in. The mode, the ruleset
 * states and the test plugin are all restored afterwards.
 */
test.describe('ModSecurity WAF', () => {
  let snapshot: ModSecSnapshot;
  let pluginPath: string;
  let ajaxUrl: string;

  test.beforeEach(async ({ api, anonymousRequest, setupUser }) => {
    const reason = await modsecUnavailableReason(api, anonymousRequest, setupUser.url);
    test.skip(Boolean(reason), reason ?? '');
    test.skip(!setupUser.wpPath, 'The setup user has no WordPress installation.');

    snapshot = await snapshotModSec(api);

    const pluginDir = `${setupUser.wpPath}/wp-content/mu-plugins`;
    pluginPath = `${pluginDir}/${AJAX_PLUGIN_FILENAME}`;
    ajaxUrl = `https://${setupUser.domain}/wp-admin/admin-ajax.php`;

    await api.createDirectory(setupUser.username, pluginDir, true);
    await api.putFileContents(setupUser.username, pluginPath, AJAX_PLUGIN_CONTENTS);

    for (const ruleset of snapshot.rulesets.filter((entry) => !entry.enabled)) {
      await api.enableModSecurityRuleset(ruleset.name);
    }

    await api.setModSecurityConfig({ mode: 'on' });
    await waitForSiteHttpReady(anonymousRequest, `https://${setupUser.domain}/`, {
      timeout: 45_000,
      interval: 2_000,
    });
    await delay(getWebserverPropagationDelay((await getWebserverInfo(api)).slug));
  });

  test.afterEach(async ({ api, setupUser }) => {
    await api.removeFile(setupUser.username, pluginPath).catch(() => undefined);
    await restoreModSec(api, snapshot).catch(() => undefined);
    await delay(MODSEC_PROPAGATION_DELAY_MS);
  });

  test.describe('what must be blocked', () => {
    for (const path of ['xmlrpc.php', 'wp-config.php']) {
      test(
        `/${path} is blocked`,
        { tag: ['@security'] },
        async ({ anonymousRequest, setupUser }) => {
          const url = `https://${setupUser.domain}/${path}`;
          const response = await anonymousRequest.get(url, { ignoreHTTPSErrors: true });
          const body = await response.text();

          expect(
            isBlockedStatus(response.status()) || isBlockedBody(body),
            `${url} answered ${response.status()} instead of being blocked`
          ).toBe(true);
        }
      );
    }

    test(
      'author enumeration via ?author=1 is mitigated',
      { tag: ['@security'] },
      async ({ anonymousRequest, setupUser }) => {
        const url = `https://${setupUser.domain}/?author=1`;
        const response = await anonymousRequest.get(url, { ignoreHTTPSErrors: true });

        expect(
          isAuthorEnumerationMitigated({
            status: response.status(),
            body: await response.text(),
            finalUrl: response.url(),
          }),
          `${url} served an author archive, leaking a username`
        ).toBe(true);
      }
    );

    test(
      'the REST users endpoint lists nothing anonymously',
      { tag: ['@security'] },
      async ({ anonymousRequest, setupUser }) => {
        const url = `https://${setupUser.domain}/wp-json/wp/v2/users?per_page=100`;
        const response = await anonymousRequest.get(url, { ignoreHTTPSErrors: true });
        const body = await response.text();

        expect(
          isBlockedStatus(response.status()) ||
            response.status() === 401 ||
            isBlockedBody(body) ||
            /rest_user_cannot_view/i.test(body),
          `${url} answered ${response.status()} and may be listing users`
        ).toBe(true);
      }
    );

    test('repeated wp-login.php requests never produce a server error', async ({
      anonymousRequest,
      setupUser,
    }) => {
      const url = `https://${setupUser.domain}/wp-login.php`;
      const statuses: number[] = [];

      for (let attempt = 0; attempt < 8; attempt++) {
        const response = await anonymousRequest.get(`${url}?t=${Date.now()}&i=${attempt}`, {
          ignoreHTTPSErrors: true,
        });
        statuses.push(response.status());

        // Being rate-limited is the ideal outcome; stop as soon as it happens.
        if (isBlockedStatus(response.status())) {
          return;
        }
        if (/too many requests|rate limit/i.test(await response.text())) {
          return;
        }
      }

      expect(
        statuses.every((status) => [200, 302, 403, 404, 405, 406, 429].includes(status)),
        `wp-login.php answered ${statuses.join(', ')} — one of those is a server error`
      ).toBe(true);
    });
  });

  test.describe('what must keep working', () => {
    test('a POST to admin-ajax succeeds', async ({ anonymousRequest }) => {
      const response = await anonymousRequest.post(ajaxUrl, {
        form: { action: AJAX_ACTION, payload: 'ping' },
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        ignoreHTTPSErrors: true,
      });

      expectNotOneOf(response.status(), [403, 406], 'admin-ajax POST was blocked by the WAF');
      expect(response.status()).toBe(200);
      expect(await response.text()).toContain('"success":true');
    });

    test('a GET to admin-ajax succeeds', async ({ anonymousRequest }) => {
      const response = await anonymousRequest.get(`${ajaxUrl}?action=${AJAX_ACTION}&payload=ping`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        ignoreHTTPSErrors: true,
      });

      expectNotOneOf(response.status(), [403, 406], 'admin-ajax GET was blocked by the WAF');
      expect(response.status()).toBe(200);
      expect(await response.text()).toContain('"success":true');
    });

    /** A burst is what trips an over-eager anomaly score. */
    test('concurrent admin-ajax POSTs all succeed', async ({ anonymousRequest }) => {
      const responses = await Promise.all(
        Array.from({ length: 5 }, () =>
          anonymousRequest.post(ajaxUrl, {
            form: { action: AJAX_ACTION, payload: 'ping' },
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            ignoreHTTPSErrors: true,
          })
        )
      );

      expect(responses.map((response) => response.status())).toEqual([200, 200, 200, 200, 200]);
    });
  });
});
