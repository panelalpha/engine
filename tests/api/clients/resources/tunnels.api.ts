/* eslint-disable @typescript-eslint/no-explicit-any */

import {
  type ApiListResponse,
  type ApiResponse,
  type CreateTunnelRequest,
  type Tunnel,
} from '@/types';
import { EngineApiBase } from '../engine-api-base';

export class TunnelsApi extends EngineApiBase {
  async listTunnels(username: string, domain: string): Promise<ApiListResponse<Tunnel>> {
    const response = await this.api.get(`projects/${username}/domains/${domain}/tunnels`);
    await this.assertStatus(response, 200);
    return response.json();
  }

  async listTunnelsRaw(username: string, domain: string): Promise<{ status: number; body: any }> {
    const response = await this.api.get(`projects/${username}/domains/${domain}/tunnels`);
    return this.rawCall(response);
  }

  async createTunnel(
    username: string,
    domain: string,
    data: CreateTunnelRequest
  ): Promise<ApiResponse<Tunnel>> {
    const response = await this.api.post(`projects/${username}/domains/${domain}/tunnels`, {
      data,
    });
    await this.assertStatus(response, 201);
    return response.json();
  }

  async createTunnelRaw(
    username: string,
    domain: string,
    data: Partial<CreateTunnelRequest>
  ): Promise<{ status: number; body: any }> {
    const response = await this.api.post(`projects/${username}/domains/${domain}/tunnels`, {
      data,
    });
    return this.rawCall(response);
  }

  async deleteTunnel(
    username: string,
    domain: string,
    hostname: string
  ): Promise<{ success: boolean }> {
    const response = await this.api.delete(
      `projects/${username}/domains/${domain}/tunnels/${hostname}`
    );
    await this.assertStatus(response, 200);
    return response.json();
  }

  async deleteTunnelRaw(
    username: string,
    domain: string,
    hostname: string
  ): Promise<{ status: number; body: any }> {
    const response = await this.api.delete(
      `projects/${username}/domains/${domain}/tunnels/${hostname}`
    );
    return this.rawCall(response);
  }
}
