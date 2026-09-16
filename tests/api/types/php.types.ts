export type PhpVersion = string; // e.g., "8.1", "8.2", "7.4"

export interface PhpConfig {
  version: PhpVersion;
  memory_limit: string;
  max_execution_time: number;
  upload_max_filesize: string;
  post_max_size: string;
}
