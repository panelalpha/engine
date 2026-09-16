import { expect, test } from '@/fixtures/test-options';
import type { EngineApi } from '@/clients/engine-api';
import { expectOneOf } from '@/helpers/expect-one-of';
import { currentMetricsResponseSchema, historicalMetricsResponseSchema } from '@/schemas';

/** Each window has its own endpoint but the same response contract. */
const WINDOWS = [
  ['current', (api: EngineApi) => api.getCurrentMetrics(), currentMetricsResponseSchema],
  [
    'the last 5 minutes',
    (api: EngineApi) => api.getLast5MinutesMetrics(),
    historicalMetricsResponseSchema,
  ],
  ['the last hour', (api: EngineApi) => api.getLastHourMetrics(), historicalMetricsResponseSchema],
  [
    'the last 12 hours',
    (api: EngineApi) => api.getLast12HoursMetrics(),
    historicalMetricsResponseSchema,
  ],
] as const;

test.describe('system metrics', () => {
  for (const [label, fetchMetrics, schema] of WINDOWS) {
    test(`metrics for ${label} match their schema`, async ({ api }) => {
      const metrics = await fetchMetrics(api);

      expect(metrics.data).toBeDefined();

      const parsed = schema.safeParse(metrics);
      expect(parsed.success, parsed.error?.message).toBe(true);
    });
  }

  test('the last hour averages are non-negative numbers', async ({ api }) => {
    const { data } = await api.getLastHourAverages();

    expect(typeof data.avg_cpu_percent).toBe('number');
    expect(typeof data.avg_ram_percent).toBe('number');
    expect(data.avg_cpu_percent).toBeGreaterThanOrEqual(0);
    expect(data.avg_ram_percent).toBeGreaterThanOrEqual(0);
  });

  const malformedQueries = [
    'metrics/current?from=not-a-timestamp&to=also-bad',
    'metrics/last-hour?bucket_seconds=-1',
    'metrics/last-5-minutes?bucket_seconds=abc',
  ];

  for (const query of malformedQueries) {
    test(`${query} answers deterministically`, async ({ authedRequest }) => {
      const response = await authedRequest.get(query);
      const status = response.status();

      // Ignoring a bad parameter and rejecting it are both defensible; crashing
      // or hanging is not.
      expectOneOf(status, [200, 400, 422]);

      if (status !== 200) {
        expect((await response.text()).toLowerCase()).toMatch(
          /from|to|bucket|metrics|invalid|validation/
        );
      }
    });
  }
});
