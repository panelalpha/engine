/* eslint-disable @typescript-eslint/require-await */

import { expect } from '@playwright/test';
import { type EngineApi } from '@/clients/engine-api';
import { type Domain } from '@/types';

export class DomainAssertions {
  constructor(private api: EngineApi) {}

  async verifyDomainExists(username: string, domain: string): Promise<void> {
    const response = await this.api.listUserDomains(username);
    const exists = response.data.some((d) => d.domain === domain);
    expect(exists).toBe(true);
  }

  async verifyDomainNotExists(username: string, domain: string): Promise<void> {
    const response = await this.api.listUserDomains(username);
    const exists = response.data.some((d) => d.domain === domain);
    expect(exists).toBe(false);
  }

  async verifyDomainStructure(
    domainData: Domain,
    expectedDomain: string,
    expectedType: 'main' | 'addon' | 'alias' = 'addon'
  ): Promise<void> {
    expect(domainData).toBeTruthy();
    expect(typeof domainData).toBe('object');

    expect(domainData).toHaveProperty('domain');
    expect(domainData.domain).toBe(expectedDomain);

    expect(domainData).toHaveProperty('type');
    expect(domainData.type).toBe(expectedType);

    if (domainData.details) {
      expect(domainData.details).toHaveProperty('document_root');
      expect(typeof domainData.details.document_root).toBe('string');
    }
  }

  async verifyWwwAliasExists(username: string, domain: string): Promise<void> {
    const response = await this.api.listUserDomains(username);
    const domainData = response.data.find((d) => d.domain === domain);

    expect(domainData).toBeTruthy();

    if (domainData?.details?.aliases) {
      const wwwAlias = `www.${domain}`;
      expect(domainData.details.aliases).toContain(wwwAlias);
    }
  }

  async verifyDomainCount(username: string, expectedMinCount: number): Promise<void> {
    const response = await this.api.listUserDomains(username);
    expect(response.data.length).toBeGreaterThanOrEqual(expectedMinCount);
  }

  async verifySslInstalled(username: string, domain: string): Promise<void> {
    const response = await this.api.listSslCertificates(username);
    const hasSsl = response.data.some(
      (cert) => cert.common_name === domain || cert.domains?.includes(domain)
    );
    expect(hasSsl).toBe(true);
  }

  async verifyLogFilesExist(username: string, domain: string): Promise<void> {
    const response = await this.api.listDomainLogFiles(username, domain);
    expect(response.data).toBeTruthy();
    expect(Array.isArray(response.data)).toBe(true);
  }

  async verifyPhpVersion(domain: string, expectedVersion: string): Promise<void> {
    const response = await this.api.getDomainPhpVersion(domain);
    expect(response.data).toBe(expectedVersion);
  }

  async verifyPhpVersionFormat(domain: string): Promise<void> {
    const response = await this.api.getDomainPhpVersion(domain);
    expect(response.data).toBeTruthy();
    expect(typeof response.data).toBe('string');
    expect(response.data).toMatch(/^\d+\.\d+$/);
  }

  async verifyDomainConfig(
    username: string,
    domain: string,
    expectedConfig: Partial<{
      document_root: string;
      redirect_enabled: boolean;
      force_https_redirect: boolean;
    }>
  ): Promise<void> {
    const response = await this.api.getDomain(username, domain);
    const domainData = response.data;

    if (expectedConfig.document_root !== undefined && domainData.details) {
      expect(domainData.details.document_root).toContain(expectedConfig.document_root);
    }
  }
}
