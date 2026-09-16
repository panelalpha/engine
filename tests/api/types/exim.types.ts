export interface EximConfig {
  smarthost_provider?: string;
  sendgrid_api_token?: string;
  mailchannels_username?: string;
  mailchannels_password?: string;
  amazon_ses_smtp_endpoint?: string;
  amazon_ses_starttls_port?: string;
  amazon_ses_smtp_username?: string;
  amazon_ses_smtp_password?: string;
  smtp_host?: string;
  smtp_port?: string;
  smtp_username?: string;
  smtp_password?: string;
  smtp_implicit_tls?: boolean | string;
  sender_domain?: string;
  [key: string]: unknown;
}

export interface EximTestEmailRequest {
  email: string;
  config?: Partial<EximConfig>;
  subject?: string;
  body?: string;
}
