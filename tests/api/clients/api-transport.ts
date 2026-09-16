import type { APIRequestContext, APIResponse } from '@playwright/test';

/**
 * The subset of {@link APIRequestContext} used by the Engine API resource clients.
 *
 * Kept as an interface (rather than depending on `APIRequestContext` directly) so
 * a client can be built over either the authenticated `request` fixture or an
 * anonymous context — see `fixtures/test-options.ts`.
 *
 * Requests and responses show up in the Playwright trace viewer's Network tab,
 * so no extra logging layer is needed to debug a failure.
 */
export interface ApiTransport {
  get(url: string, options?: Parameters<APIRequestContext['get']>[1]): Promise<APIResponse>;
  post(url: string, options?: Parameters<APIRequestContext['post']>[1]): Promise<APIResponse>;
  put(url: string, options?: Parameters<APIRequestContext['put']>[1]): Promise<APIResponse>;
  delete(url: string, options?: Parameters<APIRequestContext['delete']>[1]): Promise<APIResponse>;
}

export function createApiTransport(request: APIRequestContext): ApiTransport {
  return {
    get: (url, options) => request.get(url, options),
    post: (url, options) => request.post(url, options),
    put: (url, options) => request.put(url, options),
    delete: (url, options) => request.delete(url, options),
  };
}
