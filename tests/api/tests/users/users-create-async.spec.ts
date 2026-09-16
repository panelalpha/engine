import { expect, test } from '@/fixtures/test-options';
import { randomDomain, randomUsername } from '@/helpers/random';
import { Timeouts } from '@/config/timeouts';
import { expectOneOf } from '@/helpers/expect-one-of';
import { PROJECT_CREATE_ACCEPTED, taskIdFromBody, waitForTask } from '@/helpers/task-helpers';

test.describe('async project create', () => {
  test('POST /projects returns 202 with a task, then the account is readable', async ({
    api,
    settings,
  }) => {
    test.setTimeout(Timeouts.deploy);
    const username = randomUsername();
    const domain = randomDomain(settings.requireDomain());

    try {
      const created = await api.createUserRaw({ username, domain });
      expect(created.status).toBe(PROJECT_CREATE_ACCEPTED);
      const taskId = taskIdFromBody(created.body);
      expect(taskId).toBeGreaterThan(0);

      const polled = await api.getTask(taskId!);
      expect(polled.data.id).toBe(taskId);
      expect(['queued', 'running', 'completed', 'failed', 'cancelled']).toContain(
        polled.data.status
      );
      expect(polled.data.username).toBe(username);

      const logs = await api.listTaskLogs(taskId!);
      expect(Array.isArray(logs.data)).toBe(true);
      expect(typeof logs.meta?.task_status).toBe('string');
      expect(typeof logs.meta?.next_after_id).toBe('number');

      const sinceLogs = await api.listTaskLogs(taskId!, { since: 0 });
      expect(Array.isArray(sinceLogs.data)).toBe(true);

      const task = await waitForTask(api, taskId!);
      expect(['completed', 'failed', 'cancelled']).toContain(task.status);

      const account = await api.getUserRaw(username);
      expect(account.status).toBe(200);

      const stream = await api.streamTaskLogsRaw(taskId!);
      expect(stream.status).toBe(200);
      expect(stream.body).toMatch(/"type":"finish"/);
    } finally {
      await api.deleteUserSafe(username);
    }
  });

  test('a queued project-create task can be cancelled', async ({ api, settings }) => {
    test.setTimeout(Timeouts.deploy);
    const username = randomUsername();
    const domain = randomDomain(settings.requireDomain());

    try {
      const created = await api.createUserRaw({ username, domain });
      expect(created.status).toBe(PROJECT_CREATE_ACCEPTED);
      const taskId = taskIdFromBody(created.body);
      expect(taskId).toBeGreaterThan(0);

      const cancel = await api.cancelTaskRaw(taskId!);
      // 200 = cancelled while queued/running. 409 = DeployProject already finished.
      expectOneOf(cancel.status, [200, 409]);

      const task = await waitForTask(api, taskId!);
      expectOneOf(task.status, ['completed', 'failed', 'cancelled']);
    } finally {
      await api.deleteUserSafe(username);
    }
  });
});
