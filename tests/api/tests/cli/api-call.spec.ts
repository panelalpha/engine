import { expect, test } from '@/fixtures/test-options';
import { randomUsername } from '@/helpers/random';

test.describe('pae-artisan api:call', () => {
  test.beforeEach(({ hostExec }) => {
    test.skip(!hostExec, 'pae-artisan is not reachable from this runner.');
  });

  test('GET /projects --raw matches the REST listing', async ({ hostExec, api }) => {
    const listed = await hostExec!.pae(['api:call', 'GET', '/projects', '--raw']);
    expect(listed.exitCode).toBe(0);
    const parsed = JSON.parse(listed.stdout) as { data?: { username?: string }[] };
    const fromCli = (parsed.data ?? []).map((row) => row.username).sort();
    const fromRest = (await api.listUsers()).data.map((row) => row.username).sort();
    expect(fromCli).toEqual(fromRest);
  });

  test('an unknown route is a non-zero --raw 404', async ({ hostExec }) => {
    const result = await hostExec!.pae(['api:call', 'GET', '/no-such-route', '--raw']);
    expect(result.exitCode).toBe(1);
    expect(result.stdout).toMatch(/Not Found|404/i);
  });

  test('an invalid method is non-zero', async ({ hostExec }) => {
    const result = await hostExec!.pae(['api:call', 'NOTAMETHOD', '/projects', '--raw']);
    expect(result.exitCode).not.toBe(0);
  });

  test('pretty mode prints a Response banner', async ({ hostExec }) => {
    const result = await hostExec!.pae(['api:call', 'GET', '/test-connection']);
    expect(result.exitCode).toBe(0);
    expect(result.stdout).toMatch(/Response:/);
  });

  test('POST and DELETE /projects through api:call round-trip', async ({
    hostExec,
    settings,
    api,
  }) => {
    const username = randomUsername(12);
    const domain = `${username}.${settings.requireDomain()}`;
    const body = JSON.stringify({ username, domain });
    try {
      const created = await hostExec!.pae(['api:call', 'POST', '/projects', body, '--raw']);
      expect(created.exitCode).toBe(0);
      expect((await api.getUserRaw(username)).status).toBe(200);
    } finally {
      await hostExec!.pae(['api:call', 'DELETE', `/projects/${username}`, '--raw']);
      await api.deleteUserSafe(username);
    }
  });
});
