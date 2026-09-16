import { expect, test } from '@/fixtures/test-options';
import { waitUntilStagingActive } from '@/helpers/staging-helpers';

test.describe('waitUntilStagingActive', () => {
  test('fails on the first HTTP 404 instead of polling the 180s budget', async () => {
    const started = Date.now();
    const api = {
      getUserRaw: () => Promise.resolve({ status: 404, body: {} }),
    };

    await expect(waitUntilStagingActive(api, 'pwdead')).rejects.toThrow(/HTTP 404/);
    expect(Date.now() - started).toBeLessThan(5_000);
  });
});
