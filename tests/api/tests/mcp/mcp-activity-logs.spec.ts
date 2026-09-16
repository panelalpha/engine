import { expect, test } from '@/fixtures/test-options';
import { rand } from '@/helpers/random';

test.describe('MCP activity logs', () => {
  test('the activity log listing is paginated', async ({ api }) => {
    const page = await api.listMcpActivityLogs();
    expect(Array.isArray(page.data)).toBe(true);
  });

  test('an activity log entry can be created and filtered by token name', async ({ api }) => {
    const tokenName = rand('mcplog');
    const created = await api.createMcpActivityLog({
      token_name: tokenName,
      tool_name: 'ping',
      status: 'success',
      input: { hello: 'world' },
    });

    expect(created.data.token_name).toBe(tokenName);
    expect(created.data.tool_name).toBe('ping');
    expect(created.data.status).toBe('success');

    const filtered = await api.listMcpActivityLogs({ tokenName });
    expect(filtered.data.some((entry) => entry.id === created.data.id)).toBe(true);
  });

  test('an incomplete activity log payload is rejected', async ({ api }) => {
    const response = await api.createMcpActivityLogRaw({
      token_name: '',
      tool_name: '',
      status: 'success',
    });
    expect([400, 422]).toContain(response.status);
  });
});
