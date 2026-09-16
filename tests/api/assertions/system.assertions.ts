import { expect } from '@playwright/test';
import { type EngineApi } from '@/clients/engine-api';

export class SystemAssertions {
  constructor(private api: EngineApi) {}

  async verifySystemInfo(): Promise<void> {
    const response = await this.api.getSystemInfo();
    expect(response.data).toBeTruthy();
    expect(response.data).toHaveProperty('webserver');
    expect(response.data).toHaveProperty('version');
  }

  async verifyPhpVersionsAvailable(): Promise<void> {
    const response = await this.api.getAvailablePhpVersions();
    expect(response.data).toBeTruthy();
    expect(Array.isArray(response.data)).toBe(true);
    expect(response.data.length).toBeGreaterThan(0);
  }

  async verifyPhpVersionAvailable(version: string): Promise<void> {
    const response = await this.api.getAvailablePhpVersions();
    expect(response.data).toContain(version);
  }

  async verifyDatabaseExists(username: string, database: string): Promise<void> {
    const response = await this.api.listMySqlDatabases(username);
    const exists = response.data.some((db) => db.database === database);
    expect(exists).toBe(true);
  }

  async verifyDatabaseNotExists(username: string, database: string): Promise<void> {
    const response = await this.api.listMySqlDatabases(username);
    const exists = response.data.some((db) => db.database === database);
    expect(exists).toBe(false);
  }

  async verifyMySqlUserExists(username: string, mysqlUser: string): Promise<void> {
    const response = await this.api.listMySqlUsers(username);
    const exists = response.data.some((u) => u.user === mysqlUser);
    expect(exists).toBe(true);
  }

  async verifyFtpAccountExists(username: string, ftpUsername: string): Promise<void> {
    const response = await this.api.listFtpAccounts(username);
    const exists = response.data.some((acc) => acc.user === ftpUsername);
    expect(exists).toBe(true);
  }

  async verifyFtpAccountNotExists(username: string, ftpUsername: string): Promise<void> {
    const response = await this.api.listFtpAccounts(username);
    const exists = response.data.some((acc) => acc.user === ftpUsername);
    expect(exists).toBe(false);
  }

  async verifySftpAccountExists(username: string, sftpUsername: string): Promise<void> {
    const response = await this.api.listSftpAccounts(username);
    const exists = response.data.some((acc) => acc.username === sftpUsername);
    expect(exists).toBe(true);
  }

  async verifyCronJobExists(username: string, hash: string): Promise<void> {
    const response = await this.api.listCronJobs(username);
    const exists = response.data.some((cron) => cron.hash === hash);
    expect(exists).toBe(true);
  }

  async verifyCronJobNotExists(username: string, hash: string): Promise<void> {
    const response = await this.api.listCronJobs(username);
    const exists = response.data.some((cron) => cron.hash === hash);
    expect(exists).toBe(false);
  }

  async verifyUserUsage(username: string): Promise<void> {
    const response = await this.api.getUserUsage(username);
    expect(response).toBeTruthy();
    expect(typeof response).toBe('object');
    expect(response.storage).toBeDefined();
  }
}
