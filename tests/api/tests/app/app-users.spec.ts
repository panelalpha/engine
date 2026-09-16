import { expect, test } from '@/fixtures/test-options';
import { isDindUser } from '@/helpers/sss-feature-helpers';
import { randomEmail, randomPassword, randomUsername } from '@/helpers/random';
import { skipUnless } from '@/helpers/test-helpers';

test.describe('app users', () => {
  test('a non-DinD user is refused app endpoints', async ({ api, setupUser }) => {
    const info = await api.getAppInfoRaw(setupUser.username);
    const roles = await api.getAppRolesRaw(setupUser.username);
    const users = await api.listAppUsersRaw(setupUser.username);
    const health = await api.getAppHealthRaw(setupUser.username);

    expect([403, 404, 422]).toContain(info.status);
    expect([403, 404, 422]).toContain(roles.status);
    expect([403, 404, 422]).toContain(users.status);
    expect([403, 404, 422]).toContain(health.status);
  });

  test('a DinD user can read app info and roles when the app is present', async ({
    api,
    userFactory,
  }) => {
    const user = await userFactory.createDindUser();
    skipUnless(user, 'DinD is not available on this engine.');
    const username = user.username;

    test.skip(!(await isDindUser(api, username)), 'DinD is not available on this engine.');

    const info = await api.getAppInfoRaw(username);
    const roles = await api.getAppRolesRaw(username);
    const users = await api.listAppUsersRaw(username);

    // Without a deployed app these land on 422; with an app they are 200.
    expect([200, 422]).toContain(info.status);
    expect([200, 422]).toContain(roles.status);
    expect([200, 422]).toContain(users.status);

    if (roles.status === 200) {
      const roleList = (roles.body as { data: string[] }).data;
      expect(Array.isArray(roleList)).toBe(true);

      test.skip(roleList.length === 0, 'The app reports no roles to create users with.');

      const login = randomUsername(10);
      const createdUser = await api.createAppUserRaw(username, {
        login,
        email: randomEmail(),
        password: randomPassword(12),
        role: roleList[0],
      });

      if (createdUser.status === 201) {
        const userId = String((createdUser.body as { data: { id: string | number } }).data.id);
        try {
          await api.resetAppUserPassword(username, userId, randomPassword(12));
          const sso = await api.createAppUserSso(username, userId);
          expect(typeof sso.url).toBe('string');
          expect(sso.url.length).toBeGreaterThan(0);
        } finally {
          await api.deleteAppUser(username, userId).catch(() => undefined);
        }
      } else {
        expect([422, 500]).toContain(createdUser.status);
      }
    }
  });

  test('an invalid app install payload is rejected', async ({ api, setupUser }) => {
    const response = await api.installAppRaw(setupUser.username, {
      url: 'not-a-url',
      title: '',
      admin_user: '',
      admin_email: 'bad',
      admin_password: 'short',
    });
    expect([403, 404, 422]).toContain(response.status);
  });

  test('a valid install payload on DinD is accepted or the app has no installer', async ({
    api,
    userFactory,
  }) => {
    const user = await userFactory.createDindUser();
    skipUnless(user, 'DinD is not available on this engine.');

    const response = await api.installAppRaw(user.username, {
      url: `https://${user.domain}`,
      title: 'API test site',
      admin_user: 'admin',
      admin_email: 'admin@example.test',
      admin_password: 'SecurePass123',
    });
    expect([204, 422]).toContain(response.status);
  });
});
