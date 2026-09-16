import { expect, test } from '@/fixtures/test-options';
import type { EngineApi } from '@/clients/engine-api';
import type { UserUsage } from '@/types/system.types';
import { rand, randomDomain } from '@/helpers/random';
import { waitForCondition } from '@/helpers/retry';

/**
 * Every countable resource follows the same contract: creating one raises the
 * matching usage counter, and the counter is readable through `GET /projects/<u>/usage`.
 */
interface CountableResource {
  /** The key in the usage payload this resource is counted under. */
  counter: keyof UserUsage;
  /** Creates one instance and returns the identifier needed to delete it. */
  create: (
    api: EngineApi,
    user: { username: string; domain: string },
    baseDomain: string
  ) => Promise<string>;
  remove: (api: EngineApi, username: string, id: string) => Promise<unknown>;
}

const RESOURCES: Record<string, CountableResource> = {
  'an addon domain': {
    counter: 'addon_domains',
    create: async (api, user, baseDomain) => {
      const domain = randomDomain(baseDomain);
      await api.createDomain(user.username, { domain, type: 'addon' });
      return domain;
    },
    remove: (api, username, domain) => api.deleteDomain(username, domain),
  },
  'an FTP account': {
    counter: 'ftp_accounts',
    create: async (api, user) => {
      const name = rand('ftp');
      await api.createFtpAccount(user.username, {
        user: name,
        domain: user.domain,
        password: 'TestFtp123!',
      });
      return `${name}@${user.domain}`;
    },
    remove: (api, username, account) => api.deleteFtpAccount(username, account),
  },
  'an SFTP account': {
    counter: 'sftp_accounts',
    create: async (api, user) => {
      const username = `${user.username}_${rand('sftp')}`;
      await api.createSftpAccount(user.username, {
        username,
        auth_method: 'password',
        password: 'TestSftp123!',
      });
      return username;
    },
    remove: (api, username, account) => api.deleteSftpAccount(username, account),
  },
  'a MySQL database': {
    counter: 'mysql_databases',
    create: async (api, user) => {
      const requested = rand('testdb');
      const { data } = await api.createMySqlDatabase(user.username, requested);
      // The engine prefixes the name it actually created; delete needs that one.
      return data.database ?? requested;
    },
    remove: (api, username, database) => api.deleteMySqlDatabase(username, database),
  },
};

test.describe('usage counters', () => {
  for (const [label, resource] of Object.entries(RESOURCES)) {
    test(`creating ${label} raises ${resource.counter}.usage`, async ({
      api,
      settings,
      userFactory,
    }) => {
      const user = await userFactory.createSimpleUser();

      const before = (await api.getUserUsage(user.username))[resource.counter].usage;
      const id = await resource.create(api, user, settings.requireDomain());

      try {
        await waitForCondition(
          async () => (await api.getUserUsage(user.username))[resource.counter].usage > before,
          {
            timeout: 30_000,
            interval: 2_000,
            message: `${resource.counter}.usage stayed at ${before} after creating ${label}`,
          }
        );
      } finally {
        await resource.remove(api, user.username, id);
      }
    });
  }

  test('storage usage is a non-negative number before and after a write', async ({
    api,
    userFactory,
  }) => {
    const user = await userFactory.createSimpleUser();
    const path = `/${user.domain}/public_html/usage_test_${rand()}.bin`;

    expect((await api.getUserUsage(user.username)).storage.usage).toBeGreaterThanOrEqual(0);

    await api.putFileContents(user.username, path, 'X'.repeat(1024 * 1024));

    try {
      expect((await api.getUserUsage(user.username)).storage.usage).toBeGreaterThanOrEqual(0);
    } finally {
      await api.removeFile(user.username, path);
    }
  });
});
