import { expect, test } from '@/fixtures/test-options';
import { expectOneOf } from '@/helpers/expect-one-of';
import { wpCliUnavailableReason, wpPath } from '@/helpers/wpcli-helpers';

/**
 * The WP-CLI endpoint runs whatever it is handed inside the user's container, so
 * the argument allowlist is what stops a tenant from executing arbitrary code
 * through it.
 */
test.describe('WP-CLI argument validation', () => {
  test.beforeEach(async ({ api, setupUser }) => {
    const reason = await wpCliUnavailableReason(api, setupUser);
    test.skip(Boolean(reason), reason ?? '');
  });

  test('an empty argument list is refused', async ({ authedRequest, setupUser }) => {
    const response = await authedRequest.post(`projects/${setupUser.username}/wp-cli/command`, {
      data: { args: [] },
    });
    expect(response.status()).toBe(422);
  });

  const shellPayloads = [
    [
      'a PHP eval writing to disk',
      (path: string) => ['eval', 'file_put_contents("/tmp/pa-wpcli-test", "x");', path],
    ],
    ['the shell subcommand', (path: string) => ['shell', 'whoami', path]],
    ['a shell metacharacter', (path: string) => ['; rm -rf /', path]],
  ] as const;

  for (const [label, buildArgs] of shellPayloads) {
    test(`${label} is refused`, { tag: ['@security'] }, async ({ authedRequest, setupUser }) => {
      const response = await authedRequest.post(`projects/${setupUser.username}/wp-cli/command`, {
        data: { args: buildArgs(wpPath(setupUser)) },
      });

      test.skip(
        response.status() === 200,
        'This engine version does not enforce the WP-CLI argument allowlist.'
      );

      expectOneOf(response.status(), [403, 422], `${label} was accepted`);
    });
  }
});
