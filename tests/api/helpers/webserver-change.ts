import type { APIRequestContext } from '@playwright/test';
import type { EngineApi } from '@/clients/engine-api';
import type { SystemChangeStatus } from '@/types';
import { delay } from '@/helpers/retry';
import {
  buildWebserverChangeDebugContext,
  formatWebserverChangeDebugMessage,
} from '@/helpers/test-user-cleanup';
import {
  formatWebserverChange,
  getWebserverChangeSnapshot,
  getWebserverFirstStartReadyMs,
  getWebserverInfo,
  isNewerWebserverChange,
  isWebserverChangeInFlight,
  waitForSiteHttpReady,
  type WebserverChangeSnapshot,
  type WebserverSlug,
} from '@/helpers/webserver-helpers';
import type { SetupTestData } from '@/types/user.types';

const POLL_INTERVAL_MS = 2_000;
const STABILISATION_WAIT_MS = 5_000;
/** How long a finished change may keep reporting the old slug before we stop believing it. */
const SLUG_SETTLE_GRACE_MS = 120_000;
/** No new log output for this long means the change script is wedged. */
const STALL_DETECTION_MS = 10 * 60 * 1000;
/** The change must at least register in latest_webserver_change within this window. */
const CHANGE_START_TIMEOUT_MS = 5 * 60 * 1000;
const PROGRESS_LOG_INTERVAL_MS = 60_000;
/** Consecutive slug readings before believing the change landed. */
const SLUG_MATCHES_REQUIRED = 2;
/** Connection errors tolerated while the host restarts its webserver. */
const MAX_CONSECUTIVE_ERRORS = 36;

const TRANSIENT_ERROR = /ECONNREFUSED|ECONNRESET|ETIMEDOUT|Timeout|fetch failed/i;

export interface ChangeWebserverOptions {
  api: EngineApi;
  http: APIRequestContext;
  target: WebserverSlug;
  setupUser: SetupTestData;
  /** Overall budget, from `settings.timing.webserverChangeTimeout`. */
  timeoutMs: number;
}

export class WebserverChangeError extends Error {
  constructor(message: string, setupUser: SetupTestData, change?: SystemChangeStatus | null) {
    const debug = buildWebserverChangeDebugContext(setupUser, change);
    super(`${message}\n\n${formatWebserverChangeDebugMessage(debug)}`);
    this.name = 'WebserverChangeError';
  }
}

/**
 * Switches the engine's webserver and waits until it is actually serving.
 *
 * Success is judged from three signals, because no single one is reliable:
 *
 * 1. `latest_webserver_change.exit_code === 0` — the change script's own verdict.
 * 2. `/system/info` reporting the target slug twice in a row.
 * 3. The setup user's site answering over HTTP.
 *
 * Signal 2 can lag indefinitely: `System::getCurrentWebserver()` caches the slug
 * in a per-FPM-worker static that nothing invalidates when the symlink is
 * swapped, and those workers outlive the change. So once the script has exited 0
 * and the grace period has passed, the HTTP probe — which reaches the real
 * container, not a cached value — becomes the deciding signal.
 */
