import { expect, test } from '@/fixtures/test-options';
import { expectOneOf } from '@/helpers/expect-one-of';

test.describe('domain tunnels', () => {
  test('the tunnel list starts empty', async ({ api, userFactory }) => {
    // Default create records a panelalpha.online tunnel. `none` is the name
    // that is not a tunnel, so the list is actually empty.
    const user = await userFactory.createSimpleUser({ tunnel: 'none' });
    const listed = await api.listTunnels(user.username, user.domain);
    expect(Array.isArray(listed.data)).toBe(true);
    expect(listed.data).toEqual([]);
  });

  test('an unknown project is 404', async ({ api }) => {
    expect((await api.listTunnelsRaw('nosuchuser999', 'missing.example.test')).status).toBe(404);
  });

  test('an unknown domain is 404', async ({ api, userFactory }) => {
    const user = await userFactory.createSimpleUser();
    expect((await api.listTunnelsRaw(user.username, 'missing.example.test')).status).toBe(404);
  });

  test('a hostname that is not a public tunnel name is refused', async ({ api, userFactory }) => {
    const user = await userFactory.createSimpleUser();
    const response = await api.createTunnelRaw(user.username, user.domain, {
      hostname: 'not a hostname',
      provider: 'panelalpha',
    });
    expectOneOf(response.status, [400, 422]);
  });

  test('cloudflare without a project token is refused', async ({ api, userFactory }) => {
    const user = await userFactory.createSimpleUser();
    const response = await api.createTunnelRaw(user.username, user.domain, {
      hostname: `cf-${user.username}.example.test`,
      provider: 'cloudflare',
    });
    expectOneOf(response.status, [400, 422]);
  });

  test('deleting a missing tunnel is 404', async ({ api, userFactory }) => {
    const user = await userFactory.createSimpleUser();
    expect(
      (await api.deleteTunnelRaw(user.username, user.domain, 'missing.panelalpha.online')).status
    ).toBe(404);
  });
});
