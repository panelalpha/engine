import { expect } from '@playwright/test';

/**
 * Asserts that `actual` is one of `allowed`.
 *
 * On failure, Playwright reports the real value as "Received" (not the allowed list),
 * unlike `expect(allowed).toContain(actual)`.
 */
export function expectOneOf<T>(actual: T, allowed: readonly T[], message?: string): void {
  const pass = allowed.includes(actual);
  const label = message ?? `expected ${String(actual)} to be one of ${allowed.join(', ')}`;
  expect(actual, label).toBe(pass ? actual : (`one of: ${allowed.join(', ')}` as T & string));
}

export function expectNotOneOf<T>(actual: T, disallowed: readonly T[], message?: string): void {
  const pass = !disallowed.includes(actual);
  const label = message ?? `expected ${String(actual)} not to be one of ${disallowed.join(', ')}`;
  expect(actual, label).toBe(
    pass ? actual : (`not one of: ${disallowed.join(', ')}` as T & string)
  );
}

/** HTTP status variant of {@link expectOneOf}. */
export function expectStatusIn(status: number, allowed: readonly number[], message?: string): void {
  expectOneOf(status, allowed, message);
}
