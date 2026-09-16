import {
  type ApiResponse,
  type CsfConfig,
  type CsfRule,
  type CsfRuleRequest,
  type CsfRules,
  type CsfUiCredentials,
} from '@/types';
import { WpCliApi } from './wpcli.api';

export class CsfApi extends WpCliApi {
  async getCsfConfig(): Promise<ApiResponse<CsfConfig>> {
    const response = await this.api.get('csf/status');
    await this.assertStatus(response, 200);
    return response.json();
  }

  /**
   * Adds a CSF rule (allow/deny)
   * Returns the created rule including its line_md5 for deletion
   */
  async addCsfRule(
    target: string,
    type: 'allow' | 'deny',
    comment?: string,
    options: Omit<CsfRuleRequest, 'target' | 'comment'> = {}
  ): Promise<ApiResponse<CsfRule>> {
    const response = await this.api.post(`csf/rules/${type}`, {
      data: { target, comment, ...options },
    });
    await this.assertStatus(response, 200);
    return response.json();
  }

  async removeCsfRule(lineMd5: string, type: 'allow' | 'deny'): Promise<void> {
    const response = await this.api.delete(`csf/rules/${type}/${lineMd5}`);
    await this.assertOk(response);
  }

  async restartCsf(): Promise<void> {
    const response = await this.api.put('csf/restart');
    await this.assertOk(response);
  }

  async getCsfRules(): Promise<ApiResponse<CsfRules>> {
    const response = await this.api.get('csf/rules');
    await this.assertStatus(response, 200);
    return response.json();
  }

  async getCsfUiCredentials(): Promise<ApiResponse<CsfUiCredentials>> {
    const response = await this.api.get('csf/ui-credentials');
    await this.assertStatus(response, 200);
    return response.json();
  }

  async editCsfRule(
    lineMd5: string,
    target: string,
    type: 'allow' | 'deny',
    comment?: string,
    options: Omit<CsfRuleRequest, 'target' | 'comment'> = {}
  ): Promise<ApiResponse<CsfRule>> {
    const response = await this.api.put(`csf/rules/${type}/${lineMd5}`, {
      data: { target, comment, ...options },
    });
    await this.assertStatus(response, 200);
    return response.json();
  }

  async disableCsf(): Promise<void> {
    const response = await this.api.put('csf/disable');
    await this.assertOk(response);
  }

  async enableCsf(): Promise<void> {
    const response = await this.api.put('csf/enable');
    await this.assertOk(response);
  }
}
