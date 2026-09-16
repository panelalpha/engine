import { expect, test } from '@/fixtures/test-options';
import { assertWordPressCoreChecksums } from '@/helpers/wp-cli-helpers';
import { isMysqlcheckMissing, wpCliUnavailableReason, wpPath } from '@/helpers/wpcli-helpers';

/**
 * WP-CLI runs inside the user's container, so these tests double as a check that
 * the container has a working PHP, database connection and WordPress install.
 */
test.describe('WP-CLI commands', () => {
  test.beforeEach(async ({ api, setupUser }) => {
    const reason = await wpCliUnavailableReason(api, setupUser);
    test.skip(Boolean(reason), reason ?? '');
  });

  test(
    'wp core version reports a version number',
    { tag: ['@smoke'] },
    async ({ api, setupUser }) => {
      const result = await api.executeWpCliCommand(setupUser.username, [
        'core',
        'version',
        wpPath(setupUser),
      ]);

      expect(result.exit_code, result.stderr).toBe(0);
      expect(result.stdout.trim()).toMatch(/^\d+\.\d+(\.\d+)?$/);
    }
  );

  /** Commands that must simply run cleanly. */
  const successfulCommands = [
    ['wp plugin list', ['plugin', 'list']],
    ['wp theme list', ['theme', 'list']],
    ['wp user list', ['user', 'list']],
    ['wp cache flush', ['cache', 'flush']],
    ['wp rewrite flush', ['rewrite', 'flush']],
    ['wp transient delete --expired', ['transient', 'delete', '--expired']],
    ['wp transient delete --all', ['transient', 'delete', '--all']],
    ['wp search-replace --dry-run', ['search-replace', 'http://', 'https://', '--dry-run']],
  ] as const;

  for (const [label, args] of successfulCommands) {
    test(`${label} exits cleanly`, async ({ api, setupUser }) => {
      const result = await api.executeWpCliCommand(setupUser.username, [
        ...args,
        wpPath(setupUser),
      ]);
      expect(result.exit_code, result.stderr).toBe(0);
    });
  }

  test('an unknown command exits non-zero', async ({ api, setupUser }) => {
    const result = await api.executeWpCliCommand(setupUser.username, [
      'invalid-command-that-does-not-exist',
      wpPath(setupUser),
    ]);

    expect(result.exit_code, 'WP-CLI accepted a command that does not exist').not.toBe(0);
  });

  test('wp option get siteurl points at the user domain', async ({ api, setupUser }) => {
    const result = await api.executeWpCliCommand(setupUser.username, [
      'option',
      'get',
      'siteurl',
      wpPath(setupUser),
    ]);

    expect(result.exit_code, result.stderr).toBe(0);
    expect(result.stdout).toContain(setupUser.domain);
  });
});

test.describe('WordPress installation', () => {
  test.beforeEach(async ({ api, setupUser }) => {
    const reason = await wpCliUnavailableReason(api, setupUser);
    test.skip(Boolean(reason), reason ?? '');
  });

  test('core reports itself installed', { tag: ['@smoke'] }, async ({ api, setupUser }) => {
    const result = await api.executeWpCliCommand(setupUser.username, [
      'core',
      'is-installed',
      wpPath(setupUser),
    ]);
    expect(result.exit_code, result.stderr).toBe(0);
  });

  /** A checksum mismatch means the core files were tampered with or truncated. */
  test('core files match their published checksums', async ({ api, setupUser }) => {
    assertWordPressCoreChecksums(
      await api.executeWpCliCommand(setupUser.username, [
        'core',
        'verify-checksums',
        wpPath(setupUser),
      ])
    );
  });

  const siteOptions = [
    ['siteurl', (domain: string) => expect.stringContaining(domain)],
    ['home', (domain: string) => expect.stringContaining(domain)],
  ] as const;

  for (const [option, matcher] of siteOptions) {
    test(`the ${option} option carries the user domain`, async ({ api, setupUser }) => {
      const result = await api.executeWpCliCommand(setupUser.username, [
        'option',
        'get',
        option,
        wpPath(setupUser),
      ]);

      expect(result.exit_code, result.stderr).toBe(0);
      expect(result.stdout.trim()).toEqual(matcher(setupUser.domain));
    });
  }

  test('the site has a non-empty name', async ({ api, setupUser }) => {
    const result = await api.executeWpCliCommand(setupUser.username, [
      'option',
      'get',
      'blogname',
      wpPath(setupUser),
    ]);

    expect(result.exit_code, result.stderr).toBe(0);
    expect(result.stdout.trim()).toBeTruthy();
  });
});

test.describe('WP-CLI database commands', () => {
  test.beforeEach(async ({ api, setupUser }) => {
    const reason = await wpCliUnavailableReason(api, setupUser);
    test.skip(Boolean(reason), reason ?? '');
  });

  test('wp db check succeeds', async ({ api, setupUser }) => {
    const result = await api.executeWpCliCommand(setupUser.username, [
      'db',
      'check',
      wpPath(setupUser),
    ]);

    test.skip(isMysqlcheckMissing(result), 'This image ships no mysqlcheck binary.');
    expect(result.exit_code, result.stderr).toBe(0);
  });

  test('wp db optimize succeeds', async ({ api, setupUser }) => {
    const result = await api.executeWpCliCommand(setupUser.username, [
      'db',
      'optimize',
      wpPath(setupUser),
    ]);

    test.skip(isMysqlcheckMissing(result), 'This image ships no mysqlcheck binary.');
    expect(result.exit_code, result.stderr).toBe(0);
  });

  test('wp db size returns parseable JSON', async ({ api, setupUser }) => {
    const result = await api.executeWpCliCommand(setupUser.username, [
      'db',
      'size',
      '--format=json',
      wpPath(setupUser),
    ]);

    expect(result.exit_code, result.stderr).toBe(0);
    expect(JSON.parse(result.stdout)).toBeDefined();
  });

  test('revisions can be counted', async ({ api, setupUser }) => {
    const result = await api.executeWpCliCommand(setupUser.username, [
      'post',
      'list',
      '--post_type=revision',
      '--format=count',
      wpPath(setupUser),
    ]);

    expect(result.exit_code, result.stderr).toBe(0);
    expect(Number.parseInt(result.stdout.trim(), 10)).toBeGreaterThanOrEqual(0);
  });
});
