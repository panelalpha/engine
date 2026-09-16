/** Request body for CSF rule create/update endpoints. */
export interface CsfRuleRequest {
  target: string;
  comment?: string | null;
  protocol?: 'tcp' | 'udp' | null;
  direction?: 'in' | 'out' | null;
  port_prefix?: 's=' | 'd=' | null;
  port?: string | null;
  target_prefix?: 's=' | 'd=' | 'u=' | null;
}

/** A firewall rule returned by all CSF endpoints (list, add, edit). */
export interface CsfRule {
  line_md5: string;
  protocol?: string;
  direction?: string;
  port_prefix?: string;
  port?: string;
  target_prefix?: string;
  target: string;
  comment?: string;
}

export interface CsfConfig {
  enabled: boolean;
  testing_mode: boolean;
  version: string;
  error: string | null;
}

export interface CsfRules {
  allow: CsfRule[];
  deny: CsfRule[];
  ignore?: CsfRule[];
}

export interface CsfUiCredentials {
  username: string;
  password: string;
}
