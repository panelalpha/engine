import http from 'node:http';
import https from 'node:https';
import { URL } from 'node:url';

interface InsecureFetchInit {
  headers?: Record<string, string>;
  signal?: AbortSignal;
  redirect?: 'follow' | 'error' | 'manual';
  method?: string;
}

function abortError(signal: AbortSignal): Error {
  const reason: unknown = signal.reason;
  if (reason instanceof Error) {
    return reason;
  }
  if (typeof reason === 'string' && reason.length > 0) {
    return new Error(reason);
  }
  return new Error('Aborted');
}

function requestOnce(url: URL, init: InsecureFetchInit): Promise<Response> {
  const lib = url.protocol === 'https:' ? https : http;

  return new Promise((resolve, reject) => {
    const req = lib.request(
      url,
      {
        method: init.method ?? 'GET',
        headers: init.headers,
        ...(url.protocol === 'https:' ? { rejectUnauthorized: false } : {}),
      },
      (res) => {
        const chunks: Buffer[] = [];
        res.on('data', (chunk: Buffer) => chunks.push(chunk));
        res.on('end', () => {
          resolve(
            new Response(Buffer.concat(chunks), {
              status: res.statusCode ?? 0,
              statusText: res.statusMessage ?? '',
              headers: res.headers as Record<string, string>,
            })
          );
        });
      }
    );

    req.on('error', reject);

    if (init.signal) {
      if (init.signal.aborted) {
        req.destroy();
        reject(abortError(init.signal));
        return;
      }

      const signal = init.signal;
      const onAbort = () => {
        req.destroy();
        reject(abortError(signal));
      };
      init.signal.addEventListener('abort', onAbort, { once: true });
      req.on('close', () => init.signal?.removeEventListener('abort', onAbort));
    }

    req.end();
  });
}

/** HTTPS fetch for test servers with self-signed certificates (no global TLS env mutation). */
export async function insecureFetch(
  input: string | URL,
  init: InsecureFetchInit = {}
): Promise<Response> {
  let url = new URL(typeof input === 'string' ? input : input.toString());
  const redirect = init.redirect ?? 'follow';
  let response = await requestOnce(url, init);

  for (let hops = 0; redirect === 'follow' && hops < 5; hops++) {
    if (![301, 302, 303, 307, 308].includes(response.status)) {
      break;
    }

    const location = response.headers.get('location');
    if (!location) {
      break;
    }

    url = new URL(location, url);
    response = await requestOnce(url, {
      ...init,
      method: init.method === 'POST' && response.status === 303 ? 'GET' : init.method,
    });
  }

  return response;
}
