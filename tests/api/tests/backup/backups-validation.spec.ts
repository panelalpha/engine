import { expect, test } from '@/fixtures/test-options';
import { expectOneOf } from '@/helpers/expect-one-of';
import { skipUnless } from '@/helpers/test-helpers';

test.describe('project backups validation', () => {
  test('a PHP account cannot be backed up', async ({ api, userFactory }) => {
    const user = await userFactory.createSimpleUser();
    const response = await api.createProjectBackupRaw(user.username, { container: 'missing' });
    expect(response.status).toBe(422);
    expect(JSON.stringify(response.body)).toMatch(/not supported/i);
  });

  test('listing backups for an unknown project is 404', async ({ api }) => {
    expect((await api.listProjectBackupsRaw('nosuchuser999')).status).toBe(404);
  });

  test('a missing backup is 404', async ({ api, userFactory }) => {
    const user = await userFactory.createSimpleUser();
    expect((await api.getProjectBackupRaw(user.username, 999_999_999)).status).toBe(404);
  });

  test('creating a backup without a store is refused', async ({ api, userFactory }) => {
    const user = await userFactory.createDindUser();
    skipUnless(user, 'DinD is not available on this engine.');
    const missingStore = await api.createProjectBackupRaw(user.username, {});
    expectOneOf(missingStore.status, [400, 422]);
    const unknownStore = await api.createProjectBackupRaw(user.username, {
      container: 'no-such-store',
    });
    expectOneOf(unknownStore.status, [404, 422]);
  });
});
