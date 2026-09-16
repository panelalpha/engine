import { expect, test } from '@/fixtures/test-options';
import { waitForCondition } from '@/helpers/retry';

/** The global endpoints address a domain directly, without going through its user. */
test.describe('global domain endpoints', () => {
  test('domain details resolve without a username', async ({ api, setupUser }) => {
    expect((await api.getDomainGlobal(setupUser.domain)).data.domain).toBe(setupUser.domain);
  });

  test('the PHP version is reported in a valid format', async ({
    api,
    domainAssertions,
    setupUser,
  }) => {
    await domainAssertions.verifyPhpVersionFormat(setupUser.domain);
    expect((await api.getDomainPhpVersion(setupUser.domain)).data).toBeTruthy();
  });

  test('the PHP version can be switched and switched back', async ({
    api,
    domainAssertions,
    settings,
    setupUser,
  }) => {
    const available = (await api.getAvailablePhpVersions()).data ?? [];
    test.skip(available.length < 2, 'The engine offers fewer than two PHP versions.');

    const original = (await api.getDomainPhpVersion(setupUser.domain)).data;
    const target = available.find((version) => version !== original) ?? available[0];

    const settleTimeout =
      settings.timing.propagationDelay + settings.timing.phpExecutionDelay + 10_000;
    const waitForVersion = (version: string) =>
      waitForCondition(
        async () => (await api.getDomainPhpVersion(setupUser.domain)).data === version,
        {
          timeout: settleTimeout,
          interval: 2_000,
          message: `PHP version did not settle on ${version} for ${setupUser.domain}`,
        }
      );

    try {
      await api.setDomainPhpVersion(setupUser.domain, target);
      await waitForVersion(target);
      await domainAssertions.verifyPhpVersion(setupUser.domain, target);
    } finally {
      // The shared setup user must go back to the version everything else expects.
      await api.setDomainPhpVersion(setupUser.domain, original);
      await waitForVersion(original);
    }
  });
});
