/* eslint-disable @typescript-eslint/no-explicit-any */

import {
  type ApiResponse,
  type CreateHttpAcmeChallengeRequest,
  type HttpAcmeChallengeDetail,
  type HttpAcmeChallengeList,
} from '@/types';
import { EngineApiBase } from '../engine-api-base';

export class AcmeApi extends EngineApiBase {
  async listHttpAcmeChallenges(domain: string): Promise<ApiResponse<HttpAcmeChallengeList>> {
    const response = await this.api.get(`domains/${domain}/http-acme-challenges`);
    await this.assertStatus(response, 200);
    return response.json();
  }

  async listHttpAcmeChallengesRaw(domain: string): Promise<{ status: number; body: any }> {
    const response = await this.api.get(`domains/${domain}/http-acme-challenges`);
    return this.rawCall(response);
  }

  async getHttpAcmeChallenge(
    domain: string,
    token: string
  ): Promise<ApiResponse<HttpAcmeChallengeDetail>> {
    const response = await this.api.get(`domains/${domain}/http-acme-challenges/${token}`);
    await this.assertStatus(response, 200);
    return response.json();
  }

  async createHttpAcmeChallenge(
    domain: string,
    data: CreateHttpAcmeChallengeRequest
  ): Promise<ApiResponse<HttpAcmeChallengeDetail>> {
    const response = await this.api.post(`domains/${domain}/http-acme-challenges`, { data });
    await this.assertStatus(response, 201);
    return response.json();
  }

  async createHttpAcmeChallengeRaw(
    domain: string,
    data: CreateHttpAcmeChallengeRequest
  ): Promise<{ status: number; body: any }> {
    const response = await this.api.post(`domains/${domain}/http-acme-challenges`, { data });
    return this.rawCall(response);
  }

  async deleteHttpAcmeChallenge(domain: string, token: string): Promise<void> {
    const response = await this.api.delete(`domains/${domain}/http-acme-challenges/${token}`);
    await this.assertStatus(response, 204);
  }

  async deleteAllHttpAcmeChallenges(domain: string): Promise<void> {
    const response = await this.api.delete(`domains/${domain}/http-acme-challenges`);
    await this.assertStatus(response, 204);
  }

  async deleteAllHttpAcmeChallengesSafe(domain: string): Promise<number> {
    const response = await this.api.delete(`domains/${domain}/http-acme-challenges`);
    return response.status();
  }
}
