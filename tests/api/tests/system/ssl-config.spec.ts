import { expect, test } from '@/fixtures/test-options';
import { sslConfigResponseSchema } from '@/schemas';
import { validateParsedApiResponse } from '@/helpers/validate-parsed-response';
import { expectOneOf } from '@/helpers/expect-one-of';

test.describe('system SSL config', () => {
  test('the config has the issuer and sites_base_domain fields', async ({ api }) => {
    const response = await api.getSslConfig();
    validateParsedApiResponse(response, sslConfigResponseSchema);
    expect(typeof response.data.issuer).toBe('string');
    expect(typeof response.data.shared_zone).toBe('boolean');
  });

  test('an invalid issuer is refused', async ({ api }) => {
    const response = await api.updateSslConfigRaw({ issuer: 'not-an-issuer' });
    expectOneOf(response.status, [400, 422]);
  });

  test('an invalid sites_base_domain is refused', async ({ api }) => {
    const response = await api.updateSslConfigRaw({ sites_base_domain: 'not a hostname' });
    expectOneOf(response.status, [400, 422]);
  });

  test('self_signed round-trips and the original config is restored', async ({ api }) => {
    const original = (await api.getSslConfig()).data;
    try {
      const updated = await api.updateSslConfig({ issuer: 'self_signed' });
      expect(updated.data.issuer).toBe('self_signed');
    } finally {
      await api.updateSslConfig({
        issuer: original.issuer,
        sites_base_domain: original.sites_base_domain,
        acme_directory_url: original.acme_directory_url,
        acme_email: original.acme_email,
      });
    }
  });
});
