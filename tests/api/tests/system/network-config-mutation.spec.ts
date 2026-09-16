import { expect, test } from '@/fixtures/test-options';

/**
 * Isolated: a real address change can brick the engine. Skipped unless
 * ALLOW_NETWORK_MUTATION=1, and the original addresses are always restored.
 */
test.describe('network config mutation', () => {
  test.beforeEach(() => {
    test.skip(
      process.env.ALLOW_NETWORK_MUTATION !== '1',
      'Set ALLOW_NETWORK_MUTATION=1 to run live network-config updates.'
    );
  });

  test('writing the current addresses is a no-op round-trip', async ({ api }) => {
    const before = (await api.getSystemInfo()).data;
    test.skip(!before.default_ipv4, 'Engine has no default_ipv4 to round-trip.');

    try {
      const updated = await api.updateSystemNetworkConfig({
        default_ipv4: before.default_ipv4 ?? undefined,
        default_ipv6: before.default_ipv6 ?? undefined,
      });
      expect(updated.data.default_ipv4).toBe(before.default_ipv4);
    } finally {
      await api.updateSystemNetworkConfigRaw({
        default_ipv4: before.default_ipv4 ?? undefined,
        default_ipv6: before.default_ipv6 ?? undefined,
      });
    }
    const after = (await api.getSystemInfo()).data;
    expect(after.default_ipv4).toBe(before.default_ipv4);
    expect(after.default_ipv6).toBe(before.default_ipv6);
  });
});
