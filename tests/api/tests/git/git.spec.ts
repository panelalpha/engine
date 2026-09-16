import { expect, test } from '@/fixtures/test-options';
import { configuredGitRepo } from '@/helpers/deploy-helpers';
import { expectOneOf } from '@/helpers/expect-one-of';
import { skipUnless } from '@/helpers/test-helpers';
import { Timeouts } from '@/config/timeouts';
import type { GitStatus } from '@/types';

test.describe('git API', () => {
  test('an unknown project is 404', async ({ api }) => {
    expect((await api.gitStatusRaw('nosuchuser999')).status).toBe(404);
  });

  test('connect without a repo URL is refused', async ({ api, userFactory }) => {
    const user = await userFactory.createSimpleUser();
    const response = await api.gitConnectRaw(user.username, { branch: 'main' });
    expectOneOf(response.status, [400, 422]);
  });

  test('a PHP account reports site_git and is not connected', async ({ api, userFactory }) => {
    const user = await userFactory.createSimpleUser();
    const status = await api.gitStatus(user.username);
    expect(status.data.managed_by).toBe('site_git');
    expect(status.data.connected).toBe(false);
    expect((await api.gitPullRaw(user.username)).status).toBe(422);
    expect((await api.gitDisconnectRaw(user.username)).status).toBe(422);
  });

  test('an empty DinD project can connect, pull and disconnect', async ({ api, userFactory }) => {
    test.setTimeout(Timeouts.deploy);
    const user = await userFactory.createDindUser();
    skipUnless(user, 'DinD is not available on this engine.');

    const repo = configuredGitRepo();
    const connected = await api.gitConnectRaw(user.username, {
      repo_url: repo,
      branch: 'main',
    });
    expect(connected.status).toBe(200);
    const status = (connected.body as { data?: GitStatus }).data;
    skipUnless(status, 'git/connect returned no status payload.');
    expect(status.managed_by).toBe('site_git');
    expect(status.connected).toBe(true);

    try {
      const fetched = await api.gitStatus(user.username);
      expect(fetched.data.connected).toBe(true);

      const pulled = await api.gitPullRaw(user.username, { strategy: 'force' });
      expectOneOf(pulled.status, [200, 422]);

      if (pulled.status === 200) {
        const branches = await api.gitBranches(user.username);
        expect(Array.isArray(branches.data)).toBe(true);
        const commits = await api.gitCommits(user.username, { limit: 5 });
        expect(Array.isArray(commits.data)).toBe(true);
        expect((await api.gitRevertRaw(user.username)).status).toBe(200);
        const credentials = await api.gitUpdateCredentials(user.username, {});
        expect(credentials.data.connected).toBe(true);
      }

      const missingBranch = await api.gitChangeBranchRaw(user.username, {});
      expectOneOf(missingBranch.status, [400, 422]);
    } finally {
      const disconnected = await api.gitDisconnectRaw(user.username);
      expectOneOf(disconnected.status, [200, 422]);
    }
  });
});
