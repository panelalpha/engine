import { z } from 'zod';

export const customIniSettingsResponseSchema = z.object({
  data: z.record(z.string(), z.string()),
});
