import { expect, test } from '@/fixtures/test-options';
import { expectOneOf } from '@/helpers/expect-one-of';
import { randomUsername } from '@/helpers/random';

test.describe('main domain changes', () => {
  test('a user can be moved to a new main domain', async ({ api, userFactory, settings }) => {
    const user = await userFactory.createSimpleUser();
    const newDomain = `${randomUsername()}.${settings.requireDomain()}`;

    await api.updateUser(user.username, { domain: newDomain });

    expect((await api.getUser(user.username)).data.domain).toBe(newDomain);
  });

  test('a domain already in use is refused and the old one kept', async ({ api, userFactory }) => {
    const [mover, occupant] = await Promise.all([
      userFactory.createSimpleUser(),
      userFactory.createSimpleUser(),
    ]);

    const result = await api.updateUserRaw(mover.username, { domain: occupant.domain });

    expectOneOf(result.status, [400, 409, 422, 500]);
    expect(
      (await api.getUser(mover.username)).data.domain,
      'the rejected change must not have taken effect'
    ).toBe(mover.domain);
  });
});
