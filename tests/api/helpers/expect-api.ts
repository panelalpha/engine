import { type APIResponse, expect } from '@playwright/test';

export interface ExpectApiOptions {
  /** Human-readable intent, e.g. "reject empty WP-CLI args". */
  action?: string;
  /** HTTP method (Playwright APIResponse does not expose it). */
  method?: string;
}

const PLAIN_TEXT_MAX = 120;
const JSON_SUMMARY_MAX = 400;

function truncate(text: string, max: number): string {
  if (text.length <= max) {
    return text;
  }
  return `${text.slice(0, max)}…`;
}

function compactJson(value: unknown): string {
  try {
    return JSON.stringify(value);
  } catch {
    return String(value);
  }
}

/** Relative API path without base URL or leading slash. */
function extractApiPath(url: string): string {
  try {
    const parsed = new URL(url);
    let path = parsed.pathname.replace(/^\//, '');
    if (path.startsWith('api/')) {
      path = path.slice(4);
    }
    const query = parsed.search;
    return query ? `${path}${query}` : path;
  } catch {
    return url.replace(/^\//, '').replace(/^api\//, '');
  }
}

function summarizeApiBody(body: unknown): string {
  if (body === undefined || body === null) {
    return '';
  }

  if (typeof body === 'string') {
    const trimmed = body.trim();
    if (!trimmed) {
      return '';
    }
    if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
      try {
        return summarizeApiBody(JSON.parse(trimmed) as unknown);
      } catch {
        return `body[${trimmed.length}]: ${truncate(trimmed.replace(/\s+/g, ' '), PLAIN_TEXT_MAX)}`;
      }
    }
    return `body[${trimmed.length}]: ${truncate(trimmed.replace(/\s+/g, ' '), PLAIN_TEXT_MAX)}`;
  }

  if (typeof body !== 'object') {
    return typeof body === 'string' || typeof body === 'number' || typeof body === 'boolean'
      ? String(body)
      : compactJson(body);
  }

  const record = body as Record<string, unknown>;
  const parts: string[] = [];

  for (const key of ['message', 'error', 'success'] as const) {
    if (key in record && record[key] !== undefined && record[key] !== null) {
      parts.push(`${key}=${compactJson(record[key])}`);
    }
  }

  if ('errors' in record && record.errors !== undefined) {
    parts.push(`errors=${truncate(compactJson(record.errors), 200)}`);
  }

  if ('data' in record && record.data !== undefined) {
    const data = record.data;
    if (typeof data === 'object' && data !== null && !Array.isArray(data)) {
      const dataKeys = Object.keys(data).slice(0, 2);
      for (const dk of dataKeys) {
        const val = (data as Record<string, unknown>)[dk];
        parts.push(`${dk}=${truncate(compactJson(val), 80)}`);
      }
    } else if (typeof data === 'boolean' || typeof data === 'number' || typeof data === 'string') {
      parts.push(`data=${compactJson(data)}`);
    } else if (Array.isArray(data)) {
      parts.push(`data[${data.length}]`);
    }
  }

  if (parts.length === 0) {
    const keys = Object.keys(record).slice(0, 3);
    for (const key of keys) {
      parts.push(`${key}=${truncate(compactJson(record[key]), 60)}`);
    }
  }

  return truncate(parts.join('  '), JSON_SUMMARY_MAX);
}

async function readResponseText(response: APIResponse): Promise<string> {
  return response.text().catch(() => '<unreadable body>');
}

function formatExpectedStatus(expected: number | readonly number[]): string {
  if (Array.isArray(expected)) {
    return expected.join(' | ');
  }
  return String(expected);
}

function buildApiFailureMessage(
  method: string,
  path: string,
  actualStatus: number,
  expectedLabel: string,
  bodyText: string,
  options?: ExpectApiOptions
): string {
  const actionLine = options?.action ? `${options.action}\n` : '';
  const summary = summarizeApiBody(bodyText) || truncateBody(bodyText, 400);
  return (
    `${actionLine}` +
    `  ${method} ${path}\n` +
    `  expected status ${expectedLabel}, got ${actualStatus}\n` +
    `  response: ${summary || '(empty)'}`
  );
}

function truncateBody(text: string, max: number): string {
  const oneLine = text.replace(/\s+/g, ' ').trim();
  if (oneLine.length <= max) {
    return oneLine;
  }
  return `${oneLine.slice(0, max)}…`;
}

/**
 * Asserts HTTP status with endpoint and response context in the Playwright error message.
 */
export async function expectApiStatus(
  response: APIResponse,
  expected: number | readonly number[],
  options?: ExpectApiOptions
): Promise<void> {
  const actual = response.status();
  const allowed = Array.isArray(expected) ? expected : [expected];
  const method = options?.method ?? '?';
  const path = extractApiPath(response.url());
  const expectedLabel = formatExpectedStatus(expected);

  if (!allowed.includes(actual)) {
    const bodyText = await readResponseText(response);
    const message = buildApiFailureMessage(method, path, actual, expectedLabel, bodyText, options);
    expect(allowed, message).toContain(actual);
  }
}

/**
 * Asserts 2xx response with endpoint and body context on failure.
 */
export async function expectApiOk(
  response: APIResponse,
  options?: ExpectApiOptions
): Promise<void> {
  if (response.ok()) {
    return;
  }

  const actual = response.status();

  const method = options?.method ?? '?';
  const path = extractApiPath(response.url());
  const bodyText = await readResponseText(response);
  const message = buildApiFailureMessage(method, path, actual, '2xx', bodyText, options);
  expect(response.ok(), message).toBe(true);
}
