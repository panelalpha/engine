/* eslint-disable @typescript-eslint/no-explicit-any */

import {
  type ApiListResponse,
  type ApiResponse,
  type BackupContainer,
  type BackupRecord,
  type CreateBackupContainerRequest,
  type CreateProjectBackupRequest,
  type RestoreBackupRequest,
  type UpdateBackupContainerRequest,
} from '@/types';
import { EngineApiBase } from '../engine-api-base';

export class BackupsApi extends EngineApiBase {
  async listBackupContainers(): Promise<ApiListResponse<BackupContainer>> {
    const response = await this.api.get('backup-containers');
    await this.assertStatus(response, 200);
    return response.json();
  }

  async getBackupContainer(id: number): Promise<ApiResponse<BackupContainer>> {
    const response = await this.api.get(`backup-containers/${id}`);
    await this.assertStatus(response, 200);
    return response.json();
  }

  async getBackupContainerRaw(id: number): Promise<{ status: number; body: any }> {
    const response = await this.api.get(`backup-containers/${id}`);
    return this.rawCall(response);
  }

  async createBackupContainer(
    data: CreateBackupContainerRequest
  ): Promise<ApiResponse<BackupContainer>> {
    const response = await this.api.post('backup-containers', { data });
    await this.assertStatus(response, [200, 201]);
    return response.json();
  }

  async createBackupContainerRaw(
    data: Partial<CreateBackupContainerRequest>
  ): Promise<{ status: number; body: any }> {
    const response = await this.api.post('backup-containers', { data });
    return this.rawCall(response);
  }

  async updateBackupContainer(
    id: number,
    data: UpdateBackupContainerRequest
  ): Promise<ApiResponse<BackupContainer>> {
    const response = await this.api.put(`backup-containers/${id}`, { data });
    await this.assertStatus(response, 200);
    return response.json();
  }

  async deleteBackupContainer(
    id: number,
    options: { deleteBackups?: boolean } = {}
  ): Promise<{ status: number; body: any }> {
    const suffix = options.deleteBackups === true ? '?delete_backups=1' : '';
    const response = await this.api.delete(`backup-containers/${id}${suffix}`);
    return this.rawCall(response);
  }

  async deleteBackupContainerSafe(id: number): Promise<number> {
    const withBackups = await this.deleteBackupContainer(id, { deleteBackups: true });
    if ([200, 202, 404].includes(withBackups.status)) {
      return withBackups.status;
    }
    return (await this.deleteBackupContainer(id)).status;
  }

  async testBackupContainer(id: number): Promise<ApiResponse<{ ok: boolean }>> {
    const response = await this.api.post(`backup-containers/${id}/test`);
    await this.assertStatus(response, 200);
    return response.json();
  }

  async testBackupContainerRaw(id: number): Promise<{ status: number; body: any }> {
    const response = await this.api.post(`backup-containers/${id}/test`);
    return this.rawCall(response);
  }

  async listProjectBackups(username: string): Promise<ApiListResponse<BackupRecord>> {
    const response = await this.api.get(`projects/${username}/backups`);
    await this.assertStatus(response, 200);
    return response.json();
  }

  async listProjectBackupsRaw(username: string): Promise<{ status: number; body: any }> {
    const response = await this.api.get(`projects/${username}/backups`);
    return this.rawCall(response);
  }

  async getProjectBackup(username: string, id: number): Promise<ApiResponse<BackupRecord>> {
    const response = await this.api.get(`projects/${username}/backups/${id}`);
    await this.assertStatus(response, 200);
    return response.json();
  }

  async getProjectBackupRaw(username: string, id: number): Promise<{ status: number; body: any }> {
    const response = await this.api.get(`projects/${username}/backups/${id}`);
    return this.rawCall(response);
  }

  async createProjectBackup(
    username: string,
    data: CreateProjectBackupRequest
  ): Promise<ApiResponse<BackupRecord>> {
    const response = await this.api.post(`projects/${username}/backups`, { data });
    await this.assertStatus(response, 202);
    return response.json();
  }

  async createProjectBackupRaw(
    username: string,
    data: Partial<CreateProjectBackupRequest>
  ): Promise<{ status: number; body: any }> {
    const response = await this.api.post(`projects/${username}/backups`, { data });
    return this.rawCall(response);
  }

  async restoreProjectBackup(
    username: string,
    id: number,
    data: RestoreBackupRequest
  ): Promise<ApiResponse<BackupRecord>> {
    const response = await this.api.post(`projects/${username}/backups/${id}/restore`, { data });
    await this.assertStatus(response, 202);
    return response.json();
  }

  async restoreProjectBackupRaw(
    username: string,
    id: number,
    data: Partial<RestoreBackupRequest>
  ): Promise<{ status: number; body: any }> {
    const response = await this.api.post(`projects/${username}/backups/${id}/restore`, { data });
    return this.rawCall(response);
  }

  async deleteProjectBackup(username: string, id: number): Promise<ApiResponse<BackupRecord>> {
    const response = await this.api.delete(`projects/${username}/backups/${id}`);
    await this.assertStatus(response, 202);
    return response.json();
  }

  async deleteProjectBackupRaw(
    username: string,
    id: number
  ): Promise<{ status: number; body: any }> {
    const response = await this.api.delete(`projects/${username}/backups/${id}`);
    return this.rawCall(response);
  }
}
