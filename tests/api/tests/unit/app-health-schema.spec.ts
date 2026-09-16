import { expect, test } from '@/fixtures/test-options';
import { appHealthSchema, SERVING_WORDS } from '@/schemas';

test.describe('app health schema', () => {
  const passing = {
    healthy: true,
    serving: 'ok',
    ports: [{ port: 80, status: 'ok', http_code: 200 }],
    checks: [
      {
        id: 'entry-served',
        group: '_baseline',
        status: 'pass',
        severity: 'error',
        title: null,
        detail: null,
        fix: null,
        evidence: null,
      },
    ],
  };

  test('accepts a report that names a known serving word', () => {
    expect(appHealthSchema.parse(passing).serving).toBe('ok');
  });

  test('rejects a serving word the engine does not use', () => {
    expect(() => appHealthSchema.parse({ ...passing, serving: 'fine' })).toThrow();
  });

  test('the vocabulary includes missing_entry and unknown', () => {
    expect(SERVING_WORDS).toContain('missing_entry');
    expect(SERVING_WORDS).toContain('unknown');
    expect(SERVING_WORDS).toContain('ok');
  });
});
