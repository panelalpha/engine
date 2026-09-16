import { expect, test } from '@/fixtures/test-options';
import { isDindUser } from '@/helpers/sss-feature-helpers';
import { skipUnless } from '@/helpers/test-helpers';

test.describe('containers', () => {
  test('a non-DinD user is refused container management', async ({ api, setupUser }) => {
    const response = await api.listContainersRaw(setupUser.username);
    expect([403, 404]).toContain(response.status);
  });

  test('a DinD user can list containers and read deploy state', async ({ api, userFactory }) => {
    const user = await userFactory.createDindUser();
    skipUnless(user, 'DinD is not available on this engine.');

    test.skip(
      !(await isDindUser(api, user.username)),
      'Container management is only available for DinD users on this engine.'
    );

    const listed = await api.listContainers(user.username);
    expect(Array.isArray(listed.data)).toBe(true);

    const deploy = await api.getDeployLog(user.username);
    expect(typeof deploy.data.status).toBe('string');
    expect(typeof deploy.data.next_offset).toBe('number');

    const cancel = await api.cancelDeployRaw(user.username);
    expect([200, 409]).toContain(cancel.status);

    const invalidService = await api.get(`projects/${user.username}/containers/bad!name/logs`);
    expect(invalidService.status()).toBe(422);
  });
});
