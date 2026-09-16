import { expect, test } from '@/fixtures/test-options';
import {
  PERMALINK_STRUCTURES,
  applyPermalinkStructure,
  assertPermalinkStructureSet,
} from '@/helpers/permalinks-http-helpers';
import { wpCliUnavailableReason, wpPath } from '@/helpers/wpcli-helpers';

/**
 * Setting each permalink structure and reading it back.
 *
 * The structure is a site-wide WordPress option on the shared setup user, so
 * every test restores whatever was configured when it started — otherwise the
 * HTTP permalink specs would run against whichever structure happened to be set
 * last.
 */
test.describe('permalink structures', () => {
  let original: string;

  test.beforeEach(async ({ api, setupUser }) => {
    const reason = await wpCliUnavailableReason(api, setupUser);
    test.skip(Boolean(reason), reason ?? '');

    original = (
      await api.executeWpCliCommand(setupUser.username, [
        'option',
        'get',
        'permalink_structure',
        wpPath(setupUser),
      ])
    ).stdout.trim();
  });

  test.afterEach(async ({ api, setupUser }) => {
    await applyPermalinkStructure(api, setupUser.username, original, wpPath(setupUser)).catch(
      () => undefined
    );
  });

  for (const [name, structure] of Object.entries(PERMALINK_STRUCTURES)) {
    test(`the ${name} structure can be applied`, async ({ api, setupUser }) => {
      await applyPermalinkStructure(api, setupUser.username, structure, wpPath(setupUser));
      await assertPermalinkStructureSet(api, setupUser.username, structure, wpPath(setupUser));
    });
  }

  test('rewrite rules are generated for a pretty structure', async ({ api, setupUser }) => {
    await applyPermalinkStructure(
      api,
      setupUser.username,
      PERMALINK_STRUCTURES.postName,
      wpPath(setupUser)
    );

    const listed = await api.executeWpCliCommand(setupUser.username, [
      'rewrite',
      'list',
      '--format=json',
      wpPath(setupUser),
    ]);

    expect(listed.exit_code, listed.stderr).toBe(0);

    const rules = JSON.parse(listed.stdout) as unknown[];
    expect(Array.isArray(rules)).toBe(true);
    expect(rules.length, 'a pretty permalink structure produced no rewrite rules').toBeGreaterThan(
      0
    );
  });
});
