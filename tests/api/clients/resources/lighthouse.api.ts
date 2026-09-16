/* eslint-disable @typescript-eslint/no-explicit-any */

import {
  type ApiResponse,
  type GenerateLighthouseReportRequest,
  type LighthouseReport,
  type RawApiResponse,
} from '@/types';
import { FilesApi } from './files.api';

export class LighthouseApi extends FilesApi {
  async generateLighthouseReport(
    data: GenerateLighthouseReportRequest
  ): Promise<ApiResponse<LighthouseReport>> {
    const response = await this.api.post('lighthouse/generate-report', { data });
    await this.assertOk(response);
    return response.json();
  }

  /**
   * Generates a Lighthouse report (raw - no status expectation)
   */
  async generateLighthouseReportRaw(
    data: Partial<GenerateLighthouseReportRequest> & Record<string, any>
  ): Promise<RawApiResponse> {
    const response = await this.api.post('lighthouse/generate-report', { data });
    return this.rawCall(response);
  }
}
