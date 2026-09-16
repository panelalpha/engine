import { expect, test } from '@/fixtures/test-options';
import { isTestUsername, randomUsername, TEST_USERNAME_PREFIX } from '@/helpers/random';

test.describe('test usernames', () => {
  test('randomUsername stamps the suite prefix and hex body', () => {
    const username = randomUsername();
    expect(username.startsWith(TEST_USERNAME_PREFIX)).toBe(true);
    expect(username).toMatch(/^pw[0-9a-f]{8}$/);
    expect(isTestUsername(username)).toBe(true);
  });

  test('isTestUsername still matches a spec that suffixes the minted name', () => {
    expect(isTestUsername(`${randomUsername()}cc`)).toBe(true);
    expect(isTestUsername(`${randomUsername().slice(0, 10)}dupd`)).toBe(true);
  });

  test('does not treat a hand-created engine name as a test user', () => {
    expect(isTestUsername('shop')).toBe(false);
    expect(isTestUsername('nearthongs')).toBe(false);
    expect(isTestUsername('overcookedmidw')).toBe(false);
    expect(isTestUsername('pwilliams')).toBe(false);
    expect(isTestUsername('landbolt91k.panelalpha.online')).toBe(false);
  });
});
