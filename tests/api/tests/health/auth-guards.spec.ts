import { expect, test } from '@/fixtures/test-options';
import { expectOneOf } from '@/helpers/expect-one-of';

const AUTH_KEYWORDS = /unauth|token|forbidden|auth/;

test.describe('auth guards', () => {
  test(
    'protected endpoints reject a request with no auth header',
    { tag: ['@smoke'] },
    async ({ playwright, settings }) => {
      const request = await playwright.request.newContext({
        baseURL: settings.apiBaseUrl,
        ignoreHTTPSErrors: true,
        extraHTTPHeaders: { Accept: 'application/json' },
      });

      try {
        const response = await request.get('projects');
        expectOneOf(response.status(), [401, 403]);
        expect((await response.text()).toLowerCase()).toMatch(AUTH_KEYWORDS);
      } finally {
        await request.dispose();
      }
    }
  );

  test('protected endpoints reject an invalid bearer token', async ({ playwright, settings }) => {
    const request = await playwright.request.newContext({
      baseURL: settings.apiBaseUrl,
      ignoreHTTPSErrors: true,
      extraHTTPHeaders: {
        Accept: 'application/json',
        Authorization: 'Bearer invalid-token-for-coverage',
      },
    });

    try {
      const response = await request.get('system/info');
      expectOneOf(response.status(), [401, 403]);
      expect((await response.text()).toLowerCase()).toMatch(AUTH_KEYWORDS);
    } finally {
      await request.dispose();
    }
  });
});
