import { faker } from '@faker-js/faker';
import { type EngineApi } from '@/clients/engine-api';
import { type Domain } from '@/types';

export class DomainFactory {
  constructor(private api: EngineApi) {}

  async createAddonDomain(username: string, baseDomain: string): Promise<string> {
    const prefix = faker.string.alphanumeric(8).toLowerCase();
    const addonDomain = `${prefix}.${baseDomain}`;

    await this.api.createDomain(username, {
      domain: addonDomain,
      type: 'addon',
    });

    return addonDomain;
  }

  async createMultipleAddonDomains(
    username: string,
    baseDomain: string,
    count: number
  ): Promise<string[]> {
    const domains: string[] = [];

    for (let i = 0; i < count; i++) {
      const domain = await this.createAddonDomain(username, baseDomain);
      domains.push(domain);
    }

    return domains;
  }

  async deleteDomain(username: string, domain: string): Promise<void> {
    try {
      await this.api.deleteDomain(username, domain);
    } catch (error) {
      console.warn(`Failed to delete domain ${domain}:`, error);
    }
  }

  async domainExists(username: string, domain: string): Promise<boolean> {
    try {
      const response = await this.api.listUserDomains(username);
      return response.data.some((d) => d.domain === domain);
    } catch {
      return false;
    }
  }

  async getUserDomains(username: string): Promise<Domain[]> {
    const response = await this.api.listUserDomains(username);
    return response.data;
  }
}
