import { expect, test } from '@/fixtures/test-options';
import { expectOneOf } from '@/helpers/expect-one-of';
import { rand, randomUsername } from '@/helpers/random';

/**
 * Domain names are case-insensitive, so the engine has two acceptable answers to
 * a name containing capitals: store it lowercased, or refuse it. What it must
 * never do is store it as given — that would let `Example.com` and `example.com`
 * coexist as two different domains.
 */

const ACCEPTED_OR_REFUSED = [201, 422] as const;
const USER_ACCEPTED_OR_REFUSED = [201, 202, 422] as const;

test.describe('domain name normalisation', () => {
  const addonCases = [
    ['fully uppercase', (base: string) => `UPPERCASE${rand().toUpperCase()}.${base.toUpperCase()}`],
    ['mixed case', (base: string) => `TestAddon${rand()}.${base}`],
  ] as const;

  for (const [label, buildDomain] of addonCases) {
    test(`a ${label} addon domain is lowercased or refused`, async ({
      api,
      authedRequest,
      setupUser,
      settings,
    }) => {
      const requested = buildDomain(settings.requireDomain());
      const expected = requested.toLowerCase();

      const response = await authedRequest.post(`projects/${setupUser.username}/domains`, {
        data: { domain: requested, type: 'addon' },
      });
      expectOneOf(response.status(), ACCEPTED_OR_REFUSED);

      const { data: domains } = await api.listUserDomains(setupUser.username);

      if (response.status() !== 201) {
        expect(
          domains.map((domain) => domain.domain.toLowerCase()),
          'a refused domain must not have been stored anyway'
        ).not.toContain(expected);
        return;
      }

      const body = (await response.json()) as { data?: { domain?: string }; domain?: string };
      const created = body.data?.domain ?? body.domain ?? '';

      try {
        expect(created).toBe(expected);
        expect(domains.map((domain) => domain.domain)).toContain(expected);
      } finally {
        await api.deleteDomain(setupUser.username, created);
      }
    });
  }

  const userCases = [
    ['an uppercase TLD', (base: string) => `validuser${rand()}.${base.toUpperCase()}`],
    ['an uppercase subdomain', (base: string) => `TestUser${rand()}.${base}`],
  ] as const;

  for (const [label, buildDomain] of userCases) {
    test(`creating a user with ${label} lowercases it or is refused`, async ({ api, settings }) => {
      const requested = buildDomain(settings.requireDomain());
      const username = randomUsername();

      const response = await api.createUserRaw({ username, domain: requested });
      expectOneOf(response.status, USER_ACCEPTED_OR_REFUSED);

      if (![201, 202].includes(response.status)) {
        return;
      }

      try {
        const domain =
          response.status === 201
            ? (response.body as { data: { domain: string } }).data.domain
            : (await api.getUser(username)).data.domain;
        expect(domain).toBe(requested.toLowerCase());
      } finally {
        await api.deleteUserSafe(username);
      }
    });
  }
});
