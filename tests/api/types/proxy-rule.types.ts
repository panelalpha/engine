export type ProxyRuleTransport = 'http' | 'tcp' | 'udp';

export interface ProxyRule {
  id: number;
  owner_scope: string;
  username: string | null;
  enabled: boolean;
  transport: ProxyRuleTransport;
  listen_ip: string;
  listen_port: number;
  server_name: string | null;
  upstream_host: string;
  upstream_port: number;
  upstream_protocol: string | null;
  is_generated: boolean;
  metadata: Record<string, unknown> | null;
  created_at: string;
  updated_at: string;
}

export interface CreateProxyRuleRequest {
  owner_scope?: 'system' | 'user';
  username?: string | null;
  transport: ProxyRuleTransport;
  listen_ip?: string | null;
  listen_port: number;
  server_name?: string | null;
  upstream_host: string;
  upstream_port: number;
  upstream_protocol?: string | null;
  enabled?: boolean;
  metadata?: Record<string, unknown> | null;
}

export interface UpdateProxyRuleRequest {
  upstream_host?: string | null;
  upstream_port?: number | null;
  upstream_protocol?: string | null;
  enabled?: boolean | null;
  metadata?: Record<string, unknown> | null;
}
