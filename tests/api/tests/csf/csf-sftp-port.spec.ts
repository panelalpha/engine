import { expect, test } from '@/fixtures/test-options';
import { applyCsfToggle, readCsfState } from '@/helpers/csf-helpers';
import { rand } from '@/helpers/random';
import { delay } from '@/helpers/retry';
import { sftpLoginAndList } from '@/helpers/sftp-helpers';
import { requireEngineConnectHost } from '@/helpers/engine-host';

/**
 * SFTP does not run on port 22 here, so CSF has to be configured to let 2222
 * through. If it is not, every user loses SFTP the moment the firewall comes up
 * — which is exactly what this test would catch.
 */
test('SFTP on its non-standard port survives the firewall', async ({
  api,
  ftpFactory,
  settings,
  setupUser,
}) => {
  const state = await readCsfState(api);
  test.skip(!state, 'CSF is not installed on this engine.');

  if (!state?.enabled) {
    await applyCsfToggle(() => api.enableCsf());
  }

  const account = await ftpFactory.createSftpAccountWithPassword(
    setupUser.username,
    `${setupUser.username}_${rand('sftp')}`
  );

  try {
    await delay(settings.timing.propagationDelay);

    expect(
      (await api.listSftpAccounts(setupUser.username)).data.map((entry) => entry.username)
    ).toContain(account.username);

    const listing = await sftpLoginAndList({
      host: await requireEngineConnectHost(api),
      port: settings.ports.sftp,
      username: account.username,
      password: account.password,
    });

    expect(
      Array.isArray(listing),
      `SFTP on port ${settings.ports.sftp} did not answer while CSF was enabled`
    ).toBe(true);
  } finally {
    await ftpFactory.deleteSftpAccount(setupUser.username, account.username);
  }
});
