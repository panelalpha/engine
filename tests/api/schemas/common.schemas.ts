import { z } from 'zod';

export const apiDataEnvelopeSchema = <T extends z.ZodType>(dataSchema: T) =>
  z.object({
    data: dataSchema,
  });

export const testConnectionSchema = z.object({
  success: z.literal(true),
});

export const authErrorBodySchema = z
  .object({
    message: z.string().optional(),
    error: z.string().optional(),
  })
  .passthrough();

export function assertAuthErrorText(bodyText: string): void {
  const lower = bodyText.toLowerCase();
  if (!/unauth|token|forbidden|auth/.test(lower)) {
    throw new Error(`Expected auth-related error message, got: ${bodyText.slice(0, 200)}`);
  }
}
