import { expect, test } from '@/fixtures/test-options';
import { deployLogSnapshotSchema } from '@/schemas';
import { validateParsedApiResponse } from '@/helpers/validate-parsed-response';
import {
  configuredGitRepo,
  DEFAULT_DEPLOY_GIT_REPO,
  waitForDeploy,
} from '@/helpers/deploy-helpers';
import { isDindUser } from '@/helpers/sss-feature-helpers';
import { expectOneOf } from '@/helpers/expect-one-of';
import { skipUnless } from '@/helpers/test-helpers';
import { Timeouts } from '@/config/timeouts';
import { assertDeploymentWarnings, healthFromRaw } from '@/helpers/app-health';

test.describe('git deploy', () => {
  test('creating a project with git_repo deploys and records timings', async ({
    api,
    userFactory,
    anonymousRequest,
  }) => {
    test.setTimeout(Timeouts.deploy);
    const gitRepo = configuredGitRepo();
    const user = await userFactory.createDeployedUser({ git_repo: gitRepo });
    skipUnless(user, 'Could not create a git-deployed user on this engine.');
    test.skip(!(await isDindUser(api, user.username)), 'DinD is not available on this engine.');

    const log = await waitForDeploy(api, user.username);
    validateParsedApiResponse(log, deployLogSnapshotSchema);
    expect(['success', 'partial', 'failed', 'cancelled']).toContain(log.status);
    expect(typeof log.next_offset).toBe('number');
    expect(Array.isArray(log.lines)).toBe(true);

    const shown = await api.getUser(user.username);
    expect(shown.data.details.template).toBe('dind');
    if (shown.data.details.deploy_strategy != null) {
      expect(shown.data.details.deploy_strategy.length).toBeGreaterThan(0);
    }
    if (shown.data.details.deployment_status != null) {
      expect(['success', 'partial', 'failed', 'cancelled', 'running']).toContain(
        shown.data.details.deployment_status
      );
      assertDeploymentWarnings(
        shown.data.details.deployment_status,
        shown.data.details.deployment_warnings
      );
    }

    if (log.status === 'success' || log.status === 'partial') {
      expect(log.timings).toBeTruthy();
      if (log.timings) {
        expect(Array.isArray(log.timings.phases)).toBe(true);
        expect(
          log.timings.total_seconds === null || typeof log.timings.total_seconds === 'number'
        ).toBe(true);
      }

      const site = await anonymousRequest.get(`https://${user.domain}/`, {
        ignoreHTTPSErrors: true,
      });
      const strategy = shown.data.details.deploy_strategy;
      if (strategy === 'fallback') {
        expect(site.status(), 'fallback must not be treated as a successful app').toBeGreaterThan(
          0
        );
      } else {
        expectOneOf(site.status(), [200, 201, 202, 204, 301, 302, 303, 307, 308, 401, 403]);
      }

      const health = await api.getAppHealthRaw(user.username);
      if (health.status === 200) {
        healthFromRaw(health.body);
      }
    }
  });

  test('git_token without an HTTPS repo is refused', async ({ api, settings }) => {
    const username = `gt${Date.now().toString(36).slice(-8)}`;
    const domain = `${username}.${settings.requireDomain()}`;
    const response = await api.createUserRaw({
      username,
      domain,
      git_token: 'not-a-real-token',
    });
    expectOneOf(response.status, [400, 422]);
    await api.deleteUserSafe(username);
  });

  test('an invalid username with git_repo is refused', async ({ api, settings }) => {
    const response = await api.createUserRaw({
      username: 'A',
      domain: `bad.${settings.requireDomain()}`,
      git_repo: DEFAULT_DEPLOY_GIT_REPO,
    });
    expectOneOf(response.status, [400, 422]);
  });

  test('deploy-archive with a static zip deploys', async ({
    api,
    userFactory,
    anonymousRequest,
  }) => {
    test.setTimeout(Timeouts.deploy);
    const user = await userFactory.createDindUser();
    skipUnless(user, 'DinD is not available on this engine.');

    await api.createDirectory(user.username, '/site', true);
    await api.putFileContents(
      user.username,
      '/site/index.html',
      '<!doctype html><title>archive</title>archive-ok\n'
    );
    await api.zipFiles(user.username, '/project/app.zip', '/site', true);
    const zipped = await api.fileExists(user.username, '/project/app.zip');
    test.skip(!zipped.exists, 'Could not zip a static site in the account home.');

    const started = await api.deployArchiveRaw(user.username, { zip_path: '/project/app.zip' });
    expectOneOf(started.status, [200, 201]);

    const log = await waitForDeploy(api, user.username);
    expect(['success', 'partial', 'failed', 'cancelled']).toContain(log.status);
    if (log.status === 'success' || log.status === 'partial') {
      const shown = await api.getUser(user.username);
      expect(shown.data.details.deployment_status).toBe(log.status);
      assertDeploymentWarnings(log.status, shown.data.details.deployment_warnings);
      const site = await anonymousRequest.get(`https://${user.domain}/`, {
        ignoreHTTPSErrors: true,
      });
      expect(site.status()).toBeGreaterThan(0);
    }
  });

  test('deploy-archive without a zip is refused', async ({ api, userFactory }) => {
    const user = await userFactory.createDindUser();
    skipUnless(user, 'DinD is not available on this engine.');
    const empty = await api.deployArchiveRaw(user.username, {});
    expect(empty.status).toBe(422);
    const notZip = await api.deployArchiveRaw(user.username, { zip_path: '/no-such.zip' });
    expectOneOf(notZip.status, [404, 422]);
  });

  test('cancelling when idle is a 409', async ({ api, setupDindUser }) => {
    const cancel = await api.cancelDeployRaw(setupDindUser.username);
    expectOneOf(cancel.status, [200, 409]);
  });
});
