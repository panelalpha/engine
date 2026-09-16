import { expect, test } from '@/fixtures/test-options';
import { customIniSettingsResponseSchema } from '@/schemas';
import { validateParsedApiResponse } from '@/helpers/validate-parsed-response';
import { delay } from '@/helpers/retry';
import { getWebserverInfo, httpScheme } from '@/helpers/webserver-helpers';

test.describe('custom php.ini settings', () => {
  test('a settings profile round-trips through the API', async ({ api, setupUser }) => {
    const [version] = (await api.getAvailablePhpVersions()).data ?? [];
    expect(version, 'the engine reports no PHP versions').toBeTruthy();

    const profiles = [
      { memory_limit: '128M', max_execution_time: '60', upload_max_filesize: '50M' },
      { memory_limit: '256M', max_execution_time: '120', upload_max_filesize: '100M' },
    ];

    for (const profile of profiles) {
      await api.setCustomIniSettings(setupUser.username, version, profile);

      const response = await api.getCustomIniSettings(setupUser.username, version);
      validateParsedApiResponse(response, customIniSettingsResponseSchema);
      expect(response.data).toMatchObject(profile);
    }
  });

  test('a predefined profile is accepted', async ({ api, authedRequest, setupUser }) => {
    const [version] = (await api.getAvailablePhpVersions()).data ?? [];

    const response = await authedRequest.put(
      `projects/${setupUser.username}/php/custom-ini-settings`,
      {
        data: {
          php_version: version,
          settings: { memory_limit: '1G', upload_max_filesize: '128M', post_max_size: '128M' },
        },
      }
    );

    expect(response.status()).toBe(204);
  });

  /**
   * The API storing a setting is not the same as PHP honouring it. FPM stacks
   * read the pool's ini, while lsphp reads a `.user.ini` next to the script, so
   * each is verified through the path it actually uses.
   */
  test('the settings reach the PHP runtime', async ({
    api,
    anonymousRequest,
    settings,
    setupUser,
  }) => {
    const [version] = (await api.getAvailablePhpVersions()).data ?? [];
    const custom = { memory_limit: '321M', upload_max_filesize: '64M' };

    await api.setCustomIniSettings(setupUser.username, version, custom);
    await delay(settings.timing.phpExecutionDelay);

    const stored = await api.getCustomIniSettings(setupUser.username, version);
    validateParsedApiResponse(stored, customIniSettingsResponseSchema);
    expect(stored.data).toMatchObject(custom);

    const { slug } = await getWebserverInfo(api);

    if (slug.includes('litespeed')) {
      const scriptPath = `${setupUser.domain}/public_html/pa-ini-check.php`;
      await api.putFileContents(
        setupUser.username,
        scriptPath,
        "<?php echo ini_get('memory_limit') . '|' . ini_get('upload_max_filesize');"
      );

      try {
        await delay(settings.timing.phpExecutionDelay);
        const response = await anonymousRequest.get(
          `${httpScheme(settings.apiBaseUrl)}://${setupUser.domain}/pa-ini-check.php`,
          { ignoreHTTPSErrors: true }
        );

        const [memoryLimit, uploadLimit] = (await response.text()).split('|');
        expect(memoryLimit?.trim()).toBe(custom.memory_limit);
        expect(uploadLimit?.trim()).toBe(custom.upload_max_filesize);
      } finally {
        await api.removeFile(setupUser.username, scriptPath);
      }
      return;
    }

    const result = await api.executeWpCliCommand(setupUser.username, [
      'eval',
      'echo shell_exec(\'php -r \\\'echo "MEM=" . ini_get("memory_limit") . "\\n" . "UPLOAD=" . ini_get("upload_max_filesize");\\\' \');',
      `--path=${setupUser.wpPath}`,
    ]);

    expect(result.exit_code).toBe(0);
    const lines = result.stdout
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter(Boolean);
    expect(lines).toContain(`MEM=${custom.memory_limit}`);
    expect(lines).toContain(`UPLOAD=${custom.upload_max_filesize}`);
  });
});

test.describe('php-version endpoint validation', () => {
  const invalidPayloads = [
    ['an unknown version string', { version: 'invalid-version' }],
    ['no version at all', {}],
  ] as const;

  for (const [label, payload] of invalidPayloads) {
    test(`rejects ${label}`, async ({ authedRequest, setupUser }) => {
      const response = await authedRequest.put(`domains/${setupUser.domain}/php-version`, {
        data: payload,
      });
      expect(response.status()).toBe(422);
    });
  }

  test('rejects a version change for an unknown domain', async ({ api, authedRequest }) => {
    const [version] = (await api.getAvailablePhpVersions()).data ?? [];
    expect(version).toBeTruthy();

    const response = await authedRequest.put('domains/nonexistent.domain.xyz/php-version', {
      data: { version },
    });
    expect(response.status()).toBe(404);
  });
});
