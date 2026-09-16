import { expect, test } from '@/fixtures/test-options';
import { expectOneOf } from '@/helpers/expect-one-of';
import { rand } from '@/helpers/random';

/** Aliases are set through the main domain's `aliases` array, not as their own domain. */
function aliasUpdate(domain: string, aliases: string[]) {
  return {
    document_root: `/${domain}/public_html`,
    redirect_enabled: false,
    force_https_redirect: false,
    aliases,
  };
}

test.describe('domain aliases', () => {
  test('an alias shows up on its main domain', async ({ api, userFactory, settings }) => {
    const user = await userFactory.createSimpleUser();
    const alias = `${rand('alias')}.${settings.requireDomain()}`;

    const updated = await api.updateDomain(user.username, user.domain, {
      ...aliasUpdate(user.domain, [alias]),
    });
    expect(updated.data.domain).toBe(user.domain);

    const details = await api.getDomain(user.username, user.domain);
    expect(details.data.details?.aliases ?? []).toContain(alias);

    // Engines differ on whether an alias also gets its own listing entry.
    const { data: domains } = await api.listUserDomains(user.username);
    const aliasEntry = domains.find((domain) => domain.domain === alias);
    const listedOnMain = domains
      .find((domain) => domain.domain === user.domain)
      ?.details?.aliases?.includes(alias);

    expect(
      aliasEntry ?? listedOnMain,
      `alias ${alias} appears neither as its own entry nor on the main domain`
    ).toBeTruthy();
    if (aliasEntry) {
      expect(aliasEntry.type).toBe('alias');
    }
  });

  test('the same alias twice is refused or collapsed to one', async ({
    api,
    authedRequest,
    userFactory,
    settings,
  }) => {
    const user = await userFactory.createSimpleUser();
    const alias = `${rand('alias')}.${settings.requireDomain()}`;

    await api.updateDomain(user.username, user.domain, aliasUpdate(user.domain, [alias]));

    const response = await authedRequest.put(`projects/${user.username}/domains/${user.domain}`, {
      data: aliasUpdate(user.domain, [alias, alias]),
    });

    if (response.status() !== 200) {
      expectOneOf(response.status(), [400, 409, 422]);
      return;
    }

    const aliases = (await api.getDomain(user.username, user.domain)).data.details?.aliases ?? [];
    expect(aliases.filter((entry) => entry === alias)).toHaveLength(1);
  });
});
