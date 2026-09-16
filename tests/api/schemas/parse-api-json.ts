import { type APIResponse } from '@playwright/test';
import { type z } from 'zod';

export async function parseApiJson<T extends z.ZodType>(
  response: APIResponse,
  schema: T
): Promise<z.infer<T>> {
  const json: unknown = await response.json();
  return schema.parse(json);
}

export async function safeParseApiJson<T extends z.ZodType>(
  response: APIResponse,
  schema: T
): Promise<z.ZodSafeParseResult<z.infer<T>>> {
  const json: unknown = await response.json();
  return schema.safeParse(json);
}
