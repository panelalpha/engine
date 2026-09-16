export interface DomainDetails {
  document_root: string;
  aliases?: string[];
}

export interface Domain {
  domain: string;
  type: 'main' | 'addon' | 'alias';
  details?: DomainDetails;
}

export interface CreateDomainRequest {
  domain: string;
  type: 'addon' | 'sub';
  parent_domain?: string | null;
  no_ssl?: boolean | null;
  aliases?: string[] | null;
}

export interface UpdateDomainRequest {
  document_root?: string;
  redirect_enabled?: boolean;
  force_https_redirect?: boolean;
  redirect_url?: string | null;
  aliases?: string[] | null;
}

export interface SslCertificate {
  certificate?: string;
  cabundle?: string;
  common_name: string;
  issuer_name: string;
  issuer_common_name?: string;
  not_before?: string | number;
  not_after?: string | number;
  domains?: string[];
}

export interface LogFile {
  file: string;
  path?: string;
  size?: number;
  /** Last-modified timestamp; the engine may expose it as `modified` or `mtime`. */
  modified?: string;
  mtime?: string | number;
}
