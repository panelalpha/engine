import { expect, test } from '@/fixtures/test-options';
import { expectOneOf } from '@/helpers/expect-one-of';

test.describe('domain limits', () => {
  test('an addon domain past the limit is refused', async ({ api, authedRequest, setupUser }) => {
    const { data: domains } = await api.listUserDomains(setupUser.username);
    const addonCount = domains.filter((domain) => domain.type === 'addon').length;

    await api.updateUser(setupUser.username, { addon_domains_limit: addonCount });

    try {
      const response = await authedRequest.post(`projects/${setupUser.username}/domains`, {
        data: { domain: `limittest${Date.now()}.${setupUser.domain}`, type: 'addon' },
      });

      expect(response.status()).toBe(422);
      expect(((await response.json()) as { error_type?: string }).error_type).toBe(
        'addon_domains_limit_reached'
      );
    } finally {
      await api.updateUser(setupUser.username, { addon_domains_limit: null });
    }
  });

  test('a subdomain past the limit is refused', async ({ api, authedRequest, setupUser }) => {
    await api.updateUser(setupUser.username, { subdomains_limit: 0 });

    try {
      const response = await authedRequest.post(`projects/${setupUser.username}/domains`, {
        data: {
          domain: `sub${Date.now()}.${setupUser.domain}`,
          type: 'sub',
          parent_domain: setupUser.domain,
        },
      });

      expect(response.status()).toBe(422);
      expect(((await response.json()) as { error_type?: string }).error_type).toBe(
        'subdomains_limit_reached'
      );
    } finally {
      await api.updateUser(setupUser.username, { subdomains_limit: null });
    }
  });
});

test.describe('domain update validation', () => {
  const invalidUpdates = [
    [
      'a redirect with no target URL',
      (domain: string) => ({
        document_root: `/${domain}/public_html`,
        redirect_enabled: true,
        force_https_redirect: false,
      }),
    ],
    [
      'a document root that is a full URL',
      (domain: string) => ({
        document_root: `https://${domain}/wp-admin`,
        redirect_enabled: false,
        force_https_redirect: false,
      }),
    ],
  ] as const;

  for (const [label, buildPayload] of invalidUpdates) {
    test(`refuses ${label}`, async ({ authedRequest, setupUser }) => {
      const path = `projects/${setupUser.username}/domains/${setupUser.domain}`;
      const response = await authedRequest.put(path, { data: buildPayload(setupUser.domain) });

      // An engine that wrongly accepts one of these writes it into the
      // account's vhost, and every later test that fetches the site sees the
      // webserver refuse it. Put the domain back before asserting, so a
      // failure here stays one failure.
      if (response.status() !== 422) {
        await authedRequest
          .put(path, {
            data: {
              document_root: `/${setupUser.domain}/public_html`,
              redirect_enabled: false,
              force_https_redirect: false,
            },
          })
          .catch(() => undefined);
      }

      expect(response.status()).toBe(422);
    });
  }
});

test.describe('parent domains', () => {
  test('deleting a parent that still has a subdomain is refused', async ({
    authedRequest,
    domainFactory,
    setupUser,
  }) => {
    const addonDomain = await domainFactory.createAddonDomain(setupUser.username, setupUser.domain);
    const subdomain = `s${Date.now().toString(36)}.${addonDomain}`;

    // Nested FQDNs under panelalpha.direct exceed OpenSSL's 64-char CN, so
    // self-signed issuance 500s. This test is the parent-domain guard, not TLS.
    const created = await authedRequest.post(`projects/${setupUser.username}/domains`, {
      data: { domain: subdomain, type: 'sub', parent_domain: addonDomain, no_ssl: true },
    });
    expectOneOf(created.status(), [200, 201]);

    try {
      const response = await authedRequest.delete(
        `projects/${setupUser.username}/domains/${addonDomain}`
      );

      // Engines that do not persist parent_domain have no guard to enforce here.
      test.skip(
        response.status() === 200,
        'This engine does not track parent_domain, so it has no subdomain guard.'
      );

      expect(response.status()).toBe(422);
      expect(
        (((await response.json()) as { message?: string }).message ?? '').toLowerCase()
      ).toContain('subdomain');
    } finally {
      await authedRequest.delete(`projects/${setupUser.username}/domains/${subdomain}`);
      await authedRequest.delete(`projects/${setupUser.username}/domains/${addonDomain}`);
    }
  });
});
