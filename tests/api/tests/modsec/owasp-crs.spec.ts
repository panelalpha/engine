import { expect, test } from '@/fixtures/test-options';
import {
  MODSEC_PROPAGATION_DELAY_MS,
  isBlockedResponse,
  modsecUnavailableReason,
  restoreModSec,
  snapshotModSec,
} from '@/helpers/modsec-helpers';
import { delay, waitForCondition } from '@/helpers/retry';
import { waitForSiteHttpReady } from '@/helpers/webserver-helpers';

const OWASP_PROPAGATION_DELAY_MS = 2_000;

/**
 * The OWASP Core Rule Set ships a restricted-files rule that must keep
 * `wp-config.php` — which holds the database credentials — off the web.
 */
test(
  'OWASP CRS blocks HTTP access to wp-config.php',
  {
    tag: ['@security'],
  },
  async ({ api, anonymousRequest, setupUser }) => {
    const reason = await modsecUnavailableReason(api, anonymousRequest, setupUser.url);
    test.skip(Boolean(reason), reason ?? '');

    const snapshot = await snapshotModSec(api);

    try {
      for (const ruleset of snapshot.rulesets.filter((entry) => !entry.enabled)) {
        await api.enableModSecurityRuleset(ruleset.name);
      }

      if (snapshot.mode !== 'on') {
        await api.setModSecurityConfig({ mode: 'on' });
        await waitForSiteHttpReady(anonymousRequest, `https://${setupUser.domain}/`, {
          timeout: 45_000,
          interval: 2_000,
        });
        await delay(OWASP_PROPAGATION_DELAY_MS);
      }

      const url = `https://${setupUser.domain}/wp-config.php`;

      // The rules reach the running webserver a moment after the API accepts
      // them, so this polls rather than asserting on the first response.
      await waitForCondition(
        async () => {
          try {
            const response = await anonymousRequest.get(url, {
              ignoreHTTPSErrors: true,
              timeout: 15_000,
            });
            return isBlockedResponse(response.status(), await response.text());
          } catch {
            return false;
          }
        },
        { timeout: 60_000, interval: 2_000, message: `${url} was never blocked by OWASP CRS` }
      );

      const final = await anonymousRequest.get(url, { ignoreHTTPSErrors: true });
      expect(await final.text(), 'the response body leaked database credentials').not.toMatch(
        /DB_PASSWORD/i
      );
    } finally {
      await restoreModSec(api, snapshot).catch(() => undefined);
      await delay(MODSEC_PROPAGATION_DELAY_MS);
    }
  }
);
