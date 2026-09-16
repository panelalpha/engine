/* eslint-disable @typescript-eslint/no-explicit-any */

import {
  type ApiListResponse,
  type ApiResponse,
  type CreateProxyRuleRequest,
  type ProxyRule,
  type UpdateProxyRuleRequest,
} from '@/types';
import { EngineApiBase } from '../engine-api-base';

export class ProxyRulesApi extends EngineApiBase {
  async listProxyRules(): Promise<ApiListResponse<ProxyRule>> {
    const response = await this.api.get('proxy-rules');
    await this.assertStatus(response, 200);
    return response.json();
  }

  async listProxyRulesRaw(): Promise<{ status: number; body: any }> {
    const response = await this.api.get('proxy-rules');
    return this.rawCall(response);
  }

  async getProxyRule(id: number): Promise<ApiResponse<ProxyRule>> {
    const response = await this.api.get(`proxy-rules/${id}`);
    await this.assertStatus(response, 200);
    return response.json();
  }

  async getProxyRuleRaw(id: number): Promise<{ status: number; body: any }> {
    const response = await this.api.get(`proxy-rules/${id}`);
    return this.rawCall(response);
  }

  async createProxyRule(data: CreateProxyRuleRequest): Promise<ApiResponse<ProxyRule>> {
    const response = await this.api.post('proxy-rules', { data });
    await this.assertStatus(response, [200, 201]);
    return response.json();
  }

  async createProxyRuleRaw(data: CreateProxyRuleRequest): Promise<{ status: number; body: any }> {
    const response = await this.api.post('proxy-rules', { data });
    return this.rawCall(response);
  }

  async updateProxyRule(id: number, data: UpdateProxyRuleRequest): Promise<ApiResponse<ProxyRule>> {
    const response = await this.api.put(`proxy-rules/${id}`, { data });
    await this.assertStatus(response, 200);
    return response.json();
  }

  async deleteProxyRule(id: number): Promise<ApiResponse<ProxyRule>> {
    const response = await this.api.delete(`proxy-rules/${id}`);
    await this.assertStatus(response, 200);
    return response.json();
  }

  async deleteProxyRuleSafe(id: number): Promise<number> {
    const response = await this.api.delete(`proxy-rules/${id}`);
    return response.status();
  }
}
