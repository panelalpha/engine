import type { APIRequestContext } from '@playwright/test';
import type { EngineApi } from '@/clients/engine-api';
import type { ModSecurityMode, ModSecurityRuleset } from '@/types';
import { delay } from '@/helpers/retry';
import { probeModsecurityHostingCompatible } from '@/helpers/modsec-hosting-probe';

export const VALID_MODES: ModSecurityMode[] = ['off', 'detection_only', 'on'];

/** Values the mode endpoint has to refuse — including near-misses in the wrong case. */
export const INVALID_MODES = [
  'invalid',
  'enabled',
  'disabled',
  'true',
  'false',
  'Off',
  'On',
  'DetectionOnly',
  '',
  '123',
];

export const NON_EXISTENT_RULESET = 'non-existent-ruleset-12345';

/** Time for a mode or ruleset change to reach the running webserver. */
export const MODSEC_PROPAGATION_DELAY_MS = 1_000;

export interface ModSecSnapshot {
  mode: ModSecurityMode;
  rulesets: ModSecurityRuleset[];
}

/**
 * Why ModSecurity tests cannot run here, or `undefined` when they can.
 *
 * Two separate reasons: the module may not be installed at all, or the hosting
 * stack may not route through it (some webserver builds ship without the
 * connector). Both are skips, not failures — but they are reported distinctly.
 */
export async function modsecUnavailableReason(
  api: EngineApi,
  http: APIRequestContext,
  probeUrl: string | undefined
): Promise<string | undefined> {
  try {
    await api.getModSecurityConfig();
  } catch {
    return 'ModSecurity is not available on this engine.';
  }

  if (!probeUrl) {
    return undefined;
  }

  const probe = await probeModsecurityHostingCompatible(api, http, probeUrl);
  return probe.compatible ? undefined : probe.skipReason;
}

/** Records the mode and every ruleset's state, so a test can put them back. */
export async function snapshotModSec(api: EngineApi): Promise<ModSecSnapshot> {
  const [config, rulesets] = await Promise.all([
    api.getModSecurityConfig(),
    api.listModSecurityRulesets(),
  ]);

  return { mode: config.data.mode, rulesets: rulesets.data ?? [] };
}

/** Puts the mode and every ruleset back to what {@link snapshotModSec} recorded. */
export async function restoreModSec(api: EngineApi, snapshot: ModSecSnapshot): Promise<void> {
  await api.setModSecurityConfig({ mode: snapshot.mode });
  await delay(MODSEC_PROPAGATION_DELAY_MS);

  for (const ruleset of snapshot.rulesets) {
    if (ruleset.enabled) {
      await api.enableModSecurityRuleset(ruleset.name);
    } else {
      await api.disableModSecurityRuleset(ruleset.name);
    }
  }
}

/**
 * Whether a response is ModSecurity refusing the request.
 *
 * Which shape it takes depends on the stack and the rule: an outright 403, a 404
 * from a rule that hides the resource, a 406 or 429 from a rate limit, or a 200
 * carrying a block page.
 */
export function isBlockedResponse(status: number, body: string): boolean {
  if ([403, 404, 405, 406, 429].includes(status)) {
    return true;
  }

  return (
    status === 200 &&
    /forbidden|access denied|not found|404|406|blocked|misdirected request|mod_security|request rejected/i.test(
      body
    )
  );
}

export const AJAX_ACTION = 'panelalpha_ajax_test';
export const AJAX_PLUGIN_FILENAME = 'panelalpha-ajax-test.php';

/**
 * A must-use plugin exposing one trivial AJAX action.
 *
 * admin-ajax.php is how most WordPress plugins talk to the server, so a WAF
 * ruleset that blocks it breaks the site while looking like it is working. This
 * gives the tests a known-good endpoint to prove stays reachable.
 */
export const AJAX_PLUGIN_CONTENTS = `<?php
/*
Plugin Name: PanelAlpha AJAX Test
*/
add_action('wp_ajax_${AJAX_ACTION}', '${AJAX_ACTION}');
add_action('wp_ajax_nopriv_${AJAX_ACTION}', '${AJAX_ACTION}');

function ${AJAX_ACTION}() {
    wp_send_json_success(['status' => 'ok']);
}
`;

export function isBlockedStatus(status: number): boolean {
  return [403, 404, 405, 406, 429].includes(status);
}

export function isBlockedBody(body: string): boolean {
  return /access denied|forbidden|mod_security|request rejected|not allowed|blocked/i.test(body);
}

export interface AuthorEnumerationProbe {
  status: number;
  body: string;
  finalUrl: string;
}

/**
 * Whether the response actually served a WordPress author archive.
 *
 * `?author=1` redirects to `/author/<login>/` when enumeration is possible,
 * which leaks a valid username. A redirect to the homepage does not.
 */
export function isAuthorArchiveExposure(
  probe: Pick<AuthorEnumerationProbe, 'finalUrl' | 'body'>
): boolean {
  try {
    if (/\/author\/[^/]+/i.test(new URL(probe.finalUrl).pathname)) {
      return true;
    }
  } catch {
    // A malformed final URL tells us nothing; fall through to the body checks.
  }

  return (
    /author\s+archives/i.test(probe.body) || /<body[^>]*class="[^"]*\bauthor\b/i.test(probe.body)
  );
}

/** Blocked outright, or served something that is not an author archive. */
export function isAuthorEnumerationMitigated(probe: AuthorEnumerationProbe): boolean {
  return (
    isBlockedStatus(probe.status) || isBlockedBody(probe.body) || !isAuthorArchiveExposure(probe)
  );
}
