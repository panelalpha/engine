export interface WordPressInstallation {
  url: string;
  wpPath: string;
  site_name: string;
  admin_username: string;
  admin_password: string;
  admin_email: string;
}

export interface WpCliCommandResult {
  exit_code: number;
  stdout: string;
  stderr: string;
}
