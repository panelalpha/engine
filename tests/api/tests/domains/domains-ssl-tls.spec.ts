import { expect, test } from '@/fixtures/test-options';
import { waitForCondition } from '@/helpers/retry';
import {
  certificateText,
  getPeerCertificate,
  installedTestSslCertificatePresented,
} from '@/helpers/tls-helpers';
import { TEST_SSL_CERT, TEST_SSL_KEY } from '@/test-data/static/test-ssl-cert';

/**
 * Installing a certificate through the API is only half the job — the webserver
 * has to actually present it on the TLS handshake. This connects to port 443 and
 * reads back what the server offers.
 *
 * `tunnel: none` so :443 is the engine. A panelalpha.online name terminates TLS
 * at the WithoutDNS proxy, which never presents the fixture we just installed.
 */
test.describe('SSL over the wire', () => {
  test('HTTPS presents the certificate that was installed', async ({ api, userFactory }) => {
    test.skip(
      !TEST_SSL_CERT.includes('BEGIN CERTIFICATE'),
      'No usable test certificate fixture available.'
    );

    const user = await userFactory.createSimpleUser({ tunnel: 'none' });

    await api.installSslCertificate(
      user.username,
      user.domain,
      TEST_SSL_CERT,
      TEST_SSL_KEY,
      TEST_SSL_CERT
    );

    await waitForCondition(
      async () =>
        installedTestSslCertificatePresented(await getPeerCertificate(user.domain, user.domain)),
      {
        timeout: 30_000,
        interval: 2_000,
        message: `${user.domain} never presented the installed test certificate over HTTPS`,
      }
    );

    const presented = await getPeerCertificate(user.domain, user.domain);
    expect(certificateText(presented).toLowerCase()).toContain('example.test');
  });
});
