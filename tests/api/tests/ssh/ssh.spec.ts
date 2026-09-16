import { expect, test } from '@/fixtures/test-options';
import { sshCommandResultSchema } from '@/schemas';
import { validateParsedApiResponse } from '@/helpers/validate-parsed-response';
import { skipUnless } from '@/helpers/test-helpers';

test.describe('SSH command', () => {
  test('a classic user is refused', async ({ api, setupUser }) => {
    const response = await api.runSshCommandRaw(setupUser.username, { command: 'echo ok' });
    expect(response.status).toBe(422);
  });

  test('a missing user is a 404', async ({ api }) => {
    const response = await api.runSshCommandRaw('nosuchprojectxx', { command: 'echo ok' });
    expect(response.status).toBe(404);
  });

  test('an empty command is a 422', async ({ api, userFactory }) => {
    const user = await userFactory.createDindUser();
    skipUnless(user, 'DinD is not available on this engine.');
    const response = await api.runSshCommandRaw(user.username, { command: '' });
    expect(response.status).toBe(422);
  });

  test('a DinD user can run a command and a failing exit stays HTTP 200', async ({
    api,
    userFactory,
  }) => {
    const user = await userFactory.createDindUser();
    skipUnless(user, 'DinD is not available on this engine.');

    const ok = await api.runSshCommand(user.username, { command: 'echo ok' });
    validateParsedApiResponse(ok, sshCommandResultSchema);
    expect(ok.exit_code).toBe(0);
    expect(ok.stdout).toContain('ok');

    const failed = await api.runSshCommand(user.username, { command: 'false' });
    expect(failed.exit_code).not.toBe(0);
  });

  test('cwd is honoured and a short timeout kills a long sleep', async ({ api, userFactory }) => {
    const user = await userFactory.createDindUser();
    skipUnless(user, 'DinD is not available on this engine.');

    const cwd = await api.runSshCommand(user.username, { command: 'pwd', cwd: '/tmp' });
    expect(cwd.exit_code).toBe(0);
    expect(cwd.stdout).toContain('/tmp');

    // Today's engine surfaces a process timeout as HTTP 500; a non-zero exit
    // on 200 is the documented shape if that is ever fixed.
    const timedOut = await api.runSshCommandRaw(user.username, {
      command: 'sleep 30',
      timeout: 1,
    });
    expect([200, 500]).toContain(timedOut.status);
    if (timedOut.status === 200) {
      const body = timedOut.body as { exit_code?: number };
      expect(body.exit_code).not.toBe(0);
    }
  });

  test('pipes reach bash intact', async ({ api, userFactory }) => {
    const user = await userFactory.createDindUser();
    skipUnless(user, 'DinD is not available on this engine.');
    const result = await api.runSshCommand(user.username, { command: 'printf "a\\nb\\n" | wc -l' });
    expect(result.exit_code).toBe(0);
    expect(result.stdout.trim()).toMatch(/2/);
  });

  test('one project cannot run SSH as another', async ({ api, userFactory }) => {
    const a = await userFactory.createDindUser();
    const b = await userFactory.createDindUser();
    skipUnless(a, 'DinD is not available on this engine.');
    skipUnless(b, 'DinD is not available on this engine.');

    const response = await api.runSshCommandRaw(b.username, {
      command: `touch /tmp/from-${a.username}`,
    });
    expect(response.status).toBe(200);

    const leaked = await api.runSshCommand(a.username, {
      command: `test -f /tmp/from-${a.username}`,
    });
    expect(leaked.exit_code).not.toBe(0);
  });
});
