/* eslint-disable @typescript-eslint/no-explicit-any */

import { type ApiResponse, type TaskLogPage, type TaskSnapshot } from '@/types';
import { EngineApiBase } from '../engine-api-base';

function taskLogQuery(params?: { since?: string | number; after_id?: number }): string {
  const query = new URLSearchParams();
  if (params?.since !== undefined) {
    query.set('since', String(params.since));
  }
  if (params?.after_id !== undefined) {
    query.set('after_id', String(params.after_id));
  }
  const suffix = query.toString();
  return suffix.length > 0 ? `?${suffix}` : '';
}

export class TasksApi extends EngineApiBase {
  async getTask(id: number, afterId?: number): Promise<ApiResponse<TaskSnapshot>> {
    const suffix = afterId === undefined ? '' : `?after_id=${afterId}`;
    const response = await this.api.get(`tasks/${id}${suffix}`);
    await this.assertStatus(response, 200);
    return response.json();
  }

  async getTaskRaw(id: number, afterId?: number): Promise<{ status: number; body: any }> {
    const suffix = afterId === undefined ? '' : `?after_id=${afterId}`;
    const response = await this.api.get(`tasks/${id}${suffix}`);
    return this.rawCall(response);
  }

  async listTaskLogs(
    id: number,
    params?: { since?: string | number; after_id?: number }
  ): Promise<TaskLogPage> {
    const response = await this.api.get(`tasks/${id}/logs${taskLogQuery(params)}`);
    await this.assertStatus(response, 200);
    return response.json();
  }

  async listTaskLogsRaw(
    id: number,
    params?: { since?: string | number; after_id?: number }
  ): Promise<{ status: number; body: any }> {
    const response = await this.api.get(`tasks/${id}/logs${taskLogQuery(params)}`);
    return this.rawCall(response);
  }

  async streamTaskLogsRaw(
    id: number,
    params?: { since?: string | number; after_id?: number }
  ): Promise<{ status: number; body: string }> {
    const response = await this.api.get(`tasks/${id}/logs/stream${taskLogQuery(params)}`);
    return { status: response.status(), body: await response.text() };
  }

  async cancelTask(id: number): Promise<ApiResponse<{ cancelled: boolean }>> {
    const response = await this.api.post(`tasks/${id}/cancel`);
    await this.assertStatus(response, 200);
    return response.json();
  }

  async cancelTaskRaw(id: number): Promise<{ status: number; body: any }> {
    const response = await this.api.post(`tasks/${id}/cancel`);
    return this.rawCall(response);
  }
}