export async function changeWebserverAndWait(options: ChangeWebserverOptions): Promise<void> {
  const { api, http, target, setupUser, timeoutMs } = options;

  const current = await getWebserverInfo(api);
  if (current.slug === target) {
    return;
  }

  const before = await getWebserverChangeSnapshot(api);
  const serialNumber = process.env.LITESPEED_SERIAL_NUMBER?.trim();
  const serialSentWithChange = target === 'litespeed' && Boolean(serialNumber);

  await api.changeWebserver(
    target,
    serialSentWithChange ? { serial_number: serialNumber } : undefined
  );
  const initiatedAt = Date.now();

  const deadline = initiatedAt + timeoutMs;
  const state = {
    consecutiveErrors: 0,
    slugMatches: 0,
    changeSeen: false,
    licenseApplied: false,
    finishedAt: null as number | null,
    lastTail: '',
    lastTailChangeAt: Date.now(),
    lastProgressLogAt: 0,
    lastSnapshot: null as WebserverChangeSnapshot | null,
  };

  /** Applies the LiteSpeed licence once, and lets the reload it triggers settle. */
  const applyLicenceOnce = async (): Promise<void> => {
    if (state.licenseApplied) {
      return;
    }
    state.licenseApplied = true;

    if (target !== 'litespeed' || serialSentWithChange) {
      return;
    }
    if (!serialNumber) {
      console.warn(
        '[webserver] LITESPEED_SERIAL_NUMBER is not set — LiteSpeed will run in trial mode.'
      );
      return;
    }

    try {
      await api.updateWebserverConfig({ serial_number: serialNumber });
    } catch (error) {
      throw new Error(
        'Failed to register the LiteSpeed serial via system/webserver-config — the engine ' +
          `rejected it, or the reload failed.\n${String(error)}`
      );
    }
    await delay(STABILISATION_WAIT_MS);
  };

  /** Final gate: the setup user's site has to answer on the new stack. */
  const siteBecomesReady = async (change: SystemChangeStatus | null | undefined) => {
    await applyLicenceOnce();
    if (!setupUser.url) {
      return;
    }

    const budget = getWebserverFirstStartReadyMs(target);
    try {
      await waitForSiteHttpReady(http, setupUser.url, {
        timeout: budget,
        interval: POLL_INTERVAL_MS,
      });
    } catch {
      const hint =
        target === 'litespeed' && !serialNumber
          ? ' LiteSpeed Enterprise most likely failed to start — set LITESPEED_SERIAL_NUMBER.'
          : '';
      throw new WebserverChangeError(
        `${setupUser.url} did not answer within ${budget / 1000}s after changing to ` +
          `"${target}".${hint}`,
        setupUser,
        change
      );
    }
  };

  while (Date.now() < deadline) {
    if (Date.now() - state.lastProgressLogAt >= PROGRESS_LOG_INTERVAL_MS) {
      state.lastProgressLogAt = Date.now();
      const latest = state.lastSnapshot?.latestWebserverChange;
      console.log(
        `[webserver-change] -> ${target}: exit_code=${latest?.exit_code ?? 'pending'}, ` +
          `tail=${(latest?.tail_stdout ?? '').split('\n').pop()?.trim() ?? '(none)'}`
      );
    }

    try {
      const [info, snapshot] = await Promise.all([
        getWebserverInfo(api),
        getWebserverChangeSnapshot(api),
      ]);
      state.consecutiveErrors = 0;
      state.lastSnapshot = snapshot;

      const latest = snapshot.latestWebserverChange;
      state.changeSeen ||=
        isNewerWebserverChange(latest, before.latestWebserverChange) ||
        isWebserverChangeInFlight(latest, initiatedAt);

      if (!state.changeSeen && Date.now() - initiatedAt >= CHANGE_START_TIMEOUT_MS) {
        throw new WebserverChangeError(
          `The change to "${target}" never registered in latest_webserver_change within ` +
            `${CHANGE_START_TIMEOUT_MS / 1000}s. The background script probably never started — ` +
            'check /opt/panelalpha/log/change-webserver/ on the host.',
          setupUser,
          latest
        );
      }

      const tail = `${latest?.tail_stdout ?? ''}|${latest?.tail_stderr ?? ''}`;
      if (tail !== state.lastTail) {
        state.lastTail = tail;
        state.lastTailChangeAt = Date.now();
      } else if (state.changeSeen && Date.now() - state.lastTailChangeAt >= STALL_DETECTION_MS) {
        throw new WebserverChangeError(
          `The change to "${target}" produced no log output for ${STALL_DETECTION_MS / 1000}s.`,
          setupUser,
          latest
        );
      }

      if (!state.changeSeen) {
        await delay(POLL_INTERVAL_MS);
        continue;
      }

      const exitCode = latest?.exit_code;
      if (exitCode !== null && exitCode !== undefined && exitCode !== 0) {
        throw new WebserverChangeError(
          `The change to "${target}" exited with ${exitCode}.\n${formatWebserverChange(latest)}`,
          setupUser,
          latest
        );
      }

      if (latest?.finished_at && exitCode === 0) {
        state.finishedAt ??= Date.now();

        if (info.slug === target) {
          await delay(STABILISATION_WAIT_MS);
          if ((await getWebserverInfo(api)).slug === target) {
            await siteBecomesReady(latest);
            return;
          }
        } else if (Date.now() - state.finishedAt > SLUG_SETTLE_GRACE_MS) {
          console.warn(
            `[webserver-change] the change to "${target}" finished cleanly but /system/info ` +
              `still reports "${info.slug}" after ${SLUG_SETTLE_GRACE_MS / 1000}s — treating the ` +
              'slug as a stale FPM cache and trusting the HTTP probe.'
          );
          await siteBecomesReady(latest);
          return;
        }
      }

      if (info.slug === target) {
        state.slugMatches++;
        if (state.slugMatches >= SLUG_MATCHES_REQUIRED && setupUser.url) {
          try {
            await siteBecomesReady(latest);
            return;
          } catch {
            // The stack is up but not serving yet; keep polling.
            state.slugMatches = 0;
          }
        }
      } else {
        state.slugMatches = 0;
      }
    } catch (error) {
      if (error instanceof WebserverChangeError) {
        throw error;
      }
      if (!TRANSIENT_ERROR.test(String(error))) {
        throw error;
      }

      state.consecutiveErrors++;
      if (state.consecutiveErrors > MAX_CONSECUTIVE_ERRORS) {
        throw new WebserverChangeError(
          `The engine stayed unreachable for ${state.consecutiveErrors} polls while changing to ` +
            `"${target}".\n${formatWebserverChange(state.lastSnapshot?.latestWebserverChange)}`,
          setupUser,
          state.lastSnapshot?.latestWebserverChange
        );
      }
    }

    await delay(POLL_INTERVAL_MS);
  }

  throw new WebserverChangeError(
    `The change to "${target}" did not complete within ${timeoutMs / 1000}s.\n` +
      formatWebserverChange(state.lastSnapshot?.latestWebserverChange),
    setupUser,
    state.lastSnapshot?.latestWebserverChange
  );
}
