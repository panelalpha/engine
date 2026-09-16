import { expect, test } from '@/fixtures/test-options';
import { uniqueId } from '@/helpers/random';

const TEST_SETTING = 'pa_cli_test_setting';

test.describe('pae-artisan settings', () => {
  test.beforeEach(({ hostExec }) => {
    test.skip(!hostExec, 'pae-artisan is not reachable from this runner.');
  });

  test('set, get and exists round-trip a test key', async ({ hostExec }) => {
    const value = uniqueId('v');
    const previous = await hostExec!.pae(['settings:get', TEST_SETTING]);

    try {
      const set = await hostExec!.pae(['settings:set', TEST_SETTING, value]);
      expect(set.exitCode).toBe(0);

      const got = await hostExec!.pae(['settings:get', TEST_SETTING]);
      expect(got.exitCode).toBe(0);
      expect(got.stdout.trim()).toBe(value);

      const exists = await hostExec!.pae(['settings:exists', TEST_SETTING]);
      expect(exists.exitCode).toBe(0);
    } finally {
      if (previous.exitCode === 0 && previous.stdout.trim().length > 0) {
        await hostExec!.pae(['settings:set', TEST_SETTING, previous.stdout.trim()]);
      }
    }
  });

  test('exists is non-zero for a missing key', async ({ hostExec }) => {
    const result = await hostExec!.pae(['settings:exists', `pa_missing_${uniqueId('k')}`]);
    expect(result.exitCode).not.toBe(0);
  });

  test('ssl_issuer stays self_signed when it already was', async ({ hostExec, api }) => {
    const config = (await api.getSslConfig()).data;
    test.skip(config.issuer !== 'self_signed', 'issuer is not self_signed; not mutating it.');
    const set = await hostExec!.pae(['settings:set', 'ssl_issuer', 'self_signed']);
    expect(set.exitCode).toBe(0);
    expect((await api.getSslConfig()).data.issuer).toBe('self_signed');
  });
});
