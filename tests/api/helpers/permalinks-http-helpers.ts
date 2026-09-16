import { type APIResponse, expect } from '@playwright/test';
import type { EngineApi } from '@/clients/engine-api';
import { delay } from './retry';
import {
  getWebserverInfo,
  getWebserverPropagationDelay,
  getWebserverRewriteWaitMs,
} from './webserver-helpers';

export const PERMALINK_STRUCTURES = {
  plain: '',
  dayAndName: '/%year%/%monthnum%/%day%/%postname%/',
  monthAndName: '/%year%/%monthnum%/%postname%/',
  numeric: '/archives/%post_id%',
  postName: '/%postname%/',
  custom: '/%category%/%postname%/',
} as const;

export function generateUniqueSlug(baseSlug: string): string {
  return `${baseSlug}-${Date.now()}`;
}

export async function applyPermalinkStructure(
  api: EngineApi,
  username: string,
  structure: string,
  wpPath: string
): Promise<void> {
  await api.executeWpCliCommand(username, ['rewrite', 'structure', structure, wpPath]);
  if (structure) {
    await api.executeWpCliCommand(username, [
      'eval',
      "insert_with_markers(get_home_path() . '.htaccess', 'WordPress', (new WP_Rewrite)->mod_rewrite_rules());",
      wpPath,
    ]);
  }
  await api.executeWpCliCommand(username, ['rewrite', 'flush', '--hard', wpPath]);
}

export async function assertPermalinkStructureSet(
  api: EngineApi,
  username: string,
  structure: string,
  wpPath: string
): Promise<void> {
  const response = await api.executeWpCliCommand(username, [
    'option',
    'get',
    'permalink_structure',
    wpPath,
  ]);
  expect(response.stdout.trim()).toBe(structure);
}

export function normalizePermalink(permalink: string, domain: string): string {
  try {
    const url = new URL(permalink);
    return `https://${domain}${url.pathname}${url.search}`;
  } catch {
    const path = permalink.startsWith('/') ? permalink : `/${permalink}`;
    return `https://${domain}${path}`;
  }
}

function escapeRegex(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

export { escapeRegex };

export function slugPattern(slug: string): string {
  return `${escapeRegex(slug)}(?:-\\d+)?`;
}

export function assertPrettyPermalink(permalink: string, expectedPathPattern: RegExp): URL {
  const url = new URL(permalink);
  expect(url.search, `Expected pretty permalink without query string: ${permalink}`).toBe('');
  expect(url.pathname).toMatch(expectedPathPattern);
  return url;
}

export async function getPermalinkUrl(
  api: EngineApi,
  username: string,
  postId: string,
  wpPath: string,
  domain: string
): Promise<string> {
  const response = await api.executeWpCliCommand(username, [
    'eval',
    `echo get_permalink(${postId});`,
    wpPath,
  ]);
  const raw = response.stdout.trim();
  expect(raw).toBeTruthy();
  return normalizePermalink(raw, domain);
}

export async function fetchWithRetry(
  httpClient: { get: (url: string, options?: object) => Promise<APIResponse> },
  url: string,
  api: EngineApi
): Promise<APIResponse> {
  const { slug } = await getWebserverInfo(api);
  const propagationDelay = getWebserverPropagationDelay(slug);
  const maxWaitMs = getWebserverRewriteWaitMs(slug);
  const deadline = Date.now() + maxWaitMs;
  let response;
  let attempt = 0;

  while (Date.now() < deadline) {
    if (attempt > 0) {
      await delay(propagationDelay);
    }
    response = await httpClient.get(url, { ignoreHTTPSErrors: true, maxRedirects: 5 });
    if (response.status() === 200) {
      return response;
    }
    attempt += 1;
  }

  if (!response) {
    throw new Error(`Failed to fetch ${url} within ${maxWaitMs}ms`);
  }

  return response;
}

/** WP-CLI `--porcelain` output can carry PHP notices; take the first bare integer. */
export function parseWpCliId(stdout: string): string | undefined {
  return /\b(\d+)\b/.exec(stdout.trim())?.[1];
}
