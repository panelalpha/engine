import * as tls from 'tls';
import { TEST_SSL_CERT_CN } from '@/test-data/static/test-ssl-cert';

export function getPeerCertificate(host: string, servername: string): Promise<tls.PeerCertificate> {
  return new Promise((resolve, reject) => {
    const socket = tls.connect(
      { host, port: 443, servername, rejectUnauthorized: false, timeout: 15_000 },
      () => {
        const cert = socket.getPeerCertificate();
        socket.end();
        resolve(cert);
      }
    );
    socket.on('error', reject);
    socket.on('timeout', () => {
      socket.destroy();
      reject(new Error(`TLS handshake timed out for ${host}`));
    });
  });
}

export function certificateText(cert: tls.PeerCertificate): string {
  const parts = [cert.subject?.CN, cert.subjectaltname, JSON.stringify(cert.subject)].filter(
    Boolean
  );
  return parts.join(' ');
}

/** True when the peer cert matches the fixed {@link TEST_SSL_CERT} installed by API tests. */
export function installedTestSslCertificatePresented(cert: tls.PeerCertificate): boolean {
  return certificateText(cert).toLowerCase().includes(TEST_SSL_CERT_CN);
}
