import { expect, test } from '@/fixtures/test-options';
import {
  clearSetupDindState,
  readSetupDindState,
  writeSetupDindState,
} from '@/fixtures/helper/setup-state';
import { configuredGitRepo, waitForDeploy } from '@/helpers/deploy-helpers';
import { isDindUser } from '@/helpers/sss-feature-helpers';
import { skipUnless } from '@/helpers/test-helpers';
import { waitForSiteHttpReady } from '@/helpers/webserver-helpers';
import { Timeouts } from '@/config/timeouts';

/**
 * Provisions one shared DinD project (public git fixture) for the `deploy`
 * Playwright project. Reused across runs while the account still exists.
 */
test('provision shared DinD git-deployed user', async ({
  api,
  userFactory,
  anonymousRequest,
  settings,
}) => {
  test.setTimeout(Timeouts.deploy);
  const gitRepo = configuredGitRepo();

  const reused = await test.step('reuse DinD user from a previous run', async () => {
    const state = readSetupDindState();
    if (!state) {
      return undefined;
    }
    const known = (await api.getUserRaw(state.username)).status !== 404;
    if (!known) {
      clearSetupDindState();
      return undefined;
    }
    return state;
  });

  if (reused) {
    return;
  }

  const created = await userFactory.createDeployedUser({
    autoCleanup: false,
    git_repo: gitRepo,
  });
  skipUnless(created, 'Could not create a git-deployed DinD user on this engine.');

  test.skip(
    !(await isDindUser(api, created.username)),
    'Container management is not available for the created user.'
  );

  const log = await waitForDeploy(api, created.username, {
    timeout: settings.timing.deployTimeout,
  });
  expect(['success', 'partial', 'failed', 'cancelled']).toContain(log.status);

  const url = `https://${created.domain}/`;
  if (log.status === 'success' || log.status === 'partial') {
    await waitForSiteHttpReady(anonymousRequest, url, {
      timeout: Math.min(settings.timing.deployTimeout, 90_000),
    }).catch(() => undefined);
  }

  writeSetupDindState({
    username: created.username,
    domain: created.domain,
    url,
    git_repo: gitRepo,
    createdAt: Date.now(),
  });
});
