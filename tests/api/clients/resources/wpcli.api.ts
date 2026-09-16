import { type WpCliCommandResult } from '@/types';
import { CronApi } from './cron.api';

export class WpCliApi extends CronApi {
  async executeWpCliCommand(username: string, args: string[]): Promise<WpCliCommandResult> {
    const response = await this.api.post(`projects/${username}/wp-cli/command`, {
      data: { args },
    });
    await this.assertStatus(response, 200);
    const result = await response.json();
    return result;
  }
}
