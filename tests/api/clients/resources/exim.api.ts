/* eslint-disable @typescript-eslint/no-explicit-any */

import { type ApiResponse, type EximConfig, type EximTestEmailRequest } from '@/types';
import { UsersApi } from './users.api';

export class EximApi extends UsersApi {
  async getEximConfig(): Promise<ApiResponse<EximConfig>> {
    const response = await this.api.get('system/exim-config');
    await this.assertStatus(response, 200);
    return response.json();
  }

  async updateEximConfig(data: Partial<EximConfig>): Promise<ApiResponse<EximConfig>> {
    const response = await this.api.put('system/exim-config', { data });
    await this.assertStatus(response, [200, 204]);
    return response.json().catch(() => ({ data: data }));
  }

  async updateEximConfigRaw(
    data: Partial<EximConfig> | null
  ): Promise<{ status: number; body: any }> {
    const response = await this.api.put('system/exim-config', { data });
    return this.rawCall(response);
  }

  async sendEximTestEmail(data: EximTestEmailRequest): Promise<{ status: number; body: any }> {
    const response = await this.api.post('system/exim-send-test-email', { data });
    await this.assertStatus(response, [200, 204, 400, 422, 500, 502, 503]);
    return this.rawCall(response);
  }

  async sendEximTestEmailRaw(
    data: Partial<EximTestEmailRequest>
  ): Promise<{ status: number; body: any }> {
    const response = await this.api.post('system/exim-send-test-email', { data });
    return this.rawCall(response);
  }
}
