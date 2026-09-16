/* eslint-disable @typescript-eslint/no-explicit-any */

import {
  type ApiListResponse,
  type ApiResponse,
  type CreateMcpActivityLogRequest,
  type McpActivityLog,
  type McpToken,
} from '@/types';
import { EngineApiBase } from '../engine-api-base';

export class McpApi extends EngineApiBase {
  async listMcpTokens(): Promise<ApiListResponse<McpToken>> {
    const response = await this.api.get('mcp-tokens');
    await this.assertStatus(response, 200);
    return response.json();
  }

  async createMcpToken(name: string): Promise<ApiResponse<McpToken>> {
    const response = await this.api.post('mcp-tokens', { data: { name } });
    await this.assertStatus(response, 201);
    return response.json();
  }

  async createMcpTokenRaw(name: string): Promise<{ status: number; body: any }> {
    const response = await this.api.post('mcp-tokens', { data: { name } });
    return this.rawCall(response);
  }

  async revokeMcpToken(id: number): Promise<ApiResponse<McpToken>> {
    const response = await this.api.put(`mcp-tokens/${id}/revoke`);
    await this.assertStatus(response, 200);
    return response.json();
  }

  async deleteMcpToken(id: number): Promise<ApiResponse<{ id: number }>> {
    const response = await this.api.delete(`mcp-tokens/${id}`);
    await this.assertStatus(response, 200);
    return response.json();
  }

  async deleteMcpTokenSafe(id: number): Promise<number> {
    const response = await this.api.delete(`mcp-tokens/${id}`);
    return response.status();
  }

  async listMcpActivityLogs(options: { tokenName?: string; page?: number } = {}): Promise<{
    data: McpActivityLog[];
    [key: string]: unknown;
  }> {
    const params = new URLSearchParams();
    if (options.tokenName) {
      params.set('token_name', options.tokenName);
    }
    if (options.page !== undefined) {
      params.set('page', String(options.page));
    }
    const query = params.toString();
    const response = await this.api.get(query ? `mcp-activity-logs?${query}` : 'mcp-activity-logs');
    await this.assertStatus(response, 200);
    return response.json();
  }

  async createMcpActivityLog(
    data: CreateMcpActivityLogRequest
  ): Promise<ApiResponse<McpActivityLog>> {
    const response = await this.api.post('mcp-activity-logs', { data });
    await this.assertStatus(response, 201);
    return response.json();
  }

  async createMcpActivityLogRaw(
    data: CreateMcpActivityLogRequest
  ): Promise<{ status: number; body: any }> {
    const response = await this.api.post('mcp-activity-logs', { data });
    return this.rawCall(response);
  }
}
