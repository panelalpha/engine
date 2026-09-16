import { expect, test } from '@/fixtures/test-options';
import { delay } from '@/helpers/retry';
import {
  getWebserverInfo,
  getWebserverPropagationDelay,
  httpScheme,
} from '@/helpers/webserver-helpers';

const WORKER_SCRIPT = `<?php
header('Cache-Control: no-store');
header('Content-Type: application/json');
$start = microtime(true);
$pid = getmypid();
sleep(3);
echo json_encode(['pid' => $pid, 'start' => $start, 'end' => microtime(true)]);
?>`;

const REQUEST_TIMEOUT_MS = 25_000;

/**
 * Worker limits are squeezed to a single child, and two slow requests are fired
 * at once: both must still complete, rather than the second being dropped.
 *
 * This narrows the shared setup user's pool, so the original settings are put
 * back in a `finally` — leaving it at one worker would serialise, and eventually
 * time out, every spec that runs after this one.
 */
test('a single-worker pool still serves concurrent requests', async ({
  api,
  anonymousRequest,
  settings,
  setupUser,
}) => {
  const { slug } = await getWebserverInfo(api);
  const isLiteSpeed = slug.includes('litespeed');
  const scriptPath = `${setupUser.domain}/public_html/worker-sleep.php`;

  const { details } = (await api.getUser(setupUser.username)).data;
  const originalFpm = details.php_fpm_pool_settings ?? null;
  const originalLsphp = details.lsphp_settings ?? null;

  await api.putFileContents(setupUser.username, scriptPath, WORKER_SCRIPT);

  try {
    if (isLiteSpeed) {
      await api.updateUser(setupUser.username, { lsphp_settings: 'PHP_LSAPI_CHILDREN=1' });
    } else {
      // `static` rather than `dynamic`: FPM rejects a dynamic pool whose spare
      // server counts exceed max_children.
      await api.updateUser(setupUser.username, {
        php_fpm_pool_settings: ['pm = static', 'pm.max_children = 1'].join('\n'),
      });
      await delay(getWebserverPropagationDelay(slug) + settings.timing.phpExecutionDelay);
    }

    await delay(settings.timing.phpExecutionDelay);

    const { config } = (await api.getUser(setupUser.username)).data;
    if (isLiteSpeed) {
      expect(config.lsphp_settings?.PHP_LSAPI_CHILDREN).toBe('1');
    } else {
      expect(config.php_fpm_pool_settings?.pm).toBe('static');
      expect(config.php_fpm_pool_settings?.['pm.max_children']).toBe('1');
    }

    const url = `${httpScheme(settings.apiBaseUrl)}://${setupUser.domain}/worker-sleep.php`;
    const statuses = await Promise.all(
      [0, 1].map(async (index) => {
        const response = await anonymousRequest.get(`${url}?t=${Date.now()}&i=${index}`, {
          ignoreHTTPSErrors: true,
          timeout: REQUEST_TIMEOUT_MS,
        });
        return response.status();
      })
    );

    expect(statuses).toEqual([200, 200]);
  } finally {
    await api.removeFile(setupUser.username, scriptPath);
    await api.updateUser(
      setupUser.username,
      isLiteSpeed ? { lsphp_settings: originalLsphp } : { php_fpm_pool_settings: originalFpm }
    );
  }
});
