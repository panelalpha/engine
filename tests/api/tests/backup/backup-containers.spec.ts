import { expect, test } from '@/fixtures/test-options';
import { expectOneOf } from '@/helpers/expect-one-of';
import { rand } from '@/helpers/random';

test.describe('backup stores', () => {
  test('a local store is created, tested, updated and deleted', async ({ api }) => {
    const name = `pa-api-${rand('bk')}`;
    const location = `/var/tmp/${name}`;
    const created = await api.createBackupContainer({
      name,
      driver: 'local',
      location,
    });

    try {
      expect(created.data.name).toBe(name);
      expect(created.data.driver).toBe('local');
      expect(created.data.location).toBe(location);
      expect(created.data.has_credentials).toBe(false);

      const fetched = await api.getBackupContainer(created.data.id);
      expect(fetched.data.id).toBe(created.data.id);

      const listed = await api.listBackupContainers();
      expect(listed.data.some((row) => row.id === created.data.id)).toBe(true);

      const probed = await api.testBackupContainerRaw(created.data.id);
      expectOneOf(probed.status, [200, 422]);
      if (probed.status === 200) {
        expect((probed.body as { data?: { ok?: boolean } }).data?.ok).toBe(true);
      }

      const renamed = `${name}-ren`;
      const updated = await api.updateBackupContainer(created.data.id, { name: renamed });
      expect(updated.data.name).toBe(renamed);
    } finally {
      await api.deleteBackupContainerSafe(created.data.id);
    }

    expect((await api.getBackupContainerRaw(created.data.id)).status).toBe(404);
  });

  test('a missing store is 404', async ({ api }) => {
    expect((await api.getBackupContainerRaw(999_999_999)).status).toBe(404);
  });

  test('an invalid local location is refused', async ({ api }) => {
    const response = await api.createBackupContainerRaw({
      name: `pa-api-${rand('bad')}`,
      driver: 'local',
      location: '/',
    });
    expectOneOf(response.status, [400, 422]);
  });

  test('s3 without credentials is refused', async ({ api }) => {
    const response = await api.createBackupContainerRaw({
      name: `pa-api-${rand('s3')}`,
      driver: 's3',
      location: 'bucket/prefix',
    });
    expectOneOf(response.status, [400, 422]);
  });
});
