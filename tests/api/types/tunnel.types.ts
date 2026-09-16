export type TunnelProvider = 'cloudflare' | 'panelalpha';

export interface Tunnel {
  id: number;
  hostname: string;
  provider: TunnelProvider;
  domain?: string | null;
  url?: string;
  details?: Record<string, unknown>;
  created_at?: string;
  updated_at?: string;
}

export interface CreateTunnelRequest {
  hostname: string;
  provider?: TunnelProvider;
}
