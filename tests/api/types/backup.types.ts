export type BackupDriver = 'local' | 's3' | 'ftp' | 'ftps' | 'sftp';

export interface BackupContainer {
  id: number;
  name: string;
  driver: BackupDriver;
  location: string;
  has_credentials: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface CreateBackupContainerRequest {
  name: string;
  driver: BackupDriver;
  location: string;
  credentials?: Record<string, unknown> | null;
}

export interface UpdateBackupContainerRequest {
  name?: string;
  driver?: BackupDriver;
  location?: string;
  credentials?: Record<string, unknown> | null;
}

export interface BackupRecord {
  id: number;
  username: string;
  container_id: number;
  async_status: Record<string, unknown> | null;
  error: string | null;
  size_bytes?: number;
  created_at?: string;
  updated_at?: string;
  container?: BackupContainer;
  items?: unknown[];
}

export interface CreateProjectBackupRequest {
  container: string;
}

export interface RestoreBackupRequest {
  confirm: boolean;
  only?: {
    files?: boolean;
    volumes?: string[];
    databases?: string[];
  };
  exclude?: {
    files?: boolean;
    volumes?: string[];
    databases?: string[];
  };
}
