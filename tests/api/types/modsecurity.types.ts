export type ModSecurityMode = 'on' | 'off' | 'detection_only';

export interface ModSecurityConfig {
  mode: ModSecurityMode;
  enabled_rulesets?: string[];
}

export interface ModSecurityRule {
  id: number;
  enabled: boolean;
  description?: string;
}

export interface ModSecurityConfigFile {
  file: string;
  enabled: boolean;
}

/** API lists files as basenames; a `.disabled` suffix means the file is off. */
export type ModSecurityConfigFileEntry = string | ModSecurityConfigFile;

export interface ModSecurityRuleset {
  name: string;
  enabled: boolean;
  config_files?: ModSecurityConfigFileEntry[];
}

export interface ModSecurityAuditLogFile {
  file: string;
  path: string;
  size?: number;
}

export interface ModSecurityAuditLogEntry {
  timestamp?: string;
  message?: string;
  [key: string]: unknown;
}
