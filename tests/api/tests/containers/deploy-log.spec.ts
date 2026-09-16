import { expect, test } from '@/fixtures/test-options';
import { isDindUser } from '@/helpers/sss-feature-helpers';
import { skipUnless } from '@/helpers/test-helpers';

test.describe('deploy log', () => {
  test('a non-DinD user cannot read the deploy log', async ({ api, setupUser }) => {
    const response = await api.getDeployLogRaw(setupUser.username);
    expect([403, 404]).toContain(response.status);
  });

  test('cancelling when nothing is running returns 409 for a DinD user', async ({
    api,
    userFactory,
  }) => {
    const user = await userFactory.createDindUser();
    skipUnless(user, 'DinD is not available on this engine.');

    test.skip(!(await isDindUser(api, user.username)), 'DinD is not available on this engine.');

    const deploy = await api.getDeployLog(user.username);
    expect(['none', 'running', 'success', 'failed', 'cancelled']).toContain(deploy.data.status);

    if (deploy.data.status !== 'running') {
      const cancel = await api.cancelDeployRaw(user.username);
      expect(cancel.status).toBe(409);
    }
  });
});
