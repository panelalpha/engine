import { Readable } from 'node:stream';
import SftpClient from 'ssh2-sftp-client';
import { expect, test } from '@/fixtures/test-options';
import { withFtpClient } from '@/helpers/ftp-helpers';
import { rand } from '@/helpers/random';
import { delay } from '@/helpers/retry';
import { requireEngineConnectHost } from '@/helpers/engine-host';
import { getUserUidGid, parseUidGid } from '@/helpers/uid-helpers';

/**
 * FTP and SFTP run as their own daemons outside the user's container, so they
 * are the two routes most likely to write files under the wrong UID — which
 * leaves the site owner unable to touch what they just uploaded.
 */
test.describe('ownership of transferred files', () => {
  test(
    'a file uploaded over FTP belongs to the account',
    {
      tag: ['@security'],
    },
    async ({ api, ftpFactory, settings, setupUser }) => {
      const uid = await getUserUidGid(api, setupUser.username);
      const account = await ftpFactory.createFtpAccount(setupUser.username, setupUser.domain);
      const host = await requireEngineConnectHost(api);

      const fileName = `${rand('ftp-uid')}.txt`;
      const remotePath = `${setupUser.domain}/public_html/${fileName}`;

      try {
        await withFtpClient(async (client) => {
          await client.access({
            host,
            port: settings.ports.ftp,
            user: account.user,
            password: account.password,
            secure: false,
          });

          await client.uploadFrom(Readable.from(['FTP test content']), remotePath);
        });

        const ownership = parseUidGid(await api.getFileStat(setupUser.username, remotePath));
        expect(ownership.uid, `the FTP upload is owned by uid ${ownership.uid}`).toBe(uid.uid);
        expect(ownership.gid).toBe(uid.gid);
      } finally {
        await api.removeFile(setupUser.username, remotePath).catch(() => undefined);
        await ftpFactory.deleteFtpAccount(setupUser.username, account.user);
      }
    }
  );

  test(
    'a file uploaded over SFTP belongs to the account',
    {
      tag: ['@security'],
    },
    async ({ api, ftpFactory, settings, setupUser }) => {
      const uid = await getUserUidGid(api, setupUser.username);
      const account = await ftpFactory.createSftpAccountWithPassword(
        setupUser.username,
        `${setupUser.username}_${rand('sftp')}`
      );

      const fileName = `${rand('sftp-uid')}.txt`;
      const remotePath = `/${setupUser.domain}/public_html/${fileName}`;
      const client = new SftpClient();

      try {
        await delay(settings.timing.propagationDelay);
        await client.connect({
          host: await requireEngineConnectHost(api),
          port: settings.ports.sftp,
          username: account.username,
          password: account.password,
        });

        await client.put(Buffer.from('SFTP test content'), remotePath);

        const ownership = parseUidGid(
          await api.getFileStat(setupUser.username, `${setupUser.domain}/public_html/${fileName}`)
        );
        expect(ownership.uid, `the SFTP upload is owned by uid ${ownership.uid}`).toBe(uid.uid);
        expect(ownership.gid).toBe(uid.gid);
      } finally {
        await client.end().catch(() => undefined);
        await api
          .removeFile(setupUser.username, `${setupUser.domain}/public_html/${fileName}`)
          .catch(() => undefined);
        await ftpFactory.deleteSftpAccount(setupUser.username, account.username);
      }
    }
  );
});
