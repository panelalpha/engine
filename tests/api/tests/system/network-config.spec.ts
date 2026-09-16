import { expect, test } from '@/fixtures/test-options';
import { expectOneOf } from '@/helpers/expect-one-of';

test.describe('network configuration', () => {
  const invalidAddresses = [
    ['default_ipv4', { default_ipv4: 'not-an-ipv4-address' }],
    ['default_ipv6', { default_ipv6: 'not-an-ipv6-address' }],
  ] as const;

  for (const [field, payload] of invalidAddresses) {
    test(`a malformed ${field} is refused`, async ({ authedRequest }) => {
      const response = await authedRequest.put('system/network-config', { data: payload });
      expectOneOf(response.status(), [400, 422]);
    });
  }

  test('an empty payload changes nothing', async ({ api, authedRequest }) => {
    const before = (await api.getSystemInfo()).data;

    const response = await authedRequest.put('system/network-config', { data: {} });
    // Treating "no fields" as a no-op or as a validation error are both fine.
    expectOneOf(response.status(), [200, 422]);

    const after = (await api.getSystemInfo()).data;
    expect(after.default_ipv4, 'an empty payload changed default_ipv4').toBe(before.default_ipv4);
    expect(after.default_ipv6, 'an empty payload changed default_ipv6').toBe(before.default_ipv6);
  });
});
