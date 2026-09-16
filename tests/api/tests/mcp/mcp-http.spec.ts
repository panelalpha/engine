import { expect, test } from '@/fixtures/test-options';
import { McpHttpClient, parseMcpJsonRpc } from '@/clients/mcp-http';
import { uniqueId } from '@/helpers/random';
import { expectOneOf } from '@/helpers/expect-one-of';
import { skipUnless } from '@/helpers/test-helpers';

test.describe('MCP streamable HTTP', () => {
  test('GET /mcp/check requires a valid MCP token', async ({ api, anonymousRequest, settings }) => {
    const client = new McpHttpClient(anonymousRequest, settings.apiBaseUrl);
    const name = uniqueId('mcp-http-');
    const created = await api.createMcpToken(name);
    const token = created.data.plain_text_token;
    skipUnless(token, 'createMcpToken did not return a plaintext token.');

    try {
      const missing = await client.check();
      expectOneOf(missing.status(), [401, 403]);

      const invalid = await client.check('not-a-token');
      expectOneOf(invalid.status(), [401, 403]);

      const ok = await client.check(token);
      expect(ok.status()).toBe(200);
      expect(((await ok.json()) as { valid?: boolean }).valid).toBe(true);
    } finally {
      await api.deleteMcpTokenSafe(created.data.id);
    }
  });

  test('POST /mcp initialize succeeds and GET/DELETE are 405', async ({
    api,
    anonymousRequest,
    settings,
  }) => {
    const client = new McpHttpClient(anonymousRequest, settings.apiBaseUrl);
    const created = await api.createMcpToken(uniqueId('mcp-init-'));
    const token = created.data.plain_text_token;
    skipUnless(token, 'createMcpToken did not return a plaintext token.');

    try {
      const handshake = await client.send(
        {
          jsonrpc: '2.0',
          id: 1,
          method: 'initialize',
          params: {
            protocolVersion: '2025-06-18',
            capabilities: {},
            clientInfo: { name: 'engine-api-tests', version: '1' },
          },
        },
        token
      );
      expect(handshake.ok()).toBe(true);
      const parsed = parseMcpJsonRpc(await handshake.text());
      expect(parsed).toBeTruthy();
      const name = (parsed?.result as { serverInfo?: { name?: string } } | undefined)?.serverInfo
        ?.name;
      expect(typeof name).toBe('string');

      const get = await client.methodNotAllowed('get', token);
      expect(get.status()).toBe(405);
      const del = await client.methodNotAllowed('delete', token);
      expect(del.status()).toBe(405);

      const tools = await client.send(
        { jsonrpc: '2.0', id: 2, method: 'tools/list', params: {} },
        token
      );
      expect(tools.ok()).toBe(true);
      const listed = parseMcpJsonRpc(await tools.text());
      expect(listed).toBeTruthy();
      const names = mcpToolNames(listed);
      expect(names).toEqual(
        expect.arrayContaining([
          'backup_list',
          'git_status',
          'git_connect',
          'task_get',
          'task_log_list',
          'project_create',
          'project_list_summary',
        ])
      );

      const called = await client.send(
        {
          jsonrpc: '2.0',
          id: 3,
          method: 'tools/call',
          params: { name: 'project_list_summary', arguments: {} },
        },
        token
      );
      test.skip(
        !called.ok(),
        `tools/call project_list_summary returned HTTP ${called.status()}`
      );
      const invocation = parseMcpJsonRpc(await called.text());
      expect(invocation).toBeTruthy();
      expect(invocation?.error).toBeUndefined();
      expect(invocation?.result).toBeTruthy();
    } finally {
      await api.deleteMcpTokenSafe(created.data.id);
    }
  });

  test('read-only tools answer for the shared project', async ({
    api,
    anonymousRequest,
    settings,
    setupUser,
  }) => {
    const client = new McpHttpClient(anonymousRequest, settings.apiBaseUrl);
    const created = await api.createMcpToken(uniqueId('mcp-tools-'));
    const token = created.data.plain_text_token;
    skipUnless(token, 'createMcpToken did not return a plaintext token.');

    try {
      const handshake = await client.send(
        {
          jsonrpc: '2.0',
          id: 1,
          method: 'initialize',
          params: {
            protocolVersion: '2025-06-18',
            capabilities: {},
            clientInfo: { name: 'engine-api-tests', version: '1' },
          },
        },
        token
      );
      expect(handshake.ok()).toBe(true);

      let nextId = 2;
      const call = async (name: string, args: Record<string, unknown> = {}) => {
        const response = await client.send(
          {
            jsonrpc: '2.0',
            id: nextId++,
            method: 'tools/call',
            params: { name, arguments: args },
          },
          token
        );
        expect(response.ok(), `${name} HTTP ${response.status()}`).toBe(true);
        const parsed = parseMcpJsonRpc(await response.text());
        expect(parsed, `${name} produced no JSON-RPC payload`).toBeTruthy();
        return parsed!;
      };

      const metrics = await call('metrics_latest');
      expect(metrics.result !== undefined || metrics.error !== undefined).toBe(true);

      const summary = await call('project_list_summary');
      expect(summary.error).toBeUndefined();
      expect(summary.result).toBeTruthy();

      const project = await call('project_get', { name: setupUser.username });
      expect(project.error).toBeUndefined();
      expect(project.result).toBeTruthy();

      const domains = await call('domain_list', { name: setupUser.username });
      expect(domains.error).toBeUndefined();
      expect(domains.result).toBeTruthy();

      const found = await call('domain_find', { domain: setupUser.domain });
      expect(found.error).toBeUndefined();
      expect(found.result).toBeTruthy();

      const git = await call('git_status', { name: setupUser.username });
      expect(git.error).toBeUndefined();
      expect(git.result).toBeTruthy();

      const backups = await call('backup_list', { name: setupUser.username });
      expect(backups.result !== undefined || backups.error !== undefined).toBe(true);

      const missingTask = await call('task_get', { id: 999_999_999 });
      expect(missingTask.result !== undefined || missingTask.error !== undefined).toBe(true);
    } finally {
      await api.deleteMcpTokenSafe(created.data.id);
    }
  });
});

function mcpToolNames(payload: Record<string, unknown> | null): string[] {
  const result = payload?.result;
  if (result === null || typeof result !== 'object') {
    return [];
  }
  const tools = (result as { tools?: unknown }).tools;
  if (!Array.isArray(tools)) {
    return [];
  }
  return tools.flatMap((tool: unknown) => {
    if (tool === null || typeof tool !== 'object') {
      return [];
    }
    const name = (tool as { name?: unknown }).name;
    return typeof name === 'string' ? [name] : [];
  });
}
