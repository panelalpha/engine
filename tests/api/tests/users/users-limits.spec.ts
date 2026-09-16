import { expect, test } from '@/fixtures/test-options';
import { rand, randomDomain } from '@/helpers/random';
import { waitForCondition } from '@/helpers/retry';

test.describe('resource limits', () => {
  test('every limit field round-trips through the config', async ({ api, userFactory }) => {
    const user = await userFactory.createUser();
    const limits = {
      disk_space_limit: 1_073_741_824,
      memory_limit: 512,
      cpu_limit: 1.5,
      device_read_bps: 10_485_760,
      device_write_bps: 10_485_760,
      bandwidth_limit: 1_073_741_824,
      mysql_databases_limit: 10,
      ftp_accounts_limit: 5,
      sftp_accounts_limit: 3,
      addon_domains_limit: 2,
      subdomains_limit: 5,
      inodes_limit: 500_000,
    };

    await api.updateUser(user.username, limits);

    const { data } = await api.getUser(user.username);
    const config = data.config ?? data.details ?? {};
    expect(config).toMatchObject(limits);
  });
});

test.describe('rebuild', () => {
  test('keeps FTP, SFTP, MySQL and addon domains intact', async ({
    api,
    userFactory,
    settings,
  }) => {
    const user = await userFactory.createSimpleUser();
    const ftpUser = rand('ftp');
    const sftpUsername = `${user.username}_${rand('sftp')}`;
    const addonDomain = randomDomain(settings.requireDomain());

    await api.createFtpAccount(user.username, {
      user: ftpUser,
      domain: user.domain,
      password: 'FtpRebuild123!',
    });
    await api.createSftpAccount(user.username, {
      username: sftpUsername,
      auth_method: 'password',
      password: 'SftpRebuild123!',
    });
    const database = (await api.createMySqlDatabase(user.username, rand('db'))).data.database;

    // The container needs its webserver up before it will accept an addon domain.
    await waitForCondition(
      async () => {
        try {
          await api.createDomain(user.username, { domain: addonDomain, type: 'addon' });
          return true;
        } catch {
          return false;
        }
      },
      { timeout: settings.timing.propagationDelay + 60_000, interval: 3_000 }
    );

    await api.rebuildUser(user.username);

    const [ftpAccounts, sftpAccounts, databases, domains] = await Promise.all([
      api.listFtpAccounts(user.username),
      api.listSftpAccounts(user.username),
      api.listMySqlDatabases(user.username),
      api.listUserDomains(user.username),
    ]);

    expect(ftpAccounts.data.map((account) => account.user)).toContain(`${ftpUser}@${user.domain}`);
    expect(sftpAccounts.data.map((account) => account.username)).toContain(sftpUsername);
    expect(databases.data.map((db) => db.database)).toContain(database);
    expect(domains.data.map((domain) => domain.domain)).toContain(addonDomain);
  });
});
