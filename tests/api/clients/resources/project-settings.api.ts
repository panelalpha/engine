/* eslint-disable @typescript-eslint/no-explicit-any */

import {
  type ApiListResponse,
  type ApiResponse,
  type ProjectSetting,
  type SetProjectSettingRequest,
  type SetProjectSettingResult,
} from '@/types';
import { EngineApiBase } from '../engine-api-base';

export class ProjectSettingsApi extends EngineApiBase {
  async listProjectSettings(username: string): Promise<ApiListResponse<ProjectSetting>> {
    const response = await this.api.get(`projects/${username}/settings`);
    await this.assertStatus(response, 200);
    return response.json();
  }

  async listProjectSettingsRaw(username: string): Promise<{ status: number; body: any }> {
    const response = await this.api.get(`projects/${username}/settings`);
    return this.rawCall(response);
  }

  async getProjectSetting(username: string, key: string): Promise<ApiResponse<ProjectSetting>> {
    const response = await this.api.get(`projects/${username}/settings/${key}`);
    await this.assertStatus(response, 200);
    return response.json();
  }

  async getProjectSettingRaw(
    username: string,
    key: string
  ): Promise<{ status: number; body: any }> {
    const response = await this.api.get(`projects/${username}/settings/${key}`);
    return this.rawCall(response);
  }

  async setProjectSetting(
    username: string,
    key: string,
    data: SetProjectSettingRequest
  ): Promise<ApiResponse<SetProjectSettingResult>> {
    const response = await this.api.put(`projects/${username}/settings/${key}`, { data });
    await this.assertStatus(response, 200);
    return response.json();
  }

  async setProjectSettingRaw(
    username: string,
    key: string,
    data: Partial<SetProjectSettingRequest>
  ): Promise<{ status: number; body: any }> {
    const response = await this.api.put(`projects/${username}/settings/${key}`, { data });
    return this.rawCall(response);
  }

  async deleteProjectSetting(
    username: string,
    key: string,
    options: { force?: boolean } = {}
  ): Promise<{ success: boolean }> {
    const suffix = options.force === true ? '?force=1' : '';
    const response = await this.api.delete(`projects/${username}/settings/${key}${suffix}`);
    await this.assertStatus(response, 200);
    return response.json();
  }

  async deleteProjectSettingRaw(
    username: string,
    key: string,
    options: { force?: boolean } = {}
  ): Promise<{ status: number; body: any }> {
    const suffix = options.force === true ? '?force=1' : '';
    const response = await this.api.delete(`projects/${username}/settings/${key}${suffix}`);
    return this.rawCall(response);
  }
}
