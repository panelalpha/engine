import { expect, test } from '@/fixtures/test-options';
import {
  getHtaccessPollOptions,
  getHtaccessPropagationDelay,
  pollForHtaccessResult,
  skipIfHtaccessUnsupported,
} from '@/helpers/htaccess-helpers';
import { delay } from '@/helpers/retry';
import { httpScheme } from '@/helpers/webserver-helpers';

/**
 * A `.htaccess` dropped into the document root has to take effect without any
 * panel action — that is what site owners and most WordPress plugins rely on.
 *
 * nginx has no `.htaccess` at all, so these skip there.
 */
test.describe('.htaccess', () => {
  let webserver: Awaited<ReturnType<typeof skipIfHtaccessUnsupported>>;
  let htaccessPath: string;
  const createdFiles: string[] = [];

  test.beforeEach(async ({ api, anonymousRequest, settings, setupUser }) => {
    webserver = await skipIfHtaccessUnsupported(test, api, anonymousRequest, settings, setupUser);
    htaccessPath = `/${setupUser.domain}/public_html/.htaccess`;
    createdFiles.length = 0;
  });

  test.afterEach(async ({ api, setupUser }) => {
    for (const file of createdFiles) {
      await api.removeFile(setupUser.username, file).catch(() => undefined);
    }
    await api.removeFile(setupUser.username, htaccessPath).catch(() => undefined);
  });

  /** Writes `.htaccess`, then waits out the reload this webserver needs. */
  const applyHtaccess = async (
    api: Parameters<typeof skipIfHtaccessUnsupported>[1],
    username: string,
    contents: string,
    propagationDelay: number
  ) => {
    await api.putFileContents(username, htaccessPath, contents);
    await delay(getHtaccessPropagationDelay(webserver, propagationDelay));
  };

  test('a rewrite rule takes effect', async ({ api, anonymousRequest, settings, setupUser }) => {
    const contents = `htaccess-test-content-${Date.now()}`;
    const targetFile = 'target.txt';
    const targetPath = `/${setupUser.domain}/public_html/${targetFile}`;
    createdFiles.push(targetPath);

    await api.putFileContents(setupUser.username, targetPath, contents);
    await applyHtaccess(
      api,
      setupUser.username,
      `<IfModule mod_rewrite.c>\n  RewriteEngine On\n  RewriteRule ^htaccess-test$ ${targetFile} [L]\n</IfModule>\n`,
      settings.timing.propagationDelay
    );

    const url = `${httpScheme(settings.apiBaseUrl)}://${setupUser.domain}/htaccess-test`;
    const { timeoutMs, intervalMs } = getHtaccessPollOptions(webserver);

    const result = await pollForHtaccessResult(anonymousRequest, url, {
      expectStatus: 200,
      expectBodyContent: contents,
      timeoutMs,
      intervalMs,
    });

    expect(result.success, `${url} never served the rewritten target on ${webserver}`).toBe(true);
  });

  test('a rewrite rule can be changed and the change takes effect', async ({
    api,
    anonymousRequest,
    settings,
    setupUser,
  }) => {
    const first = `htaccess-mod-1-${Date.now()}`;
    const second = `htaccess-mod-2-${Date.now()}`;
    const base = `/${setupUser.domain}/public_html`;

    createdFiles.push(`${base}/target1.txt`, `${base}/target2.txt`);
    await api.putFileContents(setupUser.username, `${base}/target1.txt`, first);
    await api.putFileContents(setupUser.username, `${base}/target2.txt`, second);

    const url = `${httpScheme(settings.apiBaseUrl)}://${setupUser.domain}/htaccess-mod`;
    const { timeoutMs, intervalMs } = getHtaccessPollOptions(webserver);

    const routeTo = async (file: string, expected: string) => {
      await applyHtaccess(
        api,
        setupUser.username,
        `<IfModule mod_rewrite.c>\n  RewriteEngine On\n  RewriteRule ^htaccess-mod$ ${file} [L]\n</IfModule>\n`,
        settings.timing.propagationDelay
      );

      const result = await pollForHtaccessResult(anonymousRequest, url, {
        expectStatus: 200,
        expectBodyContent: expected,
        timeoutMs,
        intervalMs,
      });
      expect(result.success, `${url} did not route to ${file} on ${webserver}`).toBe(true);
    };

    await routeTo('target1.txt', first);
    await routeTo('target2.txt', second);
  });

  test('a custom response header is applied', async ({
    api,
    anonymousRequest,
    settings,
    setupUser,
  }) => {
    const testFile = 'headers-test.txt';
    const contents = `headers-test-content-${Date.now()}`;
    const headerValue = `TestCustomHeader-${Date.now()}`;
    const targetPath = `/${setupUser.domain}/public_html/${testFile}`;
    createdFiles.push(targetPath);

    await api.putFileContents(setupUser.username, targetPath, contents);
    await applyHtaccess(
      api,
      setupUser.username,
      `<IfModule mod_headers.c>\n  <Files "${testFile}">\n    Header set X-Test-Custom "${headerValue}"\n  </Files>\n</IfModule>\n`,
      settings.timing.propagationDelay
    );

    const url = `${httpScheme(settings.apiBaseUrl)}://${setupUser.domain}/${testFile}`;
    const response = await anonymousRequest.get(url, { ignoreHTTPSErrors: true });

    expect(response.status()).toBe(200);
    expect(
      response.headers()['x-test-custom'],
      `${webserver} did not apply the Header directive`
    ).toBe(headerValue);
  });

  test('a 301 redirect is honoured', async ({ api, anonymousRequest, settings, setupUser }) => {
    const targetFile = 'redirect-target.txt';
    const contents = `redirect-test-content-${Date.now()}`;
    const targetPath = `/${setupUser.domain}/public_html/${targetFile}`;
    createdFiles.push(targetPath);

    await api.putFileContents(setupUser.username, targetPath, contents);
    await applyHtaccess(
      api,
      setupUser.username,
      `<IfModule mod_rewrite.c>\n  RewriteEngine On\n  RewriteRule ^old-path$ /${targetFile} [R=301,L]\n</IfModule>\n`,
      settings.timing.propagationDelay
    );

    const url = `${httpScheme(settings.apiBaseUrl)}://${setupUser.domain}/old-path`;

    const redirect = await anonymousRequest.get(url, {
      ignoreHTTPSErrors: true,
      maxRedirects: 0,
    });
    expect(redirect.status(), `${url} did not redirect on ${webserver}`).toBe(301);
    expect(redirect.headers().location).toContain(targetFile);

    const followed = await anonymousRequest.get(url, { ignoreHTTPSErrors: true, maxRedirects: 5 });
    expect(followed.status()).toBe(200);
    expect(await followed.text()).toContain(contents);
  });
});
