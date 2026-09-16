import {
  type ApiListResponse,
  type ApiResponse,
  type CreateCronJobRequest,
  type CronJob,
} from '@/types';
import { SftpApi } from './sftp.api';

export class CronApi extends SftpApi {
  async listCronJobs(username: string): Promise<ApiListResponse<CronJob>> {
    const response = await this.api.get(`projects/${username}/cron-jobs`);
    await this.assertStatus(response, 200);
    return response.json();
  }

  async createCronJob(username: string, data: CreateCronJobRequest): Promise<ApiResponse<CronJob>> {
    const response = await this.api.post(`projects/${username}/cron-jobs`, { data });
    await this.assertStatus(response, 200);
    return response.json();
  }

  async deleteCronJob(username: string, hash: string): Promise<void> {
    const response = await this.api.delete(`projects/${username}/cron-jobs/${hash}`);
    await this.assertOk(response);
  }

  async updateCronJob(
    username: string,
    hash: string,
    data: CreateCronJobRequest
  ): Promise<ApiResponse<CronJob>> {
    const response = await this.api.put(`projects/${username}/cron-jobs/${hash}`, { data });
    await this.assertStatus(response, 200);
    return response.json();
  }
}
