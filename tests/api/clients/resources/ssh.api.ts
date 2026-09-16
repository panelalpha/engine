import { type SshCommandRequest, type SshCommandResult } from '@/types';
import { EngineApiBase } from '../engine-api-base';

export class SshApi extends EngineApiBase {
  async runSshCommand(username: string, data: SshCommandRequest): Promise<SshCommandResult> {
    const response = await this.api.post(`projects/${username}/ssh/command`, { data });
    await this.assertStatus(response, 200);
    return response.json();
  }

  async runSshCommandRaw(
    username: string,
    data: Partial<SshCommandRequest>
  ): Promise<{ status: number; body: unknown }> {
    const response = await this.api.post(`projects/${username}/ssh/command`, { data });
    return this.rawCall(response);
  }
}
