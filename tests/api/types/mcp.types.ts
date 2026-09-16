export interface McpToken {
  id: number;
  name: string;
  last_used_at: string | null;
  revoked_at: string | null;
  created_at: string;
  plain_text_token?: string;
}

export interface McpActivityLog {
  id: number;
  token_id: number | null;
  token_name: string;
  tool_name: string;
  input: Record<string, unknown> | null;
  status: 'success' | 'error';
  error_message: string | null;
  created_at: string;
}

export interface CreateMcpActivityLogRequest {
  token_name: string;
  tool_name: string;
  input?: Record<string, unknown> | null;
  status: 'success' | 'error';
  error_message?: string | null;
}
