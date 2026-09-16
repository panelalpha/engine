import { expect, test } from '@/fixtures/test-options';
import { getDomainBasePath, randomFileContent, randomFileName } from '@/helpers/file-path-helpers';
import { waitForCondition } from '@/helpers/retry';

/**
 * Deleting a user has to take its webserver configuration with it. A leftover
 * vhost that still points at a removed certificate breaks the webserver for
 * every other user on the host, so this is checked on its own.
 *
 * The vhost file itself is not readable from here: it lives in the webserver's
 * config directory, and the only file API the suite has is rooted inside an
 * account's home. So the configuration is observed through the behaviour it
 * produces - a marker file that the account's own vhost serves, and which has
 * to stop being served once the account is gone.
 */
test.describe('vhost cleanup on user deletion', () => {
  test('the account stops being served once it is deleted', async ({
    api,
    anonymousRequest,
    userFactory,
  }) => {
    const user = await userFactory.createSimpleUser();
    const fileName = randomFileName('txt');
    const marker = randomFileContent();
    const url = `https://${user.domain}/${fileName}`;

    await api.putFileContents(
      user.username,
      `${getDomainBasePath(user.domain)}/${fileName}`,
      marker
    );

    // While the account exists its vhost routes the domain at its own docroot.
    await waitForCondition(
      async () => {
        const response = await anonymousRequest.get(url, { ignoreHTTPSErrors: true });
        return response.status() === 200 && (await response.text()).trim() === marker;
      },
      { timeout: 60_000, interval: 2_000, message: `${url} never served the marker file` }
    );

    await userFactory.deleteUser(user.username);

    // Without a vhost nothing may serve that account's document root. The
    // request itself can still land somewhere - an unmatched Host falls
    // through to whichever vhost nginx loaded first - so the marker, not the
    // status code, is what proves the configuration is gone.
    const afterDelete = await anonymousRequest.get(url, { ignoreHTTPSErrors: true });
    expect(
      (await afterDelete.text()).trim(),
      `${url} still serves the deleted account's document root`
    ).not.toBe(marker);
  });

  test('deleting a user leaves the webserver serving everyone else', async ({
    anonymousRequest,
    userFactory,
    setupUser,
  }) => {
    const doomed = await userFactory.createSimpleUser();
    await userFactory.deleteUser(doomed.username);

    // The failure this guards against is a dangling vhost that references a
    // certificate the deletion removed: nginx then refuses to reload and every
    // other account goes down with it.
    const response = await anonymousRequest.get(`https://${setupUser.domain}/`, {
      ignoreHTTPSErrors: true,
      maxRedirects: 5,
    });
    expect(
      response.status(),
      `${setupUser.domain} stopped serving after an unrelated user was deleted`
    ).toBeLessThan(500);
  });
});
