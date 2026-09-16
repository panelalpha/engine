/* eslint-disable @typescript-eslint/no-explicit-any */

import { type ApiResponse, type BugReportAccepted, type CreateBugReportRequest } from '@/types';
import { EngineApiBase } from '../engine-api-base';

export class BugReportsApi extends EngineApiBase {
  async createBugReport(data: CreateBugReportRequest): Promise<ApiResponse<BugReportAccepted>> {
    const response = await this.api.post('bug-reports', { data });
    await this.assertStatus(response, 201);
    return response.json();
  }

  async createBugReportRaw(
    data: Partial<CreateBugReportRequest>
  ): Promise<{ status: number; body: any }> {
    const response = await this.api.post('bug-reports', { data });
    return this.rawCall(response);
  }
}
