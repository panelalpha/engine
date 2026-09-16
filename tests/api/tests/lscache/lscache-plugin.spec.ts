import { expect, test } from '@/fixtures/test-options';
import {
  LSCACHE_PLUGIN,
  ensureLscachePluginActive,
  isLscachePluginActive,
  lscacheUnavailableReason,
} from '@/helpers/lscache-helpers';
import { wpPath } from '@/helpers/wpcli-helpers';

test.describe('LiteSpeed Cache plugin', () => {
  let wasActive: boolean;

  test.beforeEach(async ({ api, setupUser }) => {
    const reason = await lscacheUnavailableReason(api);
    test.skip(Boolean(reason), reason ?? '');

    wasActive = await isLscachePluginActive(api, setupUser.username, wpPath(setupUser));
  });

  test.afterEach(async ({ api, setupUser }) => {
    const path = wpPath(setupUser);
    const action = wasActive ? 'activate' : 'deactivate';
    await api
      .executeWpCliCommand(setupUser.username, ['plugin', action, LSCACHE_PLUGIN, path])
      .catch(() => undefined);
  });

  test('the plugin installs and activates', async ({ api, setupUser }) => {
    const path = wpPath(setupUser);

    await ensureLscachePluginActive(api, setupUser.username, path);

    expect(await isLscachePluginActive(api, setupUser.username, path)).toBe(true);
  });

  test('the plugin deactivates', async ({ api, setupUser }) => {
    const path = wpPath(setupUser);

    await ensureLscachePluginActive(api, setupUser.username, path);
    await api.executeWpCliCommand(setupUser.username, [
      'plugin',
      'deactivate',
      LSCACHE_PLUGIN,
      path,
    ]);

    expect(await isLscachePluginActive(api, setupUser.username, path)).toBe(false);
  });
});
