export interface SshCommandRequest {
  command: string;
  cwd?: string | null;
  timeout?: number | null;
}

export interface SshCommandResult {
  exit_code: number;
  stdout: string;
  stderr: string;
}
