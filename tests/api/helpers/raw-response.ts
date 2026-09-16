import type { RawApiResponse } from '@/types';

export function rawResponseData<T>(response: RawApiResponse): T | undefined {
  if (typeof response.body !== 'object' || response.body === null) {
    return undefined;
  }
  const envelope = response.body as { data?: T };
  return envelope.data;
}

export function rawResponseMessage(response: RawApiResponse): string | undefined {
  if (typeof response.body !== 'object' || response.body === null) {
    return undefined;
  }
  const err = response.body as { message?: string };
  return err.message;
}

export function rawResponseBody<T>(response: RawApiResponse): T {
  return response.body as T;
}
