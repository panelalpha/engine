import { test } from '@/fixtures/test-options';
import { expectOneOf } from '@/helpers/expect-one-of';

/**
 * Isolated project: requesting the engine certificate briefly takes hosted
 * sites offline. Only dry_run is used; never force_renewal.
 */
test.describe('engine certificate', () => {
  test('validation rejects a malformed domain', async ({ api }) => {
    const response = await api.requestEngineCertificateRaw({ domain: 'not a hostname' });
    expectOneOf(response.status, [400, 422]);
  });

  test('dry_run runs the challenge without installing a certificate', async ({ api }) => {
    const response = await api.requestEngineCertificateRaw({ dry_run: true, staging: true });
    expectOneOf(response.status, [200, 422]);
  });
});
