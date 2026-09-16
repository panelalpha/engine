import { type z } from 'zod';

export function validateParsedApiResponse<T extends z.ZodType>(
  body: unknown,
  schema: T
): z.infer<T> {
  return schema.parse(body);
}
