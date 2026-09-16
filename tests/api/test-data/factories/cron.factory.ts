import { faker } from '@faker-js/faker';
import { type EngineApi } from '@/clients/engine-api';
import { type CreateCronJobRequest, type CronJob } from '@/types';

export class CronFactory {
  constructor(private api: EngineApi) {}

  async createMinuteCron(username: string, command: string): Promise<CronJob> {
    const response = await this.api.createCronJob(username, {
      minute: '*',
      hour: '*',
      day_of_month: '*',
      month: '*',
      day_of_week: '*',
      command,
    });
    return response.data;
  }

  async createCronJob(
    username: string,
    schedule: Partial<CreateCronJobRequest>,
    command: string
  ): Promise<CronJob> {
    const response = await this.api.createCronJob(username, {
      minute: schedule.minute ?? '0',
      hour: schedule.hour ?? '*',
      day_of_month: schedule.day_of_month ?? '*',
      month: schedule.month ?? '*',
      day_of_week: schedule.day_of_week ?? '*',
      command,
    });
    return response.data;
  }

  async createUidCaptureCron(username: string, outputFile: string): Promise<CronJob> {
    const command = `echo "UID: $(id -u)" > ${outputFile}; echo "GID: $(id -g)" >> ${outputFile}; echo "User: $(whoami)" >> ${outputFile}`;
    return this.createMinuteCron(username, command);
  }

  async deleteCronJob(username: string, hash: string): Promise<void> {
    // Cleanup helper: the job may already be gone (test deleted it, or an
    // update replaced the hash). 404 is success; anything else is worth a note.
    const response = await this.api.delete(`projects/${username}/cron-jobs/${hash}`);
    const status = response.status();
    if (status !== 200 && status !== 204 && status !== 404) {
      const body = await response.text().catch(() => '<unreadable body>');
      console.warn(`Failed to delete cron job ${hash}: HTTP ${status} ${body}`);
    }
  }

  async getCronJobs(username: string): Promise<CronJob[]> {
    const response = await this.api.listCronJobs(username);
    return response.data;
  }

  static generateHash(): string {
    return faker.string.alphanumeric(16);
  }
}
