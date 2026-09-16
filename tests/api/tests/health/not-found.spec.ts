import { expect, test } from '@/fixtures/test-options';
import { httpScheme, isUnknownHostResponse } from '@/helpers/webserver-helpers';
import { rand } from '@/helpers/random';

test.describe('404 handling', () => {
  const missingResources = [
    ['project', 'projects/nonexistent_user_xyz_12345'],
    ['domain', 'domains/nonexistent.domain.xyz'],
    ['domain PHP version', 'domains/nonexistent.domain.xyz/php-version'],
    ['API route', 'this/endpoint/does/not/exist'],
  ] as const;

  for (const [label, path] of missingResources) {
    test(`unknown ${label} returns 404`, async ({ api }) => {
      expect((await api.get(path)).status()).toBe(404);
    });
  }

  test('unknown sub-resources of an existing user return 404', async ({ api, setupUser }) => {
    const responses = await Promise.all([
      api.get(`projects/${setupUser.username}/mysql/databases/nonexistent_db_xyz`),
      api.get(`projects/${setupUser.username}/ftp-accounts/nonexistent_ftp_xyz`),
      api.delete(`projects/${setupUser.username}/cron-jobs/nonexistent_hash_xyz`),
      api.get(`projects/${setupUser.username}/domains/nonexistent.domain.xyz/log-files`),
    ]);

    for (const response of responses) {
      expect(response.status(), `${response.url()} should be 404`).toBe(404);
    }
  });

  test('an unhosted domain gets an error page over HTTP', async ({
    anonymousRequest,
    settings,
  }) => {
    const baseDomain = settings.requireDomain();
    const url = `${httpScheme(settings.apiBaseUrl)}://missing-${rand()}.${baseDomain}/`;

    const response = await anonymousRequest.get(url, { ignoreHTTPSErrors: true });
    const body = await response.text();

    expect(
      isUnknownHostResponse(response.status(), body),
      `${url} answered ${response.status()}, which is not a valid unknown-host response`
    ).toBe(true);
  });

  test('the engine IP itself serves no site', async ({ api, anonymousRequest, settings }) => {
    const host = new URL(settings.apiBaseUrl).hostname;
    const isLiteral = /^\d{1,3}(\.\d{1,3}){3}$/.test(host) || host.includes(':');

    const ip = isLiteral
      ? host
      : await api
          .getSystemInfo()
          .then(({ data }) => data.default_ipv4 ?? data.default_ipv6 ?? '')
          .catch(() => '');

    test.skip(!ip, 'Engine IP could not be resolved from the API base URL or system info.');

    const url = `${httpScheme(settings.apiBaseUrl)}://${ip.includes(':') ? `[${ip}]` : ip}/`;
    const response = await anonymousRequest.get(url, { ignoreHTTPSErrors: true });
    const body = await response.text();

    expect(
      isUnknownHostResponse(response.status(), body),
      `${url} answered ${response.status()}, which is not a valid unknown-host response`
    ).toBe(true);
  });
});
