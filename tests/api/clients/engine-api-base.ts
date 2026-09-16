import { type APIResponse } from '@playwright/test';
import { type ApiTransport } from './api-transport';

export class EngineApiBase {
  constructor(protected api: ApiTransport) {}

  /**
   * Asserts that the response status matches one of the expected codes.
   * On mismatch, throws an error that includes the URL, actual status, and
   * response body — making failures self-explanatory without opening HTML reports.
   */
  protected async assertStatus(response: APIResponse, expected: number | number[]): Promise<void> {
    const actual = response.status();
    const expectedArr = Array.isArray(expected) ? expected : [expected];
    if (!expectedArr.includes(actual)) {
      const url = response.url();
      const body = await response.text().catch(() => '<unreadable body>');
      const expectedStr =
        expectedArr.length === 1 ? String(expectedArr[0]) : expectedArr.join(' | ');
      throw new Error(
        `API call failed\n` +
          `  URL:      ${url}\n` +
          `  Expected: ${expectedStr}  —  Got: ${actual}\n` +
          `  Response: ${body}`
      );
    }
  }

  /**
   * Asserts that the response is 2xx (ok).
   * On failure, throws an error with URL, status, and response body.
   */
  protected async assertOk(response: APIResponse): Promise<void> {
    if (!response.ok()) {
      const url = response.url();
      const status = response.status();
      const body = await response.text().catch(() => '<unreadable body>');
      throw new Error(
        `API call failed\n` +
          `  URL:      ${url}\n` +
          `  Expected: 2xx  —  Got: ${status}\n` +
          `  Response: ${body}`
      );
    }
  }

  protected async parseJsonBody(response: APIResponse): Promise<unknown> {
    return response.json().catch(() => ({}));
  }

  protected isContainerRestartingError(body: unknown): boolean {
    const messageCandidate =
      typeof body === 'object' && body !== null
        ? (body as { message?: unknown }).message
        : typeof body === 'string'
          ? body
          : undefined;
    const message = typeof messageCandidate === 'string' ? messageCandidate : '';
    return /container.+restarting|wait until the container is running/i.test(message);
  }

  /**
   * Returns the raw status and JSON body of a response without asserting status.
   * Used internally by all *Raw methods to eliminate boilerplate.
   */
  protected async rawCall(response: APIResponse): Promise<{ status: number; body: unknown }> {
    const body: unknown = await response.json().catch(() => ({}));
    return { status: response.status(), body };
  }
}
