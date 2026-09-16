export interface ProjectSetting {
  key: string;
  value: string | null;
  set: boolean;
  secret: boolean;
}

export interface SetProjectSettingRequest {
  value: string;
}

export interface SetProjectSettingResult {
  message?: string;
  account_id?: string;
  account_name?: string;
  [key: string]: unknown;
}
