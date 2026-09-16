export interface WaitForConditionOptions {
  timeout?: number;
  interval?: number;
  message?: string;
  /** Included in timeout error when the condition never becomes true. */
  describeLast?: () => string | Promise<string>;
}

/**
 * Waits for a condition to be true.
 *
 * @param condition - Async function that returns true when condition is met
 * @param options - Wait options
 */
export async function waitForCondition(
  condition: () => Promise<boolean>,
  options: WaitForConditionOptions = {}
): Promise<void> {
  const {
    timeout = 30000,
    interval = 1000,
    message = 'Condition not met within timeout',
    describeLast,
  } = options;

  const startTime = Date.now();
  let attempts = 0;

  while (Date.now() - startTime < timeout) {
    attempts += 1;
    if (await condition()) {
      return;
    }
    await new Promise((resolve) => setTimeout(resolve, interval));
  }

  const elapsedSec = Math.round((Date.now() - startTime) / 1000);
  const lastState = describeLast ? await describeLast() : undefined;
  const detail = lastState ? ` Last state: ${lastState}.` : '';
  throw new Error(
    `${message} (${attempts} attempts over ${elapsedSec}s, timeout ${timeout}ms).${detail}`
  );
}

/**
 * Delays execution for specified milliseconds
 *
 * @param ms - Milliseconds to wait
 */
export function delay(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
