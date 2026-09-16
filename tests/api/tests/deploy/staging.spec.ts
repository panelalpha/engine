import { expect, test } from '@/fixtures/test-options';
import { Timeouts } from '@/config/timeouts';
import { expectOneOf } from '@/helpers/expect-one-of';
import { waitForCondition } from '@/helpers/retry';
import {
  stagingPushPhase,
  stagingUserPayload,
  waitUntilStagingActive,
} from '@/helpers/staging-helpers';
import { skipUnless } from '@/helpers/test-helpers';

/**
 * DinD staging copies volumes asynchronously. It does not belong in the default
 * api project — a failing CreateStaging used to sit on GET 404 for 180s twice.
 */
test.describe('DinD user staging', () => {
  test('a DinD user can create staging and become active', async ({ api, userFactory }) => {
    test.setTimeout(Timeouts.deploy);
    const user = await userFactory.createDindUser({ tunnel: 'none' });
    skipUnless(user, 'DinD is not available on this engine.');
    const username = user.username;

    let stagingUsername: string | undefined;

    try {
      const response = await api.createStagingRaw(username, {});
      expect(response.status).toBe(202);
      const staged = stagingUserPayload(response.body as unknown);
      expect(staged.status).toBe('pending');
      expect(staged.staging_of).toBe(username);
      stagingUsername = staged.username;
      expect(stagingUsername).toBeTruthy();

      await waitUntilStagingActive(api, stagingUsername!);

      const active = await api.getUser(stagingUsername!);
      expect(active.data.status).toBe('active');
      expect(active.data.staging_of).toBe(username);
    } finally {
      if (stagingUsername) {
        await api.deleteUserSafe(stagingUsername);
      }
      await api.deleteUserSafe(username);
    }
  });

  test('a DinD staging project can push back to live', async ({ api, userFactory }) => {
    test.setTimeout(Timeouts.deploy);
    const user = await userFactory.createDindUser({ tunnel: 'none' });
    skipUnless(user, 'DinD is not available on this engine.');
    const username = user.username;

    let stagingUsername: string | undefined;

    try {
      const response = await api.createStagingRaw(username, {});
      expect(response.status).toBe(202);
      stagingUsername = stagingUserPayload(response.body as unknown).username;
      expect(stagingUsername).toBeTruthy();

      await waitUntilStagingActive(api, stagingUsername!);

      const pushed = await api.pushProjectRaw(stagingUsername!, username);
      expect(pushed.status).toBe(202);

      await waitForCondition(
        async () => {
          const phase = stagingPushPhase((await api.getUserRaw(username)).body as unknown);
          return phase === 'completed' || phase === 'failed';
        },
        {
          timeout: 180_000,
          interval: 2_000,
          message: 'Push did not reach a terminal status',
          describeLast: async () =>
            `push=${stagingPushPhase((await api.getUserRaw(username)).body as unknown) ?? 'missing'}`,
        }
      );

      expectOneOf(stagingPushPhase((await api.getUserRaw(username)).body as unknown) ?? 'missing', [
        'completed',
        'failed',
      ]);
    } finally {
      if (stagingUsername) {
        await api.deleteUserSafe(stagingUsername);
      }
      await api.deleteUserSafe(username);
    }
  });
});
