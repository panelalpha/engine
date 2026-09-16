/* eslint-disable @typescript-eslint/no-explicit-any */

import {
  type AddIpSubnetRequest,
  type ApiListResponse,
  type ApiResponse,
  type AssignedIp,
  type AssignIpRequest,
  type IpSubnet,
  type IpSubnetListResponse,
  type UnassignIpRequest,
} from '@/types';
import { LighthouseApi } from './lighthouse.api';

export class IpApi extends LighthouseApi {
  async listSubnets(): Promise<IpSubnetListResponse> {
    const response = await this.api.get('ip/subnets');
    await this.assertStatus(response, 200);
    return response.json();
  }

  async listSubnetsRaw(): Promise<{ status: number; body: any }> {
    const response = await this.api.get('ip/subnets');
    return this.rawCall(response);
  }

  async addSubnet(data: AddIpSubnetRequest): Promise<ApiResponse<IpSubnet>> {
    const response = await this.api.post('ip/subnets', { data });
    await this.assertStatus(response, [200, 201]);
    return response.json();
  }

  async addSubnetRaw(data: AddIpSubnetRequest): Promise<{ status: number; body: any }> {
    const response = await this.api.post('ip/subnets', { data });
    return this.rawCall(response);
  }

  async deleteSubnet(id: number): Promise<ApiResponse<IpSubnet>> {
    const response = await this.api.delete(`ip/subnets/${id}`);
    await this.assertOk(response);
    return response.json();
  }

  async deleteSubnetRaw(id: number): Promise<{ status: number; body: any }> {
    const response = await this.api.delete(`ip/subnets/${id}`);
    return this.rawCall(response);
  }

  async listAssignedIps(): Promise<ApiListResponse<AssignedIp>> {
    const response = await this.api.get('ip/assigned');
    await this.assertStatus(response, 200);
    return response.json();
  }

  async listAssignedIpsRaw(): Promise<{ status: number; body: any }> {
    const response = await this.api.get('ip/assigned');
    return this.rawCall(response);
  }

  async assignIp(data: AssignIpRequest): Promise<ApiResponse<AssignedIp>> {
    const response = await this.api.post('ip/assign', { data });
    await this.assertStatus(response, [200, 201]);
    return response.json();
  }

  async assignIpRaw(data: AssignIpRequest): Promise<{ status: number; body: any }> {
    const response = await this.api.post('ip/assign', { data });
    return this.rawCall(response);
  }

  async unassignIp(data: UnassignIpRequest): Promise<void> {
    const response = await this.api.post('ip/unassign', { data });
    await this.assertOk(response);
  }

  async unassignIpRaw(data: UnassignIpRequest): Promise<{ status: number; body: any }> {
    const response = await this.api.post('ip/unassign', { data });
    return this.rawCall(response);
  }
}
